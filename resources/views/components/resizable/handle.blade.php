{{-- optimistic-ui: n/a — client-only
     Its state is the split position. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'index' => 0,
    // Accessible name for the separator. It needs one: the Alpine plugin gives this
    // element `role="separator"` and `tabindex="0"`, so a reader tabs onto it and hears
    // "splitter, 50%" with nothing saying WHICH split it moves — and a three-panel layout
    // has two of them, announced identically. The APG's Window Splitter names it after the
    // pane it sizes, so pass the pane's name when you have one, or point `aria-labelledby`
    // at that pane's visible heading; the default is only a floor.
    'label' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('resizable.handle', $attributes->getAttributes());

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

    // The name is emitted only when the caller supplied neither of the two attributes
    // that already carry one. `aria-labelledby` pointing at the pane's own heading is the
    // better answer whenever there is a heading, and writing an `aria-label` beside it
    // would put a second name on the element for the first one to lose to.
    $needsName = $attributes->missing('aria-label') && $attributes->missing('aria-labelledby');
@endphp

<div
    data-wk-resizable-handle
    x-data="wirekitResizableHandle"
    x-on:pointerdown="onPointerDown($event)"
    x-on:pointermove="onPointerMove($event)"
    x-on:pointerup="onPointerUp($event)"
    x-on:pointercancel="onPointerUp($event)"
    x-on:keydown="onKeyDown($event)"
    @if($needsName) aria-label="{{ $label ?? __('wirekit::Resize panel') }}" @endif
    {{ $attributes->class([$classes]) }}
>
    <span data-wk-resizable-grip aria-hidden="true"></span>
</div>
