<?php

namespace App\Models;

use App\Support\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workspace extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
        'owner_email',
        'billing_phone',
        'billing_name',
        'billing_email',
        'status',
        'plan_slug',
        'max_sessions',
        'monthly_message_quota',
        'api_rate_limit_per_minute',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'max_sessions' => 'integer',
            'monthly_message_quota' => 'integer',
            'api_rate_limit_per_minute' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WaSession::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function usageCounters(): HasMany
    {
        return $this->hasMany(UsageCounter::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * Paket yang sedang berlaku.
     *
     * Workspace tanpa langganan — internal Flustra, atau baris yang terbuat
     * sebelum penagihan dinyalakan — jatuh ke paket bawaan. Itu membuat setiap
     * pemanggil bisa menganggap paket selalu ada, sehingga tidak ada satu pun
     * tempat di antarmuka yang perlu menangani "workspace tanpa paket".
     */
    public function plan(): Plan
    {
        return Plan::find($this->subscription?->plan_slug ?? $this->plan_slug);
    }

    /**
     * Berapa hari riwayat pesan disimpan untuk workspace ini.
     *
     * Workspace internal memakai angka retensi global: ia tidak berlangganan,
     * jadi tidak ada paket yang bisa menjawabnya.
     */
    public function messageRetentionDays(): int
    {
        return $this->is_internal
            ? (int) config('gateway.retention.messages_days')
            : $this->plan()->messageRetentionDays();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Slug yang belum terpakai, termasuk oleh workspace yang sudah dihapus.
     *
     * `withTrashed()` bukan kehati-hatian berlebih: kolom `slug` unik tanpa
     * memandang `deleted_at`, jadi memakai ulang slug milik workspace terhapus
     * gagal dengan galat 1062 yang tidak menyebut slug sama sekali.
     *
     * Aturannya ditaruh di sini, bukan disalin ke tiap pemanggil, karena dua
     * salinan yang berbeda sedikit saja akan menghasilkan galat itu di satu
     * jalur pendaftaran tapi tidak di jalur lainnya.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (self::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /**
     * Pemakaian bulan berjalan. Dibuat lazily supaya workspace baru tidak perlu
     * baris counter sampai benar-benar mengirim pesan pertamanya.
     *
     * Nilai awal ditulis eksplisit, bukan diserahkan ke default kolom di
     * database: baris yang baru saja dibuat tidak membaca ulang nilai default
     * dari database, sehingga counter-nya akan terbaca null — membuat API
     * melaporkan null alih-alih 0, dan membuat perbandingan kuota bergantung
     * pada perilaku PHP saat membandingkan null dengan angka.
     */
    public function currentUsage(): UsageCounter
    {
        return $this->usageCounters()->firstOrCreate(
            ['period' => now()->format('Y-m')],
            ['messages_sent' => 0, 'messages_received' => 0, 'messages_failed' => 0],
        );
    }

    public function hasQuotaRemaining(): bool
    {
        if ($this->is_internal || $this->monthly_message_quota === 0) {
            return true;
        }

        return $this->currentUsage()->messages_sent < $this->monthly_message_quota;
    }

    public function canAddSession(): bool
    {
        return $this->sessions()->count() < $this->max_sessions;
    }

    /**
     * Batas jumlah API key dan anggota tim dibaca langsung dari paket, bukan
     * dari kolom di tabel ini seperti sesi dan kuota pesan.
     *
     * Bedanya disengaja. Kolom `max_sessions` dan `monthly_message_quota`
     * dipakai juga sebagai kelonggaran manual — admin sesekali perlu menaikkan
     * satu workspace tanpa memindahkannya ke paket lain. Untuk API key dan
     * anggota tim kebutuhan itu tidak pernah muncul, dan menambah dua kolom
     * hanya untuk kesetangkupan berarti dua nilai lagi yang bisa menyimpang
     * dari paket tanpa ada yang menyadarinya.
     */
    public function canAddApiKey(): bool
    {
        $batas = $this->plan()->maxApiKeys();

        if ($this->is_internal || $batas === 0) {
            return true;
        }

        return $this->apiKeys()->whereNull('revoked_at')->count() < $batas;
    }

    public function canAddMember(): bool
    {
        $batas = $this->plan()->maxMembers();

        if ($this->is_internal || $batas === 0) {
            return true;
        }

        return $this->members()->count() < $batas;
    }
}
