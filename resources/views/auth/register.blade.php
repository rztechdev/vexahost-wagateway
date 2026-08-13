@extends('layouts.auth')

@section('title', 'Daftar Akun - Flustra')

@section('topbar_action')
    <a href="{{ route('login') }}" class="auth-topbar-link">Masuk</a>
@endsection

@section('content')
<div class="auth-card auth-card--wide">
    <div class="auth-card-header">
        <h1>Daftar Akun Baru</h1>
        <p>Isi formulir pendaftaran di bawah ini untuk membuat akun baru</p>
    </div>

    @if(session('info'))
        <div class="auth-alert animate-fade-in-up">
            <i class="bi bi-info-circle" style="font-size: 1.1rem; flex-shrink: 0;"></i>
            <div>{{ session('info') }}</div>
        </div>
    @endif

    <form class="auth-form" action="{{ route('register') }}" method="POST">
        @csrf

        <!-- Baris Input Nama Lengkap & Username berdampingan -->
        <div class="auth-field-row">
            <div class="auth-field @error('name') is-error @enderror">
                <label for="name">
                    <i class="bi bi-person"></i> Nama Lengkap
                </label>
                <input type="text" name="name" id="name" value="{{ old('name', session('google_name') ?? request('name')) }}" placeholder="Nama Anda" required autocomplete="name" autofocus>
                @error('name')
                    <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-field @error('workspace') is-error @enderror">
                <label for="workspace">
                    <i class="bi bi-briefcase"></i> Nama Workspace
                </label>
                <input type="text" name="workspace" id="workspace" value="{{ old('workspace') }}" placeholder="Workspace Anda" required autocomplete="organization">
                @error('workspace')
                    <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
                @enderror
            </div>
        </div>

        <!-- Baris Input Email & Telepon berdampingan -->
        <div class="auth-field-row">
            <div class="auth-field @error('email') is-error @enderror">
                <label for="email">
                    <i class="bi bi-envelope"></i> Alamat Email
                </label>
                <input type="email" name="email" id="email" value="{{ old('email', session('google_email') ?? request('email')) }}" placeholder="nama@email.com" required autocomplete="email">
                @error('email')
                    <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
                @enderror
            </div>

        </div>

        <!-- Baris Input Sandi & Konfirmasi Sandi berdampingan -->
        <div class="auth-field-row">
            <div class="auth-field @error('password') is-error @enderror">
                <label for="password">
                    <i class="bi bi-lock"></i> Kata Sandi
                </label>
                <div style="position: relative;">
                    <input type="password" name="password" id="password" placeholder="••••••••" required autocomplete="new-password" style="padding-right: 70px;">
                    <span id="password-length-badge" style="position: absolute; right: 36px; top: 50%; transform: translateY(-50%); padding: 2px 6px; font-size: 10px; font-weight: bold; border-radius: 4px; background-color: #cbd5e1; color: white; transition: all 0.3s;">0</span>
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password', this)" title="Tampilkan/Sembunyikan Kata Sandi">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>

                <!-- 5-Segment Progress Bar -->
                <div style="display: flex; gap: 4px; margin-top: 6px;">
                    <div style="height: 4px; flex: 1; background-color: #e2e8f0; border-radius: 4px; transition: background-color 0.3s;" id="pass-bar-1"></div>
                    <div style="height: 4px; flex: 1; background-color: #e2e8f0; border-radius: 4px; transition: background-color 0.3s;" id="pass-bar-2"></div>
                    <div style="height: 4px; flex: 1; background-color: #e2e8f0; border-radius: 4px; transition: background-color 0.3s;" id="pass-bar-3"></div>
                    <div style="height: 4px; flex: 1; background-color: #e2e8f0; border-radius: 4px; transition: background-color 0.3s;" id="pass-bar-4"></div>
                    <div style="height: 4px; flex: 1; background-color: #e2e8f0; border-radius: 4px; transition: background-color 0.3s;" id="pass-bar-5"></div>
                </div>

                <!-- Strength Label -->
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 11px; color: #64748b; margin-top: 4px;">
                    <span>Kekuatan:</span>
                    <span id="password-strength-text" style="font-weight: bold; color: #94a3b8;">-</span>
                </div>

                @error('password')
                    <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
                @enderror
            </div>

            <div class="auth-field @error('password_confirmation') is-error @enderror">
                <label for="password_confirmation">
                    <i class="bi bi-lock"></i> Konfirmasi Sandi
                </label>
                <div style="position: relative;">
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="••••••••" required autocomplete="new-password" style="padding-right: 40px;">
                    <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password_confirmation', this)" title="Tampilkan/Sembunyikan Kata Sandi">
                        <i class="bi bi-eye"></i>
                    </button>
                </div>
                @error('password_confirmation')
                    <span class="auth-field-error"><i class="bi bi-exclamation-circle me-1"></i>{{ $message }}</span>
                @enderror
            </div>
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
            Daftar Akun Baru
            <i class="bi bi-arrow-right"></i>
        </button>
    </form>

    <p class="auth-footer-text">
        Sudah punya akun? <a href="{{ route('login') }}">Masuk sekarang</a>
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        const lengthBadge = document.getElementById('password-length-badge');
        const strengthText = document.getElementById('password-strength-text');
        const bars = [
            document.getElementById('pass-bar-1'),
            document.getElementById('pass-bar-2'),
            document.getElementById('pass-bar-3'),
            document.getElementById('pass-bar-4'),
            document.getElementById('pass-bar-5')
        ];

        const STRENGTH_LEVELS = [
            { score: 0, label: '-', color: '#e2e8f0' },
            { score: 1, label: 'Sangat Lemah', color: '#ef4444' },
            { score: 2, label: 'Lemah', color: '#f97316' },
            { score: 3, label: 'Sedang', color: '#eab308' },
            { score: 4, label: 'Kuat', color: '#84cc16' },
            { score: 5, label: 'Sangat Kuat', color: '#22c55e' }
        ];

        passwordInput.addEventListener('input', function() {
            const val = this.value;
            const len = val.length;

            const hasLower = /[a-z]/.test(val);
            const hasUpper = /[A-Z]/.test(val);
            const hasNumber = /[0-9]/.test(val);
            const hasSymbol = /[^A-Za-z0-9]/.test(val);

            const varietyCount = (hasLower ? 1 : 0) + (hasUpper ? 1 : 0) + (hasNumber ? 1 : 0) + (hasSymbol ? 1 : 0);
            const isRepeatedChar = /^(\x20|.)\1+$/.test(val);
            const isFullyValid = (len >= 8 && hasLower && hasUpper && hasNumber && hasSymbol);

            if (lengthBadge) {
                if (isFullyValid) {
                    lengthBadge.textContent = '8+';
                    lengthBadge.style.backgroundColor = '#22c55e';
                    lengthBadge.style.color = '#ffffff';
                } else if (len >= 8) {
                    lengthBadge.textContent = len;
                    lengthBadge.style.backgroundColor = '#f97316';
                    lengthBadge.style.color = '#ffffff';
                } else {
                    lengthBadge.textContent = len;
                    lengthBadge.style.backgroundColor = '#cbd5e1';
                    lengthBadge.style.color = '#ffffff';
                }
            }

            let score = 0;
            if (len > 0) {
                if (isRepeatedChar || varietyCount === 1) {
                    score = (len >= 8) ? 2 : 1;
                } else if (varietyCount === 2) {
                    score = (len >= 8) ? 3 : 2;
                } else if (varietyCount === 3) {
                    score = (len >= 8) ? 4 : 3;
                } else if (varietyCount === 4) {
                    score = (len >= 8) ? 5 : 4;
                }
            }

            const current = STRENGTH_LEVELS[score];

            bars.forEach((bar, index) => {
                if (bar) {
                    bar.style.backgroundColor = (index < score) ? current.color : '#e2e8f0';
                }
            });

            if (strengthText) {
                strengthText.textContent = current.label;
                strengthText.style.color = (score === 0) ? '#94a3b8' : current.color;
            }
        });
    }
});
</script>
@endsection
