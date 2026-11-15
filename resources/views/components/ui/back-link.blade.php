@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex min-h-12 items-center gap-2 rounded-control border border-slate-300 bg-white px-3 text-sm font-semibold text-muted transition hover:border-slate-400 hover:text-ink']) }}>
    <svg class="size-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
        <path d="M15.833 10H4.167M9.167 5 4.167 10l5 5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Kembali
</a>
