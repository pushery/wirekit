@props([
    'type' => 'disc',
    'spacing' => 'sm',
    'as' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Ordered types render as <ol>; everything else (incl. 'disc' / 'none') uses <ul>.
    $orderedTypes = ['decimal', 'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha'];
    $tag = $as ?? (in_array($type, $orderedTypes, true) ? 'ol' : 'ul');

    $typeClasses = match ($type) {
        'disc' => 'list-disc',
        'decimal' => 'list-decimal',
        'none' => 'list-none',
        // Roman / alpha types use Tailwind v4's arbitrary-value syntax; the
        // CSS list-style-type property has supported these values since IE6.
        'lower-roman' => 'list-[lower-roman]',
        'upper-roman' => 'list-[upper-roman]',
        'lower-alpha' => 'list-[lower-alpha]',
        'upper-alpha' => 'list-[upper-alpha]',
        default => WireKit::validateProp('list', 'type', $type, [
            'disc', 'decimal', 'none',
            'lower-roman', 'upper-roman', 'lower-alpha', 'upper-alpha',
        ]),
    };

    $spacingClasses = match ($spacing) {
        'none' => 'space-y-0',
        'sm' => 'space-y-1',
        'md' => 'space-y-2',
        default => WireKit::validateProp('list', 'spacing', $spacing, ['none', 'sm', 'md']),
    };

    $classes = WireKit::resolveClasses('list', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'text-[var(--color-wk-text)]',
        'text-[length:var(--text-wk-md)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        $type !== 'none' ? 'pl-[var(--space-wk-lg,1.5rem)]' : '',
        $typeClasses,
        $spacingClasses,
    ]), $scope);
@endphp

<{{ $tag }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $tag }}>
