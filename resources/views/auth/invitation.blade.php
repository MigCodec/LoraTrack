<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitacion &middot; LoraTrack</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    <script defer src="{{ asset('js/app.js') }}?v={{ filemtime(public_path('js/app.js')) }}"></script>
</head>
<body class="min-h-screen bg-slate-50 p-6">
    <main class="mx-auto mt-10 max-w-lg panel p-7">
        <h1 class="text-2xl font-semibold">Administrar {{ $invitation->organization->name }}</h1>
        <p class="mt-2 text-sm text-slate-500">Completa tu cuenta para ingresar a LoraTrack.</p>
        <form method="POST" action="{{ route('invitations.store', $token) }}" class="mt-6 space-y-4">
            @csrf
            <label class="field-label">Nombre<input class="field-input" name="name" required></label>
            <label class="field-label">Contrasena<input class="field-input" type="password" name="password" minlength="12" required></label>
            <label class="field-label">Confirmar contrasena<input class="field-input" type="password" name="password_confirmation" minlength="12" required></label>
            <button class="btn-primary w-full">Aceptar invitacion</button>
        </form>
    </main>
    @include('partials.toasts')
</body>
</html>
