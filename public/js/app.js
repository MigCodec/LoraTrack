window.LoraTrack = window.LoraTrack || {};
window.LoraTrack.pollWhenVisible = (callback, intervalMs) => {
    let timer = null;
    let stopped = false;

    const run = async () => {
        if (stopped) return;
        if (!document.hidden) await callback();
        if (!stopped) timer = window.setTimeout(run, intervalMs);
    };

    const restartOnVisible = () => {
        if (document.hidden || stopped) return;
        window.clearTimeout(timer);
        run();
    };

    document.addEventListener('visibilitychange', restartOnVisible);
    run();

    return () => {
        stopped = true;
        window.clearTimeout(timer);
        document.removeEventListener('visibilitychange', restartOnVisible);
    };
};

document.querySelectorAll('[data-toast]').forEach((toast) => {
    const close = () => {
        toast.classList.add('is-dismissing');
        window.setTimeout(() => toast.remove(), 160);
    };
    toast.querySelector('[data-toast-close]')?.addEventListener('click', close);
    if (toast.classList.contains('app-toast-success')) {
        window.setTimeout(close, 6000);
    }
});

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
    const supportsPopover = typeof sheetContextMenu.showPopover === 'function';
    const sheetMenuIsOpen = () => supportsPopover
        ? sheetContextMenu.matches(':popover-open')
        : !sheetContextMenu.hidden;

    const closeSheetMenu = () => {
        if (supportsPopover && sheetContextMenu.matches(':popover-open')) sheetContextMenu.hidePopover();
        sheetContextMenu.hidden = true;
    };
    const openSheetMenu = (tab, clientX, clientY) => {
        closeSheetMenu();
        selectedSheet = tab;
        sheetContextMenu.hidden = false;
        if (supportsPopover) sheetContextMenu.showPopover();
        const width = sheetContextMenu.offsetWidth;
        const height = sheetContextMenu.offsetHeight;
        const viewportPadding = 8;
        const left = Math.max(viewportPadding, Math.min(clientX, window.innerWidth - width - viewportPadding));
        const top = Math.max(viewportPadding, Math.min(clientY, window.innerHeight - height - viewportPadding));
        sheetContextMenu.style.left = `${left}px`;
        sheetContextMenu.style.top = `${top}px`;
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
    document.querySelectorAll('[data-close-rename]').forEach((button) => button.addEventListener('click', () => renameDialog.close()));
    colorInput?.addEventListener('input', () => { colorValue.value = colorInput.value; });
    document.querySelectorAll('[data-close-color]').forEach((button) => button.addEventListener('click', () => colorDialog.close()));
    document.querySelector('[data-reset-tab-color]')?.addEventListener('click', () => {
        colorValue.value = '';
        colorForm.requestSubmit();
    });
    document.addEventListener('pointerdown', (event) => {
        if (sheetMenuIsOpen() && !sheetContextMenu.contains(event.target)) closeSheetMenu();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sheetMenuIsOpen()) {
            closeSheetMenu();
            selectedSheet?.focus();
        }
    });
    window.addEventListener('blur', closeSheetMenu);
    window.addEventListener('resize', closeSheetMenu);
    window.addEventListener('scroll', closeSheetMenu, true);
}

