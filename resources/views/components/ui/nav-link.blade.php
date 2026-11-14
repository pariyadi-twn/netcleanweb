@props(['href', 'active' => false])

<a href="{{ $href }}" @class([
    'flex min-h-12 items-center rounded-control px-4 text-sm font-semibold',
    'bg-brand-50 text-brand-700' => $active,
    'text-muted hover:bg-slate-100 hover:text-ink' => ! $active,
]) {{ $attributes }}>
    {{ $slot }}
</a>
