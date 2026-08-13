@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-xl border border-stone-200 bg-white p-5 dark:border-stone-800 dark:bg-stone-900']) }}>
    @if ($title)
        <header class="mb-4">
            <h2 class="font-semibold">{{ $title }}</h2>
            @if ($subtitle)
                <p class="mt-0.5 text-sm text-stone-500">{{ $subtitle }}</p>
            @endif
        </header>
    @endif

    {{ $slot }}
</section>
