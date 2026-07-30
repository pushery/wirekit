{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('code', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'text-[length:0.875em]',
        'bg-[var(--color-wk-code-bg)]',
        'text-[color:var(--color-wk-code)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-1.5 py-0.5',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<code {{ $attributes->class([$classes]) }}>{{ $slot }}</code>