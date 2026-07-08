@props(['name'])

<svg {{ $attributes->merge(['class' => 'nav-icon', 'width' => '18', 'height' => '18', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
            @break
        @case('products')
            <path d="m12 3 8 4.5-8 4.5-8-4.5L12 3Z"/><path d="m4 7.5 8 4.5 8-4.5V16l-8 5-8-5V7.5Z"/><path d="M12 12v9"/>
            @break
        @case('assets')
            <path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M3 12h18M9 12v2h6v-2"/>
            @break
        @case('devices')
            <rect x="7" y="5" width="10" height="14" rx="2"/><path d="M10 2v3M14 2v3M10 19v3M14 19v3M4 9h3M4 15h3M17 9h3M17 15h3"/><circle cx="12" cy="12" r="2"/>
            @break
        @case('plans')
            <path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3V6Z"/><path d="M9 3v15M15 6v15"/>
            @break
        @case('map')
            <circle cx="12" cy="10" r="3"/><path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 1 1 14 0Z"/>
            @break
        @case('calibration')
            <circle cx="12" cy="12" r="7"/><circle cx="12" cy="12" r="2"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
            @break
        @case('decoder')
            <path d="m8 9-4 3 4 3M16 9l4 3-4 3M14 5l-4 14"/>
            @break
        @case('alerts')
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9ZM10 21h4"/>
            @break
        @case('health')
            <path d="M3 12h4l2-5 4 10 2-5h6"/><path d="M20 7a9 9 0 1 0 0 10"/>
            @break
        @case('connectors')
            <path d="M8 12h8M5 8v3a3 3 0 0 0 3 3M19 8v3a3 3 0 0 1-3 3"/><path d="M3 4h4v4H3zM17 4h4v4h-4zM10 16h4v4h-4z"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="4"/><path d="M2 21v-2a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v2M16 4a4 4 0 0 1 0 8M18 14a6 6 0 0 1 4 5v2"/>
            @break
        @case('organizations')
            <path d="M3 21V7l6-4 6 4v14M15 10h6v11M7 9h4M7 13h4M7 17h4M18 14h1M18 18h1"/>
            @break
        @case('help')
            <rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 7h6M9 11h6M9 15h3"/>
            @break
    @endswitch
</svg>
