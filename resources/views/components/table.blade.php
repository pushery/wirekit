{{-- optimistic-ui: n/a — query
     Sort is a query round trip, same as the header cell it contains. --}}
@props([
    'striped' => config('wirekit.components.table.striped', false),
    'hoverable' => config('wirekit.components.table.hoverable', false),
    'compact' => config('wirekit.components.table.compact', false),
    'responsive' => config('wirekit.components.table.responsive', true),
    'stickyHeader' => false,
    'stickyColumn' => false, // freeze the FIRST column while the rest scroll horizontally
    'alpineSort' => false, // enable client-side Alpine sorting (no Livewire needed)
    // WCAG 2.1.1 (Keyboard) — when stickyHeader makes the table body
    // scroll-confined, the wrapper becomes a focusable scrollable region
    // and gets a name so screen-reader users can recognize it.
    'tableLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $stickyHeader = BooleanProp::from($stickyHeader, false);
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
    class="flex w-full min-w-0 overflow-x-auto wk-scrollbar {{ $stickyHeader ? 'max-h-96 overflow-y-auto' : '' }}"
    tabindex="0"
    role="region"
    aria-label="{{ $tableLabel ?? __('Scrollable table') }}"
>
{{-- Zero inline size: as flex items these now sit IN the row, so a `w-px` each
     would widen every non-overflowing table by two pixels — a layout change paid by
     everyone to detect a condition that is not present. `w-0` still has a box for
     the observer to watch. --}}
<div x-ref="startSentinel" aria-hidden="true" class="w-0 shrink-0 self-stretch"></div>
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
<div x-ref="endSentinel" aria-hidden="true" class="w-0 shrink-0 self-stretch"></div>
</div>
{{-- aria-hidden: the shadow is an affordance for the eye. A screen reader is told about
     the scroll region by the role and label on the scroller itself. --}}
<div aria-hidden="true" x-cloak x-show="startShadow" x-transition.opacity class="wk-scroll-shadow-start"></div>
<div aria-hidden="true" x-cloak x-show="endShadow" x-transition.opacity class="wk-scroll-shadow-end"></div>
</div>
@endif
