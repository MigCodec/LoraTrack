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
