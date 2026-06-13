@props([
    'href' => null,
    'danger' => false,
    'disabled' => false,
    'icon' => null,
    'shortcut' => null, // keyboard-shortcut hint shown at the inline-end (e.g. "⌘K")
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
        ? 'text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)]'
        : 'text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]';

    // Disabled state — muted appearance, no pointer events
    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    // Render as <a> when href is provided, otherwise <button>
    $tag = $href ? 'a' : 'button';

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // CAREFUL: $attributes->merge(['rel' => ...]) treats rel as a DEFAULT —
    // if caller passed rel="prev", theirs wins and our auto-injection is lost,
    // silently re-introducing the tabnabbing vulnerability. Hence except('rel')
    // + explicit rel attribute render.
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
    @if($tag === 'button') type="button" @endif
    role="menuitem"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $colorClasses, $disabledClasses]) }}
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

    {{-- Keyboard-shortcut hint, pushed to the inline-end. Decorative. --}}
    @if($shortcut)
        <span class="ms-auto ps-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] tabular-nums" aria-hidden="true">{{ $shortcut }}</span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">(opens in new tab)</span>
    @endif
</{{ $tag }}>
