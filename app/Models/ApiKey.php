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
        'scopes',
        'rate_limit_per_minute',
        'last_used_at',
        'last_used_ip',
        'expires_at',
        'revoked_at',
        'created_by',
    ];

    protected $hidden = ['key_hash'];

    protected function casts(): array
    {
        return [
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
     * Membuat kunci baru dan mengembalikan nilai polosnya. Nilai ini satu-satunya
     * kesempatan pemilik melihat kuncinya — setelah ini hanya hash yang tersimpan.
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
            'scopes' => $scopes,
            'created_by' => $createdBy,
        ]);

        return [$key, $plain];
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
