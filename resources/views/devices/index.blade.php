@extends('layouts.app')

@section('title', 'Dispositivos')
@section('heading', 'Dispositivos')

@section('content')
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Inventario de dispositivos</h2>
                <p class="panel-subtitle">Hardware registrado, ubicacion conocida y ultimas AP MAC observadas cuando no hay area asignada</p>
            </div>
            @if(auth()->user()->hasPermission('plans.manage'))
                <a class="action-link" href="{{ route('floor-plans.index') }}">Ubicar en plano</a>
            @endif
        </div>

        <form class="mb-4 flex flex-wrap items-end gap-3" method="GET" action="{{ route('devices.index') }}">
            <label class="field-label min-w-72 flex-1">Buscar dispositivo
                <input class="field-input" type="search" name="q" value="{{ $search }}" placeholder="Nombre, MAC, identificador, modelo o tipo">
            </label>
            <button class="btn-primary" type="submit">Buscar</button>
            @if($search !== '')
                <a class="btn-secondary" href="{{ route('devices.index') }}">Limpiar</a>
            @endif
        </form>

        @if($deviceRows->isEmpty())
            <div class="empty-state">{{ $search !== '' ? 'No hay dispositivos para la busqueda indicada.' : 'Aun no hay dispositivos registrados.' }}</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Dispositivo</th>
                            <th>Tipo</th>
                            <th>Ubicacion / ultima vista</th>
                            <th>AP MAC alrededor</th>
                            <th>Ultima senal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($deviceRows as $row)
                            @php($device = $row['device'])
                            <tr>
                                <td>
                                    <strong class="block text-sm">{{ $device->name }}</strong>
                                    <code class="text-xs">{{ $device->identifier }}</code>
                                    @if($row['assignment']?->asset)
                                        <span class="mt-1 block text-xs text-slate-400">Activo: {{ $row['assignment']->asset->name }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-badge status-{{ $device->status === 'active' ? 'active' : 'disabled' }}">{{ $row['type_label'] }}</span>
                                    @if($device->model)<span class="mt-1 block text-xs text-slate-400">{{ $device->model }}</span>@endif
                                </td>
                                <td>
                                    <span class="block text-sm text-slate-700">{{ $row['location_label'] }}</span>
                                    @if($row['position'] && ! $row['position']->zone)
                                        <span class="mt-1 block text-xs text-amber-700">Sin area/zona establecida para la ultima posicion.</span>
                                    @endif
                                </td>
                                <td>
                                    @if($row['observations']->isEmpty())
                                        <span class="text-sm text-slate-400">Sin observaciones normalizadas</span>
                                    @else
                                        <div class="flex flex-wrap gap-1.5">
                                            @foreach($row['observations'] as $observation)
                                                <span class="rounded bg-slate-100 px-2 py-1 font-mono text-xs text-slate-700" title="RSSI {{ $observation->rssi }} dBm">
                                                    {{ $observation->receiver_identifier }}
                                                </span>
                                            @endforeach
                                        </div>
                                        <span class="mt-1 block text-xs text-slate-400">Ultimas anclas receptoras del dispositivo.</span>
                                    @endif
                                    <button
                                        class="device-ap-history-toggle"
                                        type="button"
                                        data-device-ap-history-toggle
                                        data-target="device-ap-history-{{ $device->id }}"
                                        data-endpoint="{{ route('devices.ap-history', $device) }}"
                                        aria-expanded="false"
                                        aria-controls="device-ap-history-{{ $device->id }}"
                                    >
                                        Ver historial AP MAC
                                    </button>
                                </td>
                                <td>
                                    <span class="text-sm text-slate-700">{{ $row['last_seen_at']?->diffForHumans() ?? 'Sin senal' }}</span>
                                    @if($row['last_seen_at'])
                                        <span class="mt-1 block text-xs text-slate-400">{{ $row['last_seen_at']->format('d-m-Y H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                            <tr id="device-ap-history-{{ $device->id }}" class="device-ap-history-row" data-device-ap-history-panel hidden>
                                <td colspan="5">
                                    <div class="device-ap-history-panel">
                                        <div class="device-ap-history-header">
                                            <div>
                                                <strong>Historial de AP MAC alrededor</strong>
                                                <span>Retencion maxima: 6 dias. Se carga bajo demanda para no ralentizar la lista.</span>
                                            </div>
                                            <span class="device-ap-history-status" data-device-ap-history-status>Sin cargar</span>
                                        </div>
                                        <div class="table-wrap device-ap-history-table-wrap">
                                            <table class="data-table device-ap-history-table">
                                                <thead>
                                                    <tr>
                                                        <th>AP MAC</th>
                                                        <th>RSSI</th>
                                                        <th>Tiempo observado</th>
                                                        <th>Origen</th>
                                                    </tr>
                                                </thead>
                                                <tbody data-device-ap-history-rows>
                                                    <tr><td colspan="4">Cargando historial...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="device-ap-history-pagination" data-device-ap-history-pagination aria-live="polite"></div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">
                {{ $deviceRows->links() }}
            </div>
        @endif
    </section>
@endsection
