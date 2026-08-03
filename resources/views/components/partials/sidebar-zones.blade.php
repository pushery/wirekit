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
        <div class="shrink-0 px-[var(--padding-wk-x-sm)]">
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
    <div class="relative flex flex-col min-h-0 flex-1" x-data="wirekitStickyPanelShadows()">
        <div
            x-ref="scroller"
            class="min-h-0 flex-1 overflow-y-auto flex flex-col gap-[var(--space-wk-sm)] wk-scrollbar"
        >
            <div x-ref="topSentinel" aria-hidden="true" class="h-px shrink-0"></div>
            {{ $slot }}
            <div x-ref="bottomSentinel" aria-hidden="true" class="h-px shrink-0"></div>
        </div>
        <div aria-hidden="true" x-cloak x-show="topShadow" x-transition.opacity class="wk-scroll-shadow-top"></div>
        <div aria-hidden="true" x-cloak x-show="bottomShadow" x-transition.opacity class="wk-scroll-shadow-bottom"></div>
    </div>

    @isset($footer)
        {{-- Same alignment as the head, for the same reason: an account row is
             the bottom of the same column and belongs on the same vertical line
             as the items above it. --}}
        <div class="shrink-0 px-[var(--padding-wk-x-sm)]">
            {{ $footer }}
        </div>
    @endisset
@else
    {{ $slot }}
@endif
