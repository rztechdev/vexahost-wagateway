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
     * Workspace baru lahir di paket coba gratis: hidup, tapi hanya lima pesan
     * seumur hidupnya dan dengan batas paling kecil. Itu memang yang diinginkan
     * di produksi, tapi tes yang sedang menguji hal lain (API key, pengaturan
     * workspace, batas paket) tidak boleh ikut terbentur jatah itu.
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

        // Cerminnya di baris workspace — di situlah MessageDispatcher dan
        // AuthenticateApiKey membaca boleh-tidaknya sebuah workspace dipakai.
        // `plan_slug` ikut, kalau tidak `isFreeTier()` masih menjawab benar dan
        // jatah lima pesan tetap berlaku untuk workspace yang sudah membayar.
        // Batas paket sengaja TIDAK ikut ditimpa: tiap tes menyetel angkanya
        // sendiri, dan menimpanya di sini membuat tes batas menguji paket,
        // bukan yang sedang mereka periksa.
        $workspace->forceFill([
            'plan_slug' => $plan,
            'status' => 'active',
            'service_until' => $subscription->current_period_end,
        ])->save();
        $workspace->refresh();

        return $subscription;
    }
}
