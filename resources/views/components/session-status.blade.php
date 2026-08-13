@props(['status'])

@php
    // Warna dipilih supaya "butuh tindakan" (qr, failed) langsung menonjol
    // dibanding keadaan normal.
    $styles = [
        'connected' => ['bg-primary/10 text-primary border border-primary/20', 'Terhubung'],
        'qr' => ['bg-amber-500/10 text-amber-600 border border-amber-500/20', 'Menunggu scan QR'],
        'connecting' => ['bg-sky-500/10 text-sky-600 border border-sky-500/20', 'Menghubungkan'],
        'disconnected' => ['bg-muted text-muted-foreground border border-border', 'Terputus'],
        'failed' => ['bg-destructive/10 text-destructive border border-destructive/20', 'Gagal'],
        'pending' => ['bg-muted text-muted-foreground border border-border', 'Belum dijalankan'],
    ];
    [$class, $label] = $styles[$status] ?? $styles['pending'];
@endphp

<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $class }}">{{ $label }}</span>
