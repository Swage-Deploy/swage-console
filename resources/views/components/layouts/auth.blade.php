@props([
    'title' => null,
    'heading',
    'description' => null,
    'passkeys' => false,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head', ['title' => $title ?? $heading])
    @if ($passkeys)
        @vite('resources/js/passkeys.js')
    @endif
</head>
<body>
<main class="auth">
    <div class="forge-grid"></div>
    <div class="ember-glow"></div>

    <div class="auth-inner">
        <div class="auth-brand"><x-logo /><span>Swage Console</span></div>

        <div class="auth-card">
            <div class="auth-head">
                <h1 class="h-lg">{{ $heading }}</h1>
                @if ($description)
                    <p>{{ $description }}</p>
                @endif
            </div>

            @if (session('status'))
                <div class="callout green"><x-icon name="check-circle" /><div>{{ session('status') }}</div></div>
            @endif

            {{ $slot }}
        </div>

        @isset($footer)
            <div class="auth-foot">{{ $footer }}</div>
        @endisset
    </div>
</main>
</body>
</html>
