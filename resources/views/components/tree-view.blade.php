{{-- optimistic-ui: n/a — client-only
     Its state is expansion and keyboard focus. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('tree-view', $attributes->getAttributes());

    // Tree view container — WAI-ARIA tree pattern.
    // Uses role="tree" with keyboard navigation handled by Alpine.
    // Padding prevents node hover backgrounds from overlapping container borders.
    $classes = WireKit::resolveClasses('tree-view', 'base', implode(' ', [
        // list-none strips the browser-default <ul> disc markers; the tree
        // renders its own indent + chevron affordances per node.
        'list-none m-0',
        'p-[var(--padding-wk-x-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- No x-cloak needed — tree has no hidden/shown toggle; Alpine only handles keyboard nav --}}
<ul
    role="tree"
    x-data="wirekitTreeView()"
    {{ $attributes->class([$classes]) }}
    style="list-style: none; margin: 0; padding: 0;"
    @keydown.arrow-down.prevent="focusNext()"
    @keydown.arrow-up.prevent="focusPrev()"
    @keydown.arrow-right.prevent="expandOrChild()"
    @keydown.arrow-left.prevent="collapseOrParent()"
    @keydown.home.prevent="focusFirst()"
    @keydown.end.prevent="focusLast()"
    @keydown.enter.prevent="selectFocused()"
    @keydown.space.prevent="selectFocused()"
>
    {{ $slot }}
</ul>
