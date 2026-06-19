const editor = document.querySelector('#zone-editor');

if (editor && !editor.dataset.editorInitialized) {
    const image = editor.querySelector('#floor-plan-image');
    const canvas = editor.querySelector('#zone-canvas');
    const context = canvas.getContext('2d');
    const zoneData = JSON.parse(document.querySelector('#zone-data')?.textContent || '[]');
    const installationData = JSON.parse(document.querySelector('#installation-data')?.textContent || '[]');
    const form = document.querySelector('#zone-form');
    const status = document.querySelector('#zone-selection-status');
    const submit = document.querySelector('#zone-submit');
    const anchorForm = document.querySelector('#anchor-form');
    const anchorModeButton = document.querySelector('#anchor-mode');
    const anchorStatus = document.querySelector('#anchor-selection-status');
    const anchorSubmit = document.querySelector('#anchor-submit');
    const zoneModeButton = document.querySelector('#zone-mode');
    const ribbonAnchorModeButton = document.querySelector('#ribbon-anchor-mode');
    const modeStatus = document.querySelector('#editor-mode-status');
    let start = null;
    let draft = null;
    let draftAnchor = null;
    let activeMode = null;

    const setMode = (mode) => {
        activeMode = mode;
        if (form) form.hidden = mode !== 'zone';
        if (anchorForm) anchorForm.hidden = mode !== 'anchor';
        zoneModeButton?.classList.toggle('is-active', mode === 'zone');
        ribbonAnchorModeButton?.classList.toggle('is-active', mode === 'anchor');
        canvas.style.cursor = mode === 'zone' ? 'crosshair' : mode === 'anchor' ? 'copy' : 'default';
        if (modeStatus) modeStatus.textContent = mode === 'zone' ? 'Modo área activo: arrastra sobre el plano.' : mode === 'anchor' ? 'Modo ancla activo: haz clic en una ubicación conocida.' : 'Selecciona una herramienta para editar el plano.';
    };

    const pointer = (event) => {
        const rect = canvas.getBoundingClientRect();
        return {
            x: Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
            y: Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)),
        };
    };

    const rectangle = (from, to) => ({
        x_min: Math.min(from.x, to.x),
        y_min: Math.min(from.y, to.y),
        x_max: Math.max(from.x, to.x),
        y_max: Math.max(from.y, to.y),
    });

    const drawRectangle = (zone, color, label) => {
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        const x = zone.x_min * width;
        const y = zone.y_min * height;
        const w = (zone.x_max - zone.x_min) * width;
        const h = (zone.y_max - zone.y_min) * height;
        context.fillStyle = `${color}33`;
        context.strokeStyle = color;
        context.lineWidth = 2;
        context.fillRect(x, y, w, h);
        context.strokeRect(x, y, w, h);
        if (label) {
            context.fillStyle = color;
            context.font = '600 12px sans-serif';
            context.fillText(label, x + 6, y + 16, Math.max(0, w - 12));
        }
    };

    const redraw = () => {
        const ratio = window.devicePixelRatio || 1;
        const width = canvas.clientWidth;
        const height = canvas.clientHeight;
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.clearRect(0, 0, width, height);
        zoneData.forEach((zone) => drawRectangle(zone, zone.color, zone.name));
        const brand = getComputedStyle(document.body);
        if (draft) drawRectangle(draft, form?.elements.color?.value || brand.getPropertyValue('--color-brand-accent').trim() || '#14B8A6', 'Nueva zona');
        [...installationData, ...(draftAnchor ? [{...draftAnchor, name: 'Nueva ancla'}] : [])].forEach((installation) => {
            const x = installation.x * width;
            const y = installation.y * height;
            context.beginPath();
            context.arc(x, y, 6, 0, Math.PI * 2);
            context.fillStyle = installation === draftAnchor ? '#dc2626' : brand.getPropertyValue('--color-brand-primary').trim() || '#2563EB';
            context.fill();
            context.strokeStyle = '#ffffff';
            context.lineWidth = 2;
            context.stroke();
            context.fillStyle = brand.getPropertyValue('--color-brand-secondary').trim() || '#0F172A';
            context.font = '600 11px sans-serif';
            context.fillText(installation.name, x + 9, y + 4);
        });
    };

    canvas.addEventListener('pointerdown', (event) => {
        if (activeMode === 'anchor' && anchorForm) {
            draftAnchor = pointer(event);
            anchorForm.elements.x_normalized.value = draftAnchor.x.toFixed(7);
            anchorForm.elements.y_normalized.value = draftAnchor.y.toFixed(7);
            anchorSubmit.disabled = false;
            anchorStatus.textContent = 'Punto seleccionado. Guarda la instalación.';
            anchorStatus.className = 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800';
            activeMode = null;
            anchorModeButton.textContent = 'Cambiar punto en plano';
            ribbonAnchorModeButton?.classList.remove('is-active');
            canvas.style.cursor = 'default';
            redraw();
            return;
        }
        if (!form || activeMode !== 'zone') return;
        start = pointer(event);
        draft = rectangle(start, start);
        canvas.setPointerCapture(event.pointerId);
        redraw();
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!start) return;
        draft = rectangle(start, pointer(event));
        redraw();
    });

    canvas.addEventListener('pointerup', (event) => {
        if (!start || !form) return;
        draft = rectangle(start, pointer(event));
        start = null;
        const valid = draft.x_max - draft.x_min > 0.005 && draft.y_max - draft.y_min > 0.005;
        ['x_min', 'y_min', 'x_max', 'y_max'].forEach((key) => {
            form.elements[key].value = valid ? draft[key].toFixed(7) : '';
        });
        submit.disabled = !valid;
        status.textContent = valid ? 'Área seleccionada. Asigna un nombre y guarda la zona.' : 'El rectángulo es demasiado pequeño.';
        status.className = valid ? 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800' : 'rounded-lg bg-amber-50 p-3 text-xs text-amber-800';
        redraw();
    });

    form?.elements.color?.addEventListener('input', redraw);
    zoneModeButton?.addEventListener('click', () => {
        draftAnchor = null;
        setMode('zone');
        status.textContent = 'Arrastra sobre el plano para definir el área.';
        redraw();
        form?.querySelector('input[name="name"]')?.focus();
    });
    ribbonAnchorModeButton?.addEventListener('click', () => {
        draft = null;
        setMode('anchor');
        anchorStatus.textContent = 'Haz clic en la posición conocida del dispositivo.';
        redraw();
        anchorForm?.querySelector('select[name="device_id"]')?.focus();
    });
    anchorModeButton?.addEventListener('click', () => {
        setMode('anchor');
        anchorModeButton.textContent = 'Haz clic sobre el plano…';
        anchorStatus.textContent = 'Modo ancla activo: haz clic en la posición conocida.';
    });
    image.addEventListener('load', redraw);
    new ResizeObserver(redraw).observe(image);
    if (image.complete) redraw();
}

