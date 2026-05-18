@props([
    'size' => 'base',
    'variant' => 'default',
    'weight' => 'normal',
    'align' => null,
    'truncate' => false,
    'lineClamp' => null,
    'as' => 'p',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $sizeClasses = match ($size) {
        'xs' => 'text-[length:var(--text-wk-xs,0.75rem)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        default => WireKit::validateProp('text', 'size', $size, ['xs', 'sm', 'base', 'lg', 'xl']),
    };

    $variantClasses = match ($variant) {
        'default' => 'text-[color:var(--color-wk-text)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'accent' => 'text-[color:var(--color-wk-accent)]',
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'warning' => 'text-[color:var(--color-wk-warning-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
        default => WireKit::validateProp('text', 'variant', $variant, ['default', 'muted', 'subtle', 'accent', 'success', 'warning', 'danger']),
    };

    $weightClasses = match ($weight) {
        'normal' => 'font-normal',
        'medium' => 'font-medium',
        'semibold' => 'font-semibold',
        'bold' => 'font-bold',
        default => WireKit::validateProp('text', 'weight', $weight, ['normal', 'medium', 'semibold', 'bold']),
    };

    $alignClasses = match ($align) {
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
        null => '',
        default => WireKit::validateProp('text', 'align', $align, ['left', 'center', 'right']),
    };

    $truncateClasses = $truncate ? 'truncate' : '';

    $lineClampClasses = $lineClamp ? "line-clamp-{$lineClamp}" : '';

    $classes = WireKit::resolveClasses('text', 'base', implode(' ', array_filter([
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'leading-[var(--font-wk-line-height,1.5)]',
        $sizeClasses,
        $variantClasses,
        $weightClasses,
        $alignClasses,
        $truncateClasses,
        $lineClampClasses,
    ])), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
