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
                    <td><div class="flex flex-wrap gap-2"><a class="action-link" href="{{ route('connectors.show', $connector) }}">Ver actividad</a><form method="POST" action="{{ route('connectors.test', $connector) }}">@csrf<button class="action-link">Probar</button></form>@if($connector->kind->value === 'catalog')<form method="POST" action="{{ route('connectors.sync', $connector) }}">@csrf<button class="action-link">Sincronizar</button></form>@endif<form method="POST" action="{{ route('connectors.toggle', $connector) }}">@csrf<button class="action-link">{{ $connector->status->value === 'active' ? 'Desactivar' : 'Activar' }}</button></form>@if($connector->status->value !== 'active')<form method="POST" action="{{ route('connectors.destroy', $connector) }}" onsubmit="return confirm('¿Eliminar este conector? También se eliminarán su telemetría, logs, referencias y decoders asociados.')">@csrf @method('DELETE')<button class="text-sm text-red-600">Eliminar</button></form>@endif</div></td>
                </tr>@endforeach
            </tbody></table></div>
        @endif
    </section>
@endsection
