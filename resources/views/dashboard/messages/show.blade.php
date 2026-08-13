@extends('layouts.app')
@section('title', 'Detail Pesan')

@section('content')
    <x-card>
        <dl class="grid gap-4 sm:grid-cols-2">
            @php
                $rows = [
                    'ID' => $message->id,
                    'Sesi' => $message->session?->name ?? '—',
                    'Arah' => $message->direction === 'outbound' ? 'Keluar' : 'Masuk',
                    'Tujuan' => $message->to_number,
                    'Pengirim' => $message->from_number ?? '—',
                    'Tipe' => $message->type,
                    'Status' => $message->status,
                    'Percobaan' => $message->attempts,
                    'ID WhatsApp' => $message->wa_message_id ?? '—',
                    'Batch' => $message->batch_id ?? '—',
                    'Dibuat' => $message->created_at->format('d M Y H:i:s'),
                    'Terkirim' => $message->sent_at?->format('d M Y H:i:s') ?? '—',
                    'Sampai' => $message->delivered_at?->format('d M Y H:i:s') ?? '—',
                    'Dibaca' => $message->read_at?->format('d M Y H:i:s') ?? '—',
                ];
            @endphp
            @foreach ($rows as $label => $value)
                <div>
                    <dt class="text-xs uppercase text-muted-foreground">{{ $label }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-sm">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </x-card>

    <x-card title="Isi pesan" class="mt-4">
        <pre class="whitespace-pre-wrap break-words text-sm">{{ $message->body }}</pre>
    </x-card>

    @if ($message->error)
        <x-card title="Kesalahan" class="mt-4">
            <p class="text-sm text-destructive">{{ $message->error }}</p>
        </x-card>
    @endif

    @if ($message->provider_response)
        <x-card title="Balasan provider" class="mt-4">
            <pre class="overflow-x-auto rounded-lg bg-muted p-3 text-xs">{{ json_encode($message->provider_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
        </x-card>
    @endif

    <a href="{{ route('messages.index') }}" class="mt-4 inline-block text-sm text-primary hover:underline">&larr; Kembali ke riwayat</a>
@endsection
