@extends('layouts.app')

@section('title', 'Usuarios y grupos')
@section('heading', 'Usuarios, grupos y permisos')

@section('content')
    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5" aria-label="Grupos operacionales de la empresa">
        @foreach($roles as $role)
            <article class="metric-card">
                <div class="flex items-start justify-between gap-3"><p class="font-semibold text-slate-950">{{ $role->label() }}</p><span class="status-badge status-active">{{ $groupStats[$role->value]['active'] }} activos</span></div>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $role->description() }}</p>
                @if($groupStats[$role->value]['total'] !== $groupStats[$role->value]['active'])<p class="mt-2 text-xs text-amber-700">{{ $groupStats[$role->value]['total'] - $groupStats[$role->value]['active'] }} con acceso vencido</p>@endif
            </article>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_21rem]">
        <section class="panel min-w-0">
            <div class="panel-header flex-wrap gap-3"><div><h2 class="panel-title">Usuarios de la empresa</h2><p class="panel-subtitle">Asigna grupos y controla la vigencia de acceso sin modificar la identidad global de la cuenta.</p></div><span class="status-badge status-active">{{ $users->count() }} cuentas</span></div>

            @if($invitations->isNotEmpty())
                <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Invitaciones pendientes</p>
                    <div class="mt-3 grid gap-2 sm:grid-cols-2">
                        @foreach($invitations as $invitation)
                            <div class="rounded-lg border border-slate-200 bg-white px-3 py-2">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $invitation->email }}</p>
                                <p class="mt-1 text-xs text-slate-500">{{ $invitation->role->label() }} · {{ $invitation->expires_at->isPast() ? 'enlace vencido' : 'invitación vence '.$invitation->expires_at->diffForHumans() }}</p>
                                <p class="mt-1 text-xs text-slate-400">Acceso: {{ $invitation->membership_expires_at ? 'hasta '.$invitation->membership_expires_at->format('d/m/Y') : 'permanente' }}</p>
                                <div class="mt-2 flex gap-3">
                                    <form method="POST" action="{{ route('user-invitations.resend', $invitation) }}">
                                        @csrf
                                        <button class="text-xs font-semibold text-blue-600" type="submit">Reenviar invitación</button>
                                    </form>
                                    <form method="POST" action="{{ route('user-invitations.destroy', $invitation) }}" onsubmit="return confirm('¿Eliminar esta invitación? El enlace dejará de funcionar.')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="text-xs font-semibold text-red-600" type="submit">Eliminar invitación</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if($users->isNotEmpty())
                <form id="bulk-membership-form" method="POST" action="{{ route('users.memberships.bulk-update') }}" class="grid gap-3 border-b border-slate-200 bg-slate-50 px-5 py-4 md:grid-cols-[minmax(10rem,1fr)_minmax(10rem,1fr)_minmax(11rem,1fr)_auto]" data-access-form>
                    @csrf @method('PATCH')
                    <label class="field-label">Mover seleccionados a<select class="field-input" name="role" required>@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
                    <label class="field-label">Vigencia<select class="field-input" name="access_type" data-access-type><option value="permanent">Permanente</option><option value="until">Hasta una fecha</option></select></label>
                    <label class="field-label" data-expiration-field hidden>Fecha de vencimiento<input class="field-input" type="date" name="expires_at" min="{{ now()->toDateString() }}" data-expiration-date disabled></label>
                    <button class="btn-primary self-end" type="submit">Aplicar</button>
                </form>

                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th class="w-10"><input type="checkbox" data-select-all-users aria-label="Seleccionar todos"></th><th>Usuario</th><th>Grupo</th><th>Vigencia</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
                        <tbody>
                        @foreach($users as $user)
                            @php($membershipExpiration = $user->pivot->expires_at ? Illuminate\Support\Carbon::parse($user->pivot->expires_at) : null)
                            @php($membershipExpired = $membershipExpiration?->isPast() ?? false)
                            <tr>
                                <td><input form="bulk-membership-form" type="checkbox" name="user_ids[]" value="{{ $user->id }}" data-user-selection aria-label="Seleccionar {{ $user->name }}"></td>
                                <td><strong>{{ $user->name }}</strong><br><span class="text-xs text-slate-500">{{ $user->email }}</span></td>
                                <td><span class="status-badge status-active">{{ App\Enums\UserRole::from($user->pivot->role)->label() }}</span></td>
                                <td>{{ $membershipExpiration ? $membershipExpiration->format('d/m/Y') : 'Permanente' }}</td>
                                <td><span class="status-badge status-{{ $membershipExpired ? 'error' : 'active' }}">{{ $membershipExpired ? 'Vencido' : 'Activo' }}</span></td>
                                <td class="text-right">
                                    <details class="inline-block text-left">
                                        <summary class="btn-secondary cursor-pointer list-none">Administrar</summary>
                                        <form method="POST" action="{{ route('users.update', $user) }}" class="mt-3 grid min-w-[17rem] gap-3 rounded-lg border border-slate-200 bg-white p-3 shadow-lg" data-access-form>
                                            @csrf @method('PUT')
                                            <label class="field-label">Grupo<select class="field-input" name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected($user->pivot->role === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
                                            <label class="field-label">Vigencia<select class="field-input" name="access_type" data-access-type><option value="permanent" @selected(!$membershipExpiration)>Permanente</option><option value="until" @selected($membershipExpiration)>Hasta una fecha</option></select></label>
                                            <label class="field-label" data-expiration-field @hidden(!$membershipExpiration)>Fecha de vencimiento<input class="field-input" type="date" name="expires_at" min="{{ now()->toDateString() }}" value="{{ $membershipExpiration?->toDateString() }}" data-expiration-date @disabled(!$membershipExpiration)></label>
                                            <button class="btn-primary" type="submit">Guardar asignación</button>
                                        </form>
                                        @unless(auth()->user()->is($user))<form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-2" onsubmit="return confirm('¿Quitar el acceso de este usuario a la empresa?')">@csrf @method('DELETE')<button class="text-sm text-red-600">Quitar de la empresa</button></form>@endunless
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-state">No hay cuentas activadas en esta empresa.</div>
            @endif
        </section>

        <aside class="space-y-6">
            <form method="POST" action="{{ route('user-invitations.store') }}" class="panel h-fit space-y-4 p-5" data-access-form>
                @csrf
                <div><h2 class="font-semibold">Invitar usuario</h2><p class="mt-1 text-xs leading-5 text-slate-500">El correo usará la identidad de {{ app(App\Tenancy\OrganizationContext::class)->organization()->name }}.</p></div>
                <label class="field-label">Correo del invitado<input class="field-input" type="email" name="email" value="{{ old('email') }}" required autocomplete="off"></label>
                <label class="field-label">Grupo<select class="field-input" name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected(old('role', 'viewer') === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
                <label class="field-label">Vigencia<select class="field-input" name="access_type" data-access-type><option value="permanent">Permanente</option><option value="until">Hasta una fecha</option></select></label>
                <label class="field-label" data-expiration-field hidden>Fecha de vencimiento<input class="field-input" type="date" name="membership_expires_at" min="{{ now()->toDateString() }}" data-expiration-date disabled></label>
                <button class="btn-primary w-full">Enviar invitación</button>
                <p class="text-xs leading-5 text-slate-400">El enlace de registro vence en 7 días; la vigencia de la membresía se administra por separado.</p>
            </form>

            <details class="panel h-fit p-5">
                <summary class="cursor-pointer text-sm font-semibold text-slate-700">Crear una cuenta manualmente</summary>
                <form method="POST" action="{{ route('users.store') }}" class="mt-4 space-y-4" data-access-form>
                    @csrf
                    <label class="field-label">Nombre<input class="field-input" name="name" required></label>
                    <label class="field-label">Correo<input class="field-input" type="email" name="email" required></label>
                    <label class="field-label">Grupo<select class="field-input" name="role">@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
                    <label class="field-label">Vigencia<select class="field-input" name="access_type" data-access-type><option value="permanent">Permanente</option><option value="until">Hasta una fecha</option></select></label>
                    <label class="field-label" data-expiration-field hidden>Fecha de vencimiento<input class="field-input" type="date" name="expires_at" min="{{ now()->toDateString() }}" data-expiration-date disabled></label>
                    <label class="field-label">Contraseña temporal<input class="field-input" type="password" name="password" minlength="12" required></label>
                    <button class="btn-secondary w-full">Crear usuario</button>
                </form>
            </details>
        </aside>
    </div>
@endsection
