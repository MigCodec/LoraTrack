<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Crear empresa · LoraTrack</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</head>
<body class="login-shell min-h-screen px-5 py-10">
    <main class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-2">
        <section class="brand-panel hidden p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3"><span class="brand-mark">LT</span><strong class="text-xl tracking-wide">LoraTrack</strong></div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/60">Nuevo espacio de trabajo</p>
                <h1 class="mt-4 max-w-md text-4xl font-semibold leading-tight">Crea tu empresa y mantén sus datos aislados.</h1>
                <p class="mt-5 max-w-md text-white/70">Después podrás personalizar el logo, los colores, usuarios, conectores y dispositivos.</p>
            </div>
            <p class="text-xs text-white/50">Registro autoservicio</p>
        </section>

        <section class="flex items-center p-7 sm:p-12">
            <div class="w-full">
                <p class="text-sm font-semibold text-brand-accent">Comenzar</p>
                <h2 class="mt-2 text-3xl font-semibold text-slate-950">Crear empresa</h2>
                <p class="mt-2 text-sm text-slate-500">Tu correo será el administrador inicial.</p>

                <form method="POST" action="{{ route('registration.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <input class="hidden" type="text" name="website" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
                    <label class="field-label">Empresa o proyecto
                        <input class="field-input" name="organization_name" value="{{ old('organization_name') }}" required autofocus maxlength="255" autocomplete="organization">
                    </label>
                    <label class="field-label">Correo del administrador
                        <input class="field-input" type="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                    </label>
                    <label class="field-label">Contraseña
                        <input class="field-input" type="password" name="password" required minlength="12" autocomplete="new-password">
                        <span class="mt-1 text-xs font-normal text-slate-500">Mínimo 12 caracteres.</span>
                    </label>
                    <label class="field-label">Confirmar contraseña
                        <input class="field-input" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
                    </label>
                    <button class="btn-primary w-full" type="submit">Crear mi empresa</button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-500">¿Ya tienes una cuenta? <a class="font-semibold text-brand-primary" href="{{ route('login') }}">Iniciar sesión</a></p>
            </div>
        </section>
    </main>
    @include('partials.toasts')
</body>
</html>
