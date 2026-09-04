{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'gap' => config('wirekit.components.stack.gap', 'md'),
    'align' => 'stretch',
    'justify' => 'start',
    // Which spacing ladder `gap` names a rung on. See `row` for the full
    // reasoning; the two components share this axis because they share the
    // prop, and a difference between them would be the worse of the two
    // outcomes — a developer who learned it on one would be wrong on the other.
    //
    // Short form: WireKit defines two ladders with the same rung names and
    // different values from `md` up (`--gap-wk-md` 0.75rem against
    // `--space-wk-md` 1rem), and only one of them was reachable here. Default
    // `space` keeps every existing call site exactly where it is.
    //
    // Both maps are written out rather than derived: Tailwind emits an
    // arbitrary utility only when it can see the class as a literal in a
    // scanned file.
    'scale' => 'space',
    'wrap' => false,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('stack', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $wrap = BooleanProp::from($wrap, false);

    // Resolved before the rungs, so an unknown ladder name is reported as what
    // it is rather than silently falling through to the historical one.
    $scale = WireKit::validateProp('stack', 'scale', (string) $scale, ['space', 'gap']);

    $gapClasses = $scale === 'gap'
        ? match ($gap) {
            'none' => '',
            'xs' => 'gap-[var(--gap-wk-xs,0.25rem)]',
            'sm' => 'gap-[var(--gap-wk-sm,0.5rem)]',
            'md' => 'gap-[var(--gap-wk-md,0.75rem)]',
            'lg' => 'gap-[var(--gap-wk-lg,1rem)]',
            'xl' => 'gap-[var(--gap-wk-xl,1.5rem)]',
            '2xl' => 'gap-[var(--gap-wk-2xl,2rem)]',
            default => WireKit::validateProp('stack', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
        }
        : match ($gap) {
            'none' => '',
            'xs' => 'gap-[var(--space-wk-xs,0.25rem)]',
            'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
            'md' => 'gap-[var(--space-wk-md,1rem)]',
            'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
            'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
            '2xl' => 'gap-[var(--space-wk-2xl,4rem)]',
            default => WireKit::validateProp('stack', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
        };

    $alignClasses = match ($align) {
        'start' => 'items-start',
        'center' => 'items-center',
        'end' => 'items-end',
        'stretch' => 'items-stretch',
        'baseline' => 'items-baseline',
        default => WireKit::validateProp('stack', 'align', $align, ['start', 'center', 'end', 'stretch', 'baseline']),
    };

    $justifyClasses = match ($justify) {
        'start' => 'justify-start',
        'center' => 'justify-center',
        'end' => 'justify-end',
        'between' => 'justify-between',
        'around' => 'justify-around',
        'evenly' => 'justify-evenly',
        default => WireKit::validateProp('stack', 'justify', $justify, ['start', 'center', 'end', 'between', 'around', 'evenly']),
    };

    $classes = WireKit::resolveClasses('stack', 'base', implode(' ', array_filter([
        'flex flex-col',
        $gapClasses,
        $alignClasses,
        $justifyClasses,
        $wrap ? 'flex-wrap' : '',
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('stack', (string) $as);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
