document.querySelectorAll('[data-floor-plan-mode]').forEach((mode) => {
    const form = mode.closest('form');
    const fields = form?.querySelector('[data-floor-plan-3d-fields]');
    const file = form?.querySelector('[data-floor-plan-file]');
    const help = form?.querySelector('[data-floor-plan-file-help]');
    const depth = form?.querySelector('[data-floor-plan-depth]');

    const sync = () => {
        const is3d = mode.value === '3d';
        if (fields) fields.hidden = !is3d;
        if (file) file.accept = is3d ? '.glb,.gltf' : '.jpg,.jpeg,.png,.webp,.pdf,.dxf';
        if (help) help.textContent = is3d
            ? 'GLB o glTF autocontenido; máximo 100 MB. GLB es el formato recomendado.'
            : 'PNG, JPG, WEBP, PDF o DXF; máximo 20 MB.';
        if (depth) depth.required = is3d;
    };

    mode.addEventListener('change', sync);
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
