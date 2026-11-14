@props(['tone' => 'info'])

@php
    $classes = match ($tone) {
        'success' => 'border-green-200 bg-success-bg text-success-text',
        'warning' => 'border-amber-300 bg-warning-bg text-warning-text',
        'danger' => 'border-red-200 bg-danger-bg text-danger-text',
        default => 'border-blue-200 bg-brand-50 text-brand-700',
    };
@endphp

<div {{ $attributes->merge(['class' => 'rounded-container border px-4 py-3 text-sm '.$classes]) }} role="alert">
    {{ $slot }}
</div>
