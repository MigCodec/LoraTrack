@extends('layouts.app')
@section('title', 'Configuración')
@section('heading', 'Configuración')
@push('styles')<link rel="stylesheet" href="{{ asset('css/scheduler-settings.css') }}?v={{ filemtime(public_path('css/scheduler-settings.css')) }}">@endpush
@section('content')
    <form class="mx-auto max-w-5xl space-y-6" method="POST" action="{{ route('settings.update') }}">
        @csrf
        @method('PUT')

        @if($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800" role="alert">
                <strong>Revisa la configuración ingresada.</strong>
                <ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section class="panel overflow-hidden" aria-labelledby="retention-title">
            <div class="panel-header"><div><h2 id="retention-title" class="panel-title">Retención de datos</h2><p class="panel-subtitle">Políticas de conservación de la organización {{ $organization->name }}</p></div></div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <label class="field-label">Historial Meraki
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Elimina eventos Meraki y sus observaciones RSSI al vencer el plazo, aunque estén pendientes, fallidos, ignorados o procesados.</span>
                    <span class="mt-3 flex items-center gap-2"><input class="field-input max-w-40" type="number" name="meraki_retention_days" min="1" max="3650" value="{{ old('meraki_retention_days', $organization->meraki_retention_days ?? 2) }}" required><span class="text-sm text-slate-500">días</span></span>
                </label>
                <label class="field-label">Retención general mínima
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Protege telemetría reciente frente a la limpieza por presión de almacenamiento. No reemplaza la política específica de Meraki.</span>
                    <span class="mt-3 flex items-center gap-2"><input class="field-input max-w-40" type="number" name="telemetry_retention_days" min="7" max="3650" value="{{ old('telemetry_retention_days', $organization->telemetry_retention_days ?? 30) }}" required><span class="text-sm text-slate-500">días</span></span>
                </label>
            </div>
        </section>

        <section class="panel overflow-hidden" aria-labelledby="processing-title">
            <div class="panel-header"><div><h2 id="processing-title" class="panel-title">Capacidad de procesamiento</h2><p class="panel-subtitle">Máximos aplicados por organización en cada ejecución programada</p></div></div>
            <div class="grid gap-6 p-6 md:grid-cols-2">
                <label class="field-label">Lotes webhook Meraki
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Lotes de entrada normalizados por minuto. Auméntalo cuando la bandeja crezca.</span>
                    <input class="field-input mt-3 max-w-48" type="number" name="meraki_webhook_batch_limit" min="1" max="100" value="{{ old('meraki_webhook_batch_limit', $organization->meraki_webhook_batch_limit ?? 100) }}" required>
                </label>
                <label class="field-label">Observaciones Meraki
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Eventos normalizados que se convierten en dispositivos, señales y posiciones por minuto.</span>
                    <input class="field-input mt-3 max-w-48" type="number" name="meraki_observation_limit" min="1" max="100000" value="{{ old('meraki_observation_limit', $organization->meraki_observation_limit ?? 100) }}" required>
                </label>
                <label class="field-label">Intentos por lote Meraki
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Cantidad máxima de intentos antes de dejar un lote fallido para revisión.</span>
                    <input class="field-input mt-3 max-w-48" type="number" name="meraki_webhook_max_attempts" min="1" max="10" value="{{ old('meraki_webhook_max_attempts', $organization->meraki_webhook_max_attempts ?? 3) }}" required>
                </label>
                <label class="field-label">Uplinks TTI
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Uplinks pendientes procesados por minuto.</span>
                    <input class="field-input mt-3 max-w-48" type="number" name="tti_uplink_limit" min="1" max="1000" value="{{ old('tti_uplink_limit', $organization->tti_uplink_limit ?? 10) }}" required>
                </label>
                <label class="field-label">Mensajes MQTT
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Mensajes pendientes procesados por minuto.</span>
                    <input class="field-input mt-3 max-w-48" type="number" name="mqtt_message_limit" min="1" max="1000" value="{{ old('mqtt_message_limit', $organization->mqtt_message_limit ?? 10) }}" required>
                </label>
                <label class="field-label">Sincronizaciones de catálogo
                    <span class="mt-1 block text-xs font-normal leading-relaxed text-slate-500">Solicitudes de sincronización iniciadas por minuto.</span>
                    <input class="field-input mt-3 max-w-48" type="number" name="catalog_sync_limit" min="1" max="10" value="{{ old('catalog_sync_limit', $organization->catalog_sync_limit ?? 1) }}" required>
                </label>
            </div>
        </section>

        <section class="panel overflow-hidden" aria-labelledby="storage-title">
            <div class="panel-header"><div><h2 id="storage-title" class="panel-title">Protección de almacenamiento</h2><p class="panel-subtitle">Límites automáticos aplicados por el mantenimiento horario</p></div></div>
            <div class="space-y-6 p-6">
                <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4">
                    <input type="hidden" name="storage_cleanup_enabled" value="0">
                    <input class="mt-1" type="checkbox" name="storage_cleanup_enabled" value="1" @checked(old('storage_cleanup_enabled', $organization->storage_cleanup_enabled))>
                    <span><strong class="block text-sm text-slate-900">Liberar telemetría antigua automáticamente</strong><span class="mt-1 block text-xs leading-relaxed text-slate-500">Sólo actúa cuando la ocupación supera el umbral y respeta la retención general mínima.</span></span>
                </label>
                <div class="grid gap-6 md:grid-cols-2">
                    <label class="field-label">Umbral de ocupación
                        <span class="mt-1 block text-xs font-normal text-slate-500">La limpieza comienza al superar este porcentaje.</span>
                        <span class="mt-3 flex items-center gap-2"><input class="field-input max-w-40" type="number" step="0.1" name="storage_cleanup_threshold_percent" min="1" max="99" value="{{ old('storage_cleanup_threshold_percent', $organization->storage_cleanup_threshold_percent ?? 50) }}" required><span class="text-sm text-slate-500">%</span></span>
                    </label>
                    <label class="field-label">Máximo por ejecución
                        <span class="mt-1 block text-xs font-normal text-slate-500">Limita el impacto de cada mantenimiento horario.</span>
                        <span class="mt-3 flex items-center gap-2"><input class="field-input max-w-48" type="number" name="storage_cleanup_max_events" min="1" max="100000" value="{{ old('storage_cleanup_max_events', $organization->storage_cleanup_max_events ?? 10000) }}" required><span class="text-sm text-slate-500">eventos</span></span>
                    </label>
                </div>
                <dl class="grid gap-3 rounded-xl bg-slate-50 p-4 text-xs text-slate-600 sm:grid-cols-3">
                    <div><dt class="font-semibold text-slate-800">Última medición</dt><dd class="mt-1">{{ $organization->storage_checked_at?->format('d-m-Y H:i') ?? 'Pendiente' }}</dd></div>
                    <div><dt class="font-semibold text-slate-800">Ocupación medida</dt><dd class="mt-1">{{ $organization->last_storage_utilization_percent === null ? '—' : number_format($organization->last_storage_utilization_percent, 2).'%' }}</dd></div>
                    <div><dt class="font-semibold text-slate-800">Eventos eliminados</dt><dd class="mt-1">{{ number_format($organization->storage_cleanup_deleted_events ?? 0) }}</dd></div>
                </dl>
            </div>
        </section>

        <div class="flex justify-end"><button class="btn-primary">Guardar configuración</button></div>
    </form>

    <section class="scheduler-settings" aria-labelledby="scheduler-settings-title">
        <div class="scheduler-settings-header">
            <div><span class="scheduler-settings-kicker">Centro de automatización</span><h2 id="scheduler-settings-title">Tareas programadas</h2><p>Configura la cadencia de {{ $organization->name }}, revisa su estado y ejecuta tareas autorizadas bajo demanda.</p></div>
            <div class="scheduler-settings-health"><i aria-hidden="true"></i><span>Dispatcher activo cada minuto</span></div>
        </div>
        <div class="scheduler-settings-list">
            @foreach($scheduledTasks as $task)
                @php($record = $task['record'])
                @php($definition = $task['definition'])
                @php($stateLabel = ['healthy' => 'Correcta', 'failed' => 'Con error', 'running' => 'En ejecución', 'never' => 'Sin historial', 'disabled' => 'Deshabilitada'][$task['state']])
                <article class="scheduler-setting-row is-{{ $task['state'] }}">
                    <div class="scheduler-setting-main"><span class="scheduler-setting-symbol" aria-hidden="true"><x-nav-icon name="settings"/></span><div><h3>{{ $definition['label'] }}</h3><p>{{ $definition['description'] }}</p><code>{{ $definition['command'] }}</code></div></div>
                    <div class="scheduler-setting-state"><span class="scheduler-state-pill is-{{ $task['state'] }}"><i aria-hidden="true"></i>{{ $stateLabel }}</span><small>Última ejecución</small><strong>{{ $record->last_finished_at?->diffForHumans() ?? 'Aún no ejecutada' }}</strong>@if($record->last_duration_ms !== null)<span>{{ number_format($record->last_duration_ms) }} ms · {{ number_format($record->run_count) }} ejecuciones</span>@endif @if($record->last_error)<p class="scheduler-setting-error">{{ Str::limit($record->last_error, 180) }}</p>@endif</div>
                    <form class="scheduler-setting-config" method="POST" action="{{ route('settings.scheduled-tasks.update', $record->task) }}">@csrf @method('PUT')<label class="scheduler-toggle"><input type="hidden" name="enabled" value="0"><input type="checkbox" name="enabled" value="1" @checked($record->enabled)><span aria-hidden="true"></span><b>Habilitada</b></label><label><span>Ejecutar cada</span><span class="scheduler-interval-input"><input type="number" name="interval_minutes" min="{{ $definition['minimum_interval'] }}" max="{{ $definition['maximum_interval'] }}" value="{{ $record->interval_minutes }}" required><b>min</b></span></label><button class="scheduler-save-button">Guardar</button></form>
                    <form class="scheduler-run-form" method="POST" action="{{ route('settings.scheduled-tasks.run', $record->task) }}" @if(in_array($record->task, ['manage-telemetry-storage', 'prune-meraki-history'], true)) onsubmit="return confirm('Esta tarea puede eliminar datos según las políticas configuradas. ¿Ejecutar ahora?')" @endif>@csrf<button class="scheduler-run-button" @disabled($task['state'] === 'running')><span aria-hidden="true">▶</span> Ejecutar ahora</button><small>Próxima: {{ $record->enabled ? ($record->next_run_at?->diffForHumans() ?? 'pendiente') : 'deshabilitada' }}</small></form>
                </article>
            @endforeach
        </div>
    </section>
@endsection
