<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Menjaga template environment tetap ada dan tetap selaras.
 *
 * Template ini pernah terhapus tanpa disadari: berkasnya sempat ter-rename
 * kehilangan akhiran `.example`, sehingga cocok dengan pola `.env.*` di
 * .gitignore, lalu `git add -A` menangkap penghapusannya sebagai perubahan
 * yang disengaja. Tidak ada yang gagal — sampai tiba waktunya men-deploy.
 *
 * Tes ini juga menangkap masalah yang lebih halus: variabel baru yang hanya
 * ditambahkan ke satu tahap. Gejalanya baru muncul setelah deploy ke tahap
 * lain, jauh dari penyebabnya.
 */
class EnvTemplateTest extends TestCase
{
    private const LOKAL = '.env.example';

    private const TAHAP = [
        '.env.development.example',
        '.env.staging.example',
        '.env.production.example',
    ];

    private const LARAVEL = [self::LOKAL, ...self::TAHAP];

    /**
     * Template lokal memakai SQLite, sehingga blok sambungan MySQL sengaja
     * dikomentari di sana. Ini satu-satunya perbedaan yang dibolehkan antara
     * template lokal dan template per tahap.
     */
    private const KHUSUS_SERVER = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    private const ENGINE = [
        'engine/.env.example',
        'engine/.env.development.example',
        'engine/.env.staging.example',
        'engine/.env.production.example',
    ];

    public function test_semua_template_environment_ada(): void
    {
        foreach ([...self::LARAVEL, ...self::ENGINE] as $berkas) {
            $this->assertFileExists(
                base_path($berkas),
                "Template {$berkas} hilang. Deploy ke tahap itu akan kehilangan acuan nilainya."
            );
        }
    }

    /**
     * Ketiga template per tahap harus punya variabel yang persis sama. Variabel
     * yang hanya ditambahkan ke satu tahap akan terlewat saat deploy ke tahap
     * lain, dan gejalanya muncul jauh dari penyebabnya.
     */
    public function test_template_per_tahap_punya_variabel_yang_sama(): void
    {
        $this->assertVariabelSelaras(self::TAHAP, '.env.production.example');
    }

    public function test_template_lokal_mencakup_variabel_yang_sama_selain_sambungan_mysql(): void
    {
        $lokal = array_keys($this->bacaVariabel(self::LOKAL));
        $produksi = array_keys($this->bacaVariabel('.env.production.example'));

        $kurang = array_diff($produksi, $lokal, self::KHUSUS_SERVER);
        $lebih = array_diff($lokal, $produksi);

        $this->assertSame(
            [],
            array_values($kurang),
            self::LOKAL.' kehilangan variabel: '.implode(', ', $kurang)
        );

        $this->assertSame(
            [],
            array_values($lebih),
            self::LOKAL.' punya variabel yang tidak ada di template produksi: '.implode(', ', $lebih)
        );
    }

    public function test_template_engine_punya_variabel_yang_sama(): void
    {
        // HOST sengaja berbeda: lokal 127.0.0.1 supaya tidak terbuka ke
        // jaringan, di server 0.0.0.0 agar container Laravel bisa menjangkaunya.
        $this->assertVariabelSelaras(self::ENGINE, 'engine/.env.example');
    }

    /**
     * Variabel yang wajib terisi nilainya di template per tahap, karena
     * nilainya memang berbeda-beda dan tidak boleh tertinggal kosong.
     */
    public function test_template_per_tahap_menyebut_domain_dan_database_masing_masing(): void
    {
        $harapan = [
            '.env.development.example' => ['wa-dev.flustra.tech', 'db_flustra-wa_dev'],
            '.env.staging.example' => ['wa-staging.flustra.tech', 'db_flustra-wa_staging'],
            '.env.production.example' => ['wa.flustra.id', 'db_flustra-wa'],
        ];

        foreach ($harapan as $berkas => $nilai) {
            $isi = file_get_contents(base_path($berkas));

            foreach ($nilai as $dicari) {
                $this->assertStringContainsString(
                    $dicari,
                    $isi,
                    "{$berkas} tidak menyebut '{$dicari}'."
                );
            }
        }
    }

    /**
     * Rahasia tidak boleh punya nilai bawaan di template. Nilai bawaan cenderung
     * ikut ter-deploy apa adanya, dan rahasia yang sama di semua tahap membuat
     * kebocoran di development berlaku juga untuk produksi.
     */
    public function test_template_tidak_memuat_nilai_rahasia(): void
    {
        $wajibKosong = ['APP_KEY', 'ENGINE_TOKEN', 'ENGINE_HMAC_SECRET', 'DB_PASSWORD'];

        foreach ([...self::LARAVEL, ...self::ENGINE] as $berkas) {
            foreach ($this->bacaVariabel($berkas) as $kunci => $nilai) {
                if (in_array($kunci, $wajibKosong, true)) {
                    $this->assertSame(
                        '',
                        $nilai,
                        "{$berkas} memuat nilai untuk {$kunci}. Rahasia tidak boleh punya nilai bawaan."
                    );
                }
            }
        }
    }

    /** @param  array<int, string>  $berkasList */
    private function assertVariabelSelaras(array $berkasList, string $acuan): void
    {
        $kunciAcuan = array_keys($this->bacaVariabel($acuan));

        foreach ($berkasList as $berkas) {
            if ($berkas === $acuan) {
                continue;
            }

            $kunci = array_keys($this->bacaVariabel($berkas));

            $kurang = array_diff($kunciAcuan, $kunci);
            $lebih = array_diff($kunci, $kunciAcuan);

            $this->assertSame(
                [],
                array_values($kurang),
                "{$berkas} kehilangan variabel yang ada di {$acuan}: ".implode(', ', $kurang)
            );

            $this->assertSame(
                [],
                array_values($lebih),
                "{$berkas} punya variabel yang tidak ada di {$acuan}: ".implode(', ', $lebih)
            );
        }
    }

    /** @return array<string, string> */
    private function bacaVariabel(string $berkas): array
    {
        $variabel = [];

        foreach (file(base_path($berkas), FILE_IGNORE_NEW_LINES) as $baris) {
            $baris = trim($baris);

            if ($baris === '' || str_starts_with($baris, '#') || ! str_contains($baris, '=')) {
                continue;
            }

            [$kunci, $nilai] = explode('=', $baris, 2);

            $variabel[trim($kunci)] = trim($nilai);
        }

        return $variabel;
    }
}
