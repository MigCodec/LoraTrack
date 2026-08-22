@extends('layouts.app')

@section('title', 'Consulta recibida')
@section('heading', 'Detalle de telemetría')

@section('content')
    <div class="mb-6">
        <a class="text-sm text-brand-primary" href="{{ route('connectors.show', $connector) }}">← Volver a {{ $connector->name }}</a>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Recepción', $event->received_at?->format('d-m-Y H:i:s') ?? '—'],
            ['Estado', $event->processing_status],
            ['Dispositivo', $event->device?->name ?? data_get($event->raw_payload, 'end_device_ids.device_id', '—')],
            ['Observaciones', $event->signalObservations->count()],
        ] as $metric)
            <article class="metric-card"><p class="text-sm text-slate-500">{{ $metric[0] }}</p><p class="mt-2 break-all font-semibold">{{ $metric[1] }}</p></article>
        @endforeach
    </div>

    @if($event->processing_error)
        <div class="mt-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><strong>Error de procesamiento:</strong> {{ $event->processing_error }}</div>
    @endif

    @if(($event->payload_storage_version ?? 1) >= 2)
        <div class="mt-6 rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-700">
            Este evento ya fue normalizado. Las señales están en observaciones, los AP en dispositivos y el resultado en el historial de posiciones; el JSON transitorio fue eliminado.
        </div>
    @else
    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="panel-header"><div><h2 class="panel-title">Datos de origen</h2><p class="panel-subtitle">{{ ($event->payload_storage_version ?? 1) >= 2 ? 'Resumen auditable; las señales y los AP se almacenan por separado' : 'Contenido conservado para procesamiento o compatibilidad' }}</p></div></div>
            <pre class="max-h-[42rem] overflow-auto bg-slate-950 p-5 text-xs leading-6 text-slate-100"><code>{{ json_encode($event->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
        </section>
        <section class="panel overflow-hidden">
            <div class="panel-header"><div><h2 class="panel-title">Payload normalizado</h2><p class="panel-subtitle">Campos compactos del resultado; no duplica señales ni inventario de AP</p></div></div>
            <pre class="max-h-[42rem] overflow-auto bg-slate-950 p-5 text-xs leading-6 text-slate-100"><code>{{ json_encode($event->normalized_payload ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</code></pre>
        </section>
    </div>
    @endif
@endsection
