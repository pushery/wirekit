{{-- optimistic-ui: n/a — client-only
     Its state is rail collapse state. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    // Collapse-to-icon rail. When true, the sidebar can collapse to a narrow
    // icon-only rail: an auto-rendered toggle flips the state, item/group labels
    // become sr-only (accessible names preserved), and only icons stay visible.
    'collapsible' => false,
    // Initial collapsed state (only meaningful with `collapsible`). Left unset, the
    // cookie driver below is allowed to answer it instead — which is the whole point
    // of that driver, so an explicit value here wins and turns the seeding off.
    //
    // Null rather than false so those two cases stay APART. Once the boolean cast has
    // run they are the same value, and a server that cannot tell "not set" from
    // "explicitly collapsed=false" would override a developer who asked for expanded.
    'collapsed' => null,
    // Surface shape. `card` (default) is the self-contained panel: background,
    // border on all four sides, rounded, meant to sit inside a padded column.
    // `flush` is the full-bleed navigation COLUMN of the common admin layout —
    // no radius, no surrounding border, the surface inherited from the host, and
    // a single inline-end edge separating it from the content.
    //
    // Two variants rather than a set of booleans, because the halves are not
    // independent: a rounded panel with no border reads as a rendering fault, and
    // a full-bleed column with a radius shows a sliver of the page at each corner.
    // Default stays `card` so no existing sidebar changes.
    // Which ARIA contract this column carries, and the two are not interchangeable.
    //
    // `navigation` (the default, and byte-identical to every sidebar that exists today)
    // is a list of LINKS: each row is in the tab order, Enter follows it, and the row for
    // the page you are on is marked `aria-current`.
    //
    // `selection` is one CONTROL holding many choices: the container is in the tab order
    // once, the arrows move an `aria-activedescendant` marker without moving focus, and
    // the chosen row is marked `aria-selected`. It emits no `aria-current` at all, which
    // is the whole point — announcing a page you are on AND a value you have picked are
    // two different claims, and a column that made both would be lying about one.
    'mode' => 'navigation',
    // The chosen value, in `selection` mode. Matched against each row's `value`, so the
    // column can render already-selected on the server rather than waiting for Alpine —
    // a listbox whose selection appears one frame late reads as a flicker.
    'selected' => null,
    'variant' => 'card',
    // The surface tone, on the same `--color-wk-rail-*` roles the module rail reads —
    // minus `accent`, for the reason spelled out where the value is validated. `default` is every sidebar that
    // exists today and is byte-identical to it; the other tones let the second
    // navigation column carry its own color, which is what a console shell needs when
    // its rail is dark chrome and the content is a light sheet.
    //
    // Shared roles rather than a private `--color-wk-sidebar-*` set on purpose: a rail
    // and the column beside it must agree about a color, and two components that must
    // agree should not each own a copy of it.
    'tone' => 'default',
    // Where the auto-rendered collapse toggle sits, or `none` to omit it and
    // supply your own. `none` keeps the state machinery here — a developer who
    // wanted the trigger elsewhere previously had to switch `collapsible` off and
    // rebuild the rail's Alpine state, the persisted flag and the marker the
    // descendant items read, which is vendor mechanics reimplemented in an app.
    // An outside trigger reaches this sidebar through the `wirekit:sidebar:toggle`
    // window event instead; see the docs page.
    'toggle' => 'end',
    // Optional storage key — persists the collapsed state across reloads.
    'persist' => null,
    // WHERE that choice is remembered: 'local' (default) or 'cookie'.
    //
    // No server can read localStorage, so a column that remembers being collapsed
    // renders at its seed width and corrects itself after the first paint. A nonced
    // script after the column closes that gap — see the partial it includes — but the driver
    // below remains the only way the FIRST render is right without any script at all. An adopting
    // application measured that on the rail next door at 0.1097 CLS against a budget of
    // 0.1 — the content column moving 187px, 53ms in. The usual answer, a blocking
    // inline script in the head, is unavailable under a strict `script-src 'self'`
    // policy without a nonce.
    //
    // A cookie is the only store Blade and Alpine both read, so this driver mirrors the
    // flag there and the first render is already right. Opt-in on purpose: writing a
    // cookie where a developer asked for localStorage is a behavior change, and a cookie
    // is something an application has to be able to account for.
    'persistDriver' => 'local',
    // Accessible name for the navigation landmark. Defaults to "Sidebar" so
    // assistive tech can tell it apart from the main <nav>; override it when the
    // page carries more than one navigation landmark. Passing aria-label OR
    // aria-labelledby directly on the component also wins over this default
    // (and suppresses it, so the <nav> never gets a duplicate/conflicting name).
    'label' => __('wirekit::Sidebar'),
    // The edge shadows on the scrolling middle. On by default, because a
    // scrollbar alone is easy to miss on a track that fades when idle.
    //
    // It has to be separable from the zones, and that is the point of the prop:
    // the zones and the shadows arrived as one thing, so a developer with their
    // own edge treatment could only be rid of ours by dropping `header` and
    // `footer` entirely — which also gives up the sticky head and foot, a
    // completely unrelated capability. Two decisions were being made by one
    // answer.
    'scrollShadows' => true,
    // The head and foot zones inset their content to line up with the navigation
    // below them. That is right for a brand row or a plain block, and wrong for a
    // component that already insets itself: a `sidebar.item` placed in a zone
    // carries its own padding and ends up indented twice, sitting visibly right of
    // the items it belongs with. Set `false` when the zone's content brings its own.
    'zoneInset' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('sidebar', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $collapsible = BooleanProp::from($collapsible, false);
    $zoneInset = BooleanProp::from($zoneInset, true);

    $persistDriver = WireKit::validateProp('sidebar', 'persistDriver', $persistDriver, ['local', 'cookie']);

    // The server half of the cookie driver, and it runs BEFORE the boolean cast on purpose:
    // the cast turns null into false, and after that "not set" and "explicitly expanded" are
    // one value. Seeding then would override the developer instead of filling in for them.
    //
    // The REQUEST is asked first and the superglobal only as a fallback, and that order is
    // the fix rather than a detail. PHP fills `$_COOKIE` once per PROCESS, not once per
    // request. Under `fpm-fcgi` those coincide — a process serves one request and dies — which
    // is why reading it alone looks correct for as long as it does. Every server that keeps a
    // worker alive across requests (Octane, FrankenPHP, RoadRunner) fills it at boot, when
    // there is no request, and never again; the column then renders the other state and
    // nothing throws.
    //
    // The superglobal stays as a FALLBACK because dropping it would be a regression. The
    // cookie is written by JavaScript, so it arrives as plaintext, and Laravel's
    // `EncryptCookies` nulls a plaintext cookie it cannot decrypt unless the name is excepted.
    // An application on FPM that never added that exception is served by `$_COOKIE` today and
    // must keep working. The fallback can fail to answer but cannot answer WRONGLY: on a
    // long-lived server nothing writes `$_COOKIE` per request, so it is empty rather than
    // another visitor's value, and the result is only ever compared as a boolean.
    if ($collapsed === null && $persistDriver === 'cookie' && $persist !== null) {
        $stored = request()->cookie($persist);

        if (! is_string($stored)) {
            $stored = isset($_COOKIE[$persist]) && is_string($_COOKIE[$persist]) ? $_COOKIE[$persist] : null;
        }

        if ($stored !== null) {
            $collapsed = $stored === '1';
        }
    }

    $collapsed = BooleanProp::from($collapsed, false);

    // Landmark accessible name. Only emit our default `aria-label` when the
    // developer supplied NEITHER aria-label NOR aria-labelledby directly on the
    // component — a raw aria-label / aria-labelledby always wins and suppresses
    // the default, so the <nav> never carries a duplicate or conflicting name.
    // Merging (vs a hardcoded literal) also keeps every other passed-through
    // attribute (id, data-*) intact instead of dropping it.
    $navLabelAttrs = ($attributes->has('aria-label') || $attributes->has('aria-labelledby'))
        ? []
        : ['aria-label' => $label];

    // MERGED into the bag rather than written as a conditional attribute on the tag.
    // `<nav @if(…) data-wk-tone=… @endif {{ $attributes }}>` leaves the spaces around the
    // conditional behind when it is false, so an untoned sidebar rendered `<nav  aria-label=`
    // with two spaces — byte-different from what it always emitted, which a case asserting
    // the classic sidebar is unchanged caught immediately. Merging keeps the untoned output
    // identical and costs nothing.
    // ONE merge, not two chained ones: a second `->merge()` call re-orders the rendered
    // attributes (class moved ahead of aria-label), which is byte-different output for a
    // sidebar nobody toned. The union keeps the label first, exactly where it always was.
    $navLabelAttrs += $tone === 'default' ? [] : ['data-wk-tone' => $tone];

    // Published so the token remap in dist/wirekit.css can tell a FLUSH column — which
    // paints nothing and therefore sits on whatever chrome is behind it — from a CARD, which
    // brings its own surface and must keep its own tokens with it.
    $navLabelAttrs += ['data-variant' => $variant];

    // Sidebar root: a semantic <nav> landmark that holds grouped navigation
    // items. Uniform `p-[var(--padding-wk-y-sm)]` (= 0.375rem all four
    // sides) so the OUTER gap between the sidebar border and each item's
    // hover/active highlight matches the INNER gap between the item edge
    // and the label text. With asymmetric `px-x-sm py-y-sm` (0.625rem
    // horizontal, 0.375rem vertical) the outer gap looked 67% larger
    // than the inner — visually unbalanced.
    $variant = in_array($variant, ['card', 'flush'], true) ? $variant : 'card';

    // NO `accent` here, and the omission is deliberate rather than an oversight.
    //
    // A sidebar's contents are ordinary components — items, groups, disclosures, a profile
    // row — and they read the generic tokens, which a toned column remaps for its subtree.
    // That works for a tone whose hover and active surfaces are a small tint: the foreground
    // stays legible on them. It does NOT work for the accent tone, whose hover and active
    // states INVERT, because a generic component paints its foreground from one token and its
    // hover surface from another and cannot switch them together. The result would be a label
    // in exactly the color of the fill behind it.
    //
    // The rail is safe there because its items switch both at once, through roles built for
    // it (`--color-wk-rail-hover-fg` / `-active-fg`). Rather than ship a combination that is
    // unreadable on five of the bundled themes, the scale here stops at the tones a generic
    // subtree can carry. A colored second column is expressible by toning the RAIL and
    // leaving this one neutral, which is what every reference console does anyway.
    $tone = WireKit::validateProp('sidebar', 'tone', $tone, ['default', 'muted', 'inverse']);

    // A toned column paints from the roles; an untoned one keeps the literals it always
    // had rather than routing through a variable with the same value. The two resolve
    // identically today, and that is exactly the reason: a sidebar nobody toned must not
    // start depending on a token a developer might repoint for their rail.
    $toneSurface = $tone === 'default'
        ? 'bg-[var(--color-wk-bg-elevated)]'
        : 'bg-[var(--color-wk-rail-bg)]';
    $toneBorder = $tone === 'default'
        ? 'border-[var(--color-wk-border)]'
        : 'border-[var(--color-wk-rail-border)]';

    // The surface half, split out so the two shapes read as alternatives rather
    // than as a base with exceptions bolted on. `border-e` is the LOGICAL
    // inline-end edge, so a right-to-left document gets the separator on the side
    // that faces its content instead of the side that faces the page margin.
    $surface = $variant === 'flush'
        ? [
            'border-e-[length:var(--border-wk-width)]',
            $toneBorder,
            // Fills its column. Only the browser showed why this is needed: the edge
            // is drawn on THIS element, so a nav sized to its content ends the
            // separator wherever the last item happens to fall — measured 200px of
            // line in a 340px column, which reads as a rendering fault rather than
            // as a short list. The card variant must NOT have it, because a card is
            // supposed to be as tall as its content.
            'h-full',
            // A flush column deliberately paints NOTHING by default — flush means the
            // host's background IS the column's. A toned one has to paint, or the tone it
            // was given is simply not there. Emitted only when toned, so the default
            // flush column is unchanged.
            ...($tone === 'default' ? [] : [$toneSurface]),
        ]
        : [
            $toneSurface,
            'border-[length:var(--border-wk-width)]',
            $toneBorder,
            'rounded-[var(--radius-wk-lg)]',
        ];

    $classes = WireKit::resolveClasses('sidebar', 'base', implode(' ', [
        // Marker class. The tone remap in dist/wirekit.css addresses the column through
        // it, and the browser tests address it too — a `group/wk-sidebar` name is a
        // Tailwind grouping handle, not something a stylesheet or a probe can select.
        'wk-sidebar',
        // Publishes what this column pads its children by, so a chrome band placed in a zone
        // can cancel it without hardcoding the token. See shell-bar's `bleed`.
        '[--wk-nav-pad:var(--padding-wk-y-sm)]',
        'flex flex-col',
        // No gap between the zones, and that is the fix rather than an omission. This
        // gap spaced head-from-scroller and scroller-from-foot, so the first row sat a
        // full 8px lower than the rail's first module before its own spacing even began.
        // The scroller pads itself now — exactly as the rail's does — which puts the two
        // columns on one rhythm instead of two that happen to look close.
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)]',
        ...$surface,
    ]), $scope);

    $toggle = in_array($toggle, ['start', 'end', 'none'], true) ? $toggle : 'end';

    $mode = in_array($mode, ['navigation', 'selection'], true) ? $mode : 'navigation';

    // Selection mode and the collapse-to-icon rail do not compose, and the refusal is
    // explicit rather than a half-working hybrid. A collapsed rail hides every label, and
    // a listbox whose options have no visible names is a control nobody can use — while
    // `aria-selected` would still be announced, so it would READ as usable to a screen
    // reader and be unusable on screen. Navigation survives collapsing because an icon
    // plus an accessible name is still a link; a choice needs its label.
    $selectionMode = $mode === 'selection' && ! $collapsible;

    if ($mode === 'selection' && $collapsible && config('app.debug')) {
        $collapsibleSelectionWarning = '[wirekit] sidebar: `mode="selection"` is ignored while '
            .'`collapsible` is set. A collapsed rail hides the labels a listbox option needs, so '
            .'the column would announce a selection nobody can read. Drop `collapsible`, or keep '
            .'the navigation contract.';
    }

    // Collapse toggle button — only rendered (and only styled) for a collapsible
    // sidebar. Resolvable rather than a local variable: as a local it could not be
    // themed, moved or replaced, so the only way to restyle the one chrome control
    // in the component was a CSS rule against `> button:first-child` — a selector
    // that depends on the vendor's child order and breaks the moment anything is
    // rendered before it.
    $collapseBtnClasses = WireKit::resolveClasses('sidebar', 'toggle', implode(' ', [
        $toggle === 'start' ? 'self-start' : 'self-end',
        // BOTTOM of the column, and never touching the row above it.
        //
        // It used to be the first child, which put it at the top — while the documentation
        // told the reader to click "the chevron toggle at the bottom of the sidebar", and
        // while the app rail's expander really is down there. Two components, one gesture,
        // two places to look for it.
        //
        // `mt-auto` claims the leftover height when the column is taller than its content;
        // with a short list it simply ends up last, which is the same thing to look at.
        //
        // The spacing is not decoration and does NOT live here: two `mt-` utilities on one
        // element fight, and the later one wins. The column carries a row gap instead — see
        // the nav below — because that is what the space is: the distance between two rows,
        // read from the same token the navigation rows already space themselves by.
        'mt-auto',
        'inline-flex items-center justify-center shrink-0',
        'p-1 rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)] cursor-pointer',
        // In the collapsed rail the button centers with the icons.
        'group-data-[collapsed]/wk-sidebar:self-center',
    ]), $scope);

    // Three zones — fixed head, scrolling middle, fixed foot — but ONLY when a
    // head or foot is actually supplied. A sidebar with neither keeps rendering
    // its slot bare, so nothing about an existing call site changes.
    //
    // The zones exist because every developer building this layout writes the same
    // three lines and makes the same mistake in them: without `min-h-0` on the
    // middle, a flex item refuses to shrink below its content, so the head and
    // foot are pushed out of view and scroll away with the list instead of staying
    // put. The bug looks like the scroll region is missing when in fact it is the
    // whole column that scrolled.
    $hasZones = isset($header) || isset($footer);
    $scrollShadows = BooleanProp::from($scrollShadows, true);
@endphp

@if(isset($collapsibleSelectionWarning))
    {{-- Dev-only, and it renders BEFORE the column so it is visible even when the sidebar
         itself is what looks wrong. Silent in production, like every other dev warning
         here — it uses the same Alpine helper because naming `console` throws while
         BUILDING a component under Alpine's CSP build. --}}
    <div x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from($collapsibleSelectionWarning) }} })" hidden></div>
