<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contraseña · LoraTrack</title>
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
                <h1 class="mt-4 max-w-md text-4xl font-semibold leading-tight">Recupera el acceso a tu cuenta.</h1>
                <p class="mt-5 max-w-md text-white/70">Te enviaremos un enlace temporal al correo registrado.</p>
            </div>
            <p class="text-xs text-white/50">El enlace vence en 60 minutos</p>
        </section>
        <section class="flex items-center p-7 sm:p-12">
            <div class="w-full">
                <p class="text-sm font-semibold text-brand-accent">Recuperación</p>
                <h2 class="mt-2 text-3xl font-semibold text-slate-950">Olvidé mi contraseña</h2>
                <p class="mt-2 text-sm text-slate-500">Ingresa tu correo y, si está registrado, recibirás las instrucciones.</p>
                <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-5">
                    @csrf
                    <label class="field-label">Correo
                        <input class="field-input" type="email" name="email" value="{{ old('email') }}" required autofocus maxlength="255" autocomplete="email">
                    </label>
                    <button class="btn-primary w-full" type="submit">Enviar enlace temporal</button>
                </form>
                <p class="mt-6 text-center text-sm text-slate-500"><a class="font-semibold text-brand-primary" href="{{ route('login') }}">Volver a iniciar sesión</a></p>
            </div>
        </section>
    </main>
    @include('partials.toasts')
</body>
</html>