const realtimeMap = document.querySelector('#realtime-map');
if (realtimeMap) {
    const markers = document.querySelector('#map-markers');
    const updated = document.querySelector('#map-updated');
    const zones = JSON.parse(document.querySelector('#map-zones')?.textContent || '[]');
    zones.forEach((zone) => {
        const element = document.createElement('div'); element.className = 'map-zone'; element.style.left = `${zone.x_min*100}%`; element.style.top = `${zone.y_min*100}%`; element.style.width = `${(zone.x_max-zone.x_min)*100}%`; element.style.height = `${(zone.y_max-zone.y_min)*100}%`; element.style.borderColor = zone.color; element.style.backgroundColor = `${zone.color}22`; element.title = zone.name; markers.appendChild(element);
    });
    const refresh = async () => {
        try {
            const response = await fetch(realtimeMap.dataset.endpoint, {headers:{Accept:'application/json'}}); if (!response.ok) throw new Error(`HTTP ${response.status}`); const data = await response.json();
            markers.querySelectorAll('.asset-marker,.asset-uncertainty').forEach((node) => node.remove());
            data.positions.forEach((position) => {
                const uncertainty=document.createElement('div'); uncertainty.className=`asset-uncertainty${position.stale?' stale':''}`; uncertainty.style.left=`${position.x*100}%`; uncertainty.style.top=`${position.y*100}%`; uncertainty.style.width=`${Math.max(1.5,Math.min(200,position.error_radius_x*200))}%`; uncertainty.style.height=`${Math.max(1.5,Math.min(200,position.error_radius_y*200))}%`; uncertainty.title=`Error estimado: ${position.accuracy_meters.toFixed(2)} m · relativo ${(position.relative_error*100).toFixed(2)}%`; markers.appendChild(uncertainty);
                const node=document.createElement('button'); node.className=`asset-marker${position.stale?' stale':''}${position.out_of_bounds?' out-of-bounds':''}`; node.style.left=`${position.x*100}%`; node.style.top=`${position.y*100}%`; node.title=`${position.name} · ${position.product||''} · ${position.zone||'Sin zona'} · confianza ${Math.round(position.confidence*100)}% · error ±${position.accuracy_meters.toFixed(2)} m${position.out_of_bounds?' · fuera del plano':''}`; const dot=document.createElement('span'); const label=document.createElement('small'); label.textContent=position.name; node.append(dot,label); markers.appendChild(node);
            });
            updated.textContent=`Actualizado ${new Date(data.generated_at).toLocaleTimeString()}`;
        } catch { updated.textContent='No fue posible actualizar'; }
    };
    refresh(); setInterval(refresh, 10000);
}

const calibrationForm = document.querySelector('#calibration-form');
if (calibrationForm) {
    const type = calibrationForm.elements.anchor_type;
    const rows = [...calibrationForm.querySelectorAll('[data-anchor-type]')];
    const submit = document.querySelector('#calibration-submit');
    const count = document.querySelector('#calibration-anchor-count');
    const refreshAnchors = () => {
        let visible = 0;
        rows.forEach((row) => {
            const active = row.dataset.anchorType === type.value;
            row.hidden = !active;
            row.querySelectorAll('input').forEach((input) => { input.disabled = !active; });
            if (active) visible += 1;
        });
        submit.disabled = visible < 3;
        count.textContent = visible >= 3 ? `${visible} anclas incluidas en esta prueba.` : `Solo hay ${visible}; se requieren al menos 3 anclas del mismo tipo.`;
    };
    type.addEventListener('change', refreshAnchors);
    refreshAnchors();
}
