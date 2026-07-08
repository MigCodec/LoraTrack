@extends('layouts.app')

@section('title', 'AP Meraki')
@section('heading', 'AP Meraki')

@section('content')
    <section class="panel">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Access points Meraki</h2>
                <p class="panel-subtitle">AP detectados por el conector Meraki v3, con ubicacion en plano y actividad normalizada</p>
            </div>
            @if(auth()->user()->hasPermission('plans.manage'))
                <a class="action-link" href="{{ route('floor-plans.index') }}">Ubicar en plano</a>
            @endif
        </div>

        @if($accessPointRows->isEmpty())
            <div class="empty-state">Aun no hay AP Meraki registrados desde el conector.</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>AP</th>
                            <th>Meraki</th>
                            <th>Ubicacion</th>
                            <th>Clientes vistos</th>
                            <th>Ultima actividad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($accessPointRows as $row)
                            @php($accessPoint = $row['access_point'])
                            <tr>
                                <td>
                                    <strong class="block text-sm">{{ $accessPoint->name }}</strong>
                                    <code class="text-xs">{{ $accessPoint->identifier }}</code>
                                    @if($accessPoint->model)
                                        <span class="mt-1 block text-xs text-slate-400">{{ $accessPoint->model }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="block text-sm text-slate-700">Serial: {{ $row['serial'] ?? 'Sin serial' }}</span>
                                    <span class="mt-1 block text-xs text-slate-400">Network: {{ $row['network_id'] ?? 'Sin network_id' }}</span>
                                    @if($row['reported_latitude'] !== null && $row['reported_longitude'] !== null)
                                        <span class="mt-1 block text-xs text-slate-400">Meraki lat/lng: {{ $row['reported_latitude'] }}, {{ $row['reported_longitude'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="status-badge status-{{ $row['status_class'] }}">{{ $row['status_label'] }}</span>
                                    <span class="mt-2 block text-sm text-slate-700">{{ $row['location_label'] }}</span>
                                </td>
                                <td>
                                    <strong class="block text-sm text-slate-800">{{ number_format($row['clients_count']) }}</strong>
                                    <span class="text-xs text-slate-400">MAC cliente distintas observadas por este AP</span>
                                </td>
                                <td>
                                    <span class="text-sm text-slate-700">{{ $row['last_observed_at']?->diffForHumans() ?? 'Sin senal' }}</span>
                                    @if($row['last_observed_at'])
                                        <span class="mt-1 block text-xs text-slate-400">{{ $row['last_observed_at']->format('d-m-Y H:i') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">
                {{ $accessPointRows->links() }}
            </div>
        @endif
    </section>
@endsection
