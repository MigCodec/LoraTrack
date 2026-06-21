document.addEventListener('click', (event) => {
    const defineAreaButton = event.target.closest('#zone-draw-mode');
    if (!defineAreaButton) return;
    event.preventDefault();
    event.stopPropagation();
    document.querySelector('#zone-editor')?.dispatchEvent(new CustomEvent('loratrack:define-area'));
}, {capture: true});

document.querySelectorAll('.plan-ribbon').forEach((ribbon) => {
    const dropdowns = [...ribbon.querySelectorAll('details.ribbon-command, details.ribbon-layers')];
    const closeDropdowns = (except = null) => dropdowns.forEach((dropdown) => {
        if (dropdown !== except) dropdown.removeAttribute('open');
    });

    dropdowns.forEach((dropdown) => dropdown.addEventListener('toggle', () => {
        if (dropdown.open) closeDropdowns(dropdown);
    }));
    ribbon.addEventListener('click', (event) => {
        if (event.target.closest('.ribbon-button')) closeDropdowns();
    });
});

const sheetContextMenu = document.querySelector('#plan-sheet-context-menu');
if (sheetContextMenu) {
    const sheetTabs = [...document.querySelectorAll('.plan-sheet-tab[data-update-url]')];
    const renameDialog = document.querySelector('#plan-rename-dialog');
    const renameForm = document.querySelector('#plan-rename-form');
    const renameInput = document.querySelector('#plan-rename-input');
    const colorDialog = document.querySelector('#plan-color-dialog');
    const colorForm = document.querySelector('#plan-color-form');
    const colorInput = document.querySelector('#plan-color-input');
    const colorValue = document.querySelector('#plan-color-value');
    const deleteForm = document.querySelector('#plan-sheet-delete-form');
    let selectedSheet = null;

    const closeSheetMenu = () => { sheetContextMenu.hidden = true; };
    const openSheetMenu = (tab, clientX, clientY) => {
        selectedSheet = tab;
        sheetContextMenu.hidden = false;
        const width = sheetContextMenu.offsetWidth;
        const height = sheetContextMenu.offsetHeight;
        sheetContextMenu.style.left = `${Math.max(8, Math.min(clientX, window.innerWidth - width - 8))}px`;
        sheetContextMenu.style.top = `${Math.max(8, Math.min(clientY, window.innerHeight - height - 8))}px`;
        sheetContextMenu.querySelector('[role="menuitem"]')?.focus();
    };

    sheetTabs.forEach((tab) => {
        tab.addEventListener('contextmenu', (event) => {
            event.preventDefault();
            openSheetMenu(tab, event.clientX, event.clientY);
        });
        tab.addEventListener('keydown', (event) => {
            if (event.key === 'ContextMenu' || (event.shiftKey && event.key === 'F10')) {
                event.preventDefault();
                const rect = tab.getBoundingClientRect();
                openSheetMenu(tab, rect.left + 12, rect.bottom - 4);
            }
        });
    });
    sheetContextMenu.addEventListener('click', (event) => {
        const action = event.target.closest('[data-sheet-action]')?.dataset.sheetAction;
        if (!action || !selectedSheet) return;
        closeSheetMenu();
        if (action === 'open') window.location.assign(selectedSheet.href);
        if (action === 'calibrate') window.location.assign(selectedSheet.dataset.calibrationUrl);
        if (action === 'rename') {
            renameForm.action = selectedSheet.dataset.updateUrl;
            renameInput.value = selectedSheet.dataset.planName;
            renameDialog.showModal();
            renameInput.focus();
            renameInput.select();
        }
        if (action === 'color') {
            const currentColor = /^#[0-9a-f]{6}$/i.test(selectedSheet.dataset.tabColor) ? selectedSheet.dataset.tabColor : '#14b8a6';
            colorForm.action = selectedSheet.dataset.updateUrl;
            colorInput.value = currentColor;
            colorValue.value = currentColor;
            colorDialog.showModal();
            colorInput.focus();
        }
        if (action === 'delete' && window.confirm(`¿Eliminar el plano "${selectedSheet.dataset.planName}" y sus zonas?`)) {
            deleteForm.action = selectedSheet.dataset.deleteUrl;
            deleteForm.submit();
        }
    });
    document.querySelector('[data-close-rename]')?.addEventListener('click', () => renameDialog.close());
    colorInput?.addEventListener('input', () => { colorValue.value = colorInput.value; });
    document.querySelector('[data-close-color]')?.addEventListener('click', () => colorDialog.close());
    document.querySelector('[data-reset-tab-color]')?.addEventListener('click', () => {
        colorValue.value = '';
        colorForm.requestSubmit();
    });
    document.addEventListener('pointerdown', (event) => {
        if (!sheetContextMenu.hidden && !sheetContextMenu.contains(event.target)) closeSheetMenu();
    });
    window.addEventListener('blur', closeSheetMenu);
    window.addEventListener('resize', closeSheetMenu);
    window.addEventListener('scroll', closeSheetMenu, true);
}

