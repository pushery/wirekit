{{-- optimistic-ui: n/a — passthrough
     A navigation entry that may carry a developer action; the action's result is not this component's. --}}
@props([
    'href' => '#',
    // `a` or `button`. A rail module is a destination, so a link is the default and stays
    // the default — this exists for the OTHER thing a rail entry legitimately is: the
    // trigger of a dropdown, which our own console-shell blueprint builds out of one.
    //
    // As a link that trigger is subtly broken: a link activates on Enter and not on Space,
    // so Space would leave the menu closed and `aria-expanded` at "false". In a `viewport`
    // shell Space is not even scrolling, so the key would have no effect anywhere. On top of that every click on an `href="#"`
    // pushes a history entry and writes `…#` into the address bar, and a modified click
    // opens a dead duplicate tab.
    //
    // Additive on purpose: switching automatically when `href` is "#" would have been the
    // tidier API and would have changed the rendered element under every existing caller,
    // including their CSS.
    'as' => 'a',
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
    // Opt-in: a visible name that does not fit is cut with an ellipsis instead of wrapping.
    //
    // This is a deliberate exception to an absolute rule, scoped to exactly what the rule
    // protects. The rule below (a visible name is never truncated) is about destinations:
    // "Insig…" cannot be told apart from "Insights" or "Insight reports", so a clipped module
    // name names nothing. That argument does not reach an entry that is not a destination —
    // the account trigger at the foot of a rail, labeled with a person's name, which is
    // unambiguous even when cut and whose row would otherwise grow past its neighbors.
    //
    // So the default stays the rule, and nothing changes for any existing rail. A caller
    // who sets this takes the trade knowingly, on an entry where the name is not what tells
    // the entries apart. The full name stays in the accessible name untouched (the span is
    // clipped visually, never shortened), and it is repeated as a `title`, because the kit's
    // own tooltip switches itself off while the rail is expanded and a clipped name would
    // otherwise have no way back to its full text for a sighted reader.
    'truncate' => false,
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
    $truncate = BooleanProp::from($truncate, false);

    // `@aware` hands back the value the PARENT was called with, before that component's
    // own normalization ran — so `expandable` arrives raw here and gets the same
    // treatment it got there. And, unlike `@props`, `@aware` does not remove its keys
    // from the attribute bag, so a key also written on this tag would render as a stray
    // HTML attribute. Blade accepts both spellings, so both are dropped.
    $expandable = BooleanProp::from($expandable, false);
    $attributes = $attributes->except(['labels', 'expandable']);

    // A caller may mark the module current with `data-current` in the attribute bag
    // instead of `:active`; an explicit `:active` always wins. Livewire's own
    // `data-current` on a `wire:navigate` link is written in the browser and never
    // reaches this bag.
    if (! $active) {
        $dataCurrent = $attributes->get('data-current');
        if ($dataCurrent === true || $dataCurrent === 'true' || $dataCurrent === '1' || $dataCurrent === 'page') {
            $active = true;
        }
    }

    $labelText = $label !== '' ? $label : \Pushery\WireKit\Support\SlotContent::text($slot);

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
        // The inline padding reads a token so the BRAND can read the same one. Beside an
        // inset panel `--size-wk-rail`'s inset-and-border budget is underspent and the
        // leftover lands inside this module, so the column re-points this token there and the
        // glyph ends up on the column's axis instead of 2.5px beside it. Everywhere else the
        // fallback is the tier this always used. See the `[data-wk-nav-gutter]` block in
        // dist/wirekit.css for the derivation.
        'px-[var(--wk-rail-item-pad,var(--padding-wk-x-sm))] py-[var(--padding-wk-y-sm)]',
        'gap-[var(--padding-wk-x-sm)]',
        // Leading-aligned, NOT centered — and in the icon-only mode that looks identical,
        // because `--size-wk-rail` is derived from exactly this content: the icon plus these
        // paddings plus the border. Centering there had nothing left to center.
        //
        // Where it stops being identical is the panel shell, which widens the column by the
        // gap to the panel and insets the contents by it. The module is then wider than the
        // glyph and its padding, and a leading-aligned glyph sits off the module's own center
        // by half the surplus.
        //
        // Not centered: a folding column must not move its icon sideways, and centering the
        // icon-only mode alone would do exactly that. The expanded row carries a label beside
        // its glyph and has to stay leading-aligned, so a centered glyph would move by half the
        // surplus on every fold.
        //
        // The surplus exists only beside an inset panel. Removing it would satisfy both, and
        // it cannot be removed without moving one of two further guarded edges: the panel's
        // even four-sided inset, or the module's symmetric visible chrome. Three requirements,
        // satisfiable in pairs and not together, which makes it a decision rather than a defect.
        'justify-start',
        // …except with a caption UNDER the icon, where the row is `flex-col` and the main
        // axis is vertical. There `justify-center` centers the pair in the row's height,
        // which is a different question and still the right answer. Horizontal centering in
        // that mode comes from `items-center` above, on the cross axis.
        'group-data-[labels=below]/wk-rail:justify-center',
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
        'focus-visible:outline-hidden',
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
        // It sits INSIDE the row. Pulled out into the column's gutter by a negative inset,
        // it would need clearance on the row's end side that the start side does not have,
        // so a column whose rows look symmetrical would not be, and the marker would ride
        // on the outside of a rounded box, where a shape meant to say "this row" stops
        // belonging to the row. Fully rounded, and inset by the hairline that keeps it
        // clear of the row's own corner radius.
        'group-data-[indicator=edge]/wk-rail:after:absolute',
        'group-data-[indicator=edge]/wk-rail:after:top-[20%]',
        'group-data-[indicator=edge]/wk-rail:after:bottom-[20%]',
        'group-data-[indicator=edge]/wk-rail:after:end-[2px]',
        'group-data-[indicator=edge]/wk-rail:after:w-[3px]',
        'group-data-[indicator=edge]/wk-rail:after:rounded-[var(--radius-wk-full)]',
        'group-data-[indicator=edge]/wk-rail:after:bg-[var(--color-wk-rail-active-text)]',
    ]), $scope);

    // The glyph follows the token the rail's width is built from, rather than a literal of the
    // same size: a theme that set `--size-wk-rail-icon` widened the rail around icons that did
    // not grow. Under the name (`labels="below"`) it follows its own token, which defaults to the
    // same size, so the labeled rail can carry a larger icon without widening the narrow one.
    $iconClasses = WireKit::resolveClasses('app-rail.item', 'icon', implode(' ', [
        'size-[var(--size-wk-rail-icon)]',
        'group-data-[labels=below]/wk-rail:size-[var(--size-wk-rail-icon-labeled)]',
    ]), $scope);

    // `sr-only` is the RESTING state, never `hidden` — the string is the link's
    // accessible name and has to survive every mode.
    //
    // AND IT IS NEVER TRUNCATED BY DEFAULT. A navigation entry whose name is clipped does not name
    // anything: "Insig…" is not a destination, and the reader cannot tell it from
    // "Insights" or "Insight reports" without hovering. Maintainer's rule, and it is
    // absolute — so a name that does not fit WRAPS. An item two lines tall beside items
    // one line tall is a small untidiness; a module nobody can identify is a defect.
    //
    // The one way out is `truncate`, opt-in and argued at the prop: it exists for an entry
    // that is not a destination, and it leaves this default exactly as it was.
    //
    // `break-words` rather than plain wrapping, because a single long word has no space to
    // break at and would otherwise overflow the column instead of wrapping inside it.
    $labelClasses = WireKit::resolveClasses('app-rail.item', 'label', implode(' ', [
        'sr-only',
        // The words wait for `data-wk-names`; the mode does not. Held together on one marker,
        // the icon would ride the widening column out to its middle and snap back at the end.
        // The mode still decides HOW a
        // name is laid out (below, inline); this decides only WHETHER it is in the layout yet.
        'group-data-[wk-names]/wk-rail:not-sr-only',
        // `max-w-full`, NOT a full width, and the difference is the whole fix. The reset of
        // `sr-only` on the line above sets `width: auto`, and it is emitted after the width
        // utility at the same specificity, so the name was as wide as its text: no wrapping
        // could apply, and "Benachrichtigungen" ran out of the rail. That reset does not touch
        // `max-width`, so this bound holds in every build. (The reset's class name is not
        // written out here: Tailwind reads this file as text and would compile it bare.)
        'group-data-[labels=below]/wk-rail:max-w-full',
        'group-data-[labels=below]/wk-rail:break-words',
        // A long compound word breaks at a syllable with a hyphen, where the document's `lang`
        // tells the browser how; `break-words` above stays the fallback for a word it cannot
        // hyphenate.
        'group-data-[labels=below]/wk-rail:hyphens-auto',
        'group-data-[labels=below]/wk-rail:text-center',
        'group-data-[labels=below]/wk-rail:text-[length:var(--text-wk-xs)]',
        'group-data-[labels=below]/wk-rail:leading-tight',

        'group-data-[labels=inline]/wk-rail:min-w-0',
        'group-data-[labels=inline]/wk-rail:flex-1',
        'group-data-[labels=inline]/wk-rail:break-words',
        'group-data-[labels=inline]/wk-rail:text-[length:var(--text-wk-sm)]',

        // Only when the caller opted in. `min-w-0` lets the span shrink below its content in
        // every flex context; `flex-1` is the half `inline` already carries and the expanded
        // `tooltip` state never had, without which an ellipsis has no bounded box to appear in.
        // Written out literally because Tailwind reads source text and cannot extract a class
        // name assembled at runtime.
        ...($truncate ? [
            'group-data-[wk-names]/wk-rail:min-w-0',
            'group-data-[labels=tooltip]/wk-rail:flex-1',
            'group-data-[wk-names]/wk-rail:truncate',
        ] : []),
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
    $railTag = \Pushery\WireKit\WireKit::validateProp('app-rail.item', 'as', (string) $as, ['a', 'button']);

    // A `<button>` arrives with a border, a background, its own font and centered text, and
    // none of that is in the rail's class list because an anchor needs none of it. The reset
    // lives in the SHIPPED stylesheet, not as utilities here — otherwise `as="button"` would
    // work only where the developer's build scanned this package, which is the same failure
    // the drawer panel had.
    $buttonReset = $railTag === 'button' ? 'wk-rail-item-button' : '';

    // The pointer rides the same condition but stays a utility rather than joining
    // that reset rule. Tailwind v4's preflight no longer sets `cursor: pointer` on
    // `button` (v3 did), so every button in this package puts the affordance back through
    // its own class list — the rail item is the one that would spell it somewhere
    // else, and a cursor is an affordance rather than a piece of the UA-chrome reset
    // the rule above undoes. The `<a>` branch gets none: an anchor with an href
    // already carries the pointer, and one without should not claim it.
    $buttonCursor = $railTag === 'button' ? 'cursor-pointer' : '';

    $linkAttributes = $attributes->except('rel')->class(['wk-rail-item', $classes, $activeClasses => $active, $buttonReset => $railTag === 'button', $buttonCursor => $railTag === 'button']);
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

     `w-full` makes the wrapper fill the rail's column; without it the module's hover
     target is narrower than the row it appears to occupy. The `block` beside it does not
     change the display: Tailwind emits `.inline-block` after `.block`, so the tooltip's
     own `inline-block` wins that tie. `focusable-trigger="false"` because the slot is already an
     <a> — the default would put a second tab stop in front of every module. --}}
@if($needsTooltip)
    {{-- The tooltip must go quiet the moment the label becomes visible, so `disabled` is BOUND
         to live state rather than decided at render: the tooltip reads the attribute at trigger
         time. Every rail can show its names at runtime, an expandable one on its toggle and any
         rail in a drawer it has to itself, and `expanded` is in scope because the rail's Alpine
         component wraps this subtree. --}}
    <x-wirekit::tooltip :text="$labelText" :placement="$placement" focusable-trigger="false" class="block w-full" x-bind:data-wk-tooltip-disabled="expanded">@include('wirekit::components.partials.app-rail-link')</x-wirekit::tooltip>
@else
    @include('wirekit::components.partials.app-rail-link')
@endif
