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
