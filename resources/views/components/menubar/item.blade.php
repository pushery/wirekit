@props([
    'href' => null,
    'danger' => false,
    'disabled' => false,
    'shortcut' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Menubar item — same visual pattern as dropdown items with optional shortcut badge.
    $classes = WireKit::resolveClasses('menubar.item', 'base', implode(' ', [
        'flex items-center justify-between gap-x-[var(--gap-wk-md)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'focus:outline-none',
        'focus:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $colorClasses = $danger
        ? 'text-[var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)]'
        : 'text-[var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]';

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

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
    x-on:click="closeAll()"
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$classes, $colorClasses, $disabledClasses]) }}
>
    <span>{{ $slot }}</span>

    @if($shortcut)
        <span class="flex items-center gap-1 text-[length:var(--text-wk-xs)] text-[var(--color-wk-text-muted)]" aria-hidden="true">
            @foreach((array) $shortcut as $key)
                <kbd class="inline-flex items-center justify-center min-w-5 px-1 py-0.5 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-muted)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-xs)]">{{ $key }}</kbd>
            @endforeach
        </span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">(opens in new tab)</span>
    @endif
</{{ $tag }}>
