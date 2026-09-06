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

    /**
     * Nomor milik perusahaan sendiri, terdaftar di panel admin.
     *
     * Sesi yang memegangnya tidak ikut dilepas saat langganan workspace mati,
     * dan pengiriman darinya tidak dihalangi status workspace — nomor itu milik
     * kami, ditumpangkan di workspace mana pun yang kebetulan memakainya.
     */
    public function isSpecial(): bool
    {
        return SpecialNumber::cocok($this->phone_number);
    }

    /**
     * Hanya sesi milik workspace yang layanannya masih berjalan.
     *
     * Dipakai oleh dua jalur yang menjalankan sesi TANPA ada manusia yang
     * menekan tombol: penjadwal tiap menit dan pemulihan saat engine boot.
     * Keduanya dulu tidak memandang status workspace sama sekali, dan itu
     * membatalkan seluruh penegakan langganan di tempat yang paling mahal:
     *
     *  - `SubscriptionService::releaseSessions()` melepas sesi workspace yang
     *    menunggak setelah masa tenggang — lalu `SyncSessionStatusJob`
     *    menjalankannya lagi satu menit kemudian, tiap menit, selamanya.
     *  - Tiap engine di-deploy ulang, seluruh sesi workspace yang menunggak
     *    ikut dipulihkan dan kembali memakan slot dari tiga yang tersedia
     *    untuk SEMUA pelanggan.
     *
     * Yang bocor di sini bukan pengiriman pesan — itu sudah dijaga
     * `MessageDispatcher::guardWorkspace()`. Yang bocor adalah sumber daya yang
     * paling langka: nomor tetap tertaut, pesan masuk tetap diterima, dan
     * webhook pesan masuk tetap terkirim ke aplikasi pelanggan yang tidak
     * membayar. Itu tetap "memakai layanan".
     */
    public function scopeLayananHidup($query)
    {
        $istimewa = SpecialNumber::daftar();

        // Nomor perusahaan sendiri selalu ikut dipulihkan, apa pun keadaan
        // workspace yang ditumpanginya.
        return $query->where(function ($luar) use ($istimewa): void {
            if ($istimewa !== []) {
                $luar->whereIn('phone_number', $istimewa);
            }

            $luar->orWhere(fn ($q) => $q->whereHas('workspace', function ($w): void {
                $w->where('status', 'active')
                    ->where(function ($t): void {
                        $t->whereNull('service_until')->orWhere('service_until', '>', now());
                    });
            }));
        });
    }

    protected $fillable = [
        'workspace_id',
        'name',
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

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
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
