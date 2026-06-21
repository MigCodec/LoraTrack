@extends('layouts.app')

@section('title', 'Mapa')
@section('heading', 'Mapa operativo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/floor-plan-editor.css') }}?v={{ filemtime(public_path('css/floor-plan-editor.css')) }}">
@endpush

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
                <div class="ribbon-group">
                    <span class="ribbon-label">Vista</span>
                    <details class="ribbon-layers">
                        <summary><x-nav-icon name="map"/><span>Visualizar</span></summary>
                        <div class="ribbon-layer-menu">
                            <label><input type="checkbox" data-map-layer="beacons" checked> Beacons</label>
                            <label><input type="checkbox" data-map-layer="zones" checked> Zonas</label>
                            <label><input type="checkbox" data-map-layer="assets" checked> Assets</label>
                        </div>
                    </details>
                </div>
            </div>
            <div id="realtime-map" class="relative inline-block max-w-full overflow-hidden rounded-xl border" data-endpoint="{{ route('map.data', $plan) }}">
                <img class="block max-h-[75vh] max-w-full" src="{{ route('floor-plans.file', $plan) }}" alt="{{ $plan->name }}">
                <div id="map-markers" class="absolute inset-0">
                    @foreach($plan->zones as $zone)
                        <div class="map-zone saved-zone" style="left: {{ (float) $zone->x_min * 100 }}%; top: {{ (float) $zone->y_min * 100 }}%; width: {{ ((float) $zone->x_max - (float) $zone->x_min) * 100 }}%; height: {{ ((float) $zone->y_max - (float) $zone->y_min) * 100 }}%; border-color: {{ $zone->color }}; background-color: {{ $zone->color }}33" title="{{ $zone->name }}"><span style="background-color: {{ $zone->color }}">{{ $zone->name }}</span></div>
                    @endforeach
                </div>
            </div>
            <p id="map-position-status" class="mt-3 text-xs text-slate-500">Consultando posiciones calculadas…</p>
        </div>

        <dialog id="asset-technical-dialog" class="asset-technical-dialog" aria-labelledby="asset-technical-title">
            <div class="asset-technical-header">
                <div>
                    <p class="text-xs text-slate-500">Detalle de posicionamiento</p>
                    <h2 id="asset-technical-title" class="text-lg font-semibold"></h2>
                    <p id="asset-technical-subtitle" class="text-xs text-slate-500"></p>
                </div>
                <form method="dialog"><button class="asset-technical-close" aria-label="Cerrar detalle">&times;</button></form>
            </div>
            <div class="asset-technical-body">
                <dl class="asset-technical-metrics">
                    <div><dt>Posición</dt><dd id="asset-detail-position"></dd></div>
                    <div><dt>Zona</dt><dd id="asset-detail-zone"></dd></div>
                    <div><dt>Confianza</dt><dd id="asset-detail-confidence"></dd></div>
                    <div><dt>Error estimado</dt><dd id="asset-detail-error"></dd></div>
                    <div><dt>Algoritmo</dt><dd id="asset-detail-algorithm"></dd></div>
                    <div><dt>Calculada</dt><dd id="asset-detail-calculated"></dd></div>
                    <div><dt>Observada</dt><dd id="asset-detail-observed"></dd></div>
                    <div><dt>Recibida</dt><dd id="asset-detail-received"></dd></div>
                </dl>
                <h3 class="mt-4 text-sm font-semibold">Anclas usadas por la estimación</h3>
                <p class="mb-2 text-xs text-slate-500">Las circunferencias del plano representan la distancia inferida desde cada RSSI. El residual compara esa distancia con la solución calculada.</p>
                <div class="table-wrap">
                    <table class="data-table asset-evidence-table">
                        <thead><tr><th>Ancla</th><th>RSSI</th><th>Distancia</th><th>Residual</th><th>Calibración</th></tr></thead>
                        <tbody id="asset-detail-evidence"></tbody>
                    </table>
                </div>
            </div>
        </dialog>
    @endif
@endsection
