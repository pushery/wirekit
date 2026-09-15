{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // A code the pushery/wirekit-flags package carries: an ISO 3166-1 alpha-2 code such as
    // `de`, or one of its region and organization codes such as `eu` or `gb-sct`. Case does not
    // matter.
    'country' => null,
    // A URL to a flag of your own, for one the package does not carry. Wins over `country`.
    'src' => null,
    // `rect` shows the 4:3 artwork, `square` the 1:1 artwork, and `circle` crops the 1:1 artwork
    // round. No artwork is stretched to a ratio it was not drawn for.
    'shape' => config('wirekit.components.flag.shape', 'rect'),
    'size' => config('wirekit.components.flag.size', 'md'),
    // Empty by default, which makes the flag decorative: next to the country's name, a second
    // announcement of the same name is noise. Give the flag a name when it stands alone.
    'alt' => '',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\FlagPackage;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified, like every other
    // component's call, so it does not depend on the imports above.
    \Pushery\WireKit\WireKit::warnUnknownProps('flag', $attributes->getAttributes());

    $shape = WireKit::validateProp('flag', 'shape', (string) $shape, ['rect', 'square', 'circle']);
    $size = WireKit::validateProp('flag', 'size', (string) $size, ['xs', 'sm', 'md', 'lg', 'xl']);
    $alt = (string) ($alt ?? '');

    $format = $shape === 'rect' ? '4x3' : '1x1';

    // Heights on the icon ladder, so a flag beside text sits where an icon of the same size would.
    // The width follows from the artwork's ratio, which the class states and the width and height
    // attributes repeat for the moment before the stylesheet or the image has loaded.
    $heightClass = match ($size) {
        'xs' => 'h-3',
        'sm' => 'h-4',
        'lg' => 'h-6',
        'xl' => 'h-8',
        default => 'h-5',
    };

    [$intrinsicWidth, $intrinsicHeight] = $format === '4x3' ? [32, 24] : [24, 24];

    $classes = WireKit::resolveClasses('flag', 'base', implode(' ', [
        'inline-block shrink-0 align-middle',
        $heightClass,
        'w-auto',
        $format === '4x3' ? 'aspect-[4/3]' : 'aspect-square',
        // A radius taken from the theme, halved, so a flag's corners soften with the theme
        // without turning a 20px flag into a pill. `circle` is the crop itself.
        $shape === 'circle' ? 'rounded-full' : 'rounded-[calc(var(--radius-wk-sm)/2)]',
    ]), $scope);

    // What to show. A caller's own `src` is used as given. A country goes through the package
    // manifest, which is the only thing that can build a URL for it; without the package, or for
    // a code the package does not carry, the result is a placeholder of the same box.
    $url = null;
    $code = strtolower(trim((string) ($country ?? '')));

    if (is_string($src) && trim($src) !== '') {
        $url = $src;
    } elseif (FlagPackage::manifest() === null) {
        FlagPackage::reportMissingOnce();
    } else {
        $url = FlagPackage::url($code, $format);

        if ($url === null) {
            FlagPackage::reportUnknownOnce($code);
        }
    }
@endphp

@if ($url !== null)
    <img data-wk-prose-skip
        src="{{ $url }}"
        width="{{ $intrinsicWidth }}"
        height="{{ $intrinsicHeight }}"
        alt="{{ $alt }}"
        loading="lazy"
        decoding="async"
        data-wk-flag
        {{ $attributes->class([$classes]) }}
    />
@else
    {{-- The same box as the flag it stands in for, so a row of countries keeps its alignment
         when one flag is missing. It is decorative unless the flag was given a name. --}}
    <span
        @if ($alt !== '') role="img" aria-label="{{ $alt }}" @else aria-hidden="true" @endif
        data-wk-flag
        {{ $attributes->class([$classes, 'bg-[var(--color-wk-bg-muted)]']) }}
    ></span>
@endif