{{-- optimistic-ui: n/a — passthrough
     It is the whole rendered body of `sidebar/item.blade.php` and carries that page's
     claim unchanged: the row spreads the attribute bag, so any server action on it is
     the developer's own `wire:*`, written at the call site and never seen here. --}}
{{-- The sidebar row itself — extracted so a tooltip can wrap it.

     A collapsed rail hides the label with `sr-only`: the accessible name survives, and
     a pointer gets nothing at all. Four rows of identical icons name nothing to anyone
     holding a mouse, and the rail one component over already solved this by wrapping
     its link in a tooltip. This partial is what makes the same wrapping possible here
     without duplicating eighty lines of link.

     Included from `sidebar/item.blade.php` and nowhere else; every variable it reads
     comes from that scope. --}}
@if($selectionMode)
    <div
        role="option"
        id="{{ $optionId }}"
        @if($value !== null) data-wk-option-value="{{ $value }}" @endif
        aria-selected="{{ $isSelected ? 'true' : 'false' }}"
        x-bind:aria-selected="isSelected({{ \Pushery\WireKit\Support\AlpinePayload::from($value === null ? null : (string) $value) }}) ? 'true' : 'false'"
        x-on:click="selectOption($el)"
        {{-- No `aria-current`, and that is the point of the mode rather than an omission.
             A page you are on and a value you have picked are two different claims; a row
             that made both would be wrong about one of them. --}}
        {{ $attributes->except(['rel', 'href'])->class([$classes, $activeClasses => $isSelected]) }}
    >
@else
<a
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $activeClasses => $active]) }}
>
@endif
    @if($icon)
        {{-- Icon — decorative; the label is the accessible name. A bare name
             string ("cube") resolves via the WireKit icon system, consistent
             with dropdown.item / context-menu.item / command-palette.item. A
             <x-slot:icon> or inline markup (a non-string ComponentSlot, which
             is Htmlable) renders verbatim, preserving the documented slot
             contract — so both `icon="cube"` and `<x-slot:icon>` now work. --}}
        <span class="shrink-0" aria-hidden="true">
            @if(is_string($icon) && ! str_contains($icon, '<') && function_exists('svg'))
                {{ svg(\Pushery\WireKit\WireKit::icon($icon), ['class' => $iconClasses]) }}
            @else
                {{ $icon }}
            @endif
        </span>
    @endif
    {{-- In a collapsed rail the label becomes sr-only — visually hidden but
         still the link's accessible name (the icon is decorative). --}}
    {{-- WRAPS, never truncates. A navigation entry whose name is clipped does not name
         anything — "Terminal set…" is not a destination, and the reader cannot tell it from
         its neighbor without hovering. Maintainer's rule, and it is absolute.
         `break-words` rather than plain wrapping, because a single long word has no space to
         break at and would otherwise overflow the column instead of wrapping inside it. --}}
    <span class="flex-1 break-words group-data-[collapsed]/wk-sidebar:sr-only group-data-[settling]/wk-sidebar:sr-only">{{ $slot }}</span>
    {{-- Trailing counter (an unread badge). Rendered OUTSIDE the label span so a long
         name wraps beside it rather than pushing it out, and pushed to the end with ml-auto.

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
            group-data-[collapsed]/wk-sidebar:w-2"><span class="group-data-[collapsed]/wk-sidebar:sr-only group-data-[settling]/wk-sidebar:sr-only">{{ $badge }}</span></span>
    @endif
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
    @if($submenu)
        {{-- Submenu indicator — signals a flyout or sub-navigation exists.
             Purely visual hint; only shown when the developer opts in via :submenu="true". --}}
        <svg class="w-3.5 h-3.5 shrink-0 text-[color:var(--color-wk-text-subtle)] wk-submenu-indicator group-data-[collapsed]/wk-sidebar:hidden group-data-[settling]/wk-sidebar:hidden" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
        </svg>
    @endif
@if($selectionMode)
    </div>
@else
</a>
@endif
