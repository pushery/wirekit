{{-- optimistic-ui: n/a — client-only
     Whether the mobile menu is open. --}}
{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'variant' => config('wirekit.components.navbar.variant', 'default'),
    'sticky' => config('wirekit.components.navbar.sticky', false),
    // When true the navbar skips the `md:` viewport-breakpoint classes and
    // renders the mobile layout unconditionally (hamburger button visible,
    // desktop item row hidden). Useful for (a) previewing the mobile state
    // in docs without resizing the browser, (b) dedicated mobile app views,
    // and (c) embedding the navbar inside a container that is narrower than
    // the 768px breakpoint. Defaults to `false` so existing developers keep
    // the responsive behavior.
    'forceMobile' => false,
    // `container` — when true, wraps the inner flex-row in a max-width
    // container so the navbar's CHROME (background, border, sticky
    // behavior) stays edge-to-edge while the CONTENT (brand, nav items,
    // actions) aligns with the body's container-wrapped column. Mirrors
    // the brand-bar `container` prop. Default false preserves the prior
    // edge-to-edge content behavior.
    'container' => false,
    // `max` — container max-width tier when `container=true`. One of
    // `sm/md/lg/xl/2xl/full`. Defaults to `xl` (the most common
    // marketing-landing-page content width). Reads the same
    // `--size-wk-container-*` tokens as the container component so
    // navbar + body align on the same vertical content-edge spine.
    'max' => null,
    // What the entries do below the `md` breakpoint. `menu` (the default) folds them behind a
    // toggle. `scroll` keeps them as one row at every width that scrolls sideways when it is
    // too long, and brings the entry for the current page (`aria-current="page"`, what an
    // `active` item carries) into view: a bar someone switches between screens with, who wants
    // to see it rather than open it.
    'mobile' => 'menu',
    // How tightly the entries sit. `compact` sets them in the small text size with less padding,
    // and draws the entries you are not on in the muted text color. `navbar.item` reads it from
    // here, so one attribute on the bar sets every entry.
    'density' => 'default',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('navbar', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sticky = BooleanProp::from($sticky, false);
    $forceMobile = BooleanProp::from($forceMobile, false);
    $container = BooleanProp::from($container, false);

    $max ??= config('wirekit.components.navbar.max', 'xl');

    $mobile = WireKit::validateProp('navbar', 'mobile', $mobile, ['menu', 'scroll']);
    $scroll = $mobile === 'scroll';
    WireKit::validateProp('navbar', 'density', $density, ['default', 'compact']);

    // Navbar — opinionated top navigation bar with responsive mobile menu.
    // Variants: default (with bottom border), bordered, transparent, sticky.
    $navClasses = WireKit::resolveClasses('navbar', 'base', implode(' ', [
        'w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-elevated)]',
    ]), $scope);

    $variantClasses = match ($variant) {
        'bordered' => 'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] shadow-[var(--shadow-wk-sm)]',
        'transparent' => 'bg-transparent',
        default => 'border-b border-[var(--color-wk-border)]',
    };

    // The CHROME layer, not the shared sticky one. A popover opened in page content is a
    // teleported panel at --z-wk-dropdown (50), and at --z-wk-sticky (40) this bar would be
    // painted over — arithmetic, not a quirk. A panel anchored INSIDE
    // this bar opens downward and never overlaps it, so nothing is lost; a modal is still
    // above, which is correct.
    // Sticks BELOW a strip above the page (`--wk-strip-inset`, 0px without one), not under it.
    $stickyClasses = $sticky ? 'sticky top-[var(--wk-strip-inset,0px)] z-[var(--z-wk-chrome)]' : '';

    $isContainerWrapped = filter_var($container, FILTER_VALIDATE_BOOL);
    // No hardcoded fallback values — the `--size-wk-container-*` tokens
    // are the canonical source of truth and ship in dist/wirekit.css.
    /*
     * The cap grows by the padding it would otherwise swallow.
     *
     * This element carries `px-[var(--padding-wk-x-lg)]` AND the cap, and under
     * `box-sizing: border-box` the cap eats that padding — so the visible content edge
     * lands at (viewport - tier) / 2 + padding. `main`, `hero`, `cta` and `footer` all
     * use the opposite shape (padding on the outer element, cap on an inner one), so
     * their content edge is (viewport - tier) / 2. With the plain cap, a container-wrapped
     * navbar would sit exactly one `--padding-wk-x-lg` inside the spine every other
     * page-edge component sits on.
     *
     * Widening the cap by that padding puts the content box on the tier, which is what
     * the prop is understood to mean: the brand lines up with the content column below.
     *
     * Tailwind arbitrary values take no spaces, hence `calc(a+2*b)` unspaced.
     */
    $maxClass = $isContainerWrapped
        ? match (WireKit::validateProp('navbar', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full'])) {
            'sm' => 'max-w-[calc(var(--size-wk-container-sm)+2*var(--padding-wk-x-lg))] mx-auto',
            'md' => 'max-w-[calc(var(--size-wk-container-md)+2*var(--padding-wk-x-lg))] mx-auto',
            'lg' => 'max-w-[calc(var(--size-wk-container-lg)+2*var(--padding-wk-x-lg))] mx-auto',
            'xl' => 'max-w-[calc(var(--size-wk-container-xl)+2*var(--padding-wk-x-lg))] mx-auto',
            '2xl' => 'max-w-[calc(var(--size-wk-container-2xl)+2*var(--padding-wk-x-lg))] mx-auto',
            'full' => 'max-w-full',
        }
        : '';

    $containerClasses = WireKit::resolveClasses('navbar', 'container', implode(' ', array_filter([
        // A scrolling row never wraps under the brand: it is one line at every width, and the
        // row itself gives way. The gap keeps it off the brand and the actions it sits between.
        $scroll ? 'flex flex-nowrap items-center justify-between gap-[var(--gap-wk-md)]' : 'flex flex-wrap items-center justify-between',
        'px-[var(--padding-wk-x-lg)]',
        // `min-h` below the breakpoint, a fixed height above it. The navigation list is one
        // node now and becomes a full-width second line when the disclosure opens, so the row
        // has to be able to grow — while a bar with a closed disclosure is the same 64px it
        // has always been.
        'min-h-16 md:h-16',
        $maxClass,
    ])), $scope);

    // When $forceMobile is on the desktop row is always hidden and the
    // hamburger is always shown; otherwise we use the `md:` breakpoint
    // classes so the layout is responsive.
    // ONE navigation list, laid out two ways — a horizontal row beside the brand above the
    // breakpoint, a full-width stack under it below.
    //
    // One list, not a row in the bar plus a copy in the disclosure: a slot is rendered once
    // into a string, so echoing it twice makes every id inside it exist twice. Harmless for
    // plain links, which carry none; not harmless for a dropdown in the list, whose panel id,
    // `aria-controls` and Alpine scope would all appear twice, with the visible trigger
    // opening a panel anchored to the hidden copy. The actions cluster below is rendered
    // once for the same reason.
    $navListClasses = $forceMobile
        ? implode(' ', [
            'w-full order-last flex-col items-stretch gap-1',
            'border-t border-[var(--color-wk-border)]',
            'mt-[var(--padding-wk-y-md)] pt-[var(--padding-wk-y-md)] pb-[var(--padding-wk-y-md)]',
        ])
        : implode(' ', [
            'w-full order-last flex-col items-stretch gap-1',
            'border-t border-[var(--color-wk-border)]',
            'mt-[var(--padding-wk-y-md)] pt-[var(--padding-wk-y-md)] pb-[var(--padding-wk-y-md)]',
            'md:w-auto md:order-none md:flex-row md:items-center md:flex-1 md:ml-[var(--padding-wk-x-lg)]',
            'md:border-t-0 md:mt-0 md:pt-0 md:pb-0',
        ]);
    // One actions cluster, in the bar, at every width, and the "one" is the point. Hiding
    // it below `md` and re-emitting the slot in the disclosure would look like a responsive
    // move and be a duplication: every id inside the slot would exist twice, and a visible
    // account trigger would open its panel at the viewport origin, anchored to the hidden
    // copy, whose box is 0x0. A disclosure would also stack the actions in a column, turning
    // a bell icon into a full-width row, where icons want to stand beside each other.
    //
    // One render avoids both. An action cluster is icon-sized by convention — that is
    // what an actions slot in a bar is for — and beside the hamburger it fits the narrowest
    // supported width with room to spare.
    $actionsClasses = 'flex items-center gap-[var(--gap-wk-sm)]';
    $hamburgerClasses = $forceMobile ? '' : 'md:hidden';

    // The `mobile-menu` personalization key survives the merge of the two containers and now
    // resolves the one list. Same override point, same subject — what the disclosure reveals.
    $navListClasses = WireKit::resolveClasses('navbar', 'mobile-menu', $navListClasses, $scope);

    // The scrolling row. `min-w-0 flex-1` lets it take what the brand and the actions leave and
    // no more, which is what makes it scroll instead of pushing them off the bar. Each entry keeps
    // its width and its one line; `wk-navbar-scroll` hides the scrollbar (see dist/wirekit.css),
    // and the row stays operable by touch, wheel and Tab, which scrolls each link into view.
    // With no scrollbar, `wk-scroll-fade` fades the edge that has entries behind it: the row's
    // script names that edge in `data-fade` as it scrolls, and without the script there is none.
    $scrollListClasses = WireKit::resolveClasses('navbar', 'scroll', implode(' ', [
        'wk-navbar-scroll',
        'wk-scroll-fade',
        'flex flex-row flex-nowrap items-center gap-1',
        'min-w-0 flex-1',
        'overflow-x-auto overscroll-x-contain',
        '[&>*]:shrink-0 [&>*]:whitespace-nowrap',
    ]), $scope);

    // The disclosure's id, and the string the hamburger's `aria-controls` points at.
    // Both were the literal `wk-navbar-mobile`, which is correct for exactly one navbar per
    // page — and a page with two (a `forceMobile` demo beside a live bar, a marketing header
    // above an app bar, several previews in one document) shipped duplicate ids, so every
    // hamburger on it resolved to the FIRST menu and the second bar's button announced a
    // disclosure it does not own.
    //
    // The base is passed to the shared registry rather than replaced by a generated id: the
    // registry hands the FIRST sight of a base back verbatim and only appends `-2`, `-3`, …
    // to the ones that follow. So a page with a single navbar renders byte-identically to
    // before — which matters, because this string is a documented handle that developer CSS
    // and page-level scripts are allowed to select on — and only the second navbar's markup
    // changes, which is the case that was broken. A caller-supplied `id` derives the menu's
    // id from it instead, so the pairing stays readable in their own markup.
    $mobileId = \Pushery\WireKit\Support\DomId::unique(
        ($navId = $attributes->get('id')) ? $navId.'-mobile' : 'wk-navbar-mobile',
        'wk-navbar-mobile-'
    );
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp

<nav
    x-data="{ mobileOpen: false }"
    {{-- Escape closes the disclosure from anywhere in the bar. A scrolling row has nothing to
         close. --}}
    @unless($scroll) x-on:keydown.escape="mobileOpen = false" @endunless
    {{-- Only when the caller did not name it. HTML keeps the FIRST of two identical
         attributes, and the bag renders after this line — so a caller's `aria-label` was
         parsed and then discarded, silently, on the one element whose name decides how the
         page's navigation landmarks are told apart. --}}
    @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby'))
    aria-label="{{ __('wirekit::Main navigation') }}"
    @endunless
    {{ $attributes->class([$navClasses, $variantClasses, $stickyClasses]) }}
>
    <div class="{{ $containerClasses }}">
        {{-- Brand / Logo slot.

             A row, not a block. The canonical brand is a mark beside a wordmark, and the
             wordmark is `<x-wirekit::text>`, which renders a block `<p>` — so in a block
             wrapper the two would stack, with the name a full line under the mark. Nothing
             in the slot can fix that from the inside, because the wrapper
             is what decides the flow, and a developer supplying a brand should not have to
             know to wrap it a second time.

             `gap-[var(--gap-wk-sm)]` rather than a margin on either child, so a brand that is
             a single logo carries no stray space. --}}
        @isset($brand)
            <div class="shrink-0 flex items-center gap-[var(--gap-wk-sm)]">
                {{ $brand }}
            </div>
        @endisset

        {{-- The navigation list — one render, revealed by the disclosure below the breakpoint
             and always shown above it.

             `x-show` writes an inline `display: none`, which no class can beat, so the
             always-shown half is a stylesheet rule keyed on `data-wk-navbar-items` (see
             `dist/wirekit.css`). The marker is absent under `force-mobile`, which is what
             keeps that demo mobile at every width. --}}
        @if($scroll)
            <div data-wk-navbar-scroll data-fade-axis="x" x-data="wirekitNavbarScroll()" class="{{ $scrollListClasses }}">
                {{ $slot }}
            </div>
        @else
        <div
            id="{{ $mobileId }}"
            @if(! $forceMobile) data-wk-navbar-items @endif
            x-show="mobileOpen"
            {{-- Hiding the list drops the focus inside it, so Escape hands it to the menu button
                 first. The key then reaches the bar, which closes the list. --}}
            x-on:keydown.escape="mobileOpen && $refs.wkNavbarToggle.focus()"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 -translate-y-1"
            class="flex {{ $navListClasses }}"
            x-cloak
        >
            {{ $slot }}
        </div>
        @endif

        {{-- Actions slot — the only render of it, at every width --}}
        @isset($actions)
            <div class="{{ $actionsClasses }}">
                {{ $actions }}
            </div>
        @endisset

        {{-- Mobile hamburger button. None for a scrolling row: there is nothing to reveal. --}}
        @unless($scroll)
        <button
            type="button"
            x-ref="wkNavbarToggle"
            x-on:click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen ? 'true' : 'false'"
            aria-controls="{{ $mobileId }}"
            aria-label="{{ __('wirekit::Toggle navigation') }}"
            class="{{ $hamburgerClasses }} p-2 cursor-pointer rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-subtle)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
        >
            {{-- Hamburger icon (open state) --}}
            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            {{-- Close icon (open state) --}}
            <svg x-show="mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
        @endunless
    </div>

    {{-- No second menu block. Both slots are rendered once, in the bar above: the list is
         the disclosure's own target and the actions stand beside the brand at every width.
         This block held a second copy of each, which duplicated every id in them. --}}
</nav>
