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
                           class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800">
                    <p class="mt-1 text-xs text-stone-500">Biasanya nama perusahaan atau tim Anda.</p>
                </div>
                <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
                    Buat workspace
                </button>
            </form>
        </x-card>
    </div>
@endsection
