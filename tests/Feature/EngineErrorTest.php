<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use App\Support\EngineError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Galat engine tidak boleh sampai ke layar pelanggan dalam bentuk mentahnya.
 *
 * Yang pernah tampil di modal, apa adanya:
 *
 *   "Gagal menghubungi engine: cURL error 7: Failed to connect to 127.0.0.1
 *    port 3100 after 2034 ms ... for http://127.0.0.1:3100/sessions/01m1t.../start"
 *
 * Ia membocorkan alamat dan port internal beserta ULID sesi ke layar yang bisa
 * di-screenshot siapa saja, tidak bisa ditindaklanjuti oleh pembacanya, dan
 * terbaca seperti kerusakan permanen — padahal engine yang sedang di-deploy
 * ulang tidak memutus satu pun nomor yang sudah tertaut.
 */
class EngineErrorTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::create([
            'name' => 'Ryan',
            'email' => 'ryan@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
        $this->berlangganan($this->workspace, 'prime');
    }

    public function test_engine_tidak_terjangkau_menghasilkan_kalimat_yang_bisa_dibaca(): void
    {
        $pesan = EngineError::pesan(new ConnectionException(
            'cURL error 7: Failed to connect to 127.0.0.1 port 3100 after 2034 ms: '
            .'Couldn\'t connect to server (see https://curl.se/libcurl/c/libcurl-errors.html) '
            .'for http://127.0.0.1:3100/sessions/01m1ttj3m6n8pstm4b593srq5r/start'
        ));

        // Yang paling ditanyakan orang saat layar ini muncul: nomor saya hilang?
        $this->assertStringContainsString('tidak terputus', $pesan);

        foreach (['cURL', '127.0.0.1', '3100', 'curl.se', '01m1ttj3m6n8pstm4b593srq5r'] as $bocor) {
            $this->assertStringNotContainsString($bocor, $pesan);
        }
    }

    public function test_batas_kapasitas_tidak_menyebut_nama_variabel_env(): void
    {
        $pesan = EngineError::pesan(new RuntimeException(
            'Engine sudah menjalankan 3 sesi (batas WA_MAX_SESSIONS).'
        ));

        $this->assertStringNotContainsString('WA_MAX_SESSIONS', $pesan);
        $this->assertStringContainsString('penuh', $pesan);
    }

    public function test_pesan_yang_memang_untuk_manusia_dibiarkan_apa_adanya(): void
    {
        $asli = 'Nomor tujuan tidak terdaftar di WhatsApp.';

        $this->assertSame($asli, EngineError::pesan(new RuntimeException($asli)));
    }

    /**
     * Dan lewat jalur sungguhan: tombol Hubungkan saat engine mati.
     */
    public function test_tombol_hubungkan_tidak_menampilkan_galat_mentah(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'pending',
        ]);

        Http::fake(fn () => throw new ConnectionException(
            'cURL error 7: Failed to connect to 127.0.0.1 port 3100 after 2034 ms'
        ));

        $response = $this->actingAs($this->owner)
            ->post(route('sessions.connect', $session->id));

        $galat = $response->baseResponse->getSession()->get('errors')->first('session');

        $this->assertStringNotContainsString('cURL', $galat);
        $this->assertStringNotContainsString('127.0.0.1', $galat);

        // Dan yang tersimpan di baris sesi juga bersih — kolom itu ikut tampil
        // di kartu sesi dan di modal QR, jadi ia sama-sama muka pengguna.
        $this->assertStringNotContainsString('cURL', (string) $session->fresh()->last_error);

        // Sesinya tetap `disconnected`, bukan `failed`: engine yang tidak
        // terjangkau adalah keadaan sementara, dan `failed` mengeluarkan sesi
        // dari pemulihan otomatis saat engine hidup lagi.
        $this->assertSame('disconnected', $session->fresh()->status);
    }

    /**
     * TIDAK ADA tombol sesi yang boleh menghasilkan 500 saat engine mati.
     *
     * `connect` sudah dijaga sejak awal, tapi `disconnect` dan `logout` sama
     * sekali tidak punya penanganan — menekan "Putus tautan" saat engine tidak
     * menjawab melempar `ConnectionException` mentah dan pelanggan mendarat di
     * halaman Internal Server Error lengkap dengan jejak tumpukan, nama berkas,
     * dan potongan source code kami. Tes ini menyapu semuanya sekaligus supaya
     * tombol baru tidak lolos dengan lubang yang sama.
     */
    public function test_tidak_ada_tombol_sesi_yang_500_saat_engine_mati(): void
    {
        Http::fake(fn () => throw new ConnectionException(
            'cURL error 28: Operation timed out after 15002 milliseconds with 0 bytes received '
            .'for http://127.0.0.1:3100/sessions/01m1ttj3m6n8pstm4b593srq5r/logout'
        ));

        foreach (['connect', 'disconnect', 'logout'] as $aksi) {
            $session = $this->workspace->sessions()->create([
                'name' => 'CS '.$aksi,
                'status' => 'connected',
                'phone_number' => '6281111111111',
                'connected_at' => now(),
            ]);

            $response = $this->actingAs($this->owner)
                ->post(route("sessions.{$aksi}", $session->id));

            $this->assertNotSame(500, $response->status(), "Tombol {$aksi} menghasilkan 500 saat engine mati.");
            $response->assertRedirect();
        }

        // Dan Hapus tetap berhasil walau engine mati — kalau tidak, sesi hantu
        // yang tidak bisa diapa-apakan menumpuk di dashboard pelanggan.
        $session = $this->workspace->sessions()->create(['name' => 'CS hapus', 'status' => 'connected']);

        $this->actingAs($this->owner)
            ->delete(route('sessions.destroy', $session->id))
            ->assertRedirect(route('sessions.index'));

        // `fresh()` tidak dipakai: WaSession memakai hapus lunak, jadi barisnya
        // memang masih ada — yang penting ia hilang dari daftar pelanggan.
        $this->assertSoftDeleted($session);
        $this->assertNotContains(
            $session->id,
            $this->workspace->sessions()->pluck('id')->all(),
            'Sesi yang dihapus masih muncul di daftar workspace.'
        );
    }

    /**
     * Putus tautan yang gagal TIDAK boleh berpura-pura berhasil.
     *
     * Kegagalan tersering di sini adalah habis waktu 15 detik — bukan bukti
     * perintahnya tidak sampai. Menandai nomornya terputus padahal engine masih
     * memegangnya menghasilkan dashboard yang bilang "belum tertaut" sementara
     * nomor aslinya masih menerima pesan.
     */
    public function test_putus_tautan_yang_gagal_tidak_mengubah_keadaan_nomor(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $this->actingAs($this->owner)->post(route('sessions.logout', $session->id));

        $session->refresh();

        $this->assertSame('connected', $session->status);
        $this->assertSame('6281111111111', $session->phone_number);
        $this->assertNotNull($session->connected_at);
    }

    /**
     * Sebaliknya, Hentikan memang boleh menandai berhenti: engine yang tidak
     * menjawab berarti ia tidak sedang menjalankan sesi ini juga, dan tombol
     * yang tidak pernah bisa berhasil lebih buruk daripada baris yang jujur.
     */
    public function test_hentikan_tetap_menandai_berhenti_saat_engine_mati(): void
    {
        $session = $this->workspace->sessions()->create([
            'name' => 'CS',
            'status' => 'connected',
            'phone_number' => '6281111111111',
            'connected_at' => now(),
        ]);

        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect'));

        $this->actingAs($this->owner)->post(route('sessions.disconnect', $session->id));

        $this->assertSame('disconnected', $session->fresh()->status);

        // Nomornya tidak ikut dilepas — Hentikan bukan Putus tautan.
        $this->assertSame('6281111111111', $session->fresh()->phone_number);
    }
}
