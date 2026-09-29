{{-- optimistic-ui: n/a — client-only
     The one interactive thing here is the scroll region's own keyboard reach: it takes
     `tabindex="0"` so a dialog holding only text can be scrolled without a mouse, which is
     WCAG 2.1.1 and not an action. Nothing about it reaches the server, so there is no result
     to show early. --}}
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
    // `min-h-0` is what makes the scroll possible. The panel is a column capped to the
    // viewport, and a flex item does not shrink below its content height unless its minimum
    // is lifted — without it the cap on the panel is inert and the body simply overflows it.
    //
    // A scroll region is keyboard-reachable the house way: an unconditional `tabindex="0"`
    // and a `focus-visible:` ring, inset because the panel clips anything drawn outside its
    // rounded edge. Deliberately not a landmark — see scroll-area for why a built-in name
    // would make every instance the same one. modal.js keeps initial focus off this wrapper
    // whenever the dialog holds a control.
    $classes = WireKit::resolveClasses('modal.body', 'base', implode(' ', [
        'min-h-0 overflow-y-auto wk-scrollbar',
        // One token on all four sides, and the header's. Wider side padding would start the content
        // further in than the title above it and sit it further from the sides than from the
        // bottom; equal padding on one token keeps a single edge down the panel, and a theme
        // cannot pull the sides apart.
        'p-[var(--padding-wk-x-lg)]',
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
