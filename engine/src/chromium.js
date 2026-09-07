import { readdirSync } from 'node:fs';
import { logger } from './logger.js';
import { petaProfil } from './profil.js';

/**
 * Batas waktu `client.destroy()`.
 *
 * `browser.close()` menunggu proses Chromium benar-benar keluar, dan proses
 * yang sedang tersangkut menunggu I/O — persis keadaan saat memori menipis dan
 * swap penuh — bisa tidak pernah keluar. Tanpa batas, `stop()` menggantung
 * selamanya di dalam handler event `disconnected`, tempat tidak ada satu pun
 * pemanggil yang menunggunya dan tidak ada satu pun galat yang muncul.
 */
export const BATAS_TUTUP_MS = 10_000;

/**
 * Menutup Chromium milik sebuah client, dan MEMASTIKAN prosesnya mati.
 *
 * Kenapa ini tidak cukup dengan `await client.destroy()`:
 *
 * ```js
 * // whatsapp-web.js/src/Client.js:1277-1284
 * async destroy() {
 *     const browser = this.pupBrowser;
 *     const isConnected = browser?.isConnected?.();
 *     if (isConnected) { await browser.close(); }   // ← DILEWATI
 *     await this.authStrategy.destroy();            // ← cuma clearInterval()
 * }
 * ```
 *
 * `isConnected()` mengukur websocket CDP, bukan proses sistem operasi. Kalau
 * websocket-nya putus sementara Chromium masih hidup — yang justru paling
 * sering terjadi saat memori menipis dan browser berhenti menjawab —
 * `browser.close()` dilewati, `authStrategy.destroy()` hanya menghentikan timer
 * backup, dan fungsi ini **kembali sukses**. Tidak ada galat, tidak ada log,
 * tidak ada apa pun yang bisa dilihat. Yang tertinggal Chromium ±400 MB tanpa
 * satu pun referensi yang memegangnya.
 *
 * Karena itu pembunuhan paksanya ada di `finally`, bukan di `catch`: jalur
 * yang bocor adalah jalur SUKSES, jadi menaruhnya di `catch` tidak menutup
 * apa pun. Yang dibunuh hanya proses yang memang masih hidup — `exitCode` dan
 * `signalCode` dua-duanya masih null — sehingga penutupan yang benar-benar
 * rapi tidak menghasilkan sinyal tambahan.
 *
 * @returns {Promise<{rapi: boolean, dibunuh: boolean, err: string|null}>}
 */
export async function tutupChromium(client, sessionId, { batasMs = BATAS_TUTUP_MS } = {}) {
    const hasil = { rapi: false, dibunuh: false, err: null };

    let timer;

    try {
        await Promise.race([
            client.destroy(),
            new Promise((_, reject) => {
                timer = setTimeout(
                    () => reject(new Error(`destroy() tidak selesai dalam ${batasMs} ms`)),
                    batasMs,
                );
            }),
        ]);

        hasil.rapi = true;
    } catch (error) {
        hasil.err = error.message;

        logger.warn({ sessionId, err: error.message }, 'Penutupan client tidak rapi');
    } finally {
        clearTimeout(timer);

        hasil.dibunuh = bunuhSisa(client, sessionId);
    }

    return hasil;
}

/**
 * Membunuh proses Chromium yang masih hidup setelah destroy().
 *
 * `browser.process()` mengembalikan `#process ?? null` dan nilainya TIDAK
 * pernah dikosongkan setelah launch, jadi ia tetap terjangkau justru pada
 * keadaan yang bikin `isConnected()` palsu.
 *
 * Seluruhnya defensif: sebuah client yang gagal sebelum browsernya sempat
 * lahir tidak punya `pupBrowser` sama sekali, dan membunuh pid yang sudah mati
 * melempar ESRCH. Tidak satu pun dari keduanya boleh menjatuhkan penutupan.
 */
