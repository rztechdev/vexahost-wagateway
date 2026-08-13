import { mkdir } from 'node:fs/promises';
import qrcode from 'qrcode';
import pkg from 'whatsapp-web.js';
import { config } from './config.js';
import { laravel } from './laravel.js';
import { logger } from './logger.js';
import { SendQueue } from './queue.js';
import { LaravelStore } from './stores/laravel-store.js';

const { Client, RemoteAuth, MessageMedia } = pkg;

export class SessionManager {
    #sessions = new Map();
    #starting = new Set();
    #queue = new SendQueue();
    #store = new LaravelStore();

    /**
     * Hasil pengiriman per message_id, untuk menangkal kiriman ganda.
     *
     * Laravel mengulang job-nya kalau permintaan HTTP-nya habis waktu — padahal
     * habis waktu bukan berarti pesannya tidak terkirim. Antrean anti-ban di
     * engine menahan tiap pesan 3-8 detik sebelum benar-benar dikirim, jadi
     * pesan ketiga dalam satu giliran bisa menunggu lebih lama daripada batas
     * waktu HTTP Laravel. Yang terjadi kemarin: satu pesan sampai ke penerima
     * tiga sampai empat kali.
     *
     * Kunci map ini message_id milik Laravel, jadi percobaan ulang menerima
     * hasil kiriman pertama alih-alih mengirim ulang. Nilainya Promise, bukan
     * hasil jadi — percobaan ulang yang datang saat kiriman pertama masih
     * berjalan ikut menunggu promise yang sama.
     */
    #kiriman = new Map();

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
            sessions = await laravel.bootstrap();
        } catch (error) {
            logger.error({ err: error.message }, 'Bootstrap gagal; engine tetap jalan tanpa sesi');

            return;
        }

        logger.info({ count: sessions.length }, 'Memulihkan sesi tersimpan');

        for (const { session_id: sessionId, name } of sessions) {
            try {
                await this.start(sessionId);
                logger.info({ sessionId, name }, 'Sesi dipulihkan');
            } catch (error) {
                // Satu sesi rusak tidak boleh menahan pemulihan sesi lainnya.
                logger.error({ sessionId, err: error.message }, 'Gagal memulihkan sesi');
            }
        }
    }

    async start(sessionId) {
        if (this.#sessions.has(sessionId)) {
            return this.#sessions.get(sessionId);
        }

        // Dua permintaan start yang datang bersamaan akan membuat dua Chromium
        // untuk satu sesi yang sama, dan keduanya berebut folder kredensial.
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
            const client = new Client({
                authStrategy: new RemoteAuth({
                    clientId: sessionId,
                    dataPath: config.dataPath,
                    store: this.#store,
                    backupSyncIntervalMs: config.backupIntervalMs,
                }),
                puppeteer: {
                    headless: true,
                    args: config.puppeteerArgs,
                },
            });

            this.#bindEvents(sessionId, client);

            const entry = { client, status: 'connecting', phoneNumber: null, pushName: null };
            this.#sessions.set(sessionId, entry);

            // initialize() sengaja tidak di-await: memulihkan sesi bisa memakan
            // puluhan detik, dan pemanggil hanya perlu tahu prosesnya dimulai.
            // Keadaan sebenarnya dikabarkan lewat event.
            client.initialize().catch(async (error) => {
                logger.error({ sessionId, err: error.message }, 'Inisialisasi client gagal');

                entry.status = 'failed';
                await laravel.event(sessionId, 'auth_failure', { message: error.message });
                this.#sessions.delete(sessionId);
            });

            return entry;
        } finally {
            this.#starting.delete(sessionId);
        }
    }

    async stop(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (!entry) return;

        this.#sessions.delete(sessionId);
        this.#queue.forget(sessionId);

        try {
            await entry.client.destroy();
        } catch (error) {
            logger.warn({ sessionId, err: error.message }, 'Gagal menutup client dengan rapi');
        }
    }

    /**
     * Memutus tautan perangkat di sisi WhatsApp lalu membuang kredensialnya.
     * Berbeda dari stop(): setelah ini nomor harus scan QR lagi.
     */
    async logout(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (entry) {
            try {
                await entry.client.logout();
            } catch (error) {
                logger.warn({ sessionId, err: error.message }, 'Logout WhatsApp gagal');
            }
        }

        await this.stop(sessionId);

        try {
            await this.#store.delete({ session: `RemoteAuth-${sessionId}` });
        } catch (error) {
            logger.warn({ sessionId, err: error.message }, 'Gagal menghapus backup sesi');
        }
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
        };
    }

    listSessionIds() {
        return [...this.#sessions.keys()];
    }

    /**
     * Mengantre satu pesan keluar. Menunggu giliran di antrean sesi, lalu
     * menunggu jeda anti-ban, baru benar-benar dikirim.
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

        if (entry.status !== 'connected') {
            const error = new Error(`Sesi berstatus ${entry.status}, belum siap mengirim.`);
            error.permanent = false;
            throw error;
        }

        const promise = this.#queue.enqueue(sessionId, async () => {
            const chatId = await this.#resolveChatId(entry.client, to);

            let sent;

            if (media) {
                const attachment = new MessageMedia(media.mimetype, media.data, media.filename);
                sent = await entry.client.sendMessage(chatId, attachment, { caption: body ?? undefined });
            } else {
                sent = await entry.client.sendMessage(chatId, body);
            }

            // `sent` kadang undefined walau pesannya benar-benar sampai —
            // whatsapp-web.js tidak selalu berhasil menyusun objek Message
            // balasannya. Sebelumnya baris ini melempar TypeError, Laravel
            // menganggapnya gagal, dan mengulang job-nya: penerima menerima
            // pesan yang sama tiga sampai empat kali. ID pesan cuma pelengkap
            // untuk melacak status, jadi tidak boleh menentukan berhasil atau
            // tidaknya pengiriman.
            return { wa_message_id: sent?.id?._serialized ?? null, chat_id: chatId };
        });

        if (messageId) {
            this.#catatKiriman(messageId, promise);
        }

        return promise;
    }

    /**
     * Hasil disimpan hanya kalau kirimannya berhasil. Kegagalan sengaja dilupakan:
     * percobaan ulang Laravel memang seharusnya mencoba lagi.
     */
    #catatKiriman(messageId, promise) {
        this.#kiriman.set(messageId, { promise, waktu: Date.now() });

        promise.catch(() => this.#kiriman.delete(messageId));

        // Dibersihkan berkala supaya map tidak tumbuh selamanya. Umurnya cukup
        // melampaui seluruh jadwal percobaan ulang Laravel (10 + 60 + 300 detik).
        const batas = Date.now() - 15 * 60 * 1000;

        for (const [kunci, nilai] of this.#kiriman) {
            if (nilai.waktu < batas) {
                this.#kiriman.delete(kunci);
            }
        }
    }

    /**
     * Memastikan nomor tujuan benar-benar terdaftar di WhatsApp sebelum kirim.
     * Tanpa pengecekan ini, pesan ke nomor tidak terdaftar akan gagal dengan
     * error generik dan job akan mengulanginya berkali-kali tanpa guna.
     */
    async #resolveChatId(client, to) {
        if (to.endsWith('@g.us') || to.endsWith('@c.us')) {
            return to;
        }

        const digits = to.replace(/\D+/g, '');
        const numberId = await client.getNumberId(digits);

        if (!numberId) {
            const error = new Error(`Nomor ${digits} tidak terdaftar di WhatsApp.`);
            error.permanent = true;
            throw error;
        }

        return numberId._serialized;
    }

    #bindEvents(sessionId, client) {
        client.on('qr', async (qr) => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.status = 'qr';

            // Dirender jadi PNG di sini supaya Laravel dan dashboard tidak perlu
            // library QR sama sekali — cukup menaruhnya di <img src>.
            const image = await qrcode.toDataURL(qr, { width: 320, margin: 1 });

            await laravel.event(sessionId, 'qr', { qr_image: image });
        });

        client.on('authenticated', async () => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.status = 'connecting';

            await laravel.event(sessionId, 'authenticated');
        });

        client.on('auth_failure', async (message) => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.status = 'failed';

            await laravel.event(sessionId, 'auth_failure', { message });
        });

        client.on('ready', async () => {
            const entry = this.#sessions.get(sessionId);

            const phoneNumber = client.info?.wid?.user ?? null;
            const pushName = client.info?.pushname ?? null;

            if (entry) {
                entry.status = 'connected';
                entry.phoneNumber = phoneNumber;
                entry.pushName = pushName;
            }

            logger.info({ sessionId }, 'Sesi siap');

            await laravel.event(sessionId, 'ready', {
                phone_number: phoneNumber,
                push_name: pushName,
            });
        });

        client.on('remote_session_saved', () => {
            logger.debug({ sessionId }, 'RemoteAuth menyimpan backup');
        });

        client.on('disconnected', async (reason) => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.status = 'disconnected';

            logger.warn({ sessionId, reason }, 'Sesi terputus');

            await laravel.event(sessionId, 'disconnected', { reason: String(reason) });

            // Client yang sudah disconnected tidak bisa dipakai lagi; harus
            // dibuang supaya start() berikutnya membuat instance yang bersih.
            await this.stop(sessionId);
        });

        client.on('message', async (message) => {
            // Status/story bukan percakapan dan jumlahnya bisa sangat banyak.
            if (message.isStatus) return;

            await laravel.event(sessionId, 'message', {
                wa_message_id: message.id?._serialized ?? null,
                chat_id: message.from,
                from: message.author ?? message.from,
                type: mapType(message.type),
                body: message.body ?? null,
            });
        });

        client.on('message_ack', async (message, ack) => {
            await laravel.event(sessionId, 'message_ack', {
                wa_message_id: message.id?._serialized ?? null,
                ack,
            });
        });
    }
}

function mapType(type) {
    const known = ['image', 'document', 'video', 'audio', 'location'];

    if (type === 'chat') return 'text';
    if (type === 'ptt') return 'audio';

    return known.includes(type) ? type : 'other';
}
