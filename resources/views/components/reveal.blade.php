@props([
    // Animation preset name. One of 11 in-presets:
    //   fade, slide-up, slide-down, slide-left, slide-right,
    //   scale, zoom, flip, rotate, bounce, spring
    'preset' => null,
    // Trigger mode: 'viewport' (IntersectionObserver), 'click', or 'manual'
    // (developer dispatches Alpine.$dispatch('wirekit:reveal')).
    'trigger' => null,
    // Duration token: 'fast' (150ms), 'normal' (300ms, default), 'slow' (600ms).
    'duration' => null,
    // Re-fire on every trigger? Default true = animate once and stop.
    'once' => null,
    // IntersectionObserver threshold (only used when trigger='viewport'). 0..1.
    'threshold' => null,
    // Delay before the entrance animation begins. One of:
    //   null   — no delay (default — animation begins immediately on trigger)
    //   'none'/'sm'/'md'/'lg'/'xl' — token name → maps to --motion-wk-delay-* var
    //   int    — raw milliseconds (e.g. delay="125" for a custom rhythm)
    'delay' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $preset ??= config('wirekit.components.reveal.preset', 'fade-in');
    $trigger ??= config('wirekit.components.reveal.trigger', 'viewport');
    $duration ??= config('wirekit.components.reveal.duration', 'normal');
    $once ??= config('wirekit.components.reveal.once', true);
    $threshold ??= config('wirekit.components.reveal.threshold', 0.4);

    // Validate preset against the 11-preset enum × in/out (auto-suffix '-in' if user
    // passed 'fade' instead of 'fade-in' — the most common mistake).
    $allowedBases = ['fade', 'slide-up', 'slide-down', 'slide-left', 'slide-right',
                     'scale', 'zoom', 'flip', 'rotate', 'bounce', 'spring'];

    // accept the Tailwind UI naming
    // convention `fade-up` / `fade-down` / `fade-left` / `fade-right`
    // as aliases for the equivalent `slide-*-in` (which combines fade
    // + slide motion). Same visual, different vocabulary — designers
    // copying from Tailwind UI / Framer Motion / Headless UI patterns
    // get the same animation without having to learn our suffix scheme.
    $presetAliases = [
        'fade-up'    => 'slide-up-in',
        'fade-down'  => 'slide-down-in',
        'fade-left'  => 'slide-left-in',
        'fade-right' => 'slide-right-in',
    ];
    $preset = $presetAliases[$preset] ?? $preset;

    // Normalise: 'fade' → 'fade-in' (default direction)
    if (in_array($preset, $allowedBases, true)) {
        $preset = $preset.'-in';
    }

    $allowedFull = array_merge(
        array_map(fn ($p) => $p.'-in', $allowedBases),
        array_map(fn ($p) => $p.'-out', $allowedBases)
    );

    $validatedPreset = in_array($preset, $allowedFull, true)
        ? $preset
        : WireKit::validateProp('reveal', 'preset', $preset, $allowedFull);

    $validatedTrigger = in_array($trigger, ['viewport', 'click', 'manual'], true)
        ? $trigger
        : WireKit::validateProp('reveal', 'trigger', $trigger, ['viewport', 'click', 'manual']);

    $validatedDuration = in_array($duration, ['fast', 'normal', 'slow'], true)
        ? $duration
        : WireKit::validateProp('reveal', 'duration', $duration, ['fast', 'normal', 'slow']);

    // JSON-encode options object for x-data — Alpine parses it as JS literal.
    $optionsJson = json_encode([
        'trigger' => $validatedTrigger,
        'once' => (bool) $once,
        'threshold' => (float) $threshold,
        'duration' => $validatedDuration,
    ], JSON_HEX_TAG | JSON_UNESCAPED_SLASHES);

    // Delay resolution:
    //   null  → no animation-delay style emitted
    //   int   → '125ms' (raw)
    //   token → 'var(--motion-wk-delay-{token})'
    // The CSS `animation-delay` is set via inline style on the root, so the
    // browser composes it with the wk-animate-{preset} class the moment
    // the Alpine helper adds the class. No JS plugin change needed.
    $delay ??= config('wirekit.components.reveal.delay', null);
    $delayValue = match (true) {
        $delay === null => null,
        is_int($delay) && $delay >= 0 => $delay.'ms',
        in_array($delay, ['none', 'sm', 'md', 'lg', 'xl'], true) => "var(--motion-wk-delay-{$delay})",
        default => WireKit::validateProp('reveal', 'delay', $delay, ['none', 'sm', 'md', 'lg', 'xl']),
    };

    // Compose caller's `style=…` with our internal `animation-delay` into
    // a SINGLE style attribute. Without this, the template emitted two
    // style attributes on the same <div> — caller-passed (e.g.
    // `style="--wk-stagger-step: 0ms"`) plus our @if-rendered
    // `style="animation-delay: 1000ms"`. Per HTML5 parsing, duplicate
    // attributes resolve to the FIRST and silently drop the second, so
    // the animation-delay was never applied whenever a caller passed
    // any inline style — and every gallery preview in animations.md
    // does, so the delay was universally dropped on the gallery (and
    // by extension every developer composition that passed inline
    // style for any reason).
    $callerStyle = trim((string) $attributes->get('style', ''), '; ');
    $internalStyle = $delayValue ? "animation-delay: {$delayValue}" : '';
    $mergedStyle = trim(implode('; ', array_filter([$callerStyle, $internalStyle], fn ($s) => $s !== '')));
@endphp

<div
    {{ $attributes->except('style')->merge(['data-replayable' => 'true', 'class' => 'w-full']) }}
    x-data="wirekitAnimate('{{ $validatedPreset }}', {{ $optionsJson }})"
    @if($mergedStyle !== '') style="{{ $mergedStyle }}" @endif
>
    {{ $slot }}
</div>
