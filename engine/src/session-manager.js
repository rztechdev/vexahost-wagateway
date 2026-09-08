import { existsSync } from 'node:fs';
import { mkdir, rm, writeFile } from 'node:fs/promises';
import path from 'node:path';
import makeWASocket, {
    Browsers,
    DisconnectReason,
    getContentType,
    jidNormalizedUser,
    useMultiFileAuthState,
    WAMessageStatus,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import qrcode from 'qrcode';
import { config } from './config.js';
import { laravel } from './laravel.js';
import { logger } from './logger.js';
import { SendQueue } from './queue.js';
import { LaravelStore } from './stores/laravel-store.js';

export class SessionManager {
    #sessions = new Map();
    #starting = new Set();
    #queue = new SendQueue();
    #store;

    /**
     * Hasil pengiriman per message_id, untuk menangkal kiriman ganda.
     *
     * Laravel mengulang job-nya kalau permintaan HTTP-nya habis waktu — padahal
     * habis waktu bukan berarti pesannya tidak terkirim. Antrean anti-ban di
     * engine menahan tiap pesan beberapa detik sebelum benar-benar dikirim, jadi
     * pesan berikutnya dalam satu giliran bisa menunggu lebih lama daripada batas
     * waktu HTTP Laravel.
     *
     * Kunci map ini message_id milik Laravel, jadi percobaan ulang menerima
     * hasil kiriman pertama alih-alih mengirim ulang. Nilainya Promise, bukan
     * hasil jadi — percobaan ulang yang datang saat kiriman pertama masih
     * berjalan ikut menunggu promise yang sama.
     */
    #kiriman = new Map();

    /**
     * Pabrik socket, bisa diganti saat pengujian unit.
     */
    #buatSocket;

    constructor({ buatSocket, store } = {}) {
        this.#store = store ?? new LaravelStore();
        this.#buatSocket = buatSocket ?? ((sessionId, options) => makeWASocket(options));
    }

    /**
     * Menjalankan ulang seluruh sesi yang tercatat di Laravel.
     *
     * Inilah yang membuat redeploy tidak lagi memaksa scan QR: daftar sesi ada
     * di database, kredensialnya ada di persistent volume (dengan cadangan di
     * Laravel), jadi engine yang baru lahir bisa memulihkan keduanya sendiri.
     */
    async bootstrap() {
        await mkdir(config.dataPath, { recursive: true });

        let sessions;

        try {
            sessions = await this.#ambilDaftarSesi();
        } catch (error) {
            logger.error({ err: error.message }, 'Bootstrap gagal; engine tetap jalan tanpa sesi');

            return;
        }

        logger.info({ count: sessions.length }, 'Memulihkan sesi tersimpan');

        for (const { session_id: sessionId, name } of sessions) {
            try {
                await this.start(sessionId, { fromBootstrap: true });
                logger.info({ sessionId, name }, 'Sesi dipulihkan');
            } catch (error) {
                // Satu sesi rusak tidak boleh menahan pemulihan sesi lainnya.
                logger.error({ sessionId, err: error.message }, 'Gagal memulihkan sesi');
            }
        }
    }

    /**
     * Menanyakan daftar sesi ke Laravel, dengan percobaan ulang.
     */
    async #ambilDaftarSesi() {
        const percobaan = 5;

        for (let ke = 1; ke <= percobaan; ke++) {
            try {
                return await laravel.bootstrap();
            } catch (error) {
                if (ke === percobaan) throw error;

                logger.warn(
                    { percobaan: ke, err: error.message },
                    'Bootstrap belum berhasil, mencoba lagi dalam 5 detik',
                );

                await new Promise((resolve) => setTimeout(resolve, 5000));
            }
        }
    }

    async start(sessionId, { has_backup, backup_data, fromBootstrap } = {}) {
        if (this.#sessions.has(sessionId)) {
            return this.#sessions.get(sessionId);
        }

        if (this.#starting.has(sessionId)) {
            throw new Error('Sesi sedang dalam proses dijalankan.');
        }

        if (this.#sessions.size >= config.maxSessions) {
            throw new Error(
                `Engine sudah menjalankan ${config.maxSessions} sesi (batas WA_MAX_SESSIONS).`
            );
        }

        this.#starting.add(sessionId);

        try {
            const sessionDir = path.join(config.dataPath, sessionId);
            await mkdir(sessionDir, { recursive: true });

            // Jika folder kredensial lokal belum memiliki creds.json, pulihkan dari backup bila ada.
            const credsPath = path.join(sessionDir, 'creds.json');
            if (!existsSync(credsPath)) {
                if (backup_data) {
                    try {
                        const payload = typeof backup_data === 'string' ? JSON.parse(backup_data) : backup_data;
                        for (const [filename, content] of Object.entries(payload.files ?? {})) {
                            await writeFile(path.join(sessionDir, filename), content, 'utf-8');
                        }
                        logger.info({ sessionId }, 'Kredensial sesi dipulihkan dari payload start');
                    } catch (err) {
                        logger.warn({ sessionId, err: err.message }, 'Gagal memulihkan backup_data dari payload start');
                    }
                } else if (fromBootstrap === true) {
                    // Hanya saat engine booting (bukan saat melayani request HTTP dari Laravel)
                    // boleh memanggil store.sessionExists / extract ke Laravel.
                    try {
                        const hasRemote = await this.#store.sessionExists({ session: sessionId });
                        if (hasRemote) {
                            await this.#store.extract({ session: sessionId });
                            logger.info({ sessionId }, 'Kredensial sesi dipulihkan dari Laravel');
                        }
                    } catch (err) {
                        logger.warn({ sessionId, err: err.message }, 'Gagal memeriksa atau memulihkan backup remote');
                    }
                }
            }

            const entry = {
                sock: null,
                status: 'connecting',
                phoneNumber: null,
                pushName: null,
                loadingPercent: null,
                pengukurInit: null,
                backupTimer: null,
                reconnectAttempts: 0,
                reconnectTimer: null,
                sessionDir,
                stopped: false,
                qrImage: null,
            };

            this.#sessions.set(sessionId, entry);
            this.#pasangPengukurInit(sessionId, entry);

            await this.#sambungSocket(sessionId, entry);

            return entry;
        } catch (error) {
            const entry = this.#sessions.get(sessionId);
            if (entry) {
                entry.status = 'failed';
                this.#lepasPengukurInit(entry);
                this.#sessions.delete(sessionId);
            }
            throw error;
        } finally {
            this.#starting.delete(sessionId);
        }
    }

    async #sambungSocket(sessionId, entry) {
        if (entry.stopped || !this.#sessions.has(sessionId)) return;

        const { state, saveCreds } = await useMultiFileAuthState(entry.sessionDir);

        const socketOptions = {
            auth: state,
            version: config.waWebVersion,
            printQRInTerminal: false,
            logger: pino({ level: 'silent' }),
            browser: Browsers.ubuntu('Chrome'),
            syncFullHistory: false,
            markOnlineOnConnect: false,
            generateHighQualityLinkPreview: false,
        };

        const sock = this.#buatSocket(sessionId, socketOptions);
        entry.sock = sock;

        this.#bindEvents(sessionId, entry, sock, saveCreds);
    }

    #bindEvents(sessionId, entry, sock, saveCreds) {
        if (typeof sock.ev?.on !== 'function') return;

        sock.ev.on('creds.update', async () => {
            if (typeof saveCreds === 'function') {
                try {
                    await saveCreds();
                } catch (err) {
                    logger.error({ sessionId, err: err.message }, 'Gagal menyimpan pembaruan creds ke disk');
                }
            }
        });

        sock.ev.on('connection.update', async (update) => {
            if (entry.stopped || this.#sessions.get(sessionId) !== entry) return;

            const { connection, lastDisconnect, qr } = update;

            if (qr) {
                entry.status = 'qr';
                this.#lepasPengukurInit(entry);

                try {
                    const image = await qrcode.toDataURL(qr, { width: 320, margin: 1 });
                    entry.qrImage = image;
                    laravel.event(sessionId, 'qr', { qr_image: image }).catch((err) => {
                        logger.warn({ sessionId, err: err.message }, 'Gagal memancarkan event QR ke Laravel');
                    });
                } catch (err) {
                    logger.error({ sessionId, err: err.message }, 'Gagal mengonversi QR ke data URL');
                }
            }

            if (connection === 'open') {
                const rawId = sock.user?.id || '';
                const phoneNumber = rawId.split(':')[0].replace(/\D+/g, '') || null;
                const pushName = sock.user?.name || null;

                entry.status = 'connected';
                entry.phoneNumber = phoneNumber;
                entry.pushName = pushName;
                entry.loadingPercent = null;
                entry.reconnectAttempts = 0;
                entry.qrImage = null;

                this.#lepasPengukurInit(entry);
                this.#pasangBackupInterval(sessionId, entry);

                logger.info({ sessionId, phoneNumber, pushName }, 'Sesi siap');

                await laravel.event(sessionId, 'ready', {
                    phone_number: phoneNumber,
                    push_name: pushName,
                });

                // Simpan backup awal ke Laravel saat berhasil terkoneksi
                this.persist(sessionId).catch((err) => {
                    logger.warn({ sessionId, err: err.message }, 'Gagal menyimpan backup awal ke Laravel');
                });
            }

            if (connection === 'close') {
                entry.qrImage = null;
                this.#lepasBackupInterval(entry);

                const statusCode = lastDisconnect?.error?.output?.statusCode;
                const isLoggedOut = statusCode === DisconnectReason.loggedOut;
                const shouldRestart = statusCode === DisconnectReason.restartRequired;

                logger.warn({ sessionId, statusCode }, 'Koneksi websocket terputus');

                if (isLoggedOut) {
                    entry.status = 'disconnected';
                    this.#lepasPengukurInit(entry);
                    logger.warn({ sessionId }, 'Sesi dikeluarkan oleh WhatsApp (logged out)');
                    await laravel.event(sessionId, 'disconnected', { reason: 'logged_out' });
                    await this.stop(sessionId);
                    return;
                }

                if (shouldRestart) {
                    logger.info({ sessionId }, 'Restart diperlukan oleh WhatsApp (515), menyambung ulang segera');
                    this.#jadwalkanReconnect(sessionId, entry, 0);
                    return;
                }

                // Disconnect lainnya (transient: timedOut, connectionLost, connectionClosed, dsb.)
                if (entry.reconnectAttempts < 5) {
                    entry.reconnectAttempts++;
                    // Saat sesi masih dalam tahap connecting awal (belum pernah QR atau connected),
                    // lakukan reconnect cepat (250ms) tanpa jeda panjang, supaya pengguna tidak
                    // melihat penundaan atau galat saat scan pertama.
                    const jeda = entry.status === 'connecting'
                        ? 250
                        : Math.min(2000 * Math.pow(1.5, entry.reconnectAttempts - 1), 15000);
                    logger.info(
                        { sessionId, attempt: entry.reconnectAttempts, jedaMs: Math.round(jeda) },
                        'Mencoba menyambung kembali websocket',
                    );
                    this.#jadwalkanReconnect(sessionId, entry, Math.round(jeda));
                } else {
                    entry.status = 'disconnected';
                    this.#lepasPengukurInit(entry);
                    logger.warn({ sessionId, attempts: entry.reconnectAttempts }, 'Batas percobaan reconnect habis, sesi dihentikan');
                    await laravel.event(sessionId, 'disconnected', {
                        reason: String(statusCode || 'connection_lost'),
                    });
                    await this.stop(sessionId);
                }
            }
        });

        sock.ev.on('messages.upsert', async ({ messages }) => {
            if (!Array.isArray(messages)) return;

            for (const msg of messages) {
                if (!msg?.message || msg.key?.fromMe) continue;

                const remoteJid = msg.key?.remoteJid;
                if (!remoteJid || remoteJid === 'status@broadcast' || remoteJid.endsWith('@broadcast')) {
                    continue;
                }

                // Unwrap pesan berulang / ephemeral / view once
                let message = msg.message;
                if (message?.ephemeralMessage?.message) message = message.ephemeralMessage.message;
                if (message?.viewOnceMessage?.message) message = message.viewOnceMessage.message;
                if (message?.viewOnceMessageV2?.message) message = message.viewOnceMessageV2.message;
                if (message?.viewOnceMessageV2Extension?.message) message = message.viewOnceMessageV2Extension.message;
                if (message?.documentWithCaptionMessage?.message) message = message.documentWithCaptionMessage.message;

                const contentType = getContentType(message);
                if (!contentType) continue;

                const normalizedFrom = jidNormalizedUser(msg.key.participant || remoteJid);

                await laravel.event(sessionId, 'message', {
                    wa_message_id: msg.key.id ?? null,
                    chat_id: toContractChatId(remoteJid),
                    from: toContractChatId(normalizedFrom),
                    type: mapType(contentType),
                    body: extractBody(contentType, message[contentType]),
                });
            }
        });

        sock.ev.on('messages.update', async (updates) => {
            if (!Array.isArray(updates)) return;

            for (const update of updates) {
                if (!update.key?.id || update.update?.status == null) continue;

                const ack = mapAck(update.update.status);
                if (ack !== null) {
                    await laravel.event(sessionId, 'message_ack', {
                        wa_message_id: update.key.id,
                        ack,
                    });
                }
            }
        });
    }

    #jadwalkanReconnect(sessionId, entry, delayMs) {
        if (entry.reconnectTimer) {
            clearTimeout(entry.reconnectTimer);
            entry.reconnectTimer = null;
        }

        if (delayMs === 0) {
            this.#lakukanReconnect(sessionId, entry);
            return;
        }

        entry.reconnectTimer = setTimeout(() => {
            this.#lakukanReconnect(sessionId, entry);
        }, delayMs);
        entry.reconnectTimer.unref?.();
    }

    async #lakukanReconnect(sessionId, entry) {
        if (entry.stopped || this.#sessions.get(sessionId) !== entry) return;

        try {
            if (entry.sock) {
                try {
                    entry.sock.end?.();
                } catch {}
                entry.sock = null;
            }

            await this.#sambungSocket(sessionId, entry);
        } catch (error) {
            logger.error({ sessionId, err: error.message }, 'Gagal saat mencoba menyambung ulang socket');
        }
    }

    #pasangPengukurInit(sessionId, entry) {
        entry.pengukurInit = setTimeout(async () => {
            if (this.#sessions.get(sessionId) !== entry) return;

            logger.error(
                { sessionId, batasMs: config.initTimeoutMs },
                'Sesi tidak selesai menginisialisasi dalam batas waktu; dihentikan',
            );

            entry.status = 'failed';

            await laravel.event(sessionId, 'auth_failure', {
                message: 'Nomor tidak selesai tersambung dalam batas waktu. Coba hubungkan lagi.',
            });

            await this.stop(sessionId);
        }, config.initTimeoutMs);

        entry.pengukurInit.unref?.();
    }

    #lepasPengukurInit(entry) {
        if (!entry?.pengukurInit) return;

        clearTimeout(entry.pengukurInit);
        entry.pengukurInit = null;
    }

    #pasangBackupInterval(sessionId, entry) {
        this.#lepasBackupInterval(entry);

        if (!config.backupIntervalMs || config.backupIntervalMs <= 0) return;

        entry.backupTimer = setInterval(async () => {
            if (entry.stopped || entry.status !== 'connected') return;

            try {
                await this.persist(sessionId);
            } catch (error) {
                logger.warn({ sessionId, err: error.message }, 'Gagal sinkronisasi backup berkala ke Laravel');
            }
        }, config.backupIntervalMs);

        entry.backupTimer.unref?.();
    }

    #lepasBackupInterval(entry) {
        if (!entry?.backupTimer) return;

        clearInterval(entry.backupTimer);
        entry.backupTimer = null;
    }

    /**
     * Menyimpan kredensial sesi ke store Laravel.
     */
    async persist(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (!entry || entry.status !== 'connected') return;

        await this.#store.save({ session: sessionId });
        logger.info({ sessionId }, 'Kredensial sesi disimpan ke Laravel');
    }

    async stop(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (!entry) return;

        entry.stopped = true;
        this.#lepasPengukurInit(entry);
        this.#lepasBackupInterval(entry);

        if (entry.reconnectTimer) {
            clearTimeout(entry.reconnectTimer);
            entry.reconnectTimer = null;
        }

        this.#sessions.delete(sessionId);
        this.#queue.forget(sessionId);

        if (entry.sock) {
            try {
                entry.sock.end?.();
            } catch (error) {
                logger.warn({ sessionId, err: error.message }, 'Galat saat menutup socket');
            }
            entry.sock = null;
        }
    }

    /**
     * Memutus tautan perangkat di sisi WhatsApp lalu membuang kredensialnya.
     * Operasi ini bersifat lokal dan merespons segera:
     * - sock.logout() dijalankan di latar belakang (fire-and-forget)
     * - Sesi lokal segera dihentikan dan dibersihkan
     * - Laravel tidak pernah menunggu WhatsApp
     */
    async logout(sessionId) {
        const entry = this.#sessions.get(sessionId);
        const sock = entry?.sock;

        // Lepas referensi socket dari entry agar stop() dan operasi lain tidak tumpang tindih
        if (entry) {
            entry.sock = null;
            entry.qrImage = null;
        }

        // Jalankan sock.logout() di latar belakang (fire-and-forget).
        // Jangan pernah menahan siklus respon HTTP ke Laravel.
        if (sock) {
            Promise.race([
                Promise.resolve().then(() => sock.logout?.()),
                new Promise((_, reject) =>
                    setTimeout(() => reject(new Error('sock.logout() timeout')), 4_000),
                ),
            ])
                .catch((error) => {
                    logger.warn({ sessionId, err: error.message }, 'Logout WhatsApp di latar belakang gagal atau timeout');
                })
                .finally(() => {
                    try {
                        sock.end?.(new Error('Session logged out'));
                    } catch {}
                });
        }

        await this.stop(sessionId);

        // Hapus folder sesi lokal jika ada
        try {
            const sessionDir = entry?.sessionDir ?? path.join(config.dataPath, sessionId);
            await rm(sessionDir, { recursive: true, force: true });
        } catch {}

        // Store remote: Laravel sudah membersihkan backup miliknya di SessionService::logout.
        // Jika perlu memanggil delete() ke store, jalankan setelah logout selesai (di macrotask berikutnya)
        // supaya tidak pernah menahan siklus respon HTTP ke Laravel.
        setImmediate(() => {
            this.#store?.delete?.({ session: sessionId })?.catch?.((error) => {
                logger.warn({ sessionId, err: error.message }, 'Gagal menghapus backup sesi di latar belakang');
            });
        });
    }

    status(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (!entry) {
            return null;
        }

        return {
            status: entry.status,
            phone_number: entry.phoneNumber,
            push_name: entry.pushName,
            loading_percent: entry.loadingPercent,
            qr: entry.qrImage ?? null,
        };
    }

    listSessionIds() {
        return [...this.#sessions.keys()];
    }

    stats() {
        let connected = 0;
        let connecting = 0;
        let qr = 0;

        for (const entry of this.#sessions.values()) {
            if (entry.status === 'connected') connected++;
            else if (entry.status === 'connecting') connecting++;
            else if (entry.status === 'qr') qr++;
        }

        return {
            total: this.#sessions.size,
            connected,
            connecting,
            qr,
        };
    }

    /**
     * Mengantre satu pesan keluar.
     */
    async send(sessionId, { to, type, body, media, messageId }) {
        const sebelumnya = messageId ? this.#kiriman.get(messageId) : undefined;

        if (sebelumnya) {
            logger.info({ sessionId, messageId }, 'Permintaan kirim diulang, memakai hasil kiriman pertama');

            return sebelumnya.promise;
        }

        const entry = this.#sessions.get(sessionId);

        if (!entry) {
            const error = new Error('Sesi tidak sedang berjalan di engine.');
            error.permanent = false;
            throw error;
        }

        if (entry.status !== 'connected' || !entry.sock) {
            const error = new Error(`Sesi berstatus ${entry.status}, belum siap mengirim.`);
            error.permanent = false;
            throw error;
        }

        const promise = this.#queue.enqueue(sessionId, async () => {
            const chatId = await this.#resolveChatId(entry.sock, to);

            let content;

            if (media) {
                const buffer = Buffer.from(media.data, 'base64');
                const mime = media.mimetype || '';

                if (type === 'image' || mime.startsWith('image/')) {
                    content = { image: buffer, caption: body ?? undefined, mimetype: mime };
                } else if (type === 'video' || mime.startsWith('video/')) {
                    content = { video: buffer, caption: body ?? undefined, mimetype: mime };
                } else if (type === 'audio' || mime.startsWith('audio/')) {
                    content = { audio: buffer, mimetype: mime, ptt: type === 'audio' && mime.includes('ogg') };
                } else {
                    content = {
                        document: buffer,
                        mimetype: mime,
                        fileName: media.filename || 'document',
                        caption: body ?? undefined,
                    };
                }
            } else {
                content = { text: body ?? '' };
            }

            const sent = await entry.sock.sendMessage(chatId, content);

            return { wa_message_id: sent?.key?.id ?? null, chat_id: toContractChatId(chatId) };
        });

        if (messageId) {
            this.#catatKiriman(messageId, promise);
        }

        return promise;
    }

    #catatKiriman(messageId, promise) {
        this.#kiriman.set(messageId, { promise, waktu: Date.now() });

        promise.catch(() => this.#kiriman.delete(messageId));

        const batas = Date.now() - 15 * 60 * 1000;

        for (const [kunci, nilai] of this.#kiriman) {
            if (nilai.waktu < batas) {
                this.#kiriman.delete(kunci);
            }
        }
    }

    async #resolveChatId(sock, to) {
        if (to.endsWith('@g.us') || to.endsWith('@s.whatsapp.net')) {
            return to;
        }

        if (to.endsWith('@c.us')) {
            return to.replace(/@c\.us$/, '@s.whatsapp.net');
        }

        const digits = to.replace(/\D+/g, '');

        if (typeof sock.onWhatsApp === 'function') {
            const results = await sock.onWhatsApp(digits);
            const match = Array.isArray(results) ? results.find((r) => r.exists) : null;

            if (!match || !match.jid) {
                const error = new Error(`Nomor ${digits} tidak terdaftar di WhatsApp.`);
                error.permanent = true;
                throw error;
            }

            return match.jid;
        }

        return `${digits}@s.whatsapp.net`;
    }
}

