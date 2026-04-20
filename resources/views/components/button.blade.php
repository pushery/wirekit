@props([
    'variant' => config('wirekit.components.button.variant', 'primary'),
    'intent' => null,
    'surface' => null,
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
        'inline-flex items-center justify-center gap-x-2',
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

    // Variant resolution: intent × surface (v2.0) takes precedence over legacy variant
    if ($intent !== null || $surface !== null) {
        $resolvedIntent = $intent ?? 'primary';
        $resolvedSurface = $surface ?? 'filled';
        $variantClasses = \Pushery\WireKit\VariantResolver::resolve($resolvedIntent, $resolvedSurface);
    } else {
        $variantClasses = match ($variant) {
            'primary' => implode(' ', [
                'bg-[var(--color-wk-accent)]',
                'text-[var(--color-wk-accent-fg)]',
                'border-[var(--color-wk-accent)]',
                'hover:bg-[var(--color-wk-accent-hover)]',
                'hover:border-[var(--color-wk-accent-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'secondary' => implode(' ', [
                'bg-[var(--color-wk-bg-muted)]',
                'text-[var(--color-wk-text)]',
                'border-[var(--color-wk-bg-muted)]',
                'hover:bg-[var(--color-wk-bg-subtle)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'outline' => implode(' ', [
                'bg-[var(--color-wk-bg)]',
                'text-[var(--color-wk-text)]',
                'border-[var(--color-wk-border)]',
                'hover:bg-[var(--color-wk-bg-subtle)]',
                'hover:border-[var(--color-wk-border-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'ghost' => implode(' ', [
                'bg-transparent',
                'text-[var(--color-wk-text)]',
                'border-transparent',
                'hover:bg-[var(--color-wk-bg-subtle)]',
                'shadow-[var(--shadow-wk-none)]',
            ]),
            'danger' => implode(' ', [
                'bg-[var(--color-wk-danger)]',
                'text-[var(--color-wk-danger-fg)]',
                'border-[var(--color-wk-danger)]',
                'hover:bg-[var(--color-wk-danger-hover)]',
                'hover:border-[var(--color-wk-danger-hover)]',
                'shadow-[var(--shadow-wk-sm)]',
            ]),
            'link' => implode(' ', [
                'text-[var(--color-wk-accent-content)]',
                'border-transparent',
                'underline-offset-4',
                'hover:underline',
                'p-0 h-auto',
            ]),
            default => WireKit::validateProp('button', 'variant', $variant, ['primary', 'secondary', 'outline', 'ghost', 'danger', 'link']),
        };
    }

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

    // Auto-inject rel="noopener noreferrer" and SR hint when target="_blank"
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr . ' noopener noreferrer')
        : $relAttr;
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $type }}" @endif
    @disabled($disabled)
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$baseClasses, $variantClasses, $sizeClasses]) }}
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
