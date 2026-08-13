@extends('layouts.app')
@section('title', 'Webhooks')

@section('content')
    <x-card title="Tambah webhook" subtitle="Gateway akan mengirim POST JSON ke URL ini setiap kejadian yang Anda pilih.">
        <form method="POST" action="{{ route('webhooks.store') }}" class="space-y-4">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium" for="url">URL tujuan</label>
                <input id="url" name="url" type="url" required placeholder="https://app.contoh.id/webhook/whatsapp"
                       class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
            </div>
            <div>
                <span class="mb-1 block text-sm font-medium">Event</span>
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($availableEvents as $value => $label)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="events[]" value="{{ $value }}" checked>
                            {{ $label }} <code class="text-xs text-stone-400">{{ $value }}</code>
                        </label>
                    @endforeach
                </div>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Simpan</button>
        </form>
    </x-card>

    <x-card title="Webhook terdaftar" class="mt-6">
        @if ($webhooks->isEmpty())
            <p class="text-sm text-stone-500">Belum ada webhook.</p>
        @else
            <div class="space-y-4">
                @foreach ($webhooks as $webhook)
                    <div class="rounded-lg border border-stone-200 p-4 dark:border-stone-800">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate font-mono text-sm">{{ $webhook->url }}</p>
                                <p class="mt-1 text-xs text-stone-500">
                                    {{ $webhook->events ? implode(', ', $webhook->events) : 'semua event' }}
                                    @if ($webhook->consecutive_failures > 0)
                                        &middot; <span class="text-red-600">{{ $webhook->consecutive_failures }} kegagalan berturut-turut</span>
                                    @endif
                                </p>
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-xs {{ $webhook->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-stone-100 text-stone-600 dark:bg-stone-800' }}">
                                {{ $webhook->is_active ? 'aktif' : 'nonaktif' }}
                            </span>
                        </div>

                        <div class="mt-3">
                            <label class="text-xs uppercase text-stone-500">Signing secret</label>
                            <input readonly value="{{ $webhook->secret }}"
                                   class="mt-1 w-full rounded-lg border-stone-300 bg-stone-50 font-mono text-xs dark:border-stone-700 dark:bg-stone-800">
                            <p class="mt-1 text-xs text-stone-500">
                                Bandingkan header <code>X-Flustra-Signature</code> dengan
                                <code>hash_hmac('sha256', $rawBody, $secret)</code> sebelum memproses payload.
                            </p>
                        </div>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('webhooks.test', $webhook->id) }}">
                                @csrf
                                <button class="rounded-lg border border-stone-300 px-3 py-1.5 text-xs dark:border-stone-700">Kirim uji coba</button>
                            </form>
                            <form method="POST" action="{{ route('webhooks.toggle', $webhook->id) }}">
                                @csrf
                                <button class="rounded-lg border border-stone-300 px-3 py-1.5 text-xs dark:border-stone-700">
                                    {{ $webhook->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('webhooks.destroy', $webhook->id) }}"
                                  onsubmit="return confirm('Hapus webhook ini?')">
                                @csrf @method('DELETE')
                                <button class="rounded-lg border border-red-300 px-3 py-1.5 text-xs text-red-700 dark:border-red-900 dark:text-red-400">Hapus</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <x-card title="Pengiriman terakhir" class="mt-6">
        @if ($deliveries->isEmpty())
            <p class="text-sm text-stone-500">Belum ada pengiriman.</p>
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="w-full min-w-[600px] text-sm">
                    <thead class="text-left text-xs uppercase text-stone-500">
                        <tr class="border-b border-stone-200 dark:border-stone-800">
                            <th class="px-5 py-2">Waktu</th>
                            <th class="px-5 py-2">Event</th>
                            <th class="px-5 py-2">Kode</th>
                            <th class="px-5 py-2">Balasan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deliveries as $delivery)
                            <tr class="border-b border-stone-100 last:border-0 dark:border-stone-800">
                                <td class="whitespace-nowrap px-5 py-2 text-stone-500">{{ $delivery->created_at->format('d M H:i:s') }}</td>
                                <td class="px-5 py-2 font-mono text-xs">{{ $delivery->event }}</td>
                                <td class="px-5 py-2">
                                    <span class="{{ $delivery->delivered_at ? 'text-emerald-600' : 'text-red-600' }}">{{ $delivery->response_code ?? 'gagal' }}</span>
                                </td>
                                <td class="max-w-xs truncate px-5 py-2 text-xs text-stone-500">{{ $delivery->response_body }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection
