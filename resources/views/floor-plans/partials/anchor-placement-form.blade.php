<form id="anchor-form" method="POST" action="{{ route('installations.store', $selectedPlan) }}" class="mt-4 space-y-4 fixed-reference-form">
    @csrf
    <p class="text-xs leading-relaxed text-slate-500">Agrega infraestructura fija con coordenadas conocidas. Elige una de las dos topologias para no mezclar beacons fijos con AP/scanners fijos.</p>
    <fieldset class="fixed-reference-type-picker">
        <legend>Tipo de punto de referencia</legend>
        <label>
            <input type="radio" name="reference_type" value="beacon" class="js-reference-type" @checked(old('reference_type', old('device_type', 'beacon')) === 'beacon')>
            <span><strong>Beacon BLE fijo</strong><small>Lo detecta el tracker SenseCAP T1000B movil para ubicar el asset asociado.</small></span>
        </label>
        <label>
            <input type="radio" name="reference_type" value="scanner" class="js-reference-type" @checked(old('reference_type', old('device_type')) === 'scanner')>
            <span><strong>AP Meraki / scanner fijo</strong><small>Detecta la MAC BLE del tag pegado al asset y reporta observaciones.</small></span>
        </label>
    </fieldset>
    <div class="fixed-reference-guidance" data-reference-guidance>
        Selecciona un tipo para filtrar dispositivos existentes y crear nuevos con el rol correcto.
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="field-label sm:col-span-2">Dispositivo
            <select class="field-input js-installation-device-select" name="device_id" data-placeholder="Buscar dispositivo fijo existente">
                <option value="">Crear nuevo por MAC</option>
            </select>
            <span class="mt-1 block text-xs font-normal text-slate-400">Busca solo dentro del tipo seleccionado. Si no existe, deja este campo vacio y completa la MAC.</span>
        </label>
        <label class="field-label">MAC del dispositivo
            <select class="field-input font-mono js-observed-mac-select" name="device_identifier" data-placeholder="Buscar o escribir MAC">
                <option value=""></option>
            </select>
            <span class="mt-1 block text-xs font-normal text-slate-400">Para beacon fijo usa la MAC del beacon. Para AP Meraki/scanner usa la MAC del AP o scanner.</span>
        </label>
        <label class="field-label">Nombre si es nuevo<input class="field-input" name="device_name" placeholder="Beacon acceso norte o AP bodega norte"></label>
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
        <p class="text-xs leading-relaxed text-slate-500 sm:col-span-2">En planos 2D puedes elegir el punto en el plano. En modelos 3D sin vista 2D, escribe X/Y/Z en metros.</p>
    </div>
    <input id="anchor-x" type="hidden" name="x_normalized"><input id="anchor-y" type="hidden" name="y_normalized">
    @if($selectedPlan->drawablePath())
        <button id="anchor-mode" class="btn-secondary" type="button">Elegir punto en el plano</button>
        <p id="anchor-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Haz clic en "Elegir punto" y luego sobre el plano, o escribe X/Y en metros.</p>
    @else
        <p id="anchor-selection-status" class="rounded-lg bg-slate-50 p-3 text-xs text-slate-600">Este modelo no tiene vista 2D para seleccionar con clic. Escribe las coordenadas en metros.</p>
    @endif
    <details class="fixed-reference-advanced">
        <summary>Parametros de calibracion avanzados</summary>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <label class="field-label">RSSI a 1 m<input class="field-input" type="number" name="reference_rssi" value="{{ old('reference_rssi', -59) }}" min="-127" max="-1" required></label>
            <label class="field-label">Factor ambiental<input class="field-input" type="number" name="path_loss_exponent" value="{{ old('path_loss_exponent', 2.0) }}" min="0.5" max="8" step="0.1" required></label>
        </div>
    </details>
    <div class="flex justify-end"><button id="anchor-submit" class="btn-primary">Guardar punto de referencia</button></div>
</form>
