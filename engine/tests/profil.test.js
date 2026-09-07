import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { mkdir, mkdtemp, writeFile } from 'node:fs/promises';
import { EventEmitter } from 'node:events';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { test } from 'node:test';

process.env.ENGINE_TOKEN = 'uji';
process.env.ENGINE_HMAC_SECRET = 'uji';
process.env.LARAVEL_URL = 'http://127.0.0.1:9';
process.env.ENGINE_LOG_LEVEL = 'silent';
process.env.WA_INIT_TIMEOUT_MS = '5000';
process.env.WA_DATA_PATH = await (async () => (await import('node:fs/promises')).mkdtemp((await import('node:path')).default.join((await import('node:os')).tmpdir(), 'flustra-data-')))();

const { petaProfil, pemegangProfil, bebaskanProfil, jalurProfil } = await import('../src/profil.js');
const { SessionManager } = await import('../src/session-manager.js');

/**
 * Proses sungguhan sebagai pengganti Chromium.
 *
 * Sengaja proses nyata: yang diuji adalah apakah sebuah PROSES berhenti hidup,
 * dan tiruan yang mencatat "kill dipanggil" membuktikan kita memanggil kill,
 * bukan bahwa prosesnya mati.
 */
function nyalakan() {
    return spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], { stdio: 'ignore' });
}

function hidup(pid) {
    try {
        process.kill(pid, 0);

        return true;
    } catch {
        return false;
    }
}

async function tungguMati(anak, batasMs = 5000) {
    if (anak.exitCode !== null || anak.signalCode !== null) return true;

    return new Promise((resolve) => {
        const timer = setTimeout(() => resolve(false), batasMs);
        anak.once('exit', () => {
            clearTimeout(timer);
            resolve(true);
        });
    });
}

/**
 * /proc tiruan yang menunjuk pid SUNGGUHAN.
 *
 * Pemilihannya diuji terhadap cmdline palsu; pembunuhannya terhadap proses
 * nyata. Keduanya sekaligus, karena bug yang sedang ditutup persis terletak di
 * antara keduanya — kode yang memilih dengan benar tapi tidak benar-benar
 * membunuh adalah kode yang sudah pernah kita punya.
 */
async function procTiruan(entri) {
    const akar = await mkdtemp(path.join(tmpdir(), 'flustra-profil-'));

    for (const [pid, argumen] of Object.entries(entri)) {
        await mkdir(path.join(akar, String(pid)), { recursive: true });
        await writeFile(path.join(akar, String(pid), 'cmdline'), argumen.join('\0'));
    }

    return akar;
}

// --- Pemilihan -------------------------------------------------------------

test('proses dikelompokkan per profil, dan duplikat terlihat', async () => {
    const proc = await procTiruan({
        // Persis yang terekam di produksi 8 September 2026.
        5314: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-01m005d2wdwz'],
        583075: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-01m005d2wdwz'],
        585979: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-01m005d2wdwz'],
        595440: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-01m005d2wdwz'],
        5313: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-01m1kk0r6fsx'],
        5316: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-01m005mg3set'],
        // Renderer: tidak membawa --user-data-dir, tidak ikut dihitung.
        5400: ['/chrome', '--type=renderer'],
        // Chromium aplikasi lain di container yang sama.
        7000: ['/usr/bin/chromium', '--user-data-dir=/tmp/lain'],
    });

    const peta = petaProfil({ proc });

    assert.equal(peta.size, 3, 'Tiga profil, bukan tiga proses.');
    assert.deepEqual(peta.get('/data/.wwebjs_auth/RemoteAuth-01m005d2wdwz'), [5314, 583075, 585979, 595440]);
    assert.deepEqual(peta.get('/data/.wwebjs_auth/RemoteAuth-01m1kk0r6fsx'), [5313]);
    assert.equal(peta.has('/tmp/lain'), false, 'Profil di luar pola RemoteAuth- tidak boleh ikut.');
});

/**
 * Pencocokan per ARGUMEN UTUH, bukan substring. Kalau salah, membebaskan
 * profil `RemoteAuth-abc` ikut membunuh sesi pelanggan `RemoteAuth-abcdef`.
 */
test('profil yang namanya berawalan sama tidak tertukar', async () => {
    const proc = await procTiruan({
        100: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-abc'],
        200: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-abcdef'],
    });

    assert.deepEqual(pemegangProfil('/data/.wwebjs_auth/RemoteAuth-abc', { proc }), [100]);
    assert.deepEqual(pemegangProfil('/data/.wwebjs_auth/RemoteAuth-abcdef', { proc }), [200]);
});

// --- Pembebasan ------------------------------------------------------------

test('proses yang memegang profil benar-benar dimatikan dan ditunggu', async () => {
    const anak = nyalakan();
    const profil = '/data/.wwebjs_auth/RemoteAuth-uji';
    const proc = await procTiruan({ [anak.pid]: ['/chrome', `--user-data-dir=${profil}`] });

    const hasil = await bebaskanProfil(profil, { proc });

    assert.deepEqual(hasil.dibunuh, [anak.pid]);
    assert.deepEqual(hasil.bandel, []);
    assert.equal(await tungguMati(anak), true);
    assert.equal(hidup(anak.pid), false);
});

