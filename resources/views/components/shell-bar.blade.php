{{-- optimistic-ui: n/a — client-only
     Its own state is the scroll position of the leading cluster — where in a strip of
     tabs the reader has scrolled to. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back.

     It said "presentational" until the cluster started scrolling, and the guard refused
     it: a scroll region is keyboard-operable, so with a `label` the bar renders a
     focusable element and "renders no interactive element" stopped being true. The claim
     was measured against the file rather than trusted, which is the point of that arm. --}}
{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    // The bottom border. This is THE horizontal line of a console shell: place one
    // bar at the head of each column and the segments meet, because they are the
    // same component reading the same height token rather than three hand-aligned
    // headers. Set false for a second, stacked bar that should not draw a rule of
    // its own (a tab strip directly under the title bar).
    'rule' => true,
    // Inline padding, on the page-edge spine scale. `none` is for the rail head,
    // whose content is a centered mark rather than text on the spine.
    'padding' => 'lg',
    // Main-axis distribution. `between` (default) pushes the `end` slot to the far
    // edge, which is what a title-plus-actions bar wants; `start` and `center` are
    // for a bar whose content is one cluster.
    'align' => 'between',
    // Sticks the bar to the top of its SCROLLING ancestor.
    //
    // Read this before reaching for it: in the console layout the bar sits inside
    // the app-shell's content column, and that column carries `overflow-hidden` and
    // therefore never scrolls — so `sticky` there sticks to something that does not
    // move, which is to say it does nothing. It is not a bug to fix here; it is what
    // `position: sticky` means. The console layout does not need it either, because
    // the main region below is the scroller and the bar already stays put.
    // Where it DOES work: a bar placed in ordinary document flow on a page that
    // scrolls as a document.
    'sticky' => false,
    // Cancels the padding of a host that pads its children, so the bar — and therefore its
    // rule — spans the host's full width and meets its top edge.
    //
    // The case this exists for is a bar placed in a sidebar's `header` zone. A sidebar pads
    // itself uniformly, which is right for navigation items and wrong for a chrome band: the
    // rule ends up inset on three sides and sits 6px below the rule in the columns either
    // side of it. Measured in the browser at exactly that, which is the padding token's value.
    //
    // The bar does not escape: it marks itself `data-wk-bleed`, and the container stands aside.
    // A zone holding a bleeding bar drops its own inset and pulls back the column's, so the head
    // reaches the column's edge FROM THE INSIDE — see dist/wirekit.css. Outside such a container
    // the prop does nothing at all, which is the right no-op.
    'bleed' => false,
    // An accessible name for the leading cluster.
    //
    // The cluster SCROLLS rather than clipping (see below), and a scroll region has to be
    // keyboard-operable. Where its content is interactive — a tab strip, a breadcrumb of
    // links — Tab reaches each item and the browser scrolls it into view, so nothing more is
    // owed. Where it is not — a long title, a status line — the region needs a name and a
    // tab stop of its own, and that is what passing a label turns on.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('shell-bar', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `rule="false"` would otherwise KEEP the rule. Normalize against each prop's
    // own default so a cast never engages a mode that was meant off.
    $rule = BooleanProp::from($rule, true);
    $sticky = BooleanProp::from($sticky, false);
    $bleed = BooleanProp::from($bleed, false);

    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--padding-wk-x-sm)]',
        'md' => 'px-[var(--padding-wk-x-md)]',
        'lg' => 'px-[var(--padding-wk-x-lg)]',
        'xl' => 'px-[var(--padding-wk-x-xl)]',
        default => WireKit::validateProp('shell-bar', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl']),
    };

    $alignClasses = match ($align) {
        'start' => 'justify-start',
        'center' => 'justify-center',
        'between' => 'justify-between',
        default => WireKit::validateProp('shell-bar', 'align', $align, ['start', 'center', 'between']),
    };

    // `h-`, not `min-h-`. An exact height is the entire contract: two bars whose
    // heights are allowed to differ under content pressure would break the line at
    // exactly the moment a column gets a longer title, on one breakpoint, silently.
    // Content that does not fit is the caller's to truncate — which is why the
    // default slot below is a `min-w-0` flex child.
    //
    // `shrink-0` for the same reason from the other direction: the bar is usually
    // the first child of a flex COLUMN, and a flex item's shrink would let a long
    // page steal pixels from the bar's height.
    // `w-full` belongs to the NON-bleeding branch only. Emitting both it and the bleed's
    // width leaves two width utilities of equal specificity on one element, and the winner is
    // then Tailwind's emission order rather than the prop — which is exactly what happened:
    // `w-auto` sorts BEFORE `w-full`, so `w-full` won and the bar shifted left by the host's
    // padding without ever widening to cover it. Measured in the browser: a 256px column, a
    // rule 223px long, and a 33px gap before the column's edge. The same defect class this
    // component's own rail records for its width, and sidebar.item for its foreground.
    $classes = WireKit::resolveClasses('shell-bar', 'base', implode(' ', [
        'wk-shell-bar',
        'flex shrink-0 items-center',
        $bleed ? '' : 'w-full',
        'h-[var(--size-wk-shell-bar,3.5rem)]',
        'gap-[var(--gap-wk-md)]',
        // The bar inherits the surface it is placed on rather than painting one.
        // A rail head, a sidebar head and a content head sit on three different
        // backgrounds in a toned shell; a background here would flatten all three
        // into one and undo the tone the developer chose.
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // The rule reads the rail's border role rather than --color-wk-border, so the
    // segment crossing a toned column matches that column's own edge instead of
    // drawing a light hairline across a dark surface. On an untoned column the
    // role falls back to --color-wk-border, so nothing changes there.
    $ruleClasses = $rule
        ? 'border-b-[length:var(--border-wk-width)] border-[var(--color-wk-rail-border,var(--color-wk-border))]'
        : '';

    $stickyClasses = $sticky ? 'sticky top-0 z-[var(--z-wk-sticky)]' : '';

    // The block margin cancels the host's own padding; the inline margin cancels that PLUS
    // the zone's inset, because a zone that insets its content to line up with the navigation
    // is a second layer of the same thing. Logical properties throughout, so a right-to-left
    // column bleeds on the side its content actually starts from.
    //
    // The BOTTOM is deliberately not canceled: the gap under the head is what separates the
    // rule from the first navigation item, and pulling it up would close it.
    // NOTHING. The bar no longer escapes its container — the container stands aside.
    //
    // It used to bleed with negative margins plus a width of `100% + 2 * the padding`, which
    // worked and was fragile in a way this repository has paid for before: an element that
    // relies on overflowing its parent is at the mercy of every ancestor, and any one of them
    // with `overflow: hidden`, `contain: layout`, a transform or a filter clips it without
    // warning. The mobile sweep caught the shape before a reader did — a 6px overflow on a zone
    // that happened not to clip.
    //
    // The `data-wk-bleed` marker lets the ancestors drop their own inset instead, in
    // dist/wirekit.css. Same result, no overflow, and nothing to clip.
    $bleedClasses = '';
@endphp

<div
    data-wk-shell-bar
    @if($bleed) data-wk-bleed @endif
    {{ $attributes->class([$classes, $paddingClasses, $alignClasses, $ruleClasses, $stickyClasses, $bleedClasses]) }}
>
    {{-- min-w-0 is load-bearing: a flex item's automatic minimum size is its content, so a
         long title would push the `end` cluster off the bar instead of truncating. With it,
         `truncate` on the title actually engages.

         It SCROLLS rather than clipping, and that came out of a mobile sweep: the console
         layout's top bar — three navigation items and two actions — is about 520px wide, and
         the content column on a 375px phone clipped 146px of it with `overflow: hidden`. The
         clipped part is not merely unseen, it is unreachable; nothing scrolls a box that has
         no scroller. A horizontally scrolling strip of tabs is what every phone-sized app bar
         does, and it costs desktop nothing because the overflow never engages there.

         `wk-shell-bar-strip` hides the scrollbar rather than thinning it, and that is a change
         of kind rather than of degree. A thin track still PAINTS, and where it paints is the
         bottom edge of a bar whose bottom edge is the shell's rule — so on a phone the reader
         got a short gray line under a clipped title and read it, correctly, as a rule that had
         come apart. Measured at 375px: the strip 65px wide with 210px of overflow, the thumb
         sitting in the last two pixels of a 56px bar.

         Nothing is lost with it gone. The affordance is the clipped item itself — a name cut
         mid-word says there is more of it — and the region stays scrollable by touch, by
         wheel, and by Tab, which scrolls each item into view as it takes focus. That last one
         is why this is not a trade against the keyboard: where the strip holds links, they are
         the tab stops; where it holds text, `label` gives the region its own. --}}
    @isset($start)
        {{-- The pinned leading cluster, and it exists because the cluster beside it scrolls.
             A hamburger, a back arrow or a workspace mark has to stay put — put one in the
             default slot and it scrolls away with the tabs the moment the bar overflows,
             which on a phone is immediately. `shrink-0` for the same reason the trailing
             cluster has it: the thing that gives way under pressure is the middle. --}}
        <div class="flex shrink-0 items-center gap-[var(--gap-wk-sm,0.5rem)]">
            {{ $start }}
        </div>
    @endisset
    <div
        {{-- Unconditional, and that is the fix. This wiring used to sit behind
             `@if($label)`, which tied a WCAG 2.1.1 obligation to a cosmetic prop:
             the strip always carries `overflow-x-auto`, and 35 of the 41 documented
             usages omit `label` — so the configuration that failed was the default
             one. The name falls back to a translated generic rather than being
             dropped, because a region with no name is announced as "group" and tells
             a screen-reader user nothing. --}}
        role="group"
        aria-label="{{ $label ?: __('Toolbar') }}"
        tabindex="0"
        @class([
            'wk-shell-bar-strip flex min-w-0 items-center gap-[var(--gap-wk-sm,0.5rem)] overflow-x-auto',
            // The strip is a tab stop now, so it must show that it has focus.
            'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
            // Grows to fill, so a child that asks for the full width gets it — a workspace
            // brand in a rail's head is the case. NOT applied under `align="center"`, where
            // the whole point is that the cluster is narrower than the bar and sits in the
            // middle of it. Under `start` and `between` it changes nothing visible: a cluster
            // already pinned to the leading edge looks the same whether or not it grows, and
            // the trailing cluster is `shrink-0` either way.
            'flex-1' => $align !== 'center',
            // Only when it is a tab stop: a focus a keyboard user cannot see is the other
            // half of the same requirement.
            'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-rail-ring,var(--color-wk-ring))]' => filled($label),
        ])
    >
        {{ $slot }}
    </div>
    @isset($end)
        {{-- The trailing cluster. `shrink-0` so actions keep their size and the
             title gives way first — the opposite is a bar whose buttons squash
             into unreadable slivers while a heading nobody needs in full stays
             intact. --}}
        <div class="flex shrink-0 items-center gap-[var(--gap-wk-sm,0.5rem)]">
            {{ $end }}
        </div>
    @endisset
</div>
