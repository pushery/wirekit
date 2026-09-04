{{-- optimistic-ui: n/a — sub-component
     It is not a component at all but an @include shared by the two branches of
     sidebar.blade.php. It renders zones and a slot; nothing here has a value a server
     could accept or refuse. --}}
{{-- The sidebar's body, shared by the collapsible and the plain branch.

     Included via @include('wirekit::components.partials.sidebar-zones') — the
     including view's scope is visible here, so `$hasZones`, `$header`, `$footer`
     and `$slot` arrive without being passed. It lives in a partial because the
     two branches of `sidebar.blade.php` render the same body, and a copy in each
     is a copy that will drift: the zone mechanics below are subtle enough that
     the second copy would be the one missing `min-h-0` a year from now.

     WHY THE SCROLL REGION NEEDS NO tabindex/role/label OF ITS OWN. The house rule
     for a generic `overflow-y-auto` container is that it must be keyboard
     reachable (WCAG 2.1.1) — normally `tabindex="0"` plus `role="region"` and a
     name. This one is exempt under the landmark shape: it is a direct child of
     the `<nav>` landmark, which carries its own accessible name, and every item
     inside it is a focusable `<a>`. Browser landmark navigation reaches the
     region and Tab reaches its contents, so the extra annotation would add a
     second stop that announces nothing new. Same shape as the reading table of
     contents, whose scrolling list sits directly inside its labeled nav. --}}
