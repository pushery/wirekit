{{-- optimistic-ui: n/a — sub-component
     The panel of one tooltip, teleported to the overlay root: the tooltip renders it unless it
     draws into the page's shared panel (`shared`). Included with the tooltip's variables. --}}
    {{-- Tooltip panel, teleported out of the document flow.

         Left inside a `<x-wirekit::scroll-area fade="…">`, a tooltip would be cut
         off at the scroll area's edge. The panel is `position: fixed` and so
         escapes overflow clipping, but `fade` works by `mask-image`, and a mask
         applies to the whole rendered subtree, fixed descendants included. The
         stylesheet's escape hatch (`.wk-scroll-fade…:focus-within { mask-image:
         none }`) cannot fire here: a tooltip opens on hover, and focus-within
         does not see a hover.

         Teleporting settles it at the root rather than widening that hatch — the
         panel leaves the masked subtree entirely, which also settles every
         other stacking-context case (a clipping card, a transformed ancestor,
         an `isolation: isolate` wrapper) in one move.

         `$refs` survive the teleport: Alpine registers the ref in the original
         scope, so the positioning code that reads `this.$refs.tooltip` is
         unaffected. --}}
    <template x-teleport="#wk-overlay-root">
    <div
        {{-- The morph key, and it is what makes writing the id below safe.
             Livewire resolves a node's morph identity as `wire:id`, then
             `wire:key`, then `el.id`. With neither of the first two present the id
             was the key — and this panel mints a fresh one on every render, so two
             renders could never agree by construction. A key mismatch does not
             patch, it swaps: `swapElements` inserts a native `cloneNode(true)`,
             which copies attributes and children and no Alpine expandos.
             The replacement therefore arrives with no `_x_dataStack`, and Alpine's
             scope walk climbs `parentNode` only — from inside the overlay root,
             which hangs off <body> in no `x-data`, that walk finds nothing. So
             `x-show="open"` resolved `open` on the global object, where it is
             `window.open`, a real function; Alpine auto-invokes a function-valued
             expression and applies it with the scope proxy as the receiver, which
             the brand check on a global operation refuses. The `Illegal
             invocation` that raises is re-thrown from a timer, so it surfaces one
             tick later as an uncaught page error whose stack names nothing on the
             page, so it does not read as a tooltip bug at all. No interaction is
             needed to trigger it: `x-show` is
             evaluated the moment the clone is initialized.
             A static value on purpose, and it must never become the id. The morph
             patches a teleported node against its own counterpart, one to one, and
             never looks a key up among siblings — so many tooltips on one page do
             not compete — while the per-instance value is exactly the one that
             cannot agree across two renders. The id stays per-instance for the
             opposite reason: `aria-describedby` on the trigger has to name this
             panel. --}}
        wire:key="wk-tooltip-panel"
        x-ref="tooltip"
        {{-- The panel holds itself open while the pointer rests on it — the "Hoverable"
             half of WCAG 1.4.13. The trigger's `mouseleave` arms a hide on the
             `delayHide` timer; arriving here cancels it, leaving here arms it again.
             Both handlers are the component's own, shared with the trigger, so there is
             one hide timer for the pair rather than two racing each other. --}}
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
        id="{{ $tooltipId }}"
        {{-- `wk-tooltip-panel` is the marker the stylesheet's forced-colors rule selects, outside
             the resolved classes so a project that restyles the panel keeps its edge there. --}}
        class="wk-tooltip-panel {{ $tooltipClasses }}"
    >
        {{-- The words of the cut text in the trigger, on a line of their own above the tooltip's,
             written by the script while that text is cut and empty while it fits. A screen reader
             reads them in the trigger already, so the description leaves them out. --}}
        @if($tipCutLead)
            <span data-wk-tooltip-cut-words class="block" aria-hidden="true"></span>
        @endif
        {{-- Rich content slot or plain text, and the shortcut after it: beside the text, and on a
             line of its own under content, which is lines itself. A single-key shortcut goes with
             its line while single keys are off. --}}
        @if($hasContent)
            {{ $content }}
            @if($tipShortcutKeys !== null)
                <span class="block mt-[var(--space-wk-xs)]"@if($tipShortcutKeys['single']) data-wk-kbd-single=""@endif><x-wirekit::kbd :shortcut="$tipShortcut" size="sm" :scope="$scope" /></span>
            @endif
        @else
            {{ $text }}
            @if($tipShortcutKeys !== null)
                <x-wirekit::kbd :shortcut="$tipShortcut" size="sm" class="ms-[var(--space-wk-sm)]" :scope="$scope" />
            @endif
        @endif
    </div>
    </template>
