{{-- optimistic-ui: n/a — client-only
     Layout state — which panes are open at this viewport. --}}
@props([
    'scope' => null,
    // Viewport-fixer mode. Default (false) keeps `min-h-screen` — the shell grows
    // with its content and the PAGE scrolls as a document. When true, the root is
    // pinned to the viewport (`h-dvh overflow-hidden`) so the inner sidebar + main
    // regions scroll INTERNALLY (brand pinned top, account menu pinned bottom) — the
    // classic fixed-height admin-shell case. `dvh` (not `vh`) so the mobile browser
    // toolbar collapse is handled.
    'viewport' => false,
    // Whether the in-flow sidebar column keeps its top and inline-start inset.
    // True (default) is the card layout: the sidebar sits as a panel inside a
    // padded column. False is the full-bleed navigation column — the sidebar meets
    // the top and the inline-start edge of the shell with nothing between.
    //
    // A prop because the two utilities that produce the inset were literals on the
    // <aside>, and the attribute bag lands on the ROOT element — so they were
    // unreachable from a call site by any means except a CSS rule targeting the
    // vendor's own marker class and out-specifying Tailwind by emission order.
    // Pair it with the sidebar's own `variant="flush"`: this removes the gap around
    // the column, that removes the card inside it. (Named without its tag on
    // purpose — a component tag written inside this comment block is compiled by
    // Blade, not ignored, and takes the whole component down with a 500.)
    'sidebarInset' => true,
    // Where the header slot renders. `shell` (default) spans the full width above
    // the sidebar row. `content` puts it INSIDE the content column, so the sidebar
    // runs the full height and the topbar begins beside it — the other common admin
    // layout, and previously reachable only by hiding the header slot at lg and
    // hand-placing a topbar in the default slot, which cost the header slot on
    // desktop entirely and cost every developer the same discovery.
    //
    // A `sticky` HEADER STOPS WORKING IN THIS MODE, and the reason is structural rather
    // than a bug to fix here. `position: sticky` sticks to the nearest scrolling ancestor;
    // in `content` mode the header's nearest one is the content column, which carries
    // `overflow-hidden` (see the comment on that element below) and therefore never
    // scrolls. Nothing sticks to a container that does not move. In `shell` mode the
    // header is outside that column, so its scrolling ancestor is the page and sticky
    // behaves as written.
    //
    // With `viewport` the question dissolves rather than being answered: the main region
    // becomes the scroller and the header sits above it without moving at all, so it needs
    // no sticky. Reaching for `sticky` there is harmless and does nothing.
    //
    // (The component tag for that region is deliberately NOT written here. Blade is a text
    // preprocessor and does not know it is inside a PHP comment — an `x-wirekit::` tag in
    // one gets COMPILED, and the compiled component construct lands in the middle of this
    // array. The failure is "Undefined variable $component", which points at the render
    // rather than at the sentence that caused it.)
    'headerPlacement' => 'shell',
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('app-shell', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `viewport="false"` would otherwise flip the mode on. Normalize against the
    // prop's own default so a cast never engages a mode that was meant off.
    $viewport = BooleanProp::from($viewport, false);
    $sidebarInset = BooleanProp::from($sidebarInset, true);

    $headerPlacement = in_array($headerPlacement, ['shell', 'content'], true) ? $headerPlacement : 'shell';

    // The inset, as the two utilities it always was — now behind a name. Only at lg+:
    // on mobile the sidebar is an off-canvas overlay anchored flush from the top, so
    // an inset there would leave a strip of backdrop above it.
    $asideInset = $sidebarInset
        ? 'lg:mt-[var(--space-wk-md,1rem)] lg:ml-[var(--padding-wk-x-lg)]'
        : '';

    // App Shell — orchestrates header + sidebar + main layout.
    // Uses CSS grid to position sidebar and main content area.
    // Default to `w-full` so the shell fills its parent in any layout
    // wrapper (raw page, docs preview, sandbox iframe). Without it, a
    // bare block-level `display:flex` div collapses to its intrinsic
    // content width inside prose / preview ancestors — making the
    // header + main visually too narrow with a wide gutter on the right.
    // Height: `min-h-screen` (default, document-scroll) vs `h-dvh overflow-hidden`
    // (viewport-fixer). Only in the fixed-height mode does the inner
    // `flex flex-1 overflow-hidden` row get a bounded height to distribute, so the
    // sidebar/main internal scroll regions finally engage — the `viewport` prop
    // replaces the old manual `style="height: ..."` override developers had to write.
    $classes = WireKit::resolveClasses('app-shell', 'base', implode(' ', [
        'flex flex-col w-full',
        $viewport ? 'h-dvh overflow-hidden' : 'min-h-screen',
        'bg-[var(--color-wk-bg)]',
        'text-[color:var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

<div
    x-data="{ sidebarOpen: false }"
    {{ $attributes->class([$classes]) }}
>
    @if($headerPlacement === 'shell')
        @isset($header)
            {{ $header }}
        @endisset
    @endif

    {{-- `relative` makes THIS the containing block for the overlay layer below.
         Without it the drawer and its backdrop measure themselves against
         whatever ancestor happens to be one — the viewport in a full-page app,
         but any element with `contain`, `transform`, `filter` or `perspective`
         when the shell is embedded. Measured: the drawer came out 64px above
         the shell and as tall as that ancestor instead of the shell. Owning the
         context makes the two coincide everywhere. --}}
    <div class="relative flex flex-1 overflow-hidden">
        @isset($sidebar)
            {{-- Mobile backdrop --}}
            <div
                x-show="sidebarOpen"
                x-on:click="sidebarOpen = false"
                x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 z-[calc(var(--z-wk-sticky)+1)] bg-black/50 lg:hidden"
                aria-hidden="true"
                x-cloak
            ></div>

            {{-- Sidebar — `lg:mt-{md}` adds breathing room between the header
                 divider and the sidebar's inner card. Stays unset on mobile
                 (the off-canvas overlay anchors flush from the top) and
                 only applies once the sidebar is in its in-flow column
                 position at lg+. --}}
            <aside
                {{-- `invisible` when closed, and it carries two fixes at once.
                     A drawer parked at -100% is only off-SCREEN while something
                     clips it, and `position: fixed` is clipped by the viewport
                     ONLY while no ancestor is its containing block. `contain:
                     layout` makes an ancestor exactly that (so does a transform
                     or a filter), and then -100% means 256px left of THAT box —
                     which paints, in full, wherever that box happens to sit.
                     Measured on a docs preview surface carrying `contain:
                     layout` and `overflow: visible`.
                     The second fix is the one that was always a defect: a
                     translated drawer is still focusable and still in the
                     accessibility tree, so keyboard users tab into a menu they
                     cannot see. `visibility: hidden` removes it from both.
                     `visibility` is in the transition list so the slide-OUT is
                     still seen: when either end of the interpolation is
                     `visible`, that value holds for the whole duration. --}}
                x-bind:class="sidebarOpen ? 'translate-x-0 visible' : '-translate-x-full invisible lg:visible'"
                {{-- wk-app-shell-aside: on lg the dist/wirekit.css rule sizes this column
                     to the inner sidebar's width (var(--wk-sidebar-w,16rem)), and shrinks it
                     to the 3.5rem icon rail when the sidebar is data-collapsed, so the main
                     content reflows instead of leaving a gap. w-64 stays the mobile overlay width.
                     lg:transition-[width] animates the column in sync with the sidebar's own
                     transition-[width] (compositor cost is a one-shot deliberate toggle, mirroring
                     the sidebar — kept out of dist so the shipped-CSS web-vitals guard stays clean). --}}
                {{-- `max-lg:[&>*]:…` — below the breakpoint the sidebar is a DRAWER,
                     and a drawer is not a card.
                     The `card` variant is deliberately as tall as its content and
                     rounded, which is right for a padded column and wrong for a
                     panel that slides in over the page. Measured with the drawer
                     open at 393px: the aside filled the shell's full 256px and the
                     thing a reader actually SEES was 85px of it, with a 10px
                     radius — a floating card dropped on the content.
                     That gap is also why the first version of the guard passed
                     while this was broken: it measured the aside, which is a
                     transparent container, instead of the panel inside it.
                     Scoped with `max-lg` so it touches the drawer only; above the
                     breakpoint the card treatment is untouched rather than
                     overridden and restored.

                     `max-lg:bg-…` closes the half this file got wrong twice. The
                     note above says the aside is "a transparent container" — and
                     that was left true, so a sidebar whose own variant paints
                     nothing produced a drawer you could read the page through.
                     `variant="flush"` is exactly that: no surface of its own,
                     which is CORRECT above the breakpoint, because flush means
                     the shell's background IS the column's. Below it the panel
                     slides over the content, and nothing is behind it.
                     The background belongs HERE rather than on the sidebar,
                     because the shell is what decides the column becomes an
                     overlay — the sidebar cannot know. It sits under the card
                     variant's own surface, so that case renders identically. --}}
                class="wk-app-shell-aside max-lg:bg-[var(--color-wk-bg-elevated)] max-lg:[&>*]:h-full max-lg:[&>*]:rounded-none absolute inset-y-0 left-0 z-[calc(var(--z-wk-sticky)+2)] w-64 transform transition-[transform,visibility] duration-[var(--transition-wk-duration)] lg:relative lg:translate-x-0 lg:z-auto lg:transition-[width] {{ $asideInset }}"
                x-cloak
                class:lg="!x-cloak"
            >
                {{ $sidebar }}
            </aside>
        @endisset

        @if($headerPlacement === 'content')
            {{-- The content column owns its own topbar, so the sidebar beside it runs
                 the full height of the shell.

                 min-w-0 is load-bearing: a flex item's automatic minimum is its
                 content, so a wide table or a long unbroken string inside the slot
                 would push this column past the shell and squeeze the sidebar rather
                 than scrolling within itself. --}}
            <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
                @isset($header)
                    {{ $header }}
                @endisset

                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
