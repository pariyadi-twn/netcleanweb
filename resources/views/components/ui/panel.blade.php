@props(['title' => null, 'description' => null])

<section {{ $attributes->merge(['class' => 'rounded-container border border-divider bg-white p-5 shadow-sm']) }}>
    @if ($title)
        <div class="mb-4">
            <h2 class="text-lg font-bold text-ink">{{ $title }}</h2>
            @if ($description)
                <p class="mt-1 text-sm text-muted">{{ $description }}</p>
            @endif
        </div>
    @endif
    {{ $slot }}
</section>
