@extends('layouts.auth')

@section('title', 'Masuk - Flustra')

@section('topbar_action')
    <a href="{{ route('register') }}" class="auth-topbar-link">Daftar Akun</a>
@endsection

@section('content')
<div class="auth-card">
    <div class="auth-card-header">
        <h1>Selamat Datang Kembali</h1>
        <p>Masuk menggunakan akun terdaftar Anda</p>
    </div>

    @if (session('status'))
        <div class="auth-alert">{{ session('status') }}</div>
    @endif

    @if (session('success'))
        <div class="auth-alert">{{ session('success') }}</div>
    @endif

    @if ($errors->any() && !$errors->has('email') && !$errors->has('password'))
        <div class="auth-alert auth-alert--error">{{ $errors->first() }}</div>
    @endif

    <form class="auth-form" action="{{ route('login') }}" method="POST">
        @csrf

        <!-- Field Email -->
        <div class="auth-field @error('email') is-error @enderror">
            <label for="email">
                <i class="bi bi-envelope"></i> Alamat Email
            </label>
            <input type="email" name="email" id="email" value="{{ old('email') }}" placeholder="nama@email.com" required autocomplete="email" autofocus>
            @error('email')
                <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
            @enderror
        </div>

        <!-- Field Password -->
        <div class="auth-field @error('password') is-error @enderror">
            <label for="password">
                <i class="bi bi-lock"></i> Kata Sandi
            </label>
            <div style="position: relative;">
                <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="current-password" style="padding-right: 40px;">
                <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Tampilkan/Sembunyikan Kata Sandi">
                    <i class="bi bi-eye"></i>
                </button>
            </div>
            @error('password')
                <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
            @enderror
        </div>

        <div class="auth-forgot">
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Lupa kata sandi?</a>
            @endif
        </div>

        <!-- Checkbox Persetujuan Syarat & Ketentuan -->
        <div class="auth-checkbox @error('terms') is-error @enderror">
            <input type="checkbox" name="terms" id="terms" value="1" required @checked(old('terms'))>
            <label for="terms">
                Saya menyetujui <a href="https://flustra.jagoankode.my.id/syarat-dan-ketentuan">Syarat & Ketentuan</a> serta <a href="https://flustra.jagoankode.my.id/kebijakan-privasi">Kebijakan Privasi</a> Flustra.
            </label>
            @error('terms')
                <span class="auth-field-error d-block w-100"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
            @enderror
        </div>

        <button type="submit" class="auth-submit">
            Masuk Sekarang
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>



    <p class="auth-footer-text">
        Belum punya akun? <a href="{{ route('register') }}">Daftar di sini</a>
    </p>
</div>
@endsection
