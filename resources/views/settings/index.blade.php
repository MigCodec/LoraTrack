@extends('layouts.app')
@section('title', 'Configuración')
@section('heading', 'Configuración')
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
@endsection
