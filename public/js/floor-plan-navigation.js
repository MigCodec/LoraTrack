document.querySelectorAll('[data-floor-plan-upload-form]').forEach((form) => {
    const modes = [...form.querySelectorAll('[data-floor-plan-mode]')];
    const fields = form?.querySelector('[data-floor-plan-3d-fields]');
    const file = form?.querySelector('[data-floor-plan-file]');
    const help = form?.querySelector('[data-floor-plan-file-help]');
    const fileStatus = form?.querySelector('[data-floor-plan-file-status]');
    const summary = form?.querySelector('[data-floor-plan-upload-summary]');
    const depth = form?.querySelector('[data-floor-plan-depth]');

    const sync = () => {
        const mode = modes.find((option) => option.checked)?.value || '2d';
        const is3d = mode === '3d';
        if (fields) fields.hidden = !is3d;
        fields?.querySelectorAll('input, select').forEach((control) => {
            control.disabled = !is3d;
        });
        if (help) help.textContent = is3d
            ? 'GLB recomendado o glTF autocontenido. El servidor actual admite archivos de hasta 40 MB.'
            : 'PNG, JPG, WEBP, PDF o DXF; máximo 20 MB.';
        if (depth) depth.required = false;
        if (summary) {
            summary.querySelector('strong').textContent = is3d ? 'Modelo 3D navegable' : 'Plano 2D navegable';
            summary.querySelector('span').textContent = is3d
                ? 'Indica ancho y largo reales. La altura y la escala se calcularán automáticamente si las dejas vacías.'
                : 'Indica el ancho y largo reales para conservar la escala de zonas y dispositivos.';
        }
    };

    const selectMode = (value) => {
        const option = modes.find((candidate) => candidate.value === value);
        if (option) option.checked = true;
        sync();
    };

    modes.forEach((mode) => mode.addEventListener('change', sync));
    file?.addEventListener('change', () => {
        const selected = file.files?.[0];
        if (!selected) {
            if (fileStatus) fileStatus.textContent = 'Ningún archivo seleccionado.';
            return;
        }

        const extension = selected.name.split('.').pop()?.toLowerCase();
        if (['glb', 'gltf'].includes(extension)) selectMode('3d');
        if (['jpg', 'jpeg', 'png', 'webp', 'pdf', 'dxf'].includes(extension)) selectMode('2d');
        if (fileStatus) {
            fileStatus.textContent = `${selected.name} · ${(selected.size / 1024 / 1024).toLocaleString(undefined, {maximumFractionDigits: 2})} MB`;
        }
    });
    sync();
});

const viewport = document.querySelector('#plan-2d-viewport');
const editor = document.querySelector('#zone-editor');

if (viewport && editor) {
    const panButton = document.querySelector('[data-plan-pan]');
    const zoomValue = document.querySelector('[data-plan-zoom-value]');
    const zoomButtons = [...document.querySelectorAll('[data-plan-zoom]')];
    let scale = 1;
    let offsetX = 0;
    let offsetY = 0;
    let panning = false;
    let panEnabled = false;
    let pointerId = null;
    let originX = 0;
    let originY = 0;

    const render = () => {
        editor.style.transform = `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
        if (zoomValue) zoomValue.value = `${Math.round(scale * 100)}%`;
        viewport.classList.toggle('is-pannable', panEnabled);
        viewport.classList.toggle('is-panning', panning);
    };

    const setScale = (nextScale) => {
        scale = Math.max(0.5, Math.min(5, nextScale));
        if (scale <= 1) {
            offsetX = 0;
            offsetY = 0;
        }
        render();
    };

    const reset = () => {
        scale = 1;
        offsetX = 0;
        offsetY = 0;
        render();
    };

    panButton?.addEventListener('click', () => {
        panEnabled = !panEnabled;
        panButton.setAttribute('aria-pressed', String(panEnabled));
        panButton.classList.toggle('is-active', panEnabled);
        render();
    });

    zoomButtons.forEach((button) => button.addEventListener('click', () => {
        if (button.dataset.planZoom === 'in') setScale(scale * 1.2);
        if (button.dataset.planZoom === 'out') setScale(scale / 1.2);
        if (button.dataset.planZoom === 'reset') reset();
    }));

    viewport.addEventListener('wheel', (event) => {
        if (editor.classList.contains('is-selecting-geometry')) return;
        event.preventDefault();
        setScale(scale * (event.deltaY < 0 ? 1.12 : 0.89));
    }, {passive: false});

    viewport.addEventListener('pointerdown', (event) => {
        const canPan = panEnabled || event.button === 1;
        if (!canPan || editor.classList.contains('is-selecting-geometry') || event.target.closest('details.plan-anchor')) return;
        event.preventDefault();
        panning = true;
        pointerId = event.pointerId;
        originX = event.clientX - offsetX;
        originY = event.clientY - offsetY;
        viewport.setPointerCapture(pointerId);
        render();
    });

    viewport.addEventListener('pointermove', (event) => {
        if (!panning || event.pointerId !== pointerId) return;
        offsetX = event.clientX - originX;
        offsetY = event.clientY - originY;
        render();
    });

    const stopPan = (event) => {
        if (!panning || event.pointerId !== pointerId) return;
        panning = false;
        if (viewport.hasPointerCapture(pointerId)) viewport.releasePointerCapture(pointerId);
        pointerId = null;
        render();
    };

    viewport.addEventListener('pointerup', stopPan);
    viewport.addEventListener('pointercancel', stopPan);
    render();
}
