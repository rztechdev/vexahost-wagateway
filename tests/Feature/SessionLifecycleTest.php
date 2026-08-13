<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\WaSession;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Daur hidup sesi: buat → hapus → buat lagi dengan nama yang sama.
 *
 * Sesi dihapus lunak, sedangkan indeks unik (tenant_id, name) tidak melihat
 * deleted_at. Tanpa penjagaan di SessionService, memakai ulang nama sesi yang
 * sudah dihapus melempar galat duplikat mentah dari MySQL ke muka pengguna.
 */
class SessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Ryan',
            'email' => 'ryan@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->tenant = Tenant::create([
            'name' => 'Toko Uji',
            'slug' => 'toko-uji',
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => 3,
        ]);
    }

    public function test_nama_sesi_yang_sudah_dihapus_bisa_dipakai_lagi(): void
    {
        $sessions = app(SessionService::class);

        $pertama = $sessions->create($this->tenant, 'flustra.id');
        $pertama->delete();

        $kedua = $sessions->create($this->tenant, 'flustra.id');

        $this->assertNotSame($pertama->id, $kedua->id);
        $this->assertSame('pending', $kedua->status);

        // Baris lama dibuang permanen, bukan sekadar tetap tersembunyi —
        // kalau tidak, indeks unik akan menolak pembuatan berikutnya lagi.
        $this->assertDatabaseMissing('wa_sessions', ['id' => $pertama->id]);
    }

    public function test_jatah_sesi_tidak_terpakai_oleh_sesi_yang_sudah_dihapus(): void
    {
        $sessions = app(SessionService::class);
        $this->tenant->update(['max_sessions' => 1]);

        $sessions->create($this->tenant, 'lama')->delete();

        $baru = $sessions->create($this->tenant, 'baru');

        $this->assertInstanceOf(WaSession::class, $baru);
    }
}
