@props(['provider'])

@php($provider = $provider instanceof BackedEnum ? $provider->value : (string) $provider)

<span {{ $attributes->class(['connector-icon'])->merge(['data-provider-icon' => $provider]) }}>
    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        @switch($provider)
            @case('tti_webhook')
                <path d="M12 18V9"/><path d="m9 12 3-3 3 3"/><circle cx="12" cy="6" r="2"/><path d="M7.8 4.2a6 6 0 0 0 0 8.5M16.2 4.2a6 6 0 0 1 0 8.5M5 2a9 9 0 0 0 0 13M19 2a9 9 0 0 1 0 13"/><path d="M8 21h8"/>
                @break
            @case('mqtt')
                <path d="M5 19a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/><path d="M5 11a6 6 0 0 1 6 6M5 6a11 11 0 0 1 11 11M5 2a15 15 0 0 1 15 15"/>
                @break
            @case('sap_s4hana')
                <ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v7c0 1.7 3.6 3 8 3s8-1.3 8-3V5"/><path d="M4 12v7c0 1.7 3.6 3 8 3s8-1.3 8-3v-7"/>
                @break
            @case('business_central')
                <rect x="3" y="3" width="8" height="8" rx="1.5"/><rect x="13" y="3" width="8" height="8" rx="1.5"/><rect x="3" y="13" width="8" height="8" rx="1.5"/><path d="M17 14v6M14 17h6"/>
                @break
            @case('shopify')
                <path d="M5 8h14l1 13H4L5 8Z"/><path d="M9 10V7a3 3 0 0 1 6 0v3"/><path d="M9 15c1.5 1.3 4.5 1.3 6 0"/>
                @break
            @case('odoo')
                <circle cx="6" cy="12" r="3"/><circle cx="18" cy="6" r="3"/><circle cx="18" cy="18" r="3"/><path d="m8.7 10.7 6.6-3.4M8.7 13.3l6.6 3.4"/>
                @break
            @case('csv')
                <path d="M6 2h8l4 4v16H6V2Z"/><path d="M14 2v5h5M9 11h6M9 15h6M9 19h4"/>
                @break
            @default
                <path d="M8 12h8M5 8v3a3 3 0 0 0 3 3M19 8v3a3 3 0 0 1-3 3"/><path d="M3 4h4v4H3zM17 4h4v4h-4zM10 16h4v4h-4z"/>
        @endswitch
    </svg>
    <span class="sr-only">{{ $slot->isEmpty() ? 'Conector '.$provider : $slot }}</span>
</span>