const assetForm = document.querySelector('#asset-form');
if (assetForm) {
    const mobility = assetForm.elements.mobility;
    const trackerField = assetForm.querySelector('[data-mobile-tracker-field]');
    const syncTrackerField = () => {
        if (!trackerField || !mobility) return;
        const mobile = mobility.value === 'mobile';
        trackerField.hidden = !mobile;
        trackerField.querySelectorAll('select,input').forEach((control) => { control.disabled = !mobile; });
    };
    mobility?.addEventListener('change', syncTrackerField);
    syncTrackerField();
}

const editor = document.querySelector('#zone-editor');

if (editor && !editor.dataset.editorInitialized) {
    editor.dataset.editorInitialized = 'app';
    const image = editor.querySelector('#floor-plan-image');
    const canvas = editor.querySelector('#zone-canvas');
    const context = canvas.getContext('2d');
    const zoneData = JSON.parse(document.querySelector('#zone-data')?.textContent || '[]');
    const installationData = JSON.parse(document.querySelector('#installation-data')?.textContent || '[]');
    const form = document.querySelector('#zone-form');
    const status = document.querySelector('#zone-selection-status');
    const submit = document.querySelector('#zone-submit');
    const zoneDrawButton = document.querySelector('#zone-draw-mode');
    const anchorForm = document.querySelector('#anchor-form');
    const anchorModeButton = document.querySelector('#anchor-mode');
    const anchorStatus = document.querySelector('#anchor-selection-status');
    const anchorSubmit = document.querySelector('#anchor-submit');
    const zoneModeButton = document.querySelector('#zone-mode');
    const ribbonAnchorModeButton = document.querySelector('#ribbon-anchor-mode');
    const zoneCommand = document.querySelector('#zone-command');
    const anchorCommand = document.querySelector('#anchor-command');
    const modeStatus = document.querySelector('#editor-mode-status');
    const geometryMetrics = document.querySelector('#zone-geometry-metrics');
    const zoneArea = document.querySelector('[data-zone-area]');
    const zonePerimeter = document.querySelector('[data-zone-perimeter]');
    const planWidthMeters = Number(editor.dataset.widthMeters);
    const planHeightMeters = Number(editor.dataset.heightMeters);
    const layerControls = [...document.querySelectorAll('[data-editor-layer]')];
    let start = null;
    let draft = null;
    let draftAnchor = null;
    let relocationForm = null;
    let activeMode = null;

    const setMode = (mode) => {
        activeMode = mode;
        zoneModeButton?.classList.toggle('is-active', mode === 'zone');
        ribbonAnchorModeButton?.classList.toggle('is-active', mode === 'anchor');
        editor.classList.toggle('is-selecting-geometry', ['zone', 'anchor', 'relocate-anchor'].includes(mode));
        if (mode === 'zone') editor.dataset.selectionInstruction = 'Arrastra para definir el área';
        else if (['anchor', 'relocate-anchor'].includes(mode)) editor.dataset.selectionInstruction = 'Haz clic para definir la posición';
        else delete editor.dataset.selectionInstruction;
        canvas.style.pointerEvents = ['zone', 'anchor', 'relocate-anchor'].includes(mode) ? 'auto' : 'none';
        canvas.style.cursor = mode === 'zone' ? 'crosshair' : ['anchor', 'relocate-anchor'].includes(mode) ? 'copy' : 'default';
        if (modeStatus) modeStatus.textContent = mode === 'zone' ? 'Modo área activo: arrastra sobre el plano.' : mode === 'anchor' ? 'Modo ancla activo: haz clic en una ubicación conocida.' : mode === 'relocate-anchor' ? 'Reubicando beacon: haz clic en su nueva posición.' : 'Selecciona una herramienta para editar el plano.';
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
        });
    };

    canvas.addEventListener('pointerdown', (event) => {
        if (activeMode === 'relocate-anchor' && relocationForm) {
            const point = pointer(event);
            relocationForm.elements.x_meters.value = (point.x * planWidthMeters).toFixed(3);
            relocationForm.elements.y_meters.value = (point.y * planHeightMeters).toFixed(3);
            const details = relocationForm.closest('[data-anchor-details]');
            if (details) {
                details.style.left = `${point.x * 100}%`;
                details.style.top = `${point.y * 100}%`;
            }
            const installation = installationData.find((item) => String(item.id) === relocationForm.dataset.installationId);
            if (installation) Object.assign(installation, point);
            const selectedForm = relocationForm;
            relocationForm = null;
            setMode(null);
            redraw();
            window.setTimeout(() => {
                const selectedDetails = selectedForm.closest('[data-anchor-details]');
                if (selectedDetails) selectedDetails.open = true;
                selectedForm.querySelector('[data-anchor-reposition]')?.focus();
            }, 0);
            return;
        }
        if (activeMode === 'anchor' && anchorForm) {
            draftAnchor = pointer(event);
            anchorForm.elements.x_normalized.value = draftAnchor.x.toFixed(7);
            anchorForm.elements.y_normalized.value = draftAnchor.y.toFixed(7);
            anchorSubmit.disabled = false;
            anchorStatus.textContent = 'Punto seleccionado. Guarda la instalación.';
            anchorStatus.className = 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800';
            setMode(null);
            anchorModeButton.textContent = 'Cambiar punto en plano';
            redraw();
            window.setTimeout(() => { if (anchorCommand) anchorCommand.open = true; }, 0);
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
        if (geometryMetrics) {
            geometryMetrics.hidden = !valid;
            if (valid) {
                const widthMeters = (draft.x_max - draft.x_min) * planWidthMeters;
                const heightMeters = (draft.y_max - draft.y_min) * planHeightMeters;
                zoneArea.textContent = `${(widthMeters * heightMeters).toLocaleString(undefined, {maximumFractionDigits: 2})} m²`;
                zonePerimeter.textContent = `${(2 * (widthMeters + heightMeters)).toLocaleString(undefined, {maximumFractionDigits: 2})} m`;
            }
        }
        status.textContent = valid ? 'Área seleccionada. Asigna un nombre y guarda la zona.' : 'El rectángulo es demasiado pequeño.';
        status.className = valid ? 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800' : 'rounded-lg bg-amber-50 p-3 text-xs text-amber-800';
        zoneDrawButton.textContent = valid ? 'Volver a definir el área' : 'Definir área en el plano';
        setMode(null);
        redraw();
        window.setTimeout(() => { if (zoneCommand) zoneCommand.open = true; }, 0);
    });

    form?.elements.color?.addEventListener('input', redraw);
    const activateZoneMode = () => {
        relocationForm = null;
        draftAnchor = null;
        draft = null;
        ['x_min', 'y_min', 'x_max', 'y_max'].forEach((key) => { form.elements[key].value = ''; });
        submit.disabled = true;
        if (geometryMetrics) geometryMetrics.hidden = true;
        setMode('zone');
        status.textContent = 'Arrastra sobre el plano para definir el área.';
        redraw();
    };
    const activateAnchorMode = () => {
        relocationForm = null;
        draft = null;
        setMode('anchor');
        anchorStatus.textContent = 'Haz clic en la posición conocida del dispositivo.';
        redraw();
    };
    [zoneCommand, anchorCommand].forEach((command) => command?.addEventListener('toggle', () => {
        if (command.open && ['zone', 'anchor'].includes(activeMode)) setMode(null);
    }));
    editor.addEventListener('loratrack:define-area', () => {
        activateZoneMode();
        if (zoneCommand) zoneCommand.removeAttribute('open');
        editor.scrollIntoView({block: 'nearest'});
    });
    anchorModeButton?.addEventListener('click', () => {
        relocationForm = null;
        activateAnchorMode();
        if (anchorCommand) anchorCommand.removeAttribute('open');
        anchorModeButton.textContent = 'Haz clic sobre el plano…';
        anchorStatus.textContent = 'Modo ancla activo: haz clic en la posición conocida.';
        editor.scrollIntoView({block: 'nearest'});
    });
    const applyEditorLayers = () => {
        const visible = (name) => document.querySelector(`[data-editor-layer="${name}"]`)?.checked ?? true;
        const layers = {
            zones: document.querySelector('#saved-zone-overlay'),
            beacons: document.querySelector('#saved-anchor-overlay'),
            assets: document.querySelector('#saved-asset-overlay'),
        };
        Object.entries(layers).forEach(([name, layer]) => { if (layer) layer.hidden = !visible(name); });
    };
    layerControls.forEach((control) => control.addEventListener('change', applyEditorLayers));
    applyEditorLayers();

    const anchorDetails = [...document.querySelectorAll('[data-anchor-details]')];
    document.querySelectorAll('[data-anchor-reposition]').forEach((button) => button.addEventListener('click', () => {
        relocationForm = button.closest('[data-anchor-edit-form]');
        const details = button.closest('[data-anchor-details]');
        if (details) details.open = false;
        setMode('relocate-anchor');
    }));
    anchorDetails.forEach((details) => details.addEventListener('toggle', () => {
        if (details.open) anchorDetails.forEach((other) => { if (other !== details) other.removeAttribute('open'); });
    }));
    editor.addEventListener('pointerdown', (event) => {
        if (!event.target.closest('[data-anchor-details]')) {
            anchorDetails.forEach((details) => details.removeAttribute('open'));
        }
    }, {capture: true});
    document.addEventListener('pointerdown', (event) => {
        anchorDetails.forEach((details) => {
            if (details.open && !details.contains(event.target)) details.removeAttribute('open');
        });
    });

    image.addEventListener('load', redraw);
    new ResizeObserver(redraw).observe(image);
    setMode(null);
    if (image.complete) redraw();
}

