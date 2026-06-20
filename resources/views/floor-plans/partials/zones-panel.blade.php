<div class="mt-4 space-y-3">
    @forelse($selectedPlan->zones as $zone)
        <details class="rounded-xl border border-slate-100 p-3">
            <summary class="flex cursor-pointer items-center justify-between gap-3"><span class="flex items-center gap-3"><span class="h-4 w-4 rounded" style="background: {{ $zone->color }}"></span><span><strong class="block text-sm">{{ $zone->name }}</strong><span class="text-xs text-slate-400">{{ $zone->code ?: 'Sin código' }} · {{ $zone->alertRules->count() }} reglas</span></span></span></summary>
            @if(auth()->user()->hasPermission('plans.manage'))
                <div class="mt-3 border-t border-slate-100 pt-3">
                    <form method="POST" action="{{ route('zones.update', $zone) }}" class="mb-4 space-y-2">@csrf @method('PUT')<label class="field-label">Nombre<input class="field-input mt-1" name="name" value="{{ $zone->name }}" required></label><label class="field-label">Código<input class="field-input mt-1" name="code" value="{{ $zone->code }}"></label><label class="field-label">Color<input class="mt-1 h-10 w-full rounded-xl border border-slate-200 p-1" type="color" name="color" value="{{ $zone->color }}" required></label><button class="btn-secondary w-full" type="submit">Actualizar área</button></form>
                    <div class="space-y-2">@foreach($zone->alertRules as $rule)<div class="flex justify-between gap-2 text-xs"><span>{{ ['entry'=>'Ingreso','exit'=>'Salida','dwell'=>'Permanencia'][$rule->event_type] }}{{ $rule->dwell_minutes ? ' · '.$rule->dwell_minutes.' min' : '' }}</span><form method="POST" action="{{ route('zone-alert-rules.destroy', $rule) }}">@csrf @method('DELETE')<button class="text-red-600">Quitar</button></form></div>@endforeach</div>
                    <form method="POST" action="{{ route('zone-alert-rules.store', $zone) }}" class="mt-3 space-y-2">@csrf<select class="field-input mt-0" name="event_type"><option value="entry">Avisar al ingresar</option><option value="exit">Avisar al salir</option><option value="dwell">Avisar por permanencia</option></select><input class="field-input mt-0" type="number" name="dwell_minutes" min="10" max="10080" value="30" aria-label="Minutos de permanencia"><textarea class="field-input mt-0" name="recipients" rows="2" placeholder="Vacío: usar destinatarios globales"></textarea><button class="action-link" type="submit">Guardar regla</button></form>
                    <form method="POST" action="{{ route('zones.destroy', $zone) }}" class="mt-3" onsubmit="return confirm('¿Eliminar esta zona y sus reglas?')">@csrf @method('DELETE')<button class="text-xs text-red-600">Eliminar zona</button></form>
                </div>
            @endif
        </details>
    @empty
        <p class="text-sm text-slate-400">Sin zonas.</p>
    @endforelse
</div>
