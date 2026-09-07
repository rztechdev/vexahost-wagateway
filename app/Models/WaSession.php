<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WaSession extends Model
{
    use HasUlids, SoftDeletes;

    /**
     * Nomor milik perusahaan sendiri, terdaftar di panel admin.
     *
     * Sesi yang memegangnya tidak ikut dilepas saat langganan workspace mati,
     * dan pengiriman darinya tidak dihalangi status workspace — nomor itu milik
     * kami, ditumpangkan di workspace mana pun yang kebetulan memakainya.
     */
    public function isSpecial(): bool
    {
        return SpecialNumber::cocok($this->phone_number);
    }

    /**
     * Hanya sesi milik workspace yang layanannya masih berjalan.
     *
     * Dipakai oleh dua jalur yang menjalankan sesi TANPA ada manusia yang
     * menekan tombol: penjadwal tiap menit dan pemulihan saat engine boot.
     * Keduanya dulu tidak memandang status workspace sama sekali, dan itu
     * membatalkan seluruh penegakan langganan di tempat yang paling mahal:
     *
     *  - `SubscriptionService::releaseSessions()` melepas sesi workspace yang
     *    menunggak setelah masa tenggang — lalu `SyncSessionStatusJob`
     *    menjalankannya lagi satu menit kemudian, tiap menit, selamanya.
     *  - Tiap engine di-deploy ulang, seluruh sesi workspace yang menunggak
     *    ikut dipulihkan dan kembali memakan slot dari tiga yang tersedia
     *    untuk SEMUA pelanggan.
     *
     * Yang bocor di sini bukan pengiriman pesan — itu sudah dijaga
     * `MessageDispatcher::guardWorkspace()`. Yang bocor adalah sumber daya yang
     * paling langka: nomor tetap tertaut, pesan masuk tetap diterima, dan
     * webhook pesan masuk tetap terkirim ke aplikasi pelanggan yang tidak
     * membayar. Itu tetap "memakai layanan".
     */
    public function scopeLayananHidup($query)
    {
        $istimewa = SpecialNumber::daftar();

        // Nomor perusahaan sendiri selalu ikut dipulihkan, apa pun keadaan
        // workspace yang ditumpanginya.
        return $query->where(function ($luar) use ($istimewa): void {
            if ($istimewa !== []) {
                $luar->whereIn('phone_number', $istimewa);
            }

            $luar->orWhere(fn ($q) => $q->whereHas('workspace', function ($w): void {
                $w->where('status', 'active')
                    ->where(function ($t): void {
                        $t->whereNull('service_until')->orWhere('service_until', '>', now());
                    });
            }));
        });
    }

    protected $fillable = [
        'workspace_id',
        'name',
        'driver',
        'status',
        'phone_number',
        'push_name',
        'qr_payload',
        'qr_expires_at',
        'auto_reconnect',
        'connect_failures',
        'last_seen_at',
        'connected_at',
        'last_error',
        'meta',
    ];

    protected $hidden = ['qr_payload'];

    protected function casts(): array
    {
        return [
            'qr_expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'connected_at' => 'datetime',
            'auto_reconnect' => 'boolean',
            'connect_failures' => 'integer',
            'meta' => 'array',
        ];
    }

    /**
     * Berapa kali penjadwal boleh mencoba menyambungkan sesi ini sebelum
     * menyerah.
     *
     * Lima dan bukan satu: `SyncSessionStatusJob` berjalan tiap menit, dan
     * penyebab kegagalan yang paling sering justru yang sembuh sendiri dalam
     * hitungan menit — engine sedang di-deploy ulang, Laravel sedang restart,
     * WhatsApp menolak sesaat. Menyerah pada percobaan pertama berarti nomor
     * pelanggan diam sampai ada manusia yang menekan tombol, untuk gangguan
     * yang sebenarnya sudah lewat sebelum siapa pun sempat melihatnya.
     *
     * Delapan dan bukan delapan puluh: penyebab yang tidak sembuh dalam
     * beberapa menit tidak akan sembuh dalam sejam. Yang dibeli angka ini bukan
     * keberhasilan menyambung melainkan berhentinya badai percobaan — log yang
     * bisa dibaca, dan pelanggan yang melihat satu kalimat jelas alih-alih
     * nomor yang berkedip tiap menit.
     *
     * KENAPA NAIK DARI LIMA. Penghitungnya bertambah dari tiga tempat, dan
     * ketiganya perlu:
     *
     *   `SyncSessionStatusJob` menaikkannya SEBELUM mencoba — satu-satunya cara
     *   membatasi percobaan yang kabar kegagalannya hilang di jalan.
     *
     *   `EngineEventController::onAuthFailure` menaikkannya saat kegagalan
     *   benar-benar dilaporkan — satu-satunya cara membatasi percobaan yang
     *   TIDAK dimulai penjadwal (pemulihan saat engine boot, tombol Hubungkan).
     *
     *   `BootstrapController` menaikkannya saat menyerahkan sesi ke engine yang
     *   baru menyala.
     *
     * Satu percobaan gagal karena itu terhitung satu atau dua tergantung apakah
     * kabarnya sampai. Jangkauan efektifnya 4-8 percobaan, dan itu diterima:
     * yang penting terbatas, bukan angkanya persis. Sejak pembebasan profil ada
     * (engine/src/profil.js), tiap percobaan baru MEMBUNUH pendahulunya lebih
     * dulu — jadi batas ini menghentikan kesia-siaan, bukan lagi menahan ledakan
     * memori.
     */
    public const BATAS_PERCOBAAN_SAMBUNG = 8;

    /**
     * Apakah penjadwal masih boleh mencoba menyambungkan sesi ini sendiri.
     *
     * Hanya berlaku untuk penyambungan OTOMATIS. Manusia yang menekan
     * Hubungkan selalu boleh mencoba, dan percobaannya mengembalikan
     * penghitung ini ke nol — orang yang baru saja memperbaiki sesuatu berhak
     * mendapat jatah penuh, bukan sisa jatah dari kegagalan sebelumnya.
     */
    public function bolehSambungOtomatis(): bool
    {
        return (int) $this->connect_failures < self::BATAS_PERCOBAAN_SAMBUNG;
    }

    /**
     * Mencatat satu percobaan penyambungan otomatis.
     *
     * Yang dihitung PERCOBAAN, bukan kegagalan yang dilaporkan. Kegagalan
     * paling berbahaya di jalur ini justru yang tidak pernah dilaporkan:
     * engine menjawab `start` dengan 200 seketika lalu mengabarkan kegagalannya
     * lewat event yang boleh hilang. Menghitung percobaan membuat batas ini
     * tetap berlaku walau tidak satu pun kabar kegagalan sampai.
     */
    public function catatPercobaanSambung(): void
    {
        $this->forceFill(['connect_failures' => (int) $this->connect_failures + 1])->save();
    }

    /**
     * Mengembalikan penghitung ke nol.
     *
     * Dipanggil pada satu-satunya bukti yang tidak bisa dibantah bahwa
     * penyambungannya berhasil — sesi benar-benar `connected` — dan pada
     * penyambungan yang diminta manusia.
     */
    public function resetPercobaanSambung(): void
    {
        if ((int) $this->connect_failures === 0) {
            return;
        }

        $this->forceFill(['connect_failures' => 0])->save();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(SessionBackup::class);
    }

    public function latestBackup(): ?SessionBackup
    {
        return $this->backups()->latest('backed_up_at')->first();
    }

    public function isConnected(): bool
    {
        return $this->status === 'connected';
    }

    /**
     * QR dari WhatsApp berumur pendek (±20 detik). Setelah lewat, yang tersimpan
     * sudah tidak bisa di-scan dan harus menunggu event `qr` berikutnya.
     */
    public function hasFreshQr(): bool
    {
        return $this->qr_payload !== null
            && $this->qr_expires_at !== null
            && $this->qr_expires_at->isFuture();
    }
}
