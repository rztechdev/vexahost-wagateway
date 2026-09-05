<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workspace;
use App\Services\Billing\SubscriptionService;
use App\Support\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WorkspaceController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $cari = trim((string) $request->query('cari'));
        $status = (string) $request->query('status', '');

        $workspaces = Workspace::query()
            ->with(['subscription', 'owner'])
            ->withCount('sessions')
            ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                $q->where('name', 'like', "%{$cari}%")
                    ->orWhere('slug', 'like', "%{$cari}%")
                    ->orWhere('owner_email', 'like', "%{$cari}%");
            }))
            ->when($status !== '', fn ($q) => $q->whereHas('subscription', fn ($s) => $s->where('status', $status)))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Pemakaian diambil sekali untuk seluruh halaman, bukan lewat relasi
        // per baris: dua puluh lima workspace berarti dua puluh lima kueri
        // tambahan pada tabel yang tumbuh paling cepat di sistem ini.
        $pemakaian = DB::table('usage_counters')
            ->where('period', now()->format('Y-m'))
            ->whereIn('workspace_id', $workspaces->pluck('id'))
            ->pluck('messages_sent', 'workspace_id');

        return view('admin.workspaces', [
            'workspaces' => $workspaces,
            'pemakaian' => $pemakaian,
            'plans' => Plan::all(),
            'cari' => $cari,
            'status' => $status,
        ]);
    }

    /**
     * Memindahkan workspace ke paket lain tanpa tagihan.
     *
     * Dipakai untuk memperbaiki kesalahan dan untuk kesepakatan di luar
     * halaman harga. Batas paket langsung berlaku, dan periodenya tidak
     * disentuh — memindahkan paket bukan memperpanjang langganan.
     */
    public function changePlan(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('plans.catalog')))],
        ]);

        $workspace = Workspace::findOrFail($id);
        $subscription = $this->subscriptions->ensureFor($workspace);

        $subscription->forceFill(['plan_slug' => $data['plan']])->save();

        $this->subscriptions->applyPlanLimits($workspace, Plan::get($data['plan']));

        AuditLog::record('admin.plan_changed', $workspace, [
            'plan' => $data['plan'],
            'oleh' => $request->user()->email,
        ], $workspace->id);

        return back()->with('status', "Paket {$workspace->name} diubah ke ".Plan::get($data['plan'])->name().'.');
    }

    public function extend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'hari' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $workspace = Workspace::findOrFail($id);

        $this->subscriptions->extend(
            $this->subscriptions->ensureFor($workspace),
            $data['hari'],
            $request->user(),
        );

        return back()->with('status', "Langganan {$workspace->name} diperpanjang {$data['hari']} hari.");
    }

    /**
     * Menangguhkan atau memulihkan langganan dengan tangan.
     *
     * Memulihkan hanya mengembalikan status dan mengaktifkan workspace; sesi
     * yang sudah terputus tidak dinyalakan otomatis dari sini. Menyalakan
     * beberapa Chromium sekaligus dari satu klik adalah cara tercepat membuat
     * container kehabisan memori — pemiliknya yang menekan Hubungkan.
     */
    public function toggleSuspend(Request $request, int $id): RedirectResponse
    {
        $workspace = Workspace::findOrFail($id);
        $subscription = $this->subscriptions->ensureFor($workspace);

        if ($subscription->status === 'suspended' || $subscription->status === 'past_due') {
            $subscription->forceFill([
                'status' => 'active',
                'past_due_at' => null,
                'suspended_at' => null,
            ])->save();

            $workspace->forceFill(['status' => 'active'])->save();

            AuditLog::record('admin.workspace_restored', $workspace, [
                'oleh' => $request->user()->email,
            ], $workspace->id);

            return back()->with('status', "{$workspace->name} diaktifkan kembali.");
        }

        $this->subscriptions->suspend($subscription);

        AuditLog::record('admin.workspace_suspended', $workspace, [
            'oleh' => $request->user()->email,
        ], $workspace->id);

        return back()->with('status', "{$workspace->name} ditangguhkan.");
    }

    /**
     * Kelonggaran batas untuk satu workspace, di luar paketnya.
     *
     * Sengaja hanya menyentuh kolom di tabel workspaces, bukan paket: satu
     * pelanggan yang butuh kuota lebih tidak boleh mengubah apa yang didapat
     * seluruh pelanggan pada paket yang sama.
     */
    public function overrideLimits(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'max_sessions' => ['required', 'integer', 'min:0', 'max:50'],
            'monthly_message_quota' => ['required', 'integer', 'min:0'],
        ]);

        $workspace = Workspace::findOrFail($id);

        $workspace->forceFill($data)->save();

        AuditLog::record('admin.limits_overridden', $workspace, $data + [
            'oleh' => $request->user()->email,
        ], $workspace->id);

        return back()->with('status', "Batas {$workspace->name} disesuaikan.");
    }
}
