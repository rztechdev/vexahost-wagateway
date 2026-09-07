import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import process from 'node:process';
import { test } from 'node:test';

process.env.ENGINE_TOKEN = 'uji';
process.env.ENGINE_HMAC_SECRET = 'uji';
process.env.LARAVEL_URL = 'http://localhost:8070';
// Bunyi peringatan "dimatikan paksa" memang yang diuji di sini; disenyapkan
// supaya keluaran uji tidak tercampur baris log.
process.env.ENGINE_LOG_LEVEL = 'silent';

const { tutupChromium, hitungChromiumHidup, keadaanChromium } = await import('../src/chromium.js');

/**
 * Proses sungguhan sebagai pengganti Chromium.
 *
 * Sengaja proses nyata, bukan objek tiruan: yang sedang diuji adalah apakah
 * sebuah PROSES benar-benar mati, dan tiruan yang mencatat "kill dipanggil"
 * membuktikan kita memanggil kill, bukan bahwa prosesnya berhenti hidup. Bug
 * ini gagal dalam diam justru karena semua yang di atas proses tampak benar.
 */
function nyalakanProses() {
    const anak = spawn(process.execPath, ['-e', 'setInterval(() => {}, 1000)'], {
        stdio: 'ignore',
    });

    return anak;
}

function masihHidup(anak) {
    if (anak.exitCode !== null || anak.signalCode !== null) return false;

    try {
        // Sinyal 0 tidak mengirim apa pun; ia cuma menanyakan apakah pid ada.
        process.kill(anak.pid, 0);

        return true;
    } catch {
        return false;
    }
}

function tungguMati(anak, batasMs = 5000) {
    if (anak.exitCode !== null || anak.signalCode !== null) return Promise.resolve(true);

    return new Promise((resolve) => {
        const timer = setTimeout(() => resolve(false), batasMs);

        anak.once('exit', () => {
            clearTimeout(timer);
            resolve(true);
        });
    });
}

/**
 * Client tiruan yang meniru `Client.destroy()` milik whatsapp-web.js PERSIS,
 * termasuk cacatnya (Client.js:1277-1284):
 *
 *     const isConnected = browser?.isConnected?.();
 *     if (isConnected) { await browser.close(); }
 *     await this.authStrategy.destroy();
 */
function clientTiruan(anak, { terhubung, closeMenggantung = false, destroyMelempar = false }) {
    const client = {
        pupBrowser: {
            isConnected: () => terhubung,
            process: () => anak,
            close: async () => {
                if (closeMenggantung) {
                    await new Promise(() => {});
                }

                anak.kill('SIGTERM');
                await tungguMati(anak);
            },
        },
        authStrategy: {
            // Yang dilakukan RemoteAuth.destroy() cuma clearInterval().
            destroy: async () => {},
        },
        async destroy() {
            if (destroyMelempar) {
                throw new Error('Protocol error: Target closed');
            }

            const terhubungSekarang = this.pupBrowser?.isConnected?.();

            if (terhubungSekarang) {
                await this.pupBrowser.close();
            }

            await this.authStrategy.destroy();
        },
    };

    return client;
}

/**
 * REPRODUKSI A1 — membuktikan bugnya ada sebelum membuktikan perbaikannya.
 *
 * Ini keadaan yang paling mungkin terjadi saat memori menipis: proses Chromium
 * masih hidup, tapi websocket CDP-nya sudah putus sehingga `isConnected()`
 * menjawab false. `destroy()` melewati `browser.close()`, tidak melempar apa
 * pun, dan kembali dengan sukses.
 */
test('destroy() bawaan whatsapp-web.js membiarkan Chromium hidup saat isConnected() palsu', async () => {
    const anak = nyalakanProses();
    const client = clientTiruan(anak, { terhubung: false });

    // Tidak melempar. Inilah yang membuat bug ini tidak terlihat di log mana pun.
    await client.destroy();

    assert.equal(
        masihHidup(anak),
        true,
        'Reproduksi gagal: prosesnya sudah mati, padahal justru itu bugnya.',
    );

    anak.kill('SIGKILL');
    await tungguMati(anak);
});

