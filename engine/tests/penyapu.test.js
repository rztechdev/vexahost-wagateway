import assert from 'node:assert/strict';
import { mkdir, mkdtemp, readdir, utimes, writeFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import path from 'node:path';
import process from 'node:process';
import { test } from 'node:test';

process.env.ENGINE_TOKEN = 'uji';
process.env.ENGINE_HMAC_SECRET = 'uji';
process.env.LARAVEL_URL = 'http://127.0.0.1:9';
process.env.ENGINE_LOG_LEVEL = 'silent';

const { sapuFolderYatim } = await import('../src/penyapu.js');

const HARI = 24 * 60 * 60 * 1000;

async function folder(akar, nama, umurHari) {
    const jalur = path.join(akar, nama);

    await mkdir(path.join(jalur, 'Default'), { recursive: true });
    await writeFile(path.join(jalur, 'Default', 'Cookies'), 'isi');

    const waktu = new Date(Date.now() - umurHari * HARI);
    await utimes(jalur, waktu, waktu);

    return jalur;
}

test('folder yang lama ditinggalkan dibuang', async () => {
    const akar = await mkdtemp(path.join(tmpdir(), 'flustra-sapu-'));

    await folder(akar, 'RemoteAuth-lama', 30);

    const hasil = await sapuFolderYatim({ dataPath: akar });

    assert.deepEqual(hasil.dibuang, ['lama']);
    assert.deepEqual(await readdir(akar), []);
});

test('folder yang baru disentuh tidak pernah dibuang', async () => {
    const akar = await mkdtemp(path.join(tmpdir(), 'flustra-sapu-'));

    await folder(akar, 'RemoteAuth-baru', 1);

    const hasil = await sapuFolderYatim({ dataPath: akar });

    assert.deepEqual(hasil.dibuang, []);
    assert.deepEqual(await readdir(akar), ['RemoteAuth-baru']);
});

/**
 * Penjagaan yang paling penting di berkas ini. Sesi yang akan dipulihkan
 * dikecualikan secara eksplisit, jadi keamanannya tidak bergantung pada mtime
 * sama sekali — folder milik nomor pelanggan yang masih dipakai tidak boleh
 * bisa terhapus walau umurnya kebetulan tua.
 */
test('sesi yang akan dipulihkan tidak pernah disentuh walau foldernya tua', async () => {
    const akar = await mkdtemp(path.join(tmpdir(), 'flustra-sapu-'));

    await folder(akar, 'RemoteAuth-dipakai', 90);
    await folder(akar, 'RemoteAuth-yatim', 90);

    const hasil = await sapuFolderYatim({ dataPath: akar, kecuali: ['dipakai'] });

    assert.deepEqual(hasil.dibuang, ['yatim']);
    assert.deepEqual(await readdir(akar), ['RemoteAuth-dipakai']);
});

test('berkas dan folder di luar pola RemoteAuth- tidak disentuh', async () => {
    const akar = await mkdtemp(path.join(tmpdir(), 'flustra-sapu-'));

    await folder(akar, 'RemoteAuth-yatim', 90);
    await folder(akar, 'sesuatu-yang-lain', 90);

    // Zip cadangan yang sedang ditulis RemoteAuth berada di folder yang sama.
    const zip = path.join(akar, 'RemoteAuth-yatim.zip');
    await writeFile(zip, 'zip');
    const tua = new Date(Date.now() - 90 * HARI);
    await utimes(zip, tua, tua);

    await sapuFolderYatim({ dataPath: akar });

    const tersisa = (await readdir(akar)).sort();

    assert.deepEqual(tersisa, ['RemoteAuth-yatim.zip', 'sesuatu-yang-lain']);
});

test('folder induk yang belum ada bukan galat', async () => {
    const hasil = await sapuFolderYatim({ dataPath: path.join(tmpdir(), 'flustra-tidak-ada-'.concat(Date.now())) });

    assert.deepEqual(hasil.dibuang, []);
});
