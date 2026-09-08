import { createHash } from 'node:crypto';
import { mkdir, readdir, readFile, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { config } from '../config.js';
import { laravel } from '../laravel.js';
import { logger } from '../logger.js';

/**
 * Store kredensial Baileys yang menyinkronkan berkas autentikasi ke Laravel.
 *
 * Baileys menyimpan kredensial sebagai kumpulan berkas JSON kecil (creds.json
 * dan serangkaian berkas kunci seperti pre-key-*.json, session-*.json, dll).
 * Store ini membundel seluruh berkas di folder sesi menjadi satu payload JSON
 * terstruktur yang ditandatangani digest SHA-256 untuk disimpan di Laravel.
 */
export class LaravelStore {
    /**
     * Memeriksa apakah backup ada di Laravel.
     * Galat dilempar jika Laravel tidak terjangkau (jangan mengembalikan false
     * karena false memicu start tanpa memulihkan kredensial).
     */
    async sessionExists(sessionOrId) {
        const sessionId = toSessionId(sessionOrId);
        return laravel.backupExists(sessionId);
    }

    /**
     * Menyimpan seluruh berkas state Baileys dari folder sesi ke Laravel.
     */
    async save(sessionOrId) {
        const sessionId = toSessionId(sessionOrId);
        const sessionDir = path.join(config.dataPath, sessionId);

        let entries;
        try {
            entries = await readdir(sessionDir);
        } catch (err) {
            if (err.code === 'ENOENT') {
                logger.warn({ sessionId }, 'Folder sesi tidak ditemukan saat akan menyimpan backup');
                return;
            }
            throw err;
        }

        const files = {};
        for (const filename of entries) {
            const filePath = path.join(sessionDir, filename);
            files[filename] = await readFile(filePath, 'utf-8');
        }

        const payload = JSON.stringify({
            version: 1,
            sessionId,
            files,
        });

        const buffer = Buffer.from(payload, 'utf-8');
        const digest = createHash('sha256').update(buffer).digest('hex');

        await laravel.uploadBackup(sessionId, buffer, digest);

        logger.info({ sessionId, bytes: buffer.length, fileCount: Object.keys(files).length }, 'Backup sesi terkirim');
    }

    /**
     * Memulihkan berkas state Baileys dari backup Laravel ke folder sesi.
     */
    async extract(sessionOrId, targetPath) {
        const sessionId = toSessionId(sessionOrId);
        const destDir = targetPath ?? path.join(config.dataPath, sessionId);

        const buffer = await laravel.downloadBackup(sessionId);
        const payload = JSON.parse(buffer.toString('utf-8'));

        await mkdir(destDir, { recursive: true });

        const incomingFiles = payload.files ?? {};
        const incomingNames = new Set(Object.keys(incomingFiles));

        // Tulis semua berkas dari backup
        for (const [filename, content] of Object.entries(incomingFiles)) {
            await writeFile(path.join(destDir, filename), content, 'utf-8');
        }

        // Bersihkan berkas lokal yang sudah tidak ada di backup
        try {
            const existing = await readdir(destDir);
            for (const file of existing) {
                if (!incomingNames.has(file)) {
                    await rm(path.join(destDir, file), { force: true });
                }
            }
        } catch {
            // Abaikan jika pembersihan sisa gagal
        }

        logger.info({ sessionId, bytes: buffer.length, fileCount: incomingNames.size }, 'Backup sesi dipulihkan');
    }

    /**
     * Menghapus backup di Laravel dan folder lokal sesi.
     */
    async delete(sessionOrId) {
        const sessionId = toSessionId(sessionOrId);
        await laravel.deleteBackup(sessionId);

        const sessionDir = path.join(config.dataPath, sessionId);
        try {
            await rm(sessionDir, { recursive: true, force: true });
        } catch {
            // Abaikan jika folder sudah tidak ada
        }
    }
}

function toSessionId(session) {
    if (!session) return '';
    if (typeof session === 'object') {
        const raw = session.sessionId ?? session.session ?? '';
        return String(raw).replace(/^RemoteAuth-/, '');
    }
    return String(session).replace(/^RemoteAuth-/, '');
}
