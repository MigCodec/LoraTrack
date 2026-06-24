<section class="panel mt-6 p-5">
    <h2 class="panel-title">Tutorial de configuración</h2>

    @switch($connector->provider->value)
        @case('tti_webhook')
            <p class="mt-2 text-sm text-slate-600">Configura un Webhook en The Things Stack con estos valores:</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4 text-sm"><dl class="space-y-3"><div><dt class="font-semibold">Webhook ID</dt><dd class="text-slate-500">loratrack-{{ Str::lower(Str::substr($connector->id, -8)) }}</dd></div><div><dt class="font-semibold">Formato</dt><dd class="text-slate-500">JSON</dd></div><div><dt class="font-semibold">Base URL</dt><dd><code class="mt-1 block break-all rounded bg-slate-100 p-2 text-xs">{{ route('api.tti.ingest', $connector) }}</code></dd></div><div><dt class="font-semibold">Header personalizado</dt><dd><code class="mt-1 block rounded bg-slate-100 p-2 text-xs">Authorization: Bearer TU_TOKEN</code></dd></div></dl></div>
                <div class="rounded-xl border border-slate-200 p-4 text-sm"><p class="font-semibold">Eventos habilitados</p><label class="mt-3 flex gap-2 text-slate-700"><input type="checkbox" checked disabled> An uplink message is received by the application</label><p class="mt-3 text-xs leading-5 text-slate-500">Deja vacíos Downlink API key, Filter path y la ruta opcional del uplink. No habilites joins, downlinks, ubicación, integración ni normalized uplink.</p><p class="mt-3 text-xs leading-5 text-slate-500">El conector debe estar <strong>Activo</strong>. TTI debe poder acceder a esta URL mediante HTTPS público.</p></div>
            </div>
            @if(session('new_webhook_token'))<div class="mt-4 rounded-xl border border-amber-300 bg-amber-50 p-4"><p class="text-sm font-semibold text-amber-900">Token nuevo — cópialo ahora</p><code class="mt-2 block break-all rounded bg-white p-3 text-xs text-amber-900">{{ session('new_webhook_token') }}</code></div>@endif
            <div class="mt-4 flex flex-wrap items-center gap-3"><form method="POST" action="{{ route('connectors.rotate-webhook-token', $connector) }}" onsubmit="return confirm('¿Renovar el token? El token anterior dejará de funcionar inmediatamente.')">@csrf<button class="btn-secondary">Generar token nuevo</button></form><span class="text-xs text-slate-500">Por seguridad el token almacenado no vuelve a mostrarse.</span></div>
            @break

        @case('meraki_location')
            <p class="mt-2 text-sm text-slate-600">Configura Cisco Meraki Scanning/Location API con la misma URL para validación GET y recepción POST.</p>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-slate-200 p-4 text-sm">
                    <dl class="space-y-3">
                        <div><dt class="font-semibold">Post URL</dt><dd><code class="mt-1 block break-all rounded bg-slate-100 p-2 text-xs">{{ route('api.meraki.ingest', $connector) }}</code></dd></div>
                        <div><dt class="font-semibold">Versión aceptada</dt><dd class="text-slate-500">v{{ $connector->configuration['api_version'] ?? '3' }}{{ ($connector->configuration['api_version'] ?? '3') === '3' ? '.x' : '.1' }}</dd></div>
                        <div><dt class="font-semibold">Network ID</dt><dd class="text-slate-500">{{ $connector->configuration['network_id'] ?? 'Cualquier red con el shared secret correcto' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 text-sm">
                    <ol class="list-inside list-decimal space-y-2 text-slate-600">
                        <li>En Meraki Dashboard habilita Scanning API para la red MR.</li>
                        <li>Usa esta Post URL y los mismos validator/shared secret guardados en el conector.</li>
                        <li>Valida el receptor y después activa el conector en LoraTrack.</li>
                        <li>Asocia los floorPlanId de Meraki con los planos locales abajo.</li>
                    </ol>
                </div>
            </div>

            <details class="mt-6 rounded-xl border border-slate-200 p-4">
                <summary class="cursor-pointer font-semibold text-slate-950">Actualizar validator o shared secret</summary>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">Los valores actuales están cifrados y no se muestran. Completa solamente el valor que quieras reemplazar.</p>
                <form class="mt-4 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('connectors.meraki-credentials.update', $connector) }}">
                    @csrf
                    @method('PUT')
                    <label class="field-label">Validator proporcionado por Meraki
                        <input class="field-input" id="meraki-validator-update" type="password" name="validator" minlength="8" maxlength="255" autocomplete="new-password" placeholder="Dejar vacío para conservar el actual">
                        <span class="mt-2 block text-xs leading-relaxed text-slate-500">Si Meraki muestra un validator nuevo, cópialo aquí. LoraTrack no puede generarlo.</span>
                    </label>
                    <label class="field-label">Shared secret nuevo
                        <input class="field-input" id="meraki-secret-update" type="password" name="shared_secret" minlength="16" maxlength="255" autocomplete="new-password" placeholder="Dejar vacío para conservar el actual">
                        <span class="mt-2 flex flex-wrap items-center gap-2">
                            <button class="btn-secondary" type="button" data-generate-secret="meraki-secret-update">Generar secret seguro</button>
                            <button class="text-xs font-semibold text-brand-primary" type="button" data-copy-secret="meraki-secret-update">Copiar</button>
                        </span>
                    </label>
                    <div class="md:col-span-2 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-800">Si reemplazas el shared secret, debes copiar el mismo valor en Meraki Dashboard antes del siguiente envío. El valor anterior dejará de funcionar inmediatamente.</div>
                    <div class="md:col-span-2"><button class="btn-primary">Actualizar credenciales</button></div>
                </form>
            </details>

            <div class="mt-6 rounded-xl border border-slate-200 p-4">
                <h3 class="font-semibold text-slate-950">Mapeo de planos Meraki</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500">Meraki expresa X/Y desde la esquina inferior izquierda. La inversión Y está activada por defecto para el lienzo web de LoraTrack.</p>
                <form class="mt-4 grid gap-4 md:grid-cols-2" method="POST" action="{{ route('connectors.meraki-floor-plans.store', $connector) }}">
                    @csrf
                    <label class="field-label">Floor plan ID o nombre externo
                        <input class="field-input" name="external_floor_plan_id" required maxlength="255" placeholder="g_643451796760560979">
                    </label>
                    <label class="field-label">Nombre externo (opcional)
                        <input class="field-input" name="external_floor_plan_name" maxlength="255" placeholder="Bodega Norte">
                    </label>
                    <label class="field-label">Plano LoraTrack
                        <select class="field-input" name="floor_plan_id" required>
                            <option value="">Seleccionar plano</option>
                            @foreach($floorPlans as $floorPlan)
                                <option value="{{ $floorPlan->id }}">{{ $floorPlan->location?->name }} · {{ $floorPlan->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="mt-7 flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="invert_y" value="0">
                        <input type="checkbox" name="invert_y" value="1" checked>
                        Convertir eje Y de Meraki al lienzo
                    </label>
                    <div class="md:col-span-2"><button class="btn-primary">Agregar mapeo</button></div>
                </form>

                @if($merakiMappings->isNotEmpty())
                    <div class="table-wrap mt-5"><table><thead><tr><th>Meraki</th><th>Plano local</th><th>Eje Y</th><th></th></tr></thead><tbody>
                    @foreach($merakiMappings as $mapping)
                        <tr>
                            <td><strong>{{ $mapping->external_floor_plan_name ?: $mapping->external_floor_plan_id }}</strong><br><span class="font-mono text-xs text-slate-400">{{ $mapping->external_floor_plan_id }}</span></td>
                            <td>{{ $mapping->floorPlan->location?->name }} · {{ $mapping->floorPlan->name }}</td>
                            <td>{{ $mapping->invert_y ? 'Convertido' : 'Sin invertir' }}</td>
                            <td><form method="POST" action="{{ route('connectors.meraki-floor-plans.destroy', [$connector, $mapping]) }}" onsubmit="return confirm('¿Eliminar este mapeo?')">@csrf @method('DELETE')<button class="text-sm text-red-600">Eliminar</button></form></td>
                        </tr>
                    @endforeach
                    </tbody></table></div>
                @endif
            </div>
            <script>
                (() => {
                    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';
                    document.querySelectorAll('[data-generate-secret]').forEach(button => {
                        if (button.dataset.secretHandler === 'ready') return;
                        button.dataset.secretHandler = 'ready';
                        button.addEventListener('click', () => {
                            const input = document.getElementById(button.dataset.generateSecret);
                            const bytes = crypto.getRandomValues(new Uint8Array(32));
                            input.value = Array.from(bytes, byte => alphabet[byte & 63]).join('');
                            input.type = 'text';
                            input.focus();
                            input.select();
                        });
                    });
                    document.querySelectorAll('[data-copy-secret]').forEach(button => {
                        if (button.dataset.secretHandler === 'ready') return;
                        button.dataset.secretHandler = 'ready';
                        button.addEventListener('click', async () => {
                            const input = document.getElementById(button.dataset.copySecret);
                            if (input.value) await navigator.clipboard.writeText(input.value);
                        });
                    });
                })();
            </script>
            @break

        @case('mqtt')
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600"><li>Activa el conector después de revisar host, puerto, TLS, credenciales y topic.</li><li>Ejecuta el listener mostrado en “Operación MQTT”.</li><li>Mantén también un worker de cola ejecutándose.</li><li>Publica JSON con MAC y RSSI y revisa el Log operacional.</li></ol>
            @break

        @case('sap_s4hana')
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600"><li>Habilita API_PRODUCT_SRV en SAP Gateway.</li><li>Configura URL base, ruta OData y autenticación Basic o Bearer.</li><li>Pulsa Probar y luego Activar.</li><li>Ejecuta Sincronizar y revisa eventos y errores.</li></ol>
            @break

        @case('business_central')
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600"><li>Registra una aplicación en Microsoft Entra ID.</li><li>Concede acceso a Business Central y configura tenant, environment y company.</li><li>Ingresa client ID y secret, prueba, activa y sincroniza.</li></ol>
            @break

        @case('shopify')
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600"><li>Crea una aplicación personalizada con permiso de lectura de productos.</li><li>Configura dominio de la tienda y Admin API access token.</li><li>Prueba, activa y sincroniza el catálogo.</li></ol>
            @break

        @case('odoo')
            <ol class="mt-3 list-inside list-decimal space-y-2 text-sm text-slate-600"><li>Habilita acceso a la API de Odoo para el usuario de integración.</li><li>Configura URL, base de datos, usuario y API key.</li><li>Prueba, activa y sincroniza.</li></ol>
            @break

        @case('csv')
            <p class="mt-3 text-sm text-slate-600">Carga un archivo UTF-8 con columnas obligatorias <code>sku</code> y <code>name</code>. Opcionales: <code>external_id</code>, <code>description</code>, <code>base_unit</code> y <code>status</code>.</p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('connectors.csv', $connector) }}" class="mt-4 flex flex-wrap items-center gap-3">@csrf<input class="field-input mt-0" type="file" name="file" accept=".csv" required><button class="btn-primary">Importar CSV</button></form>
            @break
    @endswitch
</section>
