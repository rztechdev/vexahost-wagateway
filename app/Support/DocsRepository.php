<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Dokumentasi produk yang tampil di website.
 *
 * Sumbernya berkas markdown di `resources/docs/` — dokumentasi untuk PENGGUNA:
 * cara memakai produk, dan contoh kode bagi developer yang memanggil API.
 *
 * Sengaja dipisah dari `docs/` yang berisi dokumentasi teknis internal
 * (arsitektur, referensi kode, cara memasang source code, deployment). Berkas
 * di sana tidak pernah disajikan lewat web: pelanggan tidak perlu — dan tidak
 * boleh — melihat isi dapur sistem yang mereka sewa.
 */
class DocsRepository
{
    /** Lokasi berkas dokumentasi produk, relatif terhadap root aplikasi. */
    private const DIR = 'resources/docs';

    /**
     * Katalog halaman. Daftar ini sekaligus daftar putih: hanya berkas yang
     * tercantum di sini yang bisa dibuka, sehingga slug dari URL tidak pernah
     * dipakai untuk menyusun path berkas.
     *
     * @return array<string, array<string, array{file: string, title: string, summary: string}>>
     */
    public static function catalogue(): array
    {
        return [
            'Mulai' => [
                'mulai-cepat' => [
                    'file' => 'MULAI_CEPAT.md',
                    'title' => 'Mulai Cepat',
                    'summary' => 'Dari mendaftar sampai pesan pertama terkirim, di bawah lima menit.',
                ],
                'menautkan-nomor' => [
                    'file' => 'MENAUTKAN_NOMOR.md',
                    'title' => 'Menautkan Nomor',
                    'summary' => 'Menghubungkan nomor WhatsApp, memantau keadaannya, dan menggantinya.',
                ],
                'mengirim-pesan' => [
                    'file' => 'MENGIRIM_PESAN.md',
                    'title' => 'Mengirim Pesan',
                    'summary' => 'Teks, lampiran, pengiriman massal, dan cara melacak statusnya.',
                ],
                'template-pesan' => [
                    'file' => 'TEMPLATE_PESAN.md',
                    'title' => 'Template Pesan',
                    'summary' => 'Menyimpan susunan pesan yang sering dipakai dan mengisi bagian yang berubah.',
                ],
            ],

            'Untuk Developer' => [
                'referensi-api' => [
                    'file' => 'REFERENSI_API.md',
                    'title' => 'Referensi API',
                    'summary' => 'Setiap endpoint dengan parameter, contoh, dan kode galatnya.',
                ],
                'contoh-integrasi' => [
                    'file' => 'CONTOH_INTEGRASI.md',
                    'title' => 'Contoh Integrasi',
                    'summary' => 'Kode siap pakai untuk PHP/Laravel, Node.js, dan Python.',
                ],
                'webhook' => [
                    'file' => 'WEBHOOK.md',
                    'title' => 'Webhook',
                    'summary' => 'Menerima pesan masuk dan perubahan status di aplikasi Anda.',
                ],
                'api-key' => [
                    'file' => 'API_KEY.md',
                    'title' => 'API Key & Keamanan',
                    'summary' => 'Membuat, memakai, dan menjaga kunci akses tetap aman.',
                ],
                'integrasi-ai-agent' => [
                    'file' => 'INTEGRASI_AI_AGENT.md',
                    'title' => 'Integrasi AI Agent',
                    'summary' => 'Prompt dan panduan integrasi untuk Claude Code, Cursor, Antigravity, OpenCode, dan Codex.',
                ],
            ],

            'Panduan' => [
                'praktik-baik' => [
                    'file' => 'PRAKTIK_BAIK.md',
                    'title' => 'Praktik Baik',
                    'summary' => 'Cara memakai gateway tanpa membuat nomor Anda diblokir WhatsApp.',
                ],
                'langganan-dan-tagihan' => [
                    'file' => 'LANGGANAN_DAN_TAGIHAN.md',
                    'title' => 'Langganan & Tagihan',
                    'summary' => 'Paket, cara membayar, perpanjangan, dan apa yang terjadi saat langganan habis.',
                ],
                'batas-dan-kuota' => [
                    'file' => 'BATAS_DAN_KUOTA.md',
                    'title' => 'Batas & Kuota',
                    'summary' => 'Batas yang berlaku di workspace Anda dan apa yang terjadi saat tercapai.',
                ],
                'bantuan' => [
                    'file' => 'BANTUAN.md',
                    'title' => 'Bantuan & Tiket',
                    'summary' => 'Menghubungi kami lewat tiket, dan apa yang terjadi sesudahnya.',
                ],
                'faq' => [
                    'file' => 'FAQ.md',
                    'title' => 'Tanya Jawab',
                    'summary' => 'Pertanyaan yang paling sering muncul.',
                ],
                'glosarium' => [
                    'file' => 'GLOSARIUM.md',
                    'title' => 'Glosarium',
                    'summary' => 'Istilah yang dipakai di dashboard dan dokumentasi ini.',
                ],
            ],
        ];
    }

