@extends('layouts.app')
@section('title', 'Pengaturan')

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <x-card title="Workspace">
            <dl class="space-y-3 text-sm">
                @php
                    $rows = [
                        'Nama' => $currentWorkspace->name,
                        'Slug' => $currentWorkspace->slug,
                        'Status' => $currentWorkspace->status,
                        'Paket' => $currentWorkspace->plan_slug ?? '—',
                        'Batas sesi' => $currentWorkspace->max_sessions,
                        'Kuota pesan/bulan' => $currentWorkspace->is_internal ? 'tanpa batas (internal)' : number_format($currentWorkspace->monthly_message_quota),
                        'Rate limit API' => $currentWorkspace->api_rate_limit_per_minute.' request/menit',
                    ];
                @endphp
                @foreach ($rows as $label => $value)
                    <div class="flex justify-between gap-4 border-b border-border pb-2 last:border-0">
                        <dt class="text-muted-foreground">{{ $label }}</dt>
                        <dd class="text-right font-medium">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-4 text-xs text-muted-foreground">
                Batas paket mengikuti langganan Anda dan tidak bisa diubah dari halaman ini, supaya tagihan dan
                batas pemakaian tidak pernah berbeda. Butuh batas yang lebih besar? Hubungi kami.
            </p>

            @if (auth()->user()->canManage($currentWorkspace))
                <form method="POST" action="{{ route('settings.update') }}" class="mt-5 border-t border-border pt-5">
                    @csrf
                    @method('PUT')
                    <label class="mb-1 block text-sm font-medium" for="workspace-name">Ganti nama workspace</label>
                    <div class="flex flex-wrap gap-2">
                        <input id="workspace-name" name="name" required maxlength="80"
                               value="{{ old('name', $currentWorkspace->name) }}"
                               class="min-w-48 flex-1 rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Simpan</button>
                    </div>
                    @error('name')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-muted-foreground">
                        Slug <code>{{ $currentWorkspace->slug }}</code> sengaja tidak ikut berubah — ia dipakai
                        sebagai pengenal tetap di catatan audit. Tidak ada API key atau URL yang bergantung padanya.
                    </p>

                    <label class="mb-1 mt-5 block text-sm font-medium" for="billing-phone">Nomor WhatsApp untuk tagihan</label>
                    <input id="billing-phone" name="billing_phone" maxlength="20" inputmode="tel"
                           value="{{ old('billing_phone', $currentWorkspace->billing_phone) }}"
                           placeholder="08xxxxxxxxxx"
                           class="w-full max-w-xs rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    @error('billing_phone')
                        <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-muted-foreground">
                        Ke sinilah pengingat masa berlaku langganan dikirim, beberapa hari sebelum habis.
                        Berbeda dari nomor sesi Anda — nomor ini justru paling dibutuhkan saat sesinya sedang mati.
                        Kosongkan kalau Anda memilih hanya mengandalkan pemberitahuan di dashboard.
                    </p>
                </form>
            @endif
        </x-card>

        <x-card title="Pemakaian">
            <div class="mb-4">
                <p class="text-sm text-muted-foreground">Bulan berjalan ({{ $usage->period }})</p>
                <p class="mt-1 text-2xl font-semibold">{{ number_format($usage->messages_sent) }} <span class="text-base font-normal text-muted-foreground">terkirim</span></p>
            </div>
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-muted-foreground">
                    <tr class="border-b border-border">
                        <th class="py-2">Periode</th>
                        <th class="py-2 text-right">Keluar</th>
                        <th class="py-2 text-right">Masuk</th>
                        <th class="py-2 text-right">Gagal</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history as $row)
                        <tr class="border-b border-border last:border-0 hover:bg-muted/50">
                            <td class="py-2">{{ $row->period }}</td>
                            <td class="py-2 text-right">{{ number_format($row->messages_sent) }}</td>
                            <td class="py-2 text-right">{{ number_format($row->messages_received) }}</td>
                            <td class="py-2 text-right {{ $row->messages_failed > 0 ? 'text-destructive' : '' }}">{{ number_format($row->messages_failed) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-card>
    </div>

    <x-card title="Anggota" subtitle="Anggota harus sudah pernah login ke gateway ini sebelum bisa ditambahkan." class="mt-6">
        <form method="POST" action="{{ route('settings.members.add') }}" class="mb-5 flex flex-wrap items-end gap-3">
            @csrf
            <div class="min-w-56 flex-1">
                <label class="mb-1 block text-sm font-medium" for="email">Email</label>
                <input id="email" name="email" type="email" required
                       class="w-full rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium" for="role">Peran</label>
                <select id="role" name="role" class="rounded-lg border-input bg-background text-sm focus:border-primary focus:ring-primary">
                    <option value="member">Member — hanya melihat &amp; mengirim</option>
                    <option value="admin">Admin — bisa kelola sesi &amp; kunci</option>
                </select>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90">Tambah</button>
        </form>

        <div class="space-y-2">
            @foreach ($members as $member)
                <div class="flex items-center justify-between border-b border-border py-2 last:border-0">
                    <div>
                        <p class="text-sm font-medium">{{ $member->name }}</p>
                        <p class="text-xs text-muted-foreground">{{ $member->email }} &middot; {{ $member->pivot->role }}</p>
                    </div>
                    @if ($member->pivot->role !== 'owner')
                        <form method="POST" action="{{ route('settings.members.remove', $member->id) }}"
                              data-konfirmasi="Keluarkan {{ $member->name }} dari workspace ini?">
                            @csrf @method('DELETE')
                            <button class="text-xs text-destructive hover:underline">Keluarkan</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
    </x-card>

    @if (auth()->user()->is_super_admin || auth()->user()->roleIn($currentWorkspace) === 'owner')
        <x-card title="Hapus workspace"
                subtitle="Sesi, riwayat pesan, template, webhook, dan seluruh API key milik workspace ini ikut hilang."
                class="mt-6 border-destructive/40">
            <p class="text-sm text-muted-foreground">
                Nomor yang sedang tertaut akan diputus lebih dulu, dan aplikasi mana pun yang memakai API key
                workspace ini langsung berhenti bisa mengirim. Kalau yang kamu mau hanya mengganti nama,
                pakai kolom di kartu <strong>Workspace</strong> di atas — tidak perlu menghapus apa pun.
            </p>

            <form method="POST" action="{{ route('settings.destroy') }}" class="mt-4">
                @csrf
                @method('DELETE')
                <label class="mb-1 block text-sm font-medium" for="confirm">
                    Ketik <code class="rounded bg-muted px-1">{{ $currentWorkspace->name }}</code> untuk memastikan
                </label>
                <div class="flex flex-wrap gap-2">
                    <input id="confirm" name="confirm" required autocomplete="off"
                           placeholder="{{ $currentWorkspace->name }}"
                           class="min-w-56 flex-1 rounded-lg border-input bg-background text-sm focus:border-destructive focus:ring-destructive">
                    <button class="rounded-lg bg-destructive px-4 py-2 text-sm font-medium text-destructive-foreground hover:bg-destructive/90">
                        Hapus workspace
                    </button>
                </div>
                @error('confirm')
                    <p class="mt-1 text-xs text-destructive">{{ $message }}</p>
                @enderror
            </form>
        </x-card>
    @endif
@endsection
