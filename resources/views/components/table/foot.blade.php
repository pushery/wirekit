{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'scope' => null,
])

{{-- The table's `stack-below`: a table that stacks its rows writes its roles out. --}}
@aware([
    'stackBelow' => null,
    'stackBelowContainer' => null,
])

@php
    // `@aware` takes a key from any ancestor that holds it; these are read from the family only
    // (Support\AwareProp).
    $stackBelow = \Pushery\WireKit\Support\AwareProp::read('stackBelow', ['table'], null);
    // A table that stacks by its own width writes the same roles out as one that stacks by the window's.
    $stackBelow = filled($stackBelow) ? $stackBelow : \Pushery\WireKit\Support\AwareProp::read('stackBelowContainer', ['table'], null);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.foot', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // `@aware` leaves the key in the bag, so a tag that also wrote it would render it as an attribute.
    $attributes = $attributes->except(['stackBelow', 'stack-below', 'stackBelowContainer', 'stack-below-container']);

    // Tfoot styling — same subtle background as thead + top border
    $classes = WireKit::resolveClasses('table.foot', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-subtle)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'font-[number:var(--font-wk-heading-weight)]',
    ]), $scope);
@endphp

<tfoot @if(filled($stackBelow)) role="rowgroup" @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</tfoot>
