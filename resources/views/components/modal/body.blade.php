{{-- optimistic-ui: n/a — client-only
     The one interactive thing here is the scroll region's own keyboard reach: it takes
     `tabindex="0"` so a dialog holding only text can be scrolled without a mouse, which is
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
    \Pushery\WireKit\WireKit::warnUnknownProps('modal.body', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Body classes — the one region of the dialog that scrolls.
    //
    // ⚠️ `min-h-0` IS WHAT MAKES THE SCROLL POSSIBLE. The panel is a column capped to the
    // viewport, and a flex item does not shrink below its content height unless its minimum
    // is lifted — without it the cap on the panel is inert and the body simply overflows it.
    // This file used to say "scrollable content area" over a class list that could not
    // scroll at all.
    //
    // A scroll region is keyboard-reachable the house way: an unconditional `tabindex="0"`
    // and a `focus-visible:` ring, inset because the panel clips anything drawn outside its
    // rounded edge. Deliberately not a landmark — see scroll-area for why a built-in name
    // would make every instance the same one. modal.js keeps initial focus off this wrapper
    // whenever the dialog holds a control.
    $classes = WireKit::resolveClasses('modal.body', 'base', implode(' ', [
        'min-h-0 overflow-y-auto wk-scrollbar',
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);
@endphp

{{-- Modal body — the scrolling region between header and footer --}}
<div data-wk-modal-body tabindex="0" {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
