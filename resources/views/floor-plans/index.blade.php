@extends('layouts.app')

@section('title', 'Planos y zonas')
@section('heading', 'Planos y zonas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/floor-plan-editor.css') }}?v={{ filemtime(public_path('css/floor-plan-editor.css')) }}">
@endpush

@section('content')
    @if(auth()->user()->hasPermission('plans.manage') || $selectedPlan)
        <div class="plan-ribbon floor-plans-primary-ribbon" role="toolbar" aria-label="Acciones y herramientas de planos">
            @if(auth()->user()->hasPermission('plans.manage'))
            <div class="ribbon-group">
                <span class="ribbon-label">Ubicaciones</span>
                <details class="ribbon-command" @if($locations->isEmpty()) open @endif>
                    <summary><x-nav-icon name="organizations"/><span>Nueva ubicación</span></summary>
                    <div class="ribbon-command-panel">
                        <h2>Crear ubicación o piso</h2>
                        <form method="POST" action="{{ route('locations.store') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <label class="field-label">Nombre<input class="field-input" name="name" required></label>
                            <label class="field-label">Tipo<select class="field-input" name="type"><option value="site">Sitio</option><option value="building">Edificio</option><option value="floor" selected>Piso</option></select></label>
                            <label class="field-label sm:col-span-2">Ubicación superior<select class="field-input" name="parent_id"><option value="">Sin superior</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                            <div class="sm:col-span-2"><button class="btn-primary">Crear ubicación</button></div>
                        </form>
                    </div>
                </details>
            </div>
            <div class="ribbon-group">
                <span class="ribbon-label">Planos</span>
                <details class="ribbon-command" @if($plans->isEmpty() && $locations->isNotEmpty()) open @endif>
                    <summary><x-nav-icon name="plans"/><span>Cargar plano</span></summary>
                    <div class="ribbon-command-panel ribbon-command-panel-wide">
                        <h2>Cargar plano</h2>
                        <form method="POST" action="{{ route('floor-plans.store') }}" enctype="multipart/form-data" class="mt-4 grid gap-4 sm:grid-cols-2">
                            @csrf
                            <label class="field-label">Ubicación<select class="field-input" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                            <label class="field-label">Nombre<input class="field-input" name="name" required placeholder="Planta nivel 1"></label>
                            <label class="field-label">Archivo<input class="field-input" type="file" name="plan" accept=".jpg,.jpeg,.png,.webp,.pdf,.dxf" required><span class="mt-1 block text-xs font-normal text-slate-400">PNG, JPG, WEBP, PDF o DXF; máximo 20 MB.</span></label>
                            <label class="field-label">Vista previa opcional<input class="field-input" type="file" name="preview" accept="image/png,image/jpeg,image/webp"><span class="mt-1 block text-xs font-normal text-slate-400">Obligatoria para dibujar sobre PDF o DXF.</span></label>
                            <label class="field-label">Ancho real (metros)<input class="field-input" type="number" name="width_meters" min="0.001" step="0.001" required></label>
                            <label class="field-label">Alto real (metros)<input class="field-input" type="number" name="height_meters" min="0.001" step="0.001" required></label>
                            <div class="sm:col-span-2"><button class="btn-primary" @disabled($locations->isEmpty())>Subir plano</button></div>
                        </form>
                    </div>
                </details>
            </div>
            @endif
            @if($selectedPlan?->drawablePath())
                @if(auth()->user()->hasPermission('plans.manage'))
                <div class="ribbon-group"><span class="ribbon-label">Dibujar</span><button id="zone-mode" class="ribbon-button" type="button"><x-nav-icon name="plans"/><span>Crear área</span></button></div>
                <div class="ribbon-group"><span class="ribbon-label">Dispositivos</span><button id="ribbon-anchor-mode" class="ribbon-button" type="button"><x-nav-icon name="map"/><span>Colocar ancla</span></button></div>
                <div class="ribbon-group"><span class="ribbon-label">Medición</span><a class="ribbon-button" href="{{ route('calibration.index', $selectedPlan) }}"><x-nav-icon name="calibration"/><span>Calibrar RSSI</span></a></div>
                @endif
                <div class="ribbon-group">
                    <span class="ribbon-label">Vista</span>
                    <details class="ribbon-layers">
                        <summary><x-nav-icon name="map"/><span>Visualizar</span></summary>
                        <div class="ribbon-layer-menu">
                            <label><input type="checkbox" data-editor-layer="beacons" checked> Beacons</label>
                            <label><input type="checkbox" data-editor-layer="zones" checked> Zonas</label>
                            <label><input type="checkbox" data-editor-layer="assets" checked> Assets</label>
                        </div>
                    </details>
                </div>
                @if(auth()->user()->hasPermission('plans.manage'))
                <div class="ribbon-hint" id="editor-mode-status">Selecciona una herramienta para editar el plano.</div>
                @endif
            @endif
            @if($selectedPlan)
                @if(auth()->user()->hasPermission('plans.manage'))
                    <div class="ribbon-group">
                        <span class="ribbon-label">Dispositivos</span>
                        <details class="ribbon-command">
                            <summary><x-nav-icon name="assets"/><span>Registrar dispositivo</span></summary>
                            <div class="ribbon-command-panel"><h2>Registrar dispositivo</h2>@include('floor-plans.partials.device-registration')</div>
                        </details>
                    </div>
                @endif
                <div class="ribbon-group">
                    <span class="ribbon-label">Inventario del plano</span>
                    <details class="ribbon-command">
                        <summary><x-nav-icon name="plans"/><span>Zonas ({{ $selectedPlan->zones->count() }})</span></summary>
                        <div class="ribbon-command-panel"><h2>Zonas definidas</h2>@include('floor-plans.partials.zones-panel')</div>
                    </details>
                    <details class="ribbon-command">
                        <summary><x-nav-icon name="calibration"/><span>Anclas ({{ $installations->count() }})</span></summary>
                        <div class="ribbon-command-panel"><h2>Anclas instaladas</h2>@include('floor-plans.partials.anchors-panel')</div>
                    </details>
                </div>
            @endif
        </div>
    @endif

    @if(!$selectedPlan)
        <div class="panel mt-8 empty-state">Carga un plano para comenzar a definir zonas.</div>
    @else
        <div class="floor-plan-workspace mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="panel p-4">
                <div class="plan-editor-overview">
                    <div class="plan-editor-current"><span>Plano actual</span><strong>{{ $selectedPlan->name }}</strong><small>{{ $selectedPlan->location->name }} · {{ $selectedPlan->width_meters }} × {{ $selectedPlan->height_meters }} m</small></div>
                    <div class="plan-editor-ready"><i></i><div><strong>Editor listo</strong><small>Plano activo y disponible</small></div></div>
                    <div class="plan-editor-stats">
                        <div><x-nav-icon name="plans"/><span>Zonas<strong>{{ $selectedPlan->zones->count() }}</strong></span></div>
                        <div><x-nav-icon name="map"/><span>Anclas<strong>{{ $installations->count() }}</strong></span></div>
                        <div><x-nav-icon name="assets"/><span>Assets<strong>{{ $assetPositions->count() }}</strong></span></div>
                    </div>
                </div>
                @if($selectedPlan->drawablePath())
                    <div class="plan-editor-layout">
                        <div class="plan-editor-stage">
                    <div id="zone-editor" class="relative inline-block max-w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-100 select-none" data-width-meters="{{ $selectedPlan->width_meters }}" data-height-meters="{{ $selectedPlan->height_meters }}">
                        <img id="floor-plan-image" class="block max-h-[70vh] max-w-full" src="{{ route('floor-plans.file', $selectedPlan) }}" alt="Plano {{ $selectedPlan->name }}" draggable="false">
                        <div id="saved-zone-overlay" class="absolute inset-0" aria-label="Áreas guardadas">
                            @foreach($selectedPlan->zones as $zone)
                                <div class="saved-zone" style="left: {{ (float) $zone->x_min * 100 }}%; top: {{ (float) $zone->y_min * 100 }}%; width: {{ ((float) $zone->x_max - (float) $zone->x_min) * 100 }}%; height: {{ ((float) $zone->y_max - (float) $zone->y_min) * 100 }}%; border-color: {{ $zone->color }}; background-color: {{ $zone->color }}33"><span style="background-color: {{ $zone->color }}">{{ $zone->name }}</span></div>
                            @endforeach
                        </div>
                        <div id="saved-anchor-overlay" class="absolute inset-0 pointer-events-none" aria-label="Beacons instalados">
                            @foreach($installations as $installation)
                                <span class="plan-anchor" style="left: {{ min(100, max(0, (float) $installation->x / (float) $selectedPlan->width_meters * 100)) }}%; top: {{ min(100, max(0, (float) $installation->y / (float) $selectedPlan->height_meters * 100)) }}%" title="{{ $installation->device->name }} · {{ $installation->device->identifier }}"><x-spatial-marker-icon :type="$installation->device->type === 'scanner' ? 'scanner' : 'anchor'"/><small class="sr-only">{{ $installation->device->name }}</small></span>
                            @endforeach
                        </div>
                        <div id="saved-asset-overlay" class="absolute inset-0 pointer-events-none" aria-label="Assets posicionados">
                            @foreach($assetPositions as $position)
                                <span class="plan-asset" style="left: {{ min(100, max(0, (float) $position->x / (float) $selectedPlan->width_meters * 100)) }}%; top: {{ min(100, max(0, (float) $position->y / (float) $selectedPlan->height_meters * 100)) }}%" title="{{ $position->asset->name }}"><x-spatial-marker-icon type="asset"/><small class="sr-only">{{ $position->asset->name }}</small></span>
                            @endforeach
                        </div>
                        <canvas id="zone-canvas" class="absolute inset-0 h-full w-full touch-none"></canvas>
                    </div>
                    <script id="zone-data" type="application/json">{{ Illuminate\Support\Js::encode($selectedPlan->zones->map(fn($zone) => ['name' => $zone->name, 'color' => $zone->color, 'x_min' => (float) $zone->x_min, 'y_min' => (float) $zone->y_min, 'x_max' => (float) $zone->x_max, 'y_max' => (float) $zone->y_max])->values()) }}</script>
                    <script id="installation-data" type="application/json">{{ Illuminate\Support\Js::encode($installations->map(fn($installation) => ['name' => $installation->device->name, 'type' => $installation->device->type, 'x' => (float) $installation->x / (float) $selectedPlan->width_meters, 'y' => (float) $installation->y / (float) $selectedPlan->height_meters])->values()) }}</script>
                        </div>
                    </div>
                @else
                    <div class="empty-state rounded-xl bg-slate-50">Este {{ strtoupper(pathinfo($selectedPlan->original_name, PATHINFO_EXTENSION)) }} no tiene vista previa raster. Vuelve a cargarlo con una imagen PNG/JPG/WEBP para habilitar el editor.</div>
                @endif
                @if($plans->isNotEmpty())
                    <nav class="plan-sheet-tabs" aria-label="Seleccionar plano">
                        @foreach($plans as $plan)
                            <a class="plan-sheet-tab {{ $selectedPlan->is($plan) ? 'is-active' : '' }}" href="{{ route('floor-plans.index', ['plan' => $plan]) }}" title="{{ $plan->location->name }} · {{ $plan->name }}" data-plan-name="{{ $plan->name }}" @if($plan->tab_color) style="--sheet-color: {{ $plan->tab_color }}" @endif @if(auth()->user()->hasPermission('plans.manage')) data-tab-color="{{ $plan->tab_color }}" data-update-url="{{ route('floor-plans.update', $plan) }}" data-delete-url="{{ route('floor-plans.destroy', $plan) }}" data-calibration-url="{{ route('calibration.index', $plan) }}" @endif @if($selectedPlan->is($plan)) aria-current="page" @endif>
                                <span>{{ $plan->name }}</span><small>{{ $plan->location->name }}</small>
                            </a>
                        @endforeach
                    </nav>
                @endif
            </section>

            <aside class="editor-sidebar space-y-6">
                @if(auth()->user()->hasPermission('plans.manage') && $selectedPlan->drawablePath())
                    <form id="zone-form" method="POST" action="{{ route('zones.store', $selectedPlan) }}" class="panel editor-properties-panel p-5" hidden>
                        @csrf
                        <p class="editor-properties-label">Propiedades</p>
                        <h2 class="font-semibold text-slate-950">Nueva zona rectangular</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Arrastra sobre el plano como en una selección CAD, asigna un nombre y guarda.</p>
                        <div class="mt-5 space-y-4">
                            <label class="field-label">Nombre<input class="field-input" name="name" required placeholder="Bodega Z"></label>
                            <label class="field-label">Código<input class="field-input" name="code" placeholder="ZONE-Z"></label>
                            <label class="field-label">Color<input class="mt-2 h-11 w-full rounded-xl border border-slate-200 p-1" type="color" name="color" value="#14B8A6"></label>
                            <fieldset class="rounded-xl border border-slate-200 p-3"><legend class="px-1 text-xs font-semibold text-slate-600">Notificaciones opcionales</legend><div class="space-y-2 text-sm"><label class="flex gap-2"><input type="checkbox" name="alert_types[]" value="entry"> Cuando ingresa</label><label class="flex gap-2"><input type="checkbox" name="alert_types[]" value="exit"> Cuando sale</label><label class="flex gap-2"><input type="checkbox" name="alert_types[]" value="dwell"> Cuando permanece demasiado</label></div><label class="field-label mt-3">Tiempo de permanencia (minutos)<input class="field-input" type="number" name="dwell_minutes" min="10" max="10080" value="30"></label><label class="field-label mt-3">Correos destinatarios<textarea class="field-input" name="alert_recipients" rows="2" placeholder="operaciones@empresa.com, seguridad@empresa.com"></textarea><span class="mt-1 block text-xs font-normal text-slate-400">Opcional. Si queda vacío se usarán los destinatarios globales de Alertas.</span></label></fieldset>
                            @foreach(['x_min', 'y_min', 'x_max', 'y_max'] as $coordinate)<input id="zone-{{ str_replace('_', '-', $coordinate) }}" type="hidden" name="{{ $coordinate }}" required>@endforeach
                            <dl id="zone-geometry-metrics" class="zone-geometry-metrics" hidden><div><dt>Área</dt><dd data-zone-area></dd></div><div><dt>Perímetro</dt><dd data-zone-perimeter></dd></div></dl>
                            <p id="zone-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Dibuja el rectángulo en el plano.</p>
                            <button id="zone-submit" class="btn-primary w-full" disabled>Guardar zona</button>
                        </div>
                    </form>

                    <form id="anchor-form" method="POST" action="{{ route('installations.store', $selectedPlan) }}" class="panel editor-properties-panel p-5" hidden>
                        @csrf
                        <p class="editor-properties-label">Propiedades</p>
                        <h2 class="font-semibold text-slate-950">Colocar ancla</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Activos móviles: coloca beacons fijos. Activos estáticos con beacon: coloca scanners fijos.</p>
                        <div class="mt-5 space-y-4">
                            <label class="field-label">Dispositivo registrado<select class="field-input" name="device_id"><option value="">Crear o seleccionar beacon por MAC</option>@foreach($devices->whereIn('type', ['beacon', 'scanner']) as $device)<option value="{{ $device->id }}">{{ $device->name }} · {{ $device->identifier }}</option>@endforeach</select></label>
                            <label class="field-label">MAC del beacon<input class="field-input font-mono" name="device_identifier" list="reported-beacon-macs" placeholder="58:BE:6F:65:9D:9D"><span class="mt-1 block text-xs font-normal text-slate-400">Escríbela manualmente o selecciónala entre las MAC observadas por trackers TTI.</span></label>
                            <datalist id="reported-beacon-macs">@foreach($reportedBeaconMacs as $reported)<option value="{{ $reported['identifier'] }}">{{ $reported['tracker_name'] }} · RSSI {{ $reported['rssi'] }} dBm{{ $reported['connector_name'] ? ' · '.$reported['connector_name'] : '' }}</option>@endforeach</datalist>
                            <label class="field-label">Nombre del nuevo beacon<input class="field-input" name="device_name" placeholder="Opcional; por ejemplo Beacon acceso norte"></label>
                            <div class="grid grid-cols-2 gap-3"><label class="field-label">RSSI a 1 m<input class="field-input" type="number" name="reference_rssi" value="-59" min="-127" max="-1" required></label><label class="field-label">Factor ambiental<input class="field-input" type="number" name="path_loss_exponent" value="2.0" min="0.5" max="8" step="0.1" required></label></div>
                            <input id="anchor-x" type="hidden" name="x_normalized" required><input id="anchor-y" type="hidden" name="y_normalized" required>
                            <button id="anchor-mode" class="btn-secondary w-full" type="button">Seleccionar otro punto</button>
                            <p id="anchor-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Primero activa “Seleccionar punto”.</p>
                            <button id="anchor-submit" class="btn-primary w-full" disabled>Guardar instalación</button>
                        </div>
                    </form>
                @endif

            </aside>
        </div>
        @if(auth()->user()->hasPermission('plans.manage'))
            <div id="plan-sheet-context-menu" class="plan-sheet-context-menu" role="menu" hidden>
                <button type="button" role="menuitem" data-sheet-action="open">Abrir hoja</button>
                <button type="button" role="menuitem" data-sheet-action="rename">Cambiar nombre…</button>
                <button type="button" role="menuitem" data-sheet-action="color">Color de pestaña…</button>
                <button type="button" role="menuitem" data-sheet-action="calibrate">Calibrar RSSI</button>
                <hr>
                <button class="is-danger" type="button" role="menuitem" data-sheet-action="delete">Eliminar hoja…</button>
            </div>
            <dialog id="plan-rename-dialog" class="plan-rename-dialog" aria-labelledby="plan-rename-title">
                <form id="plan-rename-form" method="POST">
                    @csrf @method('PUT')
                    <h2 id="plan-rename-title">Cambiar nombre de la hoja</h2>
                    <label class="field-label mt-4">Nombre<input id="plan-rename-input" class="field-input" name="name" maxlength="255" required></label>
                    <div class="mt-5 flex justify-end gap-2"><button class="btn-secondary" type="button" data-close-rename>Cancelar</button><button class="btn-primary">Guardar</button></div>
                </form>
            </dialog>
            <dialog id="plan-color-dialog" class="plan-rename-dialog" aria-labelledby="plan-color-title">
                <form id="plan-color-form" method="POST">
                    @csrf @method('PUT')
                    <h2 id="plan-color-title">Color de la pestaña</h2>
                    <input id="plan-color-value" type="hidden" name="tab_color">
                    <label class="field-label mt-4">Color<input id="plan-color-input" class="mt-2 h-11 w-full rounded border border-slate-200 p-1" type="color" value="#14b8a6"></label>
                    <div class="mt-5 flex flex-wrap justify-end gap-2"><button class="btn-secondary" type="button" data-reset-tab-color>Sin color</button><button class="btn-secondary" type="button" data-close-color>Cancelar</button><button class="btn-primary">Guardar</button></div>
                </form>
            </dialog>
            <form id="plan-sheet-delete-form" method="POST" hidden>@csrf @method('DELETE')</form>
        @endif
    @endif
@endsection
