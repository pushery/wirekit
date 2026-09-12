{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
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
    // The NAME of an Alpine property to read the value from, for a value that only
    // exists in the browser — an upload percentage, a streamed count. `value` is a
    // PHP prop evaluated at render time, so it cannot carry one; binding to the
    // rendered element cannot either, because `x-bind:value` sets an attribute on a
    // `<div>` that nothing reads. Pass the property name, not an expression:
    // `value-expression="percent"` resolves against the surrounding `x-data`
    // through Alpine's scope chain.
    'valueExpression' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showValue = BooleanProp::from($showValue, false);

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
    // Both variants are needed at once in `value-expression` mode, where the state is
    // decided in the browser rather than here.
    $fillIndeterminate = $fillColor.' absolute inset-y-0 rounded-[var(--radius-wk-full)] wk-progress-indeterminate';
    $fillDeterminate = $fillColor . ' h-full rounded-[var(--radius-wk-full)] transition-[width] duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]' . $animationClass;

    $fillClasses = $isIndeterminate ? $fillIndeterminate : $fillDeterminate;

    // The id that links label → progressbar via aria-labelledby, and it has to be
    // STABLE across re-renders. It was `Str::random(6)` per render, which is a
    // defect that only shows where this component is most used: inside a
    // `wire:poll` region, every poll produced a new id, so the accessible name was
    // re-resolved on a control whose whole purpose is being watched while it
    // changes. Nothing looked wrong in the markup — both halves always agreed
    // with each other; they just agreed on a different value every second.
    //
    // Derived from the caller's `id` when there is one, so the same progress bar
    // keeps the same id across renders. With no `id`, `DomId::unique` still falls
    // back to a random suffix — unavoidable without one, and page-unique — but it
    // is then stable for the life of that render pass rather than per element.
    $labelId = \Pushery\WireKit\Support\DomId::unique(
        $attributes->get('id') ? $attributes->get('id').'-label' : null,
        'progress-label-'
    );

    // Extract aria-label / aria-labelledby from attributes so they can be
    // applied to the role="progressbar" element (the ARIA contract lives
    // there, not on the outer wrapper). Without this, axe-core flags the
    // progressbar as missing an accessible name.
    $ariaLabelAttr = $attributes->get('aria-label');
    $ariaLabelledbyAttr = $attributes->get('aria-labelledby');
    $attributes = $attributes->except(['aria-label', 'aria-labelledby']);
@endphp

@if($valueExpression)
    {{-- The scope sits on the WRAPPER, not on the bar: the readout is a SIBLING above
         the track, so a scope on the track leaves `valueText()` unresolvable there.
         Measured in the browser — the width and `aria-valuenow` were right while the
         readout stayed empty, which is the half the report called "not visual-only".
         The arithmetic lives in the factory because Alpine's CSP build has no
         expression evaluator; every binding below only names a method. --}}
    <div x-data="wirekitProgress({ from: {{ \Pushery\WireKit\Support\AlpinePayload::from($valueExpression) }}, max: {{ $max + 0 }}, determinate: {{ \Pushery\WireKit\Support\AlpinePayload::from($fillDeterminate) }}, indeterminate: {{ \Pushery\WireKit\Support\AlpinePayload::from($fillIndeterminate) }} })" {{ $attributes->class(['w-full font-[family-name:var(--font-wk-sans)]']) }}>
@else
<div {{ $attributes->class(['w-full font-[family-name:var(--font-wk-sans)]']) }}>
@endif
    @if($label || $showValue)
        <div class="mb-1 flex items-center justify-between gap-[var(--gap-wk-sm)] text-[length:var(--text-wk-sm)]">
            @if($label)
                <span id="{{ $labelId }}" class="text-[color:var(--color-wk-text)]">{{ $label }}</span>
            @else
                <span></span>
            @endif
            @if($showValue && $valueExpression)
                <span class="text-[color:var(--color-wk-text-muted)] tabular-nums" x-text="valueText()"></span>
            @elseif($showValue && ! $isIndeterminate)
                <span class="text-[color:var(--color-wk-text-muted)] tabular-nums">
                    {{-- Not `(int)`. The BAR is drawn from the exact value, so truncating here
                         made the number disagree with the thing beside it: 4.7 of 5 drew a
                         94% bar and read "4 / 5". `+ 0` drops a trailing `.0`, so a whole
                         value still reads as a whole one. --}}
                    {{ $clamped + 0 }} / {{ $max + 0 }}
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
        @else aria-label="{{ __('wirekit::Progress') }}"
        @endif
        @if($valueExpression)
            {{-- Bound rather than printed: the value arrives after this render. The min and
                 max are static because the RANGE is known now even when the value is not. --}}
            x-bind:aria-valuenow="ariaValueNow()"
            aria-valuemin="0"
            aria-valuemax="{{ $max + 0 }}"
        @elseif(! $isIndeterminate)
            {{-- Same reason as the visible readout: `aria-valuenow` is what a screen reader
                 announces, and truncating it made the announcement disagree with the bar. --}}
            aria-valuenow="{{ $clamped + 0 }}"
            aria-valuemin="0"
            aria-valuemax="{{ $max + 0 }}"
        @endif
        {{-- `wk-progress-track` / `wk-progress-fill` are the markers the stylesheet's
             forced-colors rule selects. Both sit outside the resolved class list, like
             `wk-spinner`, so a `personalize()` override cannot remove the rule that keeps
             the bar readable when the palette is forced. --}}
        class="wk-progress-track {{ $trackClasses }} {{ $heightClass }}"
    >
        @if($valueExpression)
            {{-- `wk-progress-indeterminate` is bound too, so the bar animates while there is
                 no value yet and becomes a real bar the moment one arrives — rather than
                 drawing 0%, which claims no work has been done instead of not knowing. --}}
            <div
                class="wk-progress-fill"
                x-bind:class="fillClasses()"
                x-bind:style="fillStyle()"
            ></div>
        @elseif($isIndeterminate)
            <div class="wk-progress-fill {{ $fillClasses }}"></div>
        @else
            <div class="wk-progress-fill {{ $fillClasses }}" style="width: {{ $percent }}%"></div>
        @endif
    </div>
</div>
