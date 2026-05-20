@props([
    'cols' => config('wirekit.components.feature-grid.cols', '1 sm:2 lg:3'),
    'gap' => 'lg',
    // Cascade entrance animations across direct children.
    // null = no stagger (default — each child animates simultaneously)
    // true = enable with default 75ms step
    // int  = explicit ms step (e.g. 125)
    'stagger' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tailwind cannot extract runtime-concatenated class names — use literal lookup map.
    $colsMap = [
        '1' => 'grid-cols-1', '2' => 'grid-cols-2', '3' => 'grid-cols-3',
        '4' => 'grid-cols-4', '5' => 'grid-cols-5', '6' => 'grid-cols-6',
        'sm:1' => 'sm:grid-cols-1', 'sm:2' => 'sm:grid-cols-2', 'sm:3' => 'sm:grid-cols-3',
        'sm:4' => 'sm:grid-cols-4', 'sm:5' => 'sm:grid-cols-5', 'sm:6' => 'sm:grid-cols-6',
        'md:1' => 'md:grid-cols-1', 'md:2' => 'md:grid-cols-2', 'md:3' => 'md:grid-cols-3',
        'md:4' => 'md:grid-cols-4', 'md:5' => 'md:grid-cols-5', 'md:6' => 'md:grid-cols-6',
        'lg:1' => 'lg:grid-cols-1', 'lg:2' => 'lg:grid-cols-2', 'lg:3' => 'lg:grid-cols-3',
        'lg:4' => 'lg:grid-cols-4', 'lg:5' => 'lg:grid-cols-5', 'lg:6' => 'lg:grid-cols-6',
        'xl:1' => 'xl:grid-cols-1', 'xl:2' => 'xl:grid-cols-2', 'xl:3' => 'xl:grid-cols-3',
        'xl:4' => 'xl:grid-cols-4', 'xl:5' => 'xl:grid-cols-5', 'xl:6' => 'xl:grid-cols-6',
        '2xl:1' => '2xl:grid-cols-1', '2xl:2' => '2xl:grid-cols-2', '2xl:3' => '2xl:grid-cols-3',
        '2xl:4' => '2xl:grid-cols-4', '2xl:5' => '2xl:grid-cols-5', '2xl:6' => '2xl:grid-cols-6',
    ];

    $colsClasses = collect(preg_split('/\s+/', trim(is_numeric($cols) ? (string) $cols : $cols)))
        ->map(fn (string $token) => $colsMap[$token] ?? WireKit::validateProp('feature-grid', 'cols', $token, array_keys($colsMap)))
        ->implode(' ');

    $gapClasses = match ($gap) {
        'none' => '',
        'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
        'md' => 'gap-[var(--space-wk-md,1rem)]',
        'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
        default => WireKit::validateProp('feature-grid', 'gap', $gap, ['none', 'sm', 'md', 'lg', 'xl']),
    };

    // Stagger logic — see dist/wirekit.css `.wk-stagger` rules. The Blade
    // wrapper sets the per-step ms value via inline custom property; CSS
    // `:nth-child` rules walk the index up to a cap of 8 to keep long
    // grids from producing runaway delays.
    $hasStagger = $stagger !== null && $stagger !== false;
    $staggerStep = is_int($stagger) ? $stagger : 75;

    $classes = WireKit::resolveClasses('feature-grid', 'base', implode(' ', [
        'grid',
        $colsClasses,
        $gapClasses,
        $hasStagger ? 'wk-stagger' : '',
    ]), $scope);

    // Merge the auto-emitted `--wk-stagger-step` with any developer-
    // supplied `style=""` attribute so a single style attribute is
    // emitted — duplicate `style=` attributes would silently let the
    // browser drop our auto-default and undermine the developer's
    // intentional override (or vice versa). Developer style comes
    // SECOND so later-declaration-wins lets `style="--wk-stagger-step:
    // 200ms"` override our default in the canonical cascade order.
    $stylePieces = [];
    if ($hasStagger) {
        $stylePieces[] = "--wk-stagger-step: {$staggerStep}ms";
    }
    $developerStyle = trim((string) $attributes->get('style', ''));
    if ($developerStyle !== '') {
        $stylePieces[] = rtrim($developerStyle, '; ');
    }
    $mergedStyle = implode('; ', $stylePieces);
@endphp

<div
    {{ $attributes->except('style')->class([$classes]) }}
    @if($mergedStyle !== '') style="{{ $mergedStyle }}" @endif
>
    {{ $slot }}
</div>
