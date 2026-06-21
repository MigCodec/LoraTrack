@php
    $selectedRoles = (array) old('recipient_roles', $rule?->recipient_roles ?? []);
    $selectedUsers = array_map('strval', (array) old('recipient_user_ids', $rule?->recipient_user_ids ?? []));
    $actions = (array) old('actions', $rule?->actions ?? ['create_alert']);
    $trigger = old('trigger_type', $rule?->trigger_type ?? 'zone_entry');
@endphp
<div class="alert-rule-grid" data-rule-builder>
    <label class="field-label alert-rule-wide">Nombre de la regla<input class="field-input" name="name" value="{{ old('name', $rule?->name) }}" placeholder="Salida de activos críticos" required></label>
    <label class="field-label">Aplicar a<select class="field-input" name="subject_type" data-rule-subject><option value="all_assets" @selected(old('subject_type', $rule?->subject_type ?? 'all_assets') === 'all_assets')>Todos los trackers/activos</option><option value="asset" @selected(old('subject_type', $rule?->subject_type) === 'asset')>Un tracker/activo</option></select></label>
    <label class="field-label" data-rule-subject-value>Tracker o activo<select class="field-input" name="subject_id"><option value="">Seleccionar…</option>@foreach($assets as $asset)<option value="{{ $asset->id }}" @selected(old('subject_id', $rule?->subject_id) === $asset->id)>{{ $asset->name }} · {{ $asset->asset_tag }}</option>@endforeach</select></label>
    <label class="field-label">Cuando<select class="field-input" name="trigger_type" data-rule-trigger>@foreach(['zone_entry'=>'entra en','zone_exit'=>'deja de estar en','zone_inside'=>'permanece en','zone_outside'=>'permanece fuera de','speed_above'=>'su velocidad supera','speed_below'=>'su velocidad es menor que'] as $value => $label)<option value="{{ $value }}" @selected($trigger === $value)>{{ $label }}</option>@endforeach</select></label>
    <label class="field-label" data-rule-zone>Zona<select class="field-input" name="zone_id"><option value="">Seleccionar…</option>@foreach($zones as $zone)<option value="{{ $zone->id }}" @selected(old('zone_id', $rule?->zone_id) === $zone->id)>{{ $zone->name }} · {{ $zone->floorPlan?->name }}</option>@endforeach</select></label>
    <label class="field-label" data-rule-threshold>Velocidad (km/h)<input class="field-input" type="number" name="threshold" min="0" max="1000" step="0.1" value="{{ old('threshold', $rule?->threshold) }}"></label>
    <label class="field-label" data-rule-duration>Durante (minutos)<input class="field-input" type="number" name="duration_minutes" min="1" max="10080" value="{{ old('duration_minutes', $rule?->duration_minutes ?? 5) }}"></label>
    <label class="field-label">No repetir durante<input class="field-input" type="number" name="cooldown_minutes" min="1" max="10080" value="{{ old('cooldown_minutes', $rule?->cooldown_minutes ?? 5) }}" required></label>
</div>
<fieldset class="alert-rule-fieldset"><legend>Entonces</legend><label><input type="checkbox" name="actions[]" value="create_alert" @checked(in_array('create_alert', $actions, true))> Registrar alerta en la plataforma</label><label><input type="checkbox" name="actions[]" value="send_email" @checked(in_array('send_email', $actions, true))> Enviar aviso por correo</label></fieldset>
<div class="grid gap-4 lg:grid-cols-2">
    <fieldset class="alert-rule-fieldset"><legend>Grupos destinatarios</legend>@foreach($roles as $role)<label><input type="checkbox" name="recipient_roles[]" value="{{ $role->value }}" @checked(in_array($role->value, $selectedRoles, true))> {{ $role->label() }}</label>@endforeach</fieldset>
    <fieldset class="alert-rule-fieldset"><legend>Usuarios destinatarios</legend><div class="alert-rule-users">@forelse($recipientMemberships as $membership)<label><input type="checkbox" name="recipient_user_ids[]" value="{{ $membership->user_id }}" @checked(in_array((string) $membership->user_id, $selectedUsers, true))><span><strong>{{ $membership->user->name }}</strong><small>{{ $membership->user->email }}</small></span></label>@empty<p>No hay usuarios activos.</p>@endforelse</div></fieldset>
</div>
<label class="flex gap-2"><input type="checkbox" name="enabled" value="1" @checked(old('enabled', $rule?->enabled ?? true))> Regla activa</label>
