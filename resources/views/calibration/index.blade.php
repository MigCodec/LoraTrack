@extends('layouts.app')

@section('title', 'Calibración RSSI')
@section('heading', 'Banco de calibración RSSI')

@section('content')
    @php($selectedRun = $runs->firstWhere('id', session('calibration_run_id')) ?? $runs->first())
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div><h2 class="text-lg font-semibold text-slate-950">{{ $plan->name }}</h2><p class="text-sm text-slate-500">{{ $plan->location->name }} · sistema cartesiano local · {{ $plan->width_meters }} × {{ $plan->height_meters }} metros</p></div>
        <a class="btn-secondary" href="{{ route('floor-plans.index', ['plan' => $plan]) }}">Volver al plano</a>
    </div>

    <section class="panel p-6">
        <h2 class="text-lg font-semibold text-slate-950">Nueva prueba en punto conocido</h2>
        <p class="mt-2 max-w-4xl text-sm leading-6 text-slate-600">Coloca físicamente el tracker o beacon en una coordenada conocida, toma varias lecturas y utiliza el RSSI mediano de cada ancla. Modifica los parámetros y repite hasta reducir el error. No calibres usando una sola posición: prueba centro, bordes y esquinas.</p>
        <div class="mt-4 rounded-xl bg-slate-50 p-4 font-mono text-sm text-slate-700">distancia (m) = 10<sup>((RSSI a 1 m − RSSI medido) / (10 × n))</sup></div>

        @if($installations->count() < 4)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Hay {{ $installations->count() }} anclas activas. Instala al menos cuatro beacons o cuatro scanners antes de calibrar.</div>
        @else
            <form id="calibration-form" method="POST" action="{{ route('calibration.preview', $plan) }}" class="mt-6">@csrf
                <div class="grid gap-4 md:grid-cols-4">
                    <label class="field-label">Nombre de la prueba<input class="field-input" name="name" value="{{ old('name', 'Punto '.now()->format('H:i')) }}" required placeholder="Centro de bodega"></label>
                    <label class="field-label">Tipo de ancla<select class="field-input" name="anchor_type" required><option value="beacon" @selected(old('anchor_type') === 'beacon')>Beacons fijos → tracker móvil</option><option value="scanner" @selected(old('anchor_type') === 'scanner')>Scanners fijos → beacon móvil</option></select></label>
                    <label class="field-label">X real (m)<input class="field-input" type="number" name="expected_x" min="0" max="{{ $plan->width_meters }}" step="0.001" value="{{ old('expected_x') }}" required></label>
                    <label class="field-label">Y real (m)<input class="field-input" type="number" name="expected_y" min="0" max="{{ $plan->height_meters }}" step="0.001" value="{{ old('expected_y') }}" required></label>
                </div>

                <div class="table-wrap mt-6"><table class="data-table"><thead><tr><th>Ancla y coordenada</th><th>RSSI medido (dBm)</th><th>RSSI a 1 m (dBm)</th><th>Exponente n</th></tr></thead><tbody>
                    @foreach($installations as $installation)
                        <tr data-anchor-type="{{ $installation->device->type }}">
                            <td><strong>{{ $installation->device->name }}</strong><br><code class="text-xs">{{ $installation->device->identifier }}</code><br><span class="text-xs text-slate-400">X {{ number_format($installation->x, 2) }} m · Y {{ number_format($installation->y, 2) }} m</span></td>
                            <td><input class="field-input mt-0" type="number" name="anchors[{{ $installation->id }}][rssi]" min="-127" max="-1" value="{{ old("anchors.{$installation->id}.rssi", -70) }}" required></td>
                            <td><input class="field-input mt-0" type="number" name="anchors[{{ $installation->id }}][reference_rssi]" min="-127" max="-1" value="{{ old("anchors.{$installation->id}.reference_rssi", $installation->reference_rssi) }}" required></td>
                            <td><input class="field-input mt-0" type="number" name="anchors[{{ $installation->id }}][path_loss_exponent]" min="0.5" max="8" step="0.01" value="{{ old("anchors.{$installation->id}.path_loss_exponent", $installation->path_loss_exponent) }}" required></td>
                        </tr>
                    @endforeach
                </tbody></table></div>
                <div class="mt-5 flex flex-wrap items-center gap-3"><button id="calibration-submit" class="btn-primary">Calcular sin aplicar</button><span id="calibration-anchor-count" class="text-xs text-slate-500">La prueba queda registrada, pero no modifica el posicionamiento hasta pulsar “Aplicar”.</span></div>
            </form>
        @endif
    </section>

    @if($selectedRun)
        <section class="panel mt-6 p-6">
            <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold text-slate-950">Resultado: {{ $selectedRun->name }}</h2><p class="mt-1 text-sm text-slate-500">Prueba {{ $selectedRun->created_at->format('d-m-Y H:i:s') }} · {{ count($selectedRun->parameters) }} {{ $selectedRun->anchor_type === 'beacon' ? 'beacons' : 'scanners' }}</p></div><span class="status-badge status-{{ $selectedRun->status === 'applied' ? 'active' : 'disabled' }}">{{ $selectedRun->status === 'applied' ? 'Aplicada' : 'Vista previa' }}</span></div>
            <div class="mt-5 grid gap-4 md:grid-cols-4">
                <article class="metric-card"><p class="text-sm text-slate-500">Error de posición</p><p class="mt-2 text-2xl font-semibold">{{ number_format((float) $selectedRun->position_error_meters, 3) }} m</p></article>
                <article class="metric-card"><p class="text-sm text-slate-500">Esperada</p><p class="mt-2 text-lg font-semibold">X {{ number_format((float) $selectedRun->expected_x, 2) }} m<br>Y {{ number_format((float) $selectedRun->expected_y, 2) }} m</p></article>
                <article class="metric-card"><p class="text-sm text-slate-500">Calculada</p><p class="mt-2 text-lg font-semibold">X {{ number_format((float) $selectedRun->calculated_x, 2) }} m<br>Y {{ number_format((float) $selectedRun->calculated_y, 2) }} m</p></article>
                <article class="metric-card"><p class="text-sm text-slate-500">RMSE / confianza</p><p class="mt-2 text-lg font-semibold">{{ number_format((float) $selectedRun->signal_rmse_meters, 3) }} m<br>{{ number_format((float) $selectedRun->confidence * 100, 1) }}%</p></article>
            </div>
            @if($plan->drawablePath())
                @php($expectedLeft = min(100, max(0, (float)$selectedRun->expected_x / (float)$plan->width_meters * 100)))
                @php($expectedTop = min(100, max(0, (float)$selectedRun->expected_y / (float)$plan->height_meters * 100)))
                @php($calculatedLeft = min(100, max(0, (float)$selectedRun->calculated_x / (float)$plan->width_meters * 100)))
                @php($calculatedTop = min(100, max(0, (float)$selectedRun->calculated_y / (float)$plan->height_meters * 100)))
                <div class="mt-5 inline-block max-w-full overflow-hidden rounded-xl border border-slate-200"><div class="relative"><img class="block max-h-96 max-w-full" src="{{ route('floor-plans.file', $plan) }}" alt="Comparación sobre {{ $plan->name }}"><span class="calibration-marker expected" style="left:{{ $expectedLeft }}%;top:{{ $expectedTop }}%" title="Posición esperada"></span><span class="calibration-marker calculated" style="left:{{ $calculatedLeft }}%;top:{{ $calculatedTop }}%" title="Posición calculada"></span></div><div class="flex gap-4 bg-white p-3 text-xs"><span><i class="calibration-legend expected"></i> Esperada</span><span><i class="calibration-legend calculated"></i> Calculada</span></div></div>
            @endif
            <div class="table-wrap mt-5"><table class="data-table"><thead><tr><th>Ancla</th><th>RSSI</th><th>A @ 1 m</th><th>n</th><th>Distancia RSSI</th><th>Distancia real</th><th>Residual</th></tr></thead><tbody>@foreach($selectedRun->parameters as $parameter)@php($realDistance = hypot((float)$selectedRun->expected_x - $parameter['x_meters'], (float)$selectedRun->expected_y - $parameter['y_meters']))<tr><td>{{ $parameter['device'] }}</td><td>{{ $parameter['measured_rssi_dbm'] }} dBm</td><td>{{ $parameter['reference_rssi_dbm_at_1m'] }} dBm</td><td>{{ number_format((float)$parameter['path_loss_exponent'],2) }}</td><td>{{ number_format((float)$parameter['estimated_distance_meters'],3) }} m</td><td>{{ number_format($realDistance,3) }} m</td><td>{{ number_format((float)$parameter['estimated_distance_meters'] - $realDistance,3) }} m</td></tr>@endforeach</tbody></table></div>
            @if($selectedRun->status !== 'applied')<form method="POST" action="{{ route('calibration.apply', $selectedRun) }}" class="mt-5" onsubmit="return confirm('¿Aplicar estos parámetros a las anclas activas?')">@csrf<button class="btn-primary">Aplicar parámetros a las anclas</button></form>@endif
        </section>
    @endif

    <section class="panel mt-6">
        <div class="panel-header"><div><h2 class="panel-title">Historial de pruebas</h2><p class="panel-subtitle">Compara resultados antes de decidir</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Prueba</th><th>Esperada</th><th>Calculada</th><th>Error</th><th>Estado</th></tr></thead><tbody>
            @forelse($runs as $run)<tr><td>{{ $run->created_at->format('d-m-Y H:i') }}</td><td>{{ $run->name }}<br><span class="text-xs text-slate-400">{{ $run->anchor_type }}</span></td><td>({{ number_format((float)$run->expected_x,2) }}, {{ number_format((float)$run->expected_y,2) }}) m</td><td>({{ number_format((float)$run->calculated_x,2) }}, {{ number_format((float)$run->calculated_y,2) }}) m</td><td><strong>{{ number_format((float)$run->position_error_meters,3) }} m</strong></td><td>{{ $run->status === 'applied' ? 'Aplicada' : 'Prueba' }}</td></tr>@empty<tr><td colspan="6">Aún no hay pruebas de calibración.</td></tr>@endforelse
        </tbody></table></div>
    </section>
@endsection
