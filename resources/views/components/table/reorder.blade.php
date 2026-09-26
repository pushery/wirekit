{{-- optimistic-ui: n/a — passthrough
     The arrows call the developer's own action, named in `action`, which the table body's
     `wire:sort` calls too. A drag moves the row in the browser before its request leaves; the
     arrows show the order the server answers with. --}}
@props([
    // The row's key: the value its row carries in `wire:sort:item`.
    'item' => null,
    // Where the row stands, counted from 0, and how many rows there are. The first row cannot
    // move up and the last cannot move down, so those arrows are disabled. Without `count` the
    // down arrow is never disabled, because nothing here knows which row is last.
    'position' => 0,
    'count' => null,
    // The Livewire method the table body's `wire:sort` names. The arrows call it with the two
    // arguments a drop passes, the row's key and its new position, so one method serves the
    // pointer and the keyboard.
    'action' => null,
    // What the row is, for the arrows' names: "Move Photo 3 up". Without it they are "Move up"
    // and "Move down", which is enough where the row's own cells say what it is.
    'label' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.reorder', $attributes->getAttributes());

    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\WireKit;

    $position = max(0, (int) $position);
    $count = $count === null || $count === '' ? null : max(0, (int) $count);

    // The arrows call the method by name inside an attribute Livewire evaluates, so it has to be
    // a name. Anything else throws in debug, and in production the arrows render without one.
    if (! is_string($action) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $action) !== 1) {
        WireKit::validateProp('table.reorder', 'action', is_string($action) ? $action : '', [
            'the name of the Livewire method the table body\'s wire:sort calls',
        ]);
        $action = null;
    }

    $isFirst = $position === 0;
    $isLast = $count !== null && $position >= $count - 1;

    // The key the drop would pass, as a JavaScript literal, so a string key arrives as a string.
    $key = AlpinePayload::from($item);
    $upCall = $action !== null && ! $isFirst ? $action.'('.$key.', '.($position - 1).')' : null;
    $downCall = $action !== null && ! $isLast ? $action.'('.$key.', '.($position + 1).')' : null;

    $upText = filled($label) ? __('wirekit::Move :item up', ['item' => $label]) : __('wirekit::Move up');
    $downText = filled($label) ? __('wirekit::Move :item down', ['item' => $label]) : __('wirekit::Move down');

    $handleClasses = WireKit::resolveClasses('table.reorder', 'handle', implode(' ', [
        'inline-flex items-center justify-center',
        'cursor-grab active:cursor-grabbing touch-none',
        'text-[color:var(--color-wk-text-subtle)] hover:text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

{{-- `data-wk-reorder-position` is drawn by the server. A drag reorders the rows in the browser
     before the request leaves, so the order of the rows alone cannot tell a test, or anything
     else, whether the server has answered; the positions it drew can. --}}
{{-- `wirekitTableReorder` puts the focus back on the pressed arrow once the server has moved the
     row: a morph that moves the focused row drops the focus to the page. --}}
<x-wirekit::table.td
    x-data="wirekitTableReorder({ key: {{ AlpinePayload::string((string) $item) }} })"
    data-wk-table-reorder
    data-wk-reorder-key="{{ (string) $item }}"
    data-wk-reorder-position="{{ $position }}"
    {{ $attributes->class(['w-px whitespace-nowrap']) }}
>
    <div class="flex items-center gap-[var(--gap-wk-xs)]">
        {{-- The handle a pointer drags. Hidden from assistive technology on purpose: a drag is
             not something a keyboard or a screen reader can do, and the two buttons beside it are
             how they move a row. --}}
        <span wire:sort:handle aria-hidden="true" title="{{ __('wirekit::Drag to reorder') }}" class="{{ $handleClasses }}">
            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true">
                <circle cx="6" cy="3.5" r="1.25" /><circle cx="10" cy="3.5" r="1.25" />
                <circle cx="6" cy="8" r="1.25" /><circle cx="10" cy="8" r="1.25" />
                <circle cx="6" cy="12.5" r="1.25" /><circle cx="10" cy="12.5" r="1.25" />
            </svg>
        </span>

        <x-wirekit::tooltip :text="$upText" :focusable-trigger="false" :describes="false">
            <x-wirekit::button size="xs" intent="neutral" surface="ghost" icon-only :disabled="$isFirst" :wire:click="$upCall" data-wk-reorder-arrow="up" x-on:click="remember('up', $el)">
                <x-slot:iconLeft><x-wirekit::icon name="arrow-up" size="sm" aria-hidden="true" /></x-slot:iconLeft>
                {{ $upText }}
            </x-wirekit::button>
        </x-wirekit::tooltip>

        <x-wirekit::tooltip :text="$downText" :focusable-trigger="false" :describes="false">
            <x-wirekit::button size="xs" intent="neutral" surface="ghost" icon-only :disabled="$isLast" :wire:click="$downCall" data-wk-reorder-arrow="down" x-on:click="remember('down', $el)">
                <x-slot:iconLeft><x-wirekit::icon name="arrow-down" size="sm" aria-hidden="true" /></x-slot:iconLeft>
                {{ $downText }}
            </x-wirekit::button>
        </x-wirekit::tooltip>
    </div>
</x-wirekit::table.td>
