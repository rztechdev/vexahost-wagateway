import process from 'node:process';

function required(name) {
    const value = process.env[name];

    if (!value) {
        // Sengaja gagal saat boot, bukan saat request pertama: engine yang
        // berjalan tanpa token/secret akan menerima callback tak bertanda
        // tangan dan menolak semua permintaan Laravel tanpa penjelasan.
        throw new Error(`Environment variable ${name} wajib diisi.`);
    }

    return value;
}

/**
 * Angka opsional. String kosong diperlakukan sama dengan tidak diisi — di
 * Coolify variabel yang "dikosongkan" tetap terkirim ke container sebagai
 * string kosong, dan Number('') menghasilkan 0.
 */
function angka(name, bawaan) {
    const value = process.env[name];

    if (value === undefined || value === '') return bawaan;

    const parsed = Number(value);

    return Number.isFinite(parsed) ? parsed : bawaan;
}

/**
 * Sejak engine dan Laravel berbagi satu container (lihat ../../start.sh),
 * seluruh variabel env-nya berada di satu ruang nama yang sama. Tiga nama
 * bertabrakan dan harus dibedakan:
 *
 *   PORT / HOST  → dipakai Nixpacks & Coolify untuk proses web
 *   LOG_LEVEL    → Laravel memakainya dengan skala nilai yang berbeda
 *                  (`error` di produksi; di engine itu berarti kehilangan baris
 *                  "Backup sesi terkirim" yang jadi patokan uji regresi)
 *
 * Nama lama tetap diterima sebagai cadangan supaya engine masih bisa
 * dijalankan sendiri saat pengembangan lokal (`npm --prefix engine run dev`
 * membaca engine/.env yang masih memakai PORT/HOST/LOG_LEVEL).
 */
export const config = {
    port: angka('ENGINE_PORT', angka('PORT', 3100)),

    // Bawaannya tetap 0.0.0.0 supaya engine yang dijalankan sendiri tetap bisa
    // dijangkau. Dalam satu container, env-nya menyetel 127.0.0.1 — tidak ada
    // yang perlu menjangkau engine dari luar container, dan port yang tidak
    // mendengarkan di jaringan internal adalah port yang tidak bisa disalahgunakan.
    host: process.env.ENGINE_HOST || process.env.HOST || '0.0.0.0',

    // Token statis yang harus disertakan Laravel di header X-Engine-Token.
    engineToken: required('ENGINE_TOKEN'),

    // Secret HMAC untuk menandatangani callback engine -> Laravel.
    hmacSecret: required('ENGINE_HMAC_SECRET'),

    laravelUrl: required('LARAVEL_URL').replace(/\/+$/, ''),

    // Persistent volume Coolify. Inilah yang membuat sesi selamat dari redeploy.
    dataPath: process.env.WA_DATA_PATH ?? '/data/.wwebjs_auth',

    // Interval backup RemoteAuth. Minimum yang diizinkan library adalah 60 detik.
    backupIntervalMs: Math.max(60_000, angka('WA_BACKUP_INTERVAL_MS', 300_000)),

    // Jeda acak antar pesan keluar dalam satu sesi (anti-ban).
    minDelayMs: angka('WA_MIN_DELAY_MS', 3000),
    maxDelayMs: angka('WA_MAX_DELAY_MS', 8000),

    /**
     * Batas sesi aktif per container. Tiap sesi = satu Chromium (±300-500 MB).
     *
     * Bawaannya 3, bukan 10 seperti sebelumnya. Angka 10 lahir waktu engine
     * punya containernya sendiri dan asumsi RAM-nya 4 GB khusus untuk itu;
     * sekarang RAM yang sama dipakai bersama PHP, worker, penjadwal, MySQL, dan
     * tujuh aplikasi Flustra lain di VPS yang sama. Sepuluh sesi berarti sampai
     * 5 GB hanya untuk Chromium, dan yang dibunuh OOM killer belum tentu
     * Chromium-nya — bisa saja MySQL.
     *
     * Patokan menaikkannya: sisakan minimal 1,5 GB untuk sisa sistem, lalu
     * bagi sisanya dengan 500 MB.
     */
    maxSessions: angka('WA_MAX_SESSIONS', 3),

    logLevel: process.env.ENGINE_LOG_LEVEL || process.env.LOG_LEVEL || 'info',

    /**
     * Argumen Chromium.
     *
     * Tujuh yang pertama sudah ada sejak awal dan wajib untuk berjalan di dalam
     * container. Sisanya penghemat: mematikan bagian Chromium yang tidak pernah
     * dipakai WhatsApp Web sama sekali (ekstensi, sinkronisasi akun, penerjemah,
     * pembaruan komponen, audio, telemetri). Masing-masing kecil; bersama-sama
     * menahan puluhan MB dan beberapa proses latar per sesi — dan proseslah
     * yang mahal ketika satu container menampung beberapa Chromium sekaligus.
     *
     * Yang SENGAJA tidak dipakai: `--single-process` dan `--renderer-process-limit`.
     * Keduanya memang menghemat banyak, tapi keduanya juga sudah dikenal
     * membuat whatsapp-web.js gagal dengan cara yang sulit dilacak — dan sesi
     * yang mati diam-diam jauh lebih mahal daripada RAM yang dihemat.
     */
    puppeteerArgs: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',

        '--disable-extensions',
        '--disable-component-extensions-with-background-pages',
        '--disable-default-apps',
        '--disable-background-networking',
        '--disable-client-side-phishing-detection',
        '--disable-component-update',
        '--disable-sync',
        '--disable-translate',
        '--no-default-browser-check',
        '--metrics-recording-only',
        '--mute-audio',
        '--hide-scrollbars',

        // Batas heap V8 di dalam Chromium. Dibiarkan kosong secara bawaan:
        // memotong heap terlalu rendah membuat tab WhatsApp mati sendiri pada
        // akun dengan riwayat chat besar, dan gejalanya (sesi tiba-tiba
        // `disconnected`) tidak menyebut memori sama sekali. Isi
        // WA_CHROME_HEAP_MB hanya kalau memang terbukti perlu; 512 titik awal
        // yang masuk akal.
        ...(process.env.WA_CHROME_HEAP_MB
            ? [`--js-flags=--max-old-space-size=${Number(process.env.WA_CHROME_HEAP_MB)}`]
            : []),
    ],
};
