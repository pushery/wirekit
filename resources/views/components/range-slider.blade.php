@props([
    'label' => null,
    'hint' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'minValue' => null,
    'maxValue' => null,
    'showValues' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $id = $attributes->get('id', $attributes->get('name', 'range-slider-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Compute defaults: minValue defaults to min, maxValue defaults to max
    $initialMin = $minValue ?? $min;
    $initialMax = $maxValue ?? $max;

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
@endphp

<div {{ $attributes->class([$wrapperClasses]) }}>
    @if($label)
        <x-wirekit::label>{{ $label }}</x-wirekit::label>
    @endif

    {{-- Alpine logic inlined (no wirekit.js dependency needed).
         Handles dual-thumb drag, keyboard stepping, and percent calculation. --}}
    <div
        x-data="{
            minVal: {{ $initialMin }},
            maxVal: {{ $initialMax }},
            _min: {{ $min }},
            _max: {{ $max }},
            _step: {{ $step }},
            _dragging: null,
            get minPercent() { return ((this.minVal - this._min) / (this._max - this._min)) * 100; },
            get maxPercent() { return ((this.maxVal - this._min) / (this._max - this._min)) * 100; },
            stepMin(dir) {
                const v = this.minVal + (dir * this._step);
                this.minVal = Math.max(this._min, Math.min(v, this.maxVal - this._step));
                this._dispatch();
            },
            stepMax(dir) {
                const v = this.maxVal + (dir * this._step);
                this.maxVal = Math.min(this._max, Math.max(v, this.minVal + this._step));
                this._dispatch();
            },
            startDrag(handle, event) {
                event.preventDefault();
                this._dragging = handle;
                const onMove = (e) => this._onDrag(e);
                const onUp = () => { this._dragging = null; document.removeEventListener('pointermove', onMove); document.removeEventListener('pointerup', onUp); };
                document.addEventListener('pointermove', onMove);
                document.addEventListener('pointerup', onUp);
            },
            _onDrag(event) {
                if (!this._dragging) return;
                const rect = this.$refs.track.getBoundingClientRect();
                const pct = Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width));
                const stepped = Math.round((this._min + pct * (this._max - this._min)) / this._step) * this._step;
                if (this._dragging === 'min') {
                    this.minVal = Math.max(this._min, Math.min(stepped, this.maxVal - this._step));
                } else {
                    this.maxVal = Math.min(this._max, Math.max(stepped, this.minVal + this._step));
                }
                this._dispatch();
            },
            _dispatch() {
                this.$refs.minInput?.dispatchEvent(new Event('input', { bubbles: true }));
                this.$refs.maxInput?.dispatchEvent(new Event('input', { bubbles: true }));
            }
        }"
        class="relative"
        @if($resolvedShowValues)
            style="padding-top: 2rem;"
        @endif
    >
        {{-- Hidden inputs for form submission. When the caller passed
             wire:model* on the component tag, also bind the matching
             nested-key directive to each input so Livewire picks up
             value updates as the user drags. --}}
        <input
            type="hidden"
            name="{{ $name }}[min]"
            :value="minVal"
            @if($wireModel) {{ $wireModel }}="{{ $wireModelKey }}.min" @endif
            x-ref="minInput"
        />
        <input
            type="hidden"
            name="{{ $name }}[max]"
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
                :style="`left: ${minPercent}%; width: ${maxPercent - minPercent}%`"
            ></div>

            {{-- Min thumb. Static aria-valuenow / aria-valuemax mirror
                 the initial state so axe-core's pre-Alpine-init scan
                 sees a complete slider; Alpine then overrides
                 reactively. The current value bubbles ABOVE the thumb
                 as a tooltip-style badge that tracks the handle
                 horizontally (-translate-x-1/2 centers it on the thumb). --}}
            <div
                class="{{ $thumbClasses }} z-10"
                :style="`left: ${minPercent}%`"
                tabindex="0"
                role="slider"
                aria-label="Minimum"
                aria-valuenow="{{ $initialMin }}"
                :aria-valuenow="minVal"
                aria-valuemin="{{ $min }}"
                aria-valuemax="{{ $initialMax }}"
                :aria-valuemax="maxVal"
                @keydown.arrow-right.prevent="stepMin(1)"
                @keydown.arrow-left.prevent="stepMin(-1)"
                @pointerdown="startDrag('min', $event)"
            >
                @if($resolvedShowValues)
                    <span
                        aria-hidden="true"
                        class="absolute -top-8 left-1/2 -translate-x-1/2 rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] border border-[var(--color-wk-border)] px-[var(--padding-wk-x-sm)] py-0.5 text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)] tabular-nums whitespace-nowrap shadow-[var(--shadow-wk-sm)] pointer-events-none"
                        x-text="minVal"
                    >{{ $initialMin }}</span>
                @endif
            </div>

            {{-- Max thumb. Same static-fallback pattern as the min thumb. --}}
            <div
                class="{{ $thumbClasses }} z-20"
                :style="`left: ${maxPercent}%`"
                tabindex="0"
                role="slider"
                aria-label="Maximum"
                aria-valuenow="{{ $initialMax }}"
                :aria-valuenow="maxVal"
                aria-valuemin="{{ $initialMin }}"
                :aria-valuemin="minVal"
                aria-valuemax="{{ $max }}"
                @keydown.arrow-right.prevent="stepMax(1)"
                @keydown.arrow-left.prevent="stepMax(-1)"
                @pointerdown="startDrag('max', $event)"
            >
                @if($resolvedShowValues)
                    <span
                        aria-hidden="true"
                        class="absolute -top-8 left-1/2 -translate-x-1/2 rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] border border-[var(--color-wk-border)] px-[var(--padding-wk-x-sm)] py-0.5 text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)] tabular-nums whitespace-nowrap shadow-[var(--shadow-wk-sm)] pointer-events-none"
                        x-text="maxVal"
                    >{{ $initialMax }}</span>
                @endif
            </div>
        </div>

        {{-- Edge labels show the slider BOUNDS ($min and $max) — never
             the current values. The current values bubble above each
             thumb (see badges above) and follow the handles when dragged.
             aria-live region keeps screen-readers in sync with the
             current selection without duplicating the visible bound
             labels (which are constant). --}}
        <div class="mt-3 flex justify-between text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] tabular-nums">
            <span>{{ $min }}</span>
            <span>{{ $max }}</span>
        </div>
        <div class="sr-only" aria-live="polite">
            <span x-text="`Range: ${minVal} to ${maxVal}`">Range: {{ $initialMin }} to {{ $initialMax }}</span>
        </div>
    </div>

    @if($hint)
        <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
