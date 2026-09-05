<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Langganan dari sisi pelanggan: memilih paket, menerima tagihan, membayar,
 * dan apa yang terjadi saat masa berlakunya habis.
 */
class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

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
            'max_sessions' => 1,
            'monthly_message_quota' => 1000,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);
    }

    private function langganan()
    {
        return app(SubscriptionService::class)->ensureFor($this->workspace->fresh());
    }

    public function test_workspace_tanpa_langganan_langsung_dapat_masa_percobaan(): void
    {
        $this->actingAs($this->owner)->get(route('billing.index'))->assertOk();

        $subscription = $this->workspace->fresh()->subscription;

        $this->assertSame('trialing', $subscription->status);
        $this->assertSame('essentials', $subscription->plan_slug);

        // Berakhir di akhir bulan berjalan — sama untuk semua orang, supaya
        // pengingat bisa dikirim serentak dan bukan setiap hari untuk seseorang.
        $this->assertTrue($subscription->current_period_end->isSameDay(now()->endOfMonth()));
    }

    /**
     * Empat alamat terpisah, bukan satu halaman panjang.
     *
     * Yang dijaga di sini bukan tata letaknya, tapi bahwa tiap langkah punya
     * URL sendiri yang bisa ditautkan langsung — dari spanduk, dari pengingat
     * WhatsApp, dari panel admin. Menggabungkannya lagi akan membuat tautan
     * itu mendarat di halaman yang benar tapi di bagian yang salah.
     */
    public function test_tiap_langkah_langganan_punya_alamat_sendiri(): void
    {
        foreach (['billing.index', 'billing.plans', 'billing.history'] as $rute) {
            $this->actingAs($this->owner)->get(route($rute))->assertOk();
        }
    }

    public function test_halaman_paket_menampilkan_seluruh_katalog(): void
    {
        $this->actingAs($this->owner)
            ->get(route('billing.plans'))
            ->assertOk()
            ->assertSee('Essentials')
            ->assertSee('Prime')
            ->assertSee('Elite')
            ->assertSee('149.000');
    }

    /**
     * Ringkasan hanya memuat tagihan yang masih butuh tindakan. Tagihan yang
     * sudah selesai tidak boleh berbagi tempat dengan yang sedang ditunggu.
     */
    public function test_ringkasan_hanya_menampilkan_tagihan_yang_masih_terbuka(): void
    {
        $lunas = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        app(SubscriptionService::class)->markPaid($lunas);

        $terbuka = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertSee($terbuka->number)
            ->assertDontSee($lunas->number);

        // Riwayat memuat keduanya.
        $this->actingAs($this->owner)
            ->get(route('billing.history'))
            ->assertOk()
            ->assertSee($terbuka->number)
            ->assertSee($lunas->number);
    }

    public function test_memilih_paket_menerbitkan_tagihan_dengan_kode_unik(): void
    {
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'prime', 'period' => 'monthly'])
            ->assertRedirect();

        $invoice = Invoice::first();

        $this->assertSame('prime', $invoice->plan_slug);
        $this->assertSame(249_000, $invoice->amount);
        $this->assertGreaterThan(0, $invoice->unique_code);
        $this->assertSame($invoice->amount + $invoice->tax_amount + $invoice->unique_code, $invoice->total);
        $this->assertStringStartsWith('INV-WA-', $invoice->number);
    }

    public function test_harga_tahunan_sepuluh_kali_harga_bulanan(): void
    {
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'elite', 'period' => 'yearly']);

        $this->assertSame(4_490_000, Invoice::first()->amount);
    }

    /**
     * Mengklik "Bayar" dua kali tidak boleh menghasilkan dua nomor tagihan:
     * keduanya akan punya kode unik berbeda, pelanggan membayar salah satunya,
     * dan yang lain menggantung sampai kedaluwarsa.
     */
    public function test_klik_bayar_dua_kali_tidak_membuat_dua_tagihan(): void
    {
        $this->actingAs($this->owner)->post(route('billing.checkout'), ['plan' => 'prime', 'period' => 'monthly']);
        $this->actingAs($this->owner)->post(route('billing.checkout'), ['plan' => 'prime', 'period' => 'monthly']);

        $this->assertSame(1, Invoice::count());
    }

    public function test_pembayaran_menerapkan_batas_paket_ke_workspace(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'elite', 'monthly');

        app(SubscriptionService::class)->markPaid($invoice);

        $workspace = $this->workspace->fresh();

        // Batas ditegakkan dari kolom workspace, jadi pembayaranlah yang harus
        // menyalinnya ke sana — kalau tidak, pelanggan membayar Elite dan tetap
        // dibatasi angka Essentials tanpa satu pun pesan galat yang menjelaskan.
        $this->assertSame(2, $workspace->max_sessions);
        $this->assertSame(100_000, $workspace->monthly_message_quota);
        $this->assertSame(300, $workspace->api_rate_limit_per_minute);
        $this->assertSame('active', $workspace->subscription->status);
    }

    public function test_bayar_lebih_awal_tidak_menghanguskan_sisa_hari(): void
    {
        $subscription = $this->langganan();
        $subscription->forceFill(['current_period_end' => now()->addDays(5)])->save();

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'essentials', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        // Periode baru menyambung dari akhir periode lama, bukan dari hari ini.
        $this->assertTrue(
            $subscription->fresh()->current_period_end->isSameDay(now()->addDays(5)->addMonth())
        );
    }

    public function test_bayar_terlambat_dihitung_dari_hari_ini(): void
    {
        $subscription = $this->langganan();
        $subscription->forceFill(['current_period_end' => now()->subMonths(2)])->save();

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'essentials', 'monthly');
        app(SubscriptionService::class)->markPaid($invoice);

        // Menyambung dari tanggal lampau akan membuat pelanggan membayar penuh
        // untuk periode yang seluruhnya sudah berlalu, dan layanannya mati lagi
        // seketika setelah membayar.
        $this->assertTrue($subscription->fresh()->current_period_end->isSameDay(now()->addMonth()));
    }

    public function test_langganan_mati_menghentikan_perubahan_data_tapi_tidak_pembacaan(): void
    {
        $this->langganan()->forceFill(['status' => 'past_due'])->save();

        // Membaca tetap boleh: pelanggan yang berhenti berlangganan harus tetap
        // bisa melihat riwayatnya dan tahu kenapa layanannya berhenti.
        $this->actingAs($this->owner)->get(route('messages.index'))->assertOk();

        // Mengubah tidak.
        $this->actingAs($this->owner)
            ->post(route('sessions.store'), ['name' => 'CS'])
            ->assertRedirect(route('billing.index'));

        $this->assertSame(0, $this->workspace->sessions()->count());
    }

    public function test_halaman_tagihan_tetap_menerima_post_saat_langganan_mati(): void
    {
        $this->langganan()->forceFill(['status' => 'suspended'])->save();

        // Kalau halaman ini ikut terblokir, satu-satunya jalan keluar dari
        // keadaan tertangguh ikut tertutup.
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'essentials', 'period' => 'monthly'])
            ->assertRedirect();

        $this->assertSame(1, Invoice::count());
    }

    public function test_anggota_biasa_tidak_bisa_mengeluarkan_uang_workspace(): void
    {
        $member = User::create([
            'name' => 'Maya',
            'email' => 'maya@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->get(route('billing.index'))->assertOk();

        $this->actingAs($member)
            ->post(route('billing.checkout'), ['plan' => 'elite', 'period' => 'yearly'])
            ->assertForbidden();
    }

    public function test_bukti_transfer_tersimpan_tapi_tidak_mengaktifkan_apa_pun(): void
    {
        Storage::fake('media');

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.proof.upload', $invoice->id), [
                'bukti' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertNotNull($invoice->proof_path);

        // Menganggap unggahan sebagai pembayaran berarti siapa pun bisa
        // menyalakan layanannya sendiri dengan gambar apa saja dari galeri.
        $this->assertSame('pending', $invoice->status);
        $this->assertSame('trialing', $this->workspace->fresh()->subscription->status);
    }

    public function test_workspace_lain_tidak_bisa_membuka_tagihan_kita(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $orangLain = User::create([
            'name' => 'Budi',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $lain = Workspace::create([
            'name' => 'Warung Lain',
            'slug' => 'warung-lain',
            'owner_id' => $orangLain->id,
        ]);

        $lain->members()->attach($orangLain->id, ['role' => 'owner']);

        $this->actingAs($orangLain)
            ->get(route('billing.invoice', $invoice->id))
            ->assertNotFound();
    }

    public function test_halaman_bayar_menampilkan_qris_dengan_nominal_tagihan(): void
    {
        // Payload contoh yang sah; yang diuji bukan QRIS-nya (itu di QrisTest)
        // melainkan bahwa halaman benar-benar menyisipkan nominal tagihan ini.
        config(['billing.qris.payload' => $this->payloadQrisContoh()]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('54'.str_pad((string) strlen((string) $invoice->total), 2, '0', STR_PAD_LEFT).$invoice->total, false);
    }

    public function test_payload_qris_rusak_tidak_ditawarkan_sebagai_metode(): void
    {
        // Kode QR rusak yang tetap tergambar adalah kegagalan yang baru terlihat
        // saat pelanggan sudah berdiri di depan layar dengan aplikasi banknya.
        // Yang dijaga di sini lebih jauh dari itu: metodenya tidak boleh muncul
        // sebagai pilihan sama sekali, karena memilihnya berujung jalan buntu.
        config([
            'billing.qris.payload' => '00020101021126320014ID.CO.QRIS.WWW63040000',
            'billing.bank.account_number' => null,
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Belum ada metode pembayaran yang aktif')
            ->assertDontSee('data-qris', false);
    }

    public function test_transfer_bank_ditawarkan_saat_rekening_terisi(): void
    {
        config([
            'billing.qris.payload' => null,
            'billing.bank.name' => 'BCA',
            'billing.bank.account_number' => '1234567890',
            'billing.bank.account_holder' => 'PT Flustra',
        ]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Transfer bank')
            ->assertSee('1234567890');
    }

    public function test_data_penagihan_tersimpan_tanpa_menyentuh_tagihan(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->owner)
            ->post(route('billing.details', $invoice->id), [
                'billing_name' => 'PT Sinar Jaya',
                'billing_email' => 'keuangan@sinarjaya.id',
                'billing_phone' => '081234567890',
            ])
            ->assertRedirect();

        $workspace = $this->workspace->fresh();

        $this->assertSame('PT Sinar Jaya', $workspace->billing_name);
        $this->assertSame('keuangan@sinarjaya.id', $workspace->billing_email);

        // Nomor dinormalkan; pengingat ke `08...` gagal diam-diam.
        $this->assertSame('6281234567890', $workspace->billing_phone);

        // Tagihannya sendiri tidak berubah sama sekali.
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_anggota_biasa_tidak_bisa_mengubah_data_penagihan(): void
    {
        $member = User::create([
            'name' => 'Maya',
            'email' => 'maya2@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($member->id, ['role' => 'member']);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($member)
            ->post(route('billing.details', $invoice->id), [
                'billing_name' => 'Diubah orang lain',
                'billing_email' => 'maya2@contoh.id',
            ])
            ->assertForbidden();

        $this->assertNull($this->workspace->fresh()->billing_name);
    }

    public function test_tagihan_lewat_tempo_tidak_lagi_menampilkan_kode_qr(): void
    {
        config(['billing.qris.payload' => $this->payloadQrisContoh()]);

        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');
        $invoice->forceFill(['due_at' => now()->subDay()])->save();

        $this->actingAs($this->owner)
            ->get(route('billing.invoice', $invoice->id))
            ->assertOk()
            ->assertSee('Batas waktu pembayaran sudah lewat')
            ->assertDontSee('data-qris', false);
    }

    /** Payload QRIS statis contoh, sah menurut CRC. Sama dengan yang dipakai QrisTest. */
    private function payloadQrisContoh(): string
    {
        $isi = '000201'
            .'010211'
            .'26370014ID.CO.QRIS.WWW0215ID1234567890123'
            .'52045812'
            .'5303360'
            .'5802ID'
            .'5907FLUSTRA'
            .'6007JAKARTA'
            .'6304';

        $crc = 0xFFFF;

        for ($i = 0, $n = strlen($isi); $i < $n; $i++) {
            $crc ^= ord($isi[$i]) << 8;

            for ($bit = 0; $bit < 8; $bit++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) : ($crc << 1);
                $crc &= 0xFFFF;
            }
        }

        return $isi.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    public function test_paket_yang_tidak_ada_ditolak(): void
    {
        $this->actingAs($this->owner)
            ->post(route('billing.checkout'), ['plan' => 'platinum', 'period' => 'monthly'])
            ->assertSessionHasErrors('plan');

        $this->assertSame(0, Invoice::count());
    }
}
