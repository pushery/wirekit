@props([
    'defaultSize' => 50,
    'minSize' => 10,
    'maxSize' => 90,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Resizable panel — flex child sized via CSS custom properties read by
    // the rules in `dist/wirekit.css`. The actual `resize: horizontal/vertical`
    // declaration lives there too, scoped to non-last children of a
    // [data-wk-resizable] container, so a single CSS rule handles both
    // orientations without per-direction Blade branching.
    $classes = WireKit::resolveClasses('resizable.panel', 'base', '', $scope);

    // Inline custom properties — read by the [data-wk-resizable] selectors
    // in dist/wirekit.css. We keep them as percentages so the layout adapts
    // proportionally when the container resizes (e.g. on viewport changes).
    // NOTE: clamp to integers to avoid sending floats into inline style
    // attributes — Blade's @php doesn't sanitize these for us.
    $defaultPct = max(0, min(100, (int) $defaultSize));
    $minPct = max(0, min(100, (int) $minSize));
    $maxPct = max($minPct, min(100, (int) $maxSize));

    $style = sprintf(
        '--wk-default-size: %d%%; --wk-min-size: %d%%; --wk-max-size: %d%%;',
        $defaultPct,
        $minPct,
        $maxPct,
    );
@endphp

<div
    data-wk-resizable-panel
    data-wk-default-size="{{ $defaultPct }}"
    data-wk-min-size="{{ $minPct }}"
    data-wk-max-size="{{ $maxPct }}"
    style="{{ $style }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>
