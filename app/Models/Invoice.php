<?php

namespace App\Models;

use App\Support\Plan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'number',
        'external_id',
        'workspace_id',
        'subscription_id',
        'plan_slug',
        'period',
        'amount',
        'tax_amount',
        'unique_code',
        'total',
        'status',
        'channel',
        'due_at',
        'paid_at',
        'proof_path',
        'paid_by_user_id',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'tax_amount' => 'integer',
            'unique_code' => 'integer',
            'total' => 'integer',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by_user_id');
    }

    public function plan(): Plan
    {
        return Plan::find($this->plan_slug);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Sudah lewat batas bayar tapi belum ditandai kedaluwarsa oleh job harian.
     *
     * Halaman pembayaran memeriksanya sendiri, bukan menunggu job: tagihan yang
     * batas bayarnya lewat tengah malam tidak boleh masih menampilkan kode QR
     * yang aktif sampai job berikutnya berjalan.
     */
    public function isOverdue(): bool
    {
        return $this->isPending() && $this->due_at?->isPast();
    }

    public function periodLabel(): string
    {
        return $this->period === 'yearly' ? 'Tahunan' : 'Bulanan';
    }

    /**
     * Berapa lama periode ini memperpanjang langganan. Dipakai saat tagihan
     * ditandai lunas, jadi angkanya dibaca dari tagihan — bukan dari langganan
     * yang paketnya bisa saja sudah diganti sejak tagihan terbit.
     */
    public function periodMonths(): int
    {
        return $this->period === 'yearly' ? 12 : 1;
    }
}
