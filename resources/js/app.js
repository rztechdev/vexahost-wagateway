import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import QRCode from 'qrcode';
import Swal from 'sweetalert2';

// Tur pengenalan untuk pendaftar baru. Modulnya menonaktifkan dirinya sendiri
// kalau halaman tidak memuat data tur, jadi tidak ada percabangan di sini.
import './tur-pengenalan';

Alpine.plugin(collapse);
window.Alpine = Alpine;

Alpine.start();

// Semua form dashboard memakai POST biasa, jadi token CSRF cukup dibaca dari
// meta tag saat ada fetch() manual (mis. polling status sesi).
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/*
 * Kode QR untuk pembayaran QRIS digambar di sisi peramban.
 *
 * Payload-nya sudah jadi saat halaman dirender — yang dikerjakan di sini cuma
 * menggambar. Alternatifnya, menghasilkan gambar di server, menuntut pustaka
 * PHP baru untuk sesuatu yang sudah bisa dilakukan pustaka yang ikut di bundel
 * frontend, di mesin yang RAM-nya justru paling diperebutkan.
 *
 * Beda dengan QR sesi WhatsApp, yang datang dari engine sebagai data URL lewat
 * callback: yang itu berubah tiap menit dan harus menyusul, yang ini tetap
 * selama tagihannya berlaku.
 */
function renderQrisCodes() {
    document.querySelectorAll('[data-qris]').forEach((el) => {
        QRCode.toCanvas(el, el.dataset.qris, { width: 260, margin: 1 }, (error) => {
            if (! error) {
                el.style.width = '160px';
                el.style.height = '160px';
                return;
            }

            // Kegagalan menggambar tidak boleh menyisakan kotak kosong tanpa
            // penjelasan di halaman tempat orang sedang berusaha membayar.
            el.insertAdjacentHTML(
                'afterend',
                '<p class="text-sm text-destructive">Kode QR gagal ditampilkan. Silakan pakai transfer bank di bawah.</p>'
            );
        });
    });
}

window.renderQris = renderQrisCodes;
document.addEventListener('DOMContentLoaded', renderQrisCodes);

/**
 * QR untuk aplikasi authenticator (URI `otpauth://`).
 *
 * Terpisah dari QRIS meski memakai pustaka yang sama, karena pesan gagalnya
 * harus berbeda: kalau QRIS gagal digambar masih ada transfer bank, tapi kalau
 * QR ini gagal, satu-satunya jalan yang tersisa adalah mengetik rahasianya
 * secara manual — dan kalimat itu yang harus muncul, bukan saran membayar.
 */
function renderOtpCodes() {
    document.querySelectorAll('[data-otpauth]').forEach((el) => {
        QRCode.toCanvas(el, el.dataset.otpauth, { width: 220, margin: 1 }, (error) => {
            if (! error) {
                el.style.width = '180px';
                el.style.height = '180px';
                return;
            }

            el.insertAdjacentHTML(
                'afterend',
                '<p class="text-sm text-destructive">Kode QR gagal ditampilkan. Masukkan kunci di bawah secara manual ke aplikasi authenticator Anda.</p>'
            );
        });
    });
}

document.addEventListener('DOMContentLoaded', renderOtpCodes);

/* ---------------------------------------------------------------------------
   Pemberitahuan hasil tindakan.

   Dipakai untuk momen yang menentukan — bukti terkirim, pembayaran ditandai
   lunas, tagihan gagal dibuat — bukan untuk setiap pesan kecil. Latar belakang
   perlunya: pelanggan pernah mengunggah bukti, tidak menemukan tanda yang cukup
   jelas bahwa ia diterima, mengira gagal, lalu membatalkan tagihannya sendiri.
   Spanduk hijau tipis di atas halaman ternyata tidak cukup untuk keputusan yang
   menyangkut uang.
   --------------------------------------------------------------------------- */
const warnaTombol = () =>
    getComputedStyle(document.documentElement).getPropertyValue('--primary').trim() || '#2e7d32';

