@extends('layouts.app')

@section('title', 'Decoders de payload')
@section('heading', 'Decoders de payload')

@section('content')
    <section class="panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4"><div><h2 class="text-lg font-semibold text-slate-950">Normalizador tipo Datacake</h2><p class="mt-1 max-w-3xl text-sm leading-6 text-slate-500">Reconoce formatos por FPort o por un campo del payload y convierte sus lecturas a <code>mac</code> + <code>rssi</code>. Las rutas usan notación con puntos y son relativas a <code>uplink_message.decoded_payload</code>.</p></div></div>
        @if($connectors->isEmpty())
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Primero crea un conector de telemetría TTI o MQTT.</div>
        @else
            <form method="POST" action="{{ route('payload-profiles.store') }}" class="mt-6">@csrf
                @include('payload-profiles._fields', ['profile' => new \App\Models\PayloadDecoderProfile])
                <button class="btn-primary mt-5">Guardar perfil</button>
            </form>
        @endif
    </section>

    @if(session('preview'))
        <section class="panel mt-6 p-6"><h2 class="font-semibold text-slate-950">Vista previa: {{ session('preview.profile_name') }}</h2><p class="mt-1 text-sm text-slate-500">Resultado canónico que utilizará el motor de posicionamiento.</p><pre class="mt-4 overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs leading-6 text-slate-100"><code>{{ json_encode(session('preview.decoded'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre></section>
    @endif

    <section class="mt-6 space-y-4">
        @forelse($profiles as $profile)
            <details class="panel p-6" @if(session('preview.profile_id') === $profile->id) open @endif>
                <summary class="cursor-pointer"><span class="font-semibold text-slate-950">{{ $profile->name }}</span> <span class="status-badge status-{{ $profile->enabled ? 'active' : 'disabled' }} ml-2">{{ $profile->enabled ? 'Activo' : 'Inactivo' }}</span><span class="ml-2 text-xs text-slate-400">{{ $profile->connector?->name }} · prioridad {{ $profile->priority }} · {{ $profile->products->isEmpty() ? 'todos los productos' : $profile->products->pluck('name')->join(', ') }}</span></summary>
                <form method="POST" action="{{ route('payload-profiles.update', $profile) }}" class="mt-6">@csrf @method('PUT')
                    @include('payload-profiles._fields', compact('profile'))
                    <button class="btn-primary mt-5">Actualizar</button>
                </form>

                <form method="POST" action="{{ route('payload-profiles.preview', $profile) }}" class="mt-6 border-t border-slate-100 pt-6">@csrf
                    <label class="field-label">Payload completo de prueba<textarea class="field-input min-h-48 font-mono" name="sample_payload_json" required>{{ old('sample_payload_json', $profile->sample_payload ? json_encode($profile->sample_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : json_encode(['end_device_ids' => ['device_id' => 'tracker-001', 'dev_eui' => '0011223344556677'], 'uplink_message' => ['f_port' => $profile->match_f_port ?? 10, 'decoded_payload' => ['data' => ['readings' => [['address' => 'AA:BB:CC:DD:EE:01', 'signal' => -64]]]]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) }}</textarea></label>
                    <div class="mt-4 flex flex-wrap gap-3"><button class="btn-secondary">Probar transformación</button></div>
                </form>
                <form method="POST" action="{{ route('payload-profiles.destroy', $profile) }}" class="mt-4" onsubmit="return confirm('¿Eliminar este perfil?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-red-600">Eliminar perfil</button></form>
            </details>
        @empty
            <div class="panel empty-state">No hay perfiles de payload guardados.</div>
        @endforelse
    </section>
@endsection
