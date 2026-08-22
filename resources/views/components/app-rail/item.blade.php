{{-- optimistic-ui: n/a — passthrough
     A navigation entry that may carry a developer action; the action's result is not this component's. --}}
@props([
    'href' => '#',
    'active' => false,
    // The module's icon. A bare name string ("chart-bar") resolves through the WireKit
    // icon system; a <x-slot:icon> or inline markup renders verbatim. Consistent with
    // sidebar.item / dropdown.item / command-palette.item.
    'icon' => null,
    // The module's name. REQUIRED, and not merely by convention: in the rail's default
    // mode nothing of it is drawn, so this string is the link's ONLY accessible name.
    // Leave it out and a screen reader announces "link" — the failure that makes icon
    // rails inaccessible in practice, and the reason this is a prop rather than the
    // default slot (a slot cannot also be a tooltip's text without rendering twice).
    'label' => '',
    // A trailing counter. Digits where a label is visible, a dot where it is not — the
    // digits have no room in a 3.5rem rail, but an unread signal must not simply vanish
    // where it matters most. The count stays in the accessible name in BOTH states.
    'badge' => null,
    // Where the tooltip opens. `right` is correct for a rail on the inline-start edge;
    // a right-hand rail wants `left`.
    'placement' => 'right',
    'scope' => null,
])

{{-- Read from the enclosing app-rail component. The item cannot decide on its own
     whether its label is drawn — that is the rail's mode — and prop-drilling it onto
     every module is exactly the repetition `@aware` exists to remove. --}}
@aware(['labels' => 'tooltip', 'expandable' => false])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('app-rail.item', $attributes->getAttributes());

    $active = BooleanProp::from($active, false);

    // `@aware` hands back the value the PARENT was called with, before that component's
    // own normalization ran — so `expandable` arrives raw here and gets the same
    // treatment it got there. And, unlike `@props`, `@aware` does not remove its keys
    // from the attribute bag, so a key also written on this tag would render as a stray
    // HTML attribute. Blade accepts both spellings, so both are dropped.
    $expandable = BooleanProp::from($expandable, false);
    $attributes = $attributes->except(['labels', 'expandable']);

    // Livewire 4 emits `data-current` on a `wire:navigate` link automatically. Honoring
    // it means a developer does not repeat routing knowledge the route file already
    // holds on every module. Explicit `:active` always wins.
    if (! $active) {
        $dataCurrent = $attributes->get('data-current');
        if ($dataCurrent === true || $dataCurrent === 'true' || $dataCurrent === '1' || $dataCurrent === 'page') {
            $active = true;
        }
    }

    $labelText = $label !== '' ? $label : trim((string) $slot);

    // A tooltip is rendered ONLY where the label is not drawn. Beside a visible caption
    // it is not merely redundant: it gives the link a second source of the same name,
    // which a screen-reader user pays for twice.
    $needsTooltip = $labels === 'tooltip' && $labelText !== '';

    $classes = WireKit::resolveClasses('app-rail.item', 'base', implode(' ', [
        // `relative` is the containing block for both the counter dot and the edge
        // indicator; without it they would anchor to the scroller.
        'relative flex items-center',
        // Derived from the container rather than fixed: a rounded column re-points this for
        // its own subtree so the two arcs stay concentric. See dist/wirekit.css.
        'rounded-[var(--radius-wk-nav-item)]',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'gap-[var(--padding-wk-x-sm)]',
        // The icon-only default: one centered glyph.
        'justify-center',
        // A caption under the icon. A tighter gap than the row form — a vertical pair
        // reads as one unit at a spacing that would look cramped horizontally.
        //
        // The inline padding drops to the xs tier in this mode, and that is what buys the
        // caption its room: at the sm tier the pill ate 20px of an 76px column and every
        // name over seven characters was clipped. The pill still spans the full column, so
        // nothing about the hover target changes.
        'group-data-[labels=below]/wk-rail:flex-col group-data-[labels=below]/wk-rail:gap-[2px]',
        'group-data-[labels=below]/wk-rail:px-[var(--padding-wk-x-xs)]',
        // The label beside the icon, in the wide rail.
        'group-data-[labels=inline]/wk-rail:justify-start',
        // Resting foreground scoped to NON-active items. Unscoped, this and the active
        // block's foreground are both single-class selectors in the same layer, so the
        // winner would be decided by Tailwind's emission order rather than by state —
        // the exact defect sidebar.item documents, where the active item rendered muted.
        'not-[[aria-current]]:text-[color:var(--color-wk-rail-muted)]',
        'not-[[aria-current]]:hover:bg-[var(--color-wk-rail-hover-bg)]',
        'not-[[aria-current]]:hover:text-[color:var(--color-wk-rail-hover-fg)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-rail-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
    ]), $scope);

    // Two ways to mark the current module, and they are alternatives rather than a base
    // with an addition: `pill` fills the item's box, `edge` draws a bar on the rail's
    // inline-end edge and deliberately leaves the box unfilled. Doing both reads as two
    // selections.
    $activeClasses = WireKit::resolveClasses('app-rail.item', 'active', implode(' ', [
        'text-[color:var(--color-wk-rail-active-text)]',
        'font-[number:var(--font-wk-body-weight)]',
        'group-data-[indicator=pill]/wk-rail:bg-[var(--color-wk-rail-active-bg)]',
        // The foreground ON the fill, which stops being the same color as the foreground on
        // the column once the column is colored — see the accent tone in dist/wirekit.css.
        'group-data-[indicator=pill]/wk-rail:text-[color:var(--color-wk-rail-active-fg)]',
        // The edge bar. Inset as a percentage rather than given a height, so it tracks
        // the row in every labeling mode — a fixed height is wrong the moment a caption
        // makes the row taller.
        //
        // IT SITS INSIDE THE ROW, and that is a correction. It used to be pulled OUT by a
        // negative inset equal to the scroller's padding, so it painted in the column's
        // own gutter rather than on the row. Two things followed. The row then needed
        // clearance on its end side that its start side did not have, so a column whose
        // rows look symmetrical was not — and the marker rode on the outside of a rounded
        // box, which is where a shape that is meant to say "this row" stops belonging to
        // the row at all. Fully rounded now, and inset by the hairline that keeps it clear
        // of the row's own corner radius.
        'group-data-[indicator=edge]/wk-rail:after:absolute',
        'group-data-[indicator=edge]/wk-rail:after:top-[20%]',
        'group-data-[indicator=edge]/wk-rail:after:bottom-[20%]',
        'group-data-[indicator=edge]/wk-rail:after:end-[2px]',
        'group-data-[indicator=edge]/wk-rail:after:w-[3px]',
        'group-data-[indicator=edge]/wk-rail:after:rounded-[var(--radius-wk-full)]',
        'group-data-[indicator=edge]/wk-rail:after:bg-[var(--color-wk-rail-active-text)]',
    ]), $scope);

    $iconClasses = WireKit::resolveClasses('app-rail.item', 'icon', 'h-5 w-5', $scope);

    // `sr-only` is the RESTING state, never `hidden` — the string is the link's
    // accessible name and has to survive every mode.
    //
    // AND IT IS NEVER TRUNCATED. A navigation entry whose name is clipped does not name
    // anything: "Insig…" is not a destination, and the reader cannot tell it from
    // "Insights" or "Insight reports" without hovering. Maintainer's rule, and it is
    // absolute — so a name that does not fit WRAPS. An item two lines tall beside items
    // one line tall is a small untidiness; a module nobody can identify is a defect.
    //
    // `break-words` rather than plain wrapping, because a single long word has no space to
    // break at and would otherwise overflow the column instead of wrapping inside it.
    $labelClasses = WireKit::resolveClasses('app-rail.item', 'label', implode(' ', [
        'sr-only',
        'group-data-[labels=below]/wk-rail:not-sr-only',
        'group-data-[labels=below]/wk-rail:w-full',
        'group-data-[labels=below]/wk-rail:break-words',
        'group-data-[labels=below]/wk-rail:text-center',
        'group-data-[labels=below]/wk-rail:text-[length:var(--text-wk-xs)]',
        'group-data-[labels=below]/wk-rail:leading-tight',
        'group-data-[labels=inline]/wk-rail:not-sr-only',
        'group-data-[labels=inline]/wk-rail:min-w-0',
        'group-data-[labels=inline]/wk-rail:flex-1',
        'group-data-[labels=inline]/wk-rail:break-words',
        'group-data-[labels=inline]/wk-rail:text-[length:var(--text-wk-sm)]',
    ]), $scope);

    // Auto-inject rel="noopener noreferrer" when target="_blank". `$attributes->merge`
    // would treat rel as a DEFAULT, so a caller's own rel (even rel="prev") would win
    // and silently defeat the tabnabbing guard — hence the remove-and-render form.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    // Resolved HERE rather than inside the partial, and that is not tidiness. Inside a
    // component's slot `$attributes` is the WRAPPER's bag, not this component's — so a
    // partial that reached for it would silently render the tooltip's attributes onto
    // the link in the tooltip branch and this component's in the other, which is a
    // difference no test would notice until something depended on it.
    $linkAttributes = $attributes->except('rel')->class([$classes, $activeClasses => $active]);
