{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the range is sent when the gesture ENDS —
     pointerup for a drag, each keypress for the keyboard (§10: the boundary is
     an event, never a timer). The method receives the pair `[min, max]`,
     because the pair is one value: a rollback restores both, and an untouched
     handle's restore is a no-op.

     The optimistic scope nests INSIDE the slider and binds to the tuple
     `['minVal', 'maxVal']`. --}}
@props([
    // Livewire method to call optimistically. It receives the full range as
    // [min, max]. Absent -> this component renders exactly as it did before,
    // down to the byte.
    'optimistic' => null,
    'label' => null,
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
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;

    use Pushery\WireKit\WireKit;

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
        // 20rem (not the single slider's 16rem): a DUAL-thumb track needs room
        // for two handles plus their value bubbles without the bubbles colliding
        // on contact — the wider floor keeps the demo and tight-column real
        // usages comfortable. Still shrinks to 100% of a narrower parent.
        'min-w-[16rem]',
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
        'cursor-pointer',
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
    $attributes = $attributes->except('aria-describedby');
@endphp

@php
    // The optimistic layer NESTS INSIDE the slider's own scope, and the
    // direction is not interchangeable: a nested Alpine component's method
    // reads and writes its parent's properties through `this`, never the other
    // way around. So it has to be the child to reach `minVal`/`maxVal`, and the
    // thumbs have to be inside it to reach its `run()`.
    //
    // The bind is a TUPLE. §10: a range is one value, not two — snapshot the
    // pair, restore the pair, and let an unchanged half be a no-op. A tuple of
    // plain property names rather than a getter pair on the component, because
    // that does not depend on how the two scopes get merged.
    $optimisticConfig = ($optimistic === null || $attributes->has('disabled')) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => ['minVal', 'maxVal'],
        'action' => $optimistic,
        'debug' => (bool) config('app.debug'),
        // A second commit while one is in flight would resolve by whichever
        // answer arrives last — network timing, which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'reverted' => __('Could not save. Change undone.'),
        ],
    ]);
@endphp

<div
    role="group"
    @if($label) aria-labelledby="{{ $id }}-label" @endif
    {{ $attributes->class([$wrapperClasses]) }}
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
            :value="minVal"
            @if($wireModel) {{ $wireModel }}="{{ $wireModelKey }}.min" @endif
            x-ref="minInput"
        />
        <input
            type="hidden"
            name="{{ $name }}[max]"
            value="{{ $initialMax }}"
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
                tabindex="0"
                role="slider"
                aria-label="{{ $minThumbLabel }}"
                @if($thumbDescribedBy) aria-describedby="{{ $thumbDescribedBy }}" @endif
                aria-valuenow="{{ $initialMin }}"
                :aria-valuenow="minVal"
                @if($bindRangeValueText) :aria-valuetext="valueTextFor(minVal)" @endif
                aria-valuemin="{{ $min }}"
                aria-valuemax="{{ $initialMax }}"
                :aria-valuemax="maxVal"
                {{-- BOTH thumbs report the same pending state, because §10 says
                     a range is one value and one value is one flight. Putting it
                     on the thumb that happened to move would make the state
                     depend on which end the user grabbed; putting it on the
                     `role="group"` wrapper is not possible — that element sits
                     OUTSIDE this layer's scope, and the layer itself is a
                     `display: contents` scope carrier, which browsers do not
                     reliably keep in the accessibility tree. --}}
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                @keydown.arrow-right.prevent="stepMin(1)"
                @keydown.arrow-left.prevent="stepMin(-1)"
                @pointerdown="startDrag('min', $event)"
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
                tabindex="0"
                role="slider"
                aria-label="{{ $maxThumbLabel }}"
                @if($thumbDescribedBy) aria-describedby="{{ $thumbDescribedBy }}" @endif
                aria-valuenow="{{ $initialMax }}"
                :aria-valuenow="maxVal"
                @if($bindRangeValueText) :aria-valuetext="valueTextFor(maxVal)" @endif
                aria-valuemin="{{ $initialMin }}"
                :aria-valuemin="minVal"
                aria-valuemax="{{ $max }}"
                {{-- The same pending state as the min thumb — see the note there. --}}
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                @keydown.arrow-right.prevent="stepMax(1)"
                @keydown.arrow-left.prevent="stepMax(-1)"
                @pointerdown="startDrag('max', $event)"
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
            <span x-text="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('Range: :from to :to')) }}.replace(':from', valueTextFor(minVal)).replace(':to', valueTextFor(maxVal))">Range: {{ $initialMin }} to {{ $initialMax }}</span>
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
</div>
