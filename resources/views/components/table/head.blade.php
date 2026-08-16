{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // `flush` drops the fill and keeps everything else — the divider, the sticky rules,
    // the spacing. The fill was painted unconditionally, so a table head that
    // needed no background could only be reached by overriding the whole class string,
    // which also discards the sticky behavior and the border a reader relies on. One
    // opinion about one property should not cost the rest of the component.
    'variant' => config('wirekit.components.table.head.variant', 'filled'),
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.head', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Thead styling — subtle background + bottom border divider
    // Sticky header: when parent table has data-wk-sticky-header, thead sticks to top
    $variant = in_array($variant, ['filled', 'flush'], true)
        ? $variant
        : WireKit::validateProp('table.head', 'variant', (string) $variant, ['filled', 'flush']);

    // The fill is the ONLY thing `flush` removes. The divider and the sticky rules stay,
    // because they are what makes a head readable while the body scrolls under it — and
    // conflating them is exactly what forced the all-or-nothing override this replaces.
    $classes = WireKit::resolveClasses('table.head', 'base', implode(' ', array_filter([
        $variant === 'filled' ? 'bg-[var(--color-wk-bg-subtle)]' : null,
        'border-b-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        '[table[data-wk-sticky-header]_&]:sticky [table[data-wk-sticky-header]_&]:top-0 [table[data-wk-sticky-header]_&]:z-10',
    ])), $scope);
@endphp

<thead data-wk-table-head {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</thead>
