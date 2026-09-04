{{-- optimistic-ui: n/a — passthrough
     Carries the developer's link or action; holds no state. --}}
@props([
    'href' => null,
    'danger' => false,
    'disabled' => false,
    'shortcut' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('menubar.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $danger = BooleanProp::from($danger, false);
    $disabled = BooleanProp::from($disabled, false);

    // Menubar item — same visual pattern as dropdown items with optional shortcut badge.
    $classes = WireKit::resolveClasses('menubar.item', 'base', implode(' ', [
        'flex items-center justify-between gap-x-[var(--gap-wk-md)] w-full',
        'p-[var(--padding-wk-x-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'focus:outline-none',
        'focus:bg-[var(--color-wk-bg-subtle)]',
        'cursor-pointer',
    ]), $scope);

    $colorClasses = $danger
        ? 'text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)]'
        : 'text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)]';

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
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="button" @endif
    role="menuitem"
    tabindex="-1"
    @if($disabled) aria-disabled="true" @endif
    {{-- Activating an item closes the menu AND hands focus back to the trigger it came
         from. Plain closeAll() left the reader on <body> — the panel that held the focus
         was hidden out from under it — so the next Tab restarted at the top of the
         document instead of continuing from the menubar. --}}
    x-on:click="closeAndFocusTrigger()"
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $colorClasses, $disabledClasses]) }}
>
    <span>{{ $slot }}</span>

    @if($shortcut)
        <span class="flex items-center gap-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
            @foreach((array) $shortcut as $key)
                <kbd class="inline-flex items-center justify-center min-w-5 px-1 py-0.5 rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-muted)] font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-xs)]">{{ $key }}</kbd>
            @endforeach
        </span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
