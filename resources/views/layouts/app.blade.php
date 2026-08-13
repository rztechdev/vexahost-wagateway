<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-stone-50 text-stone-800 antialiased dark:bg-stone-950 dark:text-stone-200">
<div x-data="{ sidebar: false }" class="min-h-full">

    <header class="sticky top-0 z-30 border-b border-stone-200 bg-white/90 backdrop-blur dark:border-stone-800 dark:bg-stone-900/90">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <button @click="sidebar = !sidebar" class="rounded-md p-2 text-stone-500 hover:bg-stone-100 lg:hidden dark:hover:bg-stone-800" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2 font-semibold">
                <span class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-600 text-sm font-bold text-white">WA</span>
                <span class="hidden sm:inline">Flustra Gateway</span>
            </a>

            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('docs.index') }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800">
                    Docs
                </a>

                @isset($availableTenants)
                    <form method="POST" action="#" x-data class="hidden sm:block">
                        <select
                            class="rounded-lg border-stone-300 bg-white py-1.5 text-sm dark:border-stone-700 dark:bg-stone-800"
                            @change="$el.form.action = '{{ url('tenants') }}/' + $el.value + '/switch'; $el.form.submit()">
                            @foreach ($availableTenants as $t)
                                <option value="{{ $t->id }}" @selected($t->id === $currentTenant->id)>{{ $t->name }}</option>
                            @endforeach
                        </select>
                        @csrf
                    </form>
                @endisset

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="text-sm text-stone-500 hover:text-stone-800 dark:hover:text-stone-200">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 border-r border-stone-200 bg-white p-4 pt-20 lg:static lg:z-0 lg:block lg:border-0 lg:bg-transparent lg:p-0 lg:pt-0 dark:border-stone-800 dark:bg-stone-900 lg:dark:bg-transparent"
            :class="sidebar ? 'block' : 'hidden'">
            <nav class="space-y-1 text-sm">
                @php
                    $nav = [
                        ['dashboard', 'Ringkasan'],
                        ['sessions.index', 'Sesi WhatsApp'],
                        ['messages.compose', 'Kirim Pesan'],
                        ['messages.index', 'Riwayat Pesan'],
                        ['templates.index', 'Template'],
                        ['api-keys.index', 'API Keys'],
                        ['webhooks.index', 'Webhooks'],
                        ['settings', 'Pengaturan'],
                    ];
                @endphp
                @foreach ($nav as [$route, $label])
                    <a href="{{ route($route) }}"
                       class="block rounded-lg px-3 py-2 {{ request()->routeIs($route) ? 'bg-emerald-600 font-medium text-white' : 'text-stone-600 hover:bg-stone-100 dark:text-stone-400 dark:hover:bg-stone-800' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <h1 class="mb-4 text-xl font-semibold">@yield('title', 'Dashboard')</h1>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                    <ul class="list-inside list-disc space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>

@stack('scripts')
</body>
</html>
