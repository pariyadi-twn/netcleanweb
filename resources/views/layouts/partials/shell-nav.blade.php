@php($navigation = isset($tenantContext) ? \App\Support\WebNavigation::groups($tenantContext) : [])

<nav aria-label="Navigasi utama" class="flex flex-col gap-6">
    @foreach ($navigation as $group => $items)
        <div>
            <p class="px-3 text-[10px] font-bold uppercase tracking-[0.2em] text-white/35">{{ $group }}</p>
            <div class="mt-2 space-y-1">
                @foreach ($items as $item)
                    <x-ui.sidebar-link :href="route($item['route'])" :active="request()->routeIs($item['active'])" :icon="$item['icon']">
                        {{ $item['label'] }}
                    </x-ui.sidebar-link>
                @endforeach
            </div>
        </div>
    @endforeach
</nav>
