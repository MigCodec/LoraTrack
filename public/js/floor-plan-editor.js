(() => {
    const editor = document.getElementById('zone-editor');
    if (!editor) return;
    editor.dataset.editorInitialized = 'standalone';

    const canvas = document.getElementById('zone-canvas');
    const image = document.getElementById('floor-plan-image');
    const zoneForm = document.getElementById('zone-form');
    const anchorForm = document.getElementById('anchor-form');
    const zoneButton = document.getElementById('zone-mode');
    const anchorButton = document.getElementById('ribbon-anchor-mode');
    const anchorAgainButton = document.getElementById('anchor-mode');
    const modeStatus = document.getElementById('editor-mode-status');
    const zoneStatus = document.getElementById('zone-selection-status');
    const anchorStatus = document.getElementById('anchor-selection-status');
    const zoneSubmit = document.getElementById('zone-submit');
    const anchorSubmit = document.getElementById('anchor-submit');
    const context = canvas?.getContext('2d');
    if (!canvas || !image || !context) return;

    const readData = (id) => {
        try { return JSON.parse(document.getElementById(id)?.textContent || '[]'); }
        catch { return []; }
    };
    const zones = readData('zone-data');
    const layerControls = [...document.querySelectorAll('[data-editor-layer]')];
    let mode = null;
    let start = null;
    let draftZone = null;
    let draftAnchor = null;

    const setMode = (value) => {
        mode = value;
        if (zoneForm) zoneForm.hidden = value !== 'zone';
        if (anchorForm) anchorForm.hidden = value !== 'anchor';
        zoneButton?.classList.toggle('is-active', value === 'zone');
        anchorButton?.classList.toggle('is-active', value === 'anchor');
        canvas.style.cursor = value === 'zone' ? 'crosshair' : value === 'anchor' ? 'copy' : 'default';
        if (modeStatus) modeStatus.textContent = value === 'zone' ? 'Modo área activo: arrastra sobre el plano.' : 'Modo ancla activo: haz clic sobre el plano.';
    };
    const point = (event) => {
        const bounds = canvas.getBoundingClientRect();
        return {x: Math.max(0, Math.min(1, (event.clientX - bounds.left) / bounds.width)), y: Math.max(0, Math.min(1, (event.clientY - bounds.top) / bounds.height))};
    };
    const rectangle = (a, b) => ({x_min: Math.min(a.x, b.x), y_min: Math.min(a.y, b.y), x_max: Math.max(a.x, b.x), y_max: Math.max(a.y, b.y)});
    const drawZone = (zone, color, label) => {
        const x = zone.x_min * canvas.clientWidth; const y = zone.y_min * canvas.clientHeight;
        const width = (zone.x_max - zone.x_min) * canvas.clientWidth; const height = (zone.y_max - zone.y_min) * canvas.clientHeight;
        context.fillStyle = `${color}33`; context.strokeStyle = color; context.lineWidth = 2;
        context.fillRect(x, y, width, height); context.strokeRect(x, y, width, height);
        context.fillStyle = color; context.font = '600 12px sans-serif'; context.fillText(label, x + 6, y + 16, Math.max(0, width - 12));
    };
    const redraw = () => {
        const ratio = window.devicePixelRatio || 1; const width = canvas.clientWidth; const height = canvas.clientHeight;
        canvas.width = Math.round(width * ratio); canvas.height = Math.round(height * ratio); context.setTransform(ratio, 0, 0, ratio, 0, 0); context.clearRect(0, 0, width, height);
        const brand = getComputedStyle(document.body);
        if (draftZone) drawZone(draftZone, zoneForm?.elements.color?.value || brand.getPropertyValue('--color-brand-accent').trim() || '#14B8A6', 'Nueva área');
        (draftAnchor ? [{...draftAnchor, name: 'Nueva ancla'}] : []).forEach((item) => {
            const x = item.x * width; const y = item.y * height; context.beginPath(); context.arc(x, y, 6, 0, Math.PI * 2); context.fillStyle = item === draftAnchor ? '#dc2626' : brand.getPropertyValue('--color-brand-primary').trim() || '#2563EB'; context.fill(); context.strokeStyle = '#fff'; context.lineWidth = 2; context.stroke(); context.fillStyle = brand.getPropertyValue('--color-brand-secondary').trim() || '#0F172A'; context.font = '600 11px sans-serif'; context.fillText(item.name, x + 9, y + 4);
        });
    };

    zoneButton?.addEventListener('click', () => { draftAnchor = null; setMode('zone'); if (zoneStatus) zoneStatus.textContent = 'Arrastra sobre el plano para definir el área.'; redraw(); });
    const selectAnchor = () => { draftZone = null; setMode('anchor'); if (anchorStatus) anchorStatus.textContent = 'Haz clic en la posición conocida del dispositivo.'; redraw(); };
    anchorButton?.addEventListener('click', selectAnchor);
    anchorAgainButton?.addEventListener('click', selectAnchor);
    canvas.addEventListener('pointerdown', (event) => {
        if (mode === 'anchor' && anchorForm) {
            draftAnchor = point(event); anchorForm.elements.x_normalized.value = draftAnchor.x.toFixed(7); anchorForm.elements.y_normalized.value = draftAnchor.y.toFixed(7); anchorSubmit.disabled = false;
            if (anchorStatus) { anchorStatus.textContent = 'Punto seleccionado. Guarda la instalación.'; anchorStatus.className = 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800'; }
            redraw(); return;
        }
        if (mode !== 'zone' || !zoneForm) return;
        start = point(event); draftZone = rectangle(start, start); canvas.setPointerCapture?.(event.pointerId); redraw();
    });
    canvas.addEventListener('pointermove', (event) => { if (start) { draftZone = rectangle(start, point(event)); redraw(); } });
    canvas.addEventListener('pointerup', (event) => {
        if (!start || !zoneForm) return;
        draftZone = rectangle(start, point(event)); start = null;
        const valid = draftZone.x_max - draftZone.x_min > .005 && draftZone.y_max - draftZone.y_min > .005;
        ['x_min', 'y_min', 'x_max', 'y_max'].forEach((key) => { zoneForm.elements[key].value = valid ? draftZone[key].toFixed(7) : ''; });
        zoneSubmit.disabled = !valid;
        if (zoneStatus) { zoneStatus.textContent = valid ? 'Área seleccionada. Completa los datos y guarda.' : 'El área dibujada es demasiado pequeña.'; zoneStatus.className = valid ? 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800' : 'rounded-lg bg-amber-50 p-3 text-xs text-amber-800'; }
        redraw();
    });
    zoneForm?.elements.color?.addEventListener('input', redraw);
    const applyLayers = () => {
        const visible = (name) => document.querySelector(`[data-editor-layer="${name}"]`)?.checked ?? true;
        const zonesOverlay = document.getElementById('saved-zone-overlay');
        const anchorsOverlay = document.getElementById('saved-anchor-overlay');
        const assetsOverlay = document.getElementById('saved-asset-overlay');
        if (zonesOverlay) zonesOverlay.hidden = !visible('zones');
        if (anchorsOverlay) anchorsOverlay.hidden = !visible('beacons');
        if (assetsOverlay) assetsOverlay.hidden = !visible('assets');
    };
    layerControls.forEach((control) => control.addEventListener('change', applyLayers));
    applyLayers();
    image.addEventListener('load', redraw);
    if ('ResizeObserver' in window) new ResizeObserver(redraw).observe(image); else window.addEventListener('resize', redraw);
    if (image.complete) redraw();
    if (modeStatus) modeStatus.textContent = 'Editor listo. Selecciona Crear área o Colocar ancla.';
})();
