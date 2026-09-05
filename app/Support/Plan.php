<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Satu paket langganan, dibaca dari `config/plans.php`.
 *
 * Ini bukan model Eloquent dan tidak akan pernah jadi model: paket adalah
 * keputusan produk yang hidup di kode, sedangkan yang disimpan di database
 * hanyalah slug paket mana yang sedang dipakai sebuah workspace.
 *
 * Konsekuensi yang perlu disadari saat membaca kode ini: paket yang slug-nya
 * tidak ada lagi di katalog TIDAK melempar galat saat dimuat lewat `cari()`.
 * Workspace yang berlangganan paket yang dihapus dari katalog harus tetap bisa
 * membuka dashboard-nya; yang terjadi ia jatuh ke paket bawaan, bukan ke
 * halaman galat.
 */
class Plan
{
    private function __construct(
        public readonly string $slug,
        public readonly array $attributes,
    ) {}

    /**
     * Paket berdasarkan slug. Melempar galat kalau slug-nya tidak dikenal —
     * dipakai di jalur tempat slug datang dari kode kita sendiri.
     */
    public static function get(string $slug): self
    {
        $catalog = config('plans.catalog');

        if (! isset($catalog[$slug])) {
            throw new InvalidArgumentException("Paket '{$slug}' tidak ada di katalog.");
        }

        return new self($slug, $catalog[$slug]);
    }

    /**
     * Paket berdasarkan slug, dengan paket bawaan sebagai jaring pengaman.
     * Dipakai di jalur tempat slug datang dari database — termasuk baris lama
     * yang menyebut paket yang sudah tidak ada.
     */
    public static function find(?string $slug): self
    {
        $catalog = config('plans.catalog');

        if ($slug === null || ! isset($catalog[$slug])) {
            $slug = config('plans.default');
        }

        return new self($slug, $catalog[$slug]);
    }

    public static function exists(?string $slug): bool
    {
        return $slug !== null && isset(config('plans.catalog')[$slug]);
    }

    /** @return array<int, self> Seluruh katalog, urut seperti di config. */
    public static function all(): array
    {
        return array_map(
            fn (string $slug) => self::get($slug),
            array_keys(config('plans.catalog'))
        );
    }

    public function name(): string
    {
        return $this->attributes['name'];
    }

    public function tagline(): string
    {
        return $this->attributes['tagline'];
    }

    /** @return array<int, string> */
    public function features(): array
    {
        return $this->attributes['features'];
    }

    public function isHighlighted(): bool
    {
        return (bool) $this->attributes['highlight'];
    }

    /**
     * Harga untuk satu periode penagihan, dalam rupiah penuh.
     *
     * Harga tahunan diturunkan dari harga bulanan, tidak ditulis terpisah:
     * dua angka yang harus dijaga tetap selaras adalah dua angka yang cepat
     * atau lambat akan berbeda tanpa ada yang menyadarinya.
     */
    public function price(string $period): int
    {
        return $period === 'yearly'
            ? $this->attributes['price_monthly'] * config('plans.yearly_multiplier')
            : $this->attributes['price_monthly'];
    }

    public function monthlyPrice(): int
    {
        return $this->attributes['price_monthly'];
    }

    /**
     * Batas yang ditegakkan aplikasi. Bentuk array-nya sengaja sama persis
     * dengan nama kolom di tabel `workspaces`, supaya menerapkan paket ke
     * workspace tidak perlu penerjemahan nama satu per satu.
     *
     * @return array<string, int>
     */
    public function limits(): array
    {
        return [
            'max_sessions' => $this->attributes['max_sessions'],
            'monthly_message_quota' => $this->attributes['monthly_message_quota'],
            'api_rate_limit_per_minute' => $this->attributes['api_rate_limit_per_minute'],
        ];
    }

    public function maxApiKeys(): int
    {
        return $this->attributes['max_api_keys'];
    }

    public function maxMembers(): int
    {
        return $this->attributes['max_members'];
    }

    public function messageRetentionDays(): int
    {
        return $this->attributes['message_retention_days'];
    }
}
