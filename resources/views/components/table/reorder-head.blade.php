{{-- optimistic-ui: n/a — presentational
     A column heading. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.reorder-head', $attributes->getAttributes());
@endphp

{{-- The heading of the column `table.reorder` fills. Named for a screen reader, which reads a
     column's heading before its cells, and not drawn: the handles and arrows say what the column
     is to anyone who can see them. The slot renames it. --}}
<x-wirekit::table.th :scope="$scope" {{ $attributes->class(['w-px']) }}><span class="sr-only">{{ $slot->hasActualContent() ? $slot : __('wirekit::Order') }}</span></x-wirekit::table.th>
