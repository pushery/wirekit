{{-- optimistic-ui: n/a — passthrough
     A navigation entry that may carry a developer action; the action's result is not this component's. --}}
@props([
    'href' => '#',
    'active' => false,
    'icon' => null,
    'submenu' => false,
    // A trailing counter/dot (an unread badge) — a count or short string renders a
    // pill AFTER the label, OUTSIDE the truncating span so it is never clipped, and
    // stays visible in the collapsed rail.
    'badge' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $active = BooleanProp::from($active, false);
    $submenu = BooleanProp::from($submenu, false);

    // also consume `data-current` /
    // `data-current="true"` if the caller passed it via the attribute
    // bag (Livewire 4 emits this on `wire:navigate` links automatically).
    // Without this fallback the developer has to manually pass
    // `:active="request()->is('posts*')"` on every sidebar item,
    // duplicating routing knowledge that's already encoded in the
    // route file. We OR-merge: explicit `:active` always wins; if the
    // caller didn't pass `active` but did pass `data-current="true"`,
    // the item highlights.
    if (! $active) {
        $dataCurrent = $attributes->get('data-current');
        if ($dataCurrent === true || $dataCurrent === 'true' || $dataCurrent === '1' || $dataCurrent === 'page') {
            $active = true;
        }
    }

    // Individual nav link. Active items get a highlighted background and
    // aria-current="page" so AT announces "current page, <label>".
    $classes = WireKit::resolveClasses('sidebar.item', 'base', implode(' ', [
        // `relative` is the containing block the collapsed-rail counter dot
        // positions against — without it the dot would anchor to the nav.
        'relative flex items-center gap-[var(--padding-wk-x-sm)]',
        // Collapse-to-icon rail: center the lone icon when the sidebar collapses.
        'group-data-[collapsed]/wk-sidebar:justify-center',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'rounded-[var(--radius-wk-md)]',
        // The RESTING foreground is scoped to non-active items for the same reason
        // the hover below is, and it is not optional. Unscoped, this and the active
        // block's `text-[color:var(--color-wk-text)]` are both bare single-class
        // selectors — specificity (0,1,0), same layer — so the winner is decided by
        // EMISSION ORDER, and Tailwind v4 sorts arbitrary color utilities by value:
        // `--color-wk-text` comes before `--color-wk-text-muted`, so the muted rule
        // is emitted last and wins. The active item rendered muted, never the
        // emphasized foreground the block below documents.
        //
        // Worse than a no-op: a developer retinting the active state with their own
        // (0,1,0) utility loses to this one too, so the escape hatch was `!important`.
        // Do not "simplify" the variant off — equal specificity is the whole problem.
        'not-[[aria-current]]:text-[color:var(--color-wk-text-muted)]',
        // Hover is scoped to NON-active items via `:not([aria-current])`. An active item
        // already carries `aria-current="page"`, and an UNSCOPED `hover:bg` here (specificity
        // 0,2,0) would override a retinted active block (a developer's 0,1,0 utilities) the
        // instant the pointer arrives — the pill would snap back to muted mid-hover, forcing
        // the developer to reach for `!important`. Scoping matches the common expectation too:
        // the current page does not react to hover, it is already the target state.
        'not-[[aria-current]]:hover:bg-[var(--color-wk-bg-muted)]',
        'not-[[aria-current]]:hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
    ]), $scope);

    // Active state gets different styling — emphasized foreground and a
    // subtle background tint. Merged via $attributes->class conditional.
    // The icon's own size, as a named block rather than a literal in the render call.
    //
    // It was written straight into the `svg()` helper, which put it out of reach of
    // every personalization route at once — there was no block name to address. The
    // cost shows up only once delta personalization exists: an application that wants
    // a 16px icon has to take over the SURROUNDING block and reach the icon through a
    // descendant selector, and a taken-over block stops inheriting improvements
    // silently. It still renders; it renders the version from the day it was copied.
    //
    // So the literal was not merely untidy — it forced the one outcome the delta form
    // was built to avoid.
    $iconClasses = WireKit::resolveClasses('sidebar.item', 'icon', 'w-5 h-5', $scope);

    $activeClasses = WireKit::resolveClasses('sidebar.item', 'active', implode(' ', [
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'font-[number:var(--font-wk-body-weight)]',
    ]), $scope);

    // Auto-inject rel="noopener noreferrer" and SR hint when target="_blank".
    // CAREFUL: $attributes->merge(['rel' => ...]) treats rel as a DEFAULT —
    // if the caller passed their own rel (even rel="prev"), theirs wins and
    // our auto-injection would silently fail, re-introducing tabnabbing.
    // To force-override, we remove rel from the bag and render it separately
    // whenever we have a computed value.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<a
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $activeClasses => $active]) }}
>
    @if($icon)
        {{-- Icon — decorative; the label is the accessible name. A bare name
             string ("cube") resolves via the WireKit icon system, consistent
             with dropdown.item / context-menu.item / command-palette.item. A
             <x-slot:icon> or inline markup (a non-string ComponentSlot, which
             is Htmlable) renders verbatim, preserving the documented slot
             contract — so both `icon="cube"` and `<x-slot:icon>` now work. --}}
        <span class="shrink-0" aria-hidden="true">
            @if(is_string($icon) && ! str_contains($icon, '<') && function_exists('svg'))
                {{ svg(WireKit::icon($icon), ['class' => $iconClasses]) }}
            @else
                {{ $icon }}
            @endif
        </span>
    @endif
    {{-- In a collapsed rail the label becomes sr-only — visually hidden but
         still the link's accessible name (the icon is decorative). --}}
    <span class="flex-1 truncate group-data-[collapsed]/wk-sidebar:sr-only">{{ $slot }}</span>
    {{-- Trailing counter (an unread badge). Rendered OUTSIDE the truncating label
         so a long label can never clip it, and pushed to the end with ml-auto.

         In the collapsed rail it becomes a dot. The digits have no room at rail
         width, but the counter must not simply vanish either — that is where an
         unread signal matters most. Going `absolute` is what makes this work:
         the dot leaves the flex row entirely, so the lone icon keeps the exact
         center the rail gives it. Sharing the row (the shipped behavior) pushed
         the icon visibly off-center, which is what the browser test measures.

         The digits move to sr-only rather than being hidden, so the count stays
         part of the link's accessible name in BOTH states — a screen-reader user
         gets no icon cue at all and would otherwise lose the information
         outright. Note this differs from hiding the counter and announcing it
         separately; adopting `badge` over a hand-rolled counter changes what
         assistive technology reads out. Documented on the component page. --}}
    @if(filled($badge))
        <span class="shrink-0 ml-auto inline-flex items-center justify-center px-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]
            group-data-[collapsed]/wk-sidebar:absolute
            group-data-[collapsed]/wk-sidebar:top-[var(--padding-wk-y-sm)]
            group-data-[collapsed]/wk-sidebar:right-[var(--padding-wk-x-sm)]
            group-data-[collapsed]/wk-sidebar:ml-0
            group-data-[collapsed]/wk-sidebar:p-0
            group-data-[collapsed]/wk-sidebar:h-2
            group-data-[collapsed]/wk-sidebar:w-2"><span class="group-data-[collapsed]/wk-sidebar:sr-only">{{ $badge }}</span></span>
    @endif
    @if($opensNewTab)
        <span class="sr-only">{{ __('(opens in new tab)') }}</span>
    @endif
    @if($submenu)
        {{-- Submenu indicator — signals a flyout or sub-navigation exists.
             Purely visual hint; only shown when the developer opts in via :submenu="true". --}}
        <svg class="w-3.5 h-3.5 shrink-0 text-[color:var(--color-wk-text-subtle)] wk-submenu-indicator group-data-[collapsed]/wk-sidebar:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    @endif
</a>
