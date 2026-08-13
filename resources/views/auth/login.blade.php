@extends('layouts.auth')

@section('title', 'Masuk')
@section('heading', 'Masuk')
@section('subheading', 'Kelola nomor WhatsApp, API key, dan riwayat pesan Anda.')

@section('form')
    <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
        @csrf

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Email</label>
            <input id="email" name="email" type="email" required autofocus autocomplete="email"
                   value="{{ old('email') }}"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Kata sandi</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="w-full rounded-lg border-stone-300 text-sm dark:border-stone-700 dark:bg-stone-800 dark:text-stone-100">
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-400">
            <input type="checkbox" name="remember" value="1" class="rounded border-stone-300">
            Ingat saya
        </label>

        <button class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700">
            Masuk
        </button>
    </form>
@endsection

@section('footer')
    Belum punya akun?
    <a href="{{ route('register') }}" class="font-medium text-emerald-600 hover:underline">Daftar</a>
@endsection
