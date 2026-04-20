@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tree view container — WAI-ARIA tree pattern.
    // Uses role="tree" with keyboard navigation handled by Alpine.
    // Padding prevents node hover backgrounds from overlapping container borders.
    $classes = WireKit::resolveClasses('tree-view', 'base', implode(' ', [
        'p-[var(--padding-wk-x-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- No x-cloak needed — tree has no hidden/shown toggle; Alpine only handles keyboard nav --}}
<ul
    role="tree"
    x-data="wirekitTreeView()"
    {{ $attributes->class([$classes]) }}
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
