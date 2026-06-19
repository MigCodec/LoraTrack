@extends('layouts.app')
@section('title', $asset->exists ? 'Editar activo' : 'Nuevo activo')
@section('heading', $asset->exists ? 'Editar activo' : 'Nuevo activo')

@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <form class="panel space-y-4 p-6" method="POST" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
        @csrf
        @if($asset->exists) @method('PUT') @endif
        <label class="field-label">Nombre<input class="field-input" name="name" value="{{ old('name', $asset->name) }}" required></label>
        <label class="field-label">Código de activo<input class="field-input" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" required></label>
        <label class="field-label">Número de serie<input class="field-input" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}"></label>
        <label class="field-label">SKU<select class="field-input" name="sku_id"><option value="">Sin SKU</option>@foreach($skus as $sku)<option value="{{ $sku->id }}" @selected(old('sku_id', $asset->sku_id) === $sku->id)>{{ $sku->code }} · {{ $sku->product->name }}</option>@endforeach</select></label>
        <label class="field-label">Tipo<select class="field-input" name="mobility"><option value="mobile" @selected(old('mobility', $asset->mobility) === 'mobile')>Móvil</option><option value="static" @selected(old('mobility', $asset->mobility) === 'static')>Estático</option></select></label>
        <label class="field-label">Ubicación asignada<select class="field-input" name="location_id"><option value="">Sin asignar</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', $asset->location_id) === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
        <label class="field-label">Estado<select class="field-input" name="status">@foreach(['active' => 'Activo', 'inactive' => 'Inactivo', 'maintenance' => 'Mantenimiento'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $asset->status ?: 'active') === $value)>{{ $label }}</option>@endforeach</select></label>
        <button class="btn-primary">Guardar</button>
    </form>

    @if($asset->exists)
        <section class="panel p-6">
            <h2 class="font-semibold">Asignación de dispositivo</h2>
            @if($current = $asset->deviceAssignments->first())
                <div class="my-4 rounded-xl bg-emerald-50 p-4 text-sm">
                    <strong>{{ $current->device->name }}</strong><br>
                    <code class="text-xs">{{ $current->device->identifier }}</code><br>
                    {{ $current->tracking_strategy }}
                    <form class="mt-2" method="POST" action="{{ route('asset-assignments.destroy', $current) }}">@csrf @method('DELETE')<button class="text-red-600">Finalizar asignación</button></form>
                </div>
            @endif

            @if($asset->mobility === 'mobile')
                <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="tracking_strategy" value="fixed_beacons_mobile_tracker">
                    <label class="field-label">
                        Tracker SenseCAP reportado
                        <input class="field-input font-mono" name="device_identifier" list="reported-trackers" value="{{ old('device_identifier') }}" required placeholder="DevEUI o identificador del tracker">
                        <span class="mt-1 block text-xs font-normal text-slate-400">Escribe el identificador o selecciónalo por nombre entre los equipos recibidos desde los conectores.</span>
                    </label>
                    <datalist id="reported-trackers">
                        @foreach($reportedTrackers as $tracker)
                            <option value="{{ $tracker->identifier }}">{{ $tracker->name }} · {{ $tracker->identifier }}</option>
                        @endforeach
                    </datalist>
                    @if($reportedTrackers->isEmpty())<p class="text-xs text-amber-700">Todavía no hay trackers reportados. Puedes escribir manualmente el DevEUI.</p>@endif
                    <button class="btn-primary">Asociar tracker al activo móvil</button>
                </form>

                <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-8 space-y-4 border-t pt-5">
                    @csrf
                    <input type="hidden" name="tracking_strategy" value="mobile_beacon_fixed_scanners">
                    <label class="field-label">Alternativa: beacon móvil<select class="field-input" name="device_id" required>@foreach($devices->where('type', 'beacon') as $device)<option value="{{ $device->id }}">{{ $device->name }} · {{ $device->identifier }}</option>@endforeach</select></label>
                    <button class="btn-secondary" @disabled($devices->where('type', 'beacon')->isEmpty())>Asignar beacon móvil</button>
                </form>
            @else
                <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="tracking_strategy" value="assigned_static">
                    <label class="field-label">Beacon del activo estático<select class="field-input" name="device_id" required>@foreach($devices->where('type', 'beacon') as $device)<option value="{{ $device->id }}">{{ $device->name }} · {{ $device->identifier }}</option>@endforeach</select></label>
                    <button class="btn-primary" @disabled($devices->where('type', 'beacon')->isEmpty())>Asignar beacon</button>
                </form>
            @endif

            <form class="mt-8 border-t pt-4" method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('¿Archivar este activo?')">@csrf @method('DELETE')<button class="text-sm text-red-600">Archivar activo</button></form>
        </section>
    @endif
</div>
@endsection
