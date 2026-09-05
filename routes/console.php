<?php

use App\Jobs\BillingCycleJob;
use App\Jobs\PruneOldRecordsJob;
use App\Jobs\SyncSessionStatusJob;
use Illuminate\Support\Facades\Schedule;

// Menutup celah callback: kalau container engine mati mendadak, tidak ada
// event `disconnected` yang terkirim, sehingga dashboard akan terus menampilkan
// sesi sebagai terhubung padahal sudah tidak.
Schedule::job(new SyncSessionStatusJob)->everyMinute()->withoutOverlapping();

Schedule::job(new PruneOldRecordsJob)->dailyAt('03:15');

// Seluruh siklus penagihan: menutup tagihan lewat tempo, menerbitkan tagihan
// perpanjangan, mengirim pengingat, menghentikan pengiriman yang lewat jatuh
// tempo, dan melepas sesi yang masa tenggangnya habis.
//
// Pagi hari dengan sengaja. Pelanggan yang membaca pengingat pukul sembilan
// masih punya sehari penuh untuk membayar; pengingat tengah malam terbaca
// keesokan paginya bersamaan dengan layanan yang sudah berhenti.
Schedule::job(new BillingCycleJob)->dailyAt('08:00')->withoutOverlapping();
