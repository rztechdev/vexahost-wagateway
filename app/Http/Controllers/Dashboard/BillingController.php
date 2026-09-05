<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\AuditLog;
use App\Services\Billing\QrisManual;
use App\Services\Billing\SubscriptionService;
use App\Support\PhoneNumber;
use App\Support\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly QrisManual $qris,
    ) {}

    /**
     * Ringkasan langganan.
     *
     * Tiga hal yang dulu menumpuk di satu halaman — keadaan langganan, daftar
     * paket, dan riwayat tagihan — sekarang punya alamatnya masing-masing.
     * Bukan sekadar demi kerapian: yang paling sering dibuka adalah "berapa
     * lama lagi langganan saya berlaku", dan sebelumnya jawaban itu terkubur
     * di atas dua blok panjang yang jarang dibutuhkan. Halaman terpisah juga
     * bisa ditautkan langsung — dari spanduk, dari pengingat WhatsApp, dari
     * panel admin — tanpa mengandalkan pengguna menggulir ke bagian yang benar.
     */
    public function index(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.billing.index', [
            'subscription' => $this->subscriptions->ensureFor($workspace),
            'usage' => $workspace->currentUsage(),

            // Hanya tagihan terbuka yang ditampilkan di sini; sisanya di halaman
            // riwayat. Yang butuh tindakan sekarang tidak boleh berbagi tempat
            // dengan yang sudah selesai setahun lalu.
            'tagihanTerbuka' => $workspace->invoices()
                ->where('status', 'pending')
                ->where('due_at', '>', now())
                ->latest()
                ->first(),

            // Anggota biasa boleh melihat status langganan — mereka perlu tahu
            // kenapa pengiriman berhenti — tapi tidak boleh mengeluarkan uang
            // atas nama workspace.
            'bolehBayar' => $request->user()->canManage($workspace),
        ]);
    }

    /** Daftar paket dan tombol pilihnya. */
    public function plans(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.billing.plans', [
            'subscription' => $this->subscriptions->ensureFor($workspace),
            'plans' => Plan::all(),
            'bolehBayar' => $request->user()->canManage($workspace),
        ]);
    }

    /** Riwayat tagihan, berhalaman. */
    public function history(Request $request): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        return view('dashboard.billing.history', [
            'subscription' => $this->subscriptions->ensureFor($workspace),
            'invoices' => $workspace->invoices()->latest()->paginate(20),
        ]);
    }

    /**
     * Memilih paket dan menerbitkan tagihannya.
     *
     * Tidak ada perubahan paket yang terjadi di sini. Paket berpindah saat
     * tagihannya lunas, bukan saat pelanggan mengklik — kalau tidak, menaikkan
     * paket lalu tidak membayar akan menaikkan batasnya secara cuma-cuma.
     */
    public function checkout(Request $request): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        abort_unless($request->user()->canManage($workspace), 403, 'Hanya owner atau admin yang bisa mengurus langganan.');

        $data = $request->validate([
            'plan' => ['required', Rule::in(array_keys(config('plans.catalog')))],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $invoice = $this->subscriptions->issueInvoice($workspace, $data['plan'], $data['period']);

        return redirect()->route('billing.invoice', $invoice->id);
    }

    public function invoice(Request $request, int $id): View
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $invoice = $workspace->invoices()->findOrFail($id);

        return view('dashboard.billing.invoice', [
            'invoice' => $invoice,
            'plan' => $invoice->plan(),

            // Payload disusun ulang setiap kali halaman dibuka, tidak disimpan
            // di kolom. Isinya sepenuhnya turunan dari nominal tagihan dan
            // payload merchant di env; menyimpannya berarti tagihan lama
            // membawa kode QR yang menunjuk ke merchant yang mungkin sudah
            // diganti, dan tidak ada yang akan menyadarinya sampai ada yang
            // memindainya.
            'qrisPayload' => $invoice->isPending() && ! $invoice->isOverdue()
                ? $this->qris->payload($invoice)
                : null,

            'merchant' => $this->qris->merchantName(),
            'bank' => $this->qris->bankAccount(),
            'bolehBayar' => $request->user()->canManage($workspace),

            // Data penagihan dipakai dua kali di halaman ini: mengisi form di
            // kolom kiri, dan mencetak "ditagihkan kepada" di ringkasan kanan.
            'penagihan' => [
                'nama' => $workspace->billing_name ?: $workspace->name,
                'email' => $workspace->billing_email ?: ($workspace->owner_email ?: $request->user()->email),
                'telepon' => $workspace->billing_phone,
                'lengkap' => filled($workspace->billing_name) && filled($workspace->billing_email),
            ],
        ]);
    }

    /**
     * Menyimpan data penagihan dari halaman checkout.
     *
     * Tidak menyentuh tagihannya sama sekali — data ini melekat pada workspace,
     * jadi mengisinya sekali berlaku untuk seluruh tagihan berikutnya. Pengguna
     * dikembalikan ke halaman yang sama supaya ia bisa langsung lanjut membayar
     * tanpa kehilangan tempatnya.
     */
    public function saveBillingDetails(Request $request, int $id): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        abort_unless($request->user()->canManage($workspace), 403, 'Hanya owner atau admin yang bisa mengurus langganan.');

        $workspace->invoices()->findOrFail($id);

        $data = $request->validate([
            'billing_name' => ['required', 'string', 'max:120'],
            'billing_email' => ['required', 'email', 'max:180'],
            'billing_phone' => ['nullable', 'string', 'max:20'],
        ], [], [
            'billing_name' => 'nama penagihan',
            'billing_email' => 'email penagihan',
            'billing_phone' => 'nomor WhatsApp',
        ]);

        // Nomor dinormalkan seperti nomor tujuan pesan mana pun; pengingat yang
        // dikirim ke `08...` gagal diam-diam, dan gagalnya baru ketahuan sebagai
        // pelanggan yang tidak pernah tahu langganannya habis.
        $nomor = filled($data['billing_phone'] ?? null)
            ? PhoneNumber::normalize($data['billing_phone'])
            : null;

        if (filled($data['billing_phone'] ?? null) && $nomor === null) {
            return back()->withErrors(['billing_phone' => 'Nomor WhatsApp tidak valid.'])->withInput();
        }

        $workspace->forceFill([
            'billing_name' => $data['billing_name'],
            'billing_email' => $data['billing_email'],
            'billing_phone' => $nomor,
        ])->save();

        return back()->with('status', 'Data penagihan disimpan.');
    }

    /**
     * Mengunggah bukti transfer.
     *
     * Bukti tidak mengaktifkan apa pun dengan sendirinya — ia hanya memindahkan
     * tagihan ke antrean pemeriksaan admin. Menganggap unggahan sebagai
     * pembayaran berarti siapa pun bisa menyalakan layanannya sendiri dengan
     * memilih gambar apa saja dari galeri.
     */
    public function uploadProof(Request $request, int $id): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        abort_unless($request->user()->canManage($workspace), 403, 'Hanya owner atau admin yang bisa mengurus langganan.');

        $invoice = $workspace->invoices()->findOrFail($id);

        if (! $invoice->isPending()) {
            return back()->withErrors(['bukti' => 'Tagihan ini sudah tidak menunggu pembayaran.']);
        }

        $request->validate([
            'bukti' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [], ['bukti' => 'bukti transfer']);

        // Disk privat, bukan publik: bukti transfer memuat nama, nomor rekening,
        // dan saldo orang. Admin membukanya lewat rute yang memeriksa izin.
        $path = $request->file('bukti')->store('bukti-bayar', 'media');

        $invoice->forceFill(['proof_path' => $path])->save();

        AuditLog::record('invoice.proof_uploaded', $invoice, [
            'number' => $invoice->number,
        ], $workspace->id);

        return back()->with('status', 'Bukti transfer terkirim. Kami memeriksanya dan mengaktifkan langganan Anda, biasanya dalam beberapa jam pada jam kerja.');
    }

    public function cancelInvoice(Request $request, int $id): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        abort_unless($request->user()->canManage($workspace), 403, 'Hanya owner atau admin yang bisa mengurus langganan.');

        $invoice = $workspace->invoices()->findOrFail($id);

        if (! $invoice->isPending()) {
            return back()->withErrors(['tagihan' => 'Hanya tagihan yang menunggu pembayaran yang bisa dibatalkan.']);
        }

        $invoice->forceFill(['status' => 'canceled'])->save();

        AuditLog::record('invoice.canceled', $invoice, [
            'number' => $invoice->number,
        ], $workspace->id);

        return redirect()->route('billing.index')->with('status', "Tagihan {$invoice->number} dibatalkan.");
    }

    /**
     * Menyajikan bukti transfer yang tersimpan di disk privat.
     *
     * Dijaga oleh kepemilikan workspace, bukan sekadar oleh sulitnya menebak
     * nama berkas: `findOrFail` di atas berangkat dari workspace yang sedang
     * dibuka, jadi id tagihan milik orang lain berakhir 404 bukan gambar.
     */
    public function proof(Request $request, int $id)
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $invoice = $workspace->invoices()->findOrFail($id);

        abort_if(blank($invoice->proof_path), 404);

        return response()->file(
            Storage::disk('media')->path($invoice->proof_path)
        );
    }
}
