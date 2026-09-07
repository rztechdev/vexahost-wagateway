import { readFileSync, readdirSync } from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { logger } from './logger.js';

/**
 * Satu profil, satu Chromium. Ditegakkan di sini.
 *
 * KENAPA MODUL INI ADA — kejadian produksi 8 September 2026.
 *
 * Seluruh penjagaan sebelumnya menangani satu bentuk kegagalan: "proses tidak
 * mati". Tidak satu pun menangani bentuk yang sebenarnya merusak: **proses baru
 * dinyalakan sementara yang lama masih memegang folder profil yang sama**.
 *
 * Yang terekam di server:
 *
 *   profil 01m005d2wdwz:  pid 5314 (293 mnt), 583075 (88 mnt),
 *                         585979 (87 mnt), 595440 (83 mnt)
 *   profil 01m1kk0r6fsx:  pid 5313   -- sehat
 *   profil 01m005mg3set:  pid 5316   -- sehat
 *
 * Tiga profil, enam proses, seluruhnya anak dari satu proses Node yang sama.
 * Empat Chromium berebut satu `userDataDir`.
 *
 * Gejalanya BUKAN kehabisan memori, dan itu yang membuatnya salah didiagnosis:
 * `Protocol error (Runtime.callFunctionOn): Execution context was destroyed`
 * di layar pelanggan yang sedang men-scan QR. Dua Chromium pada satu profil
 * saling menimpa LevelDB dan Local Storage milik WhatsApp Web; yang kalah
 * kehilangan konteks eksekusinya di tengah jalan. Kehabisan memori datang
 * belakangan sebagai akibat, bukan sebab.
 *
 * KENAPA TIDAK CUKUP MEMERIKSA `#sessions`. Map itu justru KOSONG di kasus yang
 * bermasalah: `initialize()` yang gagal menghapus entry-nya, lalu `destroy()`
 * mengembalikan sukses tanpa menutup apa pun (Client.js:1277-1284). Sesudah itu
 * tidak ada satu pun struktur data di dalam Node yang tahu prosesnya masih
 * hidup. Satu-satunya yang tahu adalah sistem operasi, jadi ke sanalah kita
 * bertanya.
 */

/** Berapa lama menunggu SIGTERM sebelum naik ke SIGKILL. */
export const JEDA_TERM_MS = 3000;

/** Berapa lama menunggu kepastian proses benar-benar hilang setelah SIGKILL. */
export const JEDA_KILL_MS = 5000;

/**
 * Nama folder profil untuk sebuah sesi. Formatnya ditentukan RemoteAuth
 * (`RemoteAuth-<clientId>`), bukan oleh kita.
 */
export function jalurProfil(dataPath, sessionId) {
    return path.join(dataPath, `RemoteAuth-${sessionId}`);
}

/**
 * Membaca seluruh proses Chromium beserta profil yang dipegangnya.
 *
 * Hanya proses BROWSER yang punya `--user-data-dir`; renderer dan utility tidak.
 * Itu justru yang diinginkan: yang harus satu-per-sesi adalah browsernya.
 *
 * @returns {Map<string, number[]>} jalur profil -> daftar pid, urut dari yang tertua
 */
export function petaProfil({ proc = '/proc' } = {}) {
    const peta = new Map();

    let pids;

    try {
        pids = readdirSync(proc);
    } catch {
        return peta;
    }

    for (const pid of pids) {
        if (!/^\d+$/.test(pid)) continue;

        let mentah;

        try {
            mentah = readFileSync(`${proc}/${pid}/cmdline`, 'utf8');
        } catch {
            // Proses yang keluar di antara readdir dan readFile. Wajar.
            continue;
        }

        // /proc/<pid>/cmdline memisahkan argumen dengan NUL. Dipecah apa adanya
        // supaya pencocokannya per ARGUMEN UTUH — bukan substring. Tanpa itu,
        // profil `RemoteAuth-abc` cocok dengan `RemoteAuth-abcdef`, dan
        // membebaskan satu profil akan membunuh sesi pelanggan lain.
        const argumen = mentah.split('\0');
        const arg = argumen.find((a) => a.startsWith('--user-data-dir='));

        if (!arg) continue;

        const profil = arg.slice('--user-data-dir='.length);

        if (!path.basename(profil).startsWith('RemoteAuth-')) continue;

        if (!peta.has(profil)) peta.set(profil, []);

        peta.get(profil).push(Number(pid));
    }

    for (const daftar of peta.values()) {
        daftar.sort((a, b) => a - b);
    }

    return peta;
}

