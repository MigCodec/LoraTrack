@extends('layouts.app')
@section('title', 'Conectores')
@section('heading', 'Conectores')
@section('content')
    <section><div><h2 class="text-lg font-semibold text-slate-950">Agregar una integración</h2><p class="mt-1 text-sm text-slate-500">Selecciona cómo ingresará la telemetría o el catálogo de productos.</p></div>
        @foreach(['telemetry' => 'Telemetría', 'catalog' => 'Catálogo de productos'] as $kind => $label)
            <div class="mt-6"><h3 class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ $label }}</h3><div class="mt-3 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($definitions->get($kind, collect()) as $definition)<a href="{{ route('connectors.create', $definition['provider']->value) }}" class="connector-card group"><x-connector-icon :provider="$definition['provider']">{{ $definition['name'] }}</x-connector-icon><span class="block"><strong class="block text-sm text-slate-950 group-hover:text-brand-primary">{{ $definition['name'] }}</strong><span class="mt-1 block text-xs leading-relaxed text-slate-500">{{ $definition['description'] }}</span></span></a>@endforeach
            </div></div>
        @endforeach
    </section>

    @php($scheduledSummary = [
        'healthy' => $scheduledTasks->where('state', 'healthy')->count(),
        'running' => $scheduledTasks->where('state', 'running')->count(),
        'failed' => $scheduledTasks->where('state', 'failed')->count(),
        'never' => $scheduledTasks->where('state', 'never')->count(),
    ])
    <section class="panel mt-10 scheduler-center" aria-labelledby="scheduled-tasks-title">
        <div class="scheduler-center-header">
            <div class="scheduler-center-heading"><span class="scheduler-center-icon"><x-nav-icon name="health"/></span><div><p class="scheduler-center-eyebrow">Centro de automatización</p><h2 id="scheduled-tasks-title">Tareas programadas</h2><p>Supervisa la ejecución de los procesos que mantienen conectores y telemetría al día.</p></div></div>
            <span class="scheduler-center-updated">Información registrada por Laravel Scheduler</span>
        </div>
        <div class="scheduler-summary" aria-label="Resumen de tareas">
            @foreach([
                ['healthy', 'Correctas', $scheduledSummary['healthy']],
                ['running', 'En ejecución', $scheduledSummary['running']],
                ['failed', 'Con error', $scheduledSummary['failed']],
                ['never', 'Sin historial', $scheduledSummary['never']],
            ] as [$state, $label, $count])
                <div class="scheduler-summary-item is-{{ $state }}"><span class="scheduler-summary-symbol" aria-hidden="true"></span><span><strong>{{ $count }}</strong><small>{{ $label }}</small></span></div>
            @endforeach
        </div>
        <div class="scheduler-list" role="list" aria-label="Historial de tareas programadas">
            <div class="scheduler-list-columns" aria-hidden="true"><span>Automatización</span><span>Frecuencia</span><span>Última ejecución</span><span>Duración</span><span>Estado</span></div>
            @foreach($scheduledTasks as $task)
                @php($status = $task['status'])
                @php($stateMeta = [
                    'healthy' => ['Correcta', 'active'],
                    'failed' => ['Requiere atención', 'error'],
                    'running' => ['En ejecución', 'disabled'],
                    'never' => ['Sin historial', 'disabled'],
                ][$task['state']])
                <article class="scheduler-row is-{{ $task['state'] }}" role="listitem">
                    <div class="scheduler-task-identity"><span class="scheduler-task-icon" aria-hidden="true"><x-nav-icon name="connectors"/></span><span><strong>{{ $task['label'] }}</strong><small>{{ $task['description'] }}</small></span></div>
                    <div class="scheduler-row-field"><small>Frecuencia</small><span>{{ $task['frequency'] }}</span></div>
                    <div class="scheduler-row-field"><small>Última ejecución</small><span title="{{ $status?->last_started_at?->format('d-m-Y H:i:s') }}">{{ $status?->last_started_at?->diffForHumans() ?? 'Aún no ejecutada' }}</span><small>{{ $status?->last_started_at?->format('d-m-Y H:i:s') }}</small></div>
                    <div class="scheduler-row-field"><small>Duración</small><span>{{ $status?->last_duration_ms !== null ? number_format($status->last_duration_ms).' ms' : '—' }}</span><small>{{ number_format($status?->run_count ?? 0) }} ejecuciones</small></div>
                    <div class="scheduler-row-status"><span class="status-badge status-{{ $stateMeta[1] }}"><i aria-hidden="true"></i>{{ $stateMeta[0] }}</span></div>
                    <details class="scheduler-diagnostics">
                        <summary>Detalles técnicos</summary>
                        <div><span>Comando</span><code>{{ $task['command'] }}</code>@if($status?->last_error)<span>Último error</span><p>{{ Str::limit($status->last_error, 300) }}</p>@endif</div>
                    </details>
                </article>
            @endforeach
        </div>
    </section>

    <section class="panel mt-10"><div class="panel-header"><div><h2 class="panel-title">Conectores configurados</h2><p class="panel-subtitle">Estado, actividad, eventos y acceso al log operacional</p></div></div>
        @if($connectors->isEmpty())<div class="empty-state">Aún no hay conectores configurados.</div>@else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Nombre</th><th>Proveedor</th><th>Estado operacional</th><th>Eventos</th><th>Última actividad</th><th>Acciones</th></tr></thead><tbody>
                @foreach($connectors as $connector)<tr>
                    <td><a class="font-semibold text-brand-primary" href="{{ route('connectors.show', $connector) }}">{{ $connector->name }}</a><br><span class="text-xs text-slate-400">{{ $connector->kind->label() }}</span>@if($connector->last_error)<br><span class="text-xs text-red-600">{{ Str::limit($connector->last_error, 65) }}</span>@endif</td>
                    @php($providerDefinition = $definitions->flatten(1)->firstWhere('provider', $connector->provider))
                    <td><span class="flex items-center gap-3"><x-connector-icon :provider="$connector->provider">{{ $providerDefinition['name'] ?? $connector->provider->value }}</x-connector-icon><span>{{ $providerDefinition['name'] ?? $connector->provider->value }}</span></span></td>
                    <td><span class="status-badge status-{{ $connector->status->value }}">{{ $connector->status->label() }}</span>@if($connector->provider->value === 'mqtt' && $connector->status->value === 'active' && !$connector->last_activity_at)<br><span class="text-xs text-amber-800">Esperando inicio del listener</span>@endif</td>
                    <td><strong>{{ $connector->telemetry_events_count }}</strong><br><span class="text-xs text-slate-400">{{ $connector->processed_events_count }} procesados · {{ $connector->failed_events_count }} fallidos</span></td>
                    <td>{{ $connector->last_activity_at?->diffForHumans() ?? $connector->last_tested_at?->diffForHumans() ?? 'Sin actividad' }}</td>
                    <td><div class="flex flex-wrap gap-2"><form method="POST" action="{{ route('connectors.test', $connector) }}">@csrf<button class="action-link">Probar</button></form>@if($connector->kind->value === 'catalog')<form method="POST" action="{{ route('connectors.sync', $connector) }}">@csrf<button class="action-link">Sincronizar</button></form>@endif<form method="POST" action="{{ route('connectors.toggle', $connector) }}">@csrf<button class="action-link">{{ $connector->status->value === 'active' ? 'Desactivar' : 'Activar' }}</button></form>@if($connector->status->value !== 'active')<form method="POST" action="{{ route('connectors.destroy', $connector) }}" onsubmit="return confirm('¿Eliminar este conector? También se eliminarán su telemetría, logs, referencias y decoders asociados.')">@csrf @method('DELETE')<button class="text-sm text-red-600">Eliminar</button></form>@endif</div></td>
                </tr>@endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
