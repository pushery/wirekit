@props([
    'label' => null,
    'hint' => null,
    'min' => 0,
    'max' => 100,
    'step' => 1,
    'minValue' => null,
    'maxValue' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $id = $attributes->get('id', $attributes->get('name', 'range-slider-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Compute defaults: minValue defaults to min, maxValue defaults to max
    $initialMin = $minValue ?? $min;
    $initialMax = $maxValue ?? $max;

    $wrapperClasses = WireKit::resolveClasses('range-slider', 'base', implode(' ', [
        'space-y-2',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Thumb classes — shared for both handles
    $thumbClasses = implode(' ', [
        'absolute top-1/2 -translate-y-1/2 -translate-x-1/2',
        'w-5 h-5',
        'rounded-full',
        'bg-[var(--color-wk-accent)]',
        'border-2 border-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-sm)]',
        'cursor-pointer',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-shadow duration-[var(--transition-wk-duration)]',
    ]);
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
    >
        {{-- Hidden inputs for form submission --}}
        <input type="hidden" name="{{ $name }}[min]" :value="minVal" x-ref="minInput" />
        <input type="hidden" name="{{ $name }}[max]" :value="maxVal" x-ref="maxInput" />

        {{-- Track --}}
        <div class="relative h-2 rounded-full bg-[var(--color-wk-bg-muted)]" x-ref="track">
            {{-- Active range fill --}}
            <div
                class="absolute h-full rounded-full bg-[var(--color-wk-accent)]"
                :style="`left: ${minPercent}%; width: ${maxPercent - minPercent}%`"
            ></div>
        </div>

        {{-- Min thumb. Static aria-valuenow / aria-valuemax mirror the
             initial state so axe-core's pre-Alpine-init scan sees a
             complete slider; Alpine then overrides reactively. --}}
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
        ></div>

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
        ></div>
    </div>

    {{-- Value display --}}
    <div class="flex justify-between text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)] tabular-nums" aria-live="polite">
        <span x-text="minVal">{{ $initialMin }}</span>
        <span x-text="maxVal">{{ $initialMax }}</span>
    </div>

    @if($hint)
        <p class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
