{{-- Chart component — renders a canvas with Alpine.js lifecycle management.
     wire:ignore prevents Livewire's DOM morphing from destroying the chart state.
     role="img" marks the chart as a graphical element for screen readers. --}}
@php
    // Extract caller's aria-label (if any) and render it explicitly so we can
    // supply a fallback; drop it from the bag to avoid emitting a duplicate
    // attribute when the bag is echoed below.
    $chartAriaLabel = $attributes->get('aria-label', 'Chart');

    // Merge caller-supplied inline `style` with the component's own hardcoded
    // style (height + background). Without this manual merge Blade would emit
    // two separate `style=""` attributes on the same element — only the first
    // wins in every browser, so the caller's overrides (e.g. max-width) would
    // silently disappear. We strip `style` from the bag, concatenate the
    // default style first, caller style second (caller wins on duplicate
    // properties because later declarations override earlier ones).
    $callerStyle = (string) $attributes->get('style', '');
    $defaultStyle = "height: {$height}; background-color: var(--color-wk-bg);";
    $mergedStyle = trim($defaultStyle.' '.$callerStyle);
    $chartAttributes = $attributes->except(['aria-label', 'style']);
@endphp
<div
    x-data="{{ $alpineComponent }}(@js($chartConfig))"
    {{ $chartAttributes->class(['relative w-full']) }}
    style="{{ $mergedStyle }}"
    wire:ignore
    role="img"
    aria-label="{{ $chartAriaLabel }}"
>
    {{-- Canvas is hidden from assistive tech; the parent div carries the semantic role --}}
    <canvas x-ref="canvas" aria-hidden="true"></canvas>
</div>
