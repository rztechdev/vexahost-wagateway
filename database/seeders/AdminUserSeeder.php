<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Akun super admin pertama.
 *
 * Ada karena panel admin hanya bisa dibuka oleh super admin, dan super admin
 * hanya bisa diangkat oleh super admin lain — tanpa akun pertama yang lahir di
 * luar antarmuka, panel itu terkunci dari dalam sejak menit pertama.
 *
 * Kredensialnya dibaca dari env dengan nilai bawaan yang sengaja mudah dipakai
 * saat pengembangan. **Nilai bawaan itu tidak boleh dipakai di produksi.**
 * Isi `ADMIN_PASSWORD` di env produksi sebelum menjalankan seeder, atau ganti
 * kata sandinya lewat panel begitu akunnya terbuat: kata sandi yang tertulis di
 * dalam repo adalah kata sandi yang sudah bocor.
 *
 * Aman dijalankan berulang. Akun yang sudah ada tidak ditimpa kata sandinya —
 * menjalankan ulang seeder saat deploy tidak boleh diam-diam mengembalikan kata
 * sandi yang sudah diganti orang.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'flustrafinances@gmail.com');
        $password = env('ADMIN_PASSWORD', '12345678');
        $name = env('ADMIN_NAME', 'Flustra Finance');

        $user = User::where('email', $email)->first();

        if ($user) {
            // Hak super admin tetap dipulihkan: kalau akun ini pernah kehilangan
            // haknya, seeder inilah satu-satunya jalan mengembalikannya tanpa
            // menyentuh database produksi langsung.
            if (! $user->is_super_admin) {
                $user->forceFill(['is_super_admin' => true])->save();

                $this->command?->info("Hak super admin dipulihkan untuk {$email}.");
            }

            return;
        }

        User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_super_admin' => true,
        ]);

        $this->command?->info("Super admin dibuat: {$email}");

        if ($password === '12345678') {
            $this->command?->warn(
                'Kata sandi bawaan masih dipakai. Ganti segera lewat halaman pengaturan '
                .'atau isi ADMIN_PASSWORD di env sebelum menyalakan produksi.'
            );
        }
    }
}
