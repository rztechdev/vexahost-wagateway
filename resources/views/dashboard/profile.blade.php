@extends('layouts.app')
@section('title', 'Profil Akun')

@section('content')
    <div class="space-y-8" x-data="{
        sembunyikanSensitif: true,
        toggleSensitif() { this.sembunyikanSensitif = !this.sembunyikanSensitif; }
    }">
        {{-- ===================== Header & Info Akun ===================== --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground sm:text-3xl">Profil Akun</h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Kelola identitas pribadi, foto profil, dan keamanan akun Anda.
                </p>
            </div>

            <button type="button" @click="toggleSensitif()"
                    class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-3.5 py-2 text-xs sm:text-sm font-medium transition hover:bg-muted self-start sm:self-auto">
                <template x-if="sembunyikanSensitif">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Tampilkan Data Sensitif</span>
                    </span>
                </template>
                <template x-if="!sembunyikanSensitif">
                    <span class="flex items-center gap-1.5 text-primary font-semibold">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18"/></svg>
                        <span>Sembunyikan Data Sensitif</span>
                    </span>
                </template>
            </button>
        </div>

        {{-- ===================== Kartu Ringkasan Data Diri ===================== --}}
        <div class="rounded-2xl border border-border bg-card p-5 sm:p-6 shadow-xs">
            <h3 class="text-sm font-semibold uppercase tracking-wider text-muted-foreground mb-4">
                Ringkasan Identitas Akun
            </h3>

            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-5">
                {{-- Foto Avatar --}}
                <div class="relative shrink-0">
                    <div class="h-20 w-20 overflow-hidden rounded-2xl border-2 border-primary/20 bg-primary/10 grid place-items-center shadow-xs">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-full w-full object-cover">
                        @else
                            <span class="text-2xl font-bold text-primary">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        @endif
                    </div>
                </div>

                {{-- Detail Diri --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 flex-1 w-full text-xs sm:text-sm">
                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Nama Lengkap</span>
                        <span class="font-semibold text-foreground">{{ $user->name }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Email</span>
                        <span class="text-foreground">{{ $user->email }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Nomor Telepon / WA</span>
                        @if ($user->phone)
                            <span x-show="sembunyikanSensitif" class="font-mono text-muted-foreground">
                                {{ substr($user->phone, 0, 4) . '••••' . substr($user->phone, -3) }}
                            </span>
                            <span x-show="!sembunyikanSensitif" x-cloak class="font-mono text-foreground font-semibold">
                                {{ $user->phone }}
                            </span>
                        @else
                            <span class="text-muted-foreground italic">Belum diisi</span>
                        @endif
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Perusahaan / Usaha</span>
                        <span class="text-foreground">{{ $user->company ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Kota / Domisili</span>
                        <span class="text-foreground">{{ $user->city ?: '—' }}</span>
                    </div>

                    <div>
                        <span class="block text-muted-foreground text-[11px] uppercase tracking-wider font-semibold">Status Mitra</span>
                        @if ($user->referralCode)
                            @if ($user->referralCode->isApproved())
                                <span class="inline-flex items-center gap-1 font-semibold text-primary">
                                    <span class="h-2 w-2 rounded-full bg-primary"></span>
                                    Mitra Aktif (Kode: <span class="font-mono">{{ $user->referralCode->code }}</span>)
                                </span>
                            @elseif ($user->referralCode->isPending())
                                <span class="inline-flex items-center gap-1 text-amber-600 dark:text-amber-400 font-medium">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    Menunggu Verifikasi Admin
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 text-destructive font-medium">
                                    Belum Disetujui
                                </span>
                            @endif
                        @else
                            <a href="{{ route('mitra.index') }}" class="text-primary hover:underline">Daftar Mitra</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ===================== Edit Data Pribadi & Foto ===================== --}}
        <x-section judul="Perbarui Informasi Pribadi & Foto Profil"
                   sub="Lengkapi identitas diri Anda sebagai pelengkap akun.">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-5">
                @csrf
                @method('PUT')

                {{-- Upload Foto Profil --}}
                <div>
                    <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                        Foto Profil (Avatar)
                    </label>
                    <div class="flex items-center gap-4">
                        <div class="h-14 w-14 shrink-0 overflow-hidden rounded-xl border border-border bg-muted/40 grid place-items-center">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="" class="h-full w-full object-cover">
                            @else
                                <span class="font-bold text-muted-foreground">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            @endif
                        </div>
                        <input type="file" name="avatar" id="avatar" accept="image/jpeg,image/png,image/webp"
                               class="text-xs text-muted-foreground file:mr-3 file:rounded-lg file:border-0 file:bg-primary/10 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-primary hover:file:bg-primary/20">
                    </div>
                    @error('avatar')
                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="name" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Nama Lengkap <span class="text-destructive">*</span>
                        </label>
                        <input type="text" name="name" id="name"
                               value="{{ old('name', $user->name) }}"
                               required maxlength="80"
                               class="w-full rounded-xl border @error('name') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('name')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-muted-foreground">
                            Alamat Email (Tetap)
                        </label>
                        <input type="email" id="email" value="{{ $user->email }}" disabled
                               class="w-full rounded-xl border border-input bg-muted/50 px-4 py-2.5 text-sm text-muted-foreground cursor-not-allowed">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Nomor WhatsApp / Telepon
                        </label>
                        <input type="text" name="phone" id="phone"
                               value="{{ old('phone', $user->phone) }}"
                               placeholder="Contoh: 081234567890" maxlength="30"
                               class="w-full rounded-xl border @error('phone') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('phone')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="company" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Perusahaan / Usaha
                        </label>
                        <input type="text" name="company" id="company"
                               value="{{ old('company', $user->company) }}"
                               placeholder="Nama bisnis atau instansi" maxlength="100"
                               class="w-full rounded-xl border @error('company') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('company')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Kota / Kabupaten
                        </label>
                        <input type="text" name="city" id="city"
                               value="{{ old('city', $user->city) }}"
                               placeholder="Kota domisili Anda" maxlength="100"
                               class="w-full rounded-xl border @error('city') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('city')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="address" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Alamat Lengkap
                        </label>
                        <input type="text" name="address" id="address"
                               value="{{ old('address', $user->address) }}"
                               placeholder="Alamat tempat tinggal / kantor" maxlength="500"
                               class="w-full rounded-xl border @error('address') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('address')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div>
                    <label for="bio" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                        Bio Singkat
                    </label>
                    <textarea name="bio" id="bio" rows="2" maxlength="500"
                              placeholder="Deskripsi singkat mengenai Anda atau bidang keahlian Anda"
                              class="w-full rounded-xl border @error('bio') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">{{ old('bio', $user->bio) }}</textarea>
                    @error('bio')
                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="rounded-xl bg-primary px-6 py-2.5 text-sm font-semibold text-primary-foreground shadow-xs transition hover:bg-primary/90 focus:outline-none focus:ring-2 focus:ring-primary/20">
                        Simpan Data Profil
                    </button>
                </div>
            </form>
        </x-section>

        {{-- ===================== Ubah Kata Sandi ===================== --}}
        <x-section judul="Keamanan Kata Sandi"
                   sub="Pastikan kata sandi Anda menggunakan minimal 8 karakter dengan kombinasi huruf dan angka.">
            <form method="POST" action="{{ route('profile.password') }}" class="max-w-2xl space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label for="current_password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                        Kata Sandi Saat Ini
                    </label>
                    <input type="password" name="current_password" id="current_password"
                           required autocomplete="current-password"
                           class="w-full rounded-xl border @error('current_password') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                    @error('current_password')
                        <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Kata Sandi Baru
                        </label>
                        <input type="password" name="password" id="password"
                               required autocomplete="new-password"
                               placeholder="Minimal 8 karakter"
                               class="w-full rounded-xl border @error('password') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('password')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-foreground">
                            Konfirmasi Kata Sandi Baru
                        </label>
                        <input type="password" name="password_confirmation" id="password_confirmation"
                               required autocomplete="new-password"
                               placeholder="Ulangi kata sandi baru"
                               class="w-full rounded-xl border @error('password_confirmation') border-destructive focus:ring-destructive/20 @else border-input focus:border-primary focus:ring-primary/20 @enderror bg-background px-4 py-2.5 text-sm transition focus:outline-none focus:ring-2">
                        @error('password_confirmation')
                            <p class="mt-1.5 text-xs text-destructive font-medium">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="rounded-xl border border-border bg-card px-5 py-2.5 text-sm font-semibold text-foreground shadow-xs transition hover:bg-muted focus:outline-none focus:ring-2 focus:ring-primary/20">
                        Perbarui Kata Sandi
                    </button>
                </div>
            </form>
        </x-section>
    </div>
@endsection
