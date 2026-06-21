@extends('layouts.app')

@section('title', 'Alertas')
@section('heading', 'Alertas y notificaciones')

@section('content')
    <div class="grid gap-6 xl:grid-cols-[22rem_1fr]">
        <form class="panel space-y-4 p-5" method="POST" action="{{ route('alerts.update') }}">
            @csrf
            @method('PUT')
            <label class="flex gap-2"><input type="checkbox" name="enabled" value="1" @checked($settings->enabled)> Alertas habilitadas</label>

            <fieldset class="alert-recipient-picker" data-recipient-picker>
                <legend>Destinatarios</legend>
                <div class="alert-recipient-toolbar">
                    <input type="search" placeholder="Buscar usuario…" aria-label="Buscar destinatario" data-recipient-search>
                    <span data-recipient-count>0 seleccionados</span>
                </div>
                <div class="alert-recipient-actions"><button type="button" data-recipient-select-all>Seleccionar visibles</button><button type="button" data-recipient-clear>Limpiar</button></div>
                <div class="alert-recipient-list" role="listbox" aria-label="Usuarios de la empresa" aria-multiselectable="true">
                    @php($selectedIds = array_map('strval', (array) old('recipient_user_ids', $selectedRecipientIds)))
                    @forelse($recipientMemberships as $membership)
                        <label data-recipient-option data-search-value="{{ mb_strtolower($membership->user->name.' '.$membership->user->email.' '.$membership->role->label()) }}">
                            <input type="checkbox" name="recipient_user_ids[]" value="{{ $membership->user_id }}" @checked(in_array((string) $membership->user_id, $selectedIds, true))>
                            <span><strong>{{ $membership->user->name }}</strong><small>{{ $membership->user->email }} · {{ $membership->role->label() }}</small></span>
                        </label>
                    @empty
                        <p class="alert-recipient-empty">No hay miembros activos disponibles.</p>
                    @endforelse
                </div>
                <p class="alert-recipient-help">Sólo aparecen usuarios con acceso vigente a esta empresa.</p>
                @error('recipient_user_ids')<p class="mt-2 text-xs text-red-700">{{ $message }}</p>@enderror
            </fieldset>

            <label class="field-label">Minutos sin señal<input class="field-input" type="number" name="offline_minutes" min="5" max="10080" value="{{ old('offline_minutes', $settings->offline_minutes) }}" required></label>
            <label class="field-label">Confianza mínima<input class="field-input" type="number" step="0.01" min="0" max="1" name="minimum_confidence" value="{{ old('minimum_confidence', $settings->minimum_confidence) }}" required></label>
            @foreach(['device_offline' => 'Dispositivo offline', 'connector_error' => 'Error de conector', 'low_confidence' => 'Baja confianza'] as $value => $label)
                <label class="flex gap-2"><input type="checkbox" name="enabled_types[]" value="{{ $value }}" @checked(in_array($value, old('enabled_types', $settings->enabled_types ?? []), true))>{{ $label }}</label>
            @endforeach
            <button class="btn-primary w-full">Guardar</button>
        </form>

        <section class="panel">
            <div class="panel-header"><h2 class="panel-title">Historial</h2></div>
            <div class="table-wrap"><table class="data-table"><thead><tr><th>Estado</th><th>Alerta</th><th>Detectada</th><th>Notificada</th></tr></thead><tbody>
                @foreach($alerts as $alert)<tr><td><span class="status-badge {{ $alert->resolved_at ? '' : 'status-error' }}">{{ $alert->resolved_at ? 'Resuelta' : 'Abierta' }}</span></td><td><strong>{{ $alert->title }}</strong><br><small>{{ $alert->message }}</small></td><td>{{ $alert->detected_at->diffForHumans() }}</td><td>{{ $alert->notified_at?->diffForHumans() ?? 'Pendiente' }}</td></tr>@endforeach
            </tbody></table></div>
        </section>
    </div>
@endsection