test('tutupChromium mematikan proses yang ditinggalkan destroy() saat isConnected() palsu', async () => {
    const anak = nyalakanProses();
    const client = clientTiruan(anak, { terhubung: false });

    const hasil = await tutupChromium(client, 'uji-a1');

    assert.equal(hasil.rapi, true, 'destroy() memang tidak melempar; ia cuma tidak menutup apa pun.');
    assert.equal(hasil.dibunuh, true, 'Proses sisa seharusnya dimatikan paksa.');
    assert.equal(await tungguMati(anak), true, 'Proses Chromium harus benar-benar mati.');
    assert.equal(masihHidup(anak), false);
});

test('penutupan yang benar-benar rapi tidak menghasilkan pembunuhan paksa', async () => {
    const anak = nyalakanProses();
    const client = clientTiruan(anak, { terhubung: true });

    const hasil = await tutupChromium(client, 'uji-rapi');

    assert.equal(hasil.rapi, true);
    assert.equal(
        hasil.dibunuh,
        false,
        'SIGKILL pada jalur yang sudah bersih akan membuat sinyal "Chromium yatim" jadi bising dan tidak berguna.',
    );
    assert.equal(masihHidup(anak), false);
});

test('destroy() yang menggantung dibatasi waktu, lalu prosesnya dimatikan', async () => {
    const anak = nyalakanProses();
    const client = clientTiruan(anak, { terhubung: true, closeMenggantung: true });

    const mulai = Date.now();
    const hasil = await tutupChromium(client, 'uji-gantung', { batasMs: 300 });
    const lama = Date.now() - mulai;

    assert.equal(hasil.rapi, false);
    assert.match(hasil.err, /tidak selesai dalam 300 ms/);
    assert.ok(lama < 3000, `tutupChromium menunggu ${lama} ms; batasnya tidak berlaku.`);
    assert.equal(hasil.dibunuh, true);
    assert.equal(await tungguMati(anak), true);
});

test('destroy() yang melempar tetap berakhir dengan Chromium mati', async () => {
    const anak = nyalakanProses();
    const client = clientTiruan(anak, { terhubung: true, destroyMelempar: true });

    const hasil = await tutupChromium(client, 'uji-lempar');

    assert.equal(hasil.rapi, false);
    assert.match(hasil.err, /Target closed/);
    assert.equal(hasil.dibunuh, true);
    assert.equal(await tungguMati(anak), true);
});

/**
 * `start()` bisa gagal sebelum browsernya sempat lahir — Chromium tidak ada di
 * image, argumen ditolak. Penutupan tidak boleh ikut melempar di situ: kalau
 * ia melempar, ia melempar dari dalam blok catch `initialize()`, yang berujung
 * unhandledRejection dan tidak menutup apa pun.
 */
test('client tanpa browser sama sekali tidak menjatuhkan penutupan', async () => {
    const client = {
        pupBrowser: undefined,
        destroy: async () => {},
    };

    const hasil = await tutupChromium(client, 'uji-tanpa-browser');

    assert.equal(hasil.rapi, true);
    assert.equal(hasil.dibunuh, false);
});

test('browser yang sudah keluar sendiri tidak dibunuh dua kali', async () => {
    const anak = nyalakanProses();

    anak.kill('SIGKILL');
    await tungguMati(anak);

    const client = clientTiruan(anak, { terhubung: false });
    const hasil = await tutupChromium(client, 'uji-sudah-mati');

    assert.equal(hasil.dibunuh, false, 'Proses yang exitCode/signalCode-nya terisi tidak boleh disentuh lagi.');
});

// --- Penghitung Chromium untuk /health (A6) --------------------------------

