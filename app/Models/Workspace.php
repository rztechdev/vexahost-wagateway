<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