@endif

@if($selectionMode)
    {{-- SELECTION MODE — the WAI-ARIA listbox contract.

         Not a `<nav>`, deliberately. A landmark announces "navigation" and invites a
         screen-reader user to jump into it as a set of destinations; this column is one
         control holding choices, and the two are different promises. The accessible name
         still comes from the same `$navLabelAttrs`, so a caller's own `aria-label` wins
         exactly as it does for the navigating column.

         `tabindex="0"` on the CONTAINER and nothing focusable inside it is the whole
         pattern: one tab stop, and the arrows move a marker rather than focus. That is
         also why the rows below carry ids — `aria-activedescendant` points at one. --}}
    <div
        x-data="wirekitSidebarListbox({ value: {{ \Pushery\WireKit\Support\AlpinePayload::from($selected) }} })"
        x-init="initListbox()"
        role="listbox"
        tabindex="0"
        x-bind:aria-activedescendant="activeId"
        x-on:keydown.down.prevent="moveActive(1)"
        x-on:keydown.up.prevent="moveActive(-1)"
        x-on:keydown.home.prevent="moveToFirst()"
        x-on:keydown.end.prevent="moveToLast()"
        x-on:keydown.enter.prevent="selectActive()"
        x-on:keydown.space.prevent="selectActive()"
        {{ $attributes->class([$classes, 'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]'])->merge($navLabelAttrs) }}
    >
        @include('wirekit::components.partials.sidebar-zones')
    </div>
