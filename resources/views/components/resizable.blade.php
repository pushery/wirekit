{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'direction' => 'horizontal',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('resizable', $attributes->getAttributes());

    // Resizable — split panel layout, CSS for the pointer and Alpine for the rest.
    //
    // Dragging a panel edge is delegated to the browser's native CSS `resize`
    // property (see `dist/wirekit.css` → "Resizable" section): every non-last
    // panel exposes a browser-native corner grip, and the last panel uses
    // `flex: 1` to absorb whatever space the others leave behind.
    //
    // The accompanying `<x-wirekit::resizable.handle>` is the other half, and it
    // is NOT decorative: `wirekitResizableHandle` (resources/js/components/
    // resizable.js) attaches the WAI-ARIA Window Splitter attributes to it at
    // init — role, orientation, aria-controls, the value range and a live
    // aria-valuenow — and owns the pointer-drag and arrow-key handlers, so the
    // split is reachable and movable without a mouse. This comment described it
    // as a decorative line with no JavaScript and no keyboard handler long after
    // that stopped being true, which is worth more than a stale sentence usually
    // is: a developer reading it would leave the handle unnamed and untested on
    // the belief that nothing there was interactive.
    $classes = WireKit::resolveClasses('resizable', 'base', implode(' ', [
        'flex w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
    ]), $scope);

    // flex-row / flex-col drives the visual orientation. The matching
    // data-wk-direction attribute drives the CSS selectors in
    // dist/wirekit.css that flip between `resize: horizontal` and
    // `resize: vertical` on the panels, plus the per-direction
    // `contain` rule that locks the wrapper against descendant growth.
    $directionClass = $direction === 'vertical' ? 'flex-col' : 'flex-row';
@endphp

<div
    data-wk-resizable
    data-wk-direction="{{ $direction === 'vertical' ? 'vertical' : 'horizontal' }}"
    {{ $attributes->class([$classes, $directionClass]) }}
>
    {{ $slot }}
</div>
