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
    // The color and the size of the words, with the values a `text` takes: intent is
    // inherit | default | muted | subtle | accent | success | warning | danger, size is
    // inherit | xs | sm | base | lg | xl. Unset, the words take the cell's. A stand-in that only
    // mutes or shrinks its words needs no `text` inside it.
    'intent' => null,
    'size' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BlazeRender;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // Under Blaze, which an application may let compile this view into a function, no composer
    // runs for it, and it does the composers' work on its bag here (Support\BlazeRender).
    if (BlazeRender::active($attributes)) {
        BlazeRender::normalize($attributes);
    }

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

    // The words take the classes a `text` writes for the same value, as a cell's do, so a stand-in
    // and a `text` inside one read alike. Unset skips the check, since a long table renders a
    // stand-in in every row. An unknown value is reported through the strictness gate and, where
    // the gate does not throw, takes `inherit`, the first value it allows.
    $intent = filled($intent) ? (string) $intent : 'inherit';
    $intentClass = match ($intent === 'inherit' ? 'inherit' : WireKit::validateProp('table.stand-in', 'intent', $intent, ['inherit', 'default', 'muted', 'subtle', 'accent', 'success', 'warning', 'danger'])) {
        'default' => 'text-[color:var(--color-wk-text)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'accent' => 'text-[color:var(--color-wk-accent-text)]',
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'warning' => 'text-[color:var(--color-wk-warning-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
        default => '',
    };
    $size = filled($size) ? (string) $size : 'inherit';
    $sizeClass = match ($size === 'inherit' ? 'inherit' : WireKit::validateProp('table.stand-in', 'size', $size, ['inherit', 'xs', 'sm', 'base', 'lg', 'xl'])) {
        'xs' => 'text-[length:var(--text-wk-xs,0.75rem)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        default => '',
    };

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
     otherwise show the value twice or not at all. So do the color and the size, which this
     stand-in was asked for, not the look of every stand-in. --}}
<span data-wk-table-stand-in @if($belowContainer !== null) data-wk-table-measures @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [WireKit::resolveClasses('table.stand-in', 'base', '', $scope), $classes, $sizeClass, $intentClass])) }}>{{ $slot }}</span>
