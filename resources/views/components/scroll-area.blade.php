@props([
    'orientation' => 'vertical', // vertical | horizontal | both
    'maxHeight' => null,
    // Edge fade: false (none) | 'both' | 'start' | 'end'. Masks the overflow
    // edge(s) along the scroll axis so the content itself dissolves into the
    // background — a background-agnostic "there's more to scroll" hint (static
    // mask-image, not a colored overlay). Removed on :focus-within so a
    // keyboard-focused child near an edge is never clipped by the mask.
    'fade' => config('wirekit.components.scroll-area.fade', false),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Scroll Area — themed scrollbar container wrapping the existing .wk-scrollbar utility.
    // Provides a component API around the CSS scrollbar utility with configurable orientation.
    $overflowClass = match ($orientation) {
        'horizontal' => 'overflow-x-auto overflow-y-hidden',
        'both' => 'overflow-auto',
        default => 'overflow-y-auto overflow-x-hidden',
    };

    // Normalize the fade prop. Falsy / 'none' → no fade; otherwise validate to
    // the allowed edge set so an invalid value resolves to a real one.
    $fadeValue = ($fade === false || $fade === null || $fade === '' || $fade === 'none')
        ? null
        : (in_array($fade, ['both', 'start', 'end'], true)
            ? $fade
            : WireKit::validateProp('scroll-area', 'fade', $fade, ['both', 'start', 'end']));

    // The fade masks the SCROLL axis: x for horizontal, y for vertical / both.
    $fadeAxis = $orientation === 'horizontal' ? 'x' : 'y';

    $classes = WireKit::resolveClasses('scroll-area', 'base', implode(' ', array_filter([
        'wk-scrollbar',
        $overflowClass,
        'font-[family-name:var(--font-wk-sans)]',
        $fadeValue ? 'wk-scroll-fade' : '',
    ])), $scope);

    // Inline style for max-height — common pattern for scroll containers
    $inlineStyle = $maxHeight ? "max-height: {$maxHeight};" : '';
@endphp

{{-- Scroll container — focusable for keyboard scrolling (a11y: WCAG 2.1.1) --}}
<div
    tabindex="0"
    role="region"
    aria-label="Scrollable content"
    @if($fadeValue) data-fade-axis="{{ $fadeAxis }}" data-fade="{{ $fadeValue }}" @endif
    {{ $attributes->class([$classes]) }}
    @if($inlineStyle) style="{{ $inlineStyle }}" @endif
>
    {{ $slot }}
</div>
