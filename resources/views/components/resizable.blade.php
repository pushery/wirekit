{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
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
    // split is reachable and movable without a mouse. A developer who took it for a
    // decorative line would leave the handle unnamed and untested on the belief that
    // nothing there is interactive.
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

@php
    // A caller's `aria-labelledby` or `aria-label` names the element a reader meets, below; on this
    // wrapper, which has no role, ARIA prohibits a name (Support\CallerName).
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
@endphp
<div
    data-wk-resizable
    data-wk-direction="{{ $direction === 'vertical' ? 'vertical' : 'horizontal' }}"
    {{ $attributes->class([$classes, $directionClass]) }}
    @if($callerLabelledBy !== null || $callerLabel !== null) @unless($attributes->has('role')) role="group" @endunless {{ \Pushery\WireKit\Support\CallerName::attribute($callerLabelledBy, $callerLabel) }} @endif
>
    {{ $slot }}
</div>
