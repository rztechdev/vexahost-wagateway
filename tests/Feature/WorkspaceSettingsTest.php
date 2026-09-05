<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ganti nama dan hapus workspace dari halaman Pengaturan.
 *
 * Sebelum ini keduanya hanya bisa lewat tinker — workspace yang salah nama atau
 * telanjur terbuat tidak punya jalan keluar sama sekali di antarmuka.
 */
class WorkspaceSettingsTest extends TestCase
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
            'name' => 'Flustra internal',
            'slug' => 'flustra-internal',
            'owner_id' => $this->owner->id,
            'owner_email' => $this->owner->email,
            'max_sessions' => 1,
        ]);

        $this->workspace->members()->attach($this->owner->id, ['role' => 'owner']);

        // Workspace baru lahir `unpaid`; tes ini menguji hal lain,
        // jadi penagihannya tidak boleh ikut menghalangi.
        $this->berlangganan($this->workspace);
    }

    public function test_owner_bisa_mengganti_nama_workspace(): void
    {
        $this->actingAs($this->owner)
            ->put(route('settings.update'), ['name' => 'flustra.id'])
            ->assertRedirect();

        $this->assertSame('flustra.id', $this->workspace->fresh()->name);
    }

    public function test_slug_tidak_ikut_berubah_saat_nama_diganti(): void
    {
        $this->actingAs($this->owner)->put(route('settings.update'), ['name' => 'Nama Baru']);

        // Slug dipakai sebagai pengenal tetap di catatan audit; kalau ikut
        // berubah, jejak sebelum dan sesudah penggantian nama jadi terputus.
        $this->assertSame('flustra-internal', $this->workspace->fresh()->slug);
    }

    public function test_member_biasa_tidak_bisa_mengganti_nama(): void
    {
        $member = User::create([
            'name' => 'Maya',
            'email' => 'maya@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->put(route('settings.update'), ['name' => 'Coba Ganti'])
            ->assertForbidden();

        $this->assertSame('Flustra internal', $this->workspace->fresh()->name);
    }

    public function test_workspace_terhapus_kalau_namanya_diketik_benar(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('settings.destroy'), ['confirm' => 'Flustra internal'])
            ->assertRedirect();

        $this->assertSoftDeleted('workspaces', ['id' => $this->workspace->id]);
    }

    public function test_workspace_tidak_terhapus_kalau_nama_konfirmasi_salah(): void
    {
        $this->actingAs($this->owner)
            ->delete(route('settings.destroy'), ['confirm' => 'flustra internal'])
            ->assertSessionHasErrors('confirm');

        $this->assertNotSoftDeleted('workspaces', ['id' => $this->workspace->id]);
    }

    public function test_admin_tidak_bisa_menghapus_workspace(): void
    {
        $admin = User::create([
            'name' => 'Livy',
            'email' => 'livy@contoh.id',
            'password' => Hash::make('rahasia12345'),
        ]);

        $this->workspace->members()->attach($admin->id, ['role' => 'admin']);

        // Admin boleh mengelola isi workspace, tapi menghapusnya membuang
        // seluruh riwayat pesan dan mematikan API key yang dipakai aplikasi
        // lain — itu keputusan owner.
        $this->actingAs($admin)
            ->delete(route('settings.destroy'), ['confirm' => 'Flustra internal'])
            ->assertForbidden();

        $this->assertNotSoftDeleted('workspaces', ['id' => $this->workspace->id]);
    }
}
