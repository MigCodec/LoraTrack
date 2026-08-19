@extends('layouts.app')
@section('title', 'Configuración')
@section('heading', 'Configuración')
@section('content')
    @php($summary = [
        'healthy' => $tasks->where('state', 'healthy')->count(),
        'running' => $tasks->whereIn('state', ['running', 'requested'])->count(),
        'failed' => $tasks->where('state', 'failed')->count(),
        'never' => $tasks->where('state', 'never')->count(),
    ])

    <section class="automation-hero" aria-labelledby="automation-title">
        <div>
            <p class="automation-eyebrow">Power Platform control center</p>
            <h2 id="automation-title">Automatizaciones del sistema</h2>
            <p>Configura la cadencia, supervisa resultados y solicita ejecuciones prioritarias desde un solo lugar.</p>
        </div>
        <div class="automation-runtime"><span aria-hidden="true"></span><strong>Laravel Scheduler</strong><small>Requiere <code>schedule:work</code> o <code>schedule:run</code></small></div>
    </section>

    <form id="automation-settings-form" method="POST" action="{{ route('settings.automation.update') }}">
        @csrf
        @method('PUT')
    </form>

    <section class="automation-policy" data-automation-policy>
        <div class="automation-policy-icon" aria-hidden="true"><x-nav-icon name="health"/></div>
        <div class="automation-policy-copy">
            <span class="automation-policy-label">Política de ejecución</span>
            <h3>Usar configuración recomendada por el sistema</h3>
            <p>Mantiene una respuesta operativa rápida sin ejecutar innecesariamente tareas de mantenimiento. Los valores recomendados siguen visibles y se bloquean para evitar cambios accidentales.</p>
        </div>
        <label class="automation-switch">
            <input form="automation-settings-form" type="checkbox" name="use_system_recommended" value="1" data-recommended-schedule @checked($recommendedMode)>
            <span aria-hidden="true"></span><strong>{{ $recommendedMode ? 'Activado' : 'Personalizado' }}</strong>
        </label>
    </section>

    <section class="automation-summary" aria-label="Resumen de automatizaciones">
        @foreach([['healthy', 'Correctas', $summary['healthy']], ['running', 'En curso', $summary['running']], ['failed', 'Con error', $summary['failed']], ['never', 'Sin historial', $summary['never']]] as [$state, $label, $count])
            <div class="is-{{ $state }}"><i aria-hidden="true"></i><span><strong>{{ $count }}</strong><small>{{ $label }}</small></span></div>
        @endforeach
    </section>

    <section class="automation-task-list" aria-label="Configuración de tareas">
        @foreach($tasks as $task)
            @php($status = $task['status'])
            @php($stateMeta = [
                'healthy' => ['Correcta', 'active'], 'failed' => ['Requiere atención', 'error'],
                'running' => ['En ejecución', 'disabled'], 'requested' => ['Solicitada', 'disabled'],
                'never' => ['Sin historial', 'disabled'],
            ][$task['state']])
            <article class="automation-task is-{{ $task['state'] }}">
                <header>
                    <div class="automation-task-title"><span aria-hidden="true"><x-nav-icon name="connectors"/></span><div><h3>{{ $task['label'] }}</h3><p>{{ $task['description'] }}</p></div></div>
                    <span class="status-badge status-{{ $stateMeta[1] }}"><i aria-hidden="true"></i>{{ $stateMeta[0] }}</span>
                </header>

                <div class="automation-task-grid">
                    <label class="automation-interval" for="interval-{{ $task['task'] }}">
                        <span>Intervalo de ejecución</span>
                        <span class="automation-input-shell">
                            <input form="automation-settings-form" id="interval-{{ $task['task'] }}" name="intervals[{{ $task['task'] }}]" type="number" min="1" max="525600" step="1" value="{{ $recommendedMode ? $task['recommended_minutes'] : $task['manual_minutes'] }}" data-schedule-interval data-recommended-value="{{ $task['recommended_minutes'] }}" data-manual-value="{{ $task['manual_minutes'] }}" required @disabled($recommendedMode)>
                            <span>minutos</span>
                        </span>
                        <small>{{ $task['frequency'] }} @if($recommendedMode) · recomendado por el sistema @endif</small>
                    </label>
                    <dl class="automation-task-metrics">
                        <div><dt>Último inicio</dt><dd>{{ $status?->last_started_at?->diffForHumans() ?? 'Nunca' }}</dd><small>{{ $status?->last_started_at?->format('d-m-Y H:i:s') }}</small></div>
                        <div><dt>Próxima ejecución</dt><dd>{{ $task['state'] === 'requested' ? 'Siguiente ciclo' : ($task['next_run_at']?->diffForHumans() ?? 'Al iniciar scheduler') }}</dd><small>{{ $task['next_run_at']?->format('d-m-Y H:i:s') }}</small></div>
                        <div><dt>Duración</dt><dd>{{ $status?->last_duration_ms !== null ? number_format($status->last_duration_ms).' ms' : '—' }}</dd><small>{{ number_format($status?->run_count ?? 0) }} ejecuciones</small></div>
                        <div><dt>Código de salida</dt><dd>{{ $status?->last_exit_code ?? '—' }}</dd><small>{{ $status?->last_succeeded_at ? 'Correcta '.$status->last_succeeded_at->diffForHumans() : 'Sin ejecución correcta' }}</small></div>
                    </dl>
                </div>

                <footer>
                    <details class="automation-details">
                        <summary>Información técnica</summary>
                        <div><span>Identificador</span><code>{{ $task['task'] }}</code><span>Comando ejecutado</span><code>{{ $task['command'] }}</code>@if($status?->last_error)<span>Último error</span><p class="automation-error">{{ $status->last_error }}</p>@endif</div>
                    </details>
                    <form method="POST" action="{{ route('settings.automation.run', $task['task']) }}">
                        @csrf
                        <button class="automation-run-button" type="submit" @disabled(in_array($task['state'], ['running', 'requested'], true))><span aria-hidden="true">▶</span>{{ $task['state'] === 'requested' ? 'Ejecución solicitada' : 'Forzar ejecución' }}</button>
                    </form>
                </footer>
            </article>
        @endforeach
    </section>

    <div class="automation-savebar">
        <div><strong>Configuración de automatizaciones</strong><span>{{ $recommendedMode ? 'El sistema administra los intervalos.' : 'Revisa los intervalos antes de guardar.' }}</span></div>
        <button form="automation-settings-form" class="btn-primary" type="submit">Guardar configuración</button>
    </div>

    <script>
        const automationPolicy = document.querySelector('[data-automation-policy]');
        if (automationPolicy) {
            const toggle = automationPolicy.querySelector('[data-recommended-schedule]');
            const label = automationPolicy.querySelector('.automation-switch strong');
            const inputs = Array.from(document.querySelectorAll('[data-schedule-interval]'));
            const applyMode = function (rememberManual) {
                inputs.forEach(function (input) {
                    if (toggle.checked) {
                        if (rememberManual) input.dataset.manualValue = input.value;
                        input.value = input.dataset.recommendedValue;
                    } else {
                        input.value = input.dataset.manualValue || input.value;
                    }
                    input.disabled = toggle.checked;
                });
                label.textContent = toggle.checked ? 'Activado' : 'Personalizado';
            };
            toggle.addEventListener('change', function () { applyMode(true); });
            applyMode(false);
        }
    </script>
@endsection
