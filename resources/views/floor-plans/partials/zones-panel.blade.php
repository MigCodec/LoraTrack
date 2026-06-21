<div class="mt-4 space-y-3">
    @forelse($selectedPlan->zones as $zone)
        <details class="rounded-xl border border-slate-100 p-3">
            <summary class="flex cursor-pointer items-center justify-between gap-3">
                <span class="flex items-center gap-3"><span class="h-4 w-4 rounded" style="background: {{ $zone->color }}"></span><span><strong class="block text-sm">{{ $zone->name }}</strong><span class="text-xs text-slate-400">{{ $zone->code ?: 'Sin código' }}</span></span></span>
            </summary>
            @if(auth()->user()->hasPermission('plans.manage'))
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <form method="POST" action="{{ route('zones.update', $zone) }}" class="mb-4 space-y-2" data-zone-edit-form data-zone-id="{{ $zone->id }}">
                        @csrf @method('PUT')
                        <label class="field-label">Nombre<input class="field-input mt-1" name="name" value="{{ $zone->name }}" required></label>
                        <label class="field-label">Código<input class="field-input mt-1" name="code" value="{{ $zone->code }}"></label>
                        <label class="field-label">Color<input class="mt-1 h-10 w-full rounded-xl border border-slate-200 p-1" type="color" name="color" value="{{ $zone->color }}" required></label>
                        @foreach(['x_min', 'y_min', 'x_max', 'y_max'] as $coordinate)<input type="hidden" name="{{ $coordinate }}" value="{{ $zone->{$coordinate} }}">@endforeach
                        <button class="btn-secondary w-full" type="button" data-zone-redefine>Redefinir área en el plano</button>
                        <p class="text-xs text-slate-500" data-zone-edit-status>La geometría actual se conservará hasta que la redefinas.</p>
                        <button class="btn-primary w-full" type="submit">Guardar cambios</button>
                    </form>
                    <form method="POST" action="{{ route('zones.destroy', $zone) }}" onsubmit="return confirm('¿Eliminar esta zona? Esta acción no se puede deshacer.')">@csrf @method('DELETE')<button class="text-xs text-red-600">Eliminar zona</button></form>
                </div>
            @endif
        </details>
    @empty
        <p class="text-sm text-slate-400">Sin zonas.</p>
    @endforelse
</div>
