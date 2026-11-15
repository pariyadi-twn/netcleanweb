@props(['eyebrow' => null, 'title', 'description' => null])

<div {{ $attributes->merge(['class' => 'mb-7 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="max-w-3xl">
        @if ($eyebrow)
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-brand-700">{{ $eyebrow }}</p>
        @endif
        <h1 class="mt-2 text-3xl font-extrabold tracking-[-0.035em] text-ink sm:text-4xl">{{ $title }}</h1>
        @if ($description)
            <p class="mt-2 max-w-2xl text-sm leading-6 text-muted">{{ $description }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
