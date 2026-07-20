@props([
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
    // (e.g. [1 => 'Low', 5 => 'High']). Falls back to the `marks` labels (WIRE-160).
    'valueTextMap' => null,
    // Show a value bubble above the thumb that follows it as the user drags.
    'tooltip' => false,
    'disabled' => false,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

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
    $hasLabeledMarks = false;
    if (! empty($marks)) {
        $pairs = array_is_list($marks)
            ? array_map(fn ($v) => [$v, (string) $v], $marks)
            : array_map(fn ($v, $lbl) => [$v, (string) $lbl], array_keys($marks), array_values($marks));
        foreach ($pairs as [$mValue, $mLabel]) {
            $pct = max(0, min(100, (($mValue - $min) / $range) * 100));
            $normalizedMarks[] = ['value' => $mValue, 'label' => $mLabel, 'pct' => $pct];
            $hasLabeledMarks = $hasLabeledMarks || $mLabel !== '';
        }
    }
    // The track overlay (tooltip bubble + tick marks) needs a relative container.
    $hasTrackOverlay = $tooltip || ! empty($normalizedMarks);

    // Value → semantic-text map (WIRE-12). When `marks` is a labeled MAP
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
    $isLabeledMarkMap = ! empty($marks) && ! array_is_list($marks)
        && collect($marks)->contains(fn ($lbl, $val) => (string) $lbl !== (string) $val);

    $valueTextMap = [];
    if ($explicitValueTextMap !== null) {
        foreach ($explicitValueTextMap as $mValue => $mLabel) {
            $valueTextMap[(string) $mValue] = (string) $mLabel;
        }
    } elseif ($isLabeledMarkMap) {
        foreach ($marks as $mValue => $mLabel) {
            $valueTextMap[(string) $mValue] = (string) $mLabel;
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
@endphp

{{-- Alpine tracks the current value so the display (and the tooltip bubble /
     fill) update on input. `pct` is the thumb position as a 0–100 percentage,
     used to place the tooltip bubble over the thumb. --}}
<div
    x-data="{
        current: @js((string) $currentValue),
        min: {{ $min }},
        max: {{ $max }},
        marksMap: @js((object) $valueTextMap),
        get pct() {
            const r = (this.max - this.min) || 1;
            return Math.max(0, Math.min(100, ((Number(this.current) - this.min) / r) * 100));
        },
        get valueText() {
            // Labeled-mark map wins (announces 'Low' for value 0); otherwise the
            // raw number IS the value. Kept live so keyboard / drag updates the
            // announced text as the thumb moves.
            return this.marksMap[this.current] ?? String(this.current);
        }
    }"
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
            <div class="pointer-events-none absolute bottom-full z-10 mb-1.5" :style="`left: ${pct}%`" aria-hidden="true">
                <span class="block whitespace-nowrap rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-tooltip-bg)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] tabular-nums text-[color:var(--color-wk-tooltip-text)] shadow-[var(--shadow-wk-sm)]" :style="`transform: translateX(-${pct}%)`" x-text="valueText"></span>
            </div>
        @endif

        <input
            type="range"
            @if($name) name="{{ $name }}" @endif
            id="{{ $sliderId }}"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            :value="current"
            @input="current = $event.target.value"
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
                    <div class="absolute flex -translate-x-1/2 flex-col items-center" style="left: {{ $mark['pct'] }}%">
                        <span class="h-1 w-px bg-[var(--color-wk-border)]"></span>
                        @if($mark['label'] !== '')
                            <span class="mt-0.5 text-[length:var(--text-wk-xs)] tabular-nums text-[color:var(--color-wk-text-muted)]">{{ $mark['label'] }}</span>
                        @endif
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
</div>
