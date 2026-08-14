<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'workspace_id',
        'name',
        'prefix',
        'key_hash',
        'key_ciphertext',
        'scopes',
        'rate_limit_per_minute',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = ['key_hash', 'key_ciphertext'];

    protected function casts(): array
    {
        return [
            // Dienkripsi dengan APP_KEY, bukan di-hash: kolom ini memang harus
            // bisa dibaca balik untuk ditampilkan di dashboard. Verifikasi
            // permintaan API tetap lewat key_hash yang tidak bisa dibalik.
            'key_ciphertext' => 'encrypted',
            'scopes' => 'array',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Membuat kunci baru dan mengembalikan nilai polosnya.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Workspace $workspace, string $name, array $scopes = ['*'], ?int $createdBy = null): array
    {
        $prefix = 'fwa_'.Str::lower(Str::random(8));
        $secret = Str::random(40);
        $plain = $prefix.'.'.$secret;

        $key = self::create([
            'workspace_id' => $workspace->id,
            'name' => $name,
            'prefix' => $prefix,
            'key_hash' => Hash::make($secret),
            'key_ciphertext' => $plain,
            'scopes' => $scopes,
            'created_by' => $createdBy,
        ]);

        return [$key, $plain];
    }

    /**
     * Nilai penuh kunci, untuk ditampilkan ulang di dashboard.
     *
     * null untuk kunci yang dibuat sebelum kolom terenkripsi ada — nilainya
     * memang tidak tersimpan di mana pun dan tidak bisa dipulihkan dari hash.
     */
    public function plainKey(): ?string
    {
        return $this->key_ciphertext;
    }

    public function isUsable(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }

    public function allows(string $scope): bool
    {
        $scopes = $this->scopes ?? ['*'];

        return in_array('*', $scopes, true) || in_array($scope, $scopes, true);
    }
}