@endphp

{{-- THREE literal branches, and the shape is forced rather than chosen.

     Blade compiles component tags in a pass that runs BEFORE statements and echoes, with
     its own attribute scanner — so neither `@if(…) x-bind:… @endif` nor a spread
     `{{ $bag }}` inside the tag is understood. Both leave the OPENING tag uncompiled
     while the closing tag compiles normally, and the view then dies on an `endif` with
     nothing to open it, at a line number that names the wrong thing entirely.

     So each branch carries a complete, literal tag pair. What would otherwise be three
     copies of the link is one @include — the same reason the sidebar's zones live in a
     partial: the copy that drifts is always the second one.

     `block` overrides the tooltip's own `inline-block` so the wrapper fills the rail's
     column; an inline-block wrapper leaves the module's hover target narrower than the
     row it appears to occupy. `focusable-trigger="false"` because the slot is already an
     <a> — the default would put a second tab stop in front of every module. --}}
@if($needsTooltip && $expandable)
    {{-- Expandable: the tooltip must go quiet the moment the label becomes visible, so
         `disabled` is BOUND to live state rather than decided at render — the tooltip
         reads the attribute at trigger time. `expanded` is in scope because the rail's
         Alpine component wraps this subtree. --}}
    <x-wirekit::tooltip :text="$labelText" :placement="$placement" focusable-trigger="false" class="block w-full" x-bind:data-wk-tooltip-disabled="expanded">@include('wirekit::components.partials.app-rail-link')</x-wirekit::tooltip>
@elseif($needsTooltip)
    <x-wirekit::tooltip :text="$labelText" :placement="$placement" focusable-trigger="false" class="block w-full">@include('wirekit::components.partials.app-rail-link')</x-wirekit::tooltip>
@else
    @include('wirekit::components.partials.app-rail-link')
@endif