const assetForm = document.querySelector('#asset-form');
if (assetForm) {
    const mobility = assetForm.elements.mobility;
    const trackerField = assetForm.querySelector('[data-mobile-tracker-field]');
    const staticBeaconField = assetForm.querySelector('[data-static-beacon-field]');
    const syncAssetDeviceFields = () => {
        if (!mobility) return;
        const mobile = mobility.value === 'mobile';
        if (trackerField) {
            trackerField.hidden = !mobile;
            trackerField.querySelectorAll('select,input').forEach((control) => { control.disabled = !mobile; });
        }
        if (staticBeaconField) {
            staticBeaconField.hidden = mobile;
            staticBeaconField.querySelectorAll('select,input').forEach((control) => { control.disabled = mobile; });
        }
    };
    mobility?.addEventListener('change', syncAssetDeviceFields);
    syncAssetDeviceFields();
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
    let zoneEditForm = null;
    let activeMode = null;

    const setMode = (mode) => {
        activeMode = mode;
        zoneModeButton?.classList.toggle('is-active', mode === 'zone');
        ribbonAnchorModeButton?.classList.toggle('is-active', mode === 'anchor');
        editor.classList.toggle('is-selecting-geometry', ['zone', 'edit-zone', 'anchor', 'relocate-anchor'].includes(mode));
        if (['zone', 'edit-zone'].includes(mode)) editor.dataset.selectionInstruction = 'Arrastra para definir el área';
        else if (['anchor', 'relocate-anchor'].includes(mode)) editor.dataset.selectionInstruction = 'Haz clic para definir la posición';
        else delete editor.dataset.selectionInstruction;
        canvas.style.pointerEvents = ['zone', 'edit-zone', 'anchor', 'relocate-anchor'].includes(mode) ? 'auto' : 'none';
        canvas.style.cursor = ['zone', 'edit-zone'].includes(mode) ? 'crosshair' : ['anchor', 'relocate-anchor'].includes(mode) ? 'copy' : 'default';
        if (modeStatus) modeStatus.textContent = ['zone', 'edit-zone'].includes(mode) ? 'Modo área activo: arrastra sobre el plano.' : mode === 'anchor' ? 'Modo punto de referencia activo: haz clic en una ubicación conocida.' : mode === 'relocate-anchor' ? 'Reubicando punto de referencia: haz clic en su nueva posición.' : 'Selecciona una herramienta para editar el plano.';
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

    const selectedReferenceType = () => anchorForm?.querySelector('.js-reference-type:checked')?.value || 'beacon';

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
        if (draft) drawRectangle(draft, (zoneEditForm || form)?.elements.color?.value || brand.getPropertyValue('--color-brand-accent').trim() || '#14B8A6', zoneEditForm?.elements.name?.value || 'Nueva zona');
        [...installationData, ...(draftAnchor ? [{...draftAnchor, name: 'Nuevo punto', type: selectedReferenceType()}] : [])].forEach((installation) => {
            const x = installation.x * width;
            const y = installation.y * height;
            const scanner = installation.type === 'scanner';
            context.beginPath();
            if (scanner) {
                context.arc(x, y, 6, 0, Math.PI * 2);
            } else {
                context.moveTo(x, y - 7);
                context.lineTo(x + 7, y);
                context.lineTo(x, y + 7);
                context.lineTo(x - 7, y);
                context.closePath();
            }
            context.fillStyle = installation === draftAnchor ? '#dc2626' : scanner ? '#7c3aed' : brand.getPropertyValue('--color-brand-primary').trim() || '#2563EB';
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
            if (anchorForm.elements.x_meters) anchorForm.elements.x_meters.value = (draftAnchor.x * planWidthMeters).toFixed(3);
            if (anchorForm.elements.y_meters) anchorForm.elements.y_meters.value = (draftAnchor.y * planHeightMeters).toFixed(3);
            anchorSubmit.disabled = false;
            anchorStatus.textContent = 'Punto seleccionado. Guarda el punto de referencia.';
            anchorStatus.className = 'rounded-lg bg-emerald-50 p-3 text-xs text-emerald-800';
            setMode(null);
            anchorModeButton.textContent = 'Cambiar punto en plano';
            redraw();
            window.setTimeout(() => { if (anchorCommand) anchorCommand.open = true; }, 0);
            return;
        }
        if (!form || !['zone', 'edit-zone'].includes(activeMode)) return;
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
        const targetForm = zoneEditForm || form;
        if (!start || !targetForm) return;
        draft = rectangle(start, pointer(event));
        start = null;
        const valid = draft.x_max - draft.x_min > 0.005 && draft.y_max - draft.y_min > 0.005;
        ['x_min', 'y_min', 'x_max', 'y_max'].forEach((key) => {
            targetForm.elements[key].value = valid ? draft[key].toFixed(7) : '';
        });
        if (!zoneEditForm) submit.disabled = !valid;
        if (geometryMetrics) {
            geometryMetrics.hidden = !valid;
            if (valid) {
                const widthMeters = (draft.x_max - draft.x_min) * planWidthMeters;
                const heightMeters = (draft.y_max - draft.y_min) * planHeightMeters;
                zoneArea.textContent = `${(widthMeters * heightMeters).toLocaleString(undefined, {maximumFractionDigits: 2})} m²`;
                zonePerimeter.textContent = `${(2 * (widthMeters + heightMeters)).toLocaleString(undefined, {maximumFractionDigits: 2})} m`;
            }
        }
        const targetStatus = zoneEditForm?.querySelector('[data-zone-edit-status]') || status;
        targetStatus.textContent = valid ? 'Área definida. Guarda los cambios para aplicarla.' : 'El rectángulo es demasiado pequeño.';
        if (!zoneEditForm) zoneDrawButton.textContent = valid ? 'Volver a definir el área' : 'Definir área en el plano';
        setMode(null);
        redraw();
        if (!zoneEditForm) window.setTimeout(() => { if (zoneCommand) zoneCommand.open = true; }, 0);
        else window.setTimeout(() => {
            targetForm.closest('details')?.setAttribute('open', '');
            targetForm.closest('details.ribbon-layers')?.setAttribute('open', '');
        }, 0);
        zoneEditForm = null;
    });

    form?.elements.color?.addEventListener('input', redraw);
    document.querySelectorAll('[data-zone-edit-form] input[type="color"]').forEach((input) => input.addEventListener('input', redraw));
    const activateZoneMode = () => {
        relocationForm = null;
        zoneEditForm = null;
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
        anchorStatus.textContent = 'Haz clic en la posición conocida del punto de referencia.';
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
    document.querySelectorAll('[data-zone-redefine]').forEach((button) => button.addEventListener('click', () => {
        relocationForm = null;
        draftAnchor = null;
        zoneEditForm = button.closest('[data-zone-edit-form]');
        const zone = zoneData.find((item) => String(item.id) === String(zoneEditForm?.dataset.zoneId));
        draft = zone ? {...zone} : null;
        setMode('edit-zone');
        zoneEditForm?.closest('details.ribbon-layers')?.removeAttribute('open');
        const editStatus = zoneEditForm?.querySelector('[data-zone-edit-status]');
        if (editStatus) editStatus.textContent = 'Arrastra sobre el plano para redefinir el área.';
        redraw();
        editor.scrollIntoView({block: 'nearest'});
    }));
    anchorModeButton?.addEventListener('click', () => {
        relocationForm = null;
        activateAnchorMode();
        if (anchorCommand) anchorCommand.removeAttribute('open');
        anchorModeButton.textContent = 'Haz clic sobre el plano…';
        anchorStatus.textContent = 'Modo punto de referencia activo: haz clic en la posición conocida.';
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
    let loading = false;
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
        setDetail('#asset-detail-raw-position', position.raw_x_meters === null ? '—' : `X ${position.raw_x_meters.toFixed(2)} m · Y ${position.raw_y_meters.toFixed(2)} m`);
        setDetail('#asset-detail-zone', position.zone || 'Sin zona');
        setDetail('#asset-detail-confidence', `${Math.round(position.confidence * 100)}%`);
        setDetail('#asset-detail-error', `±${position.accuracy_meters.toFixed(2)} m`);
        setDetail('#asset-detail-algorithm', `${position.algorithm} v${position.algorithm_version}`);
        setDetail('#asset-detail-last-seen', formatDate(position.last_seen_at));
        setDetail('#asset-detail-calculated', formatDate(position.calculated_at));
        setDetail('#asset-detail-observed', formatDate(position.observed_at));
        setDetail('#asset-detail-received', formatDate(position.received_at));
        const trackLink = document.querySelector('#asset-detail-track-link');
        if (trackLink) trackLink.href = position.track_url || '#';
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
        if (loading) return;
        loading = true;
        try {
            const response = await fetch(realtimeMap.dataset.endpoint, {headers:{Accept:'application/json'}}); if (!response.ok) throw new Error(`HTTP ${response.status}`); const data = await response.json();
            markers.querySelectorAll('.map-anchor,.asset-marker,.asset-uncertainty,.asset-detection-circle').forEach((node) => node.remove());
            data.anchors.forEach((anchor) => {
                const node=document.createElement('span'); node.className=`map-anchor ${anchor.type}`; node.style.left=`${anchor.x*100}%`; node.style.top=`${anchor.y*100}%`; node.title=`${anchor.name} · ${anchor.identifier}`; node.setAttribute('aria-label', anchor.name); const marker=document.createElement('i'); marker.setAttribute('aria-hidden', 'true'); node.appendChild(marker); markers.appendChild(node);
            });
            data.positions.forEach((position) => {
                const uncertaintyDiameter = Math.max(1.5, Math.min(200, position.relative_error * 200));
                const uncertainty=document.createElement('div'); uncertainty.className=`asset-uncertainty${position.stale?' stale':''}`; uncertainty.style.left=`${position.x*100}%`; uncertainty.style.top=`${position.y*100}%`; uncertainty.style.width=`${uncertaintyDiameter}%`; uncertainty.style.height=`${uncertaintyDiameter}%`; uncertainty.title=`Error estimado: ${position.accuracy_meters.toFixed(2)} m · relativo ${(position.relative_error*100).toFixed(2)}%`; markers.appendChild(uncertainty);
                const node=document.createElement('button'); node.type='button'; node.dataset.assetId=position.asset_id; node.setAttribute('aria-haspopup','dialog'); node.setAttribute('aria-label', `Ver detalles de ${position.name}`); node.className=`asset-marker${position.stale?' stale':''}${position.out_of_bounds?' out-of-bounds':''}`; node.style.left=`${position.x*100}%`; node.style.top=`${position.y*100}%`; node.title=`${position.name} · ${position.product||''} · ${position.zone||'Sin zona'} · última señal ${formatDate(position.last_seen_at)} · confianza ${Math.round(position.confidence*100)}% · error ±${position.accuracy_meters.toFixed(2)} m${position.out_of_bounds?' · fuera del plano':''}`; node.appendChild(spatialMarkerIcon('asset')); node.addEventListener('click',()=>showAssetDetails(position,node)); markers.appendChild(node);
            });
            const selectedPosition = data.positions.find((position) => position.asset_id === selectedAssetId);
            if (selectedPosition && technicalDialog?.open) showAssetDetails(selectedPosition, markers.querySelector(`[data-asset-id="${CSS.escape(selectedPosition.asset_id)}"]`), false);
            applyMapLayers();
            if (positionStatus) positionStatus.textContent = data.positions.length
                ? `${data.positions.length} activo(s) triangulado(s) en este plano.`
                : 'Sin posiciones calculadas para este plano. Verifica tracker asignado, uplink BLE procesado y al menos 3 beacons instalados.';
            updated.textContent=`Actualizado ${new Date(data.generated_at).toLocaleTimeString()}`;
        } catch { updated.textContent='No fue posible actualizar'; if (positionStatus) positionStatus.textContent='Falló la consulta de posiciones del mapa.'; }
        finally { loading = false; }
    };
    window.LoraTrack.pollWhenVisible(refresh, 30000);
}

document.querySelectorAll('[data-device-ap-history-toggle]').forEach((button) => {
    const panel = document.getElementById(button.dataset.target || '');
    if (!panel) return;
    const rows = panel.querySelector('[data-device-ap-history-rows]');
    const status = panel.querySelector('[data-device-ap-history-status]');
    const pagination = panel.querySelector('[data-device-ap-history-pagination]');
    let loaded = false;
    let controller = null;

    const setMessage = (message) => {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 4;
        cell.textContent = message;
        row.appendChild(cell);
        rows?.replaceChildren(row);
    };
    const appendText = (parent, tag, text, className = null) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        element.textContent = text;
        parent.appendChild(element);
        return element;
    };
    const renderRows = (items) => {
        if (!rows) return;
        if (!items.length) {
            setMessage('Sin AP MAC detectadas dentro de la retencion vigente.');
            return;
        }
        rows.replaceChildren(...items.map((item) => {
            const row = document.createElement('tr');
            const ap = document.createElement('td');
            appendText(ap, 'code', item.ap_mac || 'Sin AP MAC', 'text-xs');
            const rssi = document.createElement('td');
            rssi.textContent = item.rssi === null || item.rssi === undefined ? 'Sin RSSI' : `${item.rssi} dBm`;
            const observed = document.createElement('td');
            appendText(observed, 'span', item.observed_at_human || 'Sin fecha', 'block text-sm text-slate-700');
            appendText(observed, 'span', item.observed_at_label || '', 'mt-1 block text-xs text-slate-400');
            const source = document.createElement('td');
            source.textContent = item.source || 'telemetria';
            row.append(ap, rssi, observed, source);
            return row;
        }));
    };
    const pageButton = (label, page, disabled = false, active = false) => {
        const pageControl = document.createElement('button');
        pageControl.type = 'button';
        pageControl.className = active ? 'btn-primary' : 'btn-secondary';
        pageControl.textContent = label;
        pageControl.disabled = disabled;
        pageControl.addEventListener('click', () => load(page));
        return pageControl;
    };
    const renderPagination = (meta) => {
        if (!pagination) return;
        const summary = document.createElement('span');
        summary.className = 'text-sm text-slate-500';
        summary.textContent = meta.total
            ? `Mostrando ${meta.from}-${meta.to} de ${meta.total} detecciones`
            : `Sin detecciones en los ultimos ${meta.retention_days || 6} dias`;

        const controls = document.createElement('div');
        controls.className = 'flex flex-wrap gap-2';
        const current = Number(meta.current_page || 1);
        const last = Number(meta.last_page || 1);
        controls.appendChild(pageButton('Anterior', Math.max(1, current - 1), current <= 1));
        for (let page = Math.max(1, current - 2); page <= Math.min(last, current + 2); page += 1) {
            controls.appendChild(pageButton(String(page), page, false, page === current));
        }
        controls.appendChild(pageButton('Siguiente', Math.min(last, current + 1), current >= last));
        pagination.replaceChildren(summary, controls);
    };
    async function load(page = 1) {
        if (!button.dataset.endpoint) return;
        if (controller) controller.abort();
        controller = new AbortController();
        setMessage('Cargando historial AP MAC...');
        if (status) status.textContent = 'Cargando';
        const url = new URL(button.dataset.endpoint, window.location.origin);
        url.searchParams.set('page', String(page));
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            renderRows(payload.data || []);
            renderPagination(payload.meta || {current_page: 1, last_page: 1, total: 0, retention_days: 6});
            loaded = true;
            if (status) status.textContent = `Retencion ${payload.meta?.retention_days || 6} dias`;
        } catch (error) {
            if (error.name !== 'AbortError') {
                setMessage('No fue posible cargar el historial AP MAC.');
                pagination?.replaceChildren();
                if (status) status.textContent = 'Error';
            }
        }
    }

    button.addEventListener('click', () => {
        const expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        panel.hidden = expanded;
        button.textContent = expanded ? 'Ver historial AP MAC' : 'Ocultar historial AP MAC';
        if (!expanded && !loaded) load(1);
    });
});

