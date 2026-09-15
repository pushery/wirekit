{{-- optimistic-ui: n/a — client-only
     Its state is expansion and keyboard focus. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Names the tree. A `role="tree"` is announced by its name and nothing else.
    'label' => null,
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
        // `wk-tree-view` is a marker, not a styled class, and it is load-bearing:
        // the library's reduced-motion clamp matches a `wk-` CLASS TOKEN and its
        // descendants — deliberately a token and not a substring, because
        // matching the attribute as a substring once clamped a whole
        // application's animations through `bg-[var(--color-wk-bg)]` on its
        // <body>. Nothing in this subtree carried such a token, so for a reader
        // who had asked their operating system for no motion the chevron still
        // rotated on `transition-transform` and a branch still opened on
        // `x-collapse`'s inline 250ms height transition. Every node, chevron and
        // role="group" panel is a descendant of this <ul>, so the token here is
        // what puts all of them inside the rule — and inside the
        // `data-reduce-motion` escape hatch written against the same selector.
        'wk-tree-view',
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
<ul data-wk-prose-skip
    role="tree"
    {{-- A `role="tree"` is announced by its name and by nothing else, and this component had
         no way to give it one: no `label` prop, no default, and nothing in the props table.
         Two file trees on a page were two identical "tree"s.

         The caller's own name wins in either form; the catalog default is a floor rather than
         a preference. Unlike a landmark, a tree has no uniqueness rule, so a shared default
         name costs nothing that an unnamed tree does not cost more. --}}
    @if(filled($label))
        aria-label="{{ $label }}"
    @elseif(! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby'))
        aria-label="{{ __('wirekit::Tree') }}"
    @endif
    x-data="wirekitTreeView()"
    {{ $attributes->merge(['style' => 'list-style: none; margin: 0; padding: 0;'])->class([$classes]) }}
    {{-- Roving tabindex: the factory's init seeds one tab stop, and this keeps it under
         whichever row focus reached last — by arrow key, by mouse, or by a developer's own
         .focus(). focusin bubbles, so one binding on the tree covers every node. --}}
    @focusin="rove()"
    @keydown.arrow-down.prevent="focusNext()"
    @keydown.arrow-up.prevent="focusPrev()"
    @keydown.arrow-right.prevent="expandOrChild()"
    @keydown.arrow-left.prevent="collapseOrParent()"
    @keydown.home.prevent="focusFirst()"
    @keydown.end.prevent="focusLast()"
    @click="selectClicked($event)"
    @keydown.enter.prevent="selectFocused()"
    @keydown.space.prevent="selectFocused()"
    {{-- Type-ahead, the last row of the documented keyboard table. Bound unfiltered
         because the key it reacts to is "any printable one" — the factory drops
         everything else, including the space this list has already claimed above. --}}
    @keydown="typeAhead($event)"
>
    {{ $slot }}
</ul>
