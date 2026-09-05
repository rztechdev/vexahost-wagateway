@extends('layouts.admin')
@section('title', 'Pengguna')

@section('content')
    <form method="GET" class="mb-4 flex flex-wrap gap-2">
        <input type="search" name="cari" value="{{ $cari }}" placeholder="Cari nama atau email"
               class="w-full max-w-sm rounded-lg border-border bg-background text-sm focus:border-primary focus:ring-primary">
        <button class="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:opacity-90">Cari</button>
    </form>

    <x-card>
        <div class="-mx-5 overflow-x-auto">
            <table class="w-full min-w-[40rem] text-sm">
                <thead class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                    <tr>
                        <th class="px-5 py-2 font-medium">Nama</th>
                        <th class="px-5 py-2 font-medium">Email</th>
                        <th class="px-5 py-2 font-medium">Workspace</th>
                        <th class="px-5 py-2 font-medium">Terakhir masuk</th>
                        <th class="px-5 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr class="border-b border-border last:border-0">
                            <td class="px-5 py-2.5 font-medium">
                                {{ $user->name }}
                                @if ($user->is_super_admin)
                                    <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">super admin</span>
                                @endif
                            </td>
                            <td class="px-5 py-2.5 text-muted-foreground">{{ $user->email }}</td>
                            <td class="px-5 py-2.5">{{ $user->workspaces_count }}</td>
                            <td class="px-5 py-2.5 text-muted-foreground">
                                {{ $user->last_login_at?->translatedFormat('j M Y, H:i') ?? 'belum pernah' }}
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                @if ($user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.users.super', $user->id) }}"
                                          onsubmit="return confirm('Ubah hak super admin untuk {{ $user->email }}?')">
                                        @csrf
                                        <button class="hover:underline {{ $user->is_super_admin ? 'text-destructive' : 'text-primary' }}">
                                            {{ $user->is_super_admin ? 'Cabut' : 'Jadikan super admin' }}
                                        </button>
                                    </form>
                                @else
                                    {{-- Hak diri sendiri tidak bisa dicabut dari sini: orang
                                         terakhir yang melakukannya mengunci panel dari luar. --}}
                                    <span class="text-xs text-muted-foreground">Anda</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-4 text-muted-foreground">Tidak ada pengguna yang cocok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection
