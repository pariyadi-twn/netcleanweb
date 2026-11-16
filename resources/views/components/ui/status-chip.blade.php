@props(['tone' => 'info'])

@php
    $classes = match ($tone) {
        'success' => 'bg-success-bg text-success-text ring-green-200',
        'warning' => 'bg-warning-bg text-warning-text ring-amber-300',
        'danger' => 'bg-danger-bg text-danger-text ring-red-200',
        default => 'bg-brand-50 text-brand-700 ring-blue-200',
    };
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide ring-1 ring-inset '.$classes]) }}>
    {{ $slot }}
</span>
