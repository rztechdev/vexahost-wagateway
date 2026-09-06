<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tur pengenalan yang sudah pernah dilihat seseorang.
 *
 * Disimpan di tabel, bukan di cookie atau localStorage: tur yang muncul lagi
 * karena orang berganti peramban atau membersihkan cache terbaca sebagai
 * kerusakan, dan pengguna lama tidak punya cara menutupnya selamanya.
 *
 * `guide_version` ada supaya tur yang isinya berubah besar bisa ditampilkan
 * ulang kepada yang sudah pernah melihatnya, tanpa menghapus barisnya —
 * menghapus berarti kehilangan jawaban atas "siapa yang sudah pernah dituntun".
 *
 * `status` membedakan `completed` dari `skipped`. Keduanya sama-sama berarti
 * "jangan tampilkan lagi", tapi banyaknya orang yang melewati tur adalah
 * satu-satunya tanda bahwa turnya sendiri yang bermasalah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_guide_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guide_key', 60);
            $table->unsignedSmallInteger('guide_version')->default(1);
            $table->string('status', 20)->default('completed');
            $table->timestamps();

            $table->unique(['user_id', 'guide_key']);
        });

        /*
         | Akun yang SUDAH ADA ditandai sudah pernah melihatnya.
         |
         | Turnya untuk pendaftar baru — orang yang belum tahu produk ini apa.
         | Menampilkannya kepada pelanggan yang sudah memakainya berbulan-bulan
         | bukan cuma mengganggu: ia menuntun mereka melewati hal yang sudah
         | mereka kerjakan tiap hari, dan itu terbaca seperti aplikasinya lupa
         | siapa mereka.
         |
         | Ditulis lewat query builder, bukan model: migrasi yang bergantung
         | pada model akan ikut rusak saat modelnya berubah bertahun-tahun
         | kemudian, dan migrasi lama harus tetap bisa dijalankan dari nol.
        */
        $sekarang = now();

        DB::table('users')->orderBy('id')->chunk(500, function ($pengguna) use ($sekarang) {
            DB::table('user_guide_progress')->insert(
                $pengguna->map(fn ($u) => [
                    'user_id' => $u->id,
                    'guide_key' => 'dashboard.mulai',
                    'guide_version' => 1,
                    'status' => 'completed',
                    'created_at' => $sekarang,
                    'updated_at' => $sekarang,
                ])->all()
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_guide_progress');
    }
};
