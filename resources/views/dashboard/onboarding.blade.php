@extends('layouts.app')
@section('title', 'Buat Workspace')

@section('content')
    <div class="mx-auto max-w-lg">
        <x-card title="Selamat datang" subtitle="Satu workspace menampung nomor WhatsApp, API key, dan riwayat pesan Anda.">
            <form method="POST" action="{{ route('onboarding.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium" for="name">Nama workspace</label>
                    <input id="name" name="name" required maxlength="80" value="{{ old('name', auth()->user()->name) }}"
                           class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <p class="mt-1 text-xs text-muted-foreground">Biasanya nama perusahaan atau tim Anda.</p>
                </div>
                <div class="rounded-lg bg-muted px-4 py-3 text-xs text-muted-foreground">
                    Workspace adalah wadah tempat nomor WhatsApp, riwayat pesan, dan API key Anda disimpan.
                    Anda bisa membuat lebih dari satu — misalnya satu untuk tiap cabang — dan mengundang rekan tim
                    ke dalamnya. Semua yang Anda lakukan setelah ini berlangsung di dalam workspace yang sedang dipilih.
                </div>
                <button class="w-full rounded-lg bg-primary px-4 py-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                    Buat workspace
                </button>
            </form>
        </x-card>
    </div>
@endsection
