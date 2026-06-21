<form id="anchor-form" method="POST" action="{{ route('installations.store', $selectedPlan) }}" class="mt-4 space-y-4">
    @csrf
    <p class="text-xs leading-relaxed text-slate-500">Selecciona el dispositivo y después marca su posición conocida directamente sobre el plano.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="field-label">Dispositivo registrado<select class="field-input" name="device_id"><option value="">Crear o seleccionar beacon por MAC</option>@foreach($devices->whereIn('type', ['beacon', 'scanner']) as $device)<option value="{{ $device->id }}">{{ $device->name }} · {{ $device->identifier }}</option>@endforeach</select></label>
        <label class="field-label">MAC del beacon<input class="field-input font-mono" name="device_identifier" list="reported-beacon-macs" placeholder="58:BE:6F:65:9D:9D"><span class="mt-1 block text-xs font-normal text-slate-400">Escríbela o selecciónala entre las MAC observadas.</span></label>
        <datalist id="reported-beacon-macs">@foreach($reportedBeaconMacs as $reported)<option value="{{ $reported['identifier'] }}">{{ $reported['tracker_name'] }} · RSSI {{ $reported['rssi'] }} dBm{{ $reported['connector_name'] ? ' · '.$reported['connector_name'] : '' }}</option>@endforeach</datalist>
        <label class="field-label">Nombre del nuevo beacon<input class="field-input" name="device_name" placeholder="Beacon acceso norte"></label>
        <div class="grid grid-cols-2 gap-3"><label class="field-label">RSSI a 1 m<input class="field-input" type="number" name="reference_rssi" value="-59" min="-127" max="-1" required></label><label class="field-label">Factor ambiental<input class="field-input" type="number" name="path_loss_exponent" value="2.0" min="0.5" max="8" step="0.1" required></label></div>
    </div>
    <input id="anchor-x" type="hidden" name="x_normalized" required><input id="anchor-y" type="hidden" name="y_normalized" required>
    <button id="anchor-mode" class="btn-secondary" type="button">Seleccionar punto en el plano</button>
    <p id="anchor-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Haz clic en “Seleccionar punto” y luego sobre el plano.</p>
    <div class="flex justify-end"><button id="anchor-submit" class="btn-primary" disabled>Guardar instalación</button></div>
</form>
