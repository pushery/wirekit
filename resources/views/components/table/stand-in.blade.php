{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // The table width below which the column this stands in for gives way: the same value as
    // that column's `hideBelowContainer`. sm … 5xl.
    'belowContainer' => null,
    // The window width from which it does, the same value as the column's
    // `hideBelowContainerFrom`: sm | md | lg | xl | 2xl.
    'from' => null,
    // For a column that gives way to the window instead, the same value as its `hideBelow`:
    // sm | md | lg | xl | 2xl.
    'below' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('table.stand-in', $attributes->getAttributes());

    $belowContainer = filled($belowContainer) ? (string) $belowContainer : null;
    if ($belowContainer !== null && ! in_array($belowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl'], true)) {
        WireKit::validateProp('table.stand-in', 'belowContainer', $belowContainer, ['sm', 'md', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']);
        $belowContainer = null;
    }

    $from = filled($from) ? (string) $from : null;
    if ($from !== null && ! in_array($from, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.stand-in', 'from', $from, ['sm', 'md', 'lg', 'xl', '2xl']);
        $from = null;
    }

    $below = filled($below) ? (string) $below : null;
    if ($below !== null && ! in_array($below, ['sm', 'md', 'lg', 'xl', '2xl'], true)) {
        WireKit::validateProp('table.stand-in', 'below', $below, ['sm', 'md', 'lg', 'xl', '2xl']);
        $below = null;
    }

    // `from` gates the container question, so it needs one. And a column that gives way to the
    // window or the table, whichever is narrower, is shown again only where both are wide, which
    // no pair of classes that each hide on their own can say: a stand-in takes one of the two.
    if ($from !== null && $belowContainer === null) {
        StrictnessGate::reject('table.stand-in', 'from', $from, 'a stand-in with belowContainer', '');
        $from = null;
    }

    if ($below !== null && $belowContainer !== null) {
        StrictnessGate::reject('table.stand-in', 'below', $below, 'a stand-in without belowContainer', '');
        $below = null;
    }

    // Shown exactly where the column is hidden, so its value is on screen once at every width:
    // each condition below hides the stand-in where the column shows. Written out per size,
    // because Tailwind reads a literal class name and never an assembled one.
    $classes = implode(' ', array_filter([
        match ($belowContainer) {
            'sm' => '@sm/wk-table:hidden',
            'md' => '@md/wk-table:hidden',
            'lg' => '@lg/wk-table:hidden',
            'xl' => '@xl/wk-table:hidden',
            '2xl' => '@2xl/wk-table:hidden',
            '3xl' => '@3xl/wk-table:hidden',
            '4xl' => '@4xl/wk-table:hidden',
            '5xl' => '@5xl/wk-table:hidden',
            default => '',
        },
        match ($from) {
            'sm' => 'max-sm:hidden',
            'md' => 'max-md:hidden',
            'lg' => 'max-lg:hidden',
            'xl' => 'max-xl:hidden',
            '2xl' => 'max-2xl:hidden',
            default => '',
        },
        match ($below) {
            'sm' => 'sm:hidden',
            'md' => 'md:hidden',
            'lg' => 'lg:hidden',
            'xl' => 'xl:hidden',
            '2xl' => '2xl:hidden',
            default => '',
        },
    ]));
@endphp

{{-- The span draws nothing of its own, so its class block starts empty and a personalization
     adds to it. The visibility stays outside the block: a personalization that replaced it would
     otherwise show the value twice or not at all. --}}
<span data-wk-table-stand-in {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [WireKit::resolveClasses('table.stand-in', 'base', '', $scope), $classes])) }}>{{ $slot }}</span>
