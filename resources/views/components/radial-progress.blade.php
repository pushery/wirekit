@props([
    // Current value. Determinate only — a ring that cannot say how far along it is
    // has nothing to draw; use the spinner for unknown-duration work.
    'value' => 0,
    'max' => 100,
    // Accessible name. REQUIRED: a progressbar with no name announces a number
    // and never says what the number is about.
    'label' => null,
    // What assistive tech should read instead of the bare percentage. Use it when
    // the percentage is not the point ("18.2 GB of 25 GB used").
    'valueText' => null,
    'size' => config('wirekit.components.radial-progress.size', 'md'),
    // Color axis. Ignored once a threshold is crossed — see below.
    'intent' => config('wirekit.components.radial-progress.intent', 'primary'),
    // Fraction of max at which the ring turns warning / danger, mirroring
    // usage-meter so a dashboard reads the same in both shapes. null disables.
    'warn' => null,
    'danger' => null,
    // Sweep the fill from empty to its value on first paint, and animate later
    // value changes instead of snapping. Purely additive polish: gated by
    // prefers-reduced-motion, and the ring is complete without it (the on-load
    // sweep is above-baseline progressive enhancement — see dist/wirekit.css).
    'animate' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $size = WireKit::validateProp('radial-progress', 'size', $size, ['md', 'sm', 'lg', 'xl']);
    $intent = WireKit::validateProp('radial-progress', 'intent', $intent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']);

    $numericMax = (float) $max;
    // A zero or negative max would make the ratio a division by zero and every
    // ring render full. Treat it as the caller meaning "out of 100".
    $numericMax = $numericMax > 0 ? $numericMax : 100.0;

    $numericValue = (float) $value;
    // Clamp rather than trust: a value over max would sweep the cone past a full
    // turn and start drawing a second lap over the first.
    $numericValue = max(0.0, min($numericValue, $numericMax));

    $ratio = $numericValue / $numericMax;
    $percent = $ratio * 100;

    // Thresholds win over the declared intent: a ring that is over its limit but
    // still drawn in the brand color is telling the reader the wrong thing.
    $effectiveIntent = $intent;

    if ($danger !== null && $ratio >= (float) $danger) {
        $effectiveIntent = 'danger';
    } elseif ($warn !== null && $ratio >= (float) $warn) {
        $effectiveIntent = 'warning';
    }

    // The exact map progress uses, so a dashboard reads the same in both shapes.
    // info falls to the accent fill because there is no distinct --color-wk-info
    // base token (only --color-wk-info-text); neutral takes the muted text token
    // for a low-emphasis ring. Full literal token per arm — the drift auditor
    // reads these statically and cannot follow an interpolated name.
    $fillToken = match ($effectiveIntent) {
        'success' => 'var(--color-wk-success)',
        'warning' => 'var(--color-wk-warning)',
        'danger' => 'var(--color-wk-danger)',
        'neutral' => 'var(--color-wk-text-muted)',
        default => 'var(--color-wk-accent)', // primary + accent + info
    };

    $sizeClass = match ($size) {
        'sm' => 'wk-radial-sm',
        'lg' => 'wk-radial-lg',
        'xl' => 'wk-radial-xl',
        default => 'wk-radial-md',
    };

    $animate = filter_var($animate, FILTER_VALIDATE_BOOLEAN);

    $classes = WireKit::resolveClasses('radial-progress', 'base', implode(' ', array_filter([
        'wk-radial',
        $sizeClass,
        // Opt-in sweep — the class carries the transition + @starting-style (see
        // dist/wirekit.css), all gated by prefers-reduced-motion.
        $animate ? 'wk-radial-animate' : '',
        'relative inline-grid place-items-center shrink-0',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ])), $scope);

    // Announce the human-readable form when there is one. Without it a screen
    // reader reads the raw number, which is wrong whenever max is not 100.
    $announced = $valueText ?? round($percent).'%';
@endphp

{{-- The ring is drawn with a conic-gradient masked into an annulus, driven by one
     custom property. That keeps the whole thing declarative: no canvas, no SVG
     arc math, and a value change is a single style write.

     role="progressbar" carries the meaning; the number in the middle is a visual
     convenience. Assistive tech reads aria-valuetext, so the two can never
     disagree — they are the same string. --}}
<div
    role="progressbar"
    aria-valuenow="{{ round($numericValue, 2) }}"
    aria-valuemin="0"
    aria-valuemax="{{ round($numericMax, 2) }}"
    aria-valuetext="{{ $announced }}"
    {{-- A progressbar MUST have an accessible name; aria-valuetext is the VALUE,
         not the name. `label` is the intended source, but it defaults to null, so
         a developer who omits it used to ship a nameless progressbar. Fall back to
         a translatable generic name so the role is never anonymous. --}}
    aria-label="{{ $label ?? __('Progress') }}"
    {{-- Opt the animated ring INTO the docs.wirekit.app replay button so the sweep
         can be re-watched — a re-mount re-fires @starting-style. No-op in a
         developer app (no such button); it only adds the attribute. --}}
    @if($animate) data-replayable="true" @endif
    data-wk-radial-progress
    data-intent="{{ $effectiveIntent }}"
    style="--wk-radial-value: {{ round($percent, 2) }}; --wk-radial-fill: {{ $fillToken }};"
    {{ $attributes->class([$classes]) }}
>
    {{-- The center. Empty by default: a bare percentage repeated inside every ring
         is noise the aria-valuetext already carries. --}}
    <span class="relative z-10 text-center leading-none">{{ $slot }}</span>
</div>
