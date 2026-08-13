@extends('layouts.app')
@section('title', 'Template Pesan')

@section('content')
    <x-card title="Template baru" subtitle="Tulis placeholder dengan {{ '{{ nama }}' }} — nilainya dikirim lewat API saat pesan dibuat.">
        <form method="POST" action="{{ route('templates.store') }}" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium" for="name">Nama</label>
                    <input id="name" name="name" required maxlength="60" value="{{ old('name') }}"
                           class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium" for="slug">Slug</label>
                    <input id="slug" name="slug" required maxlength="60" value="{{ old('slug') }}"
                           placeholder="pengingat-invoice" pattern="[a-z0-9\-]+"
                           class="w-full rounded-lg border-stone-300 font-mono text-sm dark:border-stone-700 dark:bg-stone-800">
                    <p class="mt-1 text-xs text-stone-500">Ini yang dipakai di API: <code>"template": "pengingat-invoice"</code></p>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="body">Isi</label>
                <textarea id="body" name="body" rows="5" required maxlength="4096"
                          placeholder="Halo {{ '{{ nama }}' }}, faktur {{ '{{ nomor }}' }} jatuh tempo {{ '{{ tanggal }}' }}."
                          class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">{{ old('body') }}</textarea>
            </div>
            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">Simpan</button>
        </form>
    </x-card>

    <div class="mt-6 space-y-4">
        @forelse ($templates as $template)
            <x-card>
                <form method="POST" action="{{ route('templates.update', $template->id) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="flex flex-wrap items-center gap-3">
                        <input name="name" value="{{ $template->name }}" required maxlength="60"
                               class="flex-1 rounded-lg border-stone-300 text-sm font-medium dark:border-stone-700 dark:bg-stone-800">
                        <code class="rounded bg-stone-100 px-2 py-1 text-xs dark:bg-stone-800">{{ $template->slug }}</code>
                        <label class="flex items-center gap-1.5 text-sm">
                            <input type="checkbox" name="is_active" value="1" @checked($template->is_active)> Aktif
                        </label>
                    </div>
                    <textarea name="body" rows="4" required maxlength="4096"
                              class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">{{ $template->body }}</textarea>
                    @if ($template->variables)
                        <p class="text-xs text-stone-500">Variabel: {{ implode(', ', $template->variables) }}</p>
                    @endif
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm text-white">Simpan</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('templates.destroy', $template->id) }}" class="mt-2"
                      onsubmit="return confirm('Hapus template {{ $template->name }}?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-600 hover:underline">Hapus template</button>
                </form>
            </x-card>
        @empty
            <x-card><p class="text-sm text-stone-500">Belum ada template.</p></x-card>
        @endforelse
    </div>
@endsection
