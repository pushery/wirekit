{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // Which edge carries the rule.
    //
    // There is no majority to default to: bands above and below their content are equally
    // common. `bottom` is chosen because a band under a header is the mental model most
    // callers arrive with. State it at the call site.
    'edge' => 'bottom',
    'padding' => 'sm',
    // The occasional band that is not on the page background.
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

    $edgeClasses = match (WireKit::validateProp('band', 'edge', $edge, ['none', 'top', 'bottom', 'both'])) {
        'none' => '',
        'top' => 'border-t border-[color:var(--color-wk-border)]',
        'bottom' => 'border-b border-[color:var(--color-wk-border)]',
        'both' => 'border-y border-[color:var(--color-wk-border)]',
    };

    $paddingClasses = match (WireKit::validateProp('band', 'padding', $padding, ['none', 'xs', 'sm', 'md', 'lg'])) {
        'none' => '',
        // No literal fallbacks. SIX of the eight here disagreed with the token they stood in
        // for — x-sm said 0.5rem against 0.625rem, y-sm 0.5rem against 0.375rem, x-lg 1.25rem
        // against 1rem, and so on. A fallback fires only when the token did not resolve, so
        // one carrying a different number turns a missing value into a WRONG one, and the band
        // renders at a padding scale nothing in the theme uses. These are core tokens declared
        // unconditionally, so the case never arises — which is precisely why nobody noticed
        // them drifting.
        'xs' => 'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]',
        'sm' => 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]',
        'md' => 'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-md)]',
        'lg' => 'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-lg)]',
    };

    $surfaceClasses = match (WireKit::validateProp('band', 'surface', $surface, ['none', 'subtle', 'muted'])) {
        'none' => '',
        'subtle' => 'bg-[var(--color-wk-bg-subtle)]',
        'muted' => 'bg-[var(--color-wk-bg-muted)]',
    };

    // `$as` is interpolated into the tag name below, so an unvalidated value is written
    // straight into the opening tag — `as="div onmouseover=alert(1)"` would arrive as an
    // attribute. `tagName()` checks the SHAPE rather than an allowlist, and the reason is in
    // its own test: an enum has to guess which elements a caller might legitimately want, and
    // `article` is exactly the kind a guess omits. An allowlist enum was this check's
    // first shape.
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
<{{ $as }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
