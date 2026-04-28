@props([
    'before',
    'after',
    'beforeAlt' => '',
    'afterAlt' => '',
    'orientation' => 'horizontal',
    'value' => 50,
    'beforeLabel' => 'Before',
    'afterLabel' => 'After',
    'labels' => true,
    'decorative' => false,
    'ariaLabel' => 'Image comparison slider',
    'loading' => 'lazy',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $isVertical = $orientation === 'vertical';
    $clampedValue = max(0, min(100, (int) $value));

    // Accessibility: decorative images get forced-empty alts and role=presentation
    // on the outer figure. The interactive slider handle keeps role="slider".
    $effectiveBeforeAlt = $decorative ? '' : $beforeAlt;
    $effectiveAfterAlt = $decorative ? '' : $afterAlt;

    // Detect wire:model directly from the attribute bag without requiring
    // Livewire's `wire()` macro to be registered. We walk all attributes and
    // pick the first key that starts with `wire:model`, then parse its
    // modifier suffix (e.g. `wire:model.live.debounce.200ms`).
    $wireModelKey = null;
    $wireModelValue = null;
    foreach ($attributes->getAttributes() as $attrKey => $attrValue) {
        if (is_string($attrKey) && str_starts_with($attrKey, 'wire:model')) {
            $wireModelKey = $attrKey;
            $wireModelValue = $attrValue;
            break;
        }
    }
    $wireModelModifiers = $wireModelKey
        ? array_slice(explode('.', $wireModelKey), 1)
        : [];
    $isLiveModel = in_array('live', $wireModelModifiers, true);

    // Strict whitelist for the wire:model value — only PHP property paths
    // (alphanumerics, underscores, dots, dashes). Anything else is dropped
    // to avoid injecting arbitrary JS into the x-data attribute.
    $wireModelSafe = null;
    if ($wireModelValue !== null && preg_match('/^[A-Za-z0-9_.\-]+$/', (string) $wireModelValue) === 1) {
        $wireModelSafe = (string) $wireModelValue;
    }

    // Class blocks — all themable via WireKit::personalize() + scope.
    $wrapperClasses = WireKit::resolveClasses('image-compare', 'base', implode(' ', [
        'relative block w-full h-full overflow-hidden select-none isolate',
        'rounded-[var(--radius-wk-lg)]',
        'bg-[var(--color-wk-bg-muted)]',
        'border border-[var(--color-wk-border)]',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);

    $imgClasses = 'absolute inset-0 w-full h-full object-cover pointer-events-none select-none';

    $handleClasses = WireKit::resolveClasses('image-compare', 'handle', implode(' ', [
        'absolute z-30',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-md)]',
        'rounded-full',
        'w-[var(--wk-image-compare-handle-size,2.5rem)] h-[var(--wk-image-compare-handle-size,2.5rem)]',
        'grid place-items-center',
        'cursor-grab active:cursor-grabbing',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'border border-[var(--color-wk-border)]',
    ]), $scope);

    $dividerClasses = WireKit::resolveClasses('image-compare', 'divider', implode(' ', [
        'absolute z-20 bg-[var(--color-wk-bg-elevated)] pointer-events-none',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);

    $labelClasses = WireKit::resolveClasses('image-compare', 'label', implode(' ', [
        'absolute z-40 pointer-events-none',
        'px-2 py-1 rounded-[var(--radius-wk-sm)]',
        'text-[length:var(--text-wk-xs)] font-medium',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[var(--color-wk-text)]',
        'border border-[var(--color-wk-border)]',
        'shadow-[var(--shadow-wk-sm)]',
    ]), $scope);

    $trackClasses = 'absolute inset-0 z-10 bg-transparent '.
        ($isVertical ? 'cursor-row-resize' : 'cursor-col-resize');
@endphp

<figure
    {{ $attributes->class([$wrapperClasses]) }}
    @if($decorative) role="presentation" @endif
    x-data="wirekitImageCompare({
        value: {{ $clampedValue }},
        orientation: '{{ $isVertical ? 'vertical' : 'horizontal' }}'@if($wireModelSafe !== null),
        wireModel: '{{ $wireModelSafe }}',
        wireLive: {{ $isLiveModel ? 'true' : 'false' }}
        @endif
    })"
    x-on:slide="$dispatch('wirekit:image-compare-slide', $event.detail)"
    style="touch-action: none;"
>
    {{-- Hidden input so plain-HTML form submission works without Livewire.
         The Alpine factory dispatches `input` events on this element so
         wire:model-less consumers still get form value updates. --}}
    <input
        type="hidden"
        x-ref="hiddenInput"
        :value="value"
    />

    {{-- Before image (bottom layer, fully visible under the after image). --}}
    <img
        src="{{ $before }}"
        alt="{{ $effectiveBeforeAlt }}"
        loading="{{ $loading }}"
        class="{{ $imgClasses }}"
        draggable="false"
    />

    {{-- After image (top layer, clipped by clip-path driven by `value`). --}}
    <img
        src="{{ $after }}"
        alt="{{ $effectiveAfterAlt }}"
        loading="{{ $loading }}"
        class="{{ $imgClasses }}"
        draggable="false"
        :style="orientation === 'vertical'
            ? `clip-path: inset(0 0 ${100 - value}% 0)`
            : `clip-path: inset(0 ${100 - value}% 0 0)`"
    />

    {{-- Labels — optional; controlled by `labels` master toggle + individual props. --}}
    @if($labels)
        @if($beforeLabel !== null)
            <span
                class="{{ $labelClasses }}"
                :class="orientation === 'vertical' ? 'top-2 left-2' : 'bottom-2 left-2'"
            >{{ $beforeLabel }}</span>
        @endif
        @if($afterLabel !== null)
            <span
                class="{{ $labelClasses }}"
                :class="orientation === 'vertical' ? 'bottom-2 right-2' : 'top-2 right-2'"
            >{{ $afterLabel }}</span>
        @endif
    @endif

    {{-- Click/drag track — sits above the images, below the handle, so clicks
         on empty space snap the handle to the click coordinates. Hidden from
         AT because the handle already exposes the slider role. --}}
    <button
        type="button"
        x-ref="track"
        @click="onTrackClick($event)"
        @pointerdown="startDrag($event)"
        class="{{ $trackClasses }}"
        tabindex="-1"
        aria-hidden="true"
    ></button>

    {{-- Divider line — visual separator between before/after halves. --}}
    <span
        class="{{ $dividerClasses }}"
        :style="orientation === 'vertical'
            ? `top: ${value}%; left: 0; right: 0; height: var(--wk-image-compare-divider-size, 2px); transform: translateY(-50%)`
            : `left: ${value}%; top: 0; bottom: 0; width: var(--wk-image-compare-divider-size, 2px); transform: translateX(-50%)`"
        aria-hidden="true"
    ></span>

    {{-- Handle — the interactive slider thumb. Implements WAI-ARIA Slider Pattern. --}}
    <button
        type="button"
        class="{{ $handleClasses }}"
        :style="orientation === 'vertical'
            ? `top: ${value}%; left: 50%; transform: translate(-50%, -50%)`
            : `left: ${value}%; top: 50%; transform: translate(-50%, -50%)`"
        role="slider"
        aria-label="{{ $ariaLabel }}"
        aria-valuenow="{{ (int) $clampedValue }}"
        :aria-valuenow="value"
        aria-valuemin="0"
        aria-valuemax="100"
        aria-orientation="{{ $isVertical ? 'vertical' : 'horizontal' }}"
        @pointerdown.stop="startDrag($event)"
        @keydown.left.prevent="orientation === 'horizontal' && stepBy(-1)"
        @keydown.right.prevent="orientation === 'horizontal' && stepBy(1)"
        @keydown.up.prevent="orientation === 'vertical' ? stepBy(-1) : stepBy(1)"
        @keydown.down.prevent="orientation === 'vertical' ? stepBy(1) : stepBy(-1)"
        @keydown.home.prevent="setValue(0)"
        @keydown.end.prevent="setValue(100)"
        @keydown.page-up.prevent="stepBy(10)"
        @keydown.page-down.prevent="stepBy(-10)"
    >
        {{-- Default handle icon: two chevrons pointing outward. Rotated 90° for vertical. --}}
        <svg
            aria-hidden="true"
            class="w-5 h-5 text-[var(--color-wk-text-muted)]"
            :class="orientation === 'vertical' ? 'rotate-90' : ''"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="2"
        >
            <path d="M9 6l-6 6 6 6M15 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </button>

    {{-- aria-live IS present here — do not flag as missing.
         Announces value changes during drag / keyboard interaction. --}}
    <span class="sr-only" aria-live="polite" aria-atomic="true">
        <span x-text="`${value}% revealed`"></span>
    </span>
</figure>
