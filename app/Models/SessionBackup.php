<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionBackup extends Model
{
    protected $fillable = [
        'wa_session_id',
        'disk',
        'path',
        'size',
        'checksum',
        'backed_up_at',
    ];

    protected function casts(): array
    {
        return [
            'backed_up_at' => 'datetime',
            'size' => 'integer',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(WaSession::class, 'wa_session_id');
    }
}
