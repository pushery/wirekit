{{-- optimistic-ui: n/a — sub-component
     The panel of a cut text's tooltip, which `text` with `truncate="tooltip"` renders into its
     element after the slot. It takes its words from the element when it shows
     (resources/js/components/tooltip.js), and it is hidden from assistive technology, which reads
     the whole text in the element already. --}}
<template x-teleport="#wk-overlay-root">
    <div
        x-ref="tooltip"
        x-on:mouseenter="mouseenter()"
        x-on:mouseleave="mouseleave()"
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        role="tooltip"
        aria-hidden="true"
        {{-- The tooltip's marker, outside the resolved classes as on the tooltip itself: under
             forced colors the panel's fill is repainted, and the marker gives it an edge. --}}
        class="wk-tooltip-panel {{ $tipClasses }}"
    ></div>
</template>
