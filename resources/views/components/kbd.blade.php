@props([
    'size' => 'md',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $sizeClasses = match ($size) {
        'sm' => 'text-[length:var(--text-wk-xs,0.75rem)] px-1 py-0.5 min-w-5',
        'md' => 'text-[length:var(--text-wk-sm)] px-1.5 py-0.5 min-w-6',
        'lg' => 'text-[length:var(--text-wk-md)] px-2 py-1 min-w-7',
        default => WireKit::validateProp('kbd', 'size', $size, ['sm', 'md', 'lg']),
    };

    $classes = WireKit::resolveClasses('kbd', 'base', implode(' ', [
        'inline-flex items-center justify-center',
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'font-normal',
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'border-b-2',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'leading-none',
        $sizeClasses,
    ]), $scope);
@endphp

<kbd {{ $attributes->class([$classes]) }}>{{ $slot }}</kbd>