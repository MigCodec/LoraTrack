@extends('layouts.app')

@section('title', $asset->exists ? 'Editar activo' : 'Nuevo activo')
@section('heading', $asset->exists ? 'Editar activo' : 'Nuevo activo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('vendor/select2/select2.min.css') }}">
@endpush

@section('content')
    <div class="grid max-w-5xl gap-6 lg:grid-cols-2">
        <form id="asset-form" class="panel space-y-4 p-6" method="POST" enctype="multipart/form-data" action="{{ $asset->exists ? route('assets.update', $asset) : route('assets.store') }}">
            @csrf
            @if($asset->exists)
                @method('PUT')
            @endif

            <label class="field-label">Nombre<input class="field-input" name="name" value="{{ old('name', $asset->name) }}" required></label>
            <label class="field-label">Codigo de activo<input class="field-input" name="asset_tag" value="{{ old('asset_tag', $asset->asset_tag) }}" required></label>
            <label class="field-label">Numero de serie<input class="field-input" name="serial_number" value="{{ old('serial_number', $asset->serial_number) }}"></label>
            <label class="field-label">SKU
                <select class="field-input" name="sku_id">
                    <option value="">Sin SKU</option>
                    @foreach($skus as $sku)
                        <option value="{{ $sku->id }}" @selected(old('sku_id', $asset->sku_id) === $sku->id)>{{ $sku->code }} - {{ $sku->product->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field-label">Tipo
                <select class="field-input" name="mobility">
                    <option value="mobile" @selected(old('mobility', $asset->mobility) === 'mobile')>Movil</option>
                    <option value="static" @selected(old('mobility', $asset->mobility) === 'static')>Estatico</option>
                </select>
            </label>
            <label class="field-label">Ubicacion asignada
                <select class="field-input" name="location_id">
                    <option value="">Sin asignar</option>
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" @selected(old('location_id', $asset->location_id) === $location->id)>{{ $location->name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="field-label">Estado
                <select class="field-input" name="status">
                    @foreach(['active' => 'Activo', 'inactive' => 'Inactivo', 'maintenance' => 'Mantenimiento'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $asset->status ?: 'active') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            @unless($asset->exists)
                <label class="field-label" data-mobile-tracker-field>Tracker LoRaWAN inicial (opcional)
                    <select class="field-input js-device-select" name="tracker_device_id" data-device-type="lorawan_tracker" data-placeholder="Escribe nombre, modelo o DevEUI">
                        <option value="">Asignar despues</option>
                        @if($selectedInitialTracker)
                            <option value="{{ $selectedInitialTracker->id }}" selected>{{ collect([$selectedInitialTracker->name, $selectedInitialTracker->model ?: null, $selectedInitialTracker->identifier])->filter()->join(' - ') }}</option>
                        @endif
                    </select>
                    <span class="mt-1 block text-xs font-normal text-slate-400">Busca por nombre, modelo o DevEUI. No se cargan trackers hasta que escribas al menos 2 caracteres.</span>
                </label>

                <label class="field-label" data-static-beacon-field>Beacon BLE inicial (opcional)
                    <select class="field-input js-device-select" name="static_beacon_device_id" data-device-type="beacon" data-placeholder="Escribe nombre o MAC del beacon">
                        <option value="">Asignar despues</option>
                        @if($selectedInitialBeacon)
                            <option value="{{ $selectedInitialBeacon->id }}" selected>{{ collect([$selectedInitialBeacon->name, $selectedInitialBeacon->identifier])->filter()->join(' - ') }}</option>
                        @endif
                    </select>
                    <span class="mt-1 block text-xs font-normal text-slate-400">Para activos estaticos, escribe la MAC o nombre del beacon. Solo se devuelven beacons activos y sin asignacion.</span>
                </label>
            @endunless

            <label class="field-label">Fotografia del activo (opcional)
                <input class="field-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp">
                <span class="mt-1 block text-xs font-normal text-slate-400">JPG, PNG o WEBP; maximo 8 MB. Se almacena de forma privada.</span>
            </label>

            @if($asset->exists && $asset->photo_path)
                <div class="rounded-xl border border-slate-200 p-3">
                    <img class="max-h-52 w-full rounded-lg object-contain" src="{{ route('assets.photo', $asset) }}" alt="Fotografia de {{ $asset->name }}">
                    <label class="mt-3 flex items-center gap-2 text-sm"><input type="checkbox" name="remove_photo" value="1"> Eliminar fotografia actual</label>
                </div>
            @endif

            <button class="btn-primary">Guardar</button>
        </form>

        @if($asset->exists)
            <section class="panel p-6">
                <h2 class="font-semibold">Asignacion de dispositivo</h2>
                @if($current = $asset->deviceAssignments->first())
                    <div class="my-4 rounded-xl bg-emerald-50 p-4 text-sm">
                        <strong>{{ $current->device->name }}</strong><br>
                        <code class="text-xs">{{ $current->device->identifier }}</code><br>
                        {{ $current->tracking_strategy }}
                        @if($asset->mobility === 'mobile' && $current->tracking_strategy === 'fixed_beacons_mobile_tracker')
                            <form class="mt-3" method="POST" action="{{ route('assets.position.refresh', $asset) }}">
                                @csrf
                                <button class="btn-secondary" type="submit">Recalcular ubicacion</button>
                            </form>
                        @endif
                        <form class="mt-2" method="POST" action="{{ route('asset-assignments.destroy', $current) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600">Finalizar asignacion</button>
                        </form>
                    </div>
                @endif

                @if($asset->mobility === 'mobile')
                    <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="tracking_strategy" value="fixed_beacons_mobile_tracker">
                        <label class="field-label">Tracker registrado
                            <select class="field-input js-device-select" name="device_id" data-device-type="lorawan_tracker" data-placeholder="Escribe nombre, modelo o DevEUI">
                                <option value="">Ingresar identificador manualmente</option>
                                @if($selectedAssignmentDevice?->type === 'lorawan_tracker')
                                    <option value="{{ $selectedAssignmentDevice->id }}" selected>{{ collect([$selectedAssignmentDevice->name, $selectedAssignmentDevice->model ?: null, $selectedAssignmentDevice->identifier])->filter()->join(' - ') }}</option>
                                @endif
                            </select>
                            <span class="mt-1 block text-xs font-normal text-slate-400">Inventario de trackers activos y sin asignacion. La lista se consulta mientras escribes.</span>
                        </label>
                        <label class="field-label">Identificador manual alternativo<input class="field-input font-mono" name="device_identifier" value="{{ old('device_identifier') }}" placeholder="DevEUI o identificador del tracker"></label>
                        <button class="btn-primary">Asociar tracker al activo movil</button>
                    </form>

                    <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-8 space-y-4 border-t pt-5">
                        @csrf
                        <input type="hidden" name="tracking_strategy" value="mobile_beacon_fixed_scanners">
                        <label class="field-label">Alternativa: beacon movil
                            <select class="field-input js-device-select" name="device_id" required data-device-type="beacon" data-placeholder="Escribe nombre o MAC del beacon">
                                <option value=""></option>
                                @if($selectedAssignmentDevice?->type === 'beacon')
                                    <option value="{{ $selectedAssignmentDevice->id }}" selected>{{ collect([$selectedAssignmentDevice->name, $selectedAssignmentDevice->identifier])->filter()->join(' - ') }}</option>
                                @endif
                            </select>
                        </label>
                        <button class="btn-secondary">Asignar beacon movil</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('asset-assignments.store', $asset) }}" class="mt-5 space-y-4">
                        @csrf
                        <input type="hidden" name="tracking_strategy" value="mobile_beacon_fixed_scanners">
                        <label class="field-label">Beacon del activo estatico
                            <select class="field-input js-device-select" name="device_id" required data-device-type="beacon" data-placeholder="Escribe nombre o MAC del beacon">
                                <option value=""></option>
                                @if($selectedAssignmentDevice?->type === 'beacon')
                                    <option value="{{ $selectedAssignmentDevice->id }}" selected>{{ collect([$selectedAssignmentDevice->name, $selectedAssignmentDevice->identifier])->filter()->join(' - ') }}</option>
                                @endif
                            </select>
                        </label>
                        <button class="btn-primary">Asignar beacon</button>
                    </form>
                @endif

                <form class="mt-8 border-t pt-4" method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Archivar este activo?')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm text-red-600">Archivar activo</button>
                </form>
            </section>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/jquery/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('vendor/select2/select2.min.js') }}"></script>
    <script>
        jQuery(function ($) {
            $('.js-device-select').select2({
                ajax: {
                    url: @json(route('assets.device-options')),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            q: params.term || '',
                            type: $(this).data('device-type')
                        };
                    },
                    processResults: function (data) {
                        return {results: data.results || []};
                    },
                    cache: true
                },
                allowClear: true,
                minimumInputLength: 2,
                placeholder: function () {
                    return $(this).data('placeholder') || 'Buscar dispositivo';
                },
                width: '100%',
                language: {
                    inputTooShort: function () { return 'Escribe al menos 2 caracteres.'; },
                    noResults: function () { return 'Sin coincidencias.'; },
                    searching: function () { return 'Buscando...'; },
                    errorLoading: function () { return 'No se pudo cargar la busqueda.'; }
                }
            });

            var mobility = document.querySelector('#asset-form [name="mobility"]');
            mobility?.addEventListener('change', function () {
                window.setTimeout(function () {
                    $('.js-device-select').trigger('change.select2');
                }, 0);
            });
            mobility?.dispatchEvent(new Event('change', {bubbles: true}));
        });
    </script>
@endpush
