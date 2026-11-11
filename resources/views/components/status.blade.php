@props([
    'color' => 'zinc',
    'label' => null,
])

<span {{ $attributes->class(['status', 'c-'.$color]) }}>
    <span class="dot"></span>
    @if ($label)
        <span>{{ $label }}</span>
    @endif
</span>
