@extends('layouts.app')

@section('title', 'Productos')
@section('heading', 'Productos y SKU')

@section('content')
    <section class="panel">
        <div class="panel-header flex-wrap gap-4">
            <div><h2 class="panel-title">Catálogo normalizado</h2><p class="panel-subtitle">Productos importados desde SAP y otros conectores</p></div>
            <form method="GET" class="flex gap-2"><input class="field-input min-w-64" name="search" value="{{ $search }}" placeholder="Buscar producto o SKU"><button class="btn-secondary" type="submit">Buscar</button></form>
        </div>
        @if($products->isEmpty())
            <div class="empty-state">No hay productos. Configura un conector de catálogo y ejecuta su sincronización.</div>
        @else
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Producto</th><th>SKU</th><th>Unidad</th><th>Origen</th><th>Estado</th></tr></thead><tbody>
                @foreach($products as $product)
                    @forelse($product->skus as $sku)
                        <tr><td><span class="font-semibold text-slate-900">{{ $product->name }}</span></td><td class="font-mono text-xs">{{ $sku->code }}</td><td>{{ $sku->base_unit ?? '—' }}</td><td>{{ $sku->externalReferences->pluck('connector.name')->filter()->join(', ') ?: 'Local' }}</td><td><span class="status-badge">{{ ucfirst($sku->status) }}</span></td></tr>
                    @empty
                        <tr><td class="font-semibold">{{ $product->name }}</td><td colspan="4" class="text-slate-400">Sin SKU</td></tr>
                    @endforelse
                @endforeach
            </tbody></table></div>
            <div class="border-t border-slate-100 p-4">{{ $products->links() }}</div>
        @endif
    </section>
@endsection
