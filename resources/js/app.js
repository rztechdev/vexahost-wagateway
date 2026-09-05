import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import QRCode from 'qrcode';

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
