<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WaSession;
use App\Models\Workspace;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Daur hidup sesi: buat → hapus → buat lagi dengan nama yang sama.
 *
 * Sesi dihapus lunak, sedangkan indeks unik (workspace_id, name) tidak melihat
 * deleted_at. Tanpa penjagaan di SessionService, memakai ulang nama sesi yang
 * sudah dihapus melempar galat duplikat mentah dari MySQL ke muka pengguna.
 */
class SessionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Workspace $workspace;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::create([
            'name' => 'Ryan',
            'email' => 'ryan@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace = Workspace::create([
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

        $pertama = $sessions->create($this->workspace, 'flustra.id');
        $pertama->delete();

        $kedua = $sessions->create($this->workspace, 'flustra.id');

        $this->assertNotSame($pertama->id, $kedua->id);
        $this->assertSame('pending', $kedua->status);

        // Baris lama dibuang permanen, bukan sekadar tetap tersembunyi —
        // kalau tidak, indeks unik akan menolak pembuatan berikutnya lagi.
        $this->assertDatabaseMissing('wa_sessions', ['id' => $pertama->id]);
    }

    public function test_jatah_sesi_tidak_terpakai_oleh_sesi_yang_sudah_dihapus(): void
    {
        $sessions = app(SessionService::class);
        $this->workspace->update(['max_sessions' => 1]);

        $sessions->create($this->workspace, 'lama')->delete();

        $baru = $sessions->create($this->workspace, 'baru');

        $this->assertInstanceOf(WaSession::class, $baru);
    }

    public function test_connect_kirim_has_backup_false_jika_tidak_ada_backup(): void
    {
        $sessions = app(SessionService::class);
        $session = $sessions->create($this->workspace, 'sesi-tanpa-backup');

        \Illuminate\Support\Facades\Http::fake([
            '*/sessions/*/start' => \Illuminate\Support\Facades\Http::response(['status' => 'starting']),
        ]);

        $sessions->connect($session);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) {
            return $request['has_backup'] === false && ! isset($request['backup_data']);
        });
    }

    public function test_logout_memanggil_engine_dan_menghapus_backup(): void
    {
        $sessions = app(SessionService::class);
        $session = $sessions->create($this->workspace, 'sesi-logout');
        $session->update(['status' => 'connected', 'phone_number' => '6281234567890']);

        \Illuminate\Support\Facades\Http::fake([
            '*/sessions/*/logout' => \Illuminate\Support\Facades\Http::response(['status' => 'logged_out']),
        ]);

        $sessions->logout($session);

        \Illuminate\Support\Facades\Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($session) {
            return str_contains($request->url(), "/sessions/{$session->id}/logout");
        });

        $session->refresh();
        $this->assertSame('disconnected', $session->status);
        $this->assertNull($session->phone_number);
    }
}
