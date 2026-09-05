<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\WaSession;
use App\Models\Workspace;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

/**
 * Ringkasan platform dan kesehatannya.
 *
 * Satu angka di halaman ini lebih penting dari sisanya: sesi yang sedang hidup
 * dibanding `WA_MAX_SESSIONS`. Tiap sesi berarti satu Chromium yang memakan
 * 300-500 MB, dan batasnya ditegakkan engine, bukan Laravel. Begitu keduanya
 * bertemu, pelanggan berikutnya yang membayar tidak akan bisa menautkan
 * nomornya — dan tanpa angka ini di layar, hal itu baru ketahuan dari keluhan.
 */
class OverviewController extends Controller
{
    public function __invoke(): View
    {
        $bulanIni = now()->format('Y-m');

        return view('admin.overview', [
            'workspaceTotal' => Workspace::count(),
            'workspaceAktif' => Workspace::where('status', 'active')->count(),

            'langgananPerStatus' => Subscription::query()
                ->selectRaw('status, count(*) as jumlah')
                ->groupBy('status')
                ->pluck('jumlah', 'status')
                ->all(),

            // Pendapatan bulan ini dihitung dari tagihan yang benar-benar
            // ditandai lunas, bukan dari langganan yang berstatus aktif:
            // langganan yang diperpanjang admin lewat kelonggaran juga aktif,
            // dan uangnya tidak pernah masuk.
            'pendapatanBulanIni' => (int) Invoice::where('status', 'paid')
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('total'),

            'tagihanMenunggu' => Invoice::where('status', 'pending')->count(),
            'tagihanPerluDiperiksa' => Invoice::where('status', 'pending')
                ->whereNotNull('proof_path')
                ->count(),

            'sesiHidup' => WaSession::whereIn('status', ['connected', 'connecting', 'qr'])->count(),
            'sesiTersambung' => WaSession::where('status', 'connected')->count(),
            'kapasitasSesi' => (int) config('gateway.engine.max_sessions'),

            'pesanBulanIni' => (int) DB::table('usage_counters')
                ->where('period', $bulanIni)
                ->sum('messages_sent'),

            'antrean' => DB::table('jobs')->count(),
            'antreanGagal' => DB::table('failed_jobs')->count(),

            'akanBerakhir' => Subscription::with('workspace')
                ->whereIn('status', ['trialing', 'active'])
                ->whereBetween('current_period_end', [now(), now()->addDays(7)])
                ->orderBy('current_period_end')
                ->limit(10)
                ->get(),
        ]);
    }
}
