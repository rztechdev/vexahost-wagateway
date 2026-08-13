@extends('layouts.docs')

@section('title', $page['title'])
@section('description', $page['summary'])

@section('content')
<div class="flex gap-10">
    <article class="min-w-0 flex-1">
        <nav class="mb-2 text-sm text-muted-foreground">
            <a href="{{ route('docs.index') }}" class="hover:underline hover:text-foreground">Dokumentasi</a>
            <span class="mx-1.5">/</span>
            <span>{{ $page['group'] }}</span>
        </nav>

        <h1 class="text-3xl font-semibold tracking-tight text-foreground">{{ $page['title'] }}</h1>
        <p class="mt-2 text-muted-foreground">{{ $page['summary'] }}</p>

        <hr class="my-8 border-border">

        {{-- Isinya berasal dari berkas markdown di repo, bukan masukan pengguna --}}
        <div class="doc-body">
            {!! $page['html'] !!}
        </div>

        <nav class="mt-14 flex flex-wrap gap-4 border-t border-border pt-6">
            @if ($page['prev'])
                <a href="{{ route('docs.show', $page['prev']['slug']) }}"
                   class="group flex-1 rounded-xl border border-border p-4 hover:border-primary/50 hover:bg-primary/5 transition">
                    <span class="text-xs text-muted-foreground">Sebelumnya</span>
                    <p class="mt-0.5 font-medium text-foreground group-hover:text-primary">
                        &larr; {{ $page['prev']['title'] }}
                    </p>
                </a>
            @endif

            @if ($page['next'])
                <a href="{{ route('docs.show', $page['next']['slug']) }}"
                   class="group flex-1 rounded-xl border border-border p-4 text-right hover:border-primary/50 hover:bg-primary/5 transition">
                    <span class="text-xs text-muted-foreground">Berikutnya</span>
                    <p class="mt-0.5 font-medium text-foreground group-hover:text-primary">
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
                <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Di halaman ini</p>
                <ul class="space-y-1.5 border-l border-border text-sm">
                    @foreach ($page['toc'] as $item)
                        <li>
                            <a href="#{{ $item['id'] }}"
                               class="-ml-px block border-l border-transparent py-0.5 pl-4 text-muted-foreground hover:border-primary hover:text-foreground">
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
