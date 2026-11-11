@props(['name'])
<svg {{ $attributes->class('icon') }} viewBox="0 0 20 20" aria-hidden="true">{!! app('icons')[$name] ?? '' !!}</svg>
