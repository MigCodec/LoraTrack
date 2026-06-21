<form id="zone-form" method="POST" action="{{ route('zones.store', $selectedPlan) }}" class="mt-4 space-y-4">
    @csrf
    <p class="text-xs leading-relaxed text-slate-500">Arrastra sobre el plano como en una selección CAD, completa las propiedades y guarda.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        <label class="field-label">Nombre<input class="field-input" name="name" required placeholder="Bodega Z"></label>
        <label class="field-label">Código<input class="field-input" name="code" placeholder="ZONE-Z"></label>
        <label class="field-label">Color<input class="mt-2 h-11 w-full rounded-xl border border-slate-200 p-1" type="color" name="color" value="#14B8A6"></label>
        <dl id="zone-geometry-metrics" class="zone-geometry-metrics self-end" hidden><div><dt>Área</dt><dd data-zone-area></dd></div><div><dt>Perímetro</dt><dd data-zone-perimeter></dd></div></dl>
    </div>
    @foreach(['x_min', 'y_min', 'x_max', 'y_max'] as $coordinate)<input id="zone-{{ str_replace('_', '-', $coordinate) }}" type="hidden" name="{{ $coordinate }}" required>@endforeach
    <button id="zone-draw-mode" class="btn-secondary" type="button">Definir área en el plano</button>
    <p id="zone-selection-status" class="rounded-lg bg-amber-50 p-3 text-xs text-amber-800">Pulsa “Definir área” para ocultar este panel y dibujar sobre el plano.</p>
    <div class="flex justify-end"><button id="zone-submit" class="btn-primary" disabled>Guardar zona</button></div>
</form>
