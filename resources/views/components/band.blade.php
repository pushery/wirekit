{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // Which edge carries the rule.
    //
    // ⚠️ There is no majority to default to, and the number is worth recording so nobody
    // later "corrects" this on a hunch: across the 24 hand-rolled bands in the blueprint
    // catalog the split is exactly 12 top and 12 bottom. `bottom` is chosen because a band
    // under a header is the mental model most callers arrive with, not because it is more
    // common. State it at the call site.
    'edge' => 'bottom',
    'padding' => 'sm',
    // The occasional band that is not on the page background. Measured: 5 of the 24.
    'surface' => 'none',
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('band', $attributes->getAttributes());

    $edgeClasses = match ($edge) {
        'none' => '',
        'top' => 'border-t border-[color:var(--color-wk-border)]',
        'bottom' => 'border-b border-[color:var(--color-wk-border)]',
        'both' => 'border-y border-[color:var(--color-wk-border)]',
        default => WireKit::validateProp('band', 'edge', $edge, ['none', 'top', 'bottom', 'both']),
    };

    $paddingClasses = match ($padding) {
        'none' => '',
        'xs' => 'px-[var(--padding-wk-x-sm,0.5rem)] py-[var(--padding-wk-y-xs,0.25rem)]',
        'sm' => 'px-[var(--padding-wk-x-md,0.75rem)] py-[var(--padding-wk-y-sm,0.5rem)]',
        'md' => 'px-[var(--padding-wk-x-lg,1.25rem)] py-[var(--padding-wk-y-md,0.75rem)]',
        'lg' => 'px-[var(--padding-wk-x-xl,2rem)] py-[var(--padding-wk-y-lg,1rem)]',
        default => WireKit::validateProp('band', 'padding', $padding, ['none', 'xs', 'sm', 'md', 'lg']),
    };

    $surfaceClasses = match ($surface) {
        'none' => '',
        'subtle' => 'bg-[var(--color-wk-bg-subtle)]',
        'muted' => 'bg-[var(--color-wk-bg-muted)]',
        default => WireKit::validateProp('band', 'surface', $surface, ['none', 'subtle', 'muted']),
    };

    // `$as` is interpolated into the tag name below, so an unvalidated value is written
    // straight into the opening tag — `as="div onmouseover=alert(1)"` would arrive as an
    // attribute. `tagName()` checks the SHAPE rather than an allowlist, and the reason is in
    // its own test: an enum has to guess which elements a caller might legitimately want, and
    // `article` is exactly the kind a guess omits. My first version here was that enum.
    $as = \Pushery\WireKit\WireKit::tagName('band', (string) $as);

    $classes = WireKit::resolveClasses('band', 'base', trim(implode(' ', array_filter([
        $edgeClasses,
        $paddingClasses,
        $surfaceClasses,
    ]))), $scope);
@endphp

{{--
    A blank element, and that is the whole design.

    `toolbar` looks right and renders role="toolbar" — an ARIA composite widget that promises
    operable controls with arrow-key navigation. A band holding one search field is not that,
    and claiming the role would be a promise the surface does not keep. `container` caps and
    centers its width, which is the opposite of what an edge-to-edge band wants. `card.header`
    and `card.footer` bring exactly this shape and only at the two ends of a card; a window with
    seven bands has two of them.

    So this renders a div with no role. The form is chrome, not semantics. Where a band DOES
    carry meaning, the caller says so — `as="header"`, `as="footer"`, or an aria-label of their
    own through the attribute bag.
--}}
<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
