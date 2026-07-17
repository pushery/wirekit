@props([
    'value' => null,             // numeric value (0..max). null = indeterminate
    'max' => 100,                // max value the bar represents
    'label' => null,             // visible label rendered above the bar
    'showValue' => false,        // show "42 / 100" next to label
    'variant' => config('wirekit.components.progress.variant', 'primary'), // back-compat alias of `intent`
    'intent' => null,            // canonical color axis: primary | success | warning | danger | info | neutral (+ accent alias). null → falls back to `variant`
    'size' => config('wirekit.components.progress.size', 'md'),           // sm | md | lg
    // Optional motion on the DETERMINATE fill — the "work in flight" affordance
    // (uploads, streaming). none (default) | stripes (barber-pole) | shimmer
    // (a light sweep). Purely additive polish: gated by prefers-reduced-motion,
    // and the bar's value/width is unchanged, so nothing depends on the motion.
    'animation' => 'none',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('progress', $attributes->getAttributes());

    // `intent` is the canonical color-axis name (matches the house vocabulary
    // used by badge / button / alert). `variant` is kept as a back-compat
    // alias so pre-2.4 callers render identically — when `intent` is null the
    // effective color falls back to `variant`. Validate the resolved value
    // against the canonical intent set + the legacy 'accent' synonym.
    $effectiveIntent = $intent ?? $variant;
    $variantValue = match ($effectiveIntent) {
        'primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral' => $effectiveIntent,
        default => WireKit::validateProp('progress', 'intent', $effectiveIntent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']),
    };

    // Clamp the value to [0, max] and compute percentage for fill width
    $isIndeterminate = $value === null;
    $clamped = $isIndeterminate ? 0 : max(0, min((float) $value, (float) $max));
    $percent = $max > 0 ? ($clamped / $max) * 100 : 0;

    // Track (the background bar) — use design tokens for color + radius
    $trackClasses = WireKit::resolveClasses('progress', 'track', implode(' ', [
        'relative w-full overflow-hidden',
        'rounded-[var(--radius-wk-full)]',
        'bg-[var(--color-wk-bg-muted)]',
    ]), $scope);

    // Track height scales with the size prop
    $heightClass = match ($size) {
        'sm' => 'h-1',
        'lg' => 'h-3',
        default => 'h-2',
    };

    // Fill color changes with semantic intent — always via design tokens.
    // Mapping mirrors badge's intent palette: info shares the accent fill (no
    // distinct --color-wk-info base token exists), neutral uses the muted text
    // token for a low-emphasis gray bar.
    $fillColor = match ($variantValue) {
        'success' => 'bg-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)]',
        'neutral' => 'bg-[var(--color-wk-text-muted)]',
        default => 'bg-[var(--color-wk-accent)]', // primary + accent + info
    };

    // Optional fill motion (determinate only — the indeterminate bar already
    // travels). Validated against the enum; 'none' adds nothing.
    $animationValue = in_array($animation, ['none', 'stripes', 'shimmer'], true)
        ? $animation
        : WireKit::validateProp('progress', 'animation', $animation, ['none', 'stripes', 'shimmer']);
    $animationClass = (! $isIndeterminate && $animationValue !== 'none')
        ? ' wk-progress-'.$animationValue
        : '';

    // Determinate: animate width transitions for smooth updates.
    // Indeterminate: rely on .wk-progress-indeterminate keyframes (see dist/wirekit.css)
    $fillClasses = $isIndeterminate
        ? $fillColor . ' absolute inset-y-0 rounded-[var(--radius-wk-full)] wk-progress-indeterminate'
        : $fillColor . ' h-full rounded-[var(--radius-wk-full)] transition-[width] duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]' . $animationClass;

    // Auto-generate an id so label + progressbar can be linked via aria-labelledby
    $labelId = 'progress-' . \Illuminate\Support\Str::random(6) . '-label';

    // Extract aria-label / aria-labelledby from attributes so they can be
    // applied to the role="progressbar" element (the ARIA contract lives
    // there, not on the outer wrapper). Without this, axe-core flags the
    // progressbar as missing an accessible name.
    $ariaLabelAttr = $attributes->get('aria-label');
    $ariaLabelledbyAttr = $attributes->get('aria-labelledby');
    $attributes = $attributes->except(['aria-label', 'aria-labelledby']);
@endphp

<div {{ $attributes->class(['w-full font-[family-name:var(--font-wk-sans)]']) }}>
    @if($label || $showValue)
        <div class="mb-1 flex items-center justify-between text-[length:var(--text-wk-sm)]">
            @if($label)
                <span id="{{ $labelId }}" class="text-[color:var(--color-wk-text)]">{{ $label }}</span>
            @else
                <span></span>
            @endif
            @if($showValue && ! $isIndeterminate)
                <span class="text-[color:var(--color-wk-text-muted)] tabular-nums">
                    {{ (int) $clamped }} / {{ (int) $max }}
                </span>
            @endif
        </div>
    @endif

    {{-- role="progressbar" + aria-value* are the WCAG/WAI-ARIA contract for progress indicators.
         An accessible name is MANDATORY (axe-core's progressbar-name rule) — we source it from:
         1. `label` prop (preferred, visible above bar)
         2. `aria-labelledby` attribute (links to another element's id)
         3. `aria-label` attribute (invisible text)
         4. Fallback: generic "Progress" so the progressbar always has SOME name --}}
    <div
        role="progressbar"
        @if($label) aria-labelledby="{{ $labelId }}"
        @elseif($ariaLabelledbyAttr) aria-labelledby="{{ $ariaLabelledbyAttr }}"
        @elseif($ariaLabelAttr) aria-label="{{ $ariaLabelAttr }}"
        @else aria-label="Progress"
        @endif
        @if(! $isIndeterminate)
            aria-valuenow="{{ (int) $clamped }}"
            aria-valuemin="0"
            aria-valuemax="{{ (int) $max }}"
        @endif
        class="{{ $trackClasses }} {{ $heightClass }}"
    >
        @if($isIndeterminate)
            <div class="{{ $fillClasses }}"></div>
        @else
            <div class="{{ $fillClasses }}" style="width: {{ $percent }}%"></div>
        @endif
    </div>
</div>
