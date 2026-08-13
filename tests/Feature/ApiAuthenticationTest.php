<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(): Tenant
    {
        return Tenant::create([
            'name' => 'Contoh',
            'slug' => 'contoh',
            'max_sessions' => 2,
            'monthly_message_quota' => 100,
            'api_rate_limit_per_minute' => 60,
        ]);
    }

    public function test_request_tanpa_api_key_ditolak(): void
    {
        $this->getJson('/api/v1/health')
            ->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_api_key_valid_diterima(): void
    {
        [, $plain] = ApiKey::issue($this->tenant(), 'kunci uji');

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.tenant', 'Contoh');
    }

    /**
     * Baris counter yang baru dibuat harus melaporkan 0, bukan null. Kalau null
     * lolos, API menampilkan null ke pemanggil dan pemeriksaan kuota jadi
     * bergantung pada cara PHP membandingkan null dengan angka.
     */
    public function test_pemakaian_tenant_baru_dilaporkan_nol_bukan_null(): void
    {
        [, $plain] = ApiKey::issue($this->tenant(), 'kunci uji');

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('data.usage.messages_sent', 0)
            ->assertJsonPath('data.usage.messages_received', 0)
            ->assertJsonPath('data.usage.messages_failed', 0);
    }

    public function test_api_key_bisa_dikirim_sebagai_bearer_token(): void
    {
        [, $plain] = ApiKey::issue($this->tenant(), 'kunci uji');

        $this->withHeader('Authorization', "Bearer {$plain}")
            ->getJson('/api/v1/health')
            ->assertOk();
    }

    public function test_api_key_yang_dicabut_ditolak(): void
    {
        [$key, $plain] = ApiKey::issue($this->tenant(), 'kunci uji');
        $key->update(['revoked_at' => now()]);

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertStatus(401);
    }

    public function test_tenant_yang_disuspend_ditolak(): void
    {
        $tenant = $this->tenant();
        [, $plain] = ApiKey::issue($tenant, 'kunci uji');
        $tenant->update(['status' => 'suspended']);

        $this->withHeader('X-Api-Key', $plain)
            ->getJson('/api/v1/health')
            ->assertStatus(403);
    }

    /**
     * Scope otp memberi kemampuan mengirim kode verifikasi ke nomor mana pun,
     * jadi kunci integrasi biasa tidak boleh bisa memakainya.
     */
    public function test_kunci_tanpa_scope_otp_tidak_bisa_mengirim_otp(): void
    {
        [, $plain] = ApiKey::issue($this->tenant(), 'kunci integrasi', ['messages']);

        $this->withHeader('X-Api-Key', $plain)
            ->postJson('/api/v1/otp/send', ['phone' => '081234567890'])
            ->assertStatus(403);
    }

    public function test_kunci_tenant_lain_tidak_bisa_melihat_sesi_kita(): void
    {
        $a = $this->tenant();
        $b = Tenant::create(['name' => 'Lain', 'slug' => 'lain', 'max_sessions' => 1]);

        $sessionA = $a->sessions()->create(['name' => 'CS', 'status' => 'connected']);

        [, $plainB] = ApiKey::issue($b, 'kunci b');

        $this->withHeader('X-Api-Key', $plainB)
            ->getJson("/api/v1/sessions/{$sessionA->id}")
            ->assertStatus(404);
    }
}
