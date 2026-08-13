<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Autentikasi lokal milik flustra-wa sendiri.
 *
 * Setiap aplikasi Flustra memegang form login dan register-nya masing-masing;
 * flustra-auth berperan menangkap sesi lintas aplikasi, bukan menjadi satu-satunya
 * pintu masuk. Jadi gateway ini punya tabel `users` dan alur autentikasinya
 * sendiri, sama seperti aplikasi lain di ekosistem.
 *
 * Bentuknya sengaja dijaga sederhana: cukup untuk dipakai dan diuji sekarang,
 * tanpa memutuskan lebih dulu hal-hal yang belum perlu diputuskan (verifikasi
 * email, dua faktor, penautan ke flustra-auth).
 */
class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            // Pesan yang sama untuk email tidak dikenal maupun password salah,
            // supaya form ini tidak bisa dipakai menebak email mana yang terdaftar.
            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak cocok.',
            ]);
        }

        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'workspace' => ['required', 'string', 'max:80'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'workspace' => 'nama workspace',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);

        // Workspace dibuat sekalian saat mendaftar. Tanpa ini pengguna baru
        // mendarat di dashboard yang belum bisa dipakai apa-apa dan harus
        // melewati satu langkah lagi sebelum melihat hasil apa pun.
        $tenant = Tenant::create([
            'name' => $data['workspace'],
            'slug' => $this->uniqueSlug($data['workspace']),
            'owner_id' => $user->id,
            'owner_email' => $user->email,
            'max_sessions' => config('gateway.defaults.max_sessions'),
            'monthly_message_quota' => config('gateway.defaults.monthly_message_quota'),
            'api_rate_limit_per_minute' => config('gateway.defaults.api_rate_limit_per_minute'),
        ]);

        $tenant->members()->attach($user->id, ['role' => 'owner']);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        session(['current_tenant_id' => $tenant->id]);

        return redirect()->route('sessions.index')
            ->with('status', 'Workspace dibuat. Langkah berikutnya: buat sesi lalu scan QR-nya.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('welcome');
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'workspace';
        $slug = $base;
        $i = 2;

        while (Tenant::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