import { mkdtemp, mkdir, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';

async function procTiruan(entri) {
    const akar = await mkdtemp(path.join(tmpdir(), 'flustra-proc-'));

    for (const [pid, cmdline] of Object.entries(entri)) {
        await mkdir(path.join(akar, pid), { recursive: true });
        // /proc/<pid>/cmdline dipisahkan NUL, bukan spasi.
        await writeFile(path.join(akar, pid, 'cmdline'), cmdline.split(' ').join('\0'));
    }

    return akar;
}

test('hanya proses browser milik kita yang dihitung', async () => {
    const proc = await procTiruan({
        101: '/app/engine/.puppeteer/chrome/chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        102: '/app/engine/.puppeteer/chrome/chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-b',
        // Renderer: tidak membawa --user-data-dir, jadi tidak ikut dihitung.
        103: '/app/engine/.puppeteer/chrome/chrome --type=renderer --lang=en-US',
        // Chromium milik aplikasi lain di container yang sama.
        104: '/usr/bin/chromium --user-data-dir=/tmp/aplikasi-lain',
        105: '/usr/sbin/mysqld --datadir=/var/lib/mysql',
        // Bukan pid.
        self: 'apa pun',
    });

    assert.equal(hitungChromiumHidup({ proc }), 2);
});

/**
 * Sinyal per profil — pelajaran dari produksi 8 September 2026.
 *
 * Angka lama membandingkan TOTAL PROSES dengan jumlah sesi. Itu benar tapi
 * tumpul: yang merusak bukan "ada proses berlebih" melainkan "satu profil
 * dipegang beberapa proses sekaligus", karena dua Chromium pada satu
 * userDataDir saling menimpa state WhatsApp Web.
 */
test('duplikat per profil terhitung, bukan sekadar total proses', async () => {
    const proc = await procTiruan({
        // Angka persis dari server malam itu.
        5314: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        583075: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        585979: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        595440: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        5313: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-b',
        5316: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-c',
    });

    const k = keadaanChromium(['a', 'b', 'c'], { proc });

    assert.equal(k.proses, 6);
    assert.equal(k.profil, 3, 'Tiga profil untuk tiga sesi: jumlah profilnya sendiri wajar.');
    assert.equal(k.duplikat, 3, 'Tiga proses berlebih pada satu profil — inilah yang merusak.');
    assert.equal(k.yatim, 0, 'Ketiga profil memang milik sesi yang berjalan.');
    assert.equal(k.bocor, 3);
});

test('keadaan sehat menghasilkan nol di seluruh angka kebocoran', async () => {
    const proc = await procTiruan({
        1: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        2: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-b',
        3: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-c',
    });

    const k = keadaanChromium(['a', 'b', 'c'], { proc });

    assert.equal(k.duplikat, 0);
    assert.equal(k.yatim, 0);
    assert.equal(k.bocor, 0);
});

/**
 * Profil yang tidak dimiliki sesi mana pun: sisa yang tidak pernah ditutup.
 * Bentuk kebocoran yang berbeda dari duplikat, dan sama-sama harus terlihat.
 */
test('profil tanpa sesi terhitung yatim', async () => {
    const proc = await procTiruan({
        1: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-a',
        2: 'chrome --user-data-dir=/data/.wwebjs_auth/RemoteAuth-sisa',
    });

    const k = keadaanChromium(['a'], { proc });

    assert.equal(k.yatim, 1);
    assert.equal(k.duplikat, 0);
    assert.equal(k.bocor, 1);
});

test('tanpa /proc jawabannya null, bukan nol', () => {
    assert.equal(
        keadaanChromium([], { proc: '/jalur/yang/tidak/ada' }),
        null,
        'Nol berarti "sudah diperiksa dan aman" — kebohongan untuk sesuatu yang tidak bisa diperiksa.',
    );
    assert.equal(hitungChromiumHidup({ proc: '/jalur/yang/tidak/ada' }), null);
});
