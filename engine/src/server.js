import express from 'express';
import process from 'node:process';
import { config } from './config.js';
import { logger } from './logger.js';
import { SessionManager } from './session-manager.js';

const app = express();
const manager = new SessionManager();

// Lampiran dikirim sebagai base64 di dalam JSON, jadi batas body harus lebih
// besar dari batas lampiran WhatsApp (±16 MB) ditambah overhead base64.
app.use(express.json({ limit: '32mb' }));

/**
 * Seluruh endpoint dijaga token statis. Engine tidak punya domain publik di
 * Coolify, tapi jaringan internal tetap bukan batas keamanan yang cukup:
 * siapa pun yang bisa menjangkau port ini bisa mengirim WhatsApp atas nama
 * semua tenant.
 */
app.use((req, res, next) => {
    if (req.path === '/health') return next();

    if (req.get('X-Engine-Token') !== config.engineToken) {
        return res.status(401).json({ error: 'Token engine tidak valid.' });
    }

    next();
});

app.get('/health', (req, res) => {
    res.json({
        status: 'ok',
        sessions: manager.listSessionIds().length,
        max_sessions: config.maxSessions,
        uptime_seconds: Math.round(process.uptime()),
    });
});

app.post('/sessions/:id/start', async (req, res) => {
    try {
        await manager.start(req.params.id);

        res.json({ status: 'starting' });
    } catch (error) {
        logger.error({ sessionId: req.params.id, err: error.message }, 'Gagal menjalankan sesi');

        res.status(409).json({ error: error.message });
    }
});

app.post('/sessions/:id/stop', async (req, res) => {
    await manager.stop(req.params.id);

    res.json({ status: 'stopped' });
});

app.post('/sessions/:id/logout', async (req, res) => {
    await manager.logout(req.params.id);

    res.json({ status: 'logged_out' });
});

app.get('/sessions/:id/status', (req, res) => {
    const status = manager.status(req.params.id);

    if (!status) {
        return res.status(404).json({ status: 'disconnected' });
    }

    res.json(status);
});

app.post('/sessions/:id/messages', async (req, res) => {
    const { to, type, body, media, message_id: messageId } = req.body ?? {};

    if (!to) {
        return res.status(422).json({ error: 'Nomor tujuan wajib diisi.' });
    }

    if (!body && !media) {
        return res.status(422).json({ error: 'Pesan harus punya isi atau lampiran.' });
    }

    try {
        const result = await manager.send(req.params.id, { to, type, body, media, messageId });

        res.json(result);
    } catch (error) {
        // 422 memberi tahu Laravel bahwa mengulang tidak akan menolong —
        // nomor tidak terdaftar, lampiran ditolak. Selain itu dianggap
        // sementara dan boleh dicoba lagi.
        const status = error.permanent ? 422 : 503;

        logger.warn({ sessionId: req.params.id, err: error.message }, 'Pengiriman gagal');

        res.status(status).json({ error: error.message });
    }
});

const server = app.listen(config.port, config.host, async () => {
    logger.info({ port: config.port, dataPath: config.dataPath }, 'Engine WhatsApp berjalan');

    await manager.bootstrap();
});

/**
 * Coolify mengirim SIGTERM saat redeploy.
 *
 * Kredensial disimpan dulu ke store, baru client-nya ditutup. Urutannya
 * penting: `client.destroy()` tidak menyimpan apa pun pada RemoteAuth, ia cuma
 * menghentikan timer backup — jadi menutup lebih dulu berarti membuang semua
 * perubahan sejak backup berkala terakhir.
 *
 * Penyimpanan dibatasi waktu. Docker hanya memberi jeda beberapa detik sebelum
 * SIGKILL, dan menggantung sampai dipaksa mati justru melewatkan penutupan
 * client yang rapi — lebih baik kehilangan satu siklus backup daripada
 * kehilangan keduanya.
 */
async function shutdown(signal) {
    logger.info({ signal }, 'Mematikan engine');

    server.close();

    const ids = manager.listSessionIds();

    await Promise.all(
        ids.map(async (id) => {
            try {
                await Promise.race([
                    manager.persist(id),
                    new Promise((_, reject) =>
                        setTimeout(() => reject(new Error('habis waktu')), 8000),
                    ),
                ]);
            } catch (error) {
                logger.warn({ sessionId: id, err: error.message }, 'Gagal menyimpan kredensial sesi saat berhenti');
            }

            await manager.stop(id);
        }),
    );

    process.exit(0);
}

process.on('SIGTERM', () => shutdown('SIGTERM'));
process.on('SIGINT', () => shutdown('SIGINT'));

process.on('unhandledRejection', (reason) => {
    logger.error({ err: String(reason) }, 'Promise tidak tertangani');
});