window.beriTahu = function beriTahu(opsi) {
    return Swal.fire({
        icon: opsi.icon || 'info',
        title: opsi.title || '',
        text: opsi.text || undefined,
        html: opsi.html || undefined,
        confirmButtonText: opsi.confirmButtonText || 'Mengerti',
        confirmButtonColor: warnaTombol(),
        // Gelap/terang mengikuti tema aplikasi; dialog terang di atas halaman
        // gelap menyilaukan pada dini hari, dan justru di jam itulah orang
        // sering menyelesaikan pembayaran.
        background: getComputedStyle(document.body).backgroundColor,
        color: getComputedStyle(document.body).color,
    });
};

/*
 * Konfirmasi yang menggantikan `confirm()` bawaan peramban.
 *
 * Dipasang lewat `data-konfirmasi="pesan"` pada <form>. Pengiriman ditahan
 * sampai pengguna menekan tombol setuju — tanpa `ditahan`, pengiriman kedua
 * setelah konfirmasi akan tertahan lagi tanpa akhir.
 */
function pasangKonfirmasi() {
    document.querySelectorAll('form[data-konfirmasi]').forEach((form) => {
        if (form.dataset.konfirmasiSiap) return;
        form.dataset.konfirmasiSiap = '1';

        form.addEventListener('submit', (e) => {
            if (form.dataset.ditahan === 'lepas') return;

            e.preventDefault();

            Swal.fire({
                icon: 'warning',
                title: form.dataset.konfirmasiJudul || 'Yakin?',
                text: form.dataset.konfirmasi,
                showCancelButton: true,
                confirmButtonText: form.dataset.konfirmasiYa || 'Ya, lanjutkan',
                cancelButtonText: 'Batal',
                confirmButtonColor: warnaTombol(),
                background: getComputedStyle(document.body).backgroundColor,
                color: getComputedStyle(document.body).color,
            }).then((hasil) => {
                if (! hasil.isConfirmed) return;
                form.dataset.ditahan = 'lepas';
                form.requestSubmit ? form.requestSubmit() : form.submit();
            });
        });
    });
}

/*
 * Menahan pengiriman form saat ada isian wajib yang masih kosong, lalu
 * menyebutkan yang mana.
 *
 * Peramban sudah menolak form seperti ini sendiri, tapi pesannya muncul sebagai
 * gelembung kecil yang hilang dalam hitungan detik dan sering tidak terlihat
 * pada form panjang — terutama saat isian yang kosong ada di luar layar.
 */
function pasangValidasi() {
    document.querySelectorAll('form[data-validasi]').forEach((form) => {
        if (form.dataset.validasiSiap) return;
        form.dataset.validasiSiap = '1';

        form.addEventListener('submit', (e) => {
            const kosong = [...form.querySelectorAll('[required]')].filter((el) =>
                el.type === 'file' ? el.files.length === 0 : ! String(el.value).trim()
            );

            if (kosong.length === 0) return;

            e.preventDefault();
            e.stopImmediatePropagation();

            const nama = kosong.map((el) => {
                const label = form.querySelector(`label[for="${el.id}"]`);
                return label ? label.textContent.trim().replace(/\s+/g, ' ') : (el.name || 'isian');
            });

            window.beriTahu({
                icon: 'warning',
                title: 'Ada yang belum diisi',
                html: 'Lengkapi dulu:<br><strong>' + nama.join('</strong><br><strong>') + '</strong>',
            });

            kosong[0].focus();
        });
    });
}

/*
 * Pesan dari server, dititipkan lewat <script type="application/json">.
 * Ditulis sebagai JSON, bukan sebagai kode JS yang di-echo, supaya teks pesan
 * yang memuat kutip atau tanda kurung tidak pernah bisa merusak halaman.
 */
function tampilkanPesanServer() {
    const wadah = document.getElementById('pesan-server');
    if (! wadah) return;

    try {
        const pesan = JSON.parse(wadah.textContent);
        if (pesan && pesan.title) window.beriTahu(pesan);
    } catch (e) {
        // Pesan yang rusak tidak boleh menjatuhkan sisa halaman.
    }
}

document.addEventListener('DOMContentLoaded', () => {
    pasangKonfirmasi();
    pasangValidasi();
    tampilkanPesanServer();
});
