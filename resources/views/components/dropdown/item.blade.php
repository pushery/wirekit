@props([
    'href' => null,
    'danger' => false,
    'disabled' => false,
    'icon' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Base item classes — full-width flex row with hover state
    $classes = WireKit::resolveClasses('dropdown.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        'focus:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    // Color classes — danger variant or default neutral text
    $colorClasses = $danger
        ? 'text-[var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)]'
        : 'text-[var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]';

    // Disabled state — muted appearance, no pointer events
    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

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
    @if($tag === 'button') type="button" @endif
    role="menuitem"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$classes, $colorClasses, $disabledClasses]) }}
>
    {{-- Optional icon (resolved via WireKit icon system) --}}
    @if($icon)
        <span class="shrink-0 w-5 h-5" aria-hidden="true">
            @if(function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
            @endif
        </span>
    @endif

    {{ $slot }}

    @if($opensNewTab)
        <span class="sr-only">(opens in new tab)</span>
    @endif
</{{ $tag }}>
