@extends('layouts.app')

@section('title', 'Usuarios y grupos')
@section('heading', 'Usuarios, grupos y permisos')

@section('content')
    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        @foreach($roles as $role)
            <article class="metric-card">
                <p class="font-semibold text-slate-950">{{ $role->label() }}</p>
                <p class="mt-2 text-xs leading-5 text-slate-500">{{ $role->description() }}</p>
            </article>
        @endforeach
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <section class="panel">
            <div class="panel-header"><div><h2 class="panel-title">Cuentas</h2><p class="panel-subtitle">Cada usuario pertenece a un grupo operacional.</p></div></div>
            <div class="space-y-3 p-5">
                @foreach($users as $user)
                    <div class="rounded-xl border border-slate-200 p-4">
                        <form method="POST" action="{{ route('users.update', $user) }}" class="grid gap-3 lg:grid-cols-2">
                            @csrf @method('PUT')
                            <label class="field-label">Nombre<input class="field-input" name="name" value="{{ $user->name }}" required></label>
                            <label class="field-label">Correo<input class="field-input" type="email" name="email" value="{{ $user->email }}" required></label>
                            <label class="field-label">Grupo en esta organización<select class="field-input" name="role">@foreach($roles as $role)<option value="{{ $role->value }}" @selected($user->pivot->role === $role->value)>{{ $role->label() }}</option>@endforeach</select></label>
                            <label class="field-label">Nueva contraseña<input class="field-input" type="password" name="password" minlength="12" placeholder="Dejar vacía para conservar"></label>
                            <div class="flex gap-2 lg:col-span-2"><button class="btn-primary">Guardar cambios</button></div>
                        </form>
                        @unless(auth()->user()->is($user))
                            <form method="POST" action="{{ route('users.destroy', $user) }}" class="mt-3" onsubmit="return confirm('¿Eliminar esta cuenta?')">@csrf @method('DELETE')<button class="text-sm text-red-600">Eliminar cuenta</button></form>
                        @endunless
                    </div>
                @endforeach
            </div>
        </section>

        <form method="POST" action="{{ route('users.store') }}" class="panel h-fit space-y-4 p-5">
            @csrf
            <div><h2 class="font-semibold">Nuevo usuario</h2><p class="mt-1 text-xs text-slate-500">Contraseña mínima de 12 caracteres.</p></div>
            <label class="field-label">Nombre<input class="field-input" name="name" required></label>
            <label class="field-label">Correo<input class="field-input" type="email" name="email" required></label>
            <label class="field-label">Grupo<select class="field-input" name="role">@foreach($roles as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</select></label>
            <label class="field-label">Contraseña<input class="field-input" type="password" name="password" minlength="12" required></label>
            <button class="btn-primary w-full">Crear usuario</button>
        </form>
    </div>
@endsection
