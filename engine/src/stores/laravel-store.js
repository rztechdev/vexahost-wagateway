import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { config } from '../config.js';
import { laravel } from '../laravel.js';
import { logger } from '../logger.js';

/**
 * Store RemoteAuth yang menyimpan kredensial sesi di Laravel.
 *
 * whatsapp-web.js sudah menangani zip/unzip-nya sendiri; store hanya perlu
 * memindahkan file zip itu ke dan dari tempat penyimpanan. Kontrak yang wajib
 * dipenuhi: sessionExists, save, extract, delete.
 *
 * Store inilah satu-satunya yang menentukan sesi selamat atau tidak dari
 * redeploy — bukan persistent volume. RemoteAuth SELALU menghapus isi
 * userDataDir di volume setiap kali sesi dijalankan (lihat
 * `extractRemoteSession()` di RemoteAuth.js), lalu memulihkannya dari store.
 * Kalau store bilang tidak ada backup, yang tersisa adalah folder kosong dan
 * WhatsApp meminta scan QR lagi. Volume hanya menampung file kerja Chromium di
 * antara dua restart, bukan jaring pengaman.
 */
export class LaravelStore {
    /**
     * Kegagalan sengaja dilempar, bukan dijawab "tidak ada".
     *
     * RemoteAuth memanggil ini sebelum menghapus userDataDir. Menjawab false
     * saat Laravel sekadar tidak terjangkau membuat kredensial yang masih
     * sempurna ikut terhapus, dan nomor harus di-scan ulang gara-gara gangguan
     * jaringan sesaat. Melempar galat membatalkan seluruh proses start sebelum
     * penghapusan itu terjadi, jadi kredensialnya utuh untuk percobaan
     * berikutnya.
     */
    async sessionExists({ session }) {
        return laravel.backupExists(toSessionId(session));
    }

    async save({ session }) {
        const sessionId = toSessionId(session);

        // RemoteAuth menulis zip-nya ke `<dataPath>/<session>.zip`
        // (`compressSession()` memakai path.join(this.dataPath, ...)), bukan ke
        // working directory proses. Membacanya sebagai path relatif dulu selalu
        // berakhir ENOENT, sehingga TIDAK PERNAH ada satu pun backup yang
        // terkirim — dan setiap redeploy menghapus kredensial lalu meminta scan
        // QR ulang, persis masalah yang store ini dibuat untuk menghilangkan.
        const zipPath = path.join(config.dataPath, `${session}.zip`);
        const buffer = await readFile(zipPath);
        const digest = createHash('sha256').update(buffer).digest('hex');

        await laravel.uploadBackup(sessionId, buffer, digest);

        logger.info({ sessionId, bytes: buffer.length }, 'Backup sesi terkirim');
    }

    async extract({ session, path }) {
        const sessionId = toSessionId(session);
        const buffer = await laravel.downloadBackup(sessionId);

        await writeFile(path, buffer);

        logger.info({ sessionId, bytes: buffer.length }, 'Backup sesi dipulihkan');
    }

    async delete({ session }) {
        await laravel.deleteBackup(toSessionId(session));
    }
}

/**
 * RemoteAuth memanggil store dengan nama sesi berformat `RemoteAuth-<clientId>`.
 * Yang dipakai sebagai kunci penyimpanan hanya clientId-nya, karena itulah id
 * baris wa_sessions di Laravel.
 */
function toSessionId(session) {
    return session.replace(/^RemoteAuth-/, '');
}
