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

export const config = {
    port: Number(process.env.PORT ?? 3100),
    host: process.env.HOST ?? '0.0.0.0',

    // Token statis yang harus disertakan Laravel di header X-Engine-Token.
    engineToken: required('ENGINE_TOKEN'),

    // Secret HMAC untuk menandatangani callback engine -> Laravel.
    hmacSecret: required('ENGINE_HMAC_SECRET'),

    laravelUrl: required('LARAVEL_URL').replace(/\/+$/, ''),

    // Persistent volume Coolify. Inilah yang membuat sesi selamat dari redeploy.
    dataPath: process.env.WA_DATA_PATH ?? '/data/.wwebjs_auth',

    // Interval backup RemoteAuth. Minimum yang diizinkan library adalah 60 detik.
    backupIntervalMs: Math.max(60_000, Number(process.env.WA_BACKUP_INTERVAL_MS ?? 300_000)),

    // Jeda acak antar pesan keluar dalam satu sesi (anti-ban).
    minDelayMs: Number(process.env.WA_MIN_DELAY_MS ?? 3000),
    maxDelayMs: Number(process.env.WA_MAX_DELAY_MS ?? 8000),

    // Batas sesi aktif per container. Tiap sesi = satu Chromium (±300-500 MB),
    // jadi angka ini harus disesuaikan dengan RAM yang tersedia.
    maxSessions: Number(process.env.WA_MAX_SESSIONS ?? 10),

    logLevel: process.env.LOG_LEVEL ?? 'info',

    puppeteerArgs: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu',
    ],
};