    /** @return array<string, array{file: string, title: string, summary: string, group: string}> */
    public static function flat(): array
    {
        $flat = [];

        foreach (self::catalogue() as $group => $pages) {
            foreach ($pages as $slug => $page) {
                $flat[$slug] = $page + ['group' => $group];
            }
        }

        return $flat;
    }

    /**
     * @return array{title: string, summary: string, group: string, slug: string, html: string, toc: array<int, array{id: string, text: string}>, prev: ?array, next: ?array}
     */
    public static function page(string $slug): array
    {
        $flat = self::flat();

        if (! isset($flat[$slug])) {
            throw new NotFoundHttpException("Halaman dokumentasi '{$slug}' tidak ada.");
        }

        // Di luar local, hasil render disimpan sementara: isinya hanya berubah
        // saat deploy, jadi tidak ada gunanya mem-parsing markdown di setiap
        // permintaan. Di local sengaja tidak di-cache supaya perubahan langsung
        // terlihat tanpa perlu membersihkan cache.
        $render = fn () => self::render($flat[$slug]['file']);

        $rendered = app()->environment('local')
            ? $render()
            : Cache::remember("docs:{$slug}", now()->addDay(), $render);

        $slugs = array_keys($flat);
        $position = array_search($slug, $slugs, true);

        return $flat[$slug] + $rendered + [
            'slug' => $slug,
            'prev' => $position > 0 ? ['slug' => $slugs[$position - 1]] + $flat[$slugs[$position - 1]] : null,
            'next' => isset($slugs[$position + 1]) ? ['slug' => $slugs[$position + 1]] + $flat[$slugs[$position + 1]] : null,
        ];
    }

    /** @return array{html: string, toc: array<int, array{id: string, text: string}>} */
    private static function render(string $file): array
    {
        $path = base_path(self::DIR."/{$file}");

        if (! is_file($path)) {
            throw new NotFoundHttpException("Berkas dokumentasi {$file} tidak ditemukan.");
        }

        $markdown = file_get_contents($path);

        // Judul halaman sudah ditampilkan terpisah oleh layout, jadi H1 pertama
        // dibuang agar tidak muncul dua kali, termasuk garis pemisah (---) setelahnya.
        $markdown = preg_replace('/\A#\s+[^\r\n]*\r?\n+(?:---\r?\n+)?/u', '', $markdown, 1);

        $html = Str::markdown($markdown);

        [$html, $toc] = self::addHeadingAnchors($html);

        return ['html' => self::rewriteInternalLinks($html), 'toc' => $toc];
    }

    /**
     * Memberi id pada setiap judul supaya bisa ditautkan, sekaligus mengumpulkan
     * daftar isi halaman.
     *
     * @return array{0: string, 1: array<int, array{id: string, text: string}>}
     */
    private static function addHeadingAnchors(string $html): array
    {
        $toc = [];

        $html = preg_replace_callback(
            '/<h([23])>(.*?)<\/h\1>/su',
            function (array $m) use (&$toc): string {
                $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $id = Str::slug($text) ?: 'bagian-'.count($toc);

                if ($m[1] === '2') {
                    $toc[] = ['id' => $id, 'text' => $text];
                }

                return "<h{$m[1]} id=\"{$id}\">{$m[2]}</h{$m[1]}>";
            },
            $html
        ) ?? $html;

        return [$html, $toc];
    }

    /**
     * Dokumen saling menaut memakai nama berkas (`[Webhook](WEBHOOK.md)`), yang
     * benar saat berkasnya dibaca langsung tapi menghasilkan 404 di web.
     *
     * Teks tautan ikut diganti bila isinya cuma nama berkas — "lihat WEBHOOK.md"
     * terbaca janggal bagi pengguna, sementara "lihat Webhook" wajar.
     */
    private static function rewriteInternalLinks(string $html): string
    {
        $byFile = [];

        foreach (self::flat() as $slug => $page) {
            $byFile[$page['file']] = ['slug' => $slug, 'title' => $page['title']];
        }

        return preg_replace_callback(
            '/<a href="([A-Za-z_]+\.md)(#[^"]*)?">(.*?)<\/a>/su',
            function (array $m) use ($byFile): string {
                $target = $byFile[$m[1]] ?? null;

                // Berkas di luar katalog diarahkan ke indeks, bukan dibiarkan
                // menunjuk ke alamat yang pasti 404.
                $url = $target ? route('docs.show', $target['slug']) : route('docs.index');
                $text = $target && trim($m[3]) === $m[1] ? $target['title'] : $m[3];

                return '<a href="'.$url.($m[2] ?? '').'">'.$text.'</a>';
            },
            $html
        ) ?? $html;
    }
}
