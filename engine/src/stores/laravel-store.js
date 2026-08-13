import { createHash } from 'node:crypto';
import { readFile, writeFile } from 'node:fs/promises';
import { laravel } from '../laravel.js';
import { logger } from '../logger.js';

/**
 * Store RemoteAuth yang menyimpan kredensial sesi di Laravel.
 *
 * whatsapp-web.js sudah menangani zip/unzip-nya sendiri; store hanya perlu
 * memindahkan file zip itu ke dan dari tempat penyimpanan. Kontrak yang wajib
 * dipenuhi: sessionExists, save, extract, delete.
 *
 * Ini pasangan dari persistent volume: volume menangani kasus umum (redeploy
 * biasa), store menangani kasus volume ikut hilang — server diganti, volume
 * terhapus, atau engine dipindah ke host lain.
 */
export class LaravelStore {
    async sessionExists({ session }) {
        try {
            return await laravel.backupExists(toSessionId(session));
        } catch (error) {
            logger.warn({ session, err: error.message }, 'Gagal memeriksa backup sesi');

            // Menjawab "tidak ada" saat Laravel tidak bisa dihubungi lebih aman
            // daripada "ada": RemoteAuth akan memunculkan QR baru, bukan
            // menimpa kredensial yang masih valid di volume dengan file kosong.
            return false;
        }
    }

    async save({ session }) {
        const sessionId = toSessionId(session);

        // RemoteAuth menaruh zip hasil kompresinya di `<session>.zip` relatif
        // terhadap working directory proses.
        const buffer = await readFile(`${session}.zip`);
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
