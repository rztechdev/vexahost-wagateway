import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.start();

// Semua form dashboard memakai POST biasa, jadi token CSRF cukup dibaca dari
// meta tag saat ada fetch() manual (mis. polling status sesi).
window.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