document.querySelectorAll('[data-meraki-access-points]').forEach((container) => {
    const endpoint = container.dataset.endpoint;
    const search = container.querySelector('[data-meraki-access-point-search]');
    const rows = container.querySelector('[data-meraki-access-point-rows]');
    const pagination = container.querySelector('[data-meraki-access-point-pagination]');
    let controller = null;
    let debounce = null;
    let currentPage = Number(new URLSearchParams(window.location.search).get('page') || 1);

    const appendText = (parent, tag, text, className = null) => {
        const element = document.createElement(tag);
        if (className) element.className = className;
        element.textContent = text;
        parent.appendChild(element);
        return element;
    };
    const appendOptionalText = (parent, tag, text, className = null) => {
        if (!text) return null;
        return appendText(parent, tag, text, className);
    };
    const setMessage = (message) => {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 5;
        cell.textContent = message;
        row.appendChild(cell);
        rows.replaceChildren(row);
    };
    const updateBrowserUrl = (page) => {
        const params = new URLSearchParams(window.location.search);
        const query = search?.value.trim() || '';
        if (page > 1) params.set('page', String(page));
        else params.delete('page');
        if (query !== '') params.set('q', query);
        else params.delete('q');
        const next = `${window.location.pathname}${params.toString() ? `?${params}` : ''}`;
        window.history.replaceState({}, '', next);
    };
    const renderRows = (items) => {
        if (!items.length) {
            setMessage('No hay AP Meraki para los filtros actuales.');
            return;
        }
        rows.replaceChildren(...items.map((item) => {
            const row = document.createElement('tr');

            const ap = document.createElement('td');
            appendText(ap, 'strong', item.name || 'AP sin nombre', 'block text-sm');
            appendText(ap, 'code', item.identifier || 'Sin MAC', 'text-xs');
            appendOptionalText(ap, 'span', item.model, 'mt-1 block text-xs text-slate-400');

            const meraki = document.createElement('td');
            appendText(meraki, 'span', `Serial: ${item.serial || 'Sin serial'}`, 'block text-sm text-slate-700');
            appendText(meraki, 'span', `Network: ${item.network_id || 'Sin network_id'}`, 'mt-1 block text-xs text-slate-400');
            if (item.reported_latitude !== null && item.reported_longitude !== null) {
                appendText(meraki, 'span', `Meraki lat/lng: ${item.reported_latitude}, ${item.reported_longitude}`, 'mt-1 block text-xs text-slate-400');
            }

            const location = document.createElement('td');
            appendText(location, 'span', item.status_label, `status-badge status-${item.status_class}`);
            appendText(location, 'span', item.location_label, 'mt-2 block text-sm text-slate-700');

            const clients = document.createElement('td');
            appendText(clients, 'strong', Number(item.clients_count || 0).toLocaleString(), 'block text-sm text-slate-800');
            appendText(clients, 'span', 'MAC cliente distintas observadas por este AP', 'text-xs text-slate-400');

            const activity = document.createElement('td');
            appendText(activity, 'span', item.last_activity_human || 'Sin senal', 'text-sm text-slate-700');
            appendOptionalText(activity, 'span', item.last_activity_label, 'mt-1 block text-xs text-slate-400');

            row.append(ap, meraki, location, clients, activity);
            return row;
        }));
    };
    const pageButton = (label, page, disabled = false, active = false) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = active ? 'btn-primary' : 'btn-secondary';
        button.textContent = label;
        button.disabled = disabled;
        button.addEventListener('click', () => load(page));
        return button;
    };
    const renderPagination = (meta) => {
        const summary = document.createElement('span');
        summary.className = 'text-sm text-slate-500';
        summary.textContent = meta.total
            ? `Mostrando ${meta.from}-${meta.to} de ${meta.total} AP`
            : 'Sin resultados';

        const controls = document.createElement('div');
        controls.className = 'mt-3 flex flex-wrap gap-2';
        controls.appendChild(pageButton('Anterior', Math.max(1, meta.current_page - 1), meta.current_page <= 1));
        const first = Math.max(1, meta.current_page - 2);
        const last = Math.min(meta.last_page, meta.current_page + 2);
        for (let page = first; page <= last; page += 1) {
            controls.appendChild(pageButton(String(page), page, false, page === meta.current_page));
        }
        controls.appendChild(pageButton('Siguiente', Math.min(meta.last_page, meta.current_page + 1), meta.current_page >= meta.last_page));
        pagination.replaceChildren(summary, controls);
    };
    async function load(page = 1) {
        if (!endpoint) return;
        currentPage = page;
        if (controller) controller.abort();
        controller = new AbortController();
        container.setAttribute('aria-busy', 'true');
        setMessage('Cargando AP Meraki...');
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('page', String(page));
        const query = search?.value.trim() || '';
        if (query !== '') url.searchParams.set('q', query);
        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: controller.signal});
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            renderRows(payload.data || []);
            renderPagination(payload.meta || {current_page: 1, last_page: 1, total: 0});
            updateBrowserUrl(page);
        } catch (error) {
            if (error.name !== 'AbortError') {
                setMessage('No fue posible cargar los AP Meraki.');
                pagination.replaceChildren();
            }
        } finally {
            container.removeAttribute('aria-busy');
        }
    }

    const initialParams = new URLSearchParams(window.location.search);
    if (search && initialParams.get('q')) search.value = initialParams.get('q');
    search?.addEventListener('input', () => {
        window.clearTimeout(debounce);
        debounce = window.setTimeout(() => load(1), 250);
    });
    load(currentPage);
});

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

