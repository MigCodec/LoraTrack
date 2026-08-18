document.querySelectorAll('[data-connectors-live]').forEach((container) => {
    const endpoint = container.dataset.endpoint;
    const status = container.querySelector('[data-live-status] span');
    let request = null;
    let timer = null;

    const formatNumber = (value) => Number(value || 0).toLocaleString('es-CL');
    const showDelta = (metric, difference) => {
        if (difference === 0) return;
        const indicator = document.createElement('span');
        indicator.className = `connector-metric-delta is-${difference > 0 ? 'increase' : 'decrease'}`;
        indicator.textContent = `${difference > 0 ? '+' : '−'}${formatNumber(Math.abs(difference))}`;
        indicator.setAttribute('aria-label', difference > 0
            ? `Aumentó en ${formatNumber(difference)}`
            : `Disminuyó en ${formatNumber(Math.abs(difference))}`);
        metric.parentElement.querySelector('.connector-metric-delta')?.remove();
        metric.parentElement.appendChild(indicator);
        window.setTimeout(() => indicator.remove(), 4000);
    };
    const updateRow = (item) => {
        const row = container.querySelector(`[data-connector-id="${CSS.escape(String(item.id))}"]`);
        if (!row) return;
        ['telemetry_events_count', 'processed_events_count', 'failed_events_count'].forEach((field) => {
            const metric = row.querySelector(`[data-live-metric="${field}"]`);
            if (!metric) return;
            const previous = Number(metric.dataset.value ?? metric.textContent.replace(/[^0-9-]/g, '') ?? 0);
            const current = Number(item[field] || 0);
            metric.dataset.value = String(current);
            metric.textContent = formatNumber(current);
            showDelta(metric, current - previous);
        });
        const activity = row.querySelector('[data-live-activity]');
        if (activity) activity.textContent = item.last_activity_label || 'Sin actividad';
    };
    const refresh = async () => {
        if (!endpoint || document.hidden || request) return;
        request = new AbortController();
        try {
            const response = await fetch(endpoint, {
                headers: {Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                signal: request.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const payload = await response.json();
            (payload.data || []).forEach(updateRow);
            container.classList.remove('has-live-error');
            if (status) status.textContent = 'Actualizado ahora';
        } catch (error) {
            if (error.name !== 'AbortError') {
                container.classList.add('has-live-error');
                if (status) status.textContent = 'Sin conexión · reintentando';
            }
        } finally {
            request = null;
        }
    };
    const schedule = () => {
        window.clearInterval(timer);
        if (!document.hidden) timer = window.setInterval(refresh, 5000);
    };
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) request?.abort();
        else refresh();
        schedule();
    });
    schedule();
});
