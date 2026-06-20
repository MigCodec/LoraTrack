<form method="POST" action="{{ route('devices.store') }}" class="mt-4 space-y-4">
    @csrf
    <label class="field-label">Nombre<input class="field-input" name="name" required></label>
    <label class="field-label">MAC / identificador<input class="field-input font-mono" name="identifier" list="reported-beacon-macs" required><span class="mt-1 block text-xs font-normal text-slate-400">Las MAC reportadas muestran como referencia el tracker y RSSI observados.</span></label>
    <label class="field-label">Tipo<select class="field-input" name="type"><option value="beacon">Beacon BLE</option><option value="scanner">Scanner BLE fijo</option><option value="lorawan_tracker">Tracker LoRaWAN</option></select></label>
    <label class="field-label">Modelo<input class="field-input" name="model" placeholder="Fabricante y modelo exacto"></label>
    <p class="text-xs leading-relaxed text-slate-500">Un beacon solo se vuelve ancla fija al instalarlo sobre el plano. Si va unido a un activo, asígnalo desde Activos y no lo instales como ancla.</p>
    <button class="btn-primary w-full">Crear dispositivo</button>
</form>
