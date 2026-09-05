<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Services\Billing\SubscriptionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Antrean pemeriksaan pembayaran.
 *
 * Selama saluran pembayaran masih QRIS manual, halaman ini adalah bagian
 * sistem yang tidak boleh dilewati siapa pun: tidak ada notifikasi otomatis,
 * jadi tagihan hanya menjadi lunas kalau ada orang yang membukanya di sini.
 * Karena itu urutannya bukan berdasarkan tanggal, melainkan berdasarkan
 * tagihan mana yang sudah ada buktinya dan sedang ditunggu pelanggannya.
 */
class InvoiceController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $cari = trim((string) $request->query('cari'));

        return view('admin.invoices', [
            'invoices' => Invoice::query()
                ->with(['workspace', 'paidBy'])
                ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
                ->when($cari !== '', fn ($q) => $q->where(function ($q) use ($cari) {
                    $q->where('number', 'like', "%{$cari}%")
                        ->orWhere('total', 'like', "%{$cari}%")
                        ->orWhereHas('workspace', fn ($w) => $w->where('name', 'like', "%{$cari}%"));
                }))
                // Yang sudah ada buktinya naik ke atas: di situlah ada orang
                // yang sudah membayar dan layanannya masih mati.
                ->orderByRaw('proof_path is null')
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'status' => $status,
            'cari' => $cari,
        ]);
    }

    /**
     * Menandai lunas. Di sinilah langganan benar-benar diperpanjang, batas
     * paket diterapkan, dan workspace yang tertangguh menyala kembali.
     */
    public function markPaid(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->isPaid()) {
            return back()->withErrors(['tagihan' => 'Tagihan ini sudah lunas.']);
        }

        $this->subscriptions->markPaid($invoice, $request->user(), $data['catatan'] ?? null);

        return back()->with('status', "Tagihan {$invoice->number} ditandai lunas dan langganannya diperpanjang.");
    }

    public function updateStatus(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['expired', 'canceled'])],
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($invoice->isPaid()) {
            return back()->withErrors(['tagihan' => 'Tagihan yang sudah lunas tidak bisa diubah statusnya.']);
        }

        $invoice->forceFill(['status' => $data['status']])->save();

        AuditLog::record('admin.invoice_status_changed', $invoice, [
            'status' => $data['status'],
            'oleh' => $request->user()->email,
        ], $invoice->workspace_id);

        return back()->with('status', "Tagihan {$invoice->number} ditandai {$data['status']}.");
    }

    public function proof(int $id)
    {
        $invoice = Invoice::findOrFail($id);

        abort_if(blank($invoice->proof_path), 404);

        return response()->file(Storage::disk('media')->path($invoice->proof_path));
    }
}
