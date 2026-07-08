@extends('layouts.app')
@section('title', 'Dashboard')
@section('heading', 'Dashboard operacional')
@section('content')
    <section class="panel mb-6 p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-accent">Vista {{ $role->label() }}</p>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
            <p class="max-w-2xl text-sm text-slate-600">{{ $role->description() }}</p>
            <div class="flex flex-wrap gap-2">
                @if(auth()->user()->hasPermission('assets.manage'))<a class="action-link" href="{{ route('assets.index') }}">Gestionar activos</a>@endif
                @if(auth()->user()->hasPermission('plans.manage'))<a class="action-link" href="{{ route('floor-plans.index') }}">Configurar planos</a>@endif
                @if(auth()->user()->hasPermission('payload_profiles.manage'))<a class="action-link" href="{{ route('payload-profiles.index') }}">Configurar decoders</a>@endif
                @if(auth()->user()->hasPermission('alerts.manage'))<a class="action-link" href="{{ route('alerts.index') }}">Configurar alertas</a>@endif
                @if(auth()->user()->isAdmin())<a class="action-link" href="{{ route('connectors.index') }}">Administrar conectores</a>@endif
            </div>
        </div>
    </section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach([
            ['label' => 'Productos', 'value' => $metrics['products']],
            ['label' => 'Activos', 'value' => $metrics['assets']],
            ['label' => 'Dispositivos', 'value' => $metrics['devices'], 'url' => route('devices.index')],
            ['label' => 'Conectores activos', 'value' => $metrics['activeConnectors']],
            ['label' => 'Eventos hoy', 'value' => $metrics['eventsToday']],
        ] as $metric)
            @if(isset($metric['url']))
                <a class="metric-card metric-card-link" href="{{ $metric['url'] }}"><p class="text-sm text-slate-500">{{ $metric['label'] }}</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($metric['value']) }}</p><span class="mt-2 block text-xs font-semibold text-brand-primary">Ver dispositivos</span></a>
            @else
                <article class="metric-card"><p class="text-sm text-slate-500">{{ $metric['label'] }}</p><p class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($metric['value']) }}</p></article>
            @endif
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 {{ auth()->user()->hasPermission('operations.view') ? 'xl:grid-cols-3' : '' }}">
        <section class="panel {{ auth()->user()->hasPermission('operations.view') ? 'xl:col-span-2' : '' }}">
            <div class="panel-header"><div><h2 class="panel-title">Posiciones recientes</h2><p class="panel-subtitle">Últimas estimaciones calculadas</p></div></div>
            @if($recentPositions->isEmpty())
                <div class="empty-state">Aún no hay posiciones calculadas. La información aparecerá al procesar telemetría y configurar ubicaciones.</div>
            @else
                <div class="table-wrap"><table class="data-table"><thead><tr><th>Activo</th><th>Zona</th><th>Algoritmo</th><th>Confianza</th><th>Fecha</th></tr></thead><tbody>
                    @foreach($recentPositions as $position)<tr><td>{{ $position->asset->name }}</td><td>{{ $position->zone?->name ?? 'Sin zona' }}</td><td>{{ $position->algorithm }}</td><td>{{ $position->confidence ? number_format((float) $position->confidence * 100, 0).'%' : '—' }}</td><td>{{ $position->calculated_at->diffForHumans() }}</td></tr>@endforeach
                </tbody></table></div>
            @endif
        </section>
        @if(auth()->user()->hasPermission('operations.view'))
            <section class="panel"><div class="panel-header"><div><h2 class="panel-title">Salud de integraciones</h2><p class="panel-subtitle">Errores que requieren atención</p></div></div>
                @if($connectorsWithErrors->isEmpty())<div class="empty-state">No hay errores de conectores registrados.</div>@else<div class="space-y-3 p-5">@foreach($connectorsWithErrors as $connector)<div class="rounded-xl border border-red-100 bg-red-50 p-3"><p class="text-sm font-semibold text-red-900">{{ $connector->name }}</p><p class="mt-1 text-xs text-red-700">{{ Str::limit($connector->last_error, 120) }}</p></div>@endforeach</div>@endif
            </section>
        @endif
    </div>
@endsection
