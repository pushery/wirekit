@props([
    'orientation' => 'vertical', // vertical | horizontal | both
    'maxHeight' => null,
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

    $classes = WireKit::resolveClasses('scroll-area', 'base', implode(' ', [
        'wk-scrollbar',
        $overflowClass,
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Inline style for max-height — common pattern for scroll containers
    $inlineStyle = $maxHeight ? "max-height: {$maxHeight};" : '';
@endphp

{{-- Scroll container — focusable for keyboard scrolling (a11y: WCAG 2.1.1) --}}
<div
    tabindex="0"
    role="region"
    aria-label="Scrollable content"
    {{ $attributes->class([$classes]) }}
    @if($inlineStyle) style="{{ $inlineStyle }}" @endif
>
    {{ $slot }}
</div>
