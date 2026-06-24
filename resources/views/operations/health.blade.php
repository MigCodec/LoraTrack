@extends('layouts.app')

@section('title', 'Salud operacional')
@section('heading', 'Salud operacional')

@section('content')
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach($checks as $check)
            <article class="metric-card">
                <div class="flex items-start justify-between gap-3"><p class="text-sm text-slate-500">{{ $check['name'] }}</p><span class="status-badge status-{{ $check['ok'] ? 'active' : 'error' }}">{{ $check['ok'] ? 'OK' : 'Revisar' }}</span></div>
                <p class="mt-2 text-3xl font-semibold text-slate-950">{{ number_format($check['value']) }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-400">{{ $check['detail'] }}</p>
            </article>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel">
            <div class="panel-header"><div><h2 class="panel-title">Planos y redundancia</h2><p class="panel-subtitle">Tres anclas no colineales por estrategia para operar</p></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Plano</th><th>Beacons</th><th>Scanners</th><th>Archivo</th></tr></thead><tbody>
                @forelse($plans as $item)<tr><td><strong>{{ $item['plan']->name }}</strong><br><span class="text-xs text-slate-400">{{ $item['plan']->location->name }}</span></td><td><span class="status-badge status-{{ $item['beacons'] >= 3 ? 'active' : 'disabled' }}">{{ $item['beacons'] }}</span></td><td><span class="status-badge status-{{ $item['scanners'] >= 3 ? 'active' : 'disabled' }}">{{ $item['scanners'] }}</span></td><td><span class="status-badge status-{{ $item['file_ok'] ? 'active' : 'error' }}">{{ $item['file_ok'] ? 'Privado OK' : 'Faltante' }}</span></td></tr>@empty<tr><td colspan="4">No hay planos activos.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <section class="panel">
            <div class="panel-header"><div><h2 class="panel-title">Conectores</h2><p class="panel-subtitle">Actividad y errores persistentes</p></div></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Conector</th><th>Estado</th><th>Actividad</th></tr></thead><tbody>
                @forelse($connectors as $connector)<tr><td><strong>{{ $connector->name }}</strong>@if($connector->last_error)<br><span class="text-xs text-red-600">{{ Str::limit($connector->last_error, 80) }}</span>@endif</td><td><span class="status-badge status-{{ $connector->status->value }}">{{ $connector->status->label() }}</span></td><td>{{ $connector->last_activity_at?->diffForHumans() ?? $connector->last_success_at?->diffForHumans() ?? 'Sin actividad' }}</td></tr>@empty<tr><td colspan="3">Sin conectores.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>

    <section class="panel mt-6">
        <div class="panel-header"><div><h2 class="panel-title">Scanners pendientes de instalación</h2><p class="panel-subtitle">AP Meraki registrados automáticamente, todavía sin plano ni coordenadas indoor</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Scanner</th><th>MAC</th><th>Serial</th><th>Última señal</th><th>Estado</th></tr></thead><tbody>
            @forelse($pendingScanners as $scanner)
                <tr>
                    <td><strong>{{ $scanner->name }}</strong><br><span class="text-xs text-slate-400">{{ $scanner->model }}</span></td>
                    <td><code class="text-xs">{{ $scanner->identifier }}</code></td>
                    <td>{{ data_get($scanner->metadata, 'meraki.serial', '—') }}</td>
                    <td>{{ $scanner->last_seen_at?->format('d-m-Y H:i:s') ?? 'Sin señal' }}</td>
                    <td><span class="status-badge status-disabled">Pendiente de plano</span></td>
                </tr>
            @empty
                <tr><td colspan="5">No hay scanners pendientes de instalación.</td></tr>
            @endforelse
        </tbody></table></div>
    </section>

    @if(auth()->user()->isAdmin())
    <section class="panel mt-6">
        <div class="panel-header"><div><h2 class="panel-title">Auditoría reciente</h2><p class="panel-subtitle">Cambios HTTP con usuario, resultado y Request ID</p></div></div>
        <div class="table-wrap"><table class="data-table"><thead><tr><th>Fecha</th><th>Usuario</th><th>Acción</th><th>Resultado</th><th>Request ID</th></tr></thead><tbody>
            @forelse($auditLogs as $log)<tr><td>{{ $log->created_at->format('d-m-Y H:i:s') }}</td><td>{{ $log->user?->email ?? 'Sistema/visitante' }}</td><td><strong>{{ $log->method }}</strong> {{ $log->route_name ?? $log->path }}</td><td>{{ $log->status_code }}</td><td><code class="text-xs">{{ $log->request_id }}</code></td></tr>@empty<tr><td colspan="5">La auditoría comenzará con el próximo cambio.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    @endif
@endsection
