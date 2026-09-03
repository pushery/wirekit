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
    // The content column as an INSET rounded surface, floating on the shell's own
    // background rather than meeting it edge to edge. The shape three of the reference
    // consoles use: the chrome (rail, second column, top rule) is one surface and the
    // content sits on it like a sheet, its inline-start corners rounded.
    //
    // lg only, and that is not a shortcut. Below the breakpoint the navigation is an
    // off-canvas drawer and the content is the whole viewport — a rounded inset there
    // would spend horizontal room a phone does not have on a decoration nothing is
    // beside.
    'panel' => false,
    // The shell's own chrome surface. `default` leaves the shell on --color-wk-bg, which is
    // every existing app-shell and stays byte-identical. The other tones point the root at
    // the same `--color-wk-rail-*` roles the rail and a toned sidebar read, so a shell, its
    // rail and its second column become one continuous chrome surface with the content panel
    // sitting on top of it. A flush sidebar inside a toned shell follows automatically — it
    // paints nothing of its own, so the chrome IS its surface, and its contents are remapped
    // with it.
    //
    // It is the same token set on purpose: three components that must agree about a
    // color should not each own a copy of it.
    'tone' => 'default',
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
    $panel = BooleanProp::from($panel, false);

    // NO `accent`, for the same reason the sidebar's scale stops there: a toned shell's
    // chrome shows through any flush navigation column standing on it, so that column's
    // ordinary contents follow the chrome's tokens — and the accent tone's hover and active
    // states INVERT, which a generic component cannot follow because it paints its foreground
    // and its hover surface from two tokens it cannot switch together.
    //
    // `accent` remains available on the RAIL, whose items switch both at once. That is also
    // how every reference console is built: the colored surface is the rail, and the chrome
    // around it is neutral or dark.
    $tone = WireKit::validateProp('app-shell', 'tone', $tone, ['default', 'muted', 'inverse']);

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
        // An untoned shell keeps the literal it always had, rather than routing through
        // the role token with a fallback. The two resolve to the same color today, and
        // that is exactly why: a shell nobody toned must not start depending on a
        // variable a developer might repoint for their rail.
        $tone === 'default' ? 'bg-[var(--color-wk-bg)]' : 'bg-[var(--color-wk-rail-bg)]',
        $tone === 'default' ? 'text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-rail-text)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // The content column's surface when `panel` is set. It paints --color-wk-bg
    // explicitly rather than inheriting, because the whole point is that it differs
    // from the chrome behind it — on an untoned shell the two match and the radius
    // simply is not seen, which is the correct outcome for a shell that asked for a
    // panel it has no contrast for.
    //
    // `overflow-hidden` is what makes the radius actually clip its content. Overlays are
    // unaffected: dropdown, tooltip, modal and drawer all teleport to the overlay root,
    // so nothing that opens from the first row is cut off. The browser test asserts that
    // rather than trusting it.
    $contentPanelClasses = $panel
        ? implode(' ', [
            // AN EVEN INSET ON ALL FOUR SIDES, and rounded on all four corners.
            //
            // It used to round only the inline-start corners and sit flush against the
            // chrome, and that shape does not hold up: a curve needs something to curve away
            // FROM. With the panel's top and bottom edges flush to the shell, the two rounded
            // corners opened a wedge of chrome beside the rail's straight edge — read,
            // correctly, as a gap rather than as a sheet. Closing that gap and giving
            // the panel a margin of its own are the same requirement seen twice.
            //
            // An even inset answers both at once. The space stops being a wedge that appears
            // at two corners and becomes a deliberate margin that is the same on every side —
            // which is what makes the panel read as a sheet lying on the chrome, the shape all
            // three reference consoles use.
            //
            // The SAME token the rail's own panel variant insets by, so a floating rail and a
            // floating content panel sit on one grid rather than two.
            'lg:m-[var(--space-wk-sm,0.5rem)]',
            'lg:rounded-[var(--radius-wk-shell-panel)]',
            'lg:overflow-hidden',
            // No border. With a real gap around it the tone difference IS the boundary, and a
            // border would be a third line in a place that already had one too many: the flush
            // rail's own edge sat 1px away from this one, doubling it.
            'bg-[var(--color-wk-bg)]',
            'text-[color:var(--color-wk-text)]',
        ])
        : '';

    // WHO OWNS THE GAP BESIDE THE CONTENT PANEL.
    //
    // A `panel` shell lifts its content column off the chrome with a real gap, and the column
    // next to it is a navigation column whose horizontal rules are the SAME line as the
    // panel's own — level with it, drawn by the same component, reading the same height token.
    // With the gap between them that one line arrives at the seam and stops eight pixels
    // short. Measured on the expandable rail shell: the rail's rules end at x=252, the
    // panel's begin at x=260, both at y=431. Reported three times, and not as a preference.
    //
    // So the gap moves INSIDE the navigation column: the column grows by it, insets its own
    // contents by the same amount, and the panel gives up its inline-start margin. Nothing
    // moves — the modules sit exactly where they sat, the panel starts exactly where it
    // started — and the rule now reaches the panel because the column does.
    //
    // It is also the completion of a decision this file already made rather than a reversal
    // of one: the column ALREADY treated that gap as its own trailing space, which is why it
    // gave up its trailing inset. It just did not own the pixels.
    //
    // Narrow on purpose. A `variant="panel"` rail is a floating card whose gap is the point,
    // and a shell whose panel-adjacent column is the SIDEBAR is a shape this library ships no
    // example of — neither takes the gutter, and for both the panel keeps its margin exactly
    // as before. The rail's variant is read from the rendered slot because that is the only
    // thing a shell can see of what it was handed.
    $navTakesPanelGutter = $panel
        && isset($rail)
        && ! isset($sidebar)
        && ! str_contains((string) $rail, 'data-variant="panel"');

    // A drawer nobody can open.
    //
    // Below `lg` this shell moves its navigation off-canvas — a decision the shell makes, not
    // one the developer asked for — and the panel is then reachable only through `sidebarOpen`.
    // If nothing in the slots sets that, the phone gets a shell with no navigation at all: on a
    // 375px screen, eleven controls behind a panel with no handle. Nothing renders wrong, no
    // error appears, and the desktop layout it was built against is untouched, so it survives
    // every look that is not taken on a phone. Seven of this library's own examples shipped
    // exactly that.
    //
    // The check reads the slots as strings because that is the only thing the shell can see:
    // slot content is captured before this view runs, so a `<x-wirekit::sidebar.toggle>`
    // anywhere inside them has already left its marker. A hand-wired opener counts too — what
    // matters is that something writes to `sidebarOpen`, not which component did it.
    //
    // Debug only, and it never throws: a shell whose navigation is genuinely elsewhere (a
    // bottom bar, a command palette) is a real composition, and a fatal here would break it.
    if ((bool) config('app.debug') && (isset($sidebar) || isset($rail))) {
        $slotsAsRendered = (string) ($header ?? '')
            .(string) ($slot ?? '')
            .(string) ($sidebar ?? '')
            .(string) ($rail ?? '');

        if (! str_contains($slotsAsRendered, 'data-wk-sidebar-toggle') && ! str_contains($slotsAsRendered, 'sidebarOpen')) {
            logger()->warning(
                'wirekit::app-shell: this shell turns its navigation into an off-canvas drawer below the '
                .'lg breakpoint, and nothing in its slots opens that drawer — so on a phone the navigation '
                .'is unreachable. Add <x-wirekit::sidebar.toggle class="lg:hidden" /> to the header slot, '
                .'or set `sidebarOpen` from a control of your own.'
            );
        }
    }
    // The drawer's id, so the toggle's `aria-controls` has something to name.
    //
    // `DomId::unique` and NOT `Str::random`, which is what this line said first. A random
    // id is minted afresh on every render, and inside a Livewire morph or a `wire:poll`
    // region that means the two halves stop naming each other while both remain perfectly
    // well-formed. `dropdown` and `progress` each carried that exact defect and each says
    // so in its own comment; this file does not need to learn it a third time.
    //
    // The counted fallback is unique by construction, so two shells on one page still get
    // two ids — which was the reason the random version looked right.
    $drawerId = \Pushery\WireKit\Support\DomId::unique(null, 'wk-shell-nav-');

    // WHAT THE DRAWER BINDINGS FALL BACK TO WHILE THE PANEL IS NOT A DIALOG.
    //
    // `null` is the obvious answer and it is a destructive one. Alpine does not read a null
    // binding as "leave the attribute as the server wrote it" — `bindAttribute` tests the
    // value against `[null, undefined, false]` and calls `removeAttribute`, and the only
    // names it spares are `aria-pressed`, `aria-checked`, `aria-expanded` and
    // `aria-selected`.
    //
    // On the single-column shell those bindings and the slot's own attribute bag land on the
    // SAME aside. So `x-slot:sidebar role="complementary" aria-label="Section navigation"` was
    // written correctly by the server and then deleted the moment Alpine initialized — above
    // the breakpoint always, below it whenever the drawer was closed. Markup that is right
    // in view-source and gone by the first paint is the worst version of this: the seam
    // looks like it works, and the only reader who finds out is the one using it.
    //
    // The console branch does NOT have the defect and must not be changed to match: there
    // the bindings sit on the drawer GROUP and the bags on the two aside elements inside it,
    // so the two never write the same attribute.
    //
    // Resolved into the expression as a literal rather than seeded as a scope key, because
    // both routes into that scope are closed. The factory returns a fixed object and drops
    // any key it does not name, so an unknown one leaves the identifier undefined and the
    // binding throws where it should have fallen back. And an `x-data` of its own on the
    // aside would make IT the closest root — which is where `x-ref` registers, while `$refs`
    // walks upward only — so `$refs.drawer` would go undefined in the factory and the focus
    // trap would silently never arm.
    //
    // `role` and `aria-label` only. `aria-modal` and `tabindex` keep their null fallback on
    // purpose: both describe the dialog state itself rather than the column, and an authored
    // `tabindex` on a layout column is a tab stop that leads nowhere.
    $sidebarSlotAttributes = \Pushery\WireKit\Support\SlotAttributes::of($sidebar ?? null);

    // Read through the helper, never as `$sidebar->attributes`. A slot does not always
    // arrive as an object carrying a bag — that is the whole reason `SlotAttributes` exists,
    // and where it does not, the direct property read is a fatal rather than an empty value.
    // The three call sites further down already go through it for the same reason.
    $sidebarAuthoredRole = $sidebarSlotAttributes->get('role');
    $sidebarAuthoredLabel = $sidebarSlotAttributes->get('aria-label');

    // Anything that is not a non-empty string keeps the literal `null` the binding resolved
    // to before — a valueless `role` arrives as `true` and is not a role name, and a bound
    // `:role="null"` never reaches the markup either.
    //
    // Through `AlpinePayload::string()` with NO quotes around it. This is a caller-supplied
    // value entering an expression Alpine evaluates as JavaScript, which is the one position
    // where hand-quoting it is an injection hole rather than a style choice: `{{ }}` escapes
    // the quote and the browser decodes it back before Alpine ever reads the attribute.
    $sidebarDrawerRoleFallback = is_string($sidebarAuthoredRole) && $sidebarAuthoredRole !== ''
        ? \Pushery\WireKit\Support\AlpinePayload::string($sidebarAuthoredRole)
        : 'null';

    $sidebarDrawerLabelFallback = is_string($sidebarAuthoredLabel) && $sidebarAuthoredLabel !== ''
        ? \Pushery\WireKit\Support\AlpinePayload::string($sidebarAuthoredLabel)
        : 'null';
