<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-full place-items-center bg-stone-100 p-4 antialiased dark:bg-stone-950">
    <main class="w-full max-w-sm">
        <a href="{{ route('welcome') }}" class="mb-6 flex items-center justify-center gap-2.5">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-emerald-600 text-sm font-bold text-white">WA</span>
            <span class="font-semibold text-stone-800 dark:text-stone-200">Flustra WA Gateway</span>
        </a>

        <div class="rounded-2xl border border-stone-200 bg-white p-6 dark:border-stone-800 dark:bg-stone-900">
            <h1 class="text-lg font-semibold text-stone-800 dark:text-stone-100">@yield('heading')</h1>
            <p class="mt-1 text-sm text-stone-500">@yield('subheading')</p>

            @if (session('status'))
                <div class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
                    <ul class="space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('form')
        </div>

        <p class="mt-5 text-center text-sm text-stone-500">@yield('footer')</p>

        <p class="mt-3 text-center text-sm">
            <a href="{{ route('docs.index') }}" class="text-stone-400 hover:text-stone-600 hover:underline dark:hover:text-stone-300">
                Dokumentasi
            </a>
        </p>
    </main>
</body>
</html>
