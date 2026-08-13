@props(['status'])

@php
    // Warna dipilih supaya "butuh tindakan" (qr, failed) langsung menonjol
    // dibanding keadaan normal.
    $styles = [
        'connected' => ['bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300', 'Terhubung'],
        'qr' => ['bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300', 'Menunggu scan QR'],
        'connecting' => ['bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300', 'Menghubungkan'],
        'disconnected' => ['bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300', 'Terputus'],
        'failed' => ['bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300', 'Gagal'],
        'pending' => ['bg-stone-100 text-stone-700 dark:bg-stone-800 dark:text-stone-300', 'Belum dijalankan'],
    ];
    [$class, $label] = $styles[$status] ?? $styles['pending'];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $class }}">{{ $label }}</span>
