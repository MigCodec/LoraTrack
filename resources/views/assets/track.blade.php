@extends('layouts.app')

@section('title', 'Recorrido de activo')
@section('heading', 'Recorrido de activo')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/floor-plan-editor.css') }}?v={{ filemtime(public_path('css/floor-plan-editor.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/asset-track.css') }}?v={{ filemtime(public_path('css/asset-track.css')) }}">
@endpush

@push('scripts')
    <script defer src="{{ asset('js/asset-track.js') }}?v={{ filemtime(public_path('js/asset-track.js')) }}"></script>
@endpush

@section('content')
    <section class="panel asset-track-page" data-asset-track data-endpoint="{{ route('assets.track.data', $asset) }}">
        <div class="asset-track-header">
            <div>
                <p class="asset-track-kicker">{{ $asset->mobility === 'mobile' ? 'Activo móvil' : 'Activo estático' }}</p>
                <h2>{{ $asset->name }}</h2>
                <p>{{ $asset->asset_tag }} @if($asset->sku) · {{ $asset->sku->code }} @endif @if($asset->sku?->product) · {{ $asset->sku->product->name }} @endif</p>
            </div>
            <div class="asset-track-actions">
                @if($asset->latestPosition?->floorPlan)
                    <a class="btn-secondary" href="{{ route('map.index', ['plan' => $asset->latestPosition->floor_plan_id]) }}">Ver en mapa operativo</a>
                @endif
                <a class="btn-secondary" href="{{ route('assets.index', ['mobility' => $asset->mobility]) }}">Volver a activos</a>
            </div>
        </div>

        <form class="asset-track-controls" data-track-controls>
            <label>
                <span>Plano</span>
                <select class="field-input" name="floor_plan_id" data-track-plan>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" data-file="{{ route('floor-plans.file', $plan) }}" @selected($selectedPlan?->is($plan))>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Rango</span>
                <select class="field-input" name="range" data-track-range>
                    <option value="1h">Última hora</option>
                    <option value="24h" selected>Últimas 24 horas</option>
                    <option value="7d">Últimos 7 días</option>
                    <option value="30d">Últimos 30 días</option>
                </select>
            </label>
            <label class="asset-track-live-toggle">
                <input type="checkbox" data-track-live>
                <span>Actualizar en vivo cada 30 segundos</span>
            </label>
        </form>

        @if(!$selectedPlan)
            <div class="empty-state">Este activo aún no tiene posiciones calculadas en un plano.</div>
        @elseif(!$selectedPlan->drawablePath())
            <div class="empty-state">El plano seleccionado necesita una vista previa raster para mostrar el recorrido.</div>
        @else
            <div class="asset-track-summary" aria-live="polite">
                <div><span>Puntos</span><strong data-track-count>—</strong></div>
                <div><span>Última posición</span><strong data-track-current>—</strong></div>
                <div><span>Estado</span><strong data-track-status>Consultando…</strong></div>
            </div>

            <div class="asset-track-stage">
                <div class="asset-track-map" data-track-map>
                    <img data-track-image class="block max-h-[75vh] max-w-full" src="{{ route('floor-plans.file', $selectedPlan) }}" alt="Recorrido sobre {{ $selectedPlan->name }}">
                    <svg data-track-svg class="asset-track-svg" viewBox="0 0 1000 1000" preserveAspectRatio="none" aria-hidden="true"></svg>
                    <div data-track-tooltip class="asset-track-tooltip" hidden></div>
                </div>
            </div>

            <div class="asset-track-legend">
                <span><i class="asset-track-dot start"></i> Inicio</span>
                <span><i class="asset-track-dot current"></i> Posición actual</span>
                <span><i class="asset-track-line sample-good"></i> Confianza aceptable</span>
                <span><i class="asset-track-line sample-low"></i> Baja confianza o error alto</span>
            </div>
        @endif
    </section>
@endsection
