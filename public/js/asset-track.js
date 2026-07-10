(function () {
    const root = document.querySelector('[data-asset-track]');
    if (!root) return;

    const endpoint = root.dataset.endpoint;
    const planSelect = root.querySelector('[data-track-plan]');
    const rangeSelect = root.querySelector('[data-track-range]');
    const liveToggle = root.querySelector('[data-track-live]');
    const svg = root.querySelector('[data-track-svg]');
    const image = root.querySelector('[data-track-image]');
    const tooltip = root.querySelector('[data-track-tooltip]');
    const count = root.querySelector('[data-track-count]');
    const current = root.querySelector('[data-track-current]');
    const status = root.querySelector('[data-track-status]');

    if (!endpoint || !svg || !planSelect) return;

    const ns = 'http://www.w3.org/2000/svg';
    const liveRefreshMs = 30000;
    let positions = [];
    let timer = null;
    let loading = false;
    let liveRefreshQueued = false;

    const formatDate = (value) => {
        if (!value) return '—';
        const date = new Date(value);
        if (Number.isNaN(date.getTime())) return '—';
        return date.toLocaleString();
    };

    const qualityIsLow = (position) => position.confidence < 0.45 || position.relative_error > 0.12 || position.out_of_bounds;

    const params = (after = null) => {
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('floor_plan_id', planSelect.value);
        url.searchParams.set('range', rangeSelect?.value || '24h');
        if (after) url.searchParams.set('after', after);
        return url;
    };

    const point = (position) => `${position.x * 1000},${position.y * 1000}`;

    const make = (name, attrs = {}) => {
        const node = document.createElementNS(ns, name);
        Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
        return node;
    };

    const showTooltip = (event, position) => {
        if (!tooltip) return;
        tooltip.innerHTML = [
            `<strong>${formatDate(position.calculated_at)}</strong>`,
            `<span>X ${position.x_meters.toFixed(2)} m · Y ${position.y_meters.toFixed(2)} m</span>`,
            `<span>Zona: ${position.zone || 'Sin zona'}</span>`,
            `<span>Confianza: ${Math.round(position.confidence * 100)}% · error ±${position.accuracy_meters.toFixed(2)} m</span>`,
            `<span>Método: ${position.algorithm} v${position.algorithm_version}</span>`,
            `<span>Evidencia usada: ${position.evidence_count} ancla(s)</span>`,
        ].join('');
        tooltip.hidden = false;
        const mapRect = tooltip.parentElement.getBoundingClientRect();
        const clientX = event.clientX || mapRect.left + (position.x * mapRect.width);
        const clientY = event.clientY || mapRect.top + (position.y * mapRect.height);
        tooltip.style.left = `${clientX - mapRect.left}px`;
        tooltip.style.top = `${clientY - mapRect.top}px`;
    };

    const hideTooltip = () => {
        if (tooltip) tooltip.hidden = true;
    };

    const render = () => {
        svg.replaceChildren();
        hideTooltip();

        if (count) count.textContent = String(positions.length);
        if (!positions.length) {
            if (current) current.textContent = 'Sin posiciones en el rango';
            return;
        }

        const groups = [];
        let active = [];
        positions.forEach((position) => {
            if (!active.length || qualityIsLow(position) === qualityIsLow(active[active.length - 1])) {
                active.push(position);
                return;
            }
            groups.push(active);
            active = [active[active.length - 1], position];
        });
        if (active.length) groups.push(active);

        groups.forEach((group) => {
            if (group.length < 2) return;
            svg.appendChild(make('polyline', {
                points: group.map(point).join(' '),
                class: `asset-track-segment${qualityIsLow(group[group.length - 1]) ? ' is-low-confidence' : ''}`,
            }));
        });

        const last = positions[positions.length - 1];
        const radiusX = Math.max(8, Math.min(260, last.relative_error * 1000));
        const radiusY = Math.max(8, Math.min(260, last.relative_error * 1000));
        svg.appendChild(make('ellipse', {
            cx: last.x * 1000,
            cy: last.y * 1000,
            rx: radiusX,
            ry: radiusY,
            class: 'asset-track-accuracy',
        }));

        positions.forEach((position, index) => {
            const node = make('circle', {
                cx: position.x * 1000,
                cy: position.y * 1000,
                r: index === positions.length - 1 ? 8 : 5,
                class: [
                    'asset-track-point',
                    qualityIsLow(position) ? 'is-low-confidence' : '',
                    index === 0 ? 'is-start' : '',
                    index === positions.length - 1 ? 'is-current' : '',
                ].filter(Boolean).join(' '),
                tabindex: '0',
            });
            node.addEventListener('mouseenter', (event) => showTooltip(event, position));
            node.addEventListener('focus', (event) => showTooltip(event, position));
            node.addEventListener('mouseleave', hideTooltip);
            node.addEventListener('blur', hideTooltip);
            svg.appendChild(node);
        });

        if (current) current.textContent = `${formatDate(last.calculated_at)} · X ${last.x_meters.toFixed(2)} m · Y ${last.y_meters.toFixed(2)} m`;
    };

    const load = async (append = false) => {
        if (loading) return;
        loading = true;
        if (status) status.textContent = append ? 'Buscando posiciones nuevas…' : 'Cargando recorrido…';
        try {
            const after = append && positions.length ? positions[positions.length - 1].calculated_at : null;
            const response = await fetch(params(after), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('HTTP ' + response.status);
            const data = await response.json();
            if (append) {
                const known = new Set(positions.map((position) => position.id));
                positions = positions.concat((data.positions || []).filter((position) => !known.has(position.id)));
            } else {
                positions = data.positions || [];
            }
            render();
            if (status) status.textContent = `Actualizado ${formatDate(data.generated_at)}`;
        } catch (error) {
            if (status) status.textContent = 'No fue posible cargar el recorrido';
        } finally {
            loading = false;
        }
    };

    const restartLive = () => {
        if (timer) window.clearTimeout(timer);
        timer = null;
        liveRefreshQueued = false;
        if (!liveToggle?.checked) return;

        const schedule = () => {
            if (!liveToggle?.checked || liveRefreshQueued) return;
            liveRefreshQueued = true;
            timer = window.setTimeout(async () => {
                liveRefreshQueued = false;
                if (!document.hidden) await load(true);
                schedule();
            }, liveRefreshMs);
        };

        schedule();
    };

    planSelect.addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('plan', planSelect.value);
        window.history.replaceState({}, '', url);
        const selected = planSelect.selectedOptions[0];
        if (image && selected?.dataset.file) {
            image.src = selected.dataset.file;
            image.alt = `Recorrido sobre ${selected.textContent}`;
        }
        load(false);
        restartLive();
    });
    rangeSelect?.addEventListener('change', () => {
        load(false);
        restartLive();
    });
    liveToggle?.addEventListener('change', restartLive);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && liveToggle?.checked) load(true);
    });

    load(false);
    restartLive();
})();
