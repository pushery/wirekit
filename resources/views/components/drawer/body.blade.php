{{-- optimistic-ui: n/a — client-only
     The one interactive thing here is the scroll region's own keyboard reach: it takes
     `tabindex="0"` so a drawer holding only text can be scrolled without a mouse, which is
     WCAG 2.1.1 and not an action. Nothing about it reaches the server, so there is no result
     to show early.

     ⚠️ This said "presentational" until the keyboard reach landed, and the guard is what
     caught it — that arm reads the component rather than the comment, which is the whole
     reason it exists. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('drawer.body', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Body classes — the one region of the drawer that scrolls.
    //
    // A scroll region is keyboard-reachable the house way: an unconditional `tabindex="0"` and a
    // `focus-visible:` ring, inset because the panel clips anything drawn outside its edge.
    // Deliberately not a landmark — see scroll-area for why a built-in name would make every
    // instance the same one. The scroll guard used to exempt this file on the reasoning that the
    // body "holds the drawer's own focusable content", which is only true when there is some: a
    // drawer holding nothing but text had no tab stop here at all, and a keyboard could not
    // scroll it. drawer.js keeps initial focus off this wrapper whenever the drawer holds a
    // control.
    $classes = WireKit::resolveClasses('drawer.body', 'base', implode(' ', [
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'wk-scrollbar flex-1 overflow-y-auto',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);
@endphp

{{-- Drawer body — the scrolling region between header and footer --}}
<div data-wk-drawer-body tabindex="0" {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