@endphp

<div
    x-data="wirekitAppShell({ drawerId: {{ \Pushery\WireKit\Support\AlpinePayload::from($drawerId) }} })"
    {{-- The stable way to find this shell from a test or a developer's own script.
         Its three siblings below are conditional, and the `x-data` expression above is
         not an identity: a browser case matched the literal "sidebarOpen" inside it and
         went dark the moment that state moved into a factory, while the shell rendered
         exactly as before. Seventy-three components already carry such a hook. --}}
    data-wk-app-shell
    @if($tone !== 'default') data-wk-tone="{{ $tone }}" @endif
    @if($panel) data-wk-panel @endif
    @if($navTakesPanelGutter) data-wk-nav-gutter @endif
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
        @if(isset($sidebar) || isset($rail))
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
        @endif

        @isset($rail)
            {{-- The console layout: a module rail, then the module's own navigation.

                 The two columns travel as ONE off-canvas panel below the breakpoint, and
                 that is the whole reason this branch exists rather than a second copy of
                 the aside above. Sliding them independently gives two panels that overlap
                 at different offsets during the transition and land as a seam; grouping
                 them makes the drawer a single object whose width is simply the sum of its
                 columns. Above the breakpoint the group is `contents`, so the two asides
                 become direct flex children of the shell row again and nothing about the
                 desktop layout goes through this wrapper at all. --}}
            <div
                {{-- `max-lg:bg-…` — A DRAWER IS AN OPAQUE SHEET, and this one was see-through.
                     The rail paints its own surface, so the left strip looked right; the
                     navigation column beside it is `variant="flush"`, which means "no surface
                     of my own" — correct standing next to content, where the shell's own
                     background is what shows, and wrong sliding over a page, where what shows
                     is the page. Measured in the open drawer at 375px: the rail
                     `rgb(255, 255, 255)`, the 256px column beside it `rgba(0, 0, 0, 0)`. The
                     reader saw the article's text through the menu.
                     On the GROUP rather than on the column, because the group is the drawer:
                     one sheet whose width is the sum of its columns, and one surface under
                     both of them. It reads the rail's role so a toned shell keeps its chrome
                     color rather than showing a light panel beside a dark rail; on the
                     default theme that role and the elevated surface are the same value.
                     `max-lg` only: above the breakpoint this wrapper is `display: contents`
                     and paints nothing at all, which is exactly right — there is no drawer
                     there to be opaque.
                     The single-column branch below has carried this since it was reported the
                     first time. This branch never got it, and every mobile check here asks
                     about reachability or geometry — never about opacity. --}}
                class="wk-app-shell-nav max-lg:bg-[var(--color-wk-rail-bg,var(--color-wk-bg-elevated))] absolute inset-y-0 left-0 z-[calc(var(--z-wk-sticky)+2)] flex transform transition-[transform,visibility] duration-[var(--transition-wk-duration)] lg:contents"
                {{-- `invisible` when closed carries two fixes at once, both learned in the
                     browser on the single-column form above. A drawer parked at -100% is
                     only off-SCREEN while something clips it, and `position: fixed`/absolute
                     is clipped by its containing block ONLY while no ancestor has become
                     one — `contain: layout`, a transform or a filter each make an ancestor
                     exactly that, and then -100% means "left of THAT box", which paints in
                     full wherever that box sits. The second is the one that was always a
                     defect: a translated drawer is still focusable and still in the
                     accessibility tree, so keyboard users tab into a menu they cannot see.
                     `visibility` is in the transition list so the slide-OUT is still seen —
                     when either end of the interpolation is `visible`, that value holds for
                     the whole duration. --}}
                x-bind:class="sidebarOpen ? 'translate-x-0 visible' : '-translate-x-full invisible lg:visible'"
                {{-- Dialog semantics, and ONLY where it is a dialog. Below the breakpoint
                     this element slides over the page behind a backdrop; at and above it,
                     it is layout — `display: contents` for the group, an ordinary column
                     for the single aside — and a `role` there would announce a dialog
                     nobody opened.
                     That is why the width is asked of `matchMedia` in the factory instead
                     of being expressed as a `lg:` class: `role` and `aria-modal` are
                     attributes, and an attribute has one value at a time however wide the
                     window is.
                     Measured by an adopting application at 375px with the drawer open,
                     before this existed: `role` null, `aria-modal` null, `aria-controls`
                     null, and Escape did nothing. The backdrop blocks POINTERS from the
                     page behind it and does not block the keyboard, so focus walked on
                     through controls the backdrop was covering — invisible to everyone
                     except the people who cannot see where focus went. --}}
                x-ref="drawer"
                {{-- Written by the server, not bound through Alpine. The id is known at
                     render time, so a binding would only make it appear once Alpine has
                     initialized — and until then the toggle's `aria-controls` names an
                     element that does not exist. The factory still carries the same value
                     so the toggle can read it from scope. --}}
                id="{{ $drawerId }}"
                :role="isDrawer && sidebarOpen ? 'dialog' : null"
                :aria-modal="isDrawer && sidebarOpen ? 'true' : null"
                :aria-label="isDrawer && sidebarOpen ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Navigation')) }} : null"
                {{-- `tabindex="-1"` while it is a dialog, and it is not decoration: it is what
                     lets the panel RECEIVE focus. `focus-trap` looks for a tabbable element at
                     activation and falls back to the container when it finds none — and a
                     container without a tabindex cannot take focus, so the trap reports itself
                     active while `document.activeElement` is still `<body>`.
                     Measured exactly that way in a browser: trap present, `active: true`, two
                     focusable elements in the panel, focus on BODY. Every ESM case was green
                     throughout, because they construct the state machine and this is a question
                     the DOM answers.
                     Only while it is a drawer: a `tabindex` on an ordinary layout column adds a
                     stop to the tab order that leads nowhere. --}}
                :tabindex="isDrawer && sidebarOpen ? '-1' : null"
                x-cloak
            >
                {{-- `role="presentation"` by DEFAULT, and the slot's own attributes can take it
                     back. Both of these columns are layout: the landmark is the `<nav>` inside
                     them, which already carries its own name. Left as bare `<aside>` elements
                     they are two implicit `complementary` regions with no accessible name, and
                     axe reports `landmark-unique` on any application that follows the console
                     blueprint — not on a misuse of it, on the documented composition.

                     A default LABEL was the other candidate and is worse: it satisfies the rule
                     by turning one redundant landmark into two named redundant landmarks, and a
                     screen-reader user then has two more regions to skip past that describe
                     nothing but the layout.

                     The attribute bag is what makes this a seam rather than a decision imposed
                     on every application: `<x-slot:rail role="complementary" aria-label="…">`
                     restores a real landmark where one is genuinely wanted. Before this the bag
                     was dropped, so that call site did nothing at all. --}}
                <aside {{ \Pushery\WireKit\Support\SlotAttributes::of($rail)->merge(['role' => 'presentation'])->class(['wk-app-shell-rail flex shrink-0']) }}>
                    {{ $rail }}
                </aside>
                @isset($sidebar)
                    {{-- `w-64` is the drawer width; at lg the shared
                         `aside.wk-app-shell-aside` rule in dist/wirekit.css takes over and
                         tracks the inner sidebar's own width, including its collapsed rail.
                         `max-lg:[&>*]:…` because below the breakpoint the sidebar is part of
                         a DRAWER, and a drawer is not a card: the `card` variant is
                         deliberately as tall as its content and rounded, which is right for
                         a padded column and reads as a floating sheet dropped on the page
                         when it slides in over one. --}}
                    <aside {{ \Pushery\WireKit\Support\SlotAttributes::of($sidebar)->merge(['role' => 'presentation'])->class(['wk-app-shell-aside max-lg:[&>*]:h-full max-lg:[&>*]:rounded-none w-64 shrink-0']) }}>
                        {{ $sidebar }}
                    </aside>
                @endisset
            </div>
        @elseif(isset($sidebar))

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
                {{-- Dialog semantics, and ONLY where it is a dialog. Below the breakpoint
                     this element slides over the page behind a backdrop; at and above it,
                     it is layout — `display: contents` for the group, an ordinary column
                     for the single aside — and a `role` there would announce a dialog
                     nobody opened.
                     That is why the width is asked of `matchMedia` in the factory instead
                     of being expressed as a `lg:` class: `role` and `aria-modal` are
                     attributes, and an attribute has one value at a time however wide the
                     window is.
                     Measured by an adopting application at 375px with the drawer open,
                     before this existed: `role` null, `aria-modal` null, `aria-controls`
                     null, and Escape did nothing. The backdrop blocks POINTERS from the
                     page behind it and does not block the keyboard, so focus walked on
                     through controls the backdrop was covering — invisible to everyone
                     except the people who cannot see where focus went. --}}
                x-ref="drawer"
                {{-- Written by the server, not bound through Alpine. The id is known at
                     render time, so a binding would only make it appear once Alpine has
                     initialized — and until then the toggle's `aria-controls` names an
                     element that does not exist. The factory still carries the same value
                     so the toggle can read it from scope. --}}
                id="{{ $drawerId }}"
                {{-- THE FALLBACK IS THE SLOT'S OWN VALUE, not `null`, and that is the whole
                     difference between a seam and a seam that survives to the first paint.
                     This element carries the drawer bindings AND the slot's attribute bag, so
                     a null here does not leave what the server wrote — Alpine removes the
                     attribute outright. `<x-slot:sidebar role="complementary" aria-label="…">`
                     rendered correctly and was deleted at init, above the breakpoint and with
                     the drawer closed alike.
                     The drawer's own semantics still win while it is open: what covers the
                     page is a dialog, whatever the column beside the content was called. The
                     rest of the time the call site's landmark is what stands.
                     Both fallbacks are resolved in the PHP block at the top of this file, which
                     is also where the reason they are literals rather than scope keys is
                     written down. (Named in prose rather than by its directive on purpose:
                     Blade pairs the opening and closing words of that block across everything
                     between them, so either one written loose in a comment can swallow the
                     markup below it into a raw block.) --}}
                :role="isDrawer && sidebarOpen ? 'dialog' : {{ $sidebarDrawerRoleFallback }}"
                :aria-modal="isDrawer && sidebarOpen ? 'true' : null"
                :aria-label="isDrawer && sidebarOpen ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Navigation')) }} : {{ $sidebarDrawerLabelFallback }}"
                {{-- `tabindex="-1"` while it is a dialog, and it is not decoration: it is what
                     lets the panel RECEIVE focus. `focus-trap` looks for a tabbable element at
                     activation and falls back to the container when it finds none — and a
                     container without a tabindex cannot take focus, so the trap reports itself
                     active while `document.activeElement` is still `<body>`.
                     Measured exactly that way in a browser: trap present, `active: true`, two
                     focusable elements in the panel, focus on BODY. Every ESM case was green
                     throughout, because they construct the state machine and this is a question
                     the DOM answers.
                     Only while it is a drawer: a `tabindex` on an ordinary layout column adds a
                     stop to the tab order that leads nowhere. --}}
                :tabindex="isDrawer && sidebarOpen ? '-1' : null"
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
                {{-- The slot's own attributes reach this column too, so naming it or re-roling
                     it works the same way it does in the console branch above. What this one
                     does NOT get is a `presentation` default: there is only one column here, so
                     nothing collides, and a lone complementary region beside the content is a
                     reasonable thing for an application to have. Changing that silently would
                     take a landmark away from every sidebar-only shell that ships today. --}}
                {{ \Pushery\WireKit\Support\SlotAttributes::of($sidebar)->class(['wk-app-shell-aside max-lg:bg-[var(--color-wk-bg-elevated)] max-lg:[&>*]:h-full max-lg:[&>*]:rounded-none absolute inset-y-0 left-0 z-[calc(var(--z-wk-sticky)+2)] w-64 transform transition-[transform,visibility] duration-[var(--transition-wk-duration)] lg:relative lg:translate-x-0 lg:z-auto lg:transition-[width] '.$asideInset]) }}
                x-cloak
            >
                {{ $sidebar }}
            </aside>
        @endif

        @if($headerPlacement === 'content')
            {{-- The content column owns its own topbar, so the sidebar beside it runs
                 the full height of the shell.

                 min-w-0 is load-bearing: a flex item's automatic minimum is its
                 content, so a wide table or a long unbroken string inside the slot
                 would push this column past the shell and squeeze the sidebar rather
                 than scrolling within itself. --}}
            <div data-wk-content-panel class="{{ trim('flex min-w-0 flex-1 flex-col overflow-hidden '.$contentPanelClasses) }}">
                @isset($header)
                    {{ $header }}
                @endisset

                {{ $slot }}
            </div>
        @elseif($contentPanelClasses !== '')
            {{-- `panel` with the header above the columns still needs a surface to round,
                 and the default slot is not one — it is whatever the developer put there.
                 Wrapping it is the only way to give the content column an edge, and the
                 wrapper is `flex flex-col` so a `<x-wirekit::main>` inside still stretches
                 exactly as it does unwrapped. Emitted ONLY when a panel was asked for, so
                 every shell that did not ask keeps its original DOM. --}}
            <div data-wk-content-panel class="{{ trim('flex min-w-0 flex-1 flex-col overflow-hidden '.$contentPanelClasses) }}">
                {{ $slot }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
