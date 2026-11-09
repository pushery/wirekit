{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
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

    // Blade compiles an unbound attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $center = BooleanProp::from($center, true, 'container', 'center');

    // The tier is the width of the content, and the padding stands outside it: the box may grow to
    // the tier plus the padding on both sides. That is how the navbar, the hero, the brand bar and
    // the footer lay out the same tier, so in a window wider than the box all of them start their
    // content at one line; below that width each stands at its padding. A box of the tier alone
    // put this one's content a padding further in. The padding is the container's own, read from
    // the variable the padding classes below set, so `padding="none"` adds nothing.
    // Tailwind arbitrary values take no spaces, hence `calc(a+2*b)` unspaced.
    $maxClasses = match (WireKit::validateProp('container', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full'])) {
        'sm' => 'max-w-[calc(var(--size-wk-container-sm,40rem)+2*var(--wk-container-pad,0px))]',
        'md' => 'max-w-[calc(var(--size-wk-container-md,48rem)+2*var(--wk-container-pad,0px))]',
        'lg' => 'max-w-[calc(var(--size-wk-container-lg,64rem)+2*var(--wk-container-pad,0px))]',
        'xl' => 'max-w-[calc(var(--size-wk-container-xl,80rem)+2*var(--wk-container-pad,0px))]',
        '2xl' => 'max-w-[calc(var(--size-wk-container-2xl,96rem)+2*var(--wk-container-pad,0px))]',
        'full' => 'max-w-full',
    };

    // Inline padding reads from `--padding-wk-x-*` so a
    // `<x-wirekit::container>` nested inside `<x-wirekit::main padding="lg">`
    // (which uses the same `--padding-wk-x-lg` token) inherits a single
    // content-edge spine — the inner content's visible-text left edge
    // sits exactly where main's would. Vertical section padding still
    // reads from `--space-wk-*` (developers apply `py-*` themselves on
    // the container when they want a section rhythm).
    //
    // Each tier also names its padding in `--wk-container-pad`, for the width above. `none` names
    // zero rather than nothing, because a custom property is inherited: a container without padding
    // inside one with it would otherwise add its parent's.
    $paddingClasses = match (WireKit::validateProp('container', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl'])) {
        'none' => '[--wk-container-pad:0px]',
        'sm' => '[--wk-container-pad:var(--padding-wk-x-sm,0.625rem)] px-[var(--padding-wk-x-sm,0.625rem)]',
        'md' => '[--wk-container-pad:var(--padding-wk-x-md,0.75rem)] px-[var(--padding-wk-x-md,0.75rem)]',
        'lg' => '[--wk-container-pad:var(--padding-wk-x-lg,1rem)] px-[var(--padding-wk-x-lg,1rem)]',
        'xl' => '[--wk-container-pad:var(--padding-wk-x-xl,1.5rem)] px-[var(--padding-wk-x-xl,1.5rem)]',
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

<{{ $as }} data-wk-prose-skip {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</{{ $as }}>