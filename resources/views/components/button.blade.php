@props([
    'intent' => config('wirekit.components.button.intent', 'primary'),
    'surface' => config('wirekit.components.button.surface', 'filled'),
    'size' => config('wirekit.components.button.size', 'md'),
    'type' => 'button',
    'href' => null,
    'disabled' => false,
    'loading' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Base classes: layout, typography, transitions, focus ring, disabled state
    // All values reference design tokens — no hardcoded colors, sizes, or durations
    $baseClasses = WireKit::resolveClasses('button', 'base', implode(' ', [
        // `whitespace-nowrap` keeps the button's text on a single line
        // alongside the loading-spinner / icon slots. Without it, a
        // narrow button width (or a long label like "Saving…") flexes
        // the text into a second line BELOW the spinner — visually the
        // spinner stacks above the text. `inline-flex` alone does not
        // prevent the inner TEXT NODE from soft-wrapping at its own
        // whitespace; `whitespace-nowrap` clamps the text to one line.
        'inline-flex items-center justify-center gap-x-2 whitespace-nowrap',
        'cursor-pointer',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-body-weight)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'leading-[var(--font-wk-line-height)]',
        'border-[length:var(--border-wk-width)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:pointer-events-none',
    ]), $scope);

    // Validate intent + surface (debug mode raises on unknown values).
    if (! in_array($intent, \Pushery\WireKit\VariantResolver::INTENTS, true)) {
        WireKit::validateProp('button', 'intent', $intent, \Pushery\WireKit\VariantResolver::INTENTS);
    }
    if (! in_array($surface, \Pushery\WireKit\VariantResolver::SURFACES, true)) {
        WireKit::validateProp('button', 'surface', $surface, \Pushery\WireKit\VariantResolver::SURFACES);
    }

    $variantClasses = \Pushery\WireKit\VariantResolver::resolve($intent, $surface);

    // Size classes: height, padding, font size, radius — all from sizing tokens
    $sizeClasses = match ($size) {
        'xs' => implode(' ', [
            'h-[calc(var(--size-wk-sm)*0.875)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md' => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'xl' => implode(' ', [
            'h-[calc(var(--size-wk-lg)*1.1)]',
            'px-[calc(var(--padding-wk-x-lg)*1.25)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-lg)]',
        ]),
        default => WireKit::validateProp('button', 'size', $size, ['xs', 'sm', 'md', 'lg', 'xl']),
    };

    // Render as <a> when href is provided, otherwise <button>
    $tag = $href ? 'a' : 'button';

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See dropdown/item.blade.php for rationale on except('rel') + explicit
    // rel render (avoids $attributes->merge treating rel as a default).
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $type }}" @endif
    @disabled($disabled)
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$baseClasses, $variantClasses, $sizeClasses]) }}
    @if($loading) wire:loading.attr="disabled" @endif
>
    {{-- Loading spinner (visible during Livewire requests) --}}
    @if($loading)
        <svg wire:loading class="animate-spin -ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    @endif

    {{-- Icon slots: use <x-slot:iconLeft> / <x-slot:iconRight> for HTML icons (SVG etc.) --}}
    @isset($iconLeft)
        <span class="shrink-0">{{ $iconLeft }}</span>
    @endisset

    {{ $slot }}

    @isset($iconRight)
        <span class="shrink-0">{{ $iconRight }}</span>
    @endisset

    @if($opensNewTab)
        <span class="sr-only">(opens in new tab)</span>
    @endif
</{{ $tag }}>
