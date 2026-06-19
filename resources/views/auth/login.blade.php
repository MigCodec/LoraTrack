<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión · LoraTrack</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <script defer src="{{ asset('js/app.js') }}"></script>
</head>
<body class="login-shell min-h-screen px-5 py-10">
    <main class="mx-auto grid min-h-[calc(100vh-5rem)] max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl lg:grid-cols-2">
        <section class="brand-panel hidden p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="flex items-center gap-3">
                <span class="brand-mark">LT</span>
                <strong class="text-xl tracking-wide">LoraTrack</strong>
            </div>
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/60">Asset intelligence</p>
                <h1 class="mt-4 max-w-md text-4xl font-semibold leading-tight">Ubicación y telemetría para cada activo.</h1>
                <p class="mt-5 max-w-md text-white/70">Integra SAP, TTI y tus fuentes de inventario en una sola vista operacional.</p>
            </div>
            <p class="text-xs text-white/50">Acceso autorizado</p>
        </section>

        <section class="flex items-center p-7 sm:p-12">
            <div class="w-full">
                <p class="text-sm font-semibold text-brand-accent">Bienvenido</p>
                <h2 class="mt-2 text-3xl font-semibold text-slate-950">Iniciar sesión</h2>
                <p class="mt-2 text-sm text-slate-500">Usa tus credenciales de LoraTrack o tu cuenta Microsoft autorizada.</p>

                @if($errors->any())
                    <div class="mt-6 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ url('/login') }}" class="mt-8 space-y-5">
                    @csrf
                    <label class="field-label">Correo
                        <input class="field-input" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
                    </label>
                    <label class="field-label">Contraseña
                        <input class="field-input" type="password" name="password" required autocomplete="current-password">
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-600">
                        <input class="rounded border-slate-300" type="checkbox" name="remember" value="1"> Mantener sesión iniciada
                    </label>
                    <button class="btn-primary w-full" type="submit">Ingresar</button>
                </form>

                @if($microsoftEnabled)
                    <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-wider text-slate-400"><span class="h-px flex-1 bg-slate-200"></span>o<span class="h-px flex-1 bg-slate-200"></span></div>
                    <a class="btn-secondary w-full" href="{{ route('auth.microsoft.redirect') }}">
                        <span class="grid grid-cols-2 gap-0.5" aria-hidden="true"><i class="h-2 w-2 bg-[#f25022]"></i><i class="h-2 w-2 bg-[#7fba00]"></i><i class="h-2 w-2 bg-[#00a4ef]"></i><i class="h-2 w-2 bg-[#ffb900]"></i></span>
                        Continuar con Microsoft
                    </a>
                @endif

                <p class="mt-6 text-center text-sm text-slate-500">¿Primera vez en LoraTrack? <a class="font-semibold text-brand-primary" href="{{ route('register') }}">Crear una empresa</a></p>
            </div>
        </section>
    </main>
</body>
</html>
