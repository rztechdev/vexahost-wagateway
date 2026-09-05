<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'google_id',
        'avatar',
        'email_verified_at',
        'password',
        'is_super_admin',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_super_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleIn(Workspace $workspace): ?string
    {
        return $this->workspaces->firstWhere('id', $workspace->id)?->pivot->role;
    }

    /**
     * Boleh mengubah pengaturan workspace: menambah anggota, mengelola sesi,
     * membuat dan mencabut API key. Member biasa hanya boleh melihat dan
     * mengirim pesan.
     */
    public function canManage(Workspace $workspace): bool
    {
        return $this->is_super_admin || in_array($this->roleIn($workspace), ['owner', 'admin'], true);
    }
}
