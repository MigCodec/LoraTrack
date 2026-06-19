@extends('layouts.app')

@section('title', 'Activos')
@section('heading', 'Activos rastreados')

@section('content')
    <section class="panel">
        <div class="panel-header flex-wrap gap-3"><div><h2 class="panel-title">{{ $mobility === 'mobile' ? 'Activos móviles' : 'Activos estáticos' }}</h2><p class="panel-subtitle">Instancias rastreables vinculadas a beacons o trackers</p></div><div class="flex gap-2"><a class="{{ $mobility === 'mobile' ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('assets.index', ['mobility' => 'mobile']) }}">Móviles</a><a class="{{ $mobility === 'static' ? 'btn-primary' : 'btn-secondary' }}" href="{{ route('assets.index', ['mobility' => 'static']) }}">Estáticos</a>@if(auth()->user()->hasPermission('assets.manage'))<a class="btn-secondary" href="{{ route('assets.create', ['mobility' => $mobility]) }}">Nuevo activo</a>@endif</div></div>
        @if($assets->isEmpty())
            <div class="empty-state">No hay activos registrados todavía.</div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Activo</th><th>SKU</th><th>Serial</th><th>Dispositivo</th><th>Ubicación</th><th>Estado</th></tr></thead><tbody>
                @foreach($assets as $asset)<tr><td><div class="flex items-center gap-3">@if($asset->photo_path)<img class="h-11 w-11 rounded-lg border border-slate-200 object-cover" src="{{ route('assets.photo', $asset) }}" alt="">@endif<div><a class="font-semibold text-brand-primary" href="{{ route('assets.edit', $asset) }}">{{ $asset->name }}</a><br><span class="text-xs text-slate-400">{{ $asset->asset_tag }}</span></div></div></td><td>{{ $asset->sku?->code ?? '—' }}</td><td>{{ $asset->serial_number ?? '—' }}</td><td>{{ $asset->deviceAssignments->first()?->device?->name ?? 'Sin asignar' }}</td><td>{{ $asset->latestPosition?->zone?->name ?? $asset->location?->name ?? 'Desconocida' }}</td><td><span class="status-badge">{{ ucfirst($asset->status) }}</span></td></tr>@endforeach
            </tbody></table></div>
            <div class="border-t border-slate-100 p-4">{{ $assets->links() }}</div>
        @endif
    </section>
@endsection
