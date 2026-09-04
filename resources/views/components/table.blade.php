{{-- optimistic-ui: n/a — query
     Sort is a query round trip, same as the header cell it contains. --}}
@props([
    'striped' => config('wirekit.components.table.striped', false),
    'hoverable' => config('wirekit.components.table.hoverable', false),
    'compact' => config('wirekit.components.table.compact', false),
    'responsive' => config('wirekit.components.table.responsive', true),
    'stickyHeader' => false,
    // How tall the scroll-confined body may get before it scrolls. `stickyHeader` needs a
    // bounded height to mean anything at all — the wrapper already carries `overflow-x: auto`,
    // which makes it a scroll container on BOTH axes, so a `sticky` heading measures itself
    // against a box that never scrolls vertically and therefore never sticks. The height is
    // the CONDITION, not the decoration.
    //
    // It was a fixed `24rem` until a reader with a five-row table reported the obvious
    // consequence: a box that reserves 24rem, never scrolls, and is visibly worse than the
    // plain table it replaced. The default stays `24rem` so nothing that renders today moves;
    // a viewport-relative value like `70vh` is what a table of unknown length wants, because
    // the limit then simply never applies to the short case.
    //
    // Any CSS length, taken exactly as written. The value reaches the element as an inline
    // max-height rather than as a utility class, so it is not confined to the lengths a build
    // has already seen — `calc(100vh - 4rem)` arrives with its spaces intact. A length the
    // browser cannot parse is dropped like any other bad declaration, so an unusable value
    // leaves no max-height rather than a wrong one. The reasoning is with the code below.
    'stickyHeaderMax' => config('wirekit.components.table.sticky-header-max', '24rem'),
    'stickyColumn' => false, // freeze the FIRST column while the rest scroll horizontally
    'alpineSort' => false, // enable client-side Alpine sorting (no Livewire needed)
    // Accessible name for the responsive scroll wrapper, and the switch that makes it a
    // LANDMARK. The wrapper is keyboard-reachable either way (WCAG 2.1.1 — `tabindex="0"` is
    // unconditional); the role only joins it once there is a name worth navigating to. It used
    // to fall back to "Scrollable table", which made every table on a page answer to the same
    // rotor entry — axe reports that as `landmark-unique`. Name it after the DATA
    // ("Customer list"), never after the widget.
    'tableLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $stickyHeader = BooleanProp::from($stickyHeader, false);

    // The value reaches the element as an inline max-height rather than as a Tailwind class,
    // and that is the whole design of this prop rather than a shortcut. Two reasons, and
    // neither of them is a preference.
    //
    // Tailwind can only generate a utility for a value it has SEEN, and it sees source text.
    // A caller-supplied length exists in the caller's own Blade, so their build would generate
    // it — but this package's build cannot, and a class this package emits with no rule behind
    // it is a silently absent max-height. Worse, writing the utility with a variable inside its
    // brackets makes the scanner read the expression itself as a class name; both the compiled
    // stylesheet and the drift diff picked that up, twice, once from the attribute and once
    // from a comment describing it.
    //
    // An inline height has none of that: it needs no scanner, no safelist entry, and it takes
    // any CSS length exactly as written — `calc(100vh - 4rem)` included, with no escaping.
    $stickyHeaderStyle = $stickyHeader
        ? 'max-height: '.trim((string) $stickyHeaderMax).';'
        : null;
    $stickyColumn = BooleanProp::from($stickyColumn, false);
    $alpineSort = BooleanProp::from($alpineSort, false);

    // Base table classes — full width, collapse borders, use design tokens for typography
    $classes = WireKit::resolveClasses('table', 'base', implode(' ', [
        'wk-table',
        // A sticky-column table MUST be able to exceed its scroll container so the
        // frozen column has something to scroll past — `w-full` caps it at 100% and
        // the columns just compress (no horizontal scroll, sticky-column inert). Use
        // the natural content width (min 100%) so a wide table overflows + scrolls.
        $stickyColumn ? 'min-w-full w-max' : 'w-full',
        // As a flex item the table would shrink to fit the scroller and never
        // overflow, which removes the scrolling the sentinels are watching for.
        // `flex-shrink` is inert outside a flex container, so the non-responsive
        // branch renders exactly as before.
        $responsive ? 'shrink-0' : '',
        'border-collapse',
        'text-left',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // Flag classes passed via data attributes so sub-components (row, td, th) can
    // react to them via CSS selectors. This keeps logic in one place and avoids
    // prop drilling through @aware across 6 different sub-components.
    $tableAttrs = [];
    if ($striped) {
        $tableAttrs[] = 'data-wk-striped';
    }
    if ($hoverable) {
        $tableAttrs[] = 'data-wk-hoverable';
    }
    if ($compact) {
        $tableAttrs[] = 'data-wk-compact';
    }
    if ($stickyHeader) {
        $tableAttrs[] = 'data-wk-sticky-header';
    }
    if ($stickyColumn) {
        // NOTE: this marker is a cross-surface CONTRACT, not just internal CSS
        // plumbing — docs.wirekit.app keys a preview-frame width rule (pin the
        // preview to the content column so the wide table scrolls instead of
        // growing the frame) and a scroll-demo regression check off
        // [data-wk-sticky-column]. Renaming or dropping it breaks those silently.
        $tableAttrs[] = 'data-wk-sticky-column';
    }

    // Dev-mode warn for plain-HTML descendants. <x-wirekit::table> only
    // styles the outer <table>; padding / dividers / stripe / hover live
    // on sub-components (.head, .body, .row, .th, .td). A slot that
    // renders raw <thead> / <tbody> / <tr> / <td> elements produces a
    // visually-broken table with NO error — same gotcha as the card
    // composition pattern.
    //
    // Detection: scan the slot's rendered HTML for plain-HTML table
    // descendant tags. WireKit sub-components render as Blade attributes
    // (data-wk-table-*) that we can use to distinguish "wrapped via
    // <x-wirekit::table.head>" from "raw <thead>".
    //
    // The wirekit sub-components emit specific markers on their root:
    //   table.head  → <thead data-wk-table-head>
    //   table.body  → <tbody data-wk-table-body>
    //   table.row   → <tr data-wk-table-row>
    //   table.th    → <th data-wk-table-th>
    //   table.td    → <td data-wk-table-td>
    // (See resources/views/components/table/*.blade.php for emission.)
    //
    // Plain-HTML descendant detection: render the slot to string, walk
    // for `<thead`, `<tbody`, `<tr`, `<th`, `<td` opening-tag prefixes
    // that DON'T carry a data-wk-table-* marker.
    $rawSlot = (string) $slot;
    $hasPlainHtmlDescendants = false;
    if (config('app.debug') && $rawSlot !== '') {
        foreach (['<thead', '<tbody', '<tr', '<th', '<td'] as $tag) {
            // Match the opening of the tag; immediately check that the
            // very next 80 chars (the typical attribute-bag span before
            // the closing `>`) carry a data-wk-table-* marker.
            $offset = 0;
            while (($pos = strpos($rawSlot, $tag, $offset)) !== false) {
                $after = substr($rawSlot, $pos, 200);
                if (! str_contains($after, 'data-wk-table-')) {
                    // Make sure we didn't match a longer tag prefix
                    // (e.g. `<tr` inside `<treesomething>`) — the next
                    // char must be whitespace, `>`, or attribute-start.
                    $nextChar = $rawSlot[$pos + strlen($tag)] ?? '';
                    if ($nextChar === ' ' || $nextChar === '>' || $nextChar === "\t" || $nextChar === "\n") {
                        $hasPlainHtmlDescendants = true;
                        break 2;
                    }
                }
                $offset = $pos + strlen($tag);
            }
        }
    }
@endphp

{{-- Wrap in responsive container for horizontal scroll on narrow screens.
     WCAG 2.1.1 (Keyboard): an `overflow-x-auto` region that actually scrolls
     MUST be keyboard-reachable, otherwise a keyboard / switch user cannot pan
     to the off-screen columns (the "you can't even reach the content on the
     right" report). The focusable region + accessible name therefore apply to
     EVERY responsive wrapper, not only the sticky-header variant — a wide data
     table with no focusable cells was exactly the unreachable case. `min-w-0`
     lets the wrapper shrink below the table's intrinsic width inside a flex
     parent so the scroll engages instead of the table forcing document
     overflow. --}}
@if($responsive)
{{-- A table that scrolls sideways said so with nothing but the scrollbar, and on
     a phone there is no scrollbar until you are already dragging. A reader sees a column
     cut off at the edge and no reason to believe more exists.

     The apparatus for this shipped in 2.24 and no component template used it — the CSS
     (`wk-scroll-shadow-start` / `-end`) and the Alpine factory with its inline-axis
     sentinels were all already there. This is the wiring, not new machinery. --}}
{{-- `w-full min-w-0`: making the scroller a flex row gives it a max-content
     contribution its block ancestors then inherit, and a wrapper without an
     explicit minimum resolves `min-width: auto` to that content width — so a
     14-column table pushed this whole wrapper 449px past a phone-width docs
     column. Measured, not reasoned: the frame-escape sweep named this element. --}}
<div class="relative w-full min-w-0" x-data="wirekitStickyPanelShadows()">
<div
    x-ref="scroller"
    {{-- `flex` is load-bearing, not cosmetic. The sentinels are block elements, so in
         the default block scroller they stacked ABOVE and BELOW the table instead of
         sitting at its inline edges — an IntersectionObserver watching them therefore
         answered a vertical question, and the trailing-edge hint only appeared once
         something had already scrolled. Which is after the moment it exists for. --}}
    {{-- The scroller is a tab stop, so it needs a focus state a keyboard user can SEE —
         it is the only handle for panning to columns that are off screen, and without a
         ring the caret lands on it with nothing to show for it. The ring is drawn INSIDE
         the box, matching the sortable header button: an outset ring on an element that
         is itself a min-width-zero flex child adds width outside the border box, which is
         the one thing this wrapper spends its own comment above avoiding. --}}
    class="flex w-full min-w-0 overflow-x-auto wk-scrollbar focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] {{ $stickyHeader ? 'overflow-y-auto' : '' }}"
    @if($stickyHeaderStyle) style="{{ $stickyHeaderStyle }}" @endif
    {{-- Reachability is unconditional; the landmark is opt-in. `filled()` rather than `??`,
         because `role="region"` with an empty name is not exposed as a landmark at all — an
         interpolated caller value over a record with no title yields exactly that. --}}
    tabindex="0"
    @if(filled($tableLabel)) role="region" aria-label="{{ $tableLabel }}" @endif
>
{{-- One real pixel each, canceled by a negative margin on the side facing the table.

     The pixel is what the observer needs. A zero-AREA target sitting exactly on the
     scrollport edge is not dependably "intersecting", so at full scroll the trailing
     hint had no determinate off-state and could stay lit over the end of the table —
     the one moment the component promises it goes away. A zero inline size was
     chosen originally to spare every non-overflowing table two pixels of width —
     spelled out here in words rather than as the class, because Tailwind scans
     Blade comments too and would compile a utility nothing renders, which the
     reverse drift diff then reports as an untraceable selector. The sidebar zones
     and the
     scope-switcher list pay that same cost off with `-mb-px` / `-mt-px`, and the
     arithmetic carries over: 1px of width plus -1px of margin is zero net
     contribution on a `shrink-0` flex item. The observer gets its pixel and the
     reader none.

     The margin is logical, not physical. It has to land on the side facing the table
     so the pixel sits INSIDE the scroll content instead of protruding past its edge,
     and in a right-to-left table that side is the other one — which `-me-` / `-ms-`
     follow and `-mr-` / `-ml-` would not. --}}
<div x-ref="startSentinel" aria-hidden="true" class="w-px shrink-0 self-stretch -me-px"></div>
@endif
    <table
        {{ $attributes->class([$classes]) }}
        @foreach($tableAttrs as $attr) {{ $attr }} @endforeach
        {{-- Debug-only composition warning. It cannot be an inline console.warn:
             under Alpine's CSP build naming `console` throws while BUILDING the
             component, which takes down the very element being warned about.

             An element carries ONE Alpine component, so where the table is
             already sorting, the message rides along in that factory's config
             rather than claiming a second x-data. --}}
        @php
            $plainHtmlWarning = '[wirekit] table: plain thead/tbody/tr/th/td detected in slot — these inherit no styling (padding, row dividers, stripe, hover). Wrap your rows in the table.head / table.body / table.row / table.th / table.td sub-components. See https://docs.wirekit.app/components/table for the canonical composition.';
        @endphp
        @if($alpineSort)
            x-data="wirekitTableSort({ warning: {{ $hasPlainHtmlDescendants ? \Pushery\WireKit\Support\AlpinePayload::from($plainHtmlWarning) : 'null' }} })"
        @elseif($hasPlainHtmlDescendants)
            x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from($plainHtmlWarning) }} })"
        @endif
    >
        {{ $slot }}
    </table>
@if($responsive)
{{-- `-ms-px` mirrors the start sentinel: the canceling margin faces the table, so
     this pixel is the LAST pixel of the scroll content rather than one past it, and
     a scroller at maximum scroll therefore has it fully in view. --}}
<div x-ref="endSentinel" aria-hidden="true" class="w-px shrink-0 self-stretch -ms-px"></div>
</div>
{{-- aria-hidden: the shadow is an affordance for the eye. A screen reader is told about
     the scroll region by the role and label on the scroller itself. --}}
<div aria-hidden="true" x-cloak x-show="startShadow" x-transition.opacity class="wk-scroll-shadow-start"></div>
<div aria-hidden="true" x-cloak x-show="endShadow" x-transition.opacity class="wk-scroll-shadow-end"></div>
</div>
@endif
