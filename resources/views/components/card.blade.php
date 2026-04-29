@props([
    'variant' => config('wirekit.components.card.variant', 'outlined'),
    'as' => 'div',
    'href' => null,
    // Optional reveal animation when card scrolls into view. One of 11 base presets
    // (or any -in / -out variant). Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'card');

    // Base classes: radius, overflow, transition for interactive cards
    $baseClasses = WireKit::resolveClasses('card', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[var(--color-wk-text)]',
        'rounded-[var(--radius-wk-lg)]',
        'overflow-hidden',
        'transition-shadow',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
    ]), $scope);

    // Variant classes: border/shadow combinations for visual weight
    $variantClasses = match ($variant) {
        'outlined' => implode(' ', [
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
        ]),
        'elevated' => implode(' ', [
            'shadow-[var(--shadow-wk-md)]',
            'border-[length:var(--border-wk-width)]',
            'border-transparent',
        ]),
        'flat' => implode(' ', [
            'bg-[var(--color-wk-bg-subtle)]',
            'border-[length:var(--border-wk-width)]',
            'border-transparent',
        ]),
        default => WireKit::validateProp('card', 'variant', $variant, ['outlined', 'elevated', 'flat']),
    };

    // Hover shadow only when rendered as a link (interactive)
    $interactiveClasses = $href
        ? 'hover:shadow-[var(--shadow-wk-lg)] cursor-pointer block'
        : '';

    // Render as <a> when href given, otherwise use $as tag (default: div)
    $tag = $href ? 'a' : $as;

    // Auto-inject rel="noopener noreferrer" when target="_blank"
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr . ' noopener noreferrer')
        : $relAttr;
@endphp

<{{ $tag }}
    data-wk-card
    @if($href) href="{{ $href }}" @endif
    @if($animateAttr) {!! $animateAttr !!} @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$baseClasses, $variantClasses, $interactiveClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">(opens in new tab)</span>
    @endif
</{{ $tag }}>
