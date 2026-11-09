{{-- optimistic-ui: n/a — client-only
     Not a component but an @include of `sidebar`. Its state is a width the reader sets, which no
     server owns, so there is nothing to anticipate and nothing to roll back. --}}
{{-- The handle that sets a sidebar's width.

     The resizable handle's markup and look: the same grip, the same two step controls and the
     same keyboard model, styled by the same rules, which is why the box around it carries the
     two attributes those rules key on. The behavior is `wirekitSidebarWidth`
     (resources/js/components/sidebar-width.js), which measures in lengths rather than shares.

     Placed on the edge that faces the content, half of it over the edge, and gone while the rail
     is folded, where there is no width to set. Its name is the sidebar's: the Window Splitter
     pattern names a splitter after the pane it sizes.

     Included from `sidebar.blade.php` and nowhere else; every variable it reads comes from that
     scope. --}}
<div
    data-wk-resizable
    data-wk-direction="horizontal"
    data-wk-sidebar-width
    class="absolute inset-y-0 {{ $side === 'end' ? '-start-0.5' : '-end-0.5' }} z-[1] flex w-1 group-data-[collapsed]/wk-sidebar:hidden"
>
    <div
        data-wk-resizable-handle
        @if($handleGrip === 'hover') data-wk-grip="hover" @endif
        x-data="wirekitSidebarWidth({ width: {{ \Pushery\WireKit\Support\AlpinePayload::from($width) }}, min: {{ \Pushery\WireKit\Support\AlpinePayload::from($minWidth) }}, max: {{ \Pushery\WireKit\Support\AlpinePayload::from($maxWidth) }}, side: {{ \Pushery\WireKit\Support\AlpinePayload::from($side) }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }}, persistDriver: {{ \Pushery\WireKit\Support\AlpinePayload::from($persistDriver) }} })"
        role="separator"
        aria-orientation="vertical"
        tabindex="0"
        aria-label="{{ $widthHandleLabel }}"
        x-on:pointerdown="onPointerDown($event)"
        x-on:pointermove.passive="onPointerMove($event)"
        x-on:pointerup="onPointerUp($event)"
        x-on:pointercancel="onPointerUp($event)"
        x-on:lostpointercapture="onPointerUp($event)"
        x-on:keydown="onKeyDown($event)"
        x-on:focus="onFocus()"
        x-on:blur="onBlur()"
    >
        <span data-wk-resizable-grip aria-hidden="true"></span>
        @wirekitLast($handleSteps)
            {{-- Two step controls beside the grip, for a pointer that cannot drag (WCAG 2.5.7). Each
                 points the way it moves the line, left and right of it. Spans hidden from assistive
                 technology, as on the resizable handle: the handle is the focusable control with the
                 keyboard model, and a control inside it would be nested interactive content. --}}
            <span class="select-none" data-wk-resizable-step="-1" aria-hidden="true" x-on:click="stepFromControl(-1)"></span>
            <span class="select-none" data-wk-resizable-step="1" aria-hidden="true" x-on:click="stepFromControl(1)"></span>
        @endwirekitLast
    </div>
</div>
