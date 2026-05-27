@props([
    'level' => null,
    'size' => null,
    'accent' => false,
    'tracking' => 'normal',
    'as' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Determine the HTML heading level (h1–h6)
    $headingLevel = $level ?? 2;
    $tag = $as ?? "h{$headingLevel}";

    // Auto-size based on heading level when size is not explicitly set
    $resolvedSize = $size ?? match ((int) $headingLevel) {
        1 => '2xl',
        2 => 'xl',
        3 => 'lg',
        4 => 'base',
        5, 6 => 'sm',
        default => 'xl',
    };

    // extend the heading size scale.
    //   - md accepted as an alias for base (rest of the kit uses md as
    //     the canonical middle tier — heading was the outlier).
    //   - 4xl and 5xl added so hero copy can use the standard hero
    //     scale designers copy from external typography systems.
    $sizeClasses = match ($resolvedSize) {
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md', 'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        '2xl' => 'text-[length:var(--text-wk-2xl,1.5rem)]',
        '3xl' => 'text-[length:var(--text-wk-3xl,1.875rem)]',
        '4xl' => 'text-[length:var(--text-wk-4xl,2.25rem)]',
        '5xl' => 'text-[length:var(--text-wk-5xl,3rem)]',
        default => WireKit::validateProp('heading', 'size', $resolvedSize, ['sm', 'md', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']),
    };

    $trackingClasses = match ($tracking) {
        'normal' => 'tracking-normal',
        'tight' => 'tracking-tight',
        'tighter' => 'tracking-tighter',
        default => WireKit::validateProp('heading', 'tracking', $tracking, ['normal', 'tight', 'tighter']),
    };

    $colorClasses = $accent
        ? 'text-[color:var(--color-wk-accent)]'
        : 'text-[color:var(--color-wk-text)]';

    $classes = WireKit::resolveClasses('heading', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'leading-[var(--font-wk-heading-line-height,1.25)]',
        $sizeClasses,
        $trackingClasses,
        $colorClasses,
    ]), $scope);
@endphp

<{{ $tag }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $tag }}>
