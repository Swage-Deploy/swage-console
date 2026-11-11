@props([
    'heading' => null,
    'description' => null,
    'icon' => null,
    'flush' => false,
    'link' => null,
])

<section {{ $attributes->class('panel') }}>
    @if ($heading || isset($actions))
        <header class="panel-head">
            <div class="panel-title">
                @if ($icon)
                    <span class="tile-icon"><x-icon :name="$icon" /></span>
                @endif
                <div style="min-width:0">
                    @if ($link)
                        {{-- The dashboard fills in the href for whichever server is selected. --}}
                        <h2 class="h-base"><a class="ext" id="{{ $link }}" target="_blank" rel="noopener" title="Open in Swage">{{ $heading }}<x-icon name="arrow-top-right-on-square" class="sm" /></a></h2>
                    @else
                        <h2 class="h-base">{{ $heading }}</h2>
                    @endif
                    @if ($description)
                        <p>{{ $description }}</p>
                    @endif
                </div>
            </div>
            @isset($actions)
                <div class="panel-actions">{{ $actions }}</div>
            @endisset
        </header>
    @endif

    <div @class(['panel-body', 'flush' => $flush])>
        {{ $slot }}
    </div>
</section>
