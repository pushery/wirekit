{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('mark', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('mark', 'base', implode(' ', [
        // `_` rather than spaces inside the arbitrary value: the browser splits the
        // class attribute on whitespace, so the spaced form was three garbage tokens and
        // Tailwind never compiled the class it was asked for — the mark rendered with no
        // background at all, silently. The rest of this directory already writes it this
        // way (`color-mix(in_oklch,…)_14%`).
        'bg-[var(--color-wk-warning-bg,oklch(0.905_0.093_102.1))]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-0.5',
    ]), $scope);
@endphp

<mark {{ $attributes->class([$classes]) }}>{{ $slot }}</mark>