{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the range is sent when the gesture ENDS —
     pointerup for a drag, each keypress for the keyboard (the boundary is
     an event, never a timer). The method receives the pair `[min, max]`,
     because the pair is one value: a rollback restores both, and an untouched
     handle's restore is a no-op.

     The optimistic scope nests INSIDE the slider and binds to the tuple
     `['minVal', 'maxVal']`. --}}
@props([
    // Livewire method to call optimistically. It receives the full range as
    // [min, max]. Absent -> this component renders exactly as it did before,
    // down to the byte.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    'label' => null,
    // `error` was undeclared while `label` was not, so `:error` on this control
    // landed in the attribute bag and rendered as a stray HTML attribute — a validation
    // message the developer wrote, silently not shown, on a control that is part of forms.
    'error' => null,
    'hint' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'minValue' => null,
    'maxValue' => null,
    'showValues' => null,
    // Spoken value per stop, e.g. [0 => 'Free', 100 => 'Enterprise']. Each thumb
    // announces its OWN value through it — the dual-handle equivalent of the same
    // prop on the single-value slider component. (Written without the tag syntax
    // on purpose: Blade compiles component tags even inside comments.) Without it
    // a screen reader reads the bare number, which is right for a price and wrong
    // for a tier.
    'valueTextMap' => null,
    // Disable the whole control: `aria-disabled` on both thumbs, native `disabled`
    // on the two hidden inputs (so a submitted form carries no range), no pointer
    // or keyboard handlers, and the group dims via --opacity-wk-disabled.
    // It was DOCUMENTED — a props-table row and an accessibility bullet — while
    // undeclared here, so the flag landed in the attribute bag and rendered on the
    // wrapper `<div>`, where `disabled` means nothing: the control looked untouched
    // and stayed fully operable by pointer AND keyboard.
    'disabled' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;

    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `disabled="false"` would mean the opposite of what the call site reads as.
    // Normalized against the prop's own default so a cast never turns the control off.
    $disabled = BooleanProp::from($disabled, false);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('range-slider', $attributes->getAttributes());

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'range-slider-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);

    // Compute defaults: minValue defaults to min, maxValue defaults to max
    $initialMin = $minValue ?? $min;
    $initialMax = $maxValue ?? $max;

    // Spoken-value map, string-keyed so the JS lookup matches (Alpine compares the
    // numeric value against object keys, which are always strings).
    $rangeValueTextMap = [];
    if (is_array($valueTextMap)) {
        foreach ($valueTextMap as $mValue => $mLabel) {
            $rangeValueTextMap[(string) $mValue] = (string) $mLabel;
        }
    }
    $bindRangeValueText = $rangeValueTextMap !== [];

    // Per-thumb value badge ("bubble") visibility.
    //
    // Prop default `null` defers to the config fallback so a site can
    // hide the badges globally with one line in config/wirekit.php:
    //
    //     'range-slider' => ['show_values' => false],
    //
    // The two visible bubbles above each thumb show the live numeric
    // value. The bounds-labels under the track and the sr-only
    // aria-live region are kept regardless — they carry the
    // accessible name for the slider and remain visible / audible
    // even when the bubbles are off.
    $resolvedShowValues = $showValues ?? config('wirekit.components.range-slider.show_values', true);

    $wrapperClasses = WireKit::resolveClasses('range-slider', 'base', implode(' ', [
        'space-y-2',
        'font-[family-name:var(--font-wk-sans)]',
        // Usability floor. The track is a full-width block with NO intrinsic
        // width of its own, so in any shrink-to-fit context — a flex/grid auto
        // item, a table cell, or a `width: fit-content` wrapper — the slider
        // collapses to the width of its widest text row (the two bound labels,
        // e.g. "0 … 10"), i.e. ~60px: far too narrow to drag. A min-width keeps
        // it operable everywhere while still expanding to 100% in normal flow.
        // A dual-thumb track needs room for two handles plus their value bubbles without
        // the bubbles colliding on contact, which is what the floor below is sized for.
        // ⚠️ `min(16rem, 100%)`, NOT a bare `16rem`. The floor keeps a shrink-to-fit
        // context (a flex or grid auto item, a table cell, a fit-content wrapper) from
        // collapsing the track to a few unusable pixels — but `min-width` is a HARD floor,
        // so a bare value does NOT "shrink to 100% of a narrower parent", which is what the
        // comment here used to claim. In the documentation preview column, roughly 280px
        // wide at phone width, 256px of track plus 32px of card padding is 288px: the
        // control pushed past its own container. `min()` gives the intended behavior —
        // 16rem where there is room, the parent's width where there is not.
        //
        // (The same comment also argued for a 20rem floor while shipping 16rem. Whichever
        // of the two was meant, one of them was wrong in the source of truth; 16rem is what
        // shipped and what every preview is drawn against, so that is what stays.)
        'min-w-[min(16rem,100%)]',
    ]), $scope);

    // wire:model integration (Strategy B — split-min/split-max):
    //
    // Livewire only watches wire:model on <input> / <select> /
    // <textarea> — not on a generic <div> attribute bag. We extract
    // any wire:model* directive from the component's outer attributes
    // and re-emit it on the two hidden inputs as
    // `wire:model{modifiers}="prop.min"` / `wire:model{modifiers}="prop.max"`.
    //
    // The developer side then declares:
    //
    //     public array $priceRange = ['min' => 20, 'max' => 80];
    //
    // And gets live two-way binding without any inline JS wiring.
    //
    // Modifiers (.live, .lazy, .debounce.500ms, .blur) flow through
    // verbatim — Livewire parses them on the input element the same
    // way it would on the component tag.
    $wireModel = null;
    $wireModelKey = null;
    foreach ($attributes->getAttributes() as $key => $val) {
        if (! is_string($key) || ! str_starts_with($key, 'wire:model')) {
            continue;
        }
        $wireModel = $key;          // e.g. 'wire:model' or 'wire:model.live'
        $wireModelKey = (string) $val; // e.g. 'priceRange'
        break;
    }
    // Strip wire:model* from the outer attribute bag so it doesn't
    // double-render on the wrapper <div>. Livewire would ignore it
    // there anyway, but the duplicate read costs perf + clutters the
    // DOM.
    $attributes = $wireModel !== null ? $attributes->except($wireModel) : $attributes;

    // Thumb classes — shared for both handles
    $thumbClasses = implode(' ', [
        'absolute top-1/2 -translate-y-1/2 -translate-x-1/2',
        // wk-range-thumb owns the thumb SIZE (20px default, 28px on coarse
        // pointers) + touch-action:none, shipped in dist/wirekit.css. Sizing
        // there (not via Tailwind width/height utilities) keeps the
        // coarse-pointer override deterministic regardless of stylesheet
        // order, and works in apps whose Tailwind build never compiled the
        // larger fixed-size utility.
        'wk-range-thumb',
        'rounded-full',
        'bg-[var(--color-wk-accent)]',
        'border-2 border-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-sm)]',
        // The thumb sits inside the wrapper that carries the disabled cursor, and a
        // `cursor-pointer` here would win on the one element the pointer is actually
        // over — the handle would still invite a drag it can no longer start.
        $disabled ? 'cursor-not-allowed' : 'cursor-pointer',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-shadow duration-[var(--transition-wk-duration)]',
    ]);

    // Snap-to-step tick marks: only for discrete sliders (step > 1) where the
    // step count is small enough to read (< 20 ticks) — beyond that the ticks
    // become visual noise. Decorative; positions are step boundaries as
    // percentages along the track.
    $tickPercents = [];
    if ($step > 1 && ($max - $min) > 0) {
        $segments = ($max - $min) / $step;
        if ($segments >= 1 && $segments < 20) {
            for ($i = 0; $i <= $segments; $i++) {
                $tickPercents[] = round(($i / $segments) * 100, 4);
            }
        }
    }

    // Accessibility — a caller's aria-* must reach the focusable sliders /
    // the group, NOT the role-less outer wrapper. A dual-thumb slider is a
    // group of two `role="slider"` thumbs (WAI-ARIA range pattern): the
    // wrapper carries `role="group"` + an accessible name, and each thumb's
    // name embeds that context ("<label> minimum" / "<label> maximum").
    //
    // The group name comes from the visible $label (wired via
    // aria-labelledby) or, failing that, a caller-supplied aria-label.
    $callerAriaLabel = $attributes->get('aria-label');
    $groupLabel = $label ?? $callerAriaLabel;
    $minThumbLabel = $groupLabel !== null ? trim((string) $groupLabel).' minimum' : 'Minimum';
    $maxThumbLabel = $groupLabel !== null ? trim((string) $groupLabel).' maximum' : 'Maximum';

    // Description targets the focusable thumbs (announced on focus): the
    // visible $hint plus any caller-supplied aria-describedby. Routing it to
    // the thumbs (and off the wrapper) lands it on the element the user
    // actually focuses.
    $callerDescribedBy = $attributes->get('aria-describedby');
    $thumbDescribedBy = trim(($hint !== null ? $id.'-hint' : '').' '.((string) ($callerDescribedBy ?? '')));
    $thumbDescribedBy = $thumbDescribedBy !== '' ? $thumbDescribedBy : null;

    // aria-describedby has been routed to the thumbs — drop it from the
    // wrapper bag so it doesn't also render on the (now group) wrapper.
    // `name` is dropped for the same reason: the two hidden inputs render it
    // explicitly, each with its own bracketed key, and left in the bag it also lands
    // on the wrapper, where `name` is not a valid attribute on a grouping div.
    $attributes = $attributes->except(['aria-describedby', 'name']);
@endphp

@php
    // The optimistic layer NESTS INSIDE the slider's own scope, and the
    // direction is not interchangeable: a nested Alpine component's method
    // reads and writes its parent's properties through `this`, never the other
    // way around. So it has to be the child to reach `minVal`/`maxVal`, and the
    // thumbs have to be inside it to reach its `run()`.
    //
    // The bind is a TUPLE: a range is one value, not two — snapshot the
    // pair, restore the pair, and let an unchanged half be a no-op. A tuple of
    // plain property names rather than a getter pair on the component, because
    // that does not depend on how the two scopes get merged.
    // `$disabled`, not `$attributes->has('disabled')`: the flag is a declared prop
    // now, so it never reaches the attribute bag and the bag test would read false
    // for every disabled slider — arming an optimistic layer on a control that
    // cannot be operated.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => ['minVal', 'maxVal'],
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second commit while one is in flight would resolve by whichever
        // answer arrives last — network timing, which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);
@endphp

<div
    role="group"
    @if($label) aria-labelledby="{{ $id }}-label" @endif
    {{-- On the GROUP rather than on either thumb: the message is about the range, and a
         thumb-level describedby would read it out on both ends of it. --}}
    @if($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif($hint) aria-describedby="{{ $id }}-hint" @endif
    {{-- On the GROUP as well as on the thumbs: a reader who lands on the group before
         reaching either handle has to hear that the range is not editable. --}}
    @if($disabled) aria-disabled="true" @endif
    {{-- The dim is a wrapper class rather than a `disabled:` variant, because nothing
         here is a native form control the variant could hang off — the thumbs are
         `div[role="slider"]`, and the two inputs that DO carry `disabled` are hidden. --}}
    {{ $attributes->class([$wrapperClasses, $disabled ? 'opacity-[var(--opacity-wk-disabled)] cursor-not-allowed' : '']) }}
>
    @if($label)
        <x-wirekit::label id="{{ $id }}-label">{{ $label }}</x-wirekit::label>
    @endif

    {{-- Alpine logic inlined (no wirekit.js dependency needed).
         Handles dual-thumb drag, keyboard stepping, and percent calculation. --}}
    <div
        x-data="wirekitRangeSlider({ min: {{ $min }}, max: {{ $max }}, step: {{ $step }}, minValue: {{ $initialMin }}, maxValue: {{ $initialMax }}, marksMap: {{ \Pushery\WireKit\Support\AlpinePayload::from((object) $rangeValueTextMap) }} })"
        x-effect="remeasureOnValueChange()"
        class="relative"
        @if($resolvedShowValues)
            style="padding-top: 2rem;"
        @endif
    >
        @if($optimisticConfig)
            {{-- `display: contents` — this element's `relative` is the track's
                 containing block, and a real box here would move the thumbs. --}}
            <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
        @endif

        {{-- Hidden inputs for form submission. When the caller passed
             wire:model* on the component tag, also bind the matching
             nested-key directive to each input so Livewire picks up
             value updates as the user drags. --}}
        {{-- Static value as well as the bound one: the field is empty until Alpine
             boots, and a form submitted in that window sends nothing while the
             visible control already shows the value. Both come from the same PHP
             expression that feeds the factory, so they cannot drift. --}}
        <input
            type="hidden"
            name="{{ $name }}[min]"
            value="{{ $initialMin }}"
            {{-- Native `disabled`, so a disabled range is omitted from the submitted
                 form the way every other disabled control is — the value the user
                 cannot change is not one the server should receive. --}}
            @disabled($disabled)
            :value="minVal"
            @if($wireModel) {{ $wireModel }}="{{ $wireModelKey }}.min" @endif
            x-ref="minInput"
        />
        <input
            type="hidden"
            name="{{ $name }}[max]"
            value="{{ $initialMax }}"
            {{-- Same reason as the min input above. --}}
            @disabled($disabled)
            :value="maxVal"
            @if($wireModel) {{ $wireModel }}="{{ $wireModelKey }}.max" @endif
            x-ref="maxInput"
        />

        {{-- Track + thumbs.

             The thumbs MUST live INSIDE the track div so their
             `top-1/2 -translate-y-1/2` resolves relative to the
             8px-tall track (centering the thumb vertically ON the
             track line). Pre-fix the thumbs were siblings of the
             track in the outer wrapper, so `top-1/2` resolved to the
             middle of the full wrapper height (track + tooltip + edge
             labels + sr-only region) and the thumbs visually dropped
             BELOW the track line instead of sitting centered on it.

             The inline `style="overflow: visible;"` on the track is
             load-bearing: the thumbs extend above + below the 8px
             track and the tooltip-style value-badge sits even further
             above the thumb, so any clipping here would hide them.
             We use an inline style (not a Tailwind utility class) so
             the drift suite's reverse-diff scanner can't mis-trace
             the class name from this comment block.
             --}}
        <div class="relative h-2 rounded-full bg-[var(--color-wk-bg-muted)]" style="overflow: visible;" x-ref="track">
            {{-- Snap-to-step tick marks (decorative) — only for discrete
                 sliders with a readable number of steps. --}}
            @foreach($tickPercents as $tickPercent)
                <span aria-hidden="true" class="absolute top-1/2 -translate-y-1/2 h-2 w-px bg-[var(--color-wk-border)]" style="left: {{ $tickPercent }}%;"></span>
            @endforeach

            {{-- Active range fill --}}
            <div
                class="absolute h-full rounded-full bg-[var(--color-wk-accent)]"
                :style="rangeFillStyle()"
            ></div>

            {{-- Min thumb. Static aria-valuenow / aria-valuemax mirror
                 the initial state so axe-core's pre-Alpine-init scan
                 sees a complete slider; Alpine then overrides
                 reactively. The current value bubbles ABOVE the thumb
                 as a tooltip-style badge that tracks the handle
                 horizontally (-translate-x-1/2 centers it on the thumb). --}}
            <div
                class="{{ $thumbClasses }} z-10"
                :style="minThumbStyle()"
                {{-- A disabled thumb leaves the tab order and says so. `aria-disabled`
                     rather than removing `role="slider"`: the handle is still a slider,
                     it is a slider that cannot be moved, and that is what a reader has
                     to hear when they arrive on it from the group. --}}
                tabindex="{{ $disabled ? '-1' : '0' }}"
                @if($disabled) aria-disabled="true" @endif
                role="slider"
                aria-label="{{ $minThumbLabel }}"
                @if($thumbDescribedBy) aria-describedby="{{ $thumbDescribedBy }}" @endif
                aria-valuenow="{{ $initialMin }}"
                :aria-valuenow="minVal"
                @if($bindRangeValueText) :aria-valuetext="valueTextFor(minVal)" @endif
                aria-valuemin="{{ $min }}"
                {{-- The ceiling this handle can actually hold, not the track's end.
                     Every path that moves the lower handle — `_setMin`, and through
                     it the arrows, the page keys, End and the pointer drag — stops
                     at `maxVal - step`, so announcing `maxVal` describes a value the
                     slider silently refuses. A reader hears "maximum 100", arrows to
                     90, and the handle stops while the announcement stays put: a
                     control that reads as hung when it is in fact obeying its own
                     rule. This is the one place the component says something untrue
                     about itself, and it is untrue in the direction a reader cannot
                     check.

                     `maxVal - step` is EXACTLY reachable and not merely an upper
                     estimate — the clamp is `Math.min(value, maxVal - step)`, so
                     that number is the value the handle lands on, whether or not it
                     sits on the step grid. `max()` against the floor because nothing
                     clamps the initial values at mount: `:max-value` set nearer the
                     floor than one step would otherwise put valuemax below valuemin.

                     Static AND bound, because they are two different moments. The
                     static one is what a scan sees before Alpine builds the scope;
                     leaving it uncorrected would make the rendered document and the
                     hydrated one disagree. --}}
                aria-valuemax="{{ max($min, $initialMax - $step) }}"
                :aria-valuemax="minThumbCeiling"
                {{-- BOTH thumbs report the same pending state, because
                     a range is one value and one value is one flight. Putting it
                     on the thumb that happened to move would make the state
                     depend on which end the user grabbed. The `role="group"`
                     wrapper cannot carry it either — that element sits OUTSIDE
                     this layer's Alpine scope, so a binding placed there has
                     nothing to read. The scope is the whole obstacle: a role on
                     a `display: contents` element IS exposed in the platform
                     accessibility tree together with its children, measured in
                     Blink, which is why the segmented control puts its
                     `radiogroup` on exactly that shape. And a wrapper here would
                     carry no role of its own, so the pending state belongs on
                     the sliders a reader actually lands on. --}}
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                {{-- The APG slider keyboard model, in full. Up/Down, Home and End
                     are REQUIRED keys of that pattern, not extras: a reader who
                     presses Home to reach the low end, or ArrowUp out of the
                     habit every vertical slider teaches, has to move the handle.
                     Page keys step ten at a time — `stepMin` takes a multiplier
                     on the step, so they share its clamp rather than repeating it.
                     Home/End land on the ends of THIS handle's travel, which for
                     the lower handle stops one step below the upper one. --}}
                @unless($disabled)
                    @keydown.arrow-right.prevent="stepMin(1)"
                    @keydown.arrow-left.prevent="stepMin(-1)"
                    @keydown.arrow-up.prevent="stepMin(1)"
                    @keydown.arrow-down.prevent="stepMin(-1)"
                    @keydown.page-up.prevent="stepMin(10)"
                    @keydown.page-down.prevent="stepMin(-10)"
                    @keydown.home.prevent="jumpMin(false)"
                    @keydown.end.prevent="jumpMin(true)"
                    @pointerdown="startDrag('min', $event)"
                @endunless
            >
                @if($resolvedShowValues)
                    {{-- Edge-flush clamp. The bubble's containing block is the
                         thumb wrapper (a thumb-sized box centered on the
                         handle), so `left: ${minPercent}%` slides the bubble's
                         anchor across the THUMB's own width — 0% → the thumb's
                         outer-left edge, 100% → its outer-right edge, 50% → center
                         — and `translateX(-${minPercent}%)` aligns the bubble's own
                         box the same way. Net: at the far-left the bubble closes
                         flush with the circle's outer edge (not the track line a
                         half-thumb further in), at the far-right flush on the right,
                         centered in the middle. Anchoring on the thumb box auto-
                         scales with the coarse-pointer thumb size. The transform
                         carries no transition (only opacity does), so it snaps. --}}
                    <span
                        aria-hidden="true"
                        x-ref="minBubble"
                        :class="[_merged ? 'opacity-0' : 'opacity-100', _ready ? 'transition-opacity duration-[var(--transition-wk-duration)]' : '']"
                        :style="minBadgeStyle()"
                        class="absolute -top-8 rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] border border-[var(--color-wk-border)] px-[var(--padding-wk-x-sm)] py-0.5 text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)] tabular-nums whitespace-nowrap shadow-[var(--shadow-wk-sm)] pointer-events-none"
                        x-text="valueTextFor(minVal)"
                    >{{ $initialMin }}</span>
                @endif
            </div>

            {{-- Max thumb. Same static-fallback pattern as the min thumb. --}}
            <div
                class="{{ $thumbClasses }} z-20"
                :style="maxThumbStyle()"
                {{-- The same disabled shape as the min thumb — see the note there. --}}
                tabindex="{{ $disabled ? '-1' : '0' }}"
                @if($disabled) aria-disabled="true" @endif
                role="slider"
                aria-label="{{ $maxThumbLabel }}"
                @if($thumbDescribedBy) aria-describedby="{{ $thumbDescribedBy }}" @endif
                aria-valuenow="{{ $initialMax }}"
                :aria-valuenow="maxVal"
                @if($bindRangeValueText) :aria-valuetext="valueTextFor(maxVal)" @endif
                {{-- The mirror of the lower handle's ceiling — see the note there.
                     `_setMax` clamps at `minVal + step`, so that, and not `minVal`,
                     is this handle's floor. --}}
                aria-valuemin="{{ min($max, $initialMin + $step) }}"
                :aria-valuemin="maxThumbFloor"
                aria-valuemax="{{ $max }}"
                {{-- The same pending state as the min thumb — see the note there. --}}
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                {{-- The same keyboard model as the min thumb — see the note there.
                     Mirrored at the ends: Home lands one step above the lower
                     handle, End on the track maximum. --}}
                @unless($disabled)
                    @keydown.arrow-right.prevent="stepMax(1)"
                    @keydown.arrow-left.prevent="stepMax(-1)"
                    @keydown.arrow-up.prevent="stepMax(1)"
                    @keydown.arrow-down.prevent="stepMax(-1)"
                    @keydown.page-up.prevent="stepMax(10)"
                    @keydown.page-down.prevent="stepMax(-10)"
                    @keydown.home.prevent="jumpMax(false)"
                    @keydown.end.prevent="jumpMax(true)"
                    @pointerdown="startDrag('max', $event)"
                @endunless
            >
                @if($resolvedShowValues)
                    {{-- Edge-flush clamp — mirror of the min bubble, keyed to
                         maxPercent so the max thumb's bubble closes flush with the
                         circle's outer-right edge at 100% (and outer-left at 0%). --}}
                    <span
                        aria-hidden="true"
                        x-ref="maxBubble"
                        :class="[_merged ? 'opacity-0' : 'opacity-100', _ready ? 'transition-opacity duration-[var(--transition-wk-duration)]' : '']"
                        :style="maxBadgeStyle()"
                        class="absolute -top-8 rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] border border-[var(--color-wk-border)] px-[var(--padding-wk-x-sm)] py-0.5 text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)] tabular-nums whitespace-nowrap shadow-[var(--shadow-wk-sm)] pointer-events-none"
                        x-text="valueTextFor(maxVal)"
                    >{{ $initialMax }}</span>
                @endif
            </div>

            @if($resolvedShowValues)
                {{-- Merged badge: when the thumbs sit close enough that the two
                     individual badges would overlap (_measureBubbles), show ONE
                     "min – max" badge centered between them instead.

                     Structure MIRRORS a thumb + its badge: an outer wrapper at
                     `top-1/2 -translate-y-1/2` (vertically centered on the track,
                     exactly like a thumb div) carries the horizontal `:style`
                     left, and the inner badge sits at `-top-8 left-1/2
                     -translate-x-1/2` (exactly like the per-thumb badges). Sharing
                     the identical positioning recipe guarantees the merged badge
                     sits at the SAME height as the individual badges — a bare
                     `-top-8` off the TRACK sat ~4px lower, because the track top is
                     above the thumb-centered origin the individual badges use.

                     Visibility is an OPACITY toggle on the SAME `_merged` flag the
                     individual badges read (they use `!_merged`) — NEVER `x-show`.
                     `x-show` writes `display:none` into the `style` attribute, but
                     this element also needs a `:style` left binding, and Alpine
                     re-renders `:style` on every thumb move — clobbering x-show's
                     `display:none` and making the merged badge reappear, so BOTH
                     badge sets showed at once whenever a thumb moved. Keeping
                     opacity in `class` (via `:class`) leaves `:style` free for
                     `left`, so the two bindings never fight and the individual vs
                     merged badges stay strictly mutually exclusive. --}}
                {{-- "Phantom thumb": same positioning + size recipe as a real
                     thumb (top-1/2 -translate-y-1/2 -translate-x-1/2 wk-range-thumb)
                     but invisible and non-interactive. It carries the SAME
                     `border-2` as a real thumb (transparent here) so its
                     border-box height is pixel-identical: -translate-y-1/2 then
                     shifts this wrapper by the EXACT same amount as a real thumb,
                     landing the inner badge at the same Y as the per-thumb badges.
                     Without the matching border the real thumb's 2px border
                     nudged its badge ~2px off the merged badge's row. --}}
                <div
                    class="absolute top-1/2 -translate-y-1/2 -translate-x-1/2 wk-range-thumb border-2 border-transparent pointer-events-none"
                    :style="mergedBadgeStyle()"
                >
                    <span
                        aria-hidden="true"
                        :class="[_merged ? 'opacity-100' : 'opacity-0', _ready ? 'transition-opacity duration-[var(--transition-wk-duration)]' : '']"
                        class="absolute -top-8 left-1/2 -translate-x-1/2 rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] border border-[var(--color-wk-border)] px-[var(--padding-wk-x-sm)] py-0.5 text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)] tabular-nums whitespace-nowrap shadow-[var(--shadow-wk-sm)] z-30"
                        x-text="mergedBadgeText()"
                    ></span>
                </div>
            @endif
        </div>

        {{-- Edge labels show the slider BOUNDS ($min and $max) — never
             the current values. The current values bubble above each
             thumb (see badges above) and follow the handles when dragged.
             aria-live region keeps screen-readers in sync with the
             current selection without duplicating the visible bound
             labels (which are constant). --}}
        <div class="mt-3 flex justify-between text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] tabular-nums">
            <span>{{ $rangeValueTextMap[(string) $min] ?? $min }}</span>
            <span>{{ $rangeValueTextMap[(string) $max] ?? $max }}</span>
        </div>
        <div class="sr-only" aria-live="polite">
            <span x-text="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Range: :from to :to')) }}.replace(':from', valueTextFor(minVal)).replace(':to', valueTextFor(maxVal))">Range: {{ $initialMin }} to {{ $initialMax }}</span>
        </div>

        @if($optimisticConfig)
            {{-- Its own region, marked, and NOT the polite one above: that one
                 tracks the value while you drag, this one carries the promise
                 and its withdrawal. Rendered unconditionally and starting
                 empty — a region that arrives with its text is a new node, and
                 nothing is announced at all. --}}
            <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
            </div>
        @endif
    </div>

    @if($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
    {{-- Same shape as `input`: one region, error winning over hint, announced politely so
         it does not interrupt what the reader is doing. --}}
    @if($error)
        <p id="{{ $id }}-error" aria-live="polite" aria-atomic="true" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $error }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
