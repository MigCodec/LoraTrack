<form method="POST" action="{{ route('devices.store') }}" class="mt-4 space-y-4">
    @csrf
    <p class="text-xs leading-relaxed text-slate-500">Registra hardware antes de colocarlo en un plano. Los AP recibidos desde Meraki se tratan como scanners porque detectan dispositivos y reportan observaciones.</p>
    <label class="field-label">Nombre<input class="field-input" name="name" required placeholder="AP bodega norte o Beacon acceso 1"></label>
    <label class="field-label">MAC / identificador
        <input class="field-input font-mono" name="identifier" list="reported-beacon-macs" required>
        <span class="mt-1 block text-xs font-normal text-slate-400">Para AP Meraki usa la MAC del AP. Para beacons BLE usa la MAC del beacon observado.</span>
    </label>
    <label class="field-label">Tipo
        <select class="field-input" name="type">
            <option value="beacon">Beacon BLE</option>
            <option value="scanner">Scanner/AP Meraki</option>
            <option value="lorawan_tracker">Tracker LoRaWAN</option>
        </select>
        <span class="mt-1 block text-xs font-normal text-slate-400">Beacon: va en un activo o como referencia fija. Scanner/AP: infraestructura fija que observa beacons.</span>
    </label>
    <label class="field-label">Modelo<input class="field-input" name="model" placeholder="Cisco Meraki MR, SenseCAP, fabricante y modelo"></label>
    <p class="text-xs leading-relaxed text-slate-500">Registrar no lo ubica todavia. Para usarlo en calculos o mapas, despues debes colocarlo como ancla en el plano.</p>
    <button class="btn-primary w-full">Crear dispositivo</button>
</form>
