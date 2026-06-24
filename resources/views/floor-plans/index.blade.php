@extends('layouts.app')

@section('title', 'Planos y zonas')
@section('heading', 'Planos y zonas')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/floor-plan-editor.css') }}?v={{ filemtime(public_path('css/floor-plan-editor.css')) }}">
@endpush
@push('scripts')
    <script defer src="{{ asset('js/floor-plan-navigation.js') }}?v={{ filemtime(public_path('js/floor-plan-navigation.js')) }}"></script>
    @if($selectedPlan?->isThreeDimensional())
        <script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.184.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.184.0/examples/jsm/"}}</script>
        <script type="module" src="{{ asset('js/floor-plan-3d.js') }}?v={{ filemtime(public_path('js/floor-plan-3d.js')) }}"></script>
    @endif
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
                <details class="ribbon-command" @if(($plans->isEmpty() && $locations->isNotEmpty()) || $errors->hasAny(['location_id', 'name', 'view_mode', 'plan', 'preview', 'width_meters', 'height_meters', 'depth_meters', 'model_scale', 'model_rotation_y', 'model_offset_x', 'model_offset_y', 'model_offset_z'])) open @endif>
                    <summary><x-nav-icon name="plans"/><span>Cargar plano</span></summary>
                    <div class="ribbon-command-panel ribbon-command-panel-wide">
                        <h2>Cargar plano</h2>
                        <form method="POST" action="{{ route('floor-plans.store') }}" enctype="multipart/form-data" class="floor-plan-upload-form mt-4 grid gap-4 sm:grid-cols-2" data-floor-plan-upload-form>
                            @csrf
                            <fieldset class="floor-plan-type-picker sm:col-span-2">
                                <legend>Tipo de plano</legend>
                                <label>
                                    <input type="radio" name="view_mode" value="2d" data-floor-plan-mode @checked(old('view_mode', '2d') === '2d')>
                                    <span><strong>Plano 2D</strong><small>Imagen, PDF o DXF con zoom y desplazamiento.</small></span>
                                </label>
                                <label>
                                    <input type="radio" name="view_mode" value="3d" data-floor-plan-mode @checked(old('view_mode') === '3d')>
                                    <span><strong>Modelo 3D</strong><small>GLB o glTF autocontenido con navegación orbital.</small></span>
                                </label>
                            </fieldset>
                            <label class="field-label">Ubicación<select class="field-input" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id') === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
                            <label class="field-label">Nombre<input class="field-input" name="name" value="{{ old('name') }}" required placeholder="Bodega principal"></label>
                            <label class="field-label sm:col-span-2">Archivo del plano
                                <input class="field-input" type="file" name="plan" accept=".jpg,.jpeg,.png,.webp,.pdf,.dxf,.glb,.gltf,model/gltf-binary,model/gltf+json" data-floor-plan-file required>
                                <span class="floor-plan-file-status" data-floor-plan-file-status>Ningún archivo seleccionado.</span>
                                <span class="mt-1 block text-xs font-normal text-slate-400" data-floor-plan-file-help>PNG, JPG, WEBP, PDF o DXF; máximo 20 MB.</span>
                            </label>
                            <label class="field-label">Vista previa opcional<input class="field-input" type="file" name="preview" accept="image/png,image/jpeg,image/webp"><span class="mt-1 block text-xs font-normal text-slate-400">Permite editar zonas en 2D sobre PDF, DXF o modelos 3D.</span></label>
                            <label class="field-label">Ancho real (metros)<input class="field-input" type="number" name="width_meters" value="{{ old('width_meters') }}" min="0.001" step="0.001" required></label>
                            <label class="field-label">Largo real (metros)<input class="field-input" type="number" name="height_meters" value="{{ old('height_meters') }}" min="0.001" step="0.001" required></label>
                            <div class="sm:col-span-2" data-floor-plan-3d-fields hidden>
                                <label class="field-label">Altura máxima del modelo (metros, opcional)<input class="field-input" type="number" name="depth_meters" min="0.001" step="0.001" value="{{ old('depth_meters') }}" data-floor-plan-depth placeholder="Se calculará automáticamente"></label>
                                <details class="floor-plan-advanced-settings mt-4">
                                    <summary>Ajustes avanzados de orientación y escala</summary>
                                    <div class="mt-3 grid gap-4 sm:grid-cols-2">
                                        <label class="field-label">Escala manual<input class="field-input" type="number" name="model_scale" value="{{ old('model_scale') }}" min="0.0001" step="0.0001" placeholder="Automática"><span class="mt-1 block text-xs font-normal text-slate-400">Déjala vacía salvo que el modelo haya sido exportado con unidades incorrectas.</span></label>
                                        <label class="field-label">Rotación vertical (grados)<input class="field-input" type="number" name="model_rotation_y" min="-360" max="360" step="0.1" value="{{ old('model_rotation_y', 0) }}"></label>
                                        <label class="field-label sm:col-span-2">Desplazamiento X / Y / Z (m)<span class="grid grid-cols-3 gap-2"><input class="field-input" type="number" name="model_offset_x" step="0.001" value="{{ old('model_offset_x', 0) }}" aria-label="Desplazamiento X"><input class="field-input" type="number" name="model_offset_y" step="0.001" value="{{ old('model_offset_y', 0) }}" aria-label="Desplazamiento Y"><input class="field-input" type="number" name="model_offset_z" step="0.001" value="{{ old('model_offset_z', 0) }}" aria-label="Desplazamiento Z"></span></label>
                                    </div>
                                </details>
                            </div>
                            <div class="floor-plan-upload-summary sm:col-span-2" data-floor-plan-upload-summary>
                                <strong>Plano 2D</strong>
                                <span>Selecciona un archivo y completa sus dimensiones reales.</span>
                            </div>
                            <div class="sm:col-span-2"><button class="btn-primary" type="submit" @disabled($locations->isEmpty())>Cargar plano</button></div>
                        </form>
                    </div>
                </details>
            </div>
            @endif
            @if($selectedPlan?->drawablePath())
                @if(auth()->user()->hasPermission('plans.manage'))
                <div class="ribbon-group"><span class="ribbon-label">Dibujar</span><details id="zone-command" class="ribbon-command"><summary id="zone-mode"><x-nav-icon name="plans"/><span>Crear área</span></summary><div class="ribbon-command-panel ribbon-command-panel-wide"><h2>Crear área</h2>@include('floor-plans.partials.zone-creation-form')</div></details></div>
                <div class="ribbon-group"><span class="ribbon-label">Dispositivos</span><details id="anchor-command" class="ribbon-command"><summary id="ribbon-anchor-mode"><x-nav-icon name="map"/><span>Colocar ancla</span></summary><div class="ribbon-command-panel ribbon-command-panel-wide"><h2>Colocar ancla</h2>@include('floor-plans.partials.anchor-placement-form')</div></details></div>
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
        <div class="floor-plan-workspace mt-6">
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
                @if($selectedPlan->isThreeDimensional())
                    <div class="plan-editor-layout">
                        <div class="plan-editor-stage">
                            <div class="plan-viewer-toolbar" role="toolbar" aria-label="Navegación del modelo 3D">
                                <span class="plan-viewer-badge">Vista 3D</span>
                                <button type="button" data-3d-view="home">Restablecer</button>
                                <button type="button" data-3d-view="top">Vista superior</button>
                                <span class="plan-viewer-help">Arrastra para rotar · botón derecho para mover · rueda para zoom</span>
                            </div>
                            <div id="floor-plan-3d"
                                class="floor-plan-3d"
                                data-model-url="{{ route('floor-plans.model', $selectedPlan) }}"
                                data-width-meters="{{ $selectedPlan->width_meters }}"
                                data-height-meters="{{ $selectedPlan->height_meters }}"
                                data-depth-meters="{{ $selectedPlan->depth_meters }}"
                                data-transform='@json($selectedPlan->model_transform ?? [])'
                                aria-label="Modelo 3D navegable de {{ $selectedPlan->name }}">
                                <div class="floor-plan-3d-status" data-3d-status>Cargando modelo 3D…</div>
                            </div>
                            <script id="floor-plan-3d-markers" type="application/json">{!! Illuminate\Support\Js::encode([
                                ...$installations->map(fn($installation) => ['kind' => 'anchor', 'name' => $installation->device->name, 'x' => (float) $installation->x, 'y' => (float) $installation->y, 'z' => (float) ($installation->z ?? 0.35)]),
                                ...$assetPositions->map(fn($position) => ['kind' => 'asset', 'name' => $position->asset->name, 'x' => (float) $position->x, 'y' => (float) $position->y, 'z' => (float) ($position->z ?? 0.65)]),
                            ]) !!}</script>
                        </div>
                    </div>
                    @if($selectedPlan->drawablePath())
                        <details class="plan-2d-companion">
                            <summary>Editar zonas y anclas sobre la vista 2D</summary>
                    @endif
                @endif
                @if($selectedPlan->drawablePath())
                    <div class="plan-editor-layout">
                        <div class="plan-editor-stage">
                    <div class="plan-viewer-toolbar" role="toolbar" aria-label="Navegación del plano 2D">
                        <span class="plan-viewer-badge">Vista 2D</span>
                        <button type="button" data-plan-pan aria-pressed="false">Mover</button>
                        <button type="button" data-plan-zoom="out" aria-label="Alejar">−</button>
                        <output data-plan-zoom-value>100%</output>
                        <button type="button" data-plan-zoom="in" aria-label="Acercar">+</button>
                        <button type="button" data-plan-zoom="reset">Ajustar</button>
                        <span class="plan-viewer-help">Rueda para zoom · activa Mover para desplazar</span>
                    </div>
                    <div id="plan-2d-viewport" class="plan-2d-viewport">
                    <div id="zone-editor" class="relative inline-block max-w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-100 select-none" data-width-meters="{{ $selectedPlan->width_meters }}" data-height-meters="{{ $selectedPlan->height_meters }}">
                        <img id="floor-plan-image" class="block max-h-[70vh] max-w-full" src="{{ route('floor-plans.file', $selectedPlan) }}" alt="Plano {{ $selectedPlan->name }}" draggable="false">
                        <div id="saved-zone-overlay" class="absolute inset-0" aria-label="Áreas guardadas">
                            @foreach($selectedPlan->zones as $zone)
                                <div class="saved-zone" style="left: {{ (float) $zone->x_min * 100 }}%; top: {{ (float) $zone->y_min * 100 }}%; width: {{ ((float) $zone->x_max - (float) $zone->x_min) * 100 }}%; height: {{ ((float) $zone->y_max - (float) $zone->y_min) * 100 }}%; border-color: {{ $zone->color }}; background-color: {{ $zone->color }}33"><span style="background-color: {{ $zone->color }}">{{ $zone->name }}</span></div>
                            @endforeach
                        </div>
                        <div id="saved-anchor-overlay" class="absolute inset-0 pointer-events-none" aria-label="Beacons instalados">
                            @foreach($installations as $installation)
                                @if(auth()->user()->hasPermission('plans.manage'))
                                    @php($anchorXPercent = min(100, max(0, (float) $installation->x / (float) $selectedPlan->width_meters * 100)))
                                    @php($anchorYPercent = min(100, max(0, (float) $installation->y / (float) $selectedPlan->height_meters * 100)))
                                    <details class="plan-anchor" style="left: {{ $anchorXPercent }}%; top: {{ $anchorYPercent }}%" data-anchor-details data-popup-horizontal="{{ $anchorXPercent > 62 ? 'left' : 'right' }}" data-popup-vertical="{{ $anchorYPercent > 55 ? 'up' : 'down' }}">
                                        <summary title="Editar {{ $installation->device->name }}" aria-label="Editar {{ $installation->device->name }}"><i aria-hidden="true"></i></summary>
                                        <div class="anchor-inline-popup" role="dialog" aria-label="Parámetros de {{ $installation->device->name }}">
                                            <div class="anchor-context-header"><div><p class="anchor-context-kicker">Beacon instalado</p><h2>{{ $installation->device->name }}</h2><p>{{ $installation->device->type }} · {{ $installation->device->identifier }}</p></div></div>
                                            <form method="POST" action="{{ route('installations.update', $installation) }}" class="anchor-context-body" data-anchor-edit-form data-installation-id="{{ $installation->id }}">
                                                @csrf @method('PUT')
                                                <label class="field-label">Nombre<input class="field-input" name="name" value="{{ $installation->device->name }}" maxlength="255" required></label>
                                                <div class="grid grid-cols-2 gap-3"><label class="field-label">Posición X (m)<input class="field-input" type="number" name="x_meters" value="{{ $installation->x }}" min="0" max="{{ $selectedPlan->width_meters }}" step="0.001" required></label><label class="field-label">Posición Y (m)<input class="field-input" type="number" name="y_meters" value="{{ $installation->y }}" min="0" max="{{ $selectedPlan->height_meters }}" step="0.001" required></label></div>
                                                <div class="anchor-context-position-actions"><p>Puedes escribir las coordenadas en metros o seleccionar el punto directamente sobre el plano.</p><button class="btn-secondary" type="button" data-anchor-reposition>Elegir en plano</button></div>
                                                <div class="grid grid-cols-2 gap-3"><label class="field-label">RSSI a 1 m<input class="field-input" type="number" name="reference_rssi" value="{{ $installation->reference_rssi }}" min="-127" max="-1" required></label><label class="field-label">Factor ambiental<input class="field-input" type="number" name="path_loss_exponent" value="{{ $installation->path_loss_exponent }}" min="0.5" max="8" step="0.01" required></label></div>
                                                <div class="anchor-context-actions"><button class="btn-primary" type="submit">Guardar cambios</button></div>
                                            </form>
                                            <form method="POST" action="{{ route('installations.destroy', $installation) }}" class="anchor-context-danger" onsubmit="return confirm('¿Quitar este beacon del plano? Se conservará su historial de instalación.')">
                                                @csrf @method('DELETE')
                                                <p>Quitar cierra esta instalación sin borrar el dispositivo ni su historial.</p><button type="submit">Quitar del plano</button>
                                            </form>
                                        </div>
                                    </details>
                                @else
                                    <span class="plan-anchor" style="left: {{ min(100, max(0, (float) $installation->x / (float) $selectedPlan->width_meters * 100)) }}%; top: {{ min(100, max(0, (float) $installation->y / (float) $selectedPlan->height_meters * 100)) }}%" title="{{ $installation->device->name }} · {{ $installation->device->identifier }}"><i aria-hidden="true"></i><small class="sr-only">{{ $installation->device->name }}</small></span>
                                @endif
                            @endforeach
                        </div>
                        <div id="saved-asset-overlay" class="absolute inset-0 pointer-events-none" aria-label="Assets posicionados">
                            @foreach($assetPositions as $position)
                                <span class="plan-asset" style="left: {{ min(100, max(0, (float) $position->x / (float) $selectedPlan->width_meters * 100)) }}%; top: {{ min(100, max(0, (float) $position->y / (float) $selectedPlan->height_meters * 100)) }}%" title="{{ $position->asset->name }}"><x-spatial-marker-icon type="asset"/><small class="sr-only">{{ $position->asset->name }}</small></span>
                            @endforeach
                        </div>
                        <canvas id="zone-canvas" class="absolute inset-0 h-full w-full touch-none"></canvas>
                    </div>
                    </div>
                    <script id="zone-data" type="application/json">{!! Illuminate\Support\Js::encode($selectedPlan->zones->map(fn($zone) => ['id' => $zone->id, 'name' => $zone->name, 'color' => $zone->color, 'x_min' => (float) $zone->x_min, 'y_min' => (float) $zone->y_min, 'x_max' => (float) $zone->x_max, 'y_max' => (float) $zone->y_max])->values()) !!}</script>
                    <script id="installation-data" type="application/json">{!! Illuminate\Support\Js::encode($installations->map(fn($installation) => ['id' => $installation->id, 'name' => $installation->device->name, 'type' => $installation->device->type, 'x' => (float) $installation->x / (float) $selectedPlan->width_meters, 'y' => (float) $installation->y / (float) $selectedPlan->height_meters])->values()) !!}</script>
                        </div>
                    </div>
                    @if($selectedPlan->isThreeDimensional())
                        </details>
                    @endif
                @else
                    @unless($selectedPlan->isThreeDimensional())
                        <div class="empty-state rounded-xl bg-slate-50">Este {{ strtoupper(pathinfo($selectedPlan->original_name, PATHINFO_EXTENSION)) }} no tiene vista previa raster. Vuelve a cargarlo con una imagen PNG/JPG/WEBP para habilitar el editor.</div>
                    @endunless
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

        </div>
        @if(auth()->user()->hasPermission('plans.manage'))
            <div id="plan-sheet-context-menu" class="plan-sheet-context-menu" role="menu" popover="manual" hidden>
                <button type="button" role="menuitem" data-sheet-action="open">Abrir hoja</button>
                <button type="button" role="menuitem" data-sheet-action="rename">Cambiar nombre…</button>
                <button type="button" role="menuitem" data-sheet-action="color">Color de pestaña…</button>
                <button type="button" role="menuitem" data-sheet-action="calibrate">Calibrar RSSI</button>
                <hr>
                <button class="is-danger" type="button" role="menuitem" data-sheet-action="delete">Eliminar hoja…</button>
            </div>
            <dialog id="plan-rename-dialog" class="plan-rename-dialog plan-sheet-dialog" aria-labelledby="plan-rename-title">
                <form id="plan-rename-form" method="POST" class="plan-sheet-dialog-form">
                    @csrf @method('PUT')
                    <header class="plan-sheet-dialog-header"><div><p>Propiedades de la hoja</p><h2 id="plan-rename-title">Cambiar nombre</h2></div><button type="button" class="plan-sheet-dialog-close" data-close-rename aria-label="Cerrar">&times;</button></header>
                    <div class="plan-sheet-dialog-body"><label class="field-label">Nombre de la hoja<input id="plan-rename-input" class="field-input" name="name" maxlength="255" autocomplete="off" required></label><p class="plan-sheet-dialog-help">El nombre aparecerá en la pestaña inferior del plano.</p></div>
                    <footer class="plan-sheet-dialog-footer"><button class="btn-secondary" type="button" data-close-rename>Cancelar</button><button class="btn-primary">Guardar</button></footer>
                </form>
            </dialog>
            <dialog id="plan-color-dialog" class="plan-rename-dialog plan-sheet-dialog" aria-labelledby="plan-color-title">
                <form id="plan-color-form" method="POST" class="plan-sheet-dialog-form">
                    @csrf @method('PUT')
                    <header class="plan-sheet-dialog-header"><div><p>Propiedades de la hoja</p><h2 id="plan-color-title">Color de pestaña</h2></div><button type="button" class="plan-sheet-dialog-close" data-close-color aria-label="Cerrar">&times;</button></header>
                    <input id="plan-color-value" type="hidden" name="tab_color">
                    <div class="plan-sheet-dialog-body"><label class="field-label">Color<input id="plan-color-input" class="plan-sheet-color-input" type="color" value="#14b8a6"></label><p class="plan-sheet-dialog-help">Se aplicará como indicador visual en la pestaña inferior.</p></div>
                    <footer class="plan-sheet-dialog-footer"><button class="btn-secondary plan-sheet-dialog-reset" type="button" data-reset-tab-color>Sin color</button><button class="btn-secondary" type="button" data-close-color>Cancelar</button><button class="btn-primary">Guardar</button></footer>
                </form>
            </dialog>
            <form id="plan-sheet-delete-form" method="POST" hidden>@csrf @method('DELETE')</form>
        @endif
    @endif
@endsection
