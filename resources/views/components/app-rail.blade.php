{{-- optimistic-ui: n/a — client-only
     Its state is whether the rail shows its labels. That is not a value a server owns,
     so there is nothing to anticipate and nothing to roll back. --}}
@props([
    // How an item names itself.
    //   tooltip — icon only; the label is the link's accessible name and appears on
    //             hover/focus in a tooltip. The narrowest form, and the default.
    //   below   — a caption under the icon. Wider, and it removes the hover
    //             dependency, which matters on touch where hover does not exist.
    //   inline  — the label beside the icon, in a wide rail. This is the shape a
    //             single-column application wants when the rail IS the navigation.
    'labels' => 'tooltip',
    // Adds a toggle that widens the rail to `inline` labels and back, persisting the
    // choice. Orthogonal to `labels`: the toggle switches between the width `labels`
    // selects and the expanded width, so `labels="below"` plus `expandable` gives
    // captions that become full labels.
    'expandable' => false,
    // Initial state on a first visit, before storage answers. Left unset, the cookie
    // driver below is allowed to answer it instead — which is the whole point of that
    // driver, so an explicit value here wins and turns the seeding off.
    'expanded' => null,
    // Storage key. Null keeps the choice for the session only.
    'persist' => null,
    // WHERE the choice is remembered: 'local' (default) or 'cookie'.
    //
    // No server can read localStorage, so a rail that remembers being expanded renders
    // collapsed and widens itself after the first paint. An adopting application measured
    // that at 0.1097 CLS against a budget of 0.1 — the content column moving 187px, 53ms in.
    // Reported again on 2026-09-01 from several production applications, as the navigation
    // being briefly collapsed and then snapping open.
    // A small nonced script after the column closes that gap now — it reads the stored flag
    // while the parser is still working and swaps the width class before anything is painted.
    // This comment used to end here saying the usual fix is an inline script "which a strict
    // `script-src 'self'` policy without a nonce simply blocks", and that was an argument
    // against an UNNONCED script: `fonts.blade.php` had already answered the same objection
    // for its inline <style> by emitting `WireKit::cspNonce()`. Where a policy refuses it
    // anyway, the column falls back to exactly the behavior described above.
    //
    // A cookie is the only store Blade and Alpine both read, so this driver mirrors the
    // flag there and the first render is already right. Opt-in on purpose: writing a cookie
    // where a developer asked for localStorage is a behavior change, and a cookie is
    // something an application has to be able to account for.
    'persistDriver' => 'local',
    // The surface. Each tone re-points the `--color-wk-rail-*` roles in
    // dist/wirekit.css; every class below reads only those, so a tone is a change of
    // variables rather than a parallel set of utilities. Override the roles in your
    // own CSS to reskin one tone, or the `:root` defaults to reskin them all.
    'tone' => 'default',
    // Shape. `flush` is the edge-to-edge chrome column with a single inline-end
    // edge. `panel` is the floating rounded strip that sits ON the page with a gap
    // around it — the shape that pairs with the app-shell's own `panel` prop.
    // (The component tag is deliberately NOT written here. Blade is a text preprocessor
    // and does not know it is inside a PHP comment: an `x-wirekit::` tag in one gets
    // COMPILED, and the compiled component construct lands in the middle of this array.
    // The failure reads "unexpected token" at a line that has nothing to do with it.)
    'variant' => 'flush',
    // How the current module is marked. `pill` fills the item's own box. `edge` draws
    // a bar on the inline-end edge of the rail, pointing at the column it selects —
    // which reads better when the rail is very narrow and a filled box would dominate.
    'indicator' => 'pill',
    // Accessible name for the navigation landmark. A console shell has TWO <nav>
    // landmarks side by side, and a screen-reader user moving between landmarks
    // cannot tell them apart unless each says what it is. Passing aria-label or
    // aria-labelledby directly wins over this default and suppresses it, so the
    // element never carries two conflicting names.
    'label' => __('wirekit::Modules'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('app-rail', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `expandable="false"` would otherwise turn the toggle ON. Normalize against each
    // prop's own default so a cast never engages a mode that was meant off.
    $expandable = BooleanProp::from($expandable, false);
    $persistDriver = WireKit::validateProp('app-rail', 'persistDriver', $persistDriver, ['local', 'cookie']);

    // The server half of the cookie driver. The REQUEST is asked first and the superglobal
    // only as a fallback, and that order is the whole fix rather than a detail.
    //
    // PHP fills `$_COOKIE` once per PROCESS, not once per request. Under `fpm-fcgi` those are
    // the same thing — a process serves one request and dies — which is why reading it alone
    // looked correct for as long as it did. Every server that keeps a worker alive across
    // requests (Octane, FrankenPHP, RoadRunner) fills it at boot, when there is no request,
    // and never again. Measured on one page with one cookie under two SAPIs: present under
    // FPM, EMPTY under a long-lived CLI SAPI, where the rail then rendered collapsed and the
    // client widened it a frame later — 187px of column movement, 0.1097 CLS against a
    // budget of 0.1. Nothing throws; it simply renders the other state.
    //
    // The superglobal stays as a FALLBACK because dropping it would be a regression, not a
    // cleanup. The cookie is written by JavaScript, so it arrives as plaintext, and Laravel's
    // `EncryptCookies` nulls a plaintext cookie it cannot decrypt unless the name is excepted
    // — which is what `theme-controller` has always documented. An application on FPM that
    // never added that exception is served by `$_COOKIE` today, and must keep working.
    //
    // The fallback can fail to answer but cannot answer WRONGLY: on a long-lived server
    // nothing writes `$_COOKIE` per request, so it is empty rather than another visitor's
    // value. Either way the result is used only as a boolean comparison — anything that is
    // not '1' reads as collapsed, so no arbitrary value reaches the page.
    if ($expanded === null && $persistDriver === 'cookie' && $persist !== null) {
        $stored = request()->cookie($persist);

        if (! is_string($stored)) {
            $stored = isset($_COOKIE[$persist]) && is_string($_COOKIE[$persist]) ? $_COOKIE[$persist] : null;
        }

        if ($stored !== null) {
            $expanded = $stored === '1';
        }
    }

    $expanded = BooleanProp::from($expanded, false);

    $labels = WireKit::validateProp('app-rail', 'labels', $labels, ['tooltip', 'below', 'inline']);
    $tone = WireKit::validateProp('app-rail', 'tone', $tone, ['default', 'muted', 'inverse', 'accent']);
    $variant = WireKit::validateProp('app-rail', 'variant', $variant, ['flush', 'panel']);
    $indicator = WireKit::validateProp('app-rail', 'indicator', $indicator, ['pill', 'edge']);

    // The resting width, one per labeling mode. Set on the nav itself rather than on
    // the shell's column: the column is `w-auto`, so this measurement is the single
    // place a rail width is decided and the column follows it — including while the
    // expand transition is mid-flight.
    //
    // The width is the token and nothing else, including beside an inset content panel. The
    // column used to add the shell's gap to itself there, so that its horizontal rules would
    // REACH the panel instead of stopping short of the seam. The rules still have to reach it
    // — but a wider column puts every glyph in the stack off the column's own center, which
    // is what that bought and what was reported. So the RULES overflow the column now, and
    // the column keeps the one width every other rail has. See dist/wirekit.css.
    $restingWidth = match ($labels) {
        'below' => 'w-[calc(var(--size-wk-rail-labeled,4.75rem)_+_var(--wk-rail-gutter,0px))]',
        'inline' => 'w-[calc(var(--size-wk-rail-expanded,15rem)_+_var(--wk-rail-gutter,0px))]',
        default => 'w-[calc(var(--size-wk-rail,3.25rem)_+_var(--wk-rail-gutter,0px))]',
    };

    // The expanded width, as one string, so the `:class` below and the static branch cannot
    // drift apart — they did once, and the winner was Tailwind's emission order.
    $expandedWidth = 'w-[calc(var(--size-wk-rail-expanded,15rem)_+_var(--wk-rail-gutter,0px))]';

    $navLabelAttrs = ($attributes->has('aria-label') || $attributes->has('aria-labelledby'))
        ? []
        : ['aria-label' => $label];

    // `h-full` is not cosmetic. The inline-end edge is drawn on THIS element, so a nav
    // sized to its content ends the separator wherever the last module happens to
    // fall — the sidebar learned this in the browser, where 200px of line in a 340px
    // column read as a rendering fault rather than as a short list.
    $surface = $variant === 'panel'
        ? [
            'rounded-[var(--radius-wk-shell-panel)]',
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-rail-border)]',
            // The gap that makes it float. A calc height rather than `h-full`, so the panel
            // keeps the same gap top and bottom instead of running off the shell.
            //
            // The utility is deliberately NOT quoted in this sentence. Tailwind scans this
            // file as text and does not know it is inside a comment, so a bracketed token
            // written in prose is COMPILED into a real rule — which then shows up as a
            // compiled selector no source emits, i.e. as drift caused by the note
            // explaining the code. The drift suite's own docblock records this happening
            // once already.
            'm-[var(--space-wk-sm,0.5rem)]',
            'h-[calc(100%-2*var(--space-wk-sm,0.5rem))]',
        ]
        : [
            'border-e-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-rail-border)]',
            'h-full',
        ];

    $classes = WireKit::resolveClasses('app-rail', 'base', implode(' ', [
        'wk-rail',
        // Publishes what this column pads its children by, so the concentric-radius rule and
        // a bleeding chrome band can both read it instead of hardcoding the token.
        '[--wk-nav-pad:var(--padding-wk-y-sm)]',
        'flex shrink-0 flex-col',
        'bg-[var(--color-wk-rail-bg)]',
        'text-[color:var(--color-wk-rail-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        ...$surface,
    ]), $scope);

    // The expand toggle. Resolvable rather than a local, for the reason the sidebar's
    // own toggle records: as a local it could not be themed, moved or replaced, and
    // the only way to restyle the one chrome control in the component was a selector
    // against the vendor's child order.
    $toggleClasses = WireKit::resolveClasses('app-rail', 'toggle', implode(' ', [
        'inline-flex shrink-0 items-center justify-center',
        'h-[var(--size-wk-sm,2rem)] w-[var(--size-wk-sm,2rem)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[color:var(--color-wk-rail-muted)]',
        'hover:bg-[var(--color-wk-rail-hover-bg)]',
        // The foreground ON the fill, not the one on the column. On a tone whose hover
        // inverts, these are different colors, and reading the column's would paint the
        // glyph in the same color as the surface behind it.
        'hover:text-[color:var(--color-wk-rail-hover-fg)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-rail-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
    ]), $scope);
@endphp

<nav
    data-wk-rail
    data-labels="{{ $labels }}"
    {{-- A rail that cannot expand has no Alpine, so its names must simply be present — but
         only in the modes that show names at all. An expandable rail overrides this from the
         binding below, where the words wait for the column to stop moving. --}}
    @if(! $expandable && $labels !== 'tooltip') data-wk-names @endif
    data-variant="{{ $variant }}"
    data-indicator="{{ $indicator }}"
    @if($tone !== 'default') data-wk-tone="{{ $tone }}" @endif
    @if($expandable)
        {{-- The state lives in resources/js/components/app-rail.js. It cannot live in
             an inline object literal here: Alpine's CSP build cannot declare methods
             that way, so under a strict policy the toggle would render and do nothing.
             `persist` goes through AlpinePayload; the boolean is written as a literal
             because a non-empty payload renders as JSON.parse(…) and JSON is a global
             the CSP evaluator cannot resolve. --}}
        x-data="wirekitAppRail({ persistDriver: {{ \Pushery\WireKit\Support\AlpinePayload::string($persistDriver) }}, expanded: {{ $expanded ? 'true' : 'false' }}, persist: {{ $persist === null ? 'null' : \Pushery\WireKit\Support\AlpinePayload::from($persist) }} })"
        :data-expanded="expanded ? '' : null"
        {{-- ONE attribute drives every per-mode style below, and that is deliberate.
             Items react through `group-data-[labels=…]/wk-rail:` variants; if the live
             expanded state were a SECOND attribute those variants also had to answer
             to, an expanded `labels="below"` rail would carry two utilities of equal
             specificity for the same property — flex-direction, label visibility, main-
             axis alignment — and the winner would be decided by Tailwind's emission
             order rather than by state. Rewriting the mode instead means the expanded
             rail simply IS an inline-label rail, and there is nothing to arbitrate.
             The static attribute below stays for the paint before Alpine initializes. --}}
        :data-wk-ready="ready ? '' : null"
        {{-- TWO markers, because the mode and the words need two different moments.

             `data-labels` rewrites the mode AT ONCE, and it has to: it also decides where an
             item's icon sits. Held back to the end of the transition, the icon stayed centered
             while the column grew and then snapped to the start edge — measured on a 240px
             rail, it drifted from 17.5px out to 108px and jumped back to 16px in one frame.
             That is a worse artifact than the one this was meant to remove.

             `data-wk-names` is the words alone, and those wait. Put into the layout at the
             width the animation happens to be passing through, a name wraps there and unwraps
             as the column catches up. Two concerns, two markers — the sidebar already splits
             them the same way, and this file did not. --}}
        :data-labels="expanded ? 'inline' : {{ \Pushery\WireKit\Support\AlpinePayload::string($labels) }}"
        {{-- Present whenever names belong in the layout: always in a mode that shows them,
             and in `tooltip` only once the column has finished widening. The mode above flips
             at once; this waits. --}}
        :data-wk-names="(wide || {{ \Pushery\WireKit\Support\AlpinePayload::string($labels) }} !== 'tooltip') ? '' : null"
        {{-- OBJECT syntax, not a ternary. A ternary hands Alpine one class to add, and Alpine
             only ever removes what it added itself — so the static initial width emitted below
             survived every toggle, leaving two width utilities on the element and a rail that
             never actually changed size. The object form names BOTH classes, so the one whose
             condition is false is removed regardless of who put it there. --}}
        :class="{ [{{ \Pushery\WireKit\Support\AlpinePayload::string($expandedWidth) }}]: expanded, [{{ \Pushery\WireKit\Support\AlpinePayload::string($restingWidth) }}]: ! expanded }"
    @endif
    {{ $attributes->class([
        $classes,
        'group/wk-rail',
        // The width belongs to the static branch only. With `expandable` the same
        // property is driven by the `:class` above, and emitting BOTH widths would leave two
        // utilities of equal specificity on one element — the winner decided by Tailwind's
        // emission order rather than by state, which is the exact failure sidebar.item
        // documents for its active foreground.
        // …but withholding the width from an expandable rail ENTIRELY is what made the column
        // flicker into place on load, reported from three separate shells. Until Alpine boots,
        // `:class` has not run, so the element carries no width at all: it lays out at content
        // width and then snaps to its real one. Emitting the width of the INITIAL state fixes
        // the first paint without reintroducing the conflict above — Alpine's `:class` swaps
        // the two, so exactly one width utility is ever in the list. The state is known here:
        // it is the same `$expanded` the Alpine component is seeded with a few lines up.
        //
        // ONE array key, deliberately. Written as two entries — a resting one for the static
        // branch and an initial one for the expandable branch — the keys COLLIDE whenever both
        // resolve to the same class string, and the later entry silently wins. That is not
        // hypothetical: it stripped the width off every non-expandable rail, because the second
        // entry re-keyed the resting width to `$expandable`, which is false there.
        //
        // A persisted rail can still move once, and that is not fixable from the server: the
        // stored choice lives in the reader's browser and only Alpine can read it.
        ($expandable && $expanded ? $expandedWidth : $restingWidth) => true,
        // Gated on `data-wk-ready`, which Alpine sets one frame after init. Ungated, a cold
        // load animates the ARRIVAL of the stylesheet rather than any state change: the column
        // lays out unstyled, the CSS lands, and the browser tweens between the two.
        // One class per entry: the drift inventory reads this form and reported the combined
        // one as untraceable, which would have meant allowlisting two classes that are right
        // here in the source.
        'data-[wk-ready]:transition-[width]' => $expandable,
        'data-[wk-ready]:duration-[var(--transition-wk-duration)]' => $expandable,
    ])->merge($navLabelAttrs) }}
>
    @isset($brand)
        {{-- The rail's segment of the shell's top rule. `shrink-0` so a long module
             list can never steal height from it. --}}
        <div class="shrink-0">
            {{ $brand }}
        </div>
    @endisset

    {{-- The module list. `min-h-0` is the whole trick and leaving it out is the
         mistake this zone exists to prevent: a flex item's automatic minimum size is
         its content, so without it the middle refuses to shrink, the column grows past
         the rail, and the brand and footer scroll away with the list. The symptom
         reads as "the scroll region is missing" when in fact everything scrolled.

         No tabindex/role/label of its own: it is a direct child of the <nav> landmark,
         which carries its own accessible name, and every module inside it is a
         focusable <a>. Landmark navigation reaches the region and Tab reaches its
         contents, so an extra stop would announce nothing new — the same shape the
         sidebar's own scroller is exempt under. --}}
    <div
        data-wk-rail-scroller
        class="wk-scrollbar flex min-h-0 flex-1 flex-col gap-[var(--space-wk-nav-gap)] overflow-y-auto py-[var(--padding-wk-y-sm)] ps-[var(--wk-rail-inset-start,var(--padding-wk-y-sm))] pe-[var(--wk-rail-inset-end,var(--padding-wk-y-sm))]"
    >
        {{ $slot }}
    </div>

    @if(isset($footer) || $expandable)
        {{-- The bottom block: account, help, search — the cluster every one of these rails
             puts there — and, last of all, the expand toggle.

             THE TOGGLE IS AT THE BOTTOM, and that is a correction rather than a preference.
             It used to sit directly under the brand mark, which is the most valuable row in
             the whole column: the eye lands there first, and the first thing it found was a
             chevron rather than the workspace. Expanded it was worse — it took the row where
             the workspace name belongs. A control that changes the column's WIDTH is chrome,
             and chrome goes where chrome goes.

             It shares this block with the footer rather than getting its own, so the rail
             keeps ONE separator line at the bottom instead of two. The block is emitted when
             either exists, so an expandable rail with no footer still has somewhere to put
             it, and a rail with neither has no empty band. --}}
        <div class="shrink-0 relative border-t-[length:var(--border-wk-width)] border-[var(--color-wk-rail-border)] py-[var(--padding-wk-y-sm)] ps-[var(--wk-rail-inset-start,var(--padding-wk-y-sm))] pe-[var(--wk-rail-inset-end,var(--padding-wk-y-sm))] flex flex-col gap-[var(--space-wk-nav-gap)]">
            @isset($footer)
                {{-- The trailing inset only exists while the rail is wide AND the toggle has
                     moved onto this row — it is the room the toggle occupies, so the two
                     belong to the same condition. --}}
                <div @class([
                    'flex flex-col gap-[var(--space-wk-nav-gap)]',
                    'group-data-[expanded]/wk-rail:pe-[2.25rem]' => $expandable,
                ])>
                    {{ $footer }}
                </div>
            @endisset

            @if($expandable)
                <div @class([
                    'flex',
                    // Not below `lg`. There the rail is the left strip of an off-canvas
                    // drawer, and expanding it widens that drawer past the device: measured
                    // at 375px, 0…309 collapsed against 0…496 expanded, with 121px of the
                    // module column gone and `scrollWidth` still 375 — nothing to scroll to.
                    // The factory refuses the state at this width regardless, so leaving the
                    // control here would present a button that does nothing.
                    'max-lg:hidden',
                    // Centered while the rail is narrow, pushed to the trailing edge once it
                    // is wide — where a collapse control is expected, and clear of the names.
                    'justify-center group-data-[expanded]/wk-rail:justify-end',
                    // Expanded, it stops being a ROW of its own and sits at the end of the
                    // last footer row instead. A control that changes the column's width was
                    // spending a full navigation row on itself — the most expensive way to
                    // present the least important thing in the column.
                    //
                    // Only when a footer exists: with nothing else in this block, taking the
                    // toggle out of flow leaves the block with no content to give it height,
                    // and the button hangs outside a collapsed band.
                    'group-data-[expanded]/wk-rail:absolute group-data-[expanded]/wk-rail:bottom-[var(--padding-wk-y-sm)] group-data-[expanded]/wk-rail:end-[var(--padding-wk-y-sm)]' => isset($footer),
                ])>
                    <button
                        type="button"
                        x-on:click="toggle()"
                        {{-- Static as well as bound: before Alpine runs, this button holds
                             nothing but a decorative <svg>, so a screen reader — and any
                             server-side accessibility check, permanently — meets a nameless
                             control. `$expanded` is the same normalized value the factory is
                             seeded with a few lines up, so the two states agree, and Alpine
                             owns both attributes from init onward. --}}
                        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                        aria-label="{{ $expanded ? __('wirekit::Collapse rail') : __('wirekit::Expand rail') }}"
                        :aria-expanded="expanded ? 'true' : 'false'"
                        :aria-label="expanded ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Collapse rail')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Expand rail')) }}"
                        class="{{ $toggleClasses }}"
                    >
                        <svg class="h-4 w-4 transition-transform duration-[var(--transition-wk-duration)]" :class="expanded ? '' : 'rotate-180'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 4.5 11.25 12l7.5 7.5m-7.5-15L3.75 12l7.5 7.5" />
                        </svg>
                    </button>
                </div>
            @endif
        </div>
    @endif
</nav>

{{-- Immediately after the column, so `document.currentScript.previousElementSibling` is this
     rail and nothing else. Only for the `local` driver: the cookie driver has already been
     answered by the server above, and re-answering it here would be a second source for one
     value. Only when the state was not pinned by the caller either — an explicit `expanded`
     turns the seeding off by design, and storage must not override it. --}}
@if($persist !== null && $persistDriver === 'local' && $expandable)
    @include('wirekit::components.partials.nav-persist-seed', [
        'seedKey' => $persist,
        'seedOn' => $expanded,
        'seedClassOn' => $expandedWidth,
        'seedClassOff' => $restingWidth,
        // The rail only expands above the shell's own breakpoint, and the factory gates on
        // exactly this. Ungated, a phone would widen for one frame and narrow again.
        'seedMinWidth' => '64rem',
    ])
@endif
