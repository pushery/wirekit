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
             pixels as the navigation grows. --}}
        <div class="shrink-0">
            {{ $header }}
        </div>
    @endisset

    {{-- min-h-0 is the whole trick, and leaving it out is the mistake this zone
         exists to prevent: a flex item's automatic minimum size is its content, so
         without it the middle refuses to shrink, the column grows past the
         sidebar, and the head and foot scroll away with the list. The symptom
         reads as "the scroll region is missing" when in fact everything scrolled. --}}
    <div class="min-h-0 flex-1 overflow-y-auto flex flex-col gap-[var(--space-wk-sm)] wk-scrollbar">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="shrink-0">
            {{ $footer }}
        </div>
    @endisset
@else
    {{ $slot }}
@endif