document.querySelectorAll('[data-recipient-picker]').forEach((picker) => {
    const search = picker.querySelector('[data-recipient-search]');
    const options = [...picker.querySelectorAll('[data-recipient-option]')];
    const count = picker.querySelector('[data-recipient-count]');
    const refreshCount = () => {
        const selected = options.filter((option) => option.querySelector('input').checked).length;
        count.textContent = `${selected} seleccionado${selected === 1 ? '' : 's'}`;
        options.forEach((option) => option.setAttribute('aria-selected', option.querySelector('input').checked ? 'true' : 'false'));
    };
    const filter = () => {
        const query = search.value.trim().toLocaleLowerCase();
        options.forEach((option) => { option.hidden = query !== '' && !option.dataset.searchValue.includes(query); });
    };
    search?.addEventListener('input', filter);
    options.forEach((option) => option.querySelector('input').addEventListener('change', refreshCount));
    picker.querySelector('[data-recipient-select-all]')?.addEventListener('click', () => {
        options.filter((option) => !option.hidden).forEach((option) => { option.querySelector('input').checked = true; });
        refreshCount();
    });
    picker.querySelector('[data-recipient-clear]')?.addEventListener('click', () => {
        options.forEach((option) => { option.querySelector('input').checked = false; });
        refreshCount();
    });
    refreshCount();
});

const calibrationForm = document.querySelector('#calibration-form');

document.querySelectorAll('[data-rule-builder]').forEach((builder) => {
    const subject = builder.querySelector('[data-rule-subject]');
    const trigger = builder.querySelector('[data-rule-trigger]');
    const subjectValue = builder.querySelector('[data-rule-subject-value]');
    const zone = builder.querySelector('[data-rule-zone]');
    const threshold = builder.querySelector('[data-rule-threshold]');
    const duration = builder.querySelector('[data-rule-duration]');
    const refresh = () => {
        subjectValue.hidden = subject.value !== 'asset';
        zone.hidden = !trigger.value.startsWith('zone_');
        threshold.hidden = !trigger.value.startsWith('speed_');
        duration.hidden = !['zone_inside', 'zone_outside'].includes(trigger.value);
        subjectValue.querySelector('select').disabled = subjectValue.hidden;
        zone.querySelector('select').disabled = zone.hidden;
        threshold.querySelector('input').disabled = threshold.hidden;
        duration.querySelector('input').disabled = duration.hidden;
    };
    subject.addEventListener('change', refresh);
    trigger.addEventListener('change', refresh);
    refresh();
});

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