@elseif($collapsible)
    {{-- Collapsible rail. The `collapsed` state lives here; `group/wk-sidebar` +
         the `data-collapsed` marker let descendant labels/indicators react via
         pure CSS (`group-data-[collapsed]/wk-sidebar:*`) — no per-item Alpine.
         The collapsed width reads --size-wk-rail, the same token the app-rail uses,
         so the two columns stay interchangeable in a shell. It is DERIVED from the
         icon and the paddings rather than written as a round number: a collapsed row
         centers its icon and an expanded row aligns it to the leading padding, and
         those coincide only when the column is exactly as wide as its content. The
         literal 3.5rem that stood here was described as "icon + padding" and was a
         quarter-rem wider, which centering halved into a 1.5px sideways jump on
         every expand. --}}
    <nav
        {{-- The rail's folded state lives in resources/js/components/sidebar-rail.js.
             It cannot live here: an inline object literal cannot declare methods
             under Alpine's CSP build, so the fold button did nothing at all under
             a strict policy. The key is emitted as a JSON literal rather than
             through {{ \Pushery\WireKit\Support\AlpinePayload::from() }}, because {{ \Pushery\WireKit\Support\AlpinePayload::from() }} renders a non-empty payload as
             JSON.parse(…) and JSON is a global the CSP evaluator cannot resolve. --}}
        x-data="wirekitSidebarRail({ collapsed: {{ $collapsed ? 'true' : 'false' }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }}, persistDriver: {{ \Pushery\WireKit\Support\AlpinePayload::from($persistDriver) }} })"
        {{-- Emitted STATICALLY as well as bound, for the same reason the width below is.
             Until Alpine boots, `:data-collapsed` has not run — and roughly 25 descendant
             rules key off `group-data-[collapsed]/wk-sidebar:*`. Without this a column that
             was asked to start collapsed renders at the right WIDTH with its contents laid
             out for the wrong one, until the first frame. Alpine's binding then owns the
             attribute: it sets '' or null on every toggle, so the two never disagree. --}}
        @if($collapsed) data-collapsed @endif
        :data-collapsed="collapsed ? '' : null"
        {{-- Read by exactly the rules that hide TEXT, and by nothing else. While the column
             is widening back the names stay out of the layout, so they are never set at a
             width they will not keep — which is what made the rows jump and settle. --}}
        :data-settling="settling ? '' : null"
        {{-- OBJECT syntax, not a ternary, and the difference is load-bearing. A ternary hands
             Alpine one class to add, and Alpine only ever removes what it added itself — so the
             static width below survived every toggle and a collapsed column stayed at 16rem with
             two width utilities on it. The object form names BOTH classes, so Alpine removes the
             one whose condition is false no matter who put it there. --}}
        :class="{ 'w-[var(--size-wk-rail,3.25rem)]': collapsed, 'w-[var(--wk-sidebar-w,16rem)]': ! collapsed }"
        {{-- The row gap the collapse control needs. Without it the button's hover surface
             ended exactly where the adjacent item's began — two gray rectangles sharing an
             edge, which reads as one smudged block rather than as two controls. --}}
        :data-wk-ready="ready ? '' : null"
        {{-- The width transition is gated on `data-wk-ready`, which Alpine sets one frame after
             init. Ungated, a cold load animates the ARRIVAL of the stylesheet: the column lays
             out unstyled, the CSS lands, and the browser tweens from one to the other — a column
             unfolding on a page where nothing was toggled. Only ever visible without a cache. --}}
        {{-- The initial width is emitted STATICALLY as well as through the `:class` above.
             Until Alpine boots, `:class` has not run, so without this the column carries no
             width at all — it lays out at content width and snaps to its real one a frame
             later, which is the flicker reported from three shells. Alpine's `:class` swaps
             the two widths, so exactly one width utility is ever in the list and the
             equal-specificity conflict never arises. The stored choice lives in the reader's
             browser, so this static class is the SEED rather than the memory — the nonced
             script emitted after this column reconciles the two before the first paint, which
             is where the "briefly collapsed, then open" report came from. --}}
        {{ $attributes->class([$classes, $collapsed ? 'w-[var(--size-wk-rail,3.25rem)]' : 'w-[var(--wk-sidebar-w,16rem)]', 'group/wk-sidebar gap-[var(--space-wk-nav-gap)] data-[wk-ready]:transition-[width] data-[wk-ready]:duration-[var(--transition-wk-duration)]'])->merge($navLabelAttrs) }}
    >
        @include('wirekit::components.partials.sidebar-zones')
        @if($toggle !== 'none')
        <button
            type="button"
            x-on:click="toggle()"
            {{-- Emitted STATICALLY as well as bound, for the same reason `data-collapsed`
                 above is. Until Alpine boots, `:aria-label` has not run — so the only
                 content of this button is a decorative <svg>, and it reaches a screen
                 reader as a bare "button". A server-side accessibility check never gets
                 past that point at all, so for one it is nameless permanently.
                 Alpine owns both attributes after init and rewrites them on every toggle,
                 so the static pair can never disagree with the bound one. Same __() keys,
                 so the translation is maintained once. --}}
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-label="{{ $collapsed ? __('wirekit::Expand sidebar') : __('wirekit::Collapse sidebar') }}"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :aria-label="collapsed ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Expand sidebar')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Collapse sidebar')) }}"
            class="{{ $collapseBtnClasses }}"
        >
            <svg class="h-5 w-5 transition-transform duration-[var(--transition-wk-duration)]" :class="collapsed ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 4.5 11.25 12l7.5 7.5m-7.5-15L3.75 12l7.5 7.5" />
            </svg>
        </button>
        @endif
    </nav>
    {{-- Immediately after the column, so `document.currentScript.previousElementSibling` is
         this sidebar and nothing else. Only for the `local` driver: the cookie driver was
         already answered by the server above, and re-answering it here would be a second
         source for one value. --}}
    @if($persist !== null && $persistDriver === 'local')
        @include('wirekit::components.partials.nav-persist-seed', [
            'seedKey' => $persist,
            'seedOn' => $collapsed,
            'seedClassOn' => 'w-[var(--size-wk-rail,3.25rem)]',
            'seedClassOff' => 'w-[var(--wk-sidebar-w,16rem)]',
            // No gate: unlike the rail, this factory does not re-decide on viewport, so a
            // gate here would disagree with it for a frame.
            'seedMinWidth' => null,
        ])
    @endif
@else
    {{-- <nav> carries a default aria-label so AT distinguishes it from the main
         nav; a developer-supplied aria-label / aria-labelledby / `label` prop overrides it. --}}
    <nav {{ $attributes->class([$classes])->merge($navLabelAttrs) }}>
        @include('wirekit::components.partials.sidebar-zones')
    </nav>
@endif
