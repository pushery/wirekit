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
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-lg)]',
        'overflow-hidden',
        'transition-shadow',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
    ]), $scope);

    // accept `outline` AND `outlined` for the bordered-only treatment.
    // Button uses `surface="outline"` (no trailing -d); card
    // historically used `variant="outlined"` (with trailing -d). Same
    // visual concept, different vocabulary — the alias here exists for
    // muscle-memory parity across the two components. The canonical
    // spelling stays `outlined` for card so existing developer code
    // keeps working.
    $variantAliases = ['outline' => 'outlined'];
    $variant = $variantAliases[$variant] ?? $variant;

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

    // Dev-only composition warning. The card root is a FRAME with no
    // padding — real content belongs in card.body / card.header / card.footer
    // (each emits the shared `px-[var(--padding-wk-x-lg)]` padding). If the slot
    // carries visible text but none of those padded sub-components, the content
    // renders flush against the border — the #1 silent card bug the downstream
    // DX reports hit. Mirrors the table.blade.php raw-descendant warn pattern.
    //
    // False-positive guard: `strip_tags()` leaving non-empty text means there is
    // real copy in the slot, which EXCLUDES the legitimate edge-to-edge media
    // pattern (a bare <img>/<picture>/<video>/<svg> flush in the card has no
    // card.body by design — strip_tags() leaves nothing, so no warn). Errs toward
    // under-warning, never crying wolf.
    $warnNoBody = false;
    if (config('app.debug')) {
        $rawSlot = (string) $slot;
        if (trim(strip_tags($rawSlot)) !== '') {
            $composed = str_contains($rawSlot, 'wk-card-body')
                || str_contains($rawSlot, 'px-[var(--padding-wk-x-lg)]');
            $warnNoBody = ! $composed;
        }
    }
@endphp

<{{ $tag }}
    data-wk-card
    @if($warnNoBody)
        x-data
        x-init="console.warn('[wirekit] card: content sits directly in the card with no card.body — the card root is a padding-free frame, so this content renders flush against the border. Wrap it in card.body (or card.header / card.footer). See https://docs.wirekit.app/components/card.')"
    @endif
    @if($href) href="{{ $href }}" @endif
    @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$baseClasses, $variantClasses, $interactiveClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">{{ __('(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
