<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaSession extends Model
{
    use HasUlids, SoftDeletes;

    public const KIND_PLATFORM = 'platform';
    public const KIND_TENANT = 'tenant';

    protected $fillable = [
        'tenant_id',
        'name',
        'kind',
        'driver',
        'status',
        'phone_number',
        'push_name',
        'qr_payload',
        'qr_expires_at',
        'auto_reconnect',
        'last_seen_at',
        'connected_at',
        'last_error',
        'meta',
    ];

    protected $hidden = ['qr_payload'];

    protected function casts(): array
    {
        return [
            'qr_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'connected_at' => 'datetime',
            'auto_reconnect' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(SessionBackup::class);
    }

    public function latestBackup(): ?SessionBackup
    {
        return $this->backups()->latest('backed_up_at')->first();
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * QR dari WhatsApp berumur pendek (±20 detik). Setelah lewat, yang tersimpan
     * sudah tidak bisa di-scan dan harus menunggu event `qr` berikutnya.
     */
    public function hasFreshQr(): bool
    {
        return $this->qr_payload !== null
            && $this->qr_expires_at !== null
            && $this->qr_expires_at->isFuture();
    }
}
