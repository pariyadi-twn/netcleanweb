@props(['href', 'active' => false, 'icon' => 'home'])

<a href="{{ $href }}" @class([
    'group relative flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition',
    'bg-white/10 text-white' => $active,
    'text-white/60 hover:bg-white/5 hover:text-white' => ! $active,
]) @if ($active) aria-current="page" @endif>
    @if ($active)
        <span class="absolute inset-y-2 left-0 w-1 rounded-full bg-accent"></span>
    @endif
    <x-ui.icon :name="$icon" @class(['size-5', 'text-accent' => $active, 'text-white/45 group-hover:text-white/80' => ! $active]) />
    {{ $slot }}
</a>
