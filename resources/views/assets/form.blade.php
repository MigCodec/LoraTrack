@extends('layouts.app')
@section('title', $asset->exists ? 'Editar activo' : 'Nuevo activo')
@section('heading', $asset->exists ? 'Editar activo' : 'Nuevo activo')

@section('content')
<div class="grid max-w-5xl gap-6 lg:grid-cols-2">
    <form id="asset-form" class="panel space-y-4 p-6" method="POST" enctype="multipart/form-data" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
        @csrf
        @if($asset->exists) @method('PUT') @endif
        <label class="field-label">Nombre<input class="field-input" name="name" value="{{ old('name', $asset->name) }}" required></label>
        <label class="field-label">Código de activo<input class="field-input" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" required></label>
        <label class="field-label">Número de serie<input class="field-input" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}"></label>
        <label class="field-label">SKU<select class="field-input" name="sku_id"><option value="">Sin SKU</option>@foreach($skus as $sku)<option value="{{ $sku->id }}" @selected(old('sku_id', $asset->sku_id) === $sku->id)>{{ $sku->code }} · {{ $sku->product->name }}</option>@endforeach</select></label>
        <label class="field-label">Tipo<select class="field-input" name="mobility"><option value="mobile" @selected(old('mobility', $asset->mobility) === 'mobile')>Móvil</option><option value="static" @selected(old('mobility', $asset->mobility) === 'static')>Estático</option></select></label>
        <label class="field-label">Ubicación asignada<select class="field-input" name="location_id"><option value="">Sin asignar</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(old('location_id', $asset->location_id) === $location->id)>{{ $location->name }}</option>@endforeach</select></label>
        <label class="field-label">Estado<select class="field-input" name="status">@foreach(['active' => 'Activo', 'inactive' => 'Inactivo', 'maintenance' => 'Mantenimiento'] as $value => $label)<option value="{{ $value }}" @selected(old('status', $asset->status ?: 'active') === $value)>{{ $label }}</option>@endforeach</select></label>
        @unless($asset->exists)
            <label class="field-label" data-mobile-tracker-field>Tracker LoRaWAN inicial (opcional)
                <select class="field-input" name="tracker_device_id">
                    <option value="">Asignar después</option>
                    @foreach($reportedTrackers as $tracker)<option value="{{ $tracker->id }}" @selected(old('tracker_device_id') === $tracker->id)>{{ $tracker->name }} · {{ $tracker->model ?: 'Sin modelo' }} · {{ $tracker->identifier }}</option>@endforeach
                </select>
                <span class="mt-1 block text-xs font-normal text-slate-400">Solo aparecen trackers activos y sin asignación dentro de esta empresa.</span>
            </label>
        @endunless
        <label class="field-label">Fotografía del activo (opcional)<input class="field-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp"><span class="mt-1 block text-xs font-normal text-slate-400">JPG, PNG o WEBP; máximo 8 MB. Se almacena de forma privada.</span></label>
        @if($asset->exists && $asset->photo_path)
            <div class="rounded-xl border border-slate-200 p-3"><img class="max-h-52 w-full rounded-lg object-contain" src="{{ route('assets.photo', $asset) }}" alt="Fotografía de {{ $asset->name }}"><label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_photo" value="1"> Eliminar fotografía actual</label></div>
        @endif
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
                    @if($asset->mobility === 'mobile' && $current->tracking_strategy === 'fixed_beacons_mobile_tracker')<form class="mt-3" method="POST" action="{{ route('assets.position.refresh', $asset) }}">@csrf<button class="btn-secondary" type="submit">Recalcular ubicación</button></form>@endif
                    <form class="mt-2" method="POST" action="{{ route('asset-assignments.destroy', $current) }}">@csrf @method('DELETE')<button class="text-red-600">Finalizar asignación</button></form>
                </div>
            @endif

            @if($asset->mobility === 'mobile')
                <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="tracking_strategy" value="fixed_beacons_mobile_tracker">
                    <label class="field-label">Tracker registrado
                        <select class="field-input" name="device_id"><option value="">Ingresar identificador manualmente</option>@foreach($reportedTrackers as $tracker)<option value="{{ $tracker->id }}">{{ $tracker->name }} · {{ $tracker->model ?: 'Sin modelo' }} · {{ $tracker->identifier }}</option>@endforeach</select>
                        <span class="mt-1 block text-xs font-normal text-slate-400">Inventario de trackers activos, sin asignación y pertenecientes a esta empresa.</span>
                    </label>
                    <label class="field-label">Identificador manual alternativo<input class="field-input font-mono" name="device_identifier" value="{{ old('device_identifier') }}" placeholder="DevEUI o identificador del tracker"></label>
                    @if($reportedTrackers->isEmpty())<p class="text-xs text-amber-700">No hay trackers disponibles. Puedes escribir manualmente el DevEUI.</p>@endif
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
