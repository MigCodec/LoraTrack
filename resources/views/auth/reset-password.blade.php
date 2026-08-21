<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer contraseña · LoraTrack</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/tenant-brand.css') }}?v={{ filemtime(public_path('css/tenant-brand.css')) }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
</head>
<body class="login-shell min-h-screen px-5 py-10">
    <main class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-2">
        <section class="brand-panel hidden p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3"><span class="brand-mark">LT</span><strong class="text-xl tracking-wide">LoraTrack</strong></div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/60">Acceso seguro</p>
                <h1 class="mt-4 max-w-md text-4xl font-semibold leading-tight">Define una nueva contraseña.</h1>
                <p class="mt-5 max-w-md text-white/70">El enlace es de un solo uso y deja de ser válido después del cambio.</p>
            </div>
            <p class="text-xs text-white/50">Protección de cuenta</p>
        </section>
        <section class="flex items-center p-7 sm:p-12">
            <div class="w-full">
                <p class="text-sm font-semibold text-brand-accent">Recuperación</p>
                <h2 class="mt-2 text-3xl font-semibold text-slate-950">Restablecer contraseña</h2>
                <p class="mt-2 text-sm text-slate-500">Usa al menos 12 caracteres para proteger tu cuenta.</p>
                <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">
                    <label class="field-label">Correo
                        <input class="field-input" type="email" name="email" value="{{ old('email', $email) }}" required readonly autocomplete="email">
                    </label>
                    <label class="field-label">Nueva contraseña
                        <input class="field-input" type="password" name="password" required minlength="12" autofocus autocomplete="new-password">
                    </label>
                    <label class="field-label">Confirmar nueva contraseña
                        <input class="field-input" type="password" name="password_confirmation" required minlength="12" autocomplete="new-password">
                    </label>
                    <button class="btn-primary w-full" type="submit">Guardar nueva contraseña</button>
                </form>
                <p class="mt-6 text-center text-sm text-slate-500"><a class="font-semibold text-brand-primary" href="{{ route('password.request') }}">Solicitar otro enlace</a></p>
            </div>
        </section>
    </main>
    @include('partials.toasts')
</body>
</html>