const realtimeMap = document.querySelector('#realtime-map');
if (realtimeMap) {
    const markers = document.querySelector('#map-markers');
    const updated = document.querySelector('#map-updated');
    const positionStatus = document.querySelector('#map-position-status');
    const technicalDialog = document.querySelector('#asset-technical-dialog');
    const evidenceBody = document.querySelector('#asset-detail-evidence');
    const circleColors = ['#2563eb', '#dc2626', '#7c3aed', '#d97706', '#059669', '#0891b2'];
    let selectedAssetId = null;
    const spatialMarkerIcon = (type) => {
        const symbols = {
            asset: '<path class="spatial-marker-symbol" d="M9 11.5h10v7H9zM11.5 11.5v-2h5v2M9 14.5h10M12 14.5v1.5h4v-1.5"/>',
            scanner: '<circle class="spatial-marker-symbol" cx="14" cy="14" r="2"/><path class="spatial-marker-symbol" d="M10.5 10.5a5 5 0 0 0 0 7M17.5 10.5a5 5 0 0 1 0 7"/>',
            anchor: '<path class="spatial-marker-symbol" d="M14 9v10M10.5 12.5 14 9l3.5 3.5M10 19h8"/>',
        };
        const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        icon.setAttribute('class', `spatial-marker-icon is-${type}`);
        icon.setAttribute('width', '28');
        icon.setAttribute('height', '34');
        icon.setAttribute('viewBox', '0 0 28 34');
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = `<path class="spatial-marker-pin" d="M14 1.5C7.1 1.5 2 6.6 2 13.1c0 8.4 12 18.9 12 18.9s12-10.5 12-18.9C26 6.6 20.9 1.5 14 1.5Z"/>${symbols[type] || symbols.anchor}`;
        return icon;
    };
    const layerControls = [...document.querySelectorAll('[data-map-layer]')];
    const applyMapLayers = () => {
        const visible = (name) => document.querySelector(`[data-map-layer="${name}"]`)?.checked ?? true;
        markers.querySelectorAll('.map-zone').forEach((node) => { node.hidden = !visible('zones'); });
        markers.querySelectorAll('.map-anchor').forEach((node) => { node.hidden = !visible('beacons'); });
        markers.querySelectorAll('.asset-marker,.asset-uncertainty,.asset-detection-circle').forEach((node) => { node.hidden = !visible('assets'); });
    };
    layerControls.forEach((control) => control.addEventListener('change', applyMapLayers));
    const zones = JSON.parse(document.querySelector('#map-zones')?.textContent || '[]');
    zones.forEach((zone) => {
        const element = document.createElement('div'); element.className = 'map-zone'; element.style.left = `${zone.x_min*100}%`; element.style.top = `${zone.y_min*100}%`; element.style.width = `${(zone.x_max-zone.x_min)*100}%`; element.style.height = `${(zone.y_max-zone.y_min)*100}%`; element.style.borderColor = zone.color; element.style.backgroundColor = `${zone.color}22`; element.title = zone.name; markers.appendChild(element);
    });
    const setDetail = (id, value) => { const element = document.querySelector(id); if (element) element.textContent = value; };
    const formatDate = (value) => value ? new Date(value).toLocaleString() : '—';
    const positionAssetDialog = (trigger) => {
        if (!technicalDialog || !trigger) return;
        ['top', 'right', 'bottom', 'left'].forEach((property) => technicalDialog.style.removeProperty(property));
        if (window.matchMedia('(max-width: 48rem)').matches) return;
        const gap = 12;
        const viewportPadding = 12;
        const triggerRect = trigger.getBoundingClientRect();
        const dialogRect = technicalDialog.getBoundingClientRect();
        let left = triggerRect.right + gap;
        if (left + dialogRect.width > window.innerWidth - viewportPadding) left = triggerRect.left - dialogRect.width - gap;
        left = Math.max(viewportPadding, Math.min(left, window.innerWidth - dialogRect.width - viewportPadding));
        const top = Math.max(viewportPadding, Math.min(triggerRect.top - gap, window.innerHeight - dialogRect.height - viewportPadding));
        technicalDialog.style.left = `${left}px`;
        technicalDialog.style.top = `${top}px`;
    };
    const clearAssetDetails = () => {
        markers.querySelectorAll('.asset-detection-circle').forEach((node) => node.remove());
        markers.querySelectorAll('.asset-marker.is-selected').forEach((node) => node.classList.remove('is-selected'));
    };
    const showAssetDetails = (position, trigger = null, openDialog = true) => {
        clearAssetDetails();
        selectedAssetId = position.asset_id;
        markers.querySelector(`[data-asset-id="${CSS.escape(position.asset_id)}"]`)?.classList.add('is-selected');

        position.evidence.forEach((anchor, index) => {
            const color = circleColors[index % circleColors.length];
            const circle = document.createElement('div');
            circle.className = 'asset-detection-circle';
            circle.style.setProperty('--circle-color', color);
            circle.style.left = `${anchor.x * 100}%`;
            circle.style.top = `${anchor.y * 100}%`;
            circle.style.width = `${Math.min(400, anchor.circle_diameter_x * 100)}%`;
            circle.style.height = `${Math.min(400, anchor.circle_diameter_y * 100)}%`;
            circle.title = `${anchor.name}: ${anchor.rssi} dBm, ${anchor.estimated_distance_meters.toFixed(2)} m`;
            const number = document.createElement('span'); number.textContent = String(index + 1); circle.appendChild(number); markers.appendChild(circle);
        });

        setDetail('#asset-technical-title', position.name);
        setDetail('#asset-technical-subtitle', [position.product, position.sku].filter(Boolean).join(' · ') || 'Sin producto asociado');
        setDetail('#asset-detail-position', `X ${position.x_meters.toFixed(2)} m · Y ${position.y_meters.toFixed(2)} m`);
        setDetail('#asset-detail-zone', position.zone || 'Sin zona');
        setDetail('#asset-detail-confidence', `${Math.round(position.confidence * 100)}%`);
        setDetail('#asset-detail-error', `±${position.accuracy_meters.toFixed(2)} m`);
        setDetail('#asset-detail-algorithm', `${position.algorithm} v${position.algorithm_version}`);
        setDetail('#asset-detail-calculated', formatDate(position.calculated_at));
        setDetail('#asset-detail-observed', formatDate(position.observed_at));
        setDetail('#asset-detail-received', formatDate(position.received_at));
        evidenceBody.replaceChildren(...position.evidence.map((anchor, index) => {
            const row = document.createElement('tr');
            const name = document.createElement('td');
            const swatch = document.createElement('span'); swatch.className = 'asset-evidence-swatch'; swatch.style.setProperty('--swatch-color', circleColors[index % circleColors.length]);
            name.append(swatch, document.createTextNode(`${index + 1}. ${anchor.name}`));
            const rssi = document.createElement('td'); rssi.textContent = `${anchor.rssi} dBm`;
            const distance = document.createElement('td'); distance.textContent = `${anchor.estimated_distance_meters.toFixed(2)} m`;
            const residual = document.createElement('td'); residual.textContent = `${anchor.residual_meters >= 0 ? '+' : ''}${anchor.residual_meters.toFixed(2)} m`;
            const calibration = document.createElement('td'); calibration.textContent = anchor.reference_rssi === null ? '—' : `${anchor.reference_rssi} dBm @ 1 m · n=${Number(anchor.path_loss_exponent).toFixed(2)}`;
            row.append(name, rssi, distance, residual, calibration); return row;
        }));
        if (openDialog && technicalDialog && !technicalDialog.open) technicalDialog.show();
        if (technicalDialog?.open) positionAssetDialog(trigger || markers.querySelector(`[data-asset-id="${CSS.escape(position.asset_id)}"]`));
    };
    technicalDialog?.addEventListener('close', () => { selectedAssetId = null; clearAssetDetails(); });
    document.addEventListener('pointerdown', (event) => {
        if (technicalDialog?.open && !technicalDialog.contains(event.target) && !event.target.closest('.asset-marker')) technicalDialog.close();
    });
    const refresh = async () => {
        try {
            const response = await fetch(realtimeMap.dataset.endpoint, {headers:{Accept:'application/json'}}); if (!response.ok) throw new Error(`HTTP ${response.status}`); const data = await response.json();
            markers.querySelectorAll('.map-anchor,.asset-marker,.asset-uncertainty,.asset-detection-circle').forEach((node) => node.remove());
            data.anchors.forEach((anchor) => {
                const node=document.createElement('span'); node.className=`map-anchor ${anchor.type}`; node.style.left=`${anchor.x*100}%`; node.style.top=`${anchor.y*100}%`; node.title=`${anchor.name} · ${anchor.identifier}`; node.setAttribute('aria-label', anchor.name); const marker=document.createElement('i'); marker.setAttribute('aria-hidden', 'true'); node.appendChild(marker); markers.appendChild(node);
            });
            data.positions.forEach((position) => {
                const uncertaintyDiameter = Math.max(1.5, Math.min(200, position.relative_error * 200));
                const uncertainty=document.createElement('div'); uncertainty.className=`asset-uncertainty${position.stale?' stale':''}`; uncertainty.style.left=`${position.x*100}%`; uncertainty.style.top=`${position.y*100}%`; uncertainty.style.width=`${uncertaintyDiameter}%`; uncertainty.style.height=`${uncertaintyDiameter}%`; uncertainty.title=`Error estimado: ${position.accuracy_meters.toFixed(2)} m · relativo ${(position.relative_error*100).toFixed(2)}%`; markers.appendChild(uncertainty);
                const node=document.createElement('button'); node.type='button'; node.dataset.assetId=position.asset_id; node.setAttribute('aria-haspopup','dialog'); node.setAttribute('aria-label', `Ver detalles de ${position.name}`); node.className=`asset-marker${position.stale?' stale':''}${position.out_of_bounds?' out-of-bounds':''}`; node.style.left=`${position.x*100}%`; node.style.top=`${position.y*100}%`; node.title=`${position.name} · ${position.product||''} · ${position.zone||'Sin zona'} · confianza ${Math.round(position.confidence*100)}% · error ±${position.accuracy_meters.toFixed(2)} m${position.out_of_bounds?' · fuera del plano':''}`; node.appendChild(spatialMarkerIcon('asset')); node.addEventListener('click',()=>showAssetDetails(position,node)); markers.appendChild(node);
            });
            const selectedPosition = data.positions.find((position) => position.asset_id === selectedAssetId);
            if (selectedPosition && technicalDialog?.open) showAssetDetails(selectedPosition, markers.querySelector(`[data-asset-id="${CSS.escape(selectedPosition.asset_id)}"]`), false);
            applyMapLayers();
            if (positionStatus) positionStatus.textContent = data.positions.length
                ? `${data.positions.length} activo(s) triangulado(s) en este plano.`
                : 'Sin posiciones calculadas para este plano. Verifica tracker asignado, uplink BLE procesado y al menos 3 beacons instalados.';
            updated.textContent=`Actualizado ${new Date(data.generated_at).toLocaleTimeString()}`;
        } catch { updated.textContent='No fue posible actualizar'; if (positionStatus) positionStatus.textContent='Falló la consulta de posiciones del mapa.'; }
    };
    refresh(); setInterval(refresh, 10000);
}

document.querySelectorAll('[data-access-form]').forEach((form) => {
    const accessType = form.querySelector('[data-access-type]');
    const expirationField = form.querySelector('[data-expiration-field]');
    const expirationDate = form.querySelector('[data-expiration-date]');
    if (!accessType || !expirationField || !expirationDate) return;
    const synchronizeExpiration = () => {
        const temporary = accessType.value === 'until';
        expirationField.hidden = !temporary;
        expirationDate.disabled = !temporary;
        expirationDate.required = temporary;
    };
    accessType.addEventListener('change', synchronizeExpiration);
    synchronizeExpiration();
});

const selectAllUsers = document.querySelector('[data-select-all-users]');
const userSelections = [...document.querySelectorAll('[data-user-selection]')];
selectAllUsers?.addEventListener('change', () => userSelections.forEach((selection) => { selection.checked = selectAllUsers.checked; }));
userSelections.forEach((selection) => selection.addEventListener('change', () => {
    if (!selectAllUsers) return;
    selectAllUsers.checked = userSelections.length > 0 && userSelections.every((item) => item.checked);
    selectAllUsers.indeterminate = !selectAllUsers.checked && userSelections.some((item) => item.checked);
}));

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
