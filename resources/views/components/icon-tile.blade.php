{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'icon' => null,
    // The color of the tile: a tint of the accent or of a state over the page's background,
    // with the icon in the matching text color. primary | accent | neutral | info | success |
    // warning | danger; `primary` names the accent, as it does on a button.
    'intent' => 'accent',
    // The ladder of the kit's sizes: 2rem, 2.5rem and 3rem.
    'size' => 'md',
    'shape' => 'square',
    // What the icon says, for a reader who does not see it. Without one the tile is decoration
    // and hidden from assistive technology, which is right beside a heading or a sentence that
    // says the same.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('icon-tile', $attributes->getAttributes());

    // The tints `feature` draws its icon with, over the page's background rather than over
    // transparency, so a tile reads the same on a card and on the page.
    $intentMap = [
        'accent' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-accent-text)]',
        'neutral' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]',
        'info' => 'bg-[color-mix(in_srgb,var(--color-wk-info-tone)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-info-text)]',
        'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-success-text)]',
        'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-warning-text)]',
        'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-danger-text)]',
    ];

    // Read as text, which a float beyond the integer range and a list also have, and checked
    // against the list; an unknown value is reported and takes the default, the first of each list.
    $intentKey = \Pushery\WireKit\Support\NumericProp::text($intent ?? 'accent');
    $intentKey = $intentKey === 'primary' ? 'accent' : $intentKey;
    $validIntent = isset($intentMap[$intentKey])
        ? $intentKey
        : WireKit::validateProp('icon-tile', 'intent', $intentKey, array_keys($intentMap));

    // The box, its corner and the icon inside it, one step of the ladder each.
    $sizeMap = [
        'md' => ['w-[var(--size-wk-md)] h-[var(--size-wk-md)]', 'rounded-[var(--radius-wk-md)]', 'md'],
        'sm' => ['w-[var(--size-wk-sm)] h-[var(--size-wk-sm)]', 'rounded-[var(--radius-wk-sm)]', 'sm'],
        'lg' => ['w-[var(--size-wk-lg)] h-[var(--size-wk-lg)]', 'rounded-[var(--radius-wk-lg)]', 'lg'],
    ];
    $sizeKey = \Pushery\WireKit\Support\NumericProp::text($size ?? 'md');
    $validSize = isset($sizeMap[$sizeKey])
        ? $sizeKey
        : WireKit::validateProp('icon-tile', 'size', $sizeKey, array_keys($sizeMap));
    [$boxClasses, $squareCorner, $iconSize] = $sizeMap[$validSize];

    $shapeKey = \Pushery\WireKit\Support\NumericProp::text($shape ?? 'square');
    $validShape = in_array($shapeKey, ['square', 'circle'], true)
        ? $shapeKey
        : WireKit::validateProp('icon-tile', 'shape', $shapeKey, ['square', 'circle']);
    $corner = $validShape === 'circle' ? 'rounded-full' : $squareCorner;

    $classes = WireKit::resolveClasses('icon-tile', 'base', implode(' ', [
        'inline-flex shrink-0 items-center justify-center',
        $boxClasses,
        $corner,
        $intentMap[$validIntent],
    ]), $scope);

    // Named, the tile is one image with the label as its name and the icon inside it decoration;
    // unnamed, the whole tile is decoration. A caller's own aria-label or aria-hidden wins.
    $name = \Pushery\WireKit\Support\SlotContent::textOf($label);
    $named = $name !== '' && ! $attributes->has('aria-hidden');
    $roleAttributes = $named
        ? ['role' => 'img', 'aria-label' => $name]
        : ($attributes->has('aria-label') || $attributes->has('aria-labelledby') ? ['role' => 'img'] : ['aria-hidden' => 'true']);
@endphp

<span {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes]))->merge($roleAttributes) }} data-wk-icon-tile>
    @wirekitLast(\Pushery\WireKit\Support\SlotContent::given($slot))
        {{ $slot }}
    @elseif(is_string($icon) && $icon !== '')
        <x-wirekit::icon :name="$icon" :size="$iconSize" />
    @endwirekitLast
</span>