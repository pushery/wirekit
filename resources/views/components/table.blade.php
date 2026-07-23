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
<div
    class="w-full min-w-0 overflow-x-auto wk-scrollbar {{ $stickyHeader ? 'max-h-96 overflow-y-auto' : '' }}"
    tabindex="0"
    role="region"
    aria-label="{{ $tableLabel ?? 'Scrollable table' }}"
>
@endif
    <table
        {{ $attributes->class([$classes]) }}
        @foreach($tableAttrs as $attr) {{ $attr }} @endforeach
        @if($alpineSort) x-data="wirekitTableSort()" @endif
        @if($hasPlainHtmlDescendants && ! $alpineSort)
            x-data
            x-init="console.warn('[wirekit] table: plain thead/tbody/tr/th/td detected in slot — these inherit no styling (padding, row dividers, stripe, hover). Wrap your rows in the table.head / table.body / table.row / table.th / table.td sub-components. See https://docs.wirekit.app/components/table for the canonical composition.')"
        @elseif($hasPlainHtmlDescendants && $alpineSort)
            x-init="console.warn('[wirekit] table: plain HTML descendants detected (see https://docs.wirekit.app/components/table). Wrap rows in the table.* sub-components.')"
        @endif
    >
        {{ $slot }}
    </table>
@if($responsive)
</div>
@endif
