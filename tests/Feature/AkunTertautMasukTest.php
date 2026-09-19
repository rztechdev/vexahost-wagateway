<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as PermintaanKeluar;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

/**
 * Akun vexahost yang belum sampai ke sini dijemput saat pemiliknya masuk —
 * bukan disuruh mendaftar ulang dengan email dan kata sandi yang sudah benar.
 */
class AkunTertautMasukTest extends TestCase
{
    use RefreshDatabase;

    private const RAHASIA = 'rahasia-penautan-uji';

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'services.linked_accounts.url' => 'https://vexahost.test',
            'services.linked_accounts.secret' => self::RAHASIA,
        ]);
    }

    private function akunVexahost(array $timpa = []): array
    {
        return array_merge([
            'mode' => 'link',
            'origin' => 'vexahost',
            'email' => 'budi@contoh.id',
            'previous_email' => null,
            'name' => 'Budi Santoso',
            'phone' => '6281234567890',
            'password_hash' => Hash::make('sandi-vexahost'),
            'email_verified_at' => '2026-09-19T10:00:00+07:00',
        ], $timpa);
    }

    private function vexahostPunya(?array $akun): void
    {
        Http::fake(['vexahost.test/*' => $akun
            ? Http::response(['success' => true, 'data' => $akun])
            : Http::response(['success' => false], 404)]);
    }

    public function test_akun_yang_hanya_ada_di_vexahost_langsung_bisa_masuk(): void
    {
        $this->vexahostPunya($this->akunVexahost());

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'sandi-vexahost'])
            ->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@contoh.id')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('Budi Santoso', $user->name);

        // Kata sandi yang diketik tidak pernah dikirim; yang diminta hanya akunnya.
        Http::assertSent(function (PermintaanKeluar $r) {
            $tanda = hash_hmac('sha256', $r->header('X-Akun-Timestamp')[0].'.'.$r->body(), self::RAHASIA);

            return $r->url() === 'https://vexahost.test/api/internal/akun-tertaut/cari'
                && hash_equals($tanda, $r->header('X-Akun-Signature')[0])
                && $r->data() === ['email' => 'budi@contoh.id'];
        });
    }

    public function test_kata_sandi_salah_tetap_ditolak_dan_tidak_membuat_akun(): void
    {
        $this->vexahostPunya($this->akunVexahost());

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'salah'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_email_yang_tidak_ada_di_mana_pun_ditolak_seperti_biasa(): void
    {
        $this->vexahostPunya(null);

        $this->post(route('login'), ['email' => 'siapa@contoh.id', 'password' => 'apa-saja'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_kata_sandi_lokal_yang_tertinggal_diperbarui_dari_vexahost(): void
    {
        User::withoutEvents(fn () => User::create([
            'name' => 'Budi',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('sandi-lama'),
        ]));
        $this->vexahostPunya($this->akunVexahost());

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'sandi-vexahost'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    /**
     * Akun vexahost yang emailnya belum terbukti bisa saja didaftarkan orang lain
     * memakai email korban — ia tidak boleh mengganti kata sandi akun yang
     * sudah ada di sini.
     */
    public function test_akun_vexahost_belum_terverifikasi_tidak_mengambil_alih_akun_lokal(): void
    {
        User::withoutEvents(fn () => User::create([
            'name' => 'Budi Asli',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('sandi-asli'),
        ]));
        $this->vexahostPunya($this->akunVexahost([
            'email_verified_at' => null,
            'password_hash' => Hash::make('sandi-penyerang'),
        ]));

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'sandi-penyerang'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertTrue(Hash::check('sandi-asli', User::first()->password));
    }

    public function test_admin_tidak_pernah_dijemput_dari_seberang(): void
    {
        User::withoutEvents(fn () => User::create([
            'name' => 'Admin',
            'email' => 'budi@contoh.id',
            'password' => Hash::make('sandi-admin'),
            'is_super_admin' => true,
        ]));
        $this->vexahostPunya($this->akunVexahost());

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'sandi-vexahost'])
            ->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }

    public function test_penautan_mati_tidak_menjemput_apa_pun(): void
    {
        config(['services.linked_accounts.url' => '']);
        Http::fake();

        $this->post(route('login'), ['email' => 'budi@contoh.id', 'password' => 'sandi-vexahost'])
            ->assertSessionHasErrors('email');

        Http::assertNothingSent();
    }

    public function test_masuk_lewat_google_dengan_akun_vexahost_tidak_disuruh_mendaftar(): void
    {
        config([
            'services.google.client_id' => 'uji.apps.googleusercontent.com',
            'services.google.client_secret' => 'rahasia',
            'services.google.redirect' => 'http://localhost:8051/auth/google/callback',
        ]);
        $this->vexahostPunya($this->akunVexahost(['email_verified_at' => null]));

        $google = new SocialiteUser;
        $google->map(['id' => '555', 'name' => 'Budi', 'email' => 'budi@contoh.id', 'avatar' => null]);
        Socialite::shouldReceive('driver->user')->andReturn($google);

        $this->get(route('auth.google.callback'))->assertRedirect(route('dashboard'));

        $user = User::where('email', 'budi@contoh.id')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at, 'Google sudah membuktikan emailnya.');
    }
}
