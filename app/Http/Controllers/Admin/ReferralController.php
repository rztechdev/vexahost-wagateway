<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ReferralCode;
use App\Models\ReferralRedemption;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Kode referal dan komisi yang terutang.
 *
 * **Pembayaran komisi manual, dan itu keputusan sadar.** Uang masuk saja masih
 * dicocokkan manusia lewat QRIS; membangun pembayaran keluar otomatis di atas
 * pemasukan yang belum otomatis berarti membuat jalur uang keluar yang lebih
 * dipercaya daripada jalur uang masuknya. Halaman ini menjawab satu pertanyaan
 * saja: berapa yang terutang ke siapa, dan tombol untuk menandainya sudah
 * ditransfer.
 */
class ReferralController extends Controller
{
    public function index(): View
    {
        $kode = ReferralCode::with('owner')
            ->withCount([
                'redemptions as dipakai' => fn ($q) => $q->whereIn('status', ['approved', 'paid']),
            ])
            ->latest()
            ->get();

        return view('admin.referrals', [
            'kode' => $kode,

            // Yang menunggu ditransfer, paling atas dan terpisah dari daftar
            // kode: inilah satu-satunya bagian halaman ini yang menuntut
            // tindakan, dan menguburnya di dalam tabel kode berarti tidak ada
            // yang tahu ada utang yang belum dibayar.
            'terutang' => ReferralRedemption::with(['code.owner', 'workspace', 'invoice'])
                ->where('status', 'approved')
                ->latest('approved_at')
                ->get(),

            'totalTerutang' => (int) ReferralRedemption::where('status', 'approved')->sum('commission_amount'),
            'totalDibayar' => (int) ReferralRedemption::where('status', 'paid')->sum('commission_amount'),
            'totalDiskon' => (int) ReferralRedemption::whereIn('status', ['approved', 'paid'])->sum('discount_amount'),

            'kandidatPemilik' => User::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'owner_user_id' => ['required', 'integer', 'exists:users,id'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'commission_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $kode = ReferralCode::create($data + [
            // Dibuat di sini, bukan diketik admin: kode yang dipilih manusia
            // berpola, dan pola membuat kode orang lain bisa ditebak.
            'code' => ReferralCode::buatKode(),
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('referral.created', $kode, [
            'code' => $kode->code,
            'owner' => $kode->owner?->email,
            'diskon' => $kode->discount_percent,
            'komisi' => $kode->commission_percent,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Kode dibuat: '.$kode->code,
            'pesan' => 'Pembeli dapat potongan '.$kode->discount_percent.'%, '
                .($kode->owner?->name ?? 'pemiliknya').' dapat komisi '.$kode->commission_percent.'% '
                .'dari total setelah potongan — dan komisinya baru terutang setelah tagihannya lunas.',
        ]);
    }

    public function toggle(Request $request, int $id): RedirectResponse
    {
        $kode = ReferralCode::findOrFail($id);

        $kode->forceFill(['is_active' => ! $kode->is_active])->save();

        AuditLog::record($kode->is_active ? 'referral.activated' : 'referral.deactivated', $kode, [
            'code' => $kode->code,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => $kode->is_active ? 'Kode diaktifkan' : 'Kode dimatikan',
            // Penukaran yang sudah tercatat tidak ikut batal, dan itu harus
            // disebutkan: komisi yang sudah disetujui tetap utang kami.
            'pesan' => $kode->is_active
                ? $kode->code.' bisa ditukar lagi.'
                : $kode->code.' tidak bisa ditukar lagi. Komisi yang sudah disetujui tetap terutang.',
        ]);
    }

    /**
     * Menandai satu komisi sudah ditransfer.
     *
     * Tidak ada pembayaran otomatis di balik tombol ini — ia hanya mencatat
     * bahwa seorang manusia sudah melakukan transfernya.
     */
    public function markPaid(Request $request, int $id): RedirectResponse
    {
        $redemption = ReferralRedemption::with('code')->findOrFail($id);

        if ($redemption->status !== 'approved') {
            return back()->withErrors([
                'komisi' => 'Hanya komisi yang sudah disetujui yang bisa ditandai dibayar.',
            ]);
        }

        $redemption->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

        AuditLog::record('referral.commission.paid', $redemption, [
            'code' => $redemption->code?->code,
            'jumlah' => $redemption->commission_amount,
            'oleh' => $request->user()->email,
        ]);

        return back()->with('swal', [
            'tipe' => 'success',
            'judul' => 'Komisi ditandai dibayar',
            'pesan' => 'Rp '.number_format($redemption->commission_amount, 0, ',', '.')
                .' untuk kode '.$redemption->code?->code.'.',
        ]);
    }
}
