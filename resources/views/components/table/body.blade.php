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
    \Pushery\WireKit\WireKit::warnUnknownProps('table.body', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // `@aware` leaves the key in the bag, so a tag that also wrote it would render it as an attribute.
    $attributes = $attributes->except(['stackBelow', 'stack-below', 'stackBelowContainer', 'stack-below-container']);

    // Tbody dividers — separates rows visually
    $classes = WireKit::resolveClasses('table.body', 'base', implode(' ', [
        'divide-y-[length:var(--border-wk-width)]',
        'divide-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<tbody data-wk-table-body @if(filled($stackBelow)) role="rowgroup" @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</tbody>
