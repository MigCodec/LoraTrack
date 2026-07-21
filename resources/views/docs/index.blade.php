<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentation · LoraTrack</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="login-shell min-h-screen px-5 py-10">
    <main class="mx-auto max-w-5xl overflow-hidden rounded-3xl bg-white shadow-2xl">
        <header class="brand-panel p-7 text-white sm:p-12">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="brand-mark">LT</span>
                    <strong class="text-xl tracking-wide">LoraTrack</strong>
                </div>
                @auth
                    <a class="btn-secondary" href="{{ route('dashboard') }}">Open dashboard</a>
                @else
                    <a class="btn-secondary" href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
            <div class="mt-8">
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-white/60">Product resources</p>
                <h1 class="mt-4 max-w-4xl text-4xl font-semibold leading-tight">LoraTrack Documentation</h1>
                <p class="mt-5 max-w-4xl text-white/75">Public technical, user, deployment, and operations documentation for customers, administrators, engineering teams, and platform operators.</p>
            </div>
        </header>

        <section class="p-7 sm:p-12" aria-labelledby="available-documents">
            <h2 id="available-documents" class="text-2xl font-semibold text-slate-950">Available documents</h2>
            <p class="mt-2 text-sm text-slate-500">PDF publications use stable filenames and are updated with the product documentation.</p>

            <div class="mt-8 grid gap-5 md:grid-cols-2">
                @foreach($documents as $document)
                    <article class="rounded-lg border border-slate-200 bg-slate-50 p-6">
                        <p class="text-xs font-semibold uppercase tracking-wider text-brand-accent">PDF · {{ $document['size'] ?? 'Unavailable' }}</p>
                        <h3 class="mt-3 text-xl font-semibold text-slate-950">{{ $document['title'] }}</h3>
                        <p class="mt-3 text-sm leading-7 text-slate-600">{{ $document['description'] }}</p>
                        <div class="mt-6">
                            @if($document['available'])
                                <a class="btn-primary" href="{{ route('docs.download', $document['key']) }}">Download PDF</a>
                            @else
                                <span class="text-sm font-semibold text-red-700" role="status">Document temporarily unavailable</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </main>
</body>
</html>
