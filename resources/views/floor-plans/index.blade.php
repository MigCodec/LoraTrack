@extends('layouts.app')

@section('title', 'Planos y zonas')
@section('heading', 'Planos y zonas')

@section('content')
    @if(auth()->user()->hasPermission('plans.manage'))
        <div class="grid gap-6 xl:grid-cols-2">
            <details class="panel p-5" @if($locations->isEmpty()) open @endif>
                <summary class="cursor-pointer font-semibold text-slate-950">Crear ubicación o piso</summary>
                <form method="POST" action="{{ route('locations.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <label class="field-label">Nombre<input class="field-input" name="name" required></label>
                    <label class="field-label">Tipo<select class="field-input" name="type"><option value="site">Sitio</option><option value="building">Edificio</option><option value="floor" selected>Piso</option></select></label>
                    <label class="field-label sm:col-span-2">Ubicación superior<select class="field-input" name="parent_id"><option value="">Sin superior</option>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <div class="sm:col-span-2"><button class="btn-primary">Crear ubicación</button></div>
                </form>
            </details>

            <details class="panel p-5" @if($plans->isEmpty() && $locations->isNotEmpty()) open @endif>
                <summary class="cursor-pointer font-semibold text-slate-950">Cargar plano</summary>
                <form method="POST" action="{{ route('floor-plans.store') }}" enctype="multipart/form-data" class="mt-5 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <label class="field-label">Ubicación<select class="field-input" name="location_id" required>@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></label>
                    <label class="field-label">Nombre<input class="field-input" name="name" required placeholder="Planta nivel 1"></label>
                    <label class="field-label">Archivo<input class="field-input" type="file" name="plan" accept=".jpg,.jpeg,.png,.webp,.pdf,.dxf" required><span class="mt-1 block text-xs font-normal text-slate-400">PNG, JPG, WEBP, PDF o DXF; máximo 20 MB.</span></label>
                    <label class="field-label">Vista previa opcional<input class="field-input" type="file" name="preview" accept="image/png,image/jpeg,image/webp"><span class="mt-1 block text-xs font-normal text-slate-400">Obligatoria para dibujar sobre PDF o DXF.</span></label>
                    <label class="field-label">Ancho real (metros)<input class="field-input" type="number" name="width_meters" min="0.001" step="0.001" required></label>
                    <label class="field-label">Alto real (metros)<input class="field-input" type="number" name="height_meters" min="0.001" step="0.001" required></label>
                    <div class="sm:col-span-2"><button class="btn-primary" @disabled($locations->isEmpty())>Subir plano</button></div>
                </form>
            </details>
        </div>
    @endif

    @if($plans->isNotEmpty())
        <div class="mt-8 flex gap-2 overflow-x-auto pb-2">
            @foreach($plans as $plan)<a class="{{ $selectedPlan?->is($plan) ? 'btn-primary' : 'btn-secondary' }} whitespace-nowrap" href="{{ route('floor-plans.index', ['plan' => $plan]) }}">{{ $plan->name }}</a>@endforeach
        </div>
    @endif

    @if(!$selectedPlan)
        <div class="panel mt-8 empty-state">Carga un plano para comenzar a definir zonas.</div>
    @else
        <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
            <section class="panel p-4">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div><h2 class="font-semibold text-slate-950">{{ $selectedPlan->name }}</h2><p class="mt-1 text-xs text-slate-500">{{ $selectedPlan->location->name }} · {{ $selectedPlan->width_meters }} × {{ $selectedPlan->height_meters }} m</p>@if(auth()->user()->hasPermission('plans.manage'))<a class="action-link mt-2 inline-block" href="{{ route('calibration.index', $selectedPlan) }}">Abrir banco de calibración</a>@endif</div>
                    @if(auth()->user()->hasPermission('plans.manage'))<form method="POST" action="{{ route('floor-plans.destroy', $selectedPlan) }}" onsubmit="return confirm('¿Eliminar el plano y sus zonas?')">@csrf @method('DELETE')<button class="text-xs font-semibold text-red-600">Eliminar plano</button></form>@endif
                </div>

                @if(auth()->user()->hasPermission('plans.manage') && $selectedPlan->drawablePath())
                    <div class="plan-ribbon mb-4" role="toolbar" aria-label="Herramientas del plano">
                        <div class="ribbon-group"><span class="ribbon-label">Dibujar</span><button id="zone-mode" class="ribbon-button" type="button"><x-nav-icon name="plans"/><span>Crear área</span></button></div>
                        <div class="ribbon-group"><span class="ribbon-label">Dispositivos</span><button id="ribbon-anchor-mode" class="ribbon-button" type="button"><x-nav-icon name="map"/><span>Colocar ancla</span></button></div>
                        <div class="ribbon-group"><span class="ribbon-label">Medición</span><a class="ribbon-button" href="{{ route('calibration.index', $selectedPlan) }}"><x-nav-icon name="calibration"/><span>Calibrar RSSI</span></a></div>
                        <div class="ribbon-hint" id="editor-mode-status">Selecciona una herramienta para editar el plano.</div>
                    </div>
                @endif

                @if($selectedPlan->drawablePath())
                    <div id="zone-editor" class="relative inline-block max-w-full overflow-hidden rounded-xl border border-slate-300 bg-slate-100 select-none">
                        <img id="floor-plan-image" class="block max-h-[70vh] max-w-full" src="{{ route('floor-plans.file', $selectedPlan) }}" alt="Plano {{ $selectedPlan->name }}" draggable="false">
                        <div id="saved-zone-overlay" class="absolute inset-0" aria-label="Áreas guardadas">
                            @foreach($selectedPlan->zones as $zone)
                                <div class="saved-zone" style="left: {{ (float) $zone->x_min * 100 }}%; top: {{ (float) $zone->y_min * 100 }}%; width: {{ ((float) $zone->x_max - (float) $zone->x_min) * 100 }}%; height: {{ ((float) $zone->y_max - (float) $zone->y_min) * 100 }}%; border-color: {{ $zone->color }}; background-color: {{ $zone->color }}33"><span style="background-color: {{ $zone->color }}">{{ $zone->name }}</span></div>
                            @endforeach
                        </div>
                        <canvas id="zone-canvas" class="absolute inset-0 h-full w-full touch-none"></canvas>
                    </div>
                    <script id="zone-data" type="application/json">{{ Illuminate\Support\Js::encode($selectedPlan->zones->map(fn($zone) => ['name' => $zone->name, 'color' => $zone->color, 'x_min' => (float) $zone->x_min, 'y_min' => (float) $zone->y_min, 'x_max' => (float) $zone->x_max, 'y_max' => (float) $zone->y_max])->values()) }}</script>
                    <script id="installation-data" type="application/json">{{ Illuminate\Support\Js::encode($installations->map(fn($installation) => ['name' => $installation->device->name, 'type' => $installation->device->type, 'x' => (float) $installation->x / (float) $selectedPlan->width_meters, 'y' => (float) $installation->y / (float) $selectedPlan->height_meters])->values()) }}</script>
                @else
                    <div class="empty-state rounded-xl bg-slate-50">Este {{ strtoupper(pathinfo($selectedPlan->original_name, PATHINFO_EXTENSION)) }} no tiene vista previa raster. Vuelve a cargarlo con una imagen PNG/JPG/WEBP para habilitar el editor.</div>
                @endif
            </section>

            <aside class="space-y-6">
                @if(auth()->user()->hasPermission('plans.manage') && $selectedPlan->drawablePath())
                    <form id="zone-form" method="POST" action="{{ route('zones.store', $selectedPlan) }}" class="panel p-5" hidden>
                        @csrf
                        <h2 class="font-semibold text-slate-950">Nueva zona rectangular</h2>
                        <p class="mt-1 text-xs leading-relaxed text-slate-500">Arrastra sobre el plano como en una selección CAD, asigna un nombre y guarda.</p>
                        <div class="mt-5 space-y-4">
                            <label class="field-label">Nombre<input class="field-input" name="name" required placeholder="Bodega Z"></label>
                            <label class="field-label">Código<input class="field-input" name="code" placeholder="ZONE-Z"></label>
                            <label class="field-label">Color<input class="mt-2 h-11 w-full rounded-xl border border-slate-200 p-1" type="color" name="color" value="#14B8A6"></label>
                            <fieldset class="rounded-xl border border-slate-200 p-3"><legend class="px-1 text-xs font-semibold text-slate-600">Notificaciones opcionales</legend><div class="space-y-2 text-sm"><label class="flex gap-2"><input type="checkbox" name="alert_types[]" value="entry"> Cuando ingresa</label><label class="flex gap-2"><input type="checkbox" name="alert_types[]" value="exit"> Cuando sale</label><label class="flex gap-2"><input type="checkbox" name="alert_types[]" value="dwell"> Cuando permanece demasiado</label></div><label class="field-label mt-3">Tiempo de permanencia (minutos)<input class="field-input" type="number" name="dwell_minutes" min="10" max="10080" value="30"></label><label class="field-label mt-3">Correos destinatarios<textarea class="field-input" name="alert_recipients" rows="2" placeholder="operaciones@empresa.com, seguridad@empresa.com"></textarea><span class="mt-1 block text-xs font-normal text-slate-400">Opcional. Si queda vacío se usarán los destinatarios globales de Alertas.</span></label></fieldset>
                            @foreach(['x_min', 'y_min', 'x_max', 'y_max'] as $coordinate)<input id="zone-{{ str_replace('_', '-', $coordinate) }}" type="hidden" name="{{ $coordinate }}" required>@endforeach
                            <p id="zone-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Dibuja el rectángulo en el plano.</p>
                            <button id="zone-submit" class="btn-primary w-full" disabled>Guardar zona</button>
                        </div>
                    </form>

                    <form id="anchor-form" method="POST" action="{{ route('installations.store', $selectedPlan) }}" class="panel p-5" hidden>
                        @csrf
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

                @if(auth()->user()->hasPermission('plans.manage'))
                    <details class="panel p-5">
                        <summary class="cursor-pointer font-semibold text-slate-950">Registrar dispositivo</summary>
                        <form method="POST" action="{{ route('devices.store') }}" class="mt-4 space-y-4">@csrf<label class="field-label">Nombre<input class="field-input" name="name" required></label><label class="field-label">MAC / identificador<input class="field-input font-mono" name="identifier" list="reported-beacon-macs" required><span class="mt-1 block text-xs font-normal text-slate-400">Las MAC reportadas muestran como referencia el tracker y RSSI observados.</span></label><label class="field-label">Tipo<select class="field-input" name="type"><option value="beacon">Beacon BLE</option><option value="scanner">Scanner BLE fijo</option><option value="lorawan_tracker">Tracker LoRaWAN</option></select></label><label class="field-label">Modelo<input class="field-input" name="model" placeholder="Fabricante y modelo exacto"></label><p class="text-xs leading-relaxed text-slate-500">Un beacon solo se vuelve ancla fija al instalarlo sobre el plano. Si va unido a un activo, asígnalo desde Activos y no lo instales como ancla.</p><button class="btn-primary w-full">Crear dispositivo</button></form>
                    </details>
                @endif

                <section class="panel p-5">
                    <h2 class="font-semibold text-slate-950">Zonas definidas</h2>
                    <div class="mt-4 space-y-3">
                        @forelse($selectedPlan->zones as $zone)
                            <details class="rounded-xl border border-slate-100 p-3">
                                <summary class="flex cursor-pointer items-center justify-between gap-3"><span class="flex items-center gap-3"><span class="h-4 w-4 rounded" style="background: {{ $zone->color }}"></span><span><strong class="block text-sm">{{ $zone->name }}</strong><span class="text-xs text-slate-400">{{ $zone->code ?: 'Sin código' }} · {{ $zone->alertRules->count() }} reglas</span></span></span></summary>
                                @if(auth()->user()->hasPermission('plans.manage'))
                                    <div class="mt-3 border-t border-slate-100 pt-3">
                                        <form method="POST" action="{{ route('zones.update', $zone) }}" class="mb-4 space-y-2">@csrf @method('PUT')<label class="field-label">Nombre<input class="field-input mt-1" name="name" value="{{ $zone->name }}" required></label><label class="field-label">Código<input class="field-input mt-1" name="code" value="{{ $zone->code }}"></label><label class="field-label">Color<input class="mt-1 h-10 w-full rounded-xl border border-slate-200 p-1" type="color" name="color" value="{{ $zone->color }}" required></label><button class="btn-secondary w-full" type="submit">Actualizar área</button></form>
                                        <div class="space-y-2">@foreach($zone->alertRules as $rule)<div class="flex justify-between gap-2 text-xs"><span>{{ ['entry'=>'Ingreso','exit'=>'Salida','dwell'=>'Permanencia'][$rule->event_type] }}{{ $rule->dwell_minutes ? ' · '.$rule->dwell_minutes.' min' : '' }}</span><form method="POST" action="{{ route('zone-alert-rules.destroy', $rule) }}">@csrf @method('DELETE')<button class="text-red-600">Quitar</button></form></div>@endforeach</div>
                                        <form method="POST" action="{{ route('zone-alert-rules.store', $zone) }}" class="mt-3 space-y-2">@csrf<select class="field-input mt-0" name="event_type"><option value="entry">Avisar al ingresar</option><option value="exit">Avisar al salir</option><option value="dwell">Avisar por permanencia</option></select><input class="field-input mt-0" type="number" name="dwell_minutes" min="10" max="10080" value="30" aria-label="Minutos de permanencia"><textarea class="field-input mt-0" name="recipients" rows="2" placeholder="Vacío: usar destinatarios globales"></textarea><button class="action-link" type="submit">Guardar regla</button></form>
                                        <form method="POST" action="{{ route('zones.destroy', $zone) }}" class="mt-3" onsubmit="return confirm('¿Eliminar esta zona y sus reglas?')">@csrf @method('DELETE')<button class="text-xs text-red-600">Eliminar zona</button></form>
                                    </div>
                                @endif
                            </details>
                        @empty
                            <p class="text-sm text-slate-400">Sin zonas.</p>
                        @endforelse
                    </div>
                </section>

                <section class="panel p-5">
                    <h2 class="font-semibold text-slate-950">Anclas instaladas</h2>
                    <div class="mt-4 space-y-3">@forelse($installations as $installation)<div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 p-3"><div><p class="text-sm font-semibold">{{ $installation->device->name }}</p><p class="text-xs text-slate-400">({{ number_format((float) $installation->x, 2) }}, {{ number_format((float) $installation->y, 2) }}) m · {{ $installation->reference_rssi }} dBm</p></div>@if(auth()->user()->hasPermission('plans.manage'))<form method="POST" action="{{ route('installations.destroy', $installation) }}">@csrf @method('DELETE')<button class="text-xs text-red-600">Quitar</button></form>@endif</div>@empty<p class="text-sm text-slate-400">Se requieren al menos tres anclas no colineales.</p>@endforelse</div>
                </section>
            </aside>
        </div>
    @endif
    @if($selectedPlan?->drawablePath())
        <script src="/js/floor-plan-editor.js?v={{ filemtime(public_path('js/floor-plan-editor.js')) }}"></script>
    @endif
@endsection
