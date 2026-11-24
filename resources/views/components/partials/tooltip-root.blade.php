{{-- optimistic-ui: n/a — sub-component
     The root of a tooltip with a component of its own: the wrapper with its Alpine scope, the
     trigger, and its panel or, drawing into the page's shared panel, its words. The tooltip renders
     it unless its trigger is one element the page's host drives. Included with the tooltip's
     variables, its offset and delays read once there as numbers. --}}
{{-- Tooltip wrapper — handles hover, focus, touch, and keyboard events --}}
<{{ $as }} data-wk-prose-skip
    @if($disabled) data-wk-tooltip-disabled="true" @endif
    @if($surface === 'elevated') data-wk-tooltip-surface="elevated" @endif
    @if($sharedPanel) data-wk-tooltip-panel-class="{{ \Pushery\WireKit\Support\CompactClasses::map('wk-tooltip-panel '.$tooltipClasses) }}" @endif
    @if($tipWordsAttribute !== null) data-wk-tip-words="{{ $tipWordsAttribute }}" @endif
    @if($lazyPanel) data-wk-lazy-panel-host @endif
    x-data="wirekitTooltip({
        placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }},
        offset: {{ (int) $tipOffset }},
        delayShow: {{ (int) $tipDelayShow }},
        delayHide: {{ (int) $tipDelayHide }}@if($sharedPanel),
        shared: true,
        textFrom: {{ \Pushery\WireKit\Support\AlpinePayload::string($describedById) }}@endif
    })"
    x-on:mouseenter="mouseenter()"
    x-on:mouseleave="mouseleave()"
    x-on:focusin="focusin($event)"
    x-on:focusout="focusout()"
    x-on:pointerdown="pointerdown($event)"
    x-on:pointerup="pointerup($event)"
    x-on:pointerleave="pointerleave($event)"
    {{-- Escape listens on the window, and it has to.
         A tooltip is opened by pointing at its trigger, and a pointer moves no focus —
         so a handler bound here would only fire once something inside had been tabbed
         to, the one case that needs it least. WCAG 1.4.13 requires
         content shown on hover or focus to be dismissible without moving the pointer or
         the focus, and Escape is the mechanism it names.
         In the capture phase, because a modal or a drawer around the reader reads the
         key on the document and on the window too, and a tooltip on screen marks the
         press it hides on: the overlay leaves a marked press alone.
         Deliberately not guarded on `open`, unlike the hover card's counterpart: a
         tooltip opens on a `delayShow` timer, so the key regularly arrives while the
         panel is still pending, and a guard on `open` would let it appear a moment
         after the Escape that was meant to answer it. `keydownEscape()` clears the
         pending show as well as the open panel, and on a tooltip with nothing to
         dismiss it costs four no-op `clearTimeout` calls. --}}
    x-on:keydown.escape.window.capture="keydownEscape($event)"
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['relative inline-block'])) }}
>
    {{-- Trigger element — linked to tooltip via aria-describedby. `data-wk-tooltip-trigger` is what
         the stylesheet stands a control in the slot at the top of the trigger's line by, so the
         trigger ends where the control ends. --}}
    {{-- The description sits on this wrapper, and a wrapper `<div>` is not what a reader
         lands on. With `focusable-trigger` the wrapper takes the tab stop and carries the
         name itself, which works. Without it — the documented shape for wrapping a caller's
         own `<button>` — the button is what gets focused, and it has no `aria-describedby`:
         the tooltip is announced to nobody.

         Alpine puts it on the first focusable descendant at init instead, which is where the
         reader actually arrives, and leaves it here when there is none so the wrapper case
         is unchanged. Done in JS rather than in Blade because the trigger is the CALLER's
         markup — this template never sees the element it needs to annotate. --}}
    <{{ $as }} data-wk-prose-skip data-wk-tooltip-trigger x-ref="trigger" @if($triggerDescribes) data-wk-tooltip-describedby="{{ $describedById }}" aria-describedby="{{ $describedById }}" @endif @if($focusableTrigger) tabindex="0" @if($triggerIsButton) role="button" @if($triggerExplainsDisabled) aria-disabled="true" @endif {{ $triggerName }} x-on:keydown.enter="activate($event)" x-on:keydown.space="activate($event)" @endif class="rounded-[var(--radius-wk-sm)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]" @endif>
        {{ $slot }}
    </{{ $as }}>

    {{-- The panel of its own, teleported to the overlay root, or, drawing into the page's shared
         panel, the words the trigger is described by, hidden, where none is named. A lazy panel
         waits in a template, and its words stand beside it. Includes and an echo rather than a
         conditional, which Livewire would mark at both ends. --}}
    @includeWhen(! $sharedPanel, 'wirekit::components.partials.tooltip-panel')
    @includeWhen($lazyPanel && $triggerDescribes, 'wirekit::components.partials.tooltip-words')
    {{ $ownWords }}
</{{ $as }}>