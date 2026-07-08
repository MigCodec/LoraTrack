@extends('layouts.app')

@section('title', 'AP Meraki')
@section('heading', 'AP Meraki')

@section('content')
    <section class="panel" data-meraki-access-points data-endpoint="{{ route('api.meraki-access-points.index') }}">
        <div class="panel-header">
            <div>
                <h2 class="panel-title">Access points Meraki</h2>
                <p class="panel-subtitle">AP detectados por el conector Meraki v3, con ubicacion en plano y actividad normalizada</p>
            </div>
            @if(auth()->user()->hasPermission('plans.manage'))
                <a class="action-link" href="{{ route('floor-plans.index') }}">Ubicar en plano</a>
            @endif
        </div>

        <div class="border-t border-slate-200 p-4">
            <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500" for="meraki-ap-search">Buscar AP</label>
            <input id="meraki-ap-search" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand-accent focus:outline-none" type="search" autocomplete="off" placeholder="Nombre, MAC, serial o network" data-meraki-access-point-search>
        </div>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>AP</th>
                        <th>Meraki</th>
                        <th>Ubicacion</th>
                        <th>Clientes vistos</th>
                        <th>Ultima actividad</th>
                    </tr>
                </thead>
                <tbody data-meraki-access-point-rows>
                    <tr><td colspan="5">Cargando AP Meraki...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 p-4" data-meraki-access-point-pagination aria-live="polite"></div>
    </section>
@endsection
