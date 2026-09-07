import { readdir, rm, stat } from 'node:fs/promises';
import path from 'node:path';
import { logger } from './logger.js';

/**
 * Umur minimum sebuah folder kredensial boleh dianggap ditinggalkan.
 *
 * Tujuh hari, dan longgarnya disengaja. Yang dipertaruhkan di folder ini bukan
 * ruang disk melainkan kredensial WhatsApp, dan menghapus milik sesi yang masih
 * dipakai berarti nomor pelanggan harus di-scan ulang.
 */
export const UMUR_YATIM_HARI = 7;

/**
 * Membuang folder kredensial milik sesi yang sudah tidak ada.
 *
 * `RemoteAuth.disconnect()` membuang `RemoteAuth-<id>` saat sesi di-logout
 * dengan benar. Yang tidak tertangani: logout yang GAGAL — engine sedang mati
 * saat sesinya dihapus dari dashboard, jalur yang memang sengaja tetap
 * menghapus baris databasenya (lihat CLAUDE.md). Yang tertinggal adalah profil
 * Chromium 100-400 MB yang tidak dibaca siapa pun, tidak ditunjuk baris mana
 * pun, dan tidak pernah dibuang apa pun.
 *
 * KENAPA UMUR, BUKAN DAFTAR SESI DARI LARAVEL. Menanyakan "sesi mana yang masih
 * ada" lalu menghapus sisanya terdengar lebih tepat, dan jauh lebih berbahaya:
 * jawaban yang datang tidak lengkap — Laravel baru menyala, database belum siap,
 * daftar yang dipulihkan memang sudah disaring `layananHidup()` — membuat kita
 * menghapus kredensial pelanggan yang sehat. Umur tidak bisa salah dengan cara
 * itu: folder milik sesi yang benar-benar dipakai disentuh ulang setiap kali
 * sesinya dijalankan, jadi ia tidak pernah setua ini.
 *
 * Yang hilang kalau ternyata kita salah tetap kecil: `extractRemoteSession()`
 * memang selalu mengosongkan folder ini dan memulihkannya dari cadangan di
 * Laravel. Volume ini menampung berkas kerja Chromium, bukan jaring pengaman.
 *
 * @param {object} opsi
 * @param {string} opsi.dataPath  folder induk (WA_DATA_PATH)
 * @param {string[]} [opsi.kecuali]  id sesi yang sedang berjalan; tidak pernah disentuh
 * @param {number} [opsi.umurHari]
 * @returns {Promise<{dibuang: string[], dilewati: number}>}
 */
export async function sapuFolderYatim({ dataPath, kecuali = [], umurHari = UMUR_YATIM_HARI } = {}) {
    const hasil = { dibuang: [], dilewati: 0 };

    let isi;

    try {
        isi = await readdir(dataPath, { withFileTypes: true });
    } catch (error) {
        // Folder belum ada pada boot pertama. Bukan galat.
        logger.debug({ dataPath, err: error.message }, 'Folder kredensial belum bisa dibaca');

        return hasil;
    }

    const batas = Date.now() - umurHari * 24 * 60 * 60 * 1000;
    const aman = new Set(kecuali);

    for (const entri of isi) {
        if (!entri.isDirectory() || !entri.name.startsWith('RemoteAuth-')) {
            continue;
        }

        const sessionId = entri.name.slice('RemoteAuth-'.length);

        if (aman.has(sessionId)) {
            hasil.dilewati++;

            continue;
        }

        const jalur = path.join(dataPath, entri.name);

        let umur;

        try {
            umur = (await stat(jalur)).mtimeMs;
        } catch {
            continue;
        }

        if (umur >= batas) {
            hasil.dilewati++;

            continue;
        }

        try {
            await rm(jalur, { recursive: true, force: true });

            hasil.dibuang.push(sessionId);

            logger.info(
                { sessionId, jalur, umurHari },
                'Folder kredensial sesi yang sudah lama ditinggalkan dibuang',
            );
        } catch (error) {
            // Disk penuh, izin salah, berkas terkunci. Tidak satu pun alasan
            // untuk menjatuhkan boot engine.
            logger.warn({ sessionId, err: error.message }, 'Gagal membuang folder kredensial yatim');
        }
    }

    return hasil;
}
