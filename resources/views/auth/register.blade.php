@extends('layouts.auth')

@section('title', 'Daftar')
@section('heading', 'Buat akun')
@section('subheading', 'Workspace pertama Anda dibuat sekalian saat mendaftar.')

@section('form')
    <form method="POST" action="{{ route('register') }}" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Nama</label>
            <input id="name" name="name" required autofocus maxlength="80" autocomplete="name"
                   value="{{ old('name') }}"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Email</label>
            <input id="email" name="email" type="email" required maxlength="180" autocomplete="email"
                   value="{{ old('email') }}"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
        </div>

        <div>
            <label for="workspace" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Nama workspace</label>
            <input id="workspace" name="workspace" required maxlength="80"
                   value="{{ old('workspace') }}" placeholder="mis. Toko Makmur"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
            <p class="mt-1 text-xs text-stone-500">Wadah untuk nomor WhatsApp, API key, dan riwayat pesan Anda.</p>
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="new-password"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
            <p class="mt-1 text-xs text-stone-500">Minimal 8 karakter.</p>
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Ulangi kata sandi</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
        </div>

        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Daftar
        </button>
    </form>
@endsection

@section('footer')
    Sudah punya akun?
    <a href="{{ route('login') }}" class="font-medium text-emerald-600 hover:underline">Masuk</a>
@endsection
