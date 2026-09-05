<?php

namespace Tests;

use App\Models\Subscription;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Memberi workspace langganan yang berlaku.
     *
     * Sejak workspace baru lahir berstatus `unpaid` — masa gratis hanya milik
     * akun yang sudah ada sebelum produk ini dijual — hampir seluruh tindakan
     * di dashboard dan API tertutup sampai ada yang membayar. Itu memang yang
     * diinginkan di produksi, tapi tes yang sedang menguji hal lain (API key,
     * pengaturan workspace, batas paket) tidak boleh ikut terhalang penagihan.
     *
     * Yang menguji penagihannya sendiri sengaja TIDAK memakai helper ini; di
     * sana keadaan langganannya justru yang sedang diperiksa.
     */
    protected function berlangganan(Workspace $workspace, string $plan = 'prime'): Subscription
    {
        $workspace->subscription()?->delete();

        $subscription = $workspace->subscription()->create([
            'plan_slug' => $plan,
            'period' => 'monthly',
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Cerminnya di `workspaces.status` — di situlah MessageDispatcher dan
        // AuthenticateApiKey membaca boleh-tidaknya sebuah workspace dipakai.
        $workspace->forceFill(['status' => 'active'])->save();
        $workspace->refresh();

        return $subscription;
    }
}
