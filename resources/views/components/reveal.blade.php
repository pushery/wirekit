@props([
    // Animation preset name. One of 11 in-presets:
    //   fade, slide-up, slide-down, slide-left, slide-right,
    //   scale, zoom, flip, rotate, bounce, spring
    'preset' => null,
    // Trigger mode: 'viewport' (IntersectionObserver), 'click', or 'manual'
    // (consumer dispatches Alpine.$dispatch('wirekit:reveal')).
    'trigger' => null,
    // Duration token: 'fast' (150ms), 'normal' (300ms, default), 'slow' (600ms).
    'duration' => null,
    // Re-fire on every trigger? Default true = animate once and stop.
    'once' => null,
    // IntersectionObserver threshold (only used when trigger='viewport'). 0..1.
    'threshold' => null,
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
@endphp

<div
    {{ $attributes }}
    x-data="wirekitAnimate('{{ $validatedPreset }}', {{ $optionsJson }})"
>
    {{ $slot }}
</div>