test('profil milik sesi lain tidak ikut dibebaskan', async () => {
    const punyaKita = nyalakan();
    const punyaOrangLain = nyalakan();

    const proc = await procTiruan({
        [punyaKita.pid]: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-kita'],
        [punyaOrangLain.pid]: ['/chrome', '--user-data-dir=/data/.wwebjs_auth/RemoteAuth-lain'],
    });

    await bebaskanProfil('/data/.wwebjs_auth/RemoteAuth-kita', { proc });

    assert.equal(await tungguMati(punyaKita), true);
    assert.equal(hidup(punyaOrangLain.pid), true, 'Sesi pelanggan lain ikut mati.');

    punyaOrangLain.kill('SIGKILL');
    await tungguMati(punyaOrangLain);
});

test('profil yang memang bebas tidak menghasilkan apa-apa', async () => {
    const proc = await procTiruan({});

    const hasil = await bebaskanProfil('/data/.wwebjs_auth/RemoteAuth-kosong', { proc });

    assert.deepEqual(hasil.dibunuh, []);
    assert.deepEqual(hasil.bandel, []);
});

/**
 * SIGTERM dulu, SIGKILL kalau perlu. Bukan kesopanan: Chromium yang menerima
 * SIGTERM menutup LevelDB-nya rapi, sementara SIGKILL meninggalkan kunci dan
 * berkas setengah tertulis di profil yang sebentar lagi dipakai proses
 * berikutnya — persis kerusakan yang penjagaan ini cegah.
 */
test('SIGTERM dicoba lebih dulu sebelum SIGKILL', async () => {
    const proc = await procTiruan({ 4242: ['/chrome', '--user-data-dir=/p/RemoteAuth-x'] });

    const sinyal = [];
    let mati = false;

    const kill = (pid, sig) => {
        if (sig === 0) {
            if (mati) throw new Error('ESRCH');

            return true;
        }

        sinyal.push(sig);

        if (sig === 'SIGTERM') mati = true;

        return true;
    };

    const hasil = await bebaskanProfil('/p/RemoteAuth-x', { proc, kill, jedaTermMs: 1000 });

    assert.deepEqual(sinyal, ['SIGTERM'], 'SIGKILL dipakai padahal SIGTERM sudah cukup.');
    assert.deepEqual(hasil.dibunuh, [4242]);
});

test('proses yang menolak SIGTERM naik ke SIGKILL', async () => {
    const proc = await procTiruan({ 4242: ['/chrome', '--user-data-dir=/p/RemoteAuth-x'] });

    const sinyal = [];
    let mati = false;

    const kill = (pid, sig) => {
        if (sig === 0) {
            if (mati) throw new Error('ESRCH');

            return true;
        }

        sinyal.push(sig);

        if (sig === 'SIGKILL') mati = true;

        return true;
    };

    const hasil = await bebaskanProfil('/p/RemoteAuth-x', { proc, kill, jedaTermMs: 200 });

    assert.deepEqual(sinyal, ['SIGTERM', 'SIGKILL']);
    assert.deepEqual(hasil.bandel, []);
});

/**
 * Proses yang menolak mati bahkan setelah SIGKILL (D-state, menunggu I/O pada
 * disk yang sedang tersendat) dilaporkan bandel. Pemanggil HARUS membatalkan —
 * menyalakan Chromium baru di atas profil itu mengulang kerusakan yang sama.
 */
test('proses yang tidak bisa dimatikan dilaporkan bandel', async () => {
    const proc = await procTiruan({ 4242: ['/chrome', '--user-data-dir=/p/RemoteAuth-x'] });

    const kill = () => true; // tidak pernah mati

    const hasil = await bebaskanProfil('/p/RemoteAuth-x', {
        proc,
        kill,
        jedaTermMs: 100,
        jedaKillMs: 100,
    });

    assert.deepEqual(hasil.bandel, [4242]);
    assert.deepEqual(hasil.dibunuh, []);
});

// --- Urutan asli produksi --------------------------------------------------

/**
 * REPRODUKSI kejadian 8 September 2026, urut persis seperti aslinya, dan
 * dijalankan LEWAT `start()` — bukan lewat fungsi pembebasan yang dipanggil
 * langsung. Bedanya penting: yang rusak di produksi bukan kemampuan membunuh
 * proses melainkan tidak adanya siapa pun yang memanggilnya sebelum menyalakan
 * Chromium baru.
 *
 * 1. Sesi berjalan, Chromium hidup memegang profil.
 * 2. `initialize()` gagal; entry dihapus dari `#sessions`; `destroy()` bawaan
 *    mengembalikan sukses TANPA menutup apa pun (Client.js:1277-1284). Sesudah
 *    ini tidak ada satu pun struktur data di dalam Node yang tahu prosesnya
 *    masih hidup.
 * 3. `start()` dipanggil lagi untuk sesi yang SAMA.
 *
 * Sebelum penjagaan ini, langkah 3 menyalakan Chromium kedua di atas profil
 * yang masih dipegang yang pertama; keduanya saling menimpa LevelDB milik
 * WhatsApp Web, dan yang kalah mati dengan `Execution context was destroyed`.
 */
