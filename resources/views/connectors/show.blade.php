@extends('layouts.app')
@section('title', $connector->name)
@section('heading', 'Estado del conector')

@section('content')
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div class="flex items-start gap-4">
            <x-connector-icon :provider="$connector->provider">{{ $definition['name'] }}</x-connector-icon>
            <div>
            <a class="text-sm text-brand-primary" href="{{ route('connectors.index') }}">← Todos los conectores</a>
            <h2 class="mt-2 text-xl font-semibold text-slate-950">{{ $connector->name }}</h2>
            <p class="mt-1 text-sm text-slate-500">{{ $definition['name'] }} · {{ $connector->kind->label() }}</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('connectors.test', $connector) }}">@csrf<button class="btn-secondary">Probar</button></form>
            <form method="POST" action="{{ route('connectors.toggle', $connector) }}">@csrf<button class="btn-primary">{{ $connector->status->value === 'active' ? 'Desactivar' : 'Activar' }}</button></form>
            @if($connector->status->value !== 'active')
                <form method="POST" action="{{ route('connectors.destroy', $connector) }}" onsubmit="return confirm('¿Eliminar definitivamente este conector y todos sus eventos asociados?')">@csrf @method('DELETE')<button class="btn-secondary text-red-600">Eliminar</button></form>
            @endif
        </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
        <article class="metric-card"><p class="text-sm text-slate-500">Estado</p><p class="mt-2 text-2xl font-semibold">{{ $connector->status->label() }}</p></article>
        @foreach([
            ['Eventos recibidos', $connector->telemetry_events_count, 'received', 'telemetry'],
            ['Procesados', $connector->processed_events_count, 'processed', 'telemetry'],
            ['Pendientes', $connector->pending_events_count, 'pending', 'telemetry'],
            ['Fallidos', $connector->failed_events_count, 'failed', 'telemetry'],
            ['Rechazados', $rejectedRequests->count(), 'rejected', 'rejected-requests'],
        ] as $metric)
            <a class="metric-card block transition hover:border-brand-accent focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-accent" href="{{ route('connectors.show', ['connector' => $connector, 'events' => $metric[2]]) }}#{{ $metric[3] }}" aria-label="Ver detalle de {{ mb_strtolower($metric[0]) }}">
                <p class="text-sm text-slate-500">{{ $metric[0] }}</p><p class="mt-2 text-2xl font-semibold">{{ $metric[1] }}</p>
            </a>
        @endforeach
    </div>

    @if($connector->provider->value === 'mqtt')
        <section class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
            <h3 class="font-semibold">Operación MQTT</h3><p class="mt-1">Activar habilita la configuración, pero el listener debe permanecer ejecutándose en el servidor.</p>
            <code class="mt-3 block overflow-x-auto rounded-lg bg-slate-900 p-3 text-xs text-white">php artisan loratrack:mqtt-listen {{ $connector->id }}</code>
            <p class="mt-2 text-xs">Supervísalo con Supervisor o systemd. Host: {{ $connector->configuration['host'] ?? '—' }} · Topic: {{ $connector->configuration['topic'] ?? '—' }}</p>
        </section>
    @endif

    @include('connectors.partials.guide', ['connector' => $connector])
    @if($connector->last_error)<div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Último error:</strong> {{ $connector->last_error }}</div>@endif

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <div class="panel-header"><div><h2 class="panel-title">Log operacional</h2><p class="panel-subtitle">Inicio, conexión, mensajes y errores</p></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Nivel</th><th>Evento</th><th>Detalle</th></tr></thead><tbody>
                @forelse($logs as $log)
                    <tr><td class="whitespace-nowrap">{{ $log->created_at->format('d-m-Y H:i:s') }}</td><td>{{ strtoupper($log->level) }}</td><td>{{ $log->event }}</td><td>{{ $log->message }}</td></tr>
                @empty
                    <tr><td colspan="4">Todavía no hay actividad registrada.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>

        <section class="panel" id="telemetry">
            <div class="panel-header"><div><h2 class="panel-title">Telemetría: {{ ['received' => 'recibida', 'processed' => 'procesada', 'pending' => 'pendiente', 'failed' => 'fallida', 'rejected' => 'recibida'][$eventFilter] }}</h2><p class="panel-subtitle">Hasta 100 eventos; haz clic en una recepción para ver el JSON enviado</p></div>@if($eventFilter !== 'received')<a class="text-sm font-semibold text-brand-primary" href="{{ route('connectors.show', $connector) }}#telemetry">Ver todos</a>@endif</div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Recepción</th><th>Estado</th><th>Dispositivo</th><th>Error</th></tr></thead><tbody>
                @forelse($events as $event)
                    <tr>
                        <td class="whitespace-nowrap"><a class="font-semibold text-brand-primary" href="{{ route('connectors.events.show', [$connector, $event]) }}">{{ $event->received_at->format('d-m-Y H:i:s') }}</a></td>
                        <td><span class="status-badge status-{{ $event->processing_status === 'processed' ? 'active' : ($event->processing_status === 'failed' ? 'error' : 'disabled') }}">{{ $event->processing_status }}</span></td>
                        <td>{{ $event->device?->name ?? data_get($event->normalized_payload, 'device_identifier', '—') }}</td>
                        <td class="text-xs text-red-600">{{ Str::limit($event->processing_error, 100) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4">No hay telemetría para este filtro.</td></tr>
                @endforelse
            </tbody></table></div>
        </section>
    </div>

    <section class="panel mt-6" id="rejected-requests">
        <div class="panel-header"><div><h2 class="panel-title">Últimos intentos de recepción rechazados</h2><p class="panel-subtitle">Máximo 10 por conector. No se guardan secretos ni payloads completos.</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>HTTP</th><th>Motivo</th><th>Versión / tipo</th><th>Solicitud</th><th>Contexto</th></tr></thead><tbody>
            @forelse($rejectedRequests as $rejection)
                @php($reasonLabels = [
                    'authentication_failed' => 'Autenticación fallida',
                    'unsupported_version' => 'Versión no permitida',
                    'invalid_version' => 'Versión inválida',
                    'invalid_observation_type' => 'Tipo de observación inválido',
                    'invalid_payload' => 'Payload inválido',
                    'empty_observations' => 'Sin observaciones',
                    'network_mismatch' => 'Red no autorizada',
                    'unsupported_content_type' => 'Content-Type no permitido',
                    'payload_too_large' => 'Payload demasiado grande',
                    'connector_unavailable' => 'Conector no disponible',
                ])
                <tr>
                    <td class="whitespace-nowrap">{{ $rejection->occurred_at->format('d-m-Y H:i:s') }}</td>
                    <td><span class="status-badge status-error">{{ $rejection->http_status }}</span></td>
                    <td>{{ $reasonLabels[$rejection->reason] ?? $rejection->reason }}</td>
                    <td>{{ $rejection->declared_version ?? '—' }} / {{ $rejection->declared_type ?? '—' }}</td>
                    <td><code class="text-xs">{{ $rejection->request_id }}</code></td>
                    <td class="text-xs">{{ $rejection->context ? json_encode($rejection->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No hay intentos rechazados registrados.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>
@endsection