export function toContractChatId(jid) {
    if (!jid || typeof jid !== 'string') return jid;

    if (jid.endsWith('@s.whatsapp.net')) {
        return jid.replace(/@s\.whatsapp\.net$/, '@c.us');
    }

    return jid;
}

export function mapType(contentType) {
    if (contentType === 'conversation' || contentType === 'extendedTextMessage') return 'text';
    if (contentType === 'imageMessage') return 'image';
    if (contentType === 'documentMessage') return 'document';
    if (contentType === 'videoMessage') return 'video';
    if (contentType === 'audioMessage') return 'audio';
    if (contentType === 'locationMessage' || contentType === 'liveLocationMessage') return 'location';

    return 'other';
}

export function extractBody(contentType, content) {
    if (!content) return null;
    if (typeof content === 'string') return content;
    if (contentType === 'conversation') return content;
    if (contentType === 'extendedTextMessage') return content.text ?? null;
    if (contentType === 'imageMessage' || contentType === 'videoMessage' || contentType === 'documentMessage') {
        return content.caption ?? null;
    }
    if (contentType === 'locationMessage') {
        return (
            content.name ||
            content.address ||
            (content.degreesLatitude != null ? `${content.degreesLatitude},${content.degreesLongitude}` : null)
        );
    }

    return null;
}

export function mapAck(status) {
    if (status === WAMessageStatus.SERVER_ACK || status === 'SERVER_ACK' || status === 2) return 1;
    if (status === WAMessageStatus.DELIVERY_ACK || status === 'DELIVERY_ACK' || status === 3) return 2;
    if (status === WAMessageStatus.READ || status === 'READ' || status === 4) return 3;
    if (status === WAMessageStatus.PLAYED || status === 'PLAYED' || status === 5) return 3;

    return null;
}
