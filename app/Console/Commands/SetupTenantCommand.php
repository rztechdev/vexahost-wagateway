<?php

namespace App\Console\Commands;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\WaSession;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Menyiapkan tenant beserta sesi dan API key-nya dari baris perintah.
 *
 * Terutama untuk penyiapan awal di server: tenant internal Flustra butuh
 * `is_internal` dan sesi ber-`kind=platform`, dua hal yang tidak bisa diatur
 * lewat dashboard karena memang bukan sesuatu yang boleh diubah pelanggan.
 * Tanpa command ini, langkah tersebut harus dilakukan dengan mengedit database
 * secara manual — rawan salah dan tidak terulangkan.
 */
class SetupTenantCommand extends Command
{
    protected $signature = 'gateway:setup-tenant
                            {name : Nama tenant, mis. "Flustra Internal"}
                            {--internal : Tandai sebagai tenant internal (bebas kuota)}
                            {--session= : Sekalian buat sesi dengan nama ini}
                            {--platform : Sesi yang dibuat bertipe platform (nomor resmi Flustra)}
                            {--key= : Sekalian buat API key dengan nama ini}
                            {--scopes=* : Scope API key (default *). Contoh: --scopes=otp}
                            {--max-sessions=1}
                            {--quota=1000}';

    protected $description = 'Membuat tenant, sesi, dan API key untuk penyiapan awal gateway';

    public function handle(): int
    {
        $name = $this->argument('name');
        $internal = (bool) $this->option('internal');

        // Dicocokkan lewat slug supaya command ini aman dijalankan berulang
        // saat penyiapan server diulang — tidak menumpuk tenant duplikat.
        $tenant = Tenant::firstOrCreate(
            ['slug' => Str::slug($name) ?: 'tenant'],
            [
                'name' => $name,
                'is_internal' => $internal,
                'max_sessions' => (int) $this->option('max-sessions'),
                'monthly_message_quota' => (int) $this->option('quota'),
                'api_rate_limit_per_minute' => config('gateway.defaults.api_rate_limit_per_minute'),
            ]
        );

        if ($tenant->wasRecentlyCreated) {
            $this->info("Tenant dibuat: {$tenant->name} (id={$tenant->id})");
        } else {
            $this->line("Tenant sudah ada: {$tenant->name} (id={$tenant->id})");
        }

        if ($internal && ! $tenant->is_internal) {
            $tenant->update(['is_internal' => true]);
            $this->line('Tenant ditandai internal (bebas kuota).');
        }

        if ($sessionName = $this->option('session')) {
            $session = $tenant->sessions()->firstOrCreate(
                ['name' => $sessionName],
                [
                    'kind' => $this->option('platform') ? WaSession::KIND_PLATFORM : WaSession::KIND_TENANT,
                    'driver' => 'wwebjs',
                    'status' => 'pending',
                ]
            );

            $this->info("Sesi {$session->kind}: {$session->name}");
            $this->line("  ID sesi: {$session->id}");

            if ($session->kind === WaSession::KIND_PLATFORM) {
                $this->warn("  Isikan ke .env → PLATFORM_SESSION_ID={$session->id}");
            }

            $this->line('  Buka dashboard → Sesi WhatsApp → Hubungkan, lalu scan QR-nya.');
        }

        if ($keyName = $this->option('key')) {
            $scopes = $this->option('scopes') ?: ['*'];

            [, $plain] = ApiKey::issue($tenant, $keyName, $scopes);

            $this->info("API key '{$keyName}' dibuat, scope: ".implode(', ', $scopes));
            $this->newLine();
            $this->line($plain);
            $this->newLine();
            // Yang tersimpan hanya hash-nya, jadi ini benar-benar satu-satunya
            // kesempatan nilai penuhnya bisa dibaca.
            $this->warn('Salin sekarang — nilai ini tidak bisa ditampilkan lagi.');
        }

        return self::SUCCESS;
    }
}
