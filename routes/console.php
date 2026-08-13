<?php

use App\Jobs\PruneOldRecordsJob;
use App\Jobs\SyncSessionStatusJob;
use Illuminate\Support\Facades\Schedule;

// Menutup celah callback: kalau container engine mati mendadak, tidak ada
// event `disconnected` yang terkirim, sehingga dashboard akan terus menampilkan
// sesi sebagai terhubung padahal sudah tidak.
Schedule::job(new SyncSessionStatusJob)->everyMinute()->withoutOverlapping();

Schedule::job(new PruneOldRecordsJob)->dailyAt('03:15');
