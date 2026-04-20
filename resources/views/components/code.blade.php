@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('code', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'text-[length:0.875em]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-1.5 py-0.5',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<code {{ $attributes->class([$classes]) }}>{{ $slot }}</code>
