@extends('layouts.app')

@section('title', 'Dispositivos compatibles')
@section('heading', 'Dispositivos compatibles')

@section('content')
    <div class="space-y-6">
        <section class="panel p-6">
            <p class="max-w-4xl text-sm leading-7 text-slate-600">LoraTrack es compatible por protocolo: el hardware debe entregar una MAC BLE estable y su RSSI. El modelo exacto se registra para trazabilidad, pero un equipo no debe considerarse homologado hasta validar su payload real, decoder y firmware.</p>
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"><strong>B1000:</strong> es el tracker objetivo del proyecto. Antes de desplegarlo debe registrarse el fabricante y modelo completo y validarse que el uplink incluya las MAC detectadas y el RSSI de cada beacon. El nombre “B1000” por sí solo no identifica inequívocamente un fabricante.</div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="panel p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-accent">Modo 1</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-950">Activo móvil con tracker LoRaWAN</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">El tracker B1000 viaja con el activo. Cuatro o más beacons BLE quedan instalados en coordenadas conocidas del plano.</p>
                <ol class="mt-4 list-inside list-decimal space-y-2 text-sm text-slate-600">
                    <li>Registrar los beacons BLE y colocarlos como anclas en Planos y zonas.</li>
                    <li>Registrar el tracker como <strong>Tracker LoRaWAN</strong>.</li>
                    <li>Asignarlo al activo con “Tracker móvil detecta beacons fijos”.</li>
                    <li>Enviar por TTI o MQTT la lista de MAC y RSSI observados.</li>
                </ol>
            </section>

            <section class="panel p-6">
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-brand-accent">Modo 2</p>
                <h2 class="mt-2 text-lg font-semibold text-slate-950">Activo con beacon BLE</h2>
                <p class="mt-3 text-sm leading-6 text-slate-600">El beacon se une al activo. Cuatro o más scanners BLE quedan instalados en coordenadas conocidas y reportan por la infraestructura LoRaWAN/HTTP/MQTT.</p>
                <ol class="mt-4 list-inside list-decimal space-y-2 text-sm text-slate-600">
                    <li>Registrar y colocar los scanners como anclas fijas del plano.</li>
                    <li>Registrar el beacon con su MAC estable.</li>
                    <li>Asignarlo al activo; no colocarlo como ancla.</li>
                    <li>Cada scanner debe reportar MAC del beacon, RSSI e identificador del receptor.</li>
                </ol>
            </section>
        </div>

        <section class="panel overflow-hidden">
            <div class="panel-header"><div><h2 class="panel-title">Matriz de compatibilidad</h2><p class="panel-subtitle">Requisitos mínimos para integrar hardware</p></div></div>
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Tipo</th><th>Estado</th><th>Requisitos</th><th>Uso</th></tr></thead>
                    <tbody>
                        <tr><td>Tracker B1000</td><td><span class="status-badge status-disabled">Por homologar</span></td><td>LoRaWAN, escaneo BLE, múltiples MAC+RSSI por uplink, DevEUI estable y decoder TTI.</td><td>Viaja con el activo móvil.</td></tr>
                        <tr><td>Beacon BLE genérico</td><td><span class="status-badge status-active">Compatible por protocolo</span></td><td>BLE 4.x/5.x, MAC estable, advertising continuo y potencia TX configurable. Recomendado: intervalo configurable y batería reemplazable.</td><td>Ancla fija o dispositivo unido al activo.</td></tr>
                        <tr><td>Scanner BLE</td><td><span class="status-badge status-active">Compatible por protocolo</span></td><td>Reportar MAC, RSSI, identificador del scanner y fecha. Salida mediante LoRaWAN, HTTPS o MQTT.</td><td>Ancla fija para localizar beacons.</td></tr>
                        <tr><td>Gateway LoRaWAN</td><td><span class="status-badge status-active">Gestionado por TTI</span></td><td>Compatible con The Things Stack y la banda regional configurada. No reemplaza al scanner BLE.</td><td>Transporta uplinks LoRaWAN.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel p-6">
            <h2 class="text-lg font-semibold text-slate-950">Contrato de telemetría mínimo</h2>
            <p class="mt-2 text-sm text-slate-600">El decoder puede entregar una lista en <code>observations</code>, <code>beacons</code>, <code>ble</code>, <code>scan</code> o <code>devices</code>. Cada lectura debe incluir una MAC y RSSI.</p>
            <pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>{
  "observations": [
    {"mac": "AA:BB:CC:DD:EE:01", "rssi": -64},
    {"mac": "AA:BB:CC:DD:EE:02", "rssi": -72},
    {"mac": "AA:BB:CC:DD:EE:03", "rssi": -81}
  ],
  "receiver_identifier": "B1000-001"
}</code></pre>
            <p class="mt-4 text-sm text-slate-600">LoraTrack exige al menos cuatro anclas no colineales en el mismo plano. La calibración utiliza RSSI a 1 metro y el factor de pérdida ambiental.</p>
        </section>
    </div>
@endsection
