<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Setiap skrip shell harus bermode 100755 DI GIT.
 *
 * Uji ini lahir dari produksi yang mati, 8 September 2026:
 *
 *     [FATAL tini (9)] exec ./start.sh failed: Permission denied
 *
 * `start.sh` bermode 100644 di git — tidak executable. Selama ini tidak pernah
 * jadi masalah karena Nixpacks memanggilnya lewat `bash start.sh`. Begitu
 * blok re-exec tini ditambahkan dan meng-exec-nya LANGSUNG, bit yang tidak
 * pernah ada itu jadi wajib, dan container masuk restart loop sebelum satu
 * baris pun sempat berjalan.
 *
 * KENAPA UJI INI PERLU ADA, padahal `bash -n` sudah dijalankan. Kelas kegagalan
 * ini mustahil ditangkap keduanya:
 *
 *   `bash -n` memeriksa sintaks, bukan izin.
 *   Uji unit menjalankan PHP, tidak pernah menyentuh berkas ini.
 *   Di Windows, `ls -la` MENIPU: berkasnya terbaca `-rwxr-xr-x` di disk
 *   sementara git menyimpannya 100644. Yang berlaku di container adalah yang
 *   di git.
 *
 * Karena itu yang dibaca di sini `git ls-files -s`, bukan `is_executable()`.
 */
class BerkasSkripTest extends TestCase
{
    #[Test]
    public function seluruh_skrip_shell_executable_di_git(): void
    {
        $berkas = $this->modeSkripDiGit();

        $this->assertNotEmpty(
            $berkas,
            'Tidak satu pun berkas .sh terbaca dari indeks git — jalur atau pola kuerinya salah, '
                .'dan uji ini berhenti menjaga apa pun.'
        );

        foreach ($berkas as $jalur => $mode) {
            $this->assertSame(
                '100755',
                $mode,
                "{$jalur} bermode {$mode} di git, seharusnya 100755. Perbaiki dengan:\n"
                    ."    git update-index --chmod=+x {$jalur}\n"
                    .'Mode di disk TIDAK menentukan — di Windows ia bisa terbaca executable '
                    .'sementara git menyimpannya 644, dan yang berlaku di container adalah git.'
            );
        }
    }

    /**
     * `start.sh` adalah satu-satunya skrip yang di-exec langsung oleh proses
     * lain (tini), jadi ia yang paling mahal kalau modenya salah — container
     * tidak menyala sama sekali. Diuji terpisah supaya pesan gagalnya menyebut
     * akibatnya, bukan sekadar angka mode.
     */
    #[Test]
    public function start_sh_bisa_dijalankan_langsung_oleh_tini(): void
    {
        $mode = $this->modeSkripDiGit()['start.sh'] ?? null;

        $this->assertSame(
            '100755',
            $mode,
            'start.sh tidak executable di git. tini meng-exec-nya langsung '
                .'(blok re-exec di baris ~79), jadi mode yang salah membuat container masuk '
                .'restart loop dengan "exec ./start.sh failed: Permission denied" — '
                .'sebelum satu baris pun sempat berjalan.'
        );
    }

    /**
     * Baris re-exec harus melewati `bash`, bukan meng-exec berkasnya langsung.
     *
     * Penjagaan kedua untuk kegagalan yang sama, dan sengaja tidak bergantung
     * pada yang pertama: mode git bisa hilang lagi lewat operasi yang tidak
     * satu pun dari kami perhatikan (patch yang dibuat di Windows, `git apply`
     * tanpa mode, berkas yang ditulis ulang alat lain). Selama barisnya memakai
     * `bash`, mode yang salah cuma jadi kerapian — bukan produksi yang mati.
     */
    #[Test]
    public function re_exec_tini_tidak_bergantung_pada_mode_berkas(): void
    {
        $isi = file_get_contents(base_path('start.sh'));

        $this->assertStringContainsString(
            'exec tini -s -- bash "$0"',
            $isi,
            'Blok re-exec tini tidak lagi melewati `bash`. Meng-exec start.sh langsung '
                .'menuntut bit executable, dan itu sudah mematikan produksi sekali.'
        );
    }

    /**
     * Dibaca dari INDEKS GIT, bukan dari sistem berkas.
     *
     * @return array<string, string> jalur => mode
     */
    private function modeSkripDiGit(): array
    {
        $keluaran = [];
        $status = 0;

        exec('git -C '.escapeshellarg(base_path()).' ls-files -s -- "*.sh" 2>&1', $keluaran, $status);

        if ($status !== 0) {
            $this->markTestSkipped('git tidak tersedia atau ini bukan repositori git.');
        }

        $hasil = [];

        foreach ($keluaran as $baris) {
            // Format: <mode> <sha> <stage>\t<jalur>
            if (preg_match('/^(\d{6}) \S+ \d+\t(.+)$/', $baris, $cocok)) {
                $hasil[$cocok[2]] = $cocok[1];
            }
        }

        return $hasil;
    }
}
