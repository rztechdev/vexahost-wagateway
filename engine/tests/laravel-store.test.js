import assert from 'node:assert/strict';
import { mkdtemp, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

// config.js gagal saat boot kalau variabel wajibnya kosong, jadi diisi lebih
// dulu sebelum modul apa pun diimpor.
process.env.ENGINE_TOKEN = 'uji';
process.env.ENGINE_HMAC_SECRET = 'uji';
process.env.LARAVEL_URL = 'http://localhost:8070';

const dataPath = await mkdtemp(path.join(tmpdir(), 'flustra-wa-store-'));
process.env.WA_DATA_PATH = dataPath;

const { LaravelStore } = await import('../src/stores/laravel-store.js');
const { laravel } = await import('../src/laravel.js');

/**
 * RemoteAuth menulis zip-nya ke `<dataPath>/<session>.zip` lewat
 * `compressSession()`. Store dulu membacanya sebagai path relatif, yang
 * diselesaikan terhadap working directory proses — jadi setiap upload berakhir
 * ENOENT dan tidak pernah ada satu pun backup yang tersimpan.
 *
 * Akibatnya baru terlihat saat redeploy: RemoteAuth selalu menghapus
 * userDataDir lalu memulihkannya dari store, dan store yang kosong berarti
 * folder kosong — nomor harus di-scan ulang. Persis masalah yang seluruh
 * mekanisme backup ini dibuat untuk menghilangkan.
 */
test('save membaca zip dari dataPath, bukan dari working directory', async () => {
    const store = new LaravelStore();
    const session = 'RemoteAuth-uji123';
    const isi = Buffer.from('zip palsu');

    await writeFile(path.join(dataPath, `${session}.zip`), isi);

    let terkirim = null;
    const asli = laravel.uploadBackup;
    laravel.uploadBackup = async (sessionId, buffer, digest) => {
        terkirim = { sessionId, buffer, digest };
    };

    try {
        await store.save({ session });
    } finally {
        laravel.uploadBackup = asli;
    }

    assert.notEqual(terkirim, null, 'uploadBackup seharusnya dipanggil');
    assert.equal(terkirim.sessionId, 'uji123', 'awalan RemoteAuth- harus dilepas');
    assert.deepEqual(terkirim.buffer, isi);
});

/**
 * RemoteAuth memanggil sessionExists() tepat sebelum menghapus userDataDir.
 * Menjawab "tidak ada" saat Laravel sekadar tidak terjangkau membuat kredensial
 * yang masih sempurna ikut terhapus, dan nomor harus di-scan ulang gara-gara
 * gangguan sesaat. Galat harus dilempar supaya start dibatalkan lebih dulu.
 */
test('sessionExists melempar galat, bukan menjawab false, saat Laravel bermasalah', async () => {
    const store = new LaravelStore();
    const asli = laravel.backupExists;
    laravel.backupExists = async () => {
        throw new Error('Laravel tidak terjangkau');
    };

    try {
        await assert.rejects(
            () => store.sessionExists({ session: 'RemoteAuth-uji123' }),
            /tidak terjangkau/,
        );
    } finally {
        laravel.backupExists = asli;
    }
});
