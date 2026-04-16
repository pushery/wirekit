@props([
    'index' => 0,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Resizable handle — interactive divider that drives the size of the
    // previous panel. The actual drag + keyboard logic lives in the Alpine
    // component `wirekitResizableHandle` (see resources/js/components/
    // resizable.js); this template just marks up the element and lets
    // Alpine attach the WAI-ARIA Window Splitter attributes at init time.
    //
    // The `index` prop is kept for backward compatibility — it was used by
    // the old JS implementation to locate the previous panel by ordinal,
    // but the new implementation uses `previousElementSibling` instead.
    //
    // The inner <span data-wk-resizable-grip> is a centered, direction-aware
    // three-dot pill that marks the drag target visually. It is aria-hidden
    // because the handle itself carries the ARIA separator role.
    $classes = WireKit::resolveClasses('resizable.handle', 'base', '', $scope);
@endphp

<div
    data-wk-resizable-handle
    x-data="wirekitResizableHandle"
    x-on:pointerdown="onPointerDown($event)"
    x-on:pointermove="onPointerMove($event)"
    x-on:pointerup="onPointerUp($event)"
    x-on:pointercancel="onPointerUp($event)"
    x-on:keydown="onKeyDown($event)"
    {{ $attributes->class([$classes]) }}
>
    <span data-wk-resizable-grip aria-hidden="true"></span>
</div>