@if($hasZones)
    @isset($header)
        {{-- shrink-0: the head must keep its height when the list is long. Without
             it a flex item is allowed to compress, and a brand row silently loses
             pixels as the navigation grows.

             The inline padding matches what `sidebar.item` and `sidebar.group`
             apply to themselves. Without it the head sat flush against the
             column's edge while everything below it looked inset — a brand name
             a few pixels left of the navigation it belongs to, which reads as a
             layout fault rather than a choice.

             Applied on the ZONE rather than asked of the caller: the alignment
             is a property of the column, and a developer supplying a brand row
             should not have to know which token the items happen to use. --}}
        {{-- A chrome band placed in this zone makes the zone drop its own inset entirely —
             see the `:has()` rule in dist/wirekit.css. The zone does not have to publish that
             inset any more: it simply stops applying it, which is one fewer variable and one
             fewer thing that can be read wrong. --}}
        <div @class([
            'shrink-0',
            'px-[var(--padding-wk-x-sm)]' => $zoneInset ?? true,
        ])>
            {{ $header }}
        </div>
    @endisset

    {{-- min-h-0 is the whole trick, and leaving it out is the mistake this zone
         exists to prevent: a flex item's automatic minimum size is its content, so
         without it the middle refuses to shrink, the column grows past the
         sidebar, and the head and foot scroll away with the list. The symptom
         reads as "the scroll region is missing" when in fact everything scrolled. --}}
    {{-- The scrolling middle, with the same edge affordance sticky-panel uses.
         A scrollbar alone is easy to miss, especially on a track that fades when
         idle — the shadow says "there is more this way" in peripheral vision.

         Sentinel-driven rather than masked: a mask dims the edge whether or not
         anything follows, so the last item stays greyed out once you have
         reached it. Two one-pixel sentinels and an IntersectionObserver mean the
         shadow appears exactly when there is somewhere left to scroll.

         The wrapper is `relative` because the overlays are absolutely positioned
         siblings of the scroller — painted ABOVE the content, so a hovered row at
         the edge cannot cover the affordance. --}}
    {{-- The shadow apparatus is separable from the zones, and it was not.
         A developer with their own edge treatment could only be rid of ours by
         dropping `header` and `footer` — which also gives up the sticky head and
         foot, an unrelated capability. `scroll-shadows="false"` keeps the zones,
         the scroller and its scrollbar, and leaves the edge alone. --}}
    @if($scrollShadows)
    <div class="relative flex flex-col min-h-0 flex-1" x-data="wirekitStickyPanelShadows()">
        <div
            x-ref="scroller"
            data-wk-sidebar-scroller
            class="min-h-0 flex-1 overflow-y-auto flex flex-col py-[var(--padding-wk-y-sm)] wk-scrollbar"
        >
            {{-- `-mb-px` cancels the sentinel's OWN height. It has to stay one pixel tall
                 for the observer to report it reliably — a zero-height target is not
                 dependably "intersecting" — but that pixel is real layout, and it put the
                 first row one pixel below the rail's. The negative margin gives the
                 observer its pixel and the reader none. --}}
            <div x-ref="topSentinel" aria-hidden="true" class="h-px shrink-0 -mb-px"></div>
            {{-- The rows carry the rhythm, NOT the scroller. When the gap sat on the
                 scroller the two sentinels were flex children of it and took part: an
                 invisible 1px marker plus one gap pushed the whole list down, so the
                 first row sat 17px below the rule where the rail's sat at 6px. The
                 sentinels are still where the observer needs them — inside the scroll
                 container — they simply no longer space anything. --}}
            <div class="flex flex-col gap-[var(--space-wk-nav-gap)]">{{ $slot }}</div>
            <div x-ref="bottomSentinel" aria-hidden="true" class="h-px shrink-0 -mt-px"></div>
        </div>
        <div aria-hidden="true" x-cloak x-show="topShadow" x-transition.opacity class="wk-scroll-shadow-top"></div>
        <div aria-hidden="true" x-cloak x-show="bottomShadow" x-transition.opacity class="wk-scroll-shadow-bottom"></div>
    </div>
    @else
    {{-- Same scroller, same `min-h-0` — the sentinels and the two overlays are
         what go, not the scroll region. Marked so a developer's own affordance
         has something to attach to without reaching for a class name. --}}
    <div
        data-wk-sidebar-scroller
        class="min-h-0 flex-1 overflow-y-auto flex flex-col py-[var(--padding-wk-y-sm)] wk-scrollbar"
    >
        <div class="flex flex-col gap-[var(--space-wk-nav-gap)]">{{ $slot }}</div>
    </div>
    @endif

    @isset($footer)
        {{-- Same alignment as the head, for the same reason: an account row is
             the bottom of the same column and belongs on the same vertical line
             as the items above it. --}}
        {{-- `$collapsible`, and it has to be the gate rather than `isset($collapseBtnClasses)`.
             The classes are resolved unconditionally at the top of `sidebar.blade.php`, so
             that test is true for EVERY column and the control was rendered beside the footer
             of a plain, non-collapsible sidebar too. Two things followed, and only one of them
             is visible: the button does nothing, because there is no rail state to toggle —
             and its three Alpine bindings (`:aria-expanded`, `:aria-label`, the chevron's
             `:class`) all read `collapsed`, which only the collapsible branch's `x-data`
             declares. Outside it every one of them throws `collapsed is not defined` on boot,
             on a page that otherwise looks correct. Same condition the column-level call site
             in `sidebar.blade.php` uses, read from the other side of the footer test. --}}
        @php $footHostsToggle = ($collapsible ?? false) && ($toggle ?? 'none') !== 'none'; @endphp
        <div @class([
            'shrink-0',
            // `wk-shell-foot` — the marker the shell's foot line reads. Both columns of a
            // shell carry it, and it is what lets a page put their two rules on one line
            // without either column knowing the other exists.
            'wk-shell-foot',
            // Containing block for the control below, which rides ON this band rather than
            // taking a row of its own.
            'relative' => $footHostsToggle,
            // Trailing room for the control, on the row it stands beside.
            //
            // `nth-last-child(2)`, not `last-child`: the control is out of flow but still
            // the last CHILD, so `last-child` pads the button itself — measured, it grew
            // from 28px to 60px and pushed itself off its own anchor.
            //
            // Withdrawn when the column is a rail: there the control drops back onto its
            // own line under the avatar, and the room is not owed.
            '[&>*:nth-last-child(2)]:pe-[2.25rem]' => $footHostsToggle,
            'group-data-[collapsed]/wk-sidebar:[&>*:nth-last-child(2)]:pe-0' => $footHostsToggle,
            'px-[var(--padding-wk-x-sm)]' => $zoneInset ?? true,
        ])>
            {{ $footer }}
            @if($footHostsToggle)
                @include('wirekit::components.partials.sidebar-collapse-toggle')
            @endif
        </div>
    @endisset
@else
    {{-- No zones: the slot used to inherit its row rhythm from the column's own flex
         gap. That gap is gone (it also spaced the zones, which is what pushed the
         first row down), so the rhythm is stated here instead of inherited. --}}
    {{-- `flex-1` so the slot absorbs the column's free height and the collapse control
         below it still lands at the bottom. It used to get there on `mt-auto` against the
         column's own row gap; that gap is gone, and without this the control would sit
         directly under the last item of a short list. --}}
    <div class="flex flex-col flex-1 gap-[var(--space-wk-nav-gap)]">{{ $slot }}</div>
@endif
