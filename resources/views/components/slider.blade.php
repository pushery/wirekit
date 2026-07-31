{{-- optimistic-ui: supported
     Single-handle, over a native `<input type="range">`, so the commit boundary
     is the CONTROL's own event rather than a pointerup handler this code owns.
     Which event that is was MEASURED in chromium rather than assumed, because
     assuming is what the marker mechanism is weakest against:

       a five-move drag  → 5 × input, then exactly ONE change, at release
       one arrow key     → 1 × input, then change, immediately
       two arrow keys    → one input+change pair EACH, no coalescing
       blur afterwards   → nothing further

     So `change` is the boundary for both input modes at once, and it matches
     §10 without a special case: it fires once at the end of a drag, and once per
     keypress because one press is already a finished decision.

     The mirror moves DURING the gesture (`@input="current = ..."`), so this
     marks the gesture's start — otherwise the baseline would already be the
     value being committed and a refusal would roll back onto itself. `after`
     pushes the mirror back onto the element, because the element owns its value
     and a rollback that moved only the mirror would leave the thumb where the
     server refused to put it. --}}
@props([
    // The Livewire method to call when the slider should show the new value
    // before the server has agreed to it. The value is sent when the gesture
    // ends — see the note above. Null leaves the component exactly as it has
    // always rendered.
    'optimistic' => null,
    'name' => null,
    'id' => null,
    'label' => null,
    'min' => config('wirekit.components.slider.min', 0),
    'max' => config('wirekit.components.slider.max', 100),
    'step' => config('wirekit.components.slider.step', 1),
    'value' => null,
    'size' => config('wirekit.components.slider.size', 'md'),
    'showValue' => false,
    // Step marks: a list of values (`[0, 25, 50, 75, 100]`) for plain ticks, or a
    // value => label map (`[0 => 'Low', 100 => 'High']`) for labeled ticks.
    'marks' => [],
    // Decouple the announced aria-valuetext from the visual tick labels: an explicit
    // value => spoken-text map lets you show NUMERIC ticks but announce semantic meaning
    // (e.g. [1 => 'Low', 5 => 'High']). Falls back to the `marks` labels.
    'valueTextMap' => null,
    // Show a value bubble above the thumb that follows it as the user drags.
    'tooltip' => false,
    'disabled' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showValue = BooleanProp::from($showValue, false);
    $tooltip = BooleanProp::from($tooltip, false);
    $disabled = BooleanProp::from($disabled, false);

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Slider = styled HTML <input type="range">. Native element gives us
    // arrow-key support, drag handling, and accessibility for free; we only
    // need to style the track + thumb via CSS variables.
    $sliderId = $id ?? ($name ? 'wk-slider-' . $name : 'wk-slider-' . Str::random(6));
    $currentValue = $value ?? $min;

    // Normalize marks to [['value'=>, 'label'=>, 'pct'=>], ...]. A LIST (`[0, 25, 50]`)
    // is positions-only (label = the number); a MAP (`[0 => 'Low', 100 => 'High']`)
    // uses the key as the position and the value as the label. pct positions each
    // tick along the track: (value − min) / (max − min) × 100, clamped to 0–100.
    $range = ($max - $min) ?: 1;
    $normalizedMarks = [];
    // Initialized beside the marks it describes, so the reader below does not depend on
    // `! empty($normalizedMarks) &&` short-circuiting to avoid an undefined variable.
    $marksIsList = true;
    $hasLabeledMarks = false;
    if (! empty($marks)) {
        // THREE accepted shapes, and the third is the reason this is not a simple
        // cast any more:
        //
        //   [0, 50, 100]                                  a plain list of positions
        //   [0 => 'Low', 100 => 'High']                   value => label
        //   [0 => ['label' => 'Low', 'description' => …]] value => label + meaning
        //
        // The third exists because a slider whose positions MEAN something is the
        // normal case once the values are not numbers — five steps from -2 to +2,
        // each standing for a policy. Before it, a reader saw only the numbers and
        // had to MOVE the slider to learn what a position means, which is the one
        // thing they were trying to find out before changing it.
        //
        // `valueTextMap` already solved the screen-reader half well. This is the
        // visual half: what gets announced was not readable without altering the
        // value.
        // `array_is_list` alone is not the list/map question, and reading it as if it were
        // CRASHED on a legitimate call. `[0 => ['description' => …], 1 => [...]]` is a map
        // whose positions happen to be 0 and 1 — a slider from 0 to 1 with a meaning at each
        // end — and PHP cannot tell that from a list. The list branch then cast a spec array
        // to string: "Array to string conversion", a fatal on markup that is documented as
        // supported. Found by a test written for a different gap.
        //
        // A list element is a POSITION, never a spec, so an array element settles it: the
        // caller wrote a map and PHP's key numbering is a coincidence.
        $marksIsList = array_is_list($marks)
            && ! collect($marks)->contains(fn ($m) => is_array($m));

        $pairs = $marksIsList
            ? array_map(fn ($v) => [$v, (string) $v, null], $marks)
            : array_map(
                fn ($v, $spec) => is_array($spec)
                    ? [$v, (string) ($spec['label'] ?? $v), ($spec['description'] ?? null) !== null ? (string) $spec['description'] : null]
                    : [$v, (string) $spec, null],
                array_keys($marks),
                array_values($marks)
            );

        foreach ($pairs as [$mValue, $mLabel, $mDescription]) {
            $pct = max(0, min(100, (($mValue - $min) / $range) * 100));
            $normalizedMarks[] = [
                'value' => $mValue,
                'label' => $mLabel,
                'pct' => $pct,
                'description' => $mDescription,
                // A per-mark id, needed because the description is referenced by
                // `aria-describedby` and a page can hold several sliders.
                'descId' => $mDescription === null
                    ? null
                    : \Pushery\WireKit\Support\DomId::unique(
                        ($attributes->get('id') ?: $name).'-mark-'.preg_replace('/[^a-z0-9-]/i', '', (string) $mValue),
                        'slider-mark-'
                    ),
            ];
            $hasLabeledMarks = $hasLabeledMarks || $mLabel !== '';
        }
    }
    // The track overlay (tooltip bubble + tick marks) needs a relative container.
    $hasTrackOverlay = $tooltip || ! empty($normalizedMarks);

    // Value → semantic-text map. When `marks` is a labeled MAP
    // (`[0 => 'Low', 100 => 'High']`, i.e. NOT a plain list), the labels carry
    // meaning a screen reader must hear — otherwise the native range input
    // announces only the bare number ("0"), while sighted users read "Low" off
    // the ticks. Build a string-keyed map (the Alpine `current` value is always a
    // string) so the slider can announce the label via aria-valuetext and echo
    // it in the tooltip / value display. A plain list of positions carries no
    // extra meaning, so it does NOT get aria-valuetext — the number IS the value,
    // and the DOM stays byte-identical to before.
    // A caller-supplied aria-valuetext binding wins over our own (mirrors the v2.8.0
    // aria-label precedence rule). An explicit `valueTextMap` prop decouples the spoken
    // text from the visual ticks entirely.
    $callerBindsValueText = $attributes->has('aria-valuetext')
        || $attributes->has('x-bind:aria-valuetext')
        || $attributes->has(':aria-valuetext');
    $explicitValueTextMap = is_array($valueTextMap) && $valueTextMap !== [] ? $valueTextMap : null;

    // A marks MAP opts into aria-valuetext ONLY when a label carries meaning beyond the
    // number (a numeric-label map — [-2 => '-2', …] — stays byte-identical to a plain
    // slider: the number already IS the value).
    // Reads the NORMALIZED marks, not the raw prop. Both places used to cast the
    // raw value with `(string)`, which was fine while a mark was a string and
    // throws "Array to string conversion" the moment one is a spec array. Reading
    // the normalized form means the shape is handled in exactly one place — the
    // normalizer above — rather than in every reader of `$marks`.
    // A DESCRIPTION counts as semantic content, not only a label that differs from the
    // value. Without the second clause a map whose marks carry descriptions and no labels
    // binds no `aria-valuetext` at all, so the descriptions reach nothing — while the docs
    // say a mark may carry a description with no label and that the description is what the
    // slider announces. Both cannot be true, and the docs describe the intent.
    $isLabeledMarkMap = ! empty($normalizedMarks) && ! $marksIsList
        && collect($normalizedMarks)->contains(
            fn ($m) => $m['label'] !== (string) $m['value'] || $m['description'] !== null
        );

    $valueTextMap = [];
    if ($explicitValueTextMap !== null) {
        foreach ($explicitValueTextMap as $mValue => $mLabel) {
            $valueTextMap[(string) $mValue] = (string) $mLabel;
        }
    } elseif ($isLabeledMarkMap) {
        foreach ($normalizedMarks as $m) {
            // The DESCRIPTION wins over the label when both exist, and that is
            // the point of the third shape: the label is what a sighted reader
            // sees on the tick ("−2"), the description is what the position
            // MEANS ("Single verdict"). A screen reader should hear the meaning.
            $valueTextMap[(string) $m['value']] = $m['description'] ?? $m['label'];
        }
    }

    // Bind aria-valuetext when we have a semantic map AND the caller didn't bind it.
    $bindValueText = ($explicitValueTextMap !== null || $isLabeledMarkMap) && ! $callerBindsValueText;

    // Track height per size token.
    $trackHeight = match ($size) {
        'sm' => 'h-1',
        'lg' => 'h-3',
        default => 'h-2',
    };

    // Wrapper gives us space for the thumb's vertical overflow — and reserves
    // IN-FLOW space for the out-of-flow overlays, so the component never
    // requires the caller to hand-pad around it: the tooltip bubble floats
    // `bottom-full` (pt-7 ≈ bubble + gap) and the tick marks hang `top-full`
    // (pb-6 with labels, pb-2 ticks-only). Without the reservation the bubble
    // clips inside overflow-hidden ancestors and labeled marks overlap the
    // content below. `min-w-[16rem]` is the same usability floor as
    // range-slider: in any shrink-to-fit context (flex/grid auto item, table
    // cell, fit-content wrapper) a w-full track has no intrinsic width and
    // collapses to a few px — far too narrow to drag.
    $wrapperClasses = WireKit::resolveClasses('slider', 'wrapper', implode(' ', array_filter([
        'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        'min-w-[16rem]',
        $tooltip ? 'pt-7' : '',
        $hasLabeledMarks ? 'pb-6' : (! empty($normalizedMarks) ? 'pb-2' : ''),
    ])), $scope);

    // The native input — we make the thumb and track visible via `wk-slider`
    // utility class (see wirekit.css). Uses accent color for the fill.
    $inputClasses = WireKit::resolveClasses('slider', 'input', implode(' ', [
        'wk-slider',
        // Inside a track overlay (tooltip / marks) the input fills its relative
        // container; otherwise it flexes directly in the wrapper row.
        $hasTrackOverlay ? 'w-full' : 'flex-1',
        'appearance-none',
        'bg-transparent',
        'cursor-pointer',
        'focus-visible:outline-none',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        $trackHeight,
    ]), $scope);

    // Live value display next to the slider.
    $valueClasses = WireKit::resolveClasses('slider', 'value', implode(' ', [
        'tabular-nums',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
        'min-w-[2.5ch]',
        'text-right',
    ]), $scope);

    // Accessible-name fallback. WCAG 2.1 (4.1.2) — every form input must
    // have a programmatically-determinable name. When no visible `label`
    // prop is set AND no `aria-label` / `aria-labelledby` is passed via
    // attributes, derive a sr-only fallback from `name` (humanized).
    $hasExplicitAriaName = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $needsSrOnlyFallback = ! $label && ! $hasExplicitAriaName;
    $fallbackLabel = $name ? Str::headline((string) $name) : 'Slider';

    // `bind` rather than `value`: `current` already exists on the component this
    // layer nests inside, so binding to it keeps ONE truth for the value.
    //
    // `after` is what keeps the ELEMENT in step. The mirror is deliberately not
    // bound onto the input, so a write that moved only `current` would leave the
    // thumb where the server refused to put it.
    //
    // The default `undo` exit is right here — a slider value is a choice, not
    // typed work, so putting it back costs the user nothing.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'current',
        'after' => 'syncToInput',
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

{{-- Alpine tracks the current value so the display (and the tooltip bubble /
     fill) update on input. `pct` is the thumb position as a 0–100 percentage,
     used to place the tooltip bubble over the thumb.

     `current` is a MIRROR of the input's own value, and it is kept honest in both
     directions. It used to be written once at render and mutated only by @input,
     which is correct exactly as long as the browser is the only thing that moves
     the thumb. It is not: with `wire:model`, a server-side change writes
     `el.value` directly and fires NO input event, so the mirror kept the old
     number while the thumb showed the new one — and everything derived from the
     mirror (aria-valuetext, the tooltip, showValue, the fill) announced a value
     the reader could no longer see. Assigning a property fires no event and
     mutates no attribute, so it is invisible to x-effect and to a
     MutationObserver alike (verified) — the one reliable signal is Livewire's own
     commit hook, which is exactly the moment the two can diverge. --}}
<div
    {{-- The mirror, the pct math and the announced text live in the factory
         (resources/js/components/slider.js). An inline object literal cannot
         carry methods or getters under Alpine's CSP build — it fails to parse,
         the element gets an empty scope, and every directive here goes quiet. --}}
    x-data="wirekitSlider({ current: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $currentValue) }}, min: {{ $min }}, max: {{ $max }}, marksMap: {{ \Pushery\WireKit\Support\AlpinePayload::from((object) $valueTextMap) }} })"
    x-modelable="current"
    x-init="initResync()"
    {{-- Caller layout attributes (class / style — e.g. a width constraint)
         bind to the WRAPPER, not the <input>. The tooltip bubble + tick marks
         are positioned `left: pct%` relative to the overlay container, which
         fills the wrapper; the <input> track also fills it (`w-full`). Routing
         a width override onto the input alone (the old path — $attributes lands
         on the input) made the input narrower than its overlay container, so
         the bubble's `pct%` resolved against the WIDER container and floated
         far to the side of the thumb. Sizing the wrapper keeps the input, the
         overlay container, and therefore the bubble + marks all at ONE width.
         Input-semantic attributes (wire:model, aria-*, data-*) still flow to
         the <input> below via except(['class','style']). --}}
    {{ $attributes->only(['class', 'style'])->class([$wrapperClasses]) }}
>
@if($optimisticConfig)
    {{-- The layer nests INSIDE the component that owns the mirror: a nested
         Alpine component reads and writes its parent's properties through
         `this` and never the reverse, so `bind: 'current'` only resolves this
         way round.

         `display: contents` because the wrapper is a flex row — a real box here
         would make the label, the track and the value display one flex item
         instead of three. --}}
    <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
    @if($label)
        <label for="{{ $sliderId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</label>
    @elseif($needsSrOnlyFallback)
        {{-- sr-only label fallback so the input always has an accessible
             name (axe rule "label" / WCAG 4.1.2). --}}
        <label for="{{ $sliderId }}" class="sr-only">{{ $fallbackLabel }}</label>
    @endif

    {{-- Track overlay container (tooltip bubble + tick marks). Only rendered when
         needed so the plain slider DOM stays unchanged. --}}
    @if($hasTrackOverlay)<div class="relative flex-1">@endif
        @if($tooltip)
            {{-- Value bubble that follows the thumb. Decorative — the native input
                 already exposes the value to AT. The bubble shifts by translateX(-pct%)
                 so it stays WITHIN the track at the extremes instead of overhanging:
                 at 0% it left-aligns with the thumb (extends inward/right), at 100% it
                 right-aligns (extends inward/left), and centers (-50%) in the middle.
                 (The native thumb has no JS-readable width, so pct doubles as the shift.) --}}
            <div class="pointer-events-none absolute bottom-full z-10 mb-1.5" :style="bubbleStyle()" aria-hidden="true">
                <span class="block whitespace-nowrap rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-tooltip-bg)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] tabular-nums text-[color:var(--color-wk-tooltip-text)] shadow-[var(--shadow-wk-sm)]" :style="bubbleShiftStyle()" x-text="valueText"></span>
            </div>
        @endif

        <input
            type="range"
            @if($name) name="{{ $name }}" @endif
            id="{{ $sliderId }}"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            x-ref="control"
            {{-- NOT `:value="current"`. Binding the mirror back onto the element
                 makes Alpine re-assert the stale value over whatever Livewire just
                 wrote, which is the other half of the same bug. The element owns
                 its value; the mirror follows it.

                 initResync() above re-reads it after every Livewire commit — a
                 server-side change writes el.value and fires NO input event, so
                 that is the one moment the two can silently diverge. --}}
            value="{{ $currentValue }}"
            @input="current = $event.target.value"
            @if($optimisticConfig)
                x-bind:aria-busy="isPending"
                {{-- MEASURED, not assumed (see the note at the top): `change`
                     fires once at the end of a drag and once per keypress, which
                     is exactly §10's boundary for both input modes. --}}
                x-on:change="run($event.target.value)"
                {{-- The gesture begins here, before the mirror moves. A drag
                     writes `current` on every frame via @input above, so a
                     baseline read at commit time would already BE the committed
                     value. keydown lands before the browser applies the step,
                     for the same reason. --}}
                x-on:pointerdown="mark()"
                x-on:keydown="mark()"
            @endif
            {{-- Labeled discrete slider: announce the mark's label, not the bare
                 number. Only bound for a labeled MAP so plain sliders stay
                 byte-identical (the number is already the value). --}}
            @if($bindValueText) :aria-valuetext="valueText" @endif
            @if($disabled) disabled @endif
            {{-- class / style are consumed by the wrapper above; everything
                 else (wire:model, aria-*, data-*) stays on the input. --}}
            {{ $attributes->except(['class', 'style'])->class([$inputClasses]) }}
        />

        @if(! empty($normalizedMarks))
            {{-- Tick marks under the track. Decorative; the native input announces value/min/max. --}}
            <div class="pointer-events-none absolute inset-x-0 top-full mt-1" aria-hidden="true">
                @foreach($normalizedMarks as $mark)
                    {{-- `title` AND a screen-reader description, not either/or.
                         `title` is a hover affordance and there is no hover on
                         touch, so alone it would hide the meaning from exactly the
                         readers most likely to be guessing at it. The sr-only span
                         carries the same text and is referenced by
                         `aria-describedby`, so it reaches assistive technology
                         without a pointer. --}}
                    {{-- `pointer-events-auto` ONLY on a tick that carries a title, and only
                         because the container above is `pointer-events-none` so ticks cannot
                         swallow a drag. Without it the title can never appear: no pointer
                         event reaches the element, so the browser has nothing to show a
                         tooltip for. Measured — the attribute was there and the tooltip was
                         unreachable. Ticks without a description stay transparent to the
                         pointer, which keeps dragging over them unaffected. --}}
                    <div
                        class="absolute flex -translate-x-1/2 flex-col items-center{{ $mark['description'] !== null ? ' pointer-events-auto' : '' }}"
                        style="left: {{ $mark['pct'] }}%"
                        @if($mark['description'] !== null)
                            title="{{ $mark['description'] }}"
                        @endif
                    >
                        <span class="h-1 w-px bg-[var(--color-wk-border)]"></span>
                        @if($mark['label'] !== '')
                            <span class="mt-0.5 text-[length:var(--text-wk-xs)] tabular-nums text-[color:var(--color-wk-text-muted)]">{{ $mark['label'] }}</span>
                        @endif
                        {{-- No `sr-only` description span here, and no `aria-describedby`.
                             Both were shipped and both were inert: this whole container is
                             `aria-hidden="true"` — correctly, because a tick label duplicates
                             the value — so the span was dropped from the accessibility tree
                             and the `aria-describedby` pointing at it resolved to nothing. An
                             `aria-describedby` on a non-focusable div is inert regardless.
                             The description reaches assistive technology through
                             `aria-valuetext` on the input, which announces the meaning of the
                             CURRENT position rather than reading every mark at once. --}}
                    </div>
                @endforeach
            </div>
        @endif
    @if($hasTrackOverlay)</div>@endif
    @if($showValue)
        {{-- aria-live="polite" so screen readers get the updated value when
             the user releases the slider, not on every tick. --}}
        <span class="{{ $valueClasses }}" aria-live="polite" x-text="valueText"></span>
    @endif
@if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    </div>
@endif
</div>
