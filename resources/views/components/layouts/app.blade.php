@props([
    'title' => null,
    'current' => null,
])
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head', ['title' => $title])
    {{ $head ?? '' }}
</head>
<body>
<aside class="sidebar" id="sidebar">
    <a class="brand" href="{{ route('dashboard') }}" aria-label="Swage Console">
        <x-logo />
    </a>

    <nav class="nav">
        <div class="nav-heading">Console</div>
        <a @class(['nav-item', 'current' => $current === 'dashboard']) href="{{ route('dashboard') }}">
            <x-icon name="server-stack" /> Servers
        </a>
        <a @class(['nav-item', 'current' => $current === 'panels']) href="{{ route('panels.index') }}">
            <x-icon name="cog-6-tooth" /> Swage panels
        </a>
        <a @class(['nav-item', 'current' => $current === 'security']) href="{{ route('security') }}">
            <x-icon name="shield-check" /> Security
        </a>
    </nav>

    @isset($sidebar)
        {{ $sidebar }}
    @else
        <div style="flex:1"></div>
    @endisset

    @auth
        <div class="user-menu">
            <span class="avatar" aria-hidden="true">{{ str(auth()->user()->name)->explode(' ')->map(fn (string $word): string => mb_substr($word, 0, 1))->take(2)->implode('') }}</span>
            <div style="min-width:0;flex:1">
                <div class="truncate" style="color:#fff;font-weight:500">{{ auth()->user()->name }}</div>
                <div class="truncate faint" style="font-size:12px">{{ auth()->user()->email }}</div>
            </div>
            <form method="post" action="{{ route('logout') }}" style="margin:0">
                @csrf
                <button class="btn ghost sm" title="Sign out" aria-label="Sign out"><x-icon name="arrow-right-start-on-rectangle" /></button>
            </form>
        </div>
    @endauth
</aside>
<div class="scrim" data-nav-close></div>

<div class="main-col" style="flex:1;min-width:0">
    <header class="mobilebar">
        <button class="btn ghost sm" type="button" data-nav-toggle aria-label="Open navigation"><x-icon name="bars-2" /></button>
        <a class="brand" href="{{ route('dashboard') }}" style="padding:0" aria-label="Swage Console"><x-logo /></a>
    </header>
    <main class="main">
        <div class="container">
            {{ $slot }}
        </div>
    </main>
</div>

<script>
    document.addEventListener('click', e => {
        if (e.target.closest('[data-nav-toggle]')) document.body.classList.toggle('nav-open');
        else if (e.target.closest('[data-nav-close]')) document.body.classList.remove('nav-open');
    });
</script>
{{ $scripts ?? '' }}
</body>
</html>
