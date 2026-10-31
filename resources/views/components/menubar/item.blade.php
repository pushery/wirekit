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

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:click']);
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $danger = BooleanProp::from($danger, false, 'menubar.item', 'danger');
    $disabled = BooleanProp::from($disabled, false, 'menubar.item', 'disabled');

    // Menubar item — same visual pattern as dropdown items with optional shortcut badge.
    $classes = WireKit::resolveClasses('menubar.item', 'base', implode(' ', [
        'flex items-center justify-between gap-x-[var(--gap-wk-md)] w-full',
        'p-[var(--padding-wk-x-sm)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'focus:outline-hidden',
        // `focus-visible` and a ring, per dropdown/item.blade.php. The background is the hover's
        // shade, one step from the panel, too faint to say where the keyboard is on its own, and
        // `focus:outline-hidden` takes the browser's own ring away. `focus-visible` keeps a menu
        // opened by the pointer from painting the entry it focuses.
        'focus-visible:bg-[var(--color-wk-bg-hover)]',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-inset',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'cursor-pointer',
    ]), $scope);

    $colorClasses = $danger
        ? 'text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-hover)]'
        : 'text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-hover)]';

    $disabledClasses = $disabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';

    $tag = $href !== null ? 'a' : 'button';

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See dropdown/item.blade.php for rationale on except('rel') + explicit
    // rel render (avoids $attributes->merge treating rel as a default).
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href !== null && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<{{ $tag }} data-wk-prose-skip
    @if($href !== null) href="{{ $href }}" @endif
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
    {{ $attributes->except($tag === 'button' ? ['rel', 'role', 'tabindex', 'type'] : ['rel', 'role', 'tabindex'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['wk-touch-row', $classes, $colorClasses, $disabledClasses])) }}
>
    <span>{{ $slot }}</span>

    @php
        // The keys a shortcut holds, one or a list (Support\KeyShortcuts::hintKeys()); a key of white
        // space alone holds none, so a blank shortcut draws neither a cap nor the row around it.
        $shortcutKeys = \Pushery\WireKit\Support\KeyShortcuts::hintKeys($shortcut);
    @endphp
    @if($shortcutKeys !== [])
        <span class="flex items-center gap-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
            @foreach($shortcutKeys as $key)
                {{-- The component, not a copy of it — see command-palette/item for what the
                     hand-rolled chip was dropping. --}}
                <x-wirekit::kbd size="sm">{{ $key }}</x-wirekit::kbd>
            @endforeach
        </span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
