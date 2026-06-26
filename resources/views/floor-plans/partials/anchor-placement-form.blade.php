<form id="anchor-form" method="POST" action="{{ route('installations.store', $selectedPlan) }}" class="mt-4 space-y-4">
    @csrf
    <p class="text-xs leading-relaxed text-slate-500">Selecciona un beacon/scanner existente o crea uno nuevo por MAC. En Meraki, el AP se registra como scanner.</p>
    <div class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">
        Si eliges un dispositivo registrado, se usara ese equipo y su tipo actual. Si dejas el selector en "Crear nuevo por MAC", se creara un beacon o scanner/AP segun el tipo elegido abajo.
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="field-label">Dispositivo registrado
            <select class="field-input js-installation-device-select" name="device_id" data-placeholder="Buscar beacon, scanner o AP">
                <option value="">Crear nuevo por MAC</option>
            </select>
            <span class="mt-1 block text-xs font-normal text-slate-400">Busca por nombre, modelo o MAC. No se cargan todos los dispositivos al abrir el formulario.</span>
        </label>
        <label class="field-label">Tipo del nuevo dispositivo
            <select class="field-input" name="device_type">
                <option value="beacon">Beacon BLE</option>
                <option value="scanner">Scanner/AP Meraki</option>
            </select>
            <span class="mt-1 block text-xs font-normal text-slate-400">Solo aplica si creas un dispositivo nuevo por MAC.</span>
        </label>
        <label class="field-label">MAC del dispositivo
            <select class="field-input font-mono js-observed-mac-select" name="device_identifier" data-placeholder="Buscar o escribir MAC">
                <option value=""></option>
            </select>
            <span class="mt-1 block text-xs font-normal text-slate-400">Busca MACs observadas o escribe una nueva. Para Meraki usa la MAC del AP; se guardara como scanner.</span>
        </label>
        <label class="field-label">Nombre del nuevo dispositivo<input class="field-input" name="device_name" placeholder="AP bodega norte"></label>
        <div class="grid grid-cols-3 gap-3 sm:col-span-2">
            <label class="field-label">X en metros
                <input class="field-input" type="number" name="x_meters" min="0" max="{{ $selectedPlan->width_meters }}" step="0.001" placeholder="0.000">
            </label>
            <label class="field-label">Y en metros
                <input class="field-input" type="number" name="y_meters" min="0" max="{{ $selectedPlan->height_meters }}" step="0.001" placeholder="0.000">
            </label>
            <label class="field-label">Z en metros
                <input class="field-input" type="number" name="z_meters" min="0" @if($selectedPlan->depth_meters) max="{{ $selectedPlan->depth_meters }}" @endif step="0.001" placeholder="{{ $selectedPlan->isThreeDimensional() ? 'Altura' : 'Opcional' }}">
            </label>
        </div>
        <p class="text-xs leading-relaxed text-slate-500 sm:col-span-2">En planos 2D puedes escribir X/Y o usar el boton de seleccion. En modelos 3D sin vista 2D, ingresa las coordenadas en metros; Z es la altura del AP/scanner o beacon dentro del modelo.</p>
        <div class="grid grid-cols-2 gap-3">
            <label class="field-label">RSSI a 1 m<input class="field-input" type="number" name="reference_rssi" value="-59" min="-127" max="-1" required></label>
            <label class="field-label">Factor ambiental<input class="field-input" type="number" name="path_loss_exponent" value="2.0" min="0.5" max="8" step="0.1" required></label>
        </div>
    </div>
    <input id="anchor-x" type="hidden" name="x_normalized"><input id="anchor-y" type="hidden" name="y_normalized">
    @if($selectedPlan->drawablePath())
        <button id="anchor-mode" class="btn-secondary" type="button">Seleccionar punto en el plano</button>
        <p id="anchor-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Haz clic en "Seleccionar punto" y luego sobre el plano, o escribe X/Y en metros.</p>
    @else
        <p id="anchor-selection-status" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Este modelo no tiene vista 2D para seleccionar con clic. Escribe las coordenadas en metros.</p>
    @endif
    <div class="flex justify-end"><button id="anchor-submit" class="btn-primary">Guardar instalacion</button></div>
</form>
