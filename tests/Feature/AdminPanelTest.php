<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pelanggan;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake();

        $this->admin = User::create([
            'name' => 'Flustra Finance',
            'email' => 'finance@flustra.id',
            'password' => Hash::make('rahasia12345'),
            'is_super_admin' => true,
        ]);

        $this->pelanggan = User::create([
            'name' => 'Ryan',
            'email' => 'ryan@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
            'name' => 'Toko Contoh',
            'slug' => 'toko-contoh',
            'owner_id' => $this->pelanggan->id,
            'owner_email' => $this->pelanggan->email,
            'max_sessions' => 1,
            'monthly_message_quota' => 3000,
        ]);

        $this->workspace->members()->attach($this->pelanggan->id, ['role' => 'owner']);
    }

    /**
     * 404, bukan 403. Halaman yang menjawab "dilarang" memberi tahu bahwa di
     * alamat itu ada sesuatu — dan alamatnya mudah ditebak.
     */
    public function test_bukan_super_admin_tidak_melihat_panel_sama_sekali(): void
    {
        foreach (['overview', 'workspaces', 'invoices', 'sessions', 'users'] as $halaman) {
            $this->actingAs($this->pelanggan)
                ->get(route("admin.{$halaman}"))
                ->assertNotFound();
        }
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('admin.overview'))->assertRedirect(route('login'));
    }

    public function test_super_admin_bisa_membuka_semua_halaman(): void
    {
        foreach (['overview', 'workspaces', 'invoices', 'sessions', 'users'] as $halaman) {
            $this->actingAs($this->admin)
                ->get(route("admin.{$halaman}"))
                ->assertOk();
        }
    }

    public function test_menandai_lunas_memperpanjang_langganan_dan_menerapkan_paket(): void
    {
        $invoice = app(SubscriptionService::class)
            ->issueInvoice($this->workspace, 'elite', 'monthly');

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.paid', $invoice->id), ['catatan' => 'mutasi BCA 14:32'])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame($this->admin->id, $invoice->paid_by_user_id);
        $this->assertSame('mutasi BCA 14:32', $invoice->note);

        $workspace = $this->workspace->fresh();
        $this->assertSame('active', $workspace->subscription->status);
        $this->assertSame(2, $workspace->max_sessions);
    }

    public function test_tagihan_lunas_tidak_bisa_ditandai_lunas_dua_kali(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'prime', 'monthly');

        $this->actingAs($this->admin)->post(route('admin.invoices.paid', $invoice->id));

        $berakhirPertama = $this->workspace->fresh()->subscription->current_period_end;

        $this->actingAs($this->admin)
            ->post(route('admin.invoices.paid', $invoice->id))
            ->assertSessionHasErrors('tagihan');

        // Kalau lolos, satu pembayaran memberi dua bulan.
        $this->assertTrue($berakhirPertama->equalTo($this->workspace->fresh()->subscription->current_period_end));
    }

    public function test_admin_bisa_menangguhkan_dan_memulihkan(): void
    {
        app(SubscriptionService::class)->ensureFor($this->workspace);

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.suspend', $this->workspace->id))
            ->assertRedirect();

        $this->assertSame('suspended', $this->workspace->fresh()->subscription->status);
        $this->assertSame('suspended', $this->workspace->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.workspaces.suspend', $this->workspace->id));

        $this->assertSame('active', $this->workspace->fresh()->subscription->status);
        $this->assertSame('active', $this->workspace->fresh()->status);
    }

    public function test_perpanjangan_manual_menambah_hari_tanpa_tagihan(): void
    {
        $subscription = app(SubscriptionService::class)->ensureFor($this->workspace);
        $subscription->forceFill(['current_period_end' => now()->addDays(2)])->save();

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.extend', $this->workspace->id), ['hari' => 10]);

        $this->assertTrue(
            $subscription->fresh()->current_period_end->isSameDay(now()->addDays(12))
        );
        $this->assertSame(0, Invoice::count());
    }

    public function test_kelonggaran_batas_tidak_mengubah_paket(): void
    {
        app(SubscriptionService::class)->ensureFor($this->workspace);

        $this->actingAs($this->admin)
            ->post(route('admin.workspaces.limits', $this->workspace->id), [
                'max_sessions' => 5,
                'monthly_message_quota' => 0,
            ]);

        $workspace = $this->workspace->fresh();

        $this->assertSame(5, $workspace->max_sessions);
        $this->assertSame(0, $workspace->monthly_message_quota);

        // Satu pelanggan yang butuh kuota lebih tidak boleh mengubah apa yang
        // didapat seluruh pelanggan pada paket yang sama.
        $this->assertSame('essentials', $workspace->subscription->plan_slug);
    }

    public function test_super_admin_tidak_bisa_mencabut_haknya_sendiri(): void
    {
        // Orang terakhir yang melakukannya mengunci panel dari luar, dan
        // memperbaikinya hanya bisa lewat akses langsung ke database produksi.
        $this->actingAs($this->admin)
            ->post(route('admin.users.super', $this->admin->id))
            ->assertSessionHasErrors('pengguna');

        $this->assertTrue($this->admin->fresh()->is_super_admin);
    }

    public function test_super_admin_bisa_mengangkat_orang_lain(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.users.super', $this->pelanggan->id))
            ->assertRedirect();

        $this->assertTrue($this->pelanggan->fresh()->is_super_admin);
    }

    public function test_pelanggan_tidak_bisa_menandai_tagihannya_sendiri_lunas(): void
    {
        $invoice = app(SubscriptionService::class)->issueInvoice($this->workspace, 'elite', 'yearly');

        $this->actingAs($this->pelanggan)
            ->post(route('admin.invoices.paid', $invoice->id))
            ->assertNotFound();

        $this->assertSame('pending', $invoice->fresh()->status);
    }
}
