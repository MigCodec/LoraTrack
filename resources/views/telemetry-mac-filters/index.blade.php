@extends('layouts.app')

@section('title', 'Filtro MAC')
@section('heading', 'Filtro MAC de telemetria')

@section('content')
    <section class="panel p-6">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">MAC permitidas</h2>
                <p class="panel-subtitle">Con entradas activas, cualquier POST que no incluya una MAC permitida se ignora antes de guardar o procesar.</p>
            </div>
        </div>

        <form method="POST" action="{{ route('telemetry-mac-filters.store') }}" class="mt-5 grid gap-4 lg:grid-cols-4">
            @csrf
            <label class="field-label">Dispositivo registrado
                <select class="field-input" name="device_id">
                    <option value="">Seleccionar desde inventario</option>
                    @foreach($devices as $device)
                        <option value="{{ $device->id }}" @selected(old('device_id') === $device->id)>{{ $device->name }} - {{ $device->identifier }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field-label">MAC manual
                <input class="field-input font-mono" name="mac" value="{{ old('mac') }}" placeholder="AA:BB:CC:DD:EE:FF">
            </label>
            <label class="field-label">Etiqueta
                <input class="field-input" name="label" value="{{ old('label') }}" placeholder="Beacon pallet 1">
            </label>
            <div class="flex items-end gap-3">
                <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                    <input type="checkbox" name="enabled" value="1" checked>
                    Activo
                </label>
                <button class="btn-primary" type="submit">Agregar</button>
            </div>
        </form>
    </section>

    <section class="panel mt-6">
        <div class="panel-header">
            <form class="flex flex-wrap items-end gap-3" method="GET" action="{{ route('telemetry-mac-filters.index') }}">
                <label class="field-label min-w-72">Buscar
                    <input class="field-input" type="search" name="q" value="{{ $search }}" placeholder="MAC, etiqueta o dispositivo">
                </label>
                <button class="btn-secondary" type="submit">Buscar</button>
                @if($search !== '')
                    <a class="action-link" href="{{ route('telemetry-mac-filters.index') }}">Limpiar</a>
                @endif
            </form>
        </div>

        @if($filters->isEmpty())
            <div class="empty-state">No hay filtros MAC configurados.</div>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>MAC</th>
                            <th>Origen</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($filters as $filter)
                            <tr>
                                <td>
                                    <form id="filter-{{ $filter->id }}" method="POST" action="{{ route('telemetry-mac-filters.update', $filter) }}" class="grid gap-2 lg:grid-cols-2">
                                        @csrf
                                        @method('PUT')
                                        <input class="field-input font-mono" name="mac" value="{{ implode(':', str_split($filter->normalized_mac, 2)) }}">
                                        <input class="field-input" name="label" value="{{ $filter->label }}" placeholder="Etiqueta">
                                    </form>
                                </td>
                                <td>
                                    <span class="block text-sm text-slate-700">{{ $filter->device?->name ?? 'Manual' }}</span>
                                    @if($filter->device)
                                        <code class="text-xs">{{ $filter->device->identifier }}</code>
                                    @endif
                                </td>
                                <td>
                                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                                        <input type="checkbox" name="enabled" value="1" form="filter-{{ $filter->id }}" @checked($filter->enabled)>
                                        {{ $filter->enabled ? 'Activo' : 'Inactivo' }}
                                    </label>
                                </td>
                                <td>
                                    <div class="flex flex-wrap gap-3">
                                        <button class="btn-secondary" type="submit" form="filter-{{ $filter->id }}">Guardar</button>
                                        <form method="POST" action="{{ route('telemetry-mac-filters.destroy', $filter) }}" onsubmit="return confirm('Eliminar este filtro MAC?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-sm font-semibold text-red-600">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 p-4">{{ $filters->links() }}</div>
        @endif
    </section>
@endsection
