{{-- optimistic-ui: n/a — passthrough
     A navigation entry that may carry a developer action; the action's result is not this component's. --}}
@props([
    'href' => '#',
    'active' => false,
    // What the active entry is to a screen reader: `page` for the page itself, the default,
    // `location` for an entry that leads the section the page lies in, `true` for neither.
    // The highlight is the same for all three.
    'current' => 'page',
    'icon' => null,
    'submenu' => false,
    // A trailing counter/dot (an unread badge) — a count or short string renders a
    // pill AFTER the label, OUTSIDE the label span so a long name can never push it out, and
    // stays visible in the collapsed rail.
    'badge' => null,
    'scope' => null,
    // The identity of this row when the parent sidebar is in `selection` mode. Ignored
    // in the default navigation mode, where `href` is the identity.
    'value' => null,
])

{{-- Inherited from the parent <x-wirekit::sidebar>. A row cannot know on its own which
     ARIA contract it is part of, and asking the caller to repeat the mode on every row is
     the kind of duplication that goes wrong on row nine. --}}
@aware([
    'mode' => 'navigation',
    'selected' => null,
    // Whether the column this row sits in can ever become an icon rail. A sidebar that
    // cannot collapse never hides this label, so it needs no tooltip — and wrapping it
    // anyway would put a second element around every navigation row in every classic
    // sidebar in the fleet, for a state those sidebars cannot reach. It is the SIDEBAR's
    // value: `sidebar.group` has a `collapsible` of its own that folds its rows, and the
    // class behind the group keeps that one out of this search.
    'collapsible' => false,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar.item', $attributes->getAttributes());
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into the bag the link partial
    // echoes and renders as a stray HTML attribute on the <a>/<button>. `selected`
    // is the one that bites hardest: it IS a real HTML attribute, just not on these
    // elements, so it reads as intentional to anyone looking at the DOM.
    $attributes = $attributes->except(['mode', 'selected', 'collapsible']);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $selectionMode = $mode === 'selection';

    // WHICH attribute marks "this is the one", and it is not cosmetic which one.
    //
    // The three variants below exist because the muted rule and the active rule have
    // EQUAL specificity and Tailwind emits the muted one last — see the block comment on
    // the first of them. In `selection` mode no row carries `aria-current` at all, so a
    // variant keyed on it matches EVERY row, and the chosen one renders muted along with
    // the rest. The mechanism is right; only the attribute it watches has to follow the
    // contract the column is actually under.
    // Both sets are written out in full, and that is not verbosity.
    //
    // Interpolating the attribute into the class name would read well and be silently
    // wrong: Tailwind scans SOURCE for complete class names, so a name assembled at runtime
    // is a name it never sees, the rules would not be generated, and every row would
    // render unstyled in the direction the variant exists to control, with nothing in the
    // markup or the ARIA looking wrong.
    $notCurrentClasses = $selectionMode
        ? [
            'not-[[aria-selected=true]]:text-[color:var(--color-wk-text-muted)]',
            'not-[[aria-selected=true]]:hover:bg-[var(--color-wk-bg-muted)]',
            'not-[[aria-selected=true]]:hover:text-[color:var(--color-wk-text)]',
        ]
        : [
            'not-[[aria-current]]:text-[color:var(--color-wk-text-muted)]',
            'not-[[aria-current]]:hover:bg-[var(--color-wk-bg-muted)]',
            'not-[[aria-current]]:hover:text-[color:var(--color-wk-text)]',
        ];

    // WHERE THE KEYBOARD IS — a third mark, and it has to be a third one.
    //
    // In `selection` mode the listbox keeps DOM focus on the column and moves an
    // `aria-activedescendant` instead, so the browser draws nothing on the option
    // and the indicator has to be authored. It was not: ArrowDown moved the
    // pointer, `scrollIntoView` sometimes moved the list, and nothing on screen
    // said which row the reader was on.
    //
    // A RING rather than a fill, because the fill is taken. The chosen row already
    // renders `$activeClasses` — `bg-[var(--color-wk-bg-muted)]` — so painting the
    // keyboard's position with that same token would make the chosen row and the
    // marked row look identical, which is the one distinction the two states exist
    // to draw. scope-switcher can use the fill precisely because nothing else there
    // does. `--color-wk-ring` clears contrast on a chosen row and on an ordinary one,
    // in both modes.
    //
    // `data-active`, written by `writeActiveMarker()` in sidebar-listbox.js the way
    // scope-switcher.js writes it — one attribute per move, no observer, because the
    // options are re-read on demand anyway. Both writers of the marked row route
    // through it, `initListbox()` as well as `markActive()`: the id and the attribute
    // are one state, and splitting them is silent — the announcement stays correct
    // while the highlight sits on the wrong row. Written out in full for the reason
    // the block above gives: Tailwind scans source for complete class names.
    $keyboardActiveClasses = $selectionMode
        ? [
            'data-[active]:ring-[length:var(--ring-wk-width)]',
            'data-[active]:ring-inset',
            'data-[active]:ring-[var(--color-wk-ring)]',
        ]
        : [];

    $active = BooleanProp::from($active, false);
    $ariaCurrent = \Pushery\WireKit\WireKit::validateProp('sidebar.item', 'current', (string) $current, ['page', 'location', 'true']);
    $submenu = BooleanProp::from($submenu, false);

    // A caller may mark the item current with `data-current` in the attribute bag
    // (`true`, `"true"`, `"1"` or `"page"`) instead of `:active`; an explicit `:active`
    // always wins. Livewire's own `data-current` on a `wire:navigate` link is written in
    // the browser after the page loads and never reaches this bag, so a server render
    // still needs one of the two, for example `:active="request()->is('posts*')"`.
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
        // The row height must not depend on which child happens to be tallest.
        //
        // Without this the row would be sized by its label while expanded and by its icon
        // while collapsed, because the label carries `sr-only` in the rail and `sr-only` is
        // a 1px box. A web font's line box is taller than the icon, so a folding rail would
        // change height and move everything under it.
        //
        // `1lh` is the item's own line box, so the floor tracks the type ramp and the
        // `--font-scale-wk` accessibility bump instead of pinning a length. Chrome 109 /
        // Safari 16.4 / Firefox 120 — inside the support floor, not above it.

        'min-h-[calc(1lh_+_var(--padding-wk-y-sm)_*_2)]',
        // Derived from the container rather than fixed — see dist/wirekit.css. In a card
        // sidebar the concentric answer is 4px, not the 8px of a flat radius.
        'rounded-[var(--radius-wk-nav-item)]',
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
        ...$notCurrentClasses,
        ...$keyboardActiveClasses,
        // Hover is scoped to NON-active items via `:not([aria-current])`. An active item
        // already carries `aria-current="page"`, and an UNSCOPED `hover:bg` here (specificity
        // 0,2,0) would override a retinted active block (a developer's 0,1,0 utilities) the
        // instant the pointer arrives — the pill would snap back to muted mid-hover, forcing
        // the developer to reach for `!important`. Scoping matches the common expectation too:
        // the current page does not react to hover, it is already the target state.
        'focus-visible:outline-hidden',
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

@php
    // Server-rendered selection, so the column is correct before Alpine boots. Alpine
    // then owns it: `x-bind` below wins over this literal the moment it initializes.
    $isSelected = $selectionMode && $value !== null && (string) $value === (string) $selected;

    // A stable id, because `aria-activedescendant` points at one and a random id per
    // render would break the pointer on every Livewire morph — the marker would name an
    // element that no longer exists, and the announcement would simply stop.
    $optionId = $selectionMode
        ? \Pushery\WireKit\Support\DomId::unique(
            $value !== null ? 'wk-sidebar-option-'.\Illuminate\Support\Str::slug((string) $value) : null,
            'wk-sidebar-option-'
        )
        : null;

    // `href` is refused rather than ignored. A link inside a listbox is not a smaller
    // problem than a missing role: the row would be in the tab order, Enter would
    // navigate instead of choosing, and the one-tab-stop promise of the pattern would be
    // broken by a row the caller thought was decorative.
    // `$href` rather than the attribute bag: `href` is a declared prop, so Blade has
    // already lifted it out of the bag and `$attributes->has('href')` is false for every
    // caller — the check would have been dead in exactly the case it exists for.
    if ($selectionMode && $href !== '#' && $href !== null && config('app.debug')) {
        $hrefInOptionWarning = '[wirekit] sidebar.item: `href` is ignored inside a '
            .'`mode="selection"` sidebar. A listbox option is a choice, not a destination — '
            .'a link here would put the row in the tab order and make Enter navigate instead '
            .'of select. Use `value` and your own click handler.';
    }
@endphp

{{-- The tooltip is the LABEL for a reader holding a pointer, and only then.

     `disabled` is bound to live state rather than decided at render: the sidebar
     collapses in the browser, so a render-time decision would either show the bubble
     beside a perfectly visible caption or never show it at all. Same binding the app
     rail uses, for the same reason.

     `focusable-trigger="false"` because the row is already focusable and already named;
     a second tab stop in front of every navigation entry would be the cure being worse.

     `w-full` keeps the wrapper from narrowing the row it wraps. No display class sits beside
     it: the tooltip keeps its own `inline-block`, and a second one in this attribute would be
     decided by the stylesheet's order, not by the attribute. --}}
@if($collapsible)
    <x-wirekit::tooltip
        :text="trim(strip_tags((string) $slot))"
        placement="right"
        focusable-trigger="false"
        class="w-full"
        {{-- `$data`, not a bare `collapsed`. The rail state lives on the sidebar, and `@aware`
             answers with the nearest ancestor that was called with `collapsible`: that is the
             sidebar unless something between them was called with the name too, such as an
             application's own wrapper component. Where no scope defines `collapsed`, `$data`
             yields `undefined` and the tooltip stays disabled, where a bare identifier would
             make Alpine throw on every row. --}}
        x-bind:data-wk-tooltip-disabled="! $data.collapsed"
    >@include('wirekit::components.partials.sidebar-item-link')</x-wirekit::tooltip>
@else
    @include('wirekit::components.partials.sidebar-item-link')
@endif

@if(isset($hrefInOptionWarning))
    <div x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from($hrefInOptionWarning) }} })" hidden></div>
@endif
