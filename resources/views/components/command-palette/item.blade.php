@props([
    'href' => null,
    'icon' => null,
    'shortcut' => null,
    'disabled' => false,
    // Stable DOM id for this item. It anchors the combobox's
    // aria-activedescendant, so it must survive a re-render: with server-side
    // search the list is rebuilt on every keystroke, and an id that changes each
    // time keeps moving the screen reader's announcement target and makes
    // Livewire's morph patch every row. Pass one when you have a natural key.
    'id' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Command item — selectable option in the command palette.
    //
    // The id is DERIVED, in this order: an explicit `id`, then the href (the
    // natural key for a link item), then the item's own text. Only when none of
    // those exist does it fall back to a random string — which is what every
    // item used to get, and what made the list a different set of elements on
    // every render.
    $wkCmdSlug = static function (string $value): string {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '');

        return trim($slug, '-');
    };

    $itemId = $id
        ?? ($href !== null && $href !== ''
            ? 'wk-cmd-item-'.$wkCmdSlug((string) $href)
            : null);

    if ($itemId === null) {
        $label = $wkCmdSlug(trim(strip_tags((string) $slot)));
        $itemId = $label !== ''
            ? 'wk-cmd-item-'.$label
            : 'wk-cmd-item-'.\Illuminate\Support\Str::random(6);
    }

    $classes = WireKit::resolveClasses('command-palette.item', 'base', implode(' ', [
        'flex items-center gap-x-[var(--gap-wk-sm)] w-full',
        'px-[var(--padding-wk-x-md)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus:bg-[var(--color-wk-bg-subtle)]',
        'data-[active=true]:bg-[var(--color-wk-bg-subtle)]',
    ]), $scope);

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

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
    id="{{ $itemId }}"
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="button" @endif
    role="option"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $disabledClasses]) }}
>
    @if($icon)
        <span class="shrink-0 w-5 h-5 text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
            @if(function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => 'w-5 h-5']) }}
            @endif
        </span>
    @endif

    <span class="flex-1 truncate">{{ $slot }}</span>

    @if($shortcut)
        <span class="ml-auto flex items-center gap-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
            @foreach((array) $shortcut as $key)
                <kbd class="inline-flex items-center justify-center min-w-5 px-1.5 py-0.5 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-muted)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-xs)]">{{ $key }}</kbd>
            @endforeach
        </span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">{{ __('(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
