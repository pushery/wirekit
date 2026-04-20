@props([
    'cols' => config('wirekit.components.grid.cols', 1),
    'gap' => config('wirekit.components.grid.gap', 'md'),
    'align' => null,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tailwind cannot extract runtime-concatenated class names like "{$bp}:grid-cols-{$n}".
    // Every supported combination must appear as a literal string so the content scanner finds it.
    $colsMap = [
        '1' => 'grid-cols-1', '2' => 'grid-cols-2', '3' => 'grid-cols-3',
        '4' => 'grid-cols-4', '5' => 'grid-cols-5', '6' => 'grid-cols-6',
        '7' => 'grid-cols-7', '8' => 'grid-cols-8', '9' => 'grid-cols-9',
        '10' => 'grid-cols-10', '11' => 'grid-cols-11', '12' => 'grid-cols-12',
        'sm:1' => 'sm:grid-cols-1', 'sm:2' => 'sm:grid-cols-2', 'sm:3' => 'sm:grid-cols-3',
        'sm:4' => 'sm:grid-cols-4', 'sm:5' => 'sm:grid-cols-5', 'sm:6' => 'sm:grid-cols-6',
        'sm:7' => 'sm:grid-cols-7', 'sm:8' => 'sm:grid-cols-8', 'sm:9' => 'sm:grid-cols-9',
        'sm:10' => 'sm:grid-cols-10', 'sm:11' => 'sm:grid-cols-11', 'sm:12' => 'sm:grid-cols-12',
        'md:1' => 'md:grid-cols-1', 'md:2' => 'md:grid-cols-2', 'md:3' => 'md:grid-cols-3',
        'md:4' => 'md:grid-cols-4', 'md:5' => 'md:grid-cols-5', 'md:6' => 'md:grid-cols-6',
        'md:7' => 'md:grid-cols-7', 'md:8' => 'md:grid-cols-8', 'md:9' => 'md:grid-cols-9',
        'md:10' => 'md:grid-cols-10', 'md:11' => 'md:grid-cols-11', 'md:12' => 'md:grid-cols-12',
        'lg:1' => 'lg:grid-cols-1', 'lg:2' => 'lg:grid-cols-2', 'lg:3' => 'lg:grid-cols-3',
        'lg:4' => 'lg:grid-cols-4', 'lg:5' => 'lg:grid-cols-5', 'lg:6' => 'lg:grid-cols-6',
        'lg:7' => 'lg:grid-cols-7', 'lg:8' => 'lg:grid-cols-8', 'lg:9' => 'lg:grid-cols-9',
        'lg:10' => 'lg:grid-cols-10', 'lg:11' => 'lg:grid-cols-11', 'lg:12' => 'lg:grid-cols-12',
        'xl:1' => 'xl:grid-cols-1', 'xl:2' => 'xl:grid-cols-2', 'xl:3' => 'xl:grid-cols-3',
        'xl:4' => 'xl:grid-cols-4', 'xl:5' => 'xl:grid-cols-5', 'xl:6' => 'xl:grid-cols-6',
        'xl:7' => 'xl:grid-cols-7', 'xl:8' => 'xl:grid-cols-8', 'xl:9' => 'xl:grid-cols-9',
        'xl:10' => 'xl:grid-cols-10', 'xl:11' => 'xl:grid-cols-11', 'xl:12' => 'xl:grid-cols-12',
        '2xl:1' => '2xl:grid-cols-1', '2xl:2' => '2xl:grid-cols-2', '2xl:3' => '2xl:grid-cols-3',
        '2xl:4' => '2xl:grid-cols-4', '2xl:5' => '2xl:grid-cols-5', '2xl:6' => '2xl:grid-cols-6',
        '2xl:7' => '2xl:grid-cols-7', '2xl:8' => '2xl:grid-cols-8', '2xl:9' => '2xl:grid-cols-9',
        '2xl:10' => '2xl:grid-cols-10', '2xl:11' => '2xl:grid-cols-11', '2xl:12' => '2xl:grid-cols-12',
    ];

    $colsClasses = collect(preg_split('/\s+/', trim(is_numeric($cols) ? (string) $cols : $cols)))
        ->map(fn (string $token) => $colsMap[$token] ?? WireKit::validateProp('grid', 'cols', $token, array_keys($colsMap)))
        ->implode(' ');

    $gapClasses = match ($gap) {
        'none' => '',
        'xs' => 'gap-[var(--space-wk-xs,0.25rem)]',
        'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
        'md' => 'gap-[var(--space-wk-md,1rem)]',
        'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'gap-[var(--space-wk-2xl,4rem)]',
        default => WireKit::validateProp('grid', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
    };

    $alignClasses = match ($align) {
        'start' => 'items-start',
        'center' => 'items-center',
        'end' => 'items-end',
        'stretch' => 'items-stretch',
        null => '',
        default => WireKit::validateProp('grid', 'align', $align, ['start', 'center', 'end', 'stretch']),
    };

    $classes = WireKit::resolveClasses('grid', 'base', implode(' ', array_filter([
        'grid',
        $colsClasses,
        $gapClasses,
        $alignClasses,
    ])), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