function bunuhSisa(client, sessionId) {
    let proses;

    try {
        proses = client?.pupBrowser?.process?.();
    } catch {
        return false;
    }

    if (!proses || proses.exitCode !== null || proses.signalCode !== null) {
        return false;
    }

    try {
        proses.kill('SIGKILL');

        logger.warn(
            { sessionId, pid: proses.pid },
            'Chromium masih hidup setelah destroy(); dimatikan paksa',
        );

        return true;
    } catch (error) {
        logger.error(
            { sessionId, pid: proses.pid, err: error.message },
            'Chromium tidak bisa dimatikan paksa; prosesnya jadi yatim',
        );

        return false;
    }
}

/**
 * Keadaan Chromium menurut sistem operasi, bukan menurut Map di memori engine.
 *
 * KENAPA PER PROFIL, BUKAN TOTAL PROSES. Versi pertama fungsi ini menghitung
 * total proses dan membandingkannya dengan jumlah sesi. Angka itu benar tapi
 * TUMPUL: produksi 8 September 2026 menunjukkan 3 profil dengan 6 proses, dan
 * yang merusak bukan "ada 3 proses berlebih" melainkan "satu profil dipegang
 * empat proses sekaligus". Dua Chromium pada satu `userDataDir` saling menimpa
 * LevelDB milik WhatsApp Web; gejalanya `Execution context was destroyed` di
 * layar pelanggan, bukan kehabisan memori.
 *
 * Perekam kapasitas sudah menangkapnya satu jam sebelum ada yang mengeluh —
 * proses 33 naik ke 66 sementara profil tetap 3. Selisih itulah sinyal paling
 * tajam yang kita punya, dan fungsi ini menghitungnya persis.
 *
 * @param {string[]} sesiAktif  id sesi yang menurut engine sedang berjalan
 * @returns {{
 *   proses: number, profil: number, duplikat: number, yatim: number,
 *   bocor: number, rincian: Array<{profil: string, pid: number[]}>
 * } | null} null kalau /proc tidak ada
 */
export function keadaanChromium(sesiAktif = [], { proc = '/proc' } = {}) {
    let peta;

    try {
        peta = petaProfil({ proc });
    } catch {
        return null;
    }

    // /proc yang tidak ada menghasilkan Map kosong dari petaProfil, dan itu
    // tidak bisa dibedakan dari "tidak ada Chromium sama sekali". Dibedakan di
    // sini: nol berarti "sudah diperiksa dan aman", dan itu kebohongan untuk
    // sesuatu yang tidak pernah bisa diperiksa.
    try {
        readdirSync(proc);
    } catch {
        return null;
    }

    const aktif = new Set(sesiAktif);

    let proses = 0;
    let duplikat = 0;
    let yatim = 0;

    const rincian = [];

    for (const [profil, pids] of peta) {
        proses += pids.length;

        // Satu profil boleh dipegang SATU proses browser. Sisanya kelebihan.
        if (pids.length > 1) duplikat += pids.length - 1;

        const sessionId = profil.slice(profil.lastIndexOf('RemoteAuth-') + 'RemoteAuth-'.length);

        if (!aktif.has(sessionId)) yatim++;

        rincian.push({ profil, pid: pids });
    }

    return {
        proses,
        profil: peta.size,
        duplikat,
        yatim,
        // Dua bentuk kebocoran yang berbeda, dijumlahkan karena keduanya sama-
        // sama berarti "ada Chromium yang seharusnya sudah tidak ada".
        bocor: duplikat + yatim,
        rincian,
    };
}

/**
 * Berapa proses Chromium milik kita yang hidup. Dipertahankan sebagai angka
 * mentah untuk /health; yang punya arti diagnostik adalah `duplikat`.
 *
 * @returns {number|null} null kalau /proc tidak ada — bukan 0.
 */
export function hitungChromiumHidup({ proc = '/proc' } = {}) {
    const keadaan = keadaanChromium([], { proc });

    return keadaan === null ? null : keadaan.proses;
}
