@props([
    'variant' => config('wirekit.components.card.variant', 'outlined'),
    'as' => 'div',
    'href' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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
        default => $variant,
    };

    // Hover shadow only when rendered as a link (interactive)
    $interactiveClasses = $href
        ? 'hover:shadow-[var(--shadow-wk-lg)] cursor-pointer block'
        : '';

    // Render as <a> when href given, otherwise use $as tag (default: div)
    $tag = $href ? 'a' : $as;
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->class([$baseClasses, $variantClasses, $interactiveClasses]) }}
>
    {{ $slot }}
</{{ $tag }}>
