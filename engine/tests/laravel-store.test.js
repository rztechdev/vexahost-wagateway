import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { test } from 'node:test';

process.env.ENGINE_TOKEN = 'uji';
process.env.ENGINE_HMAC_SECRET = 'uji';
process.env.LARAVEL_URL = 'http://localhost:8051';

const dataPath = await mkdtemp(path.join(tmpdir(), 'vexahost-wa-store-'));
process.env.WA_DATA_PATH = dataPath;

const { LaravelStore } = await import('../src/stores/laravel-store.js');
const { laravel } = await import('../src/laravel.js');

test('save membundel berkas dari folder sesi ke dalam JSON dan mengunggahnya', async () => {
    const store = new LaravelStore();
    const sessionId = 'uji123';
    const sessionDir = path.join(dataPath, sessionId);
    await mkdir(sessionDir, { recursive: true });

    await writeFile(path.join(sessionDir, 'creds.json'), JSON.stringify({ me: '628123' }));
    await writeFile(path.join(sessionDir, 'pre-key-1.json'), JSON.stringify({ keyId: 1 }));

    let terkirim = null;
    const asli = laravel.uploadBackup;
    laravel.uploadBackup = async (id, buffer, digest) => {
        terkirim = { id, buffer, digest };
    };

    try {
        await store.save(sessionId);
    } finally {
        laravel.uploadBackup = asli;
    }

    assert.notEqual(terkirim, null, 'uploadBackup seharusnya dipanggil');
    assert.equal(terkirim.id, 'uji123');

    const parsed = JSON.parse(terkirim.buffer.toString('utf-8'));
    assert.equal(parsed.sessionId, 'uji123');
    assert.equal(parsed.version, 1);
    assert.deepEqual(JSON.parse(parsed.files['creds.json']), { me: '628123' });
    assert.deepEqual(JSON.parse(parsed.files['pre-key-1.json']), { keyId: 1 });
});

test('sessionExists melempar galat, bukan menjawab false, saat Laravel bermasalah', async () => {
    const store = new LaravelStore();
    const asli = laravel.backupExists;
    laravel.backupExists = async () => {
        throw new Error('Laravel tidak terjangkau');
    };

    try {
        await assert.rejects(
            () => store.sessionExists('uji123'),
            /tidak terjangkau/,
        );
    } finally {
        laravel.backupExists = asli;
    }
});

test('simpan lalu pulihkan menghasilkan state yang sama persis', async () => {
    const store = new LaravelStore();
    const sessionId = 'uji-pulih';
    const sessionDir = path.join(dataPath, sessionId);
    await mkdir(sessionDir, { recursive: true });

    const berkasUji = {
        'creds.json': JSON.stringify({ registrationId: 98765, registered: true }),
        'app-state-sync-key-1.json': JSON.stringify({ keyData: 'abc123xyz' }),
        'pre-key-42.json': JSON.stringify({ keyPair: { public: 'pubkey', private: 'privkey' } }),
    };

    for (const [nama, isi] of Object.entries(berkasUji)) {
        await writeFile(path.join(sessionDir, nama), isi);
    }

    let storageBuffer = null;
    const asliUpload = laravel.uploadBackup;
    const asliDownload = laravel.downloadBackup;

    laravel.uploadBackup = async (id, buffer) => {
        storageBuffer = buffer;
    };
    laravel.downloadBackup = async () => storageBuffer;

    try {
        // Simpan
        await store.save(sessionId);

        // Hapus folder lokal seolah container dibuat ulang
        await rm(sessionDir, { recursive: true, force: true });
        assert.equal((await readdir(dataPath)).includes(sessionId), false);

        // Pulihkan
        await store.extract(sessionId);

        // Verifikasi seluruh berkas ada dan isinya identik
        const pulihEntries = await readdir(sessionDir);
        assert.deepEqual(pulihEntries.sort(), Object.keys(berkasUji).sort());

        for (const [nama, isi] of Object.entries(berkasUji)) {
            const terbaca = await readFile(path.join(sessionDir, nama), 'utf-8');
            assert.equal(terbaca, isi, `Isi berkas ${nama} harus sama persis`);
        }
    } finally {
        laravel.uploadBackup = asliUpload;
        laravel.downloadBackup = asliDownload;
    }
});

