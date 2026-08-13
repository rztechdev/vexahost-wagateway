@extends('layouts.docs')

@section('title', $page['title'])
@section('description', $page['summary'])

@section('content')
<div class="flex gap-10">
    <article class="min-w-0 flex-1">
        <nav class="mb-2 text-sm text-stone-500">
            <a href="{{ route('docs.index') }}" class="hover:underline">Dokumentasi</a>
            <span class="mx-1.5">/</span>
            <span>{{ $page['group'] }}</span>
        </nav>

        <h1 class="text-3xl font-semibold tracking-tight">{{ $page['title'] }}</h1>
        <p class="mt-2 text-stone-600 dark:text-stone-400">{{ $page['summary'] }}</p>

        <hr class="my-8 border-stone-200 dark:border-stone-800">

        {{-- Isinya berasal dari berkas markdown di repo, bukan masukan pengguna --}}
        <div class="doc-body">
            {!! $page['html'] !!}
        </div>

        <nav class="mt-14 flex flex-wrap gap-4 border-t border-stone-200 pt-6 dark:border-stone-800">
            @if ($page['prev'])
                <a href="{{ route('docs.show', $page['prev']['slug']) }}"
                   class="group flex-1 rounded-xl border border-stone-200 p-4 hover:border-emerald-400 dark:border-stone-800 dark:hover:border-emerald-800">
                    <span class="text-xs text-stone-500">Sebelumnya</span>
                    <p class="mt-0.5 font-medium group-hover:text-emerald-700 dark:group-hover:text-emerald-400">
                        &larr; {{ $page['prev']['title'] }}
                    </p>
                </a>
            @endif

            @if ($page['next'])
                <a href="{{ route('docs.show', $page['next']['slug']) }}"
                   class="group flex-1 rounded-xl border border-stone-200 p-4 text-right hover:border-emerald-400 dark:border-stone-800 dark:hover:border-emerald-800">
                    <span class="text-xs text-stone-500">Berikutnya</span>
                    <p class="mt-0.5 font-medium group-hover:text-emerald-700 dark:group-hover:text-emerald-400">
                        {{ $page['next']['title'] }} &rarr;
                    </p>
                </a>
            @endif
        </nav>
    </article>

    {{-- Daftar isi halaman ini --}}
    @if (count($page['toc']) > 2)
        <aside class="hidden w-56 shrink-0 xl:block">
            <div class="sticky top-24">
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-stone-400">Di halaman ini</p>
                <ul class="space-y-1.5 border-l border-stone-200 text-sm dark:border-stone-800">
                    @foreach ($page['toc'] as $item)
                        <li>
                            <a href="#{{ $item['id'] }}"
                               class="-ml-px block border-l border-transparent py-0.5 pl-4 text-stone-500 hover:border-emerald-500 hover:text-emerald-700 dark:hover:text-emerald-400">
                                {{ $item['text'] }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </aside>
    @endif
</div>
@endsection
