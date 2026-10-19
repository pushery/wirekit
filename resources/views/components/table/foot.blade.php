{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'scope' => null,
])

{{-- The table's `stack-below`: a table that stacks its rows writes its roles out. --}}
@aware([
    'stackBelow' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.foot', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // `@aware` leaves the key in the bag, so a tag that also wrote it would render it as an attribute.
    $attributes = $attributes->except(['stackBelow', 'stack-below']);

    // Tfoot styling — same subtle background as thead + top border
    $classes = WireKit::resolveClasses('table.foot', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-subtle)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'font-[number:var(--font-wk-heading-weight)]',
    ]), $scope);
@endphp

<tfoot @if(filled($stackBelow)) role="rowgroup" @endif {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</tfoot>
