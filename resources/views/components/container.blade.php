{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'max' => config('wirekit.components.container.max', 'xl'),
    'padding' => config('wirekit.components.container.padding', 'md'),
    'center' => true,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('container', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $center = BooleanProp::from($center, true);

    $maxClasses = match ($max) {
        'sm' => 'max-w-[var(--size-wk-container-sm,40rem)]',
        'md' => 'max-w-[var(--size-wk-container-md,48rem)]',
        'lg' => 'max-w-[var(--size-wk-container-lg,64rem)]',
        'xl' => 'max-w-[var(--size-wk-container-xl,80rem)]',
        '2xl' => 'max-w-[var(--size-wk-container-2xl,96rem)]',
        'full' => 'max-w-full',
        default => WireKit::validateProp('container', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full']),
    };

    // Inline padding reads from `--padding-wk-x-*` so a
    // `<x-wirekit::container>` nested inside `<x-wirekit::main padding="lg">`
    // (which uses the same `--padding-wk-x-lg` token) inherits a single
    // content-edge spine — the inner content's visible-text left edge
    // sits exactly where main's would. Vertical section padding still
    // reads from `--space-wk-*` (developers apply `py-*` themselves on
    // the container when they want a section rhythm).
    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--padding-wk-x-sm,0.625rem)]',
        'md' => 'px-[var(--padding-wk-x-md,0.75rem)]',
        'lg' => 'px-[var(--padding-wk-x-lg,1rem)]',
        'xl' => 'px-[var(--padding-wk-x-xl,1.5rem)]',
        default => WireKit::validateProp('container', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl']),
    };

    $classes = WireKit::resolveClasses('container', 'base', implode(' ', array_filter([
        'w-full',
        $maxClasses,
        $center ? 'mx-auto' : '',
        $paddingClasses,
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('container', (string) $as);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
