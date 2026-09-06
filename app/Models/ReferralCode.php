<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Kode referal milik satu reseller.
 *
 * Bentuknya 5 huruf kapital tanpa I dan O. Keduanya dibuang bukan demi estetika
 * melainkan karena kode ini didiktekan lewat telepon dan WhatsApp: I tertukar
 * dengan 1, O tertukar dengan 0, dan kode yang salah ketik menghasilkan
 * penolakan yang terlihat seperti kode palsu. Sisa 24 huruf memberi 7,9 juta
 * kombinasi — cukup jauh, tapi tabrakan tetap DIPERIKSA saat pembuatan, bukan
 * diharapkan tidak terjadi.
 */
class ReferralCode extends Model
{
    /** I dan O sengaja tidak ada. */
    private const HURUF = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    protected $fillable = [
        'code',
        'owner_user_id',
        'discount_percent',
        'commission_percent',
        'max_redemptions',
        'expires_at',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'discount_percent' => 'integer',
            'commission_percent' => 'integer',
            'max_redemptions' => 'integer',
            'redeemed_count' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(ReferralRedemption::class);
    }

    /**
     * Kode acak yang dijamin belum dipakai.
     *
     * Tabrakan diperiksa, bukan diabaikan: 7,9 juta kombinasi terdengar aman
     * sampai kolomnya unik dan pembuatan kode ke-sekian gagal dengan galat 1062
     * di depan admin yang tidak melakukan apa pun yang salah.
     */
    public static function buatKode(): string
    {
        do {
            $kode = '';

            for ($i = 0; $i < 5; $i++) {
                $kode .= self::HURUF[random_int(0, strlen(self::HURUF) - 1)];
            }
        } while (self::where('code', $kode)->exists());

        return $kode;
    }

    /** Menormalkan masukan pengguna: huruf kecil dan spasi selalu dimaafkan. */
    public static function normalkan(string $kode): string
    {
        return Str::upper(preg_replace('/[^A-Za-z]/', '', $kode) ?? '');
    }

    /**
     * Apakah kode ini masih boleh ditukar sama sekali.
     *
     * Belum memandang siapa yang menukarnya — itu urusan `ReferralService`,
     * karena penolakannya butuh kalimat yang berbeda-beda.
     */
    public function bisaDitukar(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_redemptions === null || $this->redeemed_count < $this->max_redemptions;
    }

    /** Potongan untuk sebuah nominal, dalam rupiah penuh. */
    public function potongan(int $nominal): int
    {
        return (int) floor($nominal * $this->discount_percent / 100);
    }

    /** Komisi dihitung dari total SETELAH diskon, bukan dari harga penuh. */
    public function komisi(int $totalSetelahDiskon): int
    {
        return (int) floor($totalSetelahDiskon * $this->commission_percent / 100);
    }

    /** Komisi yang sudah disetujui tapi belum ditransfer. */
    public function komisiTerutang(): int
    {
        return (int) $this->redemptions()->where('status', 'approved')->sum('commission_amount');
    }

    public function komisiSudahDibayar(): int
    {
        return (int) $this->redemptions()->where('status', 'paid')->sum('commission_amount');
    }
}
