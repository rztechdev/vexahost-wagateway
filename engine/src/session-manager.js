import { mkdir } from 'node:fs/promises';
import qrcode from 'qrcode';
import pkg from 'whatsapp-web.js';
import { config } from './config.js';
import { laravel } from './laravel.js';
import { logger } from './logger.js';
import { sapuFolderYatim } from './penyapu.js';
import { bebaskanProfil, jalurProfil } from './profil.js';
import { tutupChromium } from './chromium.js';
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
     * Pabrik client, bisa diganti saat pengujian.
     *
     * Tanpa seam ini tidak ada satu pun perilaku SessionManager yang bisa
     * diuji: `new Client()` menyalakan Chromium sungguhan, jadi menguji batas
     * waktu inisialisasi berarti menunggu browser nyata gagal memuat WhatsApp
     * Web. Bawaannya tetap client sungguhan; produksi tidak berubah.
     */
    #buatClient;

    /**
     * Akar /proc. Bisa diarahkan ke pohon tiruan saat pengujian supaya
     * penjagaan tabrakan profil benar-benar dilewati `start()`, bukan cuma
     * diuji sebagai fungsi lepas. Produksi tidak pernah menyetelnya.
     */
    #procRoot;

    /**
     * Pengirim sinyal. Ikut bisa diganti bersama `procRoot` supaya perilaku
     * "profil tidak bisa dibebaskan" bisa diuji tanpa proses yang benar-benar
     * menolak mati — proses seperti itu tidak bisa dibuat-buat, dan memakai pid
     * proses uji sendiri sebagai umpan justru membunuh penguji.
     */
    #killProses;

    constructor({ buatClient, procRoot = '/proc', killProses } = {}) {
        this.#procRoot = procRoot;
        this.#killProses = killProses;
        this.#buatClient =
            buatClient ??
            ((sessionId) =>
                new Client({
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
                }));
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

        /*
         | Membuang folder kredensial milik sesi yang sudah tidak ada.
         |
         | Dijalankan SEBELUM sesi dinyalakan, dan itu yang membuatnya aman:
         | folder milik sesi yang benar-benar dipakai disentuh ulang tiap kali
         | sesinya jalan, jadi umurnya tidak pernah mendekati tujuh hari. Sesi
         | yang akan dipulihkan di bawah ikut dikecualikan secara eksplisit,
         | supaya tidak bergantung pada mtime sama sekali.
         |
         | Kegagalannya tidak boleh menahan pemulihan: ruang disk yang tidak
         | jadi dibebaskan jauh lebih murah daripada nomor yang tidak jadi
         | tersambung.
        */
        try {
            await sapuFolderYatim({
                dataPath: config.dataPath,
                kecuali: sessions.map(({ session_id: id }) => id),
            });
        } catch (error) {
            logger.warn({ err: error.message }, 'Penyapuan folder kredensial yatim dilewati');
        }

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

    /**
     * Menanyakan daftar sesi ke Laravel, dengan percobaan ulang.
     *
     * Kegagalan di sini tidak punya jaring pengaman lain: `bootstrap()` cuma
     * dipanggil sekali seumur hidup proses, dan sesi yang tidak ikut dipulihkan
     * baru akan disentuh lagi kalau `SyncSessionStatusJob` kebetulan
     * menemukannya berstatus `disconnected` — sesi yang tercatat `failed`
     * tidak pernah tersentuh sama sekali.
     *
     * Sejak engine berbagi container dengan Laravel, keduanya lahir berbarengan
     * dan percobaan pertama memang wajar gagal. `start.sh` sudah menunggu web
     * menjawab /up sebelum menjalankan engine; percobaan ulang di sini
     * menangani sisanya — web yang menjawab /up tapi belum siap melayani rute
     * `/internal/*`, atau database yang belum menerima koneksi.
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

    async start(sessionId) {
        if (this.#sessions.has(sessionId)) {
            return this.#sessions.get(sessionId);
        }

        /*
         | Dua permintaan start yang datang bersamaan akan membuat dua Chromium
         | untuk satu sesi yang sama, dan keduanya berebut folder kredensial.
         |
         | Penjagaan ini dulu hampir tidak berguna: tidak ada satu pun `await`
         | antara pemeriksaan di atas dan `#sessions.set()` di bawah, jadi tidak
         | ada celah untuk disisipi. Sejak pembebasan profil ditambahkan — dan
         | pembebasan itu MENUNGGU proses lama benar-benar mati — celahnya nyata
         | dan bisa selebar beberapa detik. Justru di celah itulah permintaan
         | kedua akan menyalakan Chromium di atas profil yang sedang dibersihkan
         | untuk yang pertama.
        */
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
            /*
             | SATU PROFIL, SATU CHROMIUM. Ditegakkan terhadap sistem operasi,
             | bukan terhadap `#sessions`.
             |
             | Map itu justru KOSONG di kasus yang bermasalah: `initialize()`
             | yang gagal menghapus entry-nya, lalu `destroy()` mengembalikan
             | sukses tanpa menutup apa pun. Sesudah itu tidak ada satu pun
             | struktur data di dalam Node yang tahu prosesnya masih hidup —
             | jadi pemeriksaan `#sessions.has()` di atas melewatkannya, dan
             | Chromium baru lahir di atas profil yang masih dipegang yang lama.
             |
             | Produksi 8 September 2026: empat Chromium pada satu profil,
             | seluruhnya anak dari satu proses Node. Gejalanya bukan kehabisan
             | memori melainkan `Execution context was destroyed` di layar
             | pelanggan yang sedang men-scan.
             |
             | `await` di sini menahan `start()` beberapa detik saat memang ada
             | yang harus dibunuh, dan itu benar: memulai lebih cepat di atas
             | profil yang belum bebas persis kerusakan yang sedang dicegah.
            */
            const profil = jalurProfil(config.dataPath, sessionId);
            const bebas = await bebaskanProfil(profil, {
                proc: this.#procRoot,
                ...(this.#killProses ? { kill: this.#killProses } : {}),
            });

            if (bebas.bandel.length > 0) {
                throw new Error(
                    `Profil sesi masih dipegang proses ${bebas.bandel.join(', ')} yang tidak bisa dimatikan.`
                );
            }

            const client = this.#buatClient(sessionId);

            this.#bindEvents(sessionId, client);

            const entry = {
                client,
                status: 'connecting',
                phoneNumber: null,
                pushName: null,
                loadingPercent: null,
                pengukurInit: null,
            };
            this.#sessions.set(sessionId, entry);

            this.#pasangPengukurInit(sessionId, entry);

            // initialize() sengaja tidak di-await: memulihkan sesi bisa memakan
            // puluhan detik, dan pemanggil hanya perlu tahu prosesnya dimulai.
            // Keadaan sebenarnya dikabarkan lewat event.
            client.initialize().catch(async (error) => {
                logger.error({ sessionId, err: error.message }, 'Inisialisasi client gagal');

                entry.status = 'failed';
                this.#lepasPengukurInit(entry);
                await laravel.event(sessionId, 'auth_failure', { message: error.message });
                this.#sessions.delete(sessionId);

                // initialize() menyalakan Chromium lebih dulu, baru memuat
                // WhatsApp Web. Kalau gagal setelah tahap itu, prosesnya sudah
                // hidup — dan begitu entry dibuang, tidak ada lagi yang
                // memegang referensinya. Tanpa penutupan di sini tiap start
                // yang gagal meninggalkan Chromium yatim ±400 MB yang tidak
                // pernah kembali sampai server kehabisan memori.
                //
                // Lewat tutupChromium(), bukan client.destroy() langsung:
                // destroy() melewati browser.close() kalau websocket CDP-nya
                // sudah putus, dan justru start yang gagal karena kehabisan
                // memori adalah keadaan yang paling mungkin memutus websocket
                // itu lebih dulu.
                await tutupChromium(client, sessionId);
            });

            return entry;
        } finally {
            this.#starting.delete(sessionId);
        }
    }

    /**
     * Menyalakan pengukur batas waktu inisialisasi untuk satu sesi.
     *
     * Yang dijaga adalah sesi yang menggantung TANPA gagal — keadaan yang tidak
     * menghasilkan galat, tidak menghasilkan event, dan tidak bisa dilihat dari
     * mana pun kecuali dari fakta bahwa slotnya tidak pernah kembali.
     */
    #pasangPengukurInit(sessionId, entry) {
        entry.pengukurInit = setTimeout(async () => {
            // Sesi bisa sudah diganti oleh start() berikutnya; yang dihentikan
            // harus sesi yang sama dengan yang dulu memasang pengukurnya.
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

        // Pengukur ini tidak boleh menahan proses tetap hidup saat engine
        // hendak berhenti — SIGTERM sudah punya urutannya sendiri di server.js.
        entry.pengukurInit.unref?.();
    }

    /**
     * Melepas pengukur. Dipanggil pada setiap bukti bahwa WhatsApp Web hidup,
     * dan pada setiap jalur yang mengakhiri sesi.
     */
    #lepasPengukurInit(entry) {
        if (!entry?.pengukurInit) return;

        clearTimeout(entry.pengukurInit);
        entry.pengukurInit = null;
    }

    /**
     * Memaksa RemoteAuth mengirim kredensial terbaru ke store sekarang juga.
     *
     * Dipanggil sebelum mematikan engine. `client.destroy()` tidak menyimpan
     * apa pun pada RemoteAuth — ia cuma menghentikan timer backup berkala —
     * jadi tanpa ini, semua perubahan sejak backup terakhir (sampai
     * WA_BACKUP_INTERVAL_MS) hilang saat redeploy.
     *
     * Hanya untuk sesi yang benar-benar tersambung: menyimpan kredensial sesi
     * yang sedang menampilkan QR atau baru saja terputus berarti menimpa backup
     * yang masih baik dengan folder yang belum tentu bisa dipulihkan.
     */
    async persist(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (!entry || entry.status !== 'connected') return;

        const strategy = entry.client.authStrategy;

        if (typeof strategy?.storeRemoteSession !== 'function') return;

        await strategy.storeRemoteSession();
        logger.info({ sessionId }, 'Kredensial sesi disimpan sebelum engine berhenti');
    }

    async stop(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (!entry) return;

        this.#lepasPengukurInit(entry);
        this.#sessions.delete(sessionId);
        this.#queue.forget(sessionId);

        // Satu-satunya penutup Chromium di seluruh engine. Ia harus menutup
        // pada SETIAP jalur — destroy() yang melempar, destroy() yang
        // menggantung, dan destroy() yang kembali sukses tanpa menutup apa pun.
        // Ketiganya ditangani tutupChromium(); alasan lengkapnya di chromium.js.
        await tutupChromium(entry.client, sessionId);
    }

    /**
     * Memutus tautan perangkat di sisi WhatsApp lalu membuang kredensialnya.
     * Berbeda dari stop(): setelah ini nomor harus scan QR lagi.
     */
    async logout(sessionId) {
        const entry = this.#sessions.get(sessionId);

        if (entry) {
            // Dibatasi waktu karena `Client.logout()` diawali
            // `pupPage.evaluate()` pada halaman yang mungkin sudah tidak
            // menjawab. Tanpa batas, logout yang menggantung tidak pernah
            // sampai ke stop() di bawah — dan stop() itulah yang memastikan
            // Chromium-nya mati.
            try {
                await Promise.race([
                    entry.client.logout(),
                    new Promise((_, reject) =>
                        setTimeout(() => reject(new Error('logout() melewati batas waktu')), 15_000),
                    ),
                ]);
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
            loading_percent: entry.loadingPercent,
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

            // Bukti pertama bahwa Chromium hidup dan WhatsApp Web memuat. Mulai
            // dari sini yang ditunggu adalah manusia yang men-scan, dan menunggu
            // manusia tidak boleh punya batas waktu.
            this.#lepasPengukurInit(entry);

            // Dirender jadi PNG di sini supaya Laravel dan dashboard tidak perlu
            // library QR sama sekali — cukup menaruhnya di <img src>.
            const image = await qrcode.toDataURL(qr, { width: 320, margin: 1 });

            await laravel.event(sessionId, 'qr', { qr_image: image });
        });

        client.on('authenticated', async () => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.status = 'connecting';

            this.#lepasPengukurInit(entry);

            await laravel.event(sessionId, 'authenticated');
        });

        /**
         * Antara QR ter-scan dan sesi siap, WhatsApp Web menarik riwayat chat
         * lebih dulu. Untuk akun yang ramai, jeda itu bisa berupa menit-menit
         * tanpa satu pun event lain — dan dashboard hanya bisa menampilkan
         * "menyiapkan sesi" tanpa tahu apakah ada kemajuan atau memang macet.
         * Persentasenya cukup disimpan di memori: ia hanya berguna selama ada
         * orang menatap modalnya, dan status() yang menyajikannya.
         */
        client.on('loading_screen', (percent) => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.loadingPercent = Number(percent);

            // Menarik riwayat chat besar memang bisa makan menit-menit, dan itu
            // pekerjaan yang sah. Yang dijaga pengukur adalah sesi yang tidak
            // menunjukkan kemajuan apa pun — bukan sesi yang lambat.
            this.#lepasPengukurInit(entry);
        });

        client.on('auth_failure', async (message) => {
            const entry = this.#sessions.get(sessionId);
            if (entry) entry.status = 'failed';

            this.#lepasPengukurInit(entry);

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
                entry.loadingPercent = null;
            }

            this.#lepasPengukurInit(entry);

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
