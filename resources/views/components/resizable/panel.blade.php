{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'defaultSize' => 50,
    'minSize' => 10,
    'maxSize' => 90,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('resizable.panel', $attributes->getAttributes());

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
    $defaultPct = max(0, min(100, \Pushery\WireKit\Support\NumericProp::int($defaultSize, 0, 'resizable.panel', 'defaultSize')));
    $minPct = max(0, min(100, \Pushery\WireKit\Support\NumericProp::int($minSize, 0, 'resizable.panel', 'minSize')));
    $maxPct = max($minPct, min(100, \Pushery\WireKit\Support\NumericProp::int($maxSize, 0, 'resizable.panel', 'maxSize')));

    $style = sprintf(
        '--wk-default-size: %d%%; --wk-min-size: %d%%; --wk-max-size: %d%%;',
        $defaultPct,
        $minPct,
        $maxPct,
    );

    // The id the handle's `aria-controls` names. Written here, counted rather than random, so a
    // Livewire update renders the same one: the morph keys an element without `wire:key` by its
    // id, and an id only the script gave the panel made every update replace it with the server's,
    // which dropped the reader's size and left the handle naming a panel no longer on the page.
    // A caller's id without whitespace (Support\DomId::valid()): the handle names it in a list of
    // ids, which splits on a space.
    $panelId = \Pushery\WireKit\Support\DomId::valid(filled($attributes->get('id')) ? (string) $attributes->get('id') : null)
        ?? \Pushery\WireKit\Support\DomId::unique(null, 'wk-resizable-panel-');
@endphp

<div
    id="{{ $panelId }}"
    data-wk-resizable-panel
    data-wk-default-size="{{ $defaultPct }}"
    data-wk-min-size="{{ $minPct }}"
    data-wk-max-size="{{ $maxPct }}"
    {{ $attributes->except('id')->merge(['style' => $style])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{ $slot }}
</div>
