@props([
    'scope' => null,
    // Viewport-fixer mode. Default (false) keeps `min-h-screen` — the shell grows
    // with its content and the PAGE scrolls as a document. When true, the root is
    // pinned to the viewport (`h-dvh overflow-hidden`) so the inner sidebar + main
    // regions scroll INTERNALLY (brand pinned top, account menu pinned bottom) — the
    // classic fixed-height admin-shell case. `dvh` (not `vh`) so the mobile browser
    // toolbar collapse is handled.
    'viewport' => false,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `viewport="false"` would otherwise flip the mode on. Normalize against the
    // prop's own default so a cast never engages a mode that was meant off.
    $viewport = BooleanProp::from($viewport, false);

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
    @isset($header)
        {{ $header }}
    @endisset

    <div class="flex flex-1 overflow-hidden">
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
                class="fixed inset-0 z-[calc(var(--z-wk-sticky)+1)] bg-black/50 lg:hidden"
                aria-hidden="true"
                x-cloak
            ></div>

            {{-- Sidebar — `lg:mt-{md}` adds breathing room between the header
                 divider and the sidebar's inner card. Stays unset on mobile
                 (the off-canvas overlay anchors flush from the top) and
                 only applies once the sidebar is in its in-flow column
                 position at lg+. --}}
            <aside
                x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
                {{-- wk-app-shell-aside: on lg the dist/wirekit.css rule sizes this column
                     to the inner sidebar's width (var(--wk-sidebar-w,16rem)), and shrinks it
                     to the 3.5rem icon rail when the sidebar is data-collapsed, so the main
                     content reflows instead of leaving a gap. w-64 stays the mobile overlay width.
                     lg:transition-[width] animates the column in sync with the sidebar's own
                     transition-[width] (compositor cost is a one-shot deliberate toggle, mirroring
                     the sidebar — kept out of dist so the shipped-CSS web-vitals guard stays clean). --}}
                class="wk-app-shell-aside fixed inset-y-0 left-0 z-[calc(var(--z-wk-sticky)+2)] w-64 transform transition-transform duration-[var(--transition-wk-duration)] lg:relative lg:translate-x-0 lg:z-auto lg:transition-[width] lg:mt-[var(--space-wk-md,1rem)] lg:ml-[var(--padding-wk-x-lg)]"
                x-cloak
                class:lg="!x-cloak"
            >
                {{ $sidebar }}
            </aside>
        @endisset

        {{ $slot }}
    </div>
</div>
