@props(['label', 'value', 'description' => null, 'tone' => 'default'])

@php
    $classes = $tone === 'accent'
        ? 'border-brand-100 bg-brand-50'
        : 'border-divider bg-white';
@endphp

<section {{ $attributes->merge(['class' => 'rounded-container border p-5 shadow-sm '.$classes]) }}>
    <p class="text-xs font-bold uppercase tracking-[0.14em] text-muted">{{ $label }}</p>
    <p class="mt-3 text-3xl font-extrabold tabular-nums tracking-tight text-ink">{{ $value }}</p>
    @if ($description)
        <p class="mt-2 text-xs leading-5 text-muted">{{ $description }}</p>
    @endif
</section>
