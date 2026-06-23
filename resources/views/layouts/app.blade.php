<!DOCTYPE html>
<html lang="es">
<head>
    @php($tenant = app(App\Tenancy\OrganizationContext::class)->organization())
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LoraTrack') · {{ config('app.name') }}</title>
    @php($faviconVersion = $tenant?->logo_path ? sha1($tenant->logo_path.'|'.$tenant->updated_at?->timestamp) : 'default-v1')
    <link rel="icon" href="{{ route('favicon', ['v' => $faviconVersion]) }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @stack('styles')
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
    @stack('scripts')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900" @if($tenant) style="--color-brand-primary: {{ $tenant->primary_color }}; --color-brand-secondary: {{ $tenant->secondary_color }}; --color-brand-accent: {{ $tenant->accent_color }}; --color-brand-energy: {{ $tenant->accent_color }}" @endif>
    <div class="min-h-screen lg:flex">
        <aside class="brand-sidebar sidebar-shell px-5 py-6 text-white lg:fixed lg:inset-y-0 lg:w-64">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3" aria-label="LoraTrack, inicio">
                @if($tenant?->logo_path)<img src="{{ route('organizations.logo') }}" alt="Logo de {{ $tenant->name }}" class="h-11 w-11 rounded bg-white object-contain p-1">@else<img src="{{ asset('images/loratrack-default-logo.png') }}" alt="Logo de LoraTrack" class="h-11 w-11 rounded object-contain">@endif
                <span><strong class="block max-w-36 truncate text-lg tracking-wide">{{ $tenant?->name ?? 'LoraTrack' }}</strong><span class="text-xs text-white/65">Asset intelligence</span></span>
            </a>

            <nav class="sidebar-nav mt-8 grid gap-1" aria-label="Navegación principal">
                <p class="nav-section-label">Operación</p>
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'nav-link-active' : '' }}" href="{{ route('dashboard') }}"><x-nav-icon name="dashboard"/><span>Dashboard</span></a>
                <a class="nav-link {{ request()->routeIs('products.*') ? 'nav-link-active' : '' }}" href="{{ route('products.index') }}"><x-nav-icon name="products"/><span>Productos y SKU</span></a>
                <a class="nav-link {{ request()->routeIs('assets.*') ? 'nav-link-active' : '' }}" href="{{ route('assets.index') }}"><x-nav-icon name="assets"/><span>Activos</span></a>
                <a class="nav-link {{ request()->routeIs('floor-plans.*') || request()->routeIs('zones.*') ? 'nav-link-active' : '' }}" href="{{ route('floor-plans.index') }}"><x-nav-icon name="plans"/><span>Planos y zonas</span></a>
                <a class="nav-link {{ request()->routeIs('map.*') ? 'nav-link-active' : '' }}" href="{{ route('map.index') }}"><x-nav-icon name="map"/><span>Mapa operativo</span></a>

                @if(auth()->user()->hasPermission('plans.manage') || auth()->user()->hasPermission('payload_profiles.manage'))
                    <p class="nav-section-label">Ingeniería</p>
                    @if(auth()->user()->hasPermission('plans.manage'))
                        <a class="nav-link {{ request()->routeIs('calibration.*') || request()->routeIs('installations.*') ? 'nav-link-active' : '' }}" href="{{ route('floor-plans.index') }}"><x-nav-icon name="calibration"/><span>Configuración de planos</span></a>
                    @endif
                    @if(auth()->user()->hasPermission('payload_profiles.manage'))
                        <a class="nav-link {{ request()->routeIs('payload-profiles.*') ? 'nav-link-active' : '' }}" href="{{ route('payload-profiles.index') }}"><x-nav-icon name="decoder"/><span>Decoders de payload</span></a>
                    @endif
                @endif

                @if(auth()->user()->hasPermission('alerts.manage') || auth()->user()->hasPermission('operations.view'))
                    <p class="nav-section-label">Supervisión</p>
                    @if(auth()->user()->hasPermission('alerts.manage'))
                        <a class="nav-link {{ request()->routeIs('alerts.*') ? 'nav-link-active' : '' }}" href="{{ route('alerts.index') }}"><x-nav-icon name="alerts"/><span>Alertas</span></a>
                    @endif
                    @if(auth()->user()->hasPermission('operations.view'))
                        <a class="nav-link {{ request()->routeIs('operations.*') ? 'nav-link-active' : '' }}" href="{{ route('operations.health') }}"><x-nav-icon name="health"/><span>Salud operacional</span></a>
                    @endif
                @endif

                @if(auth()->user()->isAdmin())
                    <p class="nav-section-label">Administración</p>
                    <a class="nav-link {{ request()->routeIs('organizations.*') ? 'nav-link-active' : '' }}" href="{{ route('organizations.index') }}"><x-nav-icon name="organizations"/><span>Identidad de empresa</span></a>
                    <a class="nav-link {{ request()->routeIs('connectors.*') ? 'nav-link-active' : '' }}" href="{{ route('connectors.index') }}"><x-nav-icon name="connectors"/><span>Conectores</span></a>
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'nav-link-active' : '' }}" href="{{ route('users.index') }}"><x-nav-icon name="users"/><span>Usuarios y grupos</span></a>
                @endif

                <p class="nav-section-label">Soporte</p>
                <a class="nav-link {{ request()->routeIs('help.*') ? 'nav-link-active' : '' }}" href="{{ route('help.devices') }}"><x-nav-icon name="help"/><span>Dispositivos compatibles</span></a>
            </nav>

            <div class="sidebar-user mt-8 border-t border-white/15 pt-5">
                <p class="truncate text-sm font-semibold">{{ auth()->user()->name }}</p>
                <p class="truncate text-xs text-white/60">{{ auth()->user()->email }}</p>
                <p class="mt-1 truncate text-xs text-white/75">{{ app(App\Tenancy\OrganizationContext::class)->organization()?->name }}</p>
                <p class="mt-1 text-xs text-white/60">{{ auth()->user()->effectiveRole()->label() }}</p>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">@csrf<button class="text-sm text-white/75 hover:text-white">Cerrar sesión</button></form>
            </div>
        </aside>

        <main class="min-w-0 flex-1 lg:ml-64">
            <header class="border-b border-slate-200 bg-white px-6 py-5 lg:px-10">
                <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-xs font-semibold uppercase tracking-[0.18em] text-brand-accent">LoraTrack · {{ $tenant?->name }}</p>@if(auth()->user()->isAdmin())@endif</div>
                <h1 class="mt-1 text-2xl font-semibold text-slate-950">@yield('heading', 'Dashboard')</h1>
            </header>
            <div class="p-6 lg:p-10">
                @if(session('status'))<div class="mb-6 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>@endif
                @if($errors->any())
                    <div class="mb-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><p class="font-semibold">Revisa la información ingresada.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
