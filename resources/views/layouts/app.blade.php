<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-background text-foreground antialiased">
<div x-data="{ sidebar: false }" class="min-h-full">

    <header class="sticky top-0 z-30 border-b border-border bg-background/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            <button @click="sidebar = !sidebar" class="rounded-md p-2 text-muted-foreground hover:bg-muted lg:hidden" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 font-semibold">
                <img src="{{ asset('images/flustra-wa.png') }}" alt="Logo" class="h-8 w-auto object-contain">
                <span class="hidden sm:inline">Flustra WA Gateway</span>
            </a>

            <div class="ml-auto flex items-center gap-3">
                <a href="{{ route('docs.index') }}"
                   class="rounded-lg px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted hover:text-foreground">
                    Docs
                </a>

                @isset($availableTenants)
                    <form method="POST" action="#" x-data class="hidden sm:block">
                        <select
                            class="rounded-lg border-border bg-background py-1.5 text-sm focus:border-primary focus:ring-primary"
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
                    <button class="text-sm text-muted-foreground hover:text-foreground">Keluar</button>
                </form>
            </div>
        </div>
    </header>

    <div class="mx-auto flex max-w-7xl gap-6 px-4 py-6">
        <aside
            class="fixed inset-y-0 left-0 z-40 w-60 shrink-0 border-r border-border bg-background p-4 pt-20 lg:static lg:z-0 lg:block lg:border-0 lg:bg-transparent lg:p-0 lg:pt-0"
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
                       class="block rounded-lg px-3 py-2 {{ request()->routeIs($route) ? 'bg-primary font-medium text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </aside>

        <main class="min-w-0 flex-1">
            <h1 class="mb-4 text-xl font-semibold">@yield('title', 'Dashboard')</h1>

            @if (session('status'))
                <div class="mb-4 rounded-lg border border-primary/20 bg-primary/10 px-4 py-3 text-sm font-medium text-primary">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-destructive/20 bg-destructive/10 px-4 py-3 text-sm font-medium text-destructive">
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
