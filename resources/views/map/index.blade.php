@extends('layouts.app')

@section('title', 'Mapa')
@section('heading', 'Mapa operativo')
@section('body_class', 'floor-plan-office-body')
@section('main_class', 'floor-plan-office-main')
@section('header_class', 'floor-plan-office-header')
@section('content_class', 'floor-plan-office-content')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/floor-plan-editor.css') }}?v={{ filemtime(public_path('css/floor-plan-editor.css')) }}">
@endpush
@push('scripts')
    <script defer src="{{ asset('js/floor-plan-navigation.js') }}?v={{ filemtime(public_path('js/floor-plan-navigation.js')) }}"></script>
    @if($plan?->isThreeDimensional())
        <script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.184.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.184.0/examples/jsm/"}}</script>
        <script type="module" src="{{ asset('js/floor-plan-3d.js') }}?v={{ filemtime(public_path('js/floor-plan-3d.js')) }}"></script>
    @endif
@endpush

@section('content')
<div class="floor-plan-office-shell operational-map-shell">
    <div class="plan-ribbon floor-plans-primary-ribbon operational-map-ribbon" role="toolbar" aria-label="Controles del mapa operativo">
        <div class="ribbon-group">
            <span class="ribbon-label">Mapa operativo</span>
            <div class="operational-map-title"><x-nav-icon name="map"/><span><strong>Supervisión en tiempo real</strong><small>Consulta de activos y referencias</small></span></div>
        </div>
        @if($plan && !$plan->isThreeDimensional() && $plan->drawablePath())
            <div class="ribbon-group">
                <span class="ribbon-label">Capas</span>
                <label class="operational-layer-toggle"><input type="checkbox" data-map-layer="assets" checked><span>Activos</span></label>
                <label class="operational-layer-toggle"><input type="checkbox" data-map-layer="zones" checked><span>Zonas</span></label>
                <label class="operational-layer-toggle"><input type="checkbox" data-map-layer="beacons" checked><span>Referencias</span></label>
            </div>
        @endif
        <div class="ribbon-group operational-map-legend" aria-label="Leyenda">
            <span class="ribbon-label">Estado</span>
            <span><i class="is-live"></i>Vigente</span>
            <span><i class="is-stale"></i>Sin señal reciente</span>
            <span><i class="is-outside"></i>Fuera del plano</span>
        </div>
    </div>

    @if(!$plan)
        <div class="floor-plan-empty-workbook empty-state">
            <div><x-nav-icon name="map"/><strong>Mapa operativo sin planos activos</strong><p>Cuando exista un plano activo podrás supervisar aquí la ubicación de los activos.</p></div>
        </div>
    @else
        <div class="floor-plan-workspace">
            <section class="floor-plan-canvas-shell">
                <div class="plan-editor-overview operational-map-overview">
                    <div class="plan-editor-current"><span>Plano en operación</span><strong>{{ $plan->name }}</strong><small>{{ $plan->location->name }} · {{ $plan->width_meters }} × {{ $plan->height_meters }} m</small></div>
                    <div class="plan-editor-ready" id="map-health"><i></i><div><strong>Conectando</strong><small id="map-updated">Esperando datos…</small></div></div>
                    <div class="plan-editor-stats">
                        <div><x-nav-icon name="plans"/><span>Zonas<strong>{{ $plan->zones->count() }}</strong></span></div>
                        <div><x-nav-icon name="map"/><span>Referencias<strong id="map-anchor-count">—</strong></span></div>
                        <div><x-nav-icon name="assets"/><span>Activos<strong id="map-asset-count">—</strong></span></div>
                    </div>
                </div>

                @if($plan->isThreeDimensional())
                    <div class="plan-editor-layout">
                        <div class="plan-editor-stage">
            <div class="plan-viewer-toolbar" role="toolbar" aria-label="Navegación del mapa 3D">
                <span class="plan-viewer-badge">Vista 3D</span>
                <button type="button" data-3d-view="home">Restablecer</button>
                <button type="button" data-3d-view="top">Vista superior</button>
                                <span class="plan-viewer-help">Arrastra para rotar · botón derecho para mover · rueda para zoom</span>
            </div>
            <div id="floor-plan-3d"
                class="floor-plan-3d"
                data-model-url="{{ route('floor-plans.model', $plan) }}"
                data-endpoint="{{ route('map.data', $plan) }}"
                data-width-meters="{{ $plan->width_meters }}"
                data-height-meters="{{ $plan->height_meters }}"
                data-depth-meters="{{ $plan->depth_meters }}"
                data-transform='@json($plan->model_transform ?? [])'
                aria-label="Mapa operativo 3D de {{ $plan->name }}">
                <div class="floor-plan-3d-status" data-3d-status>Cargando modelo 3D…</div>
            </div>
            <script id="floor-plan-3d-markers" type="application/json">[]</script>
                        </div>
                    </div>
                @elseif(!$plan->drawablePath())
                    <div class="floor-plan-empty-workbook empty-state"><div><strong>Vista previa no disponible</strong><p>Este plano necesita una imagen raster para mostrarse en el mapa operativo.</p></div></div>
                @else
                    <div class="plan-editor-layout">
                        <div class="plan-editor-stage operational-map-stage">
                            <div class="plan-viewer-toolbar" role="toolbar" aria-label="Navegación del mapa 2D">
                                <span class="plan-viewer-badge">Vista 2D</span>
                                <button type="button" data-plan-pan aria-pressed="false">Mover</button>
                                <button type="button" data-plan-zoom="out" aria-label="Alejar">−</button>
                                <output data-plan-zoom-value>100%</output>
                                <button type="button" data-plan-zoom="in" aria-label="Acercar">+</button>
                                <button type="button" data-plan-zoom="reset">Ajustar</button>
                                <span class="plan-viewer-help">Selecciona un activo para ver su detalle · rueda para zoom</span>
                            </div>
                            <div id="plan-2d-viewport" class="plan-2d-viewport operational-map-viewport">
            <div id="realtime-map" class="relative inline-block max-w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-100 select-none" data-plan-canvas data-endpoint="{{ route('map.data', $plan) }}">
                <img id="floor-plan-image" class="block max-h-[70vh] max-w-full" src="{{ route('floor-plans.file', $plan) }}" alt="Mapa operativo de {{ $plan->name }}" draggable="false">
                <div id="map-markers" class="absolute inset-0">
                    @foreach($plan->zones as $zone)
                        <div class="map-zone saved-zone" style="left: {{ (float) $zone->x_min * 100 }}%; top: {{ (float) $zone->y_min * 100 }}%; width: {{ ((float) $zone->x_max - (float) $zone->x_min) * 100 }}%; height: {{ ((float) $zone->y_max - (float) $zone->y_min) * 100 }}%; border-color: {{ $zone->color }}; background-color: {{ $zone->color }}33" title="{{ $zone->name }}"><span style="background-color: {{ $zone->color }}">{{ $zone->name }}</span></div>
                    @endforeach
                </div>
            </div>
                            </div>
                            <div id="map-position-status" class="operational-map-status" role="status" aria-live="polite"><i></i><span>Consultando posiciones calculadas…</span></div>
                        </div>
                    </div>
                @endif

                <nav class="plan-sheet-tabs" aria-label="Seleccionar plano operativo">
                    @foreach($plans as $item)
                        <a class="plan-sheet-tab {{ $plan->is($item) ? 'is-active' : '' }}" href="{{ route('map.index', ['plan' => $item]) }}" title="{{ $item->location->name }} · {{ $item->name }}" @if($item->tab_color) style="--sheet-color: {{ $item->tab_color }}" @endif @if($plan->is($item)) aria-current="page" @endif>
                            <span>{{ $item->name }}</span><small>{{ $item->location->name }}</small>
                        </a>
                    @endforeach
                </nav>
            </section>
        </div>

        @if(!$plan->isThreeDimensional() && $plan->drawablePath())
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
                    <div><dt>Medición RSSI sin filtrar</dt><dd id="asset-detail-raw-position"></dd></div>
                    <div><dt>Zona</dt><dd id="asset-detail-zone"></dd></div>
                    <div><dt>Confianza</dt><dd id="asset-detail-confidence"></dd></div>
                    <div><dt>Error estimado</dt><dd id="asset-detail-error"></dd></div>
                    <div><dt>Algoritmo</dt><dd id="asset-detail-algorithm"></dd></div>
                    <div><dt>Última señal</dt><dd id="asset-detail-last-seen"></dd></div>
                    <div><dt>Calculada</dt><dd id="asset-detail-calculated"></dd></div>
                    <div><dt>Observada</dt><dd id="asset-detail-observed"></dd></div>
                    <div><dt>Recibida</dt><dd id="asset-detail-received"></dd></div>
                </dl>
                <h3 class="mt-4 text-sm font-semibold">Anclas usadas por la estimación</h3>
                <p class="mb-2 text-xs text-slate-500">Las circunferencias del plano representan la distancia inferida desde cada RSSI. El residual compara esa distancia con la solución calculada.</p>
                <p class="mb-3"><a id="asset-detail-track-link" class="btn-secondary text-xs" href="#">Ver recorrido del activo</a></p>
                <div class="table-wrap">
                    <table class="data-table asset-evidence-table">
                        <thead><tr><th>Ancla</th><th>RSSI</th><th>Distancia</th><th>Residual</th><th>Calibración</th></tr></thead>
                        <tbody id="asset-detail-evidence"></tbody>
                    </table>
                </div>
            </div>
        </dialog>
        @endif
    @endif
</div>
@endsection
