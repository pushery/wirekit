{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'gap' => config('wirekit.components.row.gap', 'md'),
    'align' => 'center',
    // Line the CONTROLS up when this row holds form fields.
    //
    // A labeled field is taller than an unlabeled one and a field with a hint is taller
    // still, so any single `align` value lines up the wrong edge for some mix of them.
    // Measured across six combinations: `center` leaves the controls 13.5px apart, `end`
    // fixes that and then breaks by 25.5px as soon as one field carries a hint, and
    // `start`/`stretch`/`baseline` are worse than either.
    //
    // With this on, the row becomes a three-row grid — labels, controls, messages — that
    // every field shares, so a control sits on the control row whatever its neighbors
    // carry. Children that are not fields (a submit button) sit on the control row too.
    //
    // Opt-in, and it has to be: it changes the row's display model and asks the fields
    // inside it to render placeholder parts. Nothing renders differently until asked.
    'alignFields' => false,
    'justify' => 'start',
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
    \Pushery\WireKit\WireKit::warnUnknownProps('row', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $wrap = BooleanProp::from($wrap, false);

    $gapClasses = match ($gap) {
        'none' => '',
        'xs' => 'gap-[var(--space-wk-xs,0.25rem)]',
        'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
        'md' => 'gap-[var(--space-wk-md,1rem)]',
        'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'gap-[var(--space-wk-2xl,4rem)]',
        default => WireKit::validateProp('row', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
    };

    $alignClasses = match ($align) {
        'start' => 'items-start',
        'center' => 'items-center',
        'end' => 'items-end',
        'stretch' => 'items-stretch',
        'baseline' => 'items-baseline',
        default => WireKit::validateProp('row', 'align', $align, ['start', 'center', 'end', 'stretch', 'baseline']),
    };

    $justifyClasses = match ($justify) {
        'start' => 'justify-start',
        'center' => 'justify-center',
        'end' => 'justify-end',
        'between' => 'justify-between',
        'around' => 'justify-around',
        'evenly' => 'justify-evenly',
        default => WireKit::validateProp('row', 'justify', $justify, ['start', 'center', 'end', 'between', 'around', 'evenly']),
    };

    $alignFields = BooleanProp::from($alignFields, false);

    $classes = WireKit::resolveClasses('row', 'base', implode(' ', array_filter([
        // `wk-form-row` replaces the flex model with a shared three-row grid, so the flex
        // utilities below would be dead weight rather than harmless — and `align`/`justify`
        // no longer describe anything, which is why they are dropped rather than overridden.
        $alignFields ? 'wk-form-row' : 'flex flex-row',
        $gapClasses,
        $alignFields ? '' : $alignClasses,
        $alignFields ? '' : $justifyClasses,
        $alignFields || ! $wrap ? '' : 'flex-wrap',
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('row', (string) $as);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