/** Pid yang sedang memegang satu profil tertentu. */
export function pemegangProfil(profil, { proc = '/proc' } = {}) {
    return petaProfil({ proc }).get(profil) ?? [];
}

const tidur = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

function masihHidup(pid, kill = process.kill) {
    try {
        // Sinyal 0 tidak mengirim apa pun; ia hanya menanyakan apakah pid ada.
        kill(pid, 0);

        return true;
    } catch {
        return false;
    }
}

/**
 * Memastikan tidak ada proses lain yang memegang profil ini, lalu menunggu
 * sampai benar-benar hilang.
 *
 * SIGTERM dulu, SIGKILL kalau perlu. Bukan kesopanan: Chromium yang diberi
 * SIGTERM menutup LevelDB-nya dengan rapi, sementara SIGKILL meninggalkan
 * kunci dan berkas setengah tertulis di profil yang sebentar lagi akan dipakai
 * proses berikutnya — persis kerusakan yang fungsi ini ada untuk mencegahnya.
 *
 * Menunggu sampai benar-benar hilang adalah bagian yang tidak boleh
 * disederhanakan. Mengirim sinyal lalu langsung menyalakan yang baru
 * menghasilkan keadaan yang sama dengan yang sedang diperbaiki, cuma dengan
 * jendela yang lebih sempit.
 *
 * @returns {Promise<{dibunuh: number[], bandel: number[]}>}
 */
export async function bebaskanProfil(
    profil,
    { proc = '/proc', jedaTermMs = JEDA_TERM_MS, jedaKillMs = JEDA_KILL_MS, kill = process.kill } = {},
) {
    const hasil = { dibunuh: [], bandel: [] };

    const pids = pemegangProfil(profil, { proc });

    if (pids.length === 0) return hasil;

    logger.warn(
        { profil, pids },
        'Profil masih dipegang proses lain sebelum sesi dijalankan; dibebaskan lebih dulu',
    );

    for (const pid of pids) {
        try {
            kill(pid, 'SIGTERM');
        } catch {
            // Sudah mati di antara pembacaan dan pengiriman sinyal.
        }
    }

    const batasTerm = Date.now() + jedaTermMs;

    while (Date.now() < batasTerm && pids.some((pid) => masihHidup(pid, kill))) {
        await tidur(100);
    }

    const bandel = pids.filter((pid) => masihHidup(pid, kill));

    for (const pid of bandel) {
        try {
            kill(pid, 'SIGKILL');
        } catch {
            // idem
        }
    }

    const batasKill = Date.now() + jedaKillMs;

    while (Date.now() < batasKill && bandel.some((pid) => masihHidup(pid, kill))) {
        await tidur(100);
    }

    hasil.bandel = bandel.filter((pid) => masihHidup(pid, kill));
    hasil.dibunuh = pids.filter((pid) => !hasil.bandel.includes(pid));

    if (hasil.bandel.length > 0) {
        // Menyalakan Chromium baru di atas profil yang masih dipegang proses
        // yang menolak mati akan mengulang kerusakan yang sama. Pemanggil harus
        // membatalkan, bukan melanjutkan.
        logger.error(
            { profil, bandel: hasil.bandel },
            'Ada proses yang tidak bisa dimatikan; profil tidak aman dipakai',
        );
    }

    return hasil;
}
