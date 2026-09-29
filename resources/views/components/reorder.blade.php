{{-- optimistic-ui: n/a — passthrough
     The arrows call the developer's own action, named in `action`, which the list's `wire:sort`
     calls too. A drag moves the row in the browser before its request leaves; the arrows show the
     order the server answers with. --}}
@props([
    // The row's key: the value its row carries in `wire:sort:item`.
    'item' => null,
    // Where the row stands, counted from 0, and how many rows there are. The first row cannot
    // move up and the last cannot move down, so those arrows are disabled. Without `count` the
    // down arrow is never disabled, because nothing here knows which row is last.
    'position' => 0,
    'count' => null,
    // The Livewire method the list's `wire:sort` names. The arrows call it with the two arguments
    // a drop passes, the row's key and its new position, so one method serves the pointer and the
    // keyboard.
    'action' => null,
    // What the row is, for the arrows' names: "Move Photo 3 up". Without it they are "Move up"
    // and "Move down", which is enough where the row itself says what it is.
    'label' => null,
    // The arrows' button surface and size, handed to both. An application whose icon buttons use
    // another surface sets it here: a class on this element does not reach the buttons inside it.
    'arrowSurface' => 'ghost',
    'arrowSize' => 'xs',
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reorder', $attributes->getAttributes());

    use Pushery\WireKit\Support\AlpinePayload;
@endphp

{{-- The same controls as `table.reorder`, on an element that fits in any row: a list item, a
     card, a grid cell. `data-wk-reorder-position` is drawn by the server, so a test that waits for
     the positions to match the new order waits for the server's answer rather than for the
     browser's guess after a drag. `wirekitTableReorder` puts the focus back on the pressed arrow
     once the server has moved the row. --}}
<div
    x-data="wirekitTableReorder({ key: {{ AlpinePayload::string((string) $item) }} })"
    data-wk-reorder
    data-wk-reorder-key="{{ (string) $item }}"
    data-wk-reorder-position="{{ max(0, (int) $position) }}"
    {{ $attributes->class(['inline-flex shrink-0 items-center gap-[var(--gap-wk-xs)]']) }}
>
    @include('wirekit::components.partials.reorder-controls', ['reorderComponent' => 'reorder'])
</div>
