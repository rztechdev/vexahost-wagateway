import assert from 'node:assert/strict';
import { EventEmitter } from 'node:events';
import process from 'node:process';
import { test } from 'node:test';

process.env.ENGINE_TOKEN = 'uji';
process.env.ENGINE_HMAC_SECRET = 'uji';
// Port yang tidak ada yang mendengarkan: `laravel.event()` memang menelan
// kegagalan jaringan (laravel.js:92-98), jadi uji ini tidak butuh server.
process.env.LARAVEL_URL = 'http://127.0.0.1:9';
process.env.ENGINE_LOG_LEVEL = 'silent';
// Dipendekkan drastis dari 180 detik supaya uji ini selesai dalam hitungan
// milidetik. Yang diuji perilakunya, bukan angkanya.
process.env.WA_INIT_TIMEOUT_MS = '150';

const { SessionManager } = await import('../src/session-manager.js');

/**
 * Client tiruan: EventEmitter yang tidak pernah menyelesaikan initialize().
 *
 * Ini persis keadaan A4 di produksi \u2014 Chromium menyala, WhatsApp Web tidak
 * pernah selesai memuat, dan tidak ada satu pun galat maupun event yang keluar.
 */
function clientMenggantung() {
    const client = new EventEmitter();

    client.ditutup = false;
    client.initialize = () => new Promise(() => {});
    client.destroy = async () => {
        client.ditutup = true;
    };
    client.pupBrowser = undefined;

    return client;
}

function tunggu(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
}

test('sesi yang menggantung tanpa gagal dihentikan setelah batas waktu', async () => {
    const client = clientMenggantung();
    const manager = new SessionManager({ buatClient: () => client });

    await manager.start('sesi-gantung');

    assert.deepEqual(manager.listSessionIds(), ['sesi-gantung']);
    assert.equal(manager.status('sesi-gantung').status, 'connecting');

    await tunggu(400);

    assert.deepEqual(
        manager.listSessionIds(),
        [],
        'Sesi yang menggantung harus dilepas, kalau tidak ia menahan satu slot selamanya.',
    );
    assert.equal(client.ditutup, true, 'Chromium-nya harus ikut ditutup, bukan cuma entry-nya dibuang.');
    assert.equal(manager.status('sesi-gantung'), null);
});

test('slot kembali bebas setelah sesi menggantung dihentikan', async () => {
    const manager = new SessionManager({ buatClient: () => clientMenggantung() });

    await manager.start('a');
    await tunggu(400);

    // Kalau pengukurnya tidak bekerja, sesi 'a' masih memegang slotnya dan
    // start() berikutnya akan menabrak batas WA_MAX_SESSIONS lebih cepat.
    await manager.start('b');

    assert.deepEqual(manager.listSessionIds(), ['b']);
});

test('event qr melepas pengukur \u2014 menunggu manusia men-scan tidak boleh dibatasi waktu', async () => {
    const client = clientMenggantung();
    const manager = new SessionManager({ buatClient: () => client });

    await manager.start('sesi-qr');

    client.emit('qr', 'kode-qr-palsu');

    await tunggu(400);

    assert.deepEqual(
        manager.listSessionIds(),
        ['sesi-qr'],
        'Sesi yang sudah menampilkan QR tidak boleh dihentikan; yang ditunggu manusia.',
    );
    assert.equal(manager.status('sesi-qr').status, 'qr');
    assert.equal(client.ditutup, false);
});

test('loading_screen melepas pengukur \u2014 riwayat chat besar memang lama', async () => {
    const client = clientMenggantung();
    const manager = new SessionManager({ buatClient: () => client });

    await manager.start('sesi-muat');

    client.emit('loading_screen', 42);

    await tunggu(400);

    assert.deepEqual(manager.listSessionIds(), ['sesi-muat']);
    assert.equal(manager.status('sesi-muat').loading_percent, 42);
});

test('sesi yang siap tidak pernah tersentuh pengukur', async () => {
    const client = clientMenggantung();
    client.info = { wid: { user: '628123' }, pushname: 'Uji' };

    const manager = new SessionManager({ buatClient: () => client });

    await manager.start('sesi-siap');

    client.emit('ready');
    await tunggu(400);

    assert.deepEqual(manager.listSessionIds(), ['sesi-siap']);
    assert.equal(manager.status('sesi-siap').status, 'connected');
    assert.equal(client.ditutup, false);
});

test('stop() melepas pengukur, jadi sesi yang sudah dihentikan tidak dihentikan dua kali', async () => {
    const client = clientMenggantung();
    const manager = new SessionManager({ buatClient: () => client });

    await manager.start('sesi-stop');
    await manager.stop('sesi-stop');

    client.ditutup = false;

    // Kalau pengukurnya masih menyala, ia akan menembak stop() kedua kalinya di
    // sini \u2014 dan pada sesi yang sudah tidak ada, atau lebih buruk, pada sesi
    // baru yang kebetulan memakai id yang sama.
    await manager.start('sesi-stop');
    client.emit('ready');
    await tunggu(400);

    assert.deepEqual(manager.listSessionIds(), ['sesi-stop']);
    assert.equal(client.ditutup, false, 'Pengukur milik sesi lama membunuh sesi baru yang memakai id yang sama.');
});
