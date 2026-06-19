@extends('layouts.app')

@section('title', 'Mapa')
@section('heading', 'Mapa operativo')

@section('content')
    <div class="mb-5 flex gap-2 overflow-x-auto">
        @foreach($plans as $item)
            <a class="{{ $plan?->is($item) ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('map.index', ['plan' => $item]) }}">{{ $item->name }}</a>
        @endforeach
    </div>

    @if(!$plan)
        <div class="panel empty-state">Carga un plano para activar el mapa.</div>
    @elseif(!$plan->drawablePath())
        <div class="panel empty-state">El plano necesita una vista previa raster.</div>
    @else
        <div class="panel p-4">
            <div class="mb-3 flex justify-between"><div><strong>{{ $plan->name }}</strong><p class="text-xs text-slate-500">Actualización cada 10 segundos · {{ $plan->zones->count() }} áreas definidas · el círculo representa el error estimado</p></div><span id="map-updated" class="text-xs text-slate-500">Esperando datos…</span></div>
            <div class="plan-ribbon mb-3" role="toolbar" aria-label="Capas del mapa">
                <details class="ribbon-layers">
                    <summary><x-nav-icon name="map"/><span>Visualizar</span></summary>
                    <div class="ribbon-layer-menu">
                        <label><input type="checkbox" data-map-layer="beacons" checked> Beacons</label>
                        <label><input type="checkbox" data-map-layer="zones" checked> Zonas</label>
                        <label><input type="checkbox" data-map-layer="assets" checked> Assets</label>
                    </div>
                </details>
            </div>
            <div id="realtime-map" class="relative inline-block max-w-full overflow-hidden rounded-xl border" data-endpoint="{{ route('map.data', $plan) }}">
                <img class="block max-h-[75vh] max-w-full" src="{{ route('floor-plans.file', $plan) }}" alt="{{ $plan->name }}">
                <div id="map-markers" class="absolute inset-0">
                    @foreach($plan->zones as $zone)
                        <div class="map-zone saved-zone" style="left: {{ (float) $zone->x_min * 100 }}%; top: {{ (float) $zone->y_min * 100 }}%; width: {{ ((float) $zone->x_max - (float) $zone->x_min) * 100 }}%; height: {{ ((float) $zone->y_max - (float) $zone->y_min) * 100 }}%; border-color: {{ $zone->color }}; background-color: {{ $zone->color }}33" title="{{ $zone->name }}"><span style="background-color: {{ $zone->color }}">{{ $zone->name }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
@endsection
