<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureWorkspaceSelected;
use App\Models\AuditLog;
use App\Services\Billing\QrisManual;
use App\Services\Billing\ReferralService;
use App\Services\Billing\SubscriptionService;
use App\Services\Notifications\BillingMessages;
use App\Services\Notifications\WhatsAppNotifier;
use App\Support\PhoneNumber;
use App\Support\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly QrisManual $qris,
        private readonly WhatsAppNotifier $notifier,
        private readonly ReferralService $referrals,
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
            'referral' => ['nullable', 'string', 'max:16'],
        ]);

        $referral = null;

        if (filled($data['referral'] ?? null)) {
            /*
             | Tagihan yang sudah terbit tidak diberi diskon belakangan.
             |
             | `issueInvoice()` mengembalikan tagihan pending yang sama untuk
             | paket dan periode yang sama, jadi kodenya akan diterima lalu
             | diabaikan diam-diam — pelanggan membaca "kode diterima" dan
             | mentransfer nominal yang tidak pernah berubah. Menambal diskon ke
             | tagihan lama juga bukan jawabannya: nominalnya ikut berubah,
             | sementara pelanggan bisa saja sudah mentransfer angka yang lama.
             | Membatalkan lalu menerbitkan ulang adalah satu-satunya jalan yang
             | tidak menghasilkan dua angka untuk satu tagihan.
            */
            $adaTagihan = $workspace->invoices()
                ->where('status', 'pending')
                ->where('plan_slug', $data['plan'])
                ->where('period', $data['period'])
                ->where('due_at', '>', now())
                ->first();

            if ($adaTagihan) {
                return back()->withErrors(['referral' => "Tagihan {$adaTagihan->number} untuk paket ini "
                    .'sudah terbit tanpa kode referal. Batalkan tagihan itu dulu, lalu pilih paketnya lagi '
                    .'dengan kodenya — supaya nominal yang Anda transfer tidak berubah di tengah jalan.']);
            }

            try {
                $referral = $this->referrals->periksa($data['referral'], $workspace);
            } catch (\RuntimeException $e) {
                return back()->withErrors(['referral' => $e->getMessage()])->withInput();
            }
        }

        $invoice = $this->subscriptions->issueInvoice($workspace, $data['plan'], $data['period'], $referral);

        return redirect()->route('billing.invoice', $invoice->id);
    }

    /**
     * Meninjau kode referal sebelum tagihan terbit.
     *
     * Dipisah dari checkout dengan sengaja: pelanggan harus bisa melihat
     * potongannya **sebelum** memutuskan, dan satu-satunya cara lain adalah
     * menerbitkan tagihan dulu lalu membatalkannya kalau kodenya ternyata
     * ditolak — memaksa orang membuat tagihan untuk sesuatu yang belum mereka
     * putuskan.
     */
    public function reviewReferral(Request $request): JsonResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'plan' => ['required', Rule::in(array_keys(config('plans.catalog')))],
            'period' => ['required', Rule::in(['monthly', 'yearly'])],
        ]);

        $harga = Plan::get($data['plan'])->price($data['period']);
        $nominal = $harga + (int) round($harga * config('billing.tax_percent') / 100);

        return response()->json($this->referrals->tinjau($data['code'], $workspace, $nominal));
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

        return back()->with('swal', [
            'icon' => 'success',
            'title' => 'Data penagihan disimpan',
            'text' => 'Nama dan email ini akan tercetak di tagihan Anda.',
        ]);
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
            return back()->with('swal', [
                'icon' => 'error',
                'title' => 'Bukti tidak bisa dikirim',
                'text' => 'Tagihan ini sudah tidak menunggu pembayaran.',
            ]);
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

        // Tanda terima ke pelanggan, dan panggilan ke kami sendiri. Yang kedua
        // yang paling menentukan: selama pencocokan masih manual, tagihan hanya
        // menjadi lunas kalau ada orang yang membukanya di panel — dan tanpa
        // pesan ini tidak ada apa pun yang memberi tahu ada yang perlu dibuka.
        $this->notifier->toWorkspace(
            $workspace,
            BillingMessages::proofReceived($invoice),
            "proof-received:{$invoice->id}",
        );

        $this->notifier->toAdmin(
            BillingMessages::adminProofWaiting($invoice),
            "admin-proof:{$invoice->id}",
        );

        /*
         | Dialihkan ke halaman tersendiri, bukan kembali ke form.
         |
         | Kembali ke halaman yang sama dengan spanduk hijau tipis ternyata tidak
         | cukup meyakinkan: pelanggan pernah mengunggah bukti, tidak merasa ada
         | yang berubah, mengira gagal, lalu membatalkan tagihannya sendiri 24
         | detik kemudian. Halaman yang seluruhnya berbicara tentang "bukti Anda
         | sudah kami terima" tidak menyisakan ruang untuk keraguan itu.
         */
        return redirect()
            ->route('billing.verifying', $invoice->id)
            ->with('swal', [
                'icon' => 'success',
                'title' => 'Bukti pembayaran terkirim',
                'text' => 'Kami sudah menerimanya. Tim kami memeriksa dan mengaktifkan langganan Anda, biasanya dalam beberapa jam pada jam kerja.',
                'confirmButtonText' => 'Baik',
            ]);
    }

    /**
     * Halaman "bukti sudah kami terima, sedang diperiksa".
     *
     * Punya alamat sendiri supaya bisa ditautkan dan dibuka lagi kapan saja —
     * pelanggan yang menutup tab lalu bertanya-tanya apakah buktinya benar
     * terkirim punya satu tempat pasti untuk memeriksanya, tanpa harus menebak
     * dari status di halaman lain.
     */
    public function verifying(Request $request, int $id): View|RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $invoice = $workspace->invoices()->findOrFail($id);

        // Tagihan yang sudah dijawab tidak lagi "menunggu diperiksa"; halaman
        // checkout yang tahu cara menampilkan keadaan akhirnya.
        if ($invoice->isPaid() || blank($invoice->proof_path)) {
            return redirect()->route('billing.invoice', $invoice->id);
        }

        return view('dashboard.billing.verifying', [
            'invoice' => $invoice,
            'plan' => $invoice->plan(),
            'subscription' => $this->subscriptions->ensureFor($workspace),
        ]);
    }

    /**
     * Keadaan satu tagihan sebagai JSON, untuk ditanyakan berkala oleh halaman
     * menunggu verifikasi.
     *
     * Ada karena halaman itu menjanjikan "berubah sendiri begitu selesai", dan
     * janji yang tidak ditepati di halaman pembayaran adalah cara tercepat
     * membuat orang menekan tombol yang tidak seharusnya — persis yang dulu
     * terjadi saat pelanggan membatalkan tagihannya sendiri.
     *
     * Sengaja sekecil mungkin: hanya status dan ke mana harus pergi. Tidak ada
     * nominal, nama, atau apa pun yang tidak dibutuhkan pemanggilnya.
     */
    public function status(Request $request, int $id): JsonResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        $invoice = $workspace->invoices()->findOrFail($id);

        return response()->json([
            'status' => $invoice->status,
            'lunas' => $invoice->isPaid(),
            'lanjut' => route('billing.invoice', $invoice->id),
        ]);
    }

    public function cancelInvoice(Request $request, int $id): RedirectResponse
    {
        $workspace = EnsureWorkspaceSelected::from($request);

        abort_unless($request->user()->canManage($workspace), 403, 'Hanya owner atau admin yang bisa mengurus langganan.');

        $invoice = $workspace->invoices()->findOrFail($id);

        if (! $invoice->isPending()) {
            return back()->withErrors(['tagihan' => 'Hanya tagihan yang menunggu pembayaran yang bisa dibatalkan.']);
        }

        /*
         | Tagihan yang buktinya sudah dikirim tidak boleh dibatalkan pelanggan.
         |
         | Ini pernah terjadi dan menelan satu pembayaran: bukti terunggah, tidak
         | ada tanda yang cukup jelas bahwa ia diterima, pelanggan mengira gagal
         | lalu membatalkan tagihannya 24 detik kemudian — dan tagihan yang sudah
         | dibatalkan lenyap dari layar admin. Uangnya masuk, layanannya mati,
         | dan tidak ada satu pun tempat yang menunjukkannya.
         */
        if (filled($invoice->proof_path)) {
            return back()->withErrors([
                'tagihan' => 'Bukti pembayaran untuk tagihan ini sudah kami terima, jadi tagihannya tidak bisa dibatalkan sendiri. Hubungi kami kalau ini keliru.',
            ]);
        }

        $invoice->forceFill(['status' => 'canceled'])->save();

        // Kodenya batal ditukar, tapi barisnya TIDAK dihapus: workspace ini
        // tetap terhitung sudah pernah memakai kode referal. Kalau barisnya
        // dibuang, pelanggan yang sama bisa membatalkan tagihannya berulang
        // kali sambil mencoba kode lain sampai menemukan diskon terbesar.
        $this->referrals->batalkan($invoice);

        AuditLog::record('invoice.canceled', $invoice, [
            'number' => $invoice->number,
        ], $workspace->id);

        return redirect()->route('billing.index')->with('swal', [
            'icon' => 'success',
            'title' => 'Tagihan dibatalkan',
            'text' => "{$invoice->number} tidak akan ditagihkan lagi.",
        ]);
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
