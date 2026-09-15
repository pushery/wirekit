{{-- optimistic-ui: n/a — passthrough
     Same: the palette owns selection, the item carries the developer's action. --}}
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
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);

    // Command item — selectable option in the command palette.
    //
    // The id is DERIVED, in this order: an explicit `id`, then the href (the
    // natural key for a link item), then the item's own text. Only when none of
    // those exist is there nothing to derive from at all.
    $wkCmdSlug = static function (string $value): string {
        $slug = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? '');

        return trim($slug, '-');
    };

    $wkCmdDerivedId = $id
        ?? ($href !== null && $href !== ''
            ? 'wk-cmd-item-'.$wkCmdSlug((string) $href)
            : null);

    if ($wkCmdDerivedId === null) {
        $label = $wkCmdSlug(trim(strip_tags((string) $slot)));
        $wkCmdDerivedId = $label !== '' ? 'wk-cmd-item-'.$label : null;
    }

    // ⚠️ The derivation above is stable but NOT unique, and a palette is the one
    // place that shape hurts. The same verb under two groups — "Settings" under
    // Docs and under Admin, "Open" under Files and Projects — derives ONE id for
    // two rows, and `document.getElementById` answers with the first: arrowing
    // onto the second row publishes an `aria-activedescendant` that resolves to
    // the first, so the reader is told about a row it is not standing on, and
    // because the string does not change between the two some readers announce
    // nothing at all. DomId::unique hands the FIRST sight back verbatim — a lone
    // item keeps the clean, readable id the paragraph above argues for — and
    // counts only the collisions, so both properties hold instead of one being
    // traded for the other. It also replaces the old Str::random fallback for the
    // item that has neither href nor text: a counter survives a re-render, a
    // random string makes the row a different element every time. Its registry is
    // per request, which is what a re-render is.
    $itemId = \Pushery\WireKit\Support\DomId::unique($wkCmdDerivedId, 'wk-cmd-item-');

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
        'focus:outline-hidden',
        // `data-active`, not `focus`, and this is the one menu-ish component where
        // that distinction matters. The option is `tabindex="-1"` under the list's
        // `aria-activedescendant`, and command-palette.js never calls `.focus()` on
        // it — the highlight is written by markActive() at line 325. So a `focus:`
        // background here was inert and has been dropped rather than converted.
        //
        // The RING is the mark, for the reason dropdown/item.blade.php records: dark
        // mode declares --color-wk-bg-subtle and --color-wk-bg-elevated as the same
        // value, and the palette panel is bg-elevated, so the highlighted row moved
        // 1.00:1 against what it sits on. Light managed 1.04:1. The ring measures
        // 17.2:1 dark and 19.8:1 light.
        'data-[active=true]:bg-[var(--color-wk-bg-subtle)]',
        'data-[active=true]:ring-[length:var(--ring-wk-width)]',
        'data-[active=true]:ring-inset',
        'data-[active=true]:ring-[var(--color-wk-ring)]',
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

<{{ $tag }} data-wk-prose-skip
    id="{{ $itemId }}"
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="button" @endif
    role="option"
    {{-- The ARIA state for this role, written statically so it exists before the first
         keystroke. `_paintActive()` in the factory keeps it in step with the highlight —
         both are set there, so a Livewire morph that re-runs the paint restores the state
         and the styling hook together rather than one of them. --}}
    aria-selected="false"
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
                {{-- The component, not a copy of it. The hand-rolled chip reproduced most of
                     kbd's classes and dropped two: `border-b-2`, which is the keycap edge that
                     makes it read as a key, and the `resolveClasses('kbd', …)` seam a developer
                     overrides to retheme every shortcut chip at once. Neither absence is
                     visible next to the other chips until you put them side by side. --}}
                <x-wirekit::kbd size="sm">{{ $key }}</x-wirekit::kbd>
            @endforeach
        </span>
    @endif

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