test('start() membunuh pemegang profil lama lebih dulu, lalu menyisakan tepat satu', async () => {
    const dataPath = process.env.WA_DATA_PATH;
    const sessionId = 'sesi-tabrakan';
    const profil = jalurProfil(dataPath, sessionId);

    // Langkah 1-2: proses lama hidup, tidak dikenal siapa pun di dalam Node.
    const lama = nyalakan();
    const proc = await procTiruan({ [lama.pid]: ['/chrome', `--user-data-dir=${profil}`] });

    let dibuat = 0;

    const manager = new SessionManager({
        procRoot: proc,
        buatClient: () => {
            dibuat++;

            const c = new EventEmitter();
            c.initialize = () => new Promise(() => {});
            c.destroy = async () => {};
            c.pupBrowser = undefined;

            return c;
        },
    });

    // Langkah 3.
    await manager.start(sessionId);

    assert.equal(
        await tungguMati(lama),
        true,
        'Chromium lama masih hidup setelah start(). Dua proses akan berebut satu profil.',
    );
    assert.equal(hidup(lama.pid), false);
    assert.equal(dibuat, 1, 'Client baru harus dibuat tepat sekali.');
    assert.deepEqual(manager.listSessionIds(), [sessionId]);

    // Pohon /proc tiruan bersifat statis, jadi ia tetap mencantumkan pid lama.
    // Yang punya arti adalah berapa pemegang yang masih HIDUP: itulah invarian
    // yang dilanggar produksi, dan harus nol di sini karena client barunya
    // tiruan dan tidak menyalakan proses apa pun.
    const masihHidup = pemegangProfil(profil, { proc }).filter(hidup);

    assert.deepEqual(masihHidup, [], 'Masih ada pemegang profil yang hidup setelah start().');

    await manager.stop(sessionId);
});

/**
 * `start()` harus MEMBATALKAN, bukan melanjutkan, saat profilnya tidak bisa
 * dibebaskan. Melanjutkan berarti sengaja mengulang kerusakan yang sama.
 *
 * Yang ditiru: proses D-state yang menunggu I/O pada disk tersendat — ia tidak
 * menjawab SIGTERM maupun SIGKILL selama masih di dalam syscall.
 */
test('start() membatalkan dan tidak membuat client saat profil tidak bisa dibebaskan', async () => {
    const dataPath = process.env.WA_DATA_PATH;
    const sessionId = 'sesi-bandel';
    const profil = jalurProfil(dataPath, sessionId);

    const proc = await procTiruan({
        4242: ['/chrome', `--user-data-dir=${profil}`],
    });

    let dibuat = 0;

    const manager = new SessionManager({
        procRoot: proc,
        // Selalu menjawab "masih hidup" apa pun sinyalnya. Proses yang benar-
        // benar menolak SIGKILL tidak bisa dibuat-buat di dalam uji, dan
        // memakai pid proses uji sendiri sebagai umpan justru membunuh penguji.
        killProses: () => true,
        buatClient: () => {
            dibuat++;

            return new EventEmitter();
        },
    });

    await assert.rejects(
        manager.start(sessionId),
        /masih dipegang proses/,
        'start() melanjutkan padahal profilnya tidak bebas.',
    );

    assert.equal(dibuat, 0, 'Client dibuat padahal profilnya masih dipegang.');
    assert.deepEqual(manager.listSessionIds(), [], 'Sesi gagal tidak boleh tercatat sebagai berjalan.');
});

/**
 * Pembebasan menunggu proses lama benar-benar mati, dan penantian itu membuka
 * celah yang dulu tidak ada: permintaan start kedua yang datang di tengahnya.
 * Justru di celah itulah Chromium kedua akan lahir di atas profil yang sedang
 * dibersihkan untuk yang pertama.
 */
test('permintaan start kedua di tengah pembebasan ditolak, bukan diladeni', async () => {
    const dataPath = process.env.WA_DATA_PATH;
    const sessionId = 'sesi-berbarengan';
    const profil = jalurProfil(dataPath, sessionId);

    const lama = nyalakan();
    const proc = await procTiruan({ [lama.pid]: ['/chrome', `--user-data-dir=${profil}`] });

    let dibuat = 0;

    const manager = new SessionManager({
        procRoot: proc,
        buatClient: () => {
            dibuat++;

            const c = new EventEmitter();
            c.initialize = () => new Promise(() => {});
            c.destroy = async () => {};

            return c;
        },
    });

    const pertama = manager.start(sessionId);
    const kedua = manager.start(sessionId);

    await assert.rejects(kedua, /sedang dalam proses dijalankan/);
    await pertama;

    assert.equal(dibuat, 1, 'Dua client dibuat untuk satu sesi.');

    await tungguMati(lama);
    await manager.stop(sessionId);
});
