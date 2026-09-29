{{-- optimistic-ui: n/a — navigation
     A workspace mark and its name. With an href it renders a link, and a link leaves the
     page rather than changing state; without one it renders no interactive element. --}}
@props([
    // The workspace's name. Drawn only where the rail is wide enough to read it; it stays
    // the mark's accessible name in every mode regardless, so a narrow rail is not an
    // unlabeled circle to a screen reader.
    'name' => null,
    // A second, quieter line under the name — the plan, the environment, the role. Drawn
    // only beside the name, never on its own: a subline without its subject says nothing.
    'description' => null,
    // Makes the whole block a link. Leave it out for a switcher — wrap the component in a
    // dropdown trigger instead, so the control is a button and announces itself as one.
    'href' => null,
    // There is a second mark slot, `expanded`, and it is not declared here because a named
    // slot is not a prop. It is documented here because this is where a reader looks.
    //
    // A rail that opens has two widths, and a brand usually has two forms: a signet that fits
    // in the narrow column, and a wordmark that is only legible in the wide one. Put the signet
    // in the default slot and the wordmark in `<x-slot:expanded>`, and the two swap with the
    // rail's LIVE state.
    //
    // Leave the slot out and nothing changes — the default slot is drawn at every width, byte
    // for byte as before. The markers below are emitted only when there is something to swap
    // with, so a rail with one mark carries no rule that could hide it.
    //
    // The accessible name comes from `name` and is unaffected, which is what makes the swap
    // safe. Both forms are presentation: the hidden one is `display: none` and reaches no
    // accessibility tree, while `name` stays in it at every width. A developer who instead
    // labels the images themselves gets two names for one thing, and only one of them at a
    // time — which is the trap this arrangement avoids rather than one it creates.
    'scope' => null,
])

{{-- Read from the enclosing app-rail: whether the name is drawn is the rail's mode, not
     this component's decision. --}}
@aware(['labels' => 'tooltip'])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('app-rail.brand', $attributes->getAttributes());

    // `@aware` — unlike `@props` — leaves its key in the attribute bag, so a key also written
    // on this tag would render as a stray HTML attribute.
    $attributes = $attributes->except(['labels']);

    $classes = WireKit::resolveClasses('app-rail.brand', 'base', implode(' ', [
        'flex w-full min-w-0 items-center',
        // Centered while the rail is narrow — the mark is all there is, and a mark pushed to
        // one side of a 56px column reads as a mistake.
        'justify-center gap-0',
        'group-data-[labels=inline]/wk-rail:justify-start',
        'group-data-[labels=inline]/wk-rail:gap-[var(--gap-wk-sm,0.5rem)]',
        // The mark shares the modules' center line, not their left edge, and the difference
        // between those two readings is this whole rule.
        //
        // The rail's plain inline tier on both sides would put the mark's left edge on the
        // icons' left edge. That is the same vertical line only for two things of the same
        // width, and these are not: the mark is `--size-wk-sm` (2rem) and a module icon is
        // 1.25rem. Sharing an edge would put the mark's mass half the difference right of the
        // column the icons make, and an eye reads a circle by its center.
        //
        // The narrow rail centers both in the column, so they already share a center. This
        // makes the wide rail agree.
        //
        // The subtraction assumes a mark the size the component ships with. That assumption is
        // held by a browser guard that compares the two centers rather than the two edges, so
        // a change to either size fails loudly instead of drifting six pixels at a time.
        //
        // It reads the module's own padding token, not the tier directly, and that is what
        // keeps this true beside an inset panel. There the modules take a wider inline padding
        // so their glyphs land on the column's axis; a mark subtracting from the fixed tier
        // would have stayed put while the glyphs moved, and the two would part by exactly the
        // amount the modules gained. Reading the same token means the mark follows whatever
        // the modules do, in every shell, with no second rule to keep in step.
        //
        // And it carries the rail's own inset, because the zone this lands in does not.
        // A module's padding counts from the start of the module list, and the rail insets
        // that list by `--wk-rail-inset-start`. The brand slot is rendered into a zone with no
        // inline padding at all, so without the inset a mark would begin its measurement one
        // inset earlier than every glyph below it, and the optical correction above would pull
        // it a further half-mark to the left. Only the wide rail needs it: symmetric padding
        // does not move a centered mark.
        //
        // The inset is taken from a variable rather than added outright, because there are TWO
        // shapes and only one of them is short of it. Put inside a shell-bar — the shape the
        // documentation teaches, so that the mark lines up with the heads of the columns beside
        // it — the bar has already taken the inset for the whole row, and a mark adding it again
        // would move by that amount in the shape that was correct. The stylesheet therefore
        // zeroes `--wk-rail-brand-inset` on a bar inside a rail, and both shapes land on one
        // line. A Blade template cannot see its own ancestors, which is why this half is CSS.
        'group-data-[labels=inline]/wk-rail:ps-[calc(var(--wk-rail-brand-inset,var(--wk-rail-inset-start,var(--padding-wk-y-sm)))+var(--wk-rail-item-pad,var(--padding-wk-x-sm))-((var(--size-wk-sm,2rem)-1.25rem)/2))]',
        'group-data-[labels=inline]/wk-rail:pe-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[color:var(--color-wk-rail-text)]',
    ]), $scope);

    // Only a link gets interactive affordances. A plain block that lit up on hover would
    // promise something it does not do.
    $interactiveClasses = $href !== null
        ? implode(' ', [
            'hover:bg-[var(--color-wk-rail-hover-bg)]',
            'focus-visible:outline-hidden',
            'focus-visible:ring-[length:var(--ring-wk-width)]',
            'focus-visible:ring-[var(--color-wk-rail-ring)]',
            'transition-colors duration-[var(--transition-wk-duration)]',
        ])
        : '';

    $nameClasses = WireKit::resolveClasses('app-rail.brand', 'name', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-sm)]',
        'leading-tight',
        // Wraps rather than truncating, for the same reason a module's name does: a clipped
        // workspace name is a workspace nobody can identify.
        'break-words',
    ]), $scope);

    $descriptionClasses = WireKit::resolveClasses('app-rail.brand', 'description', implode(' ', [
        'text-[length:var(--text-wk-xs)]',
        'leading-tight',
        'break-words',
        'text-[color:var(--color-wk-rail-muted)]',
    ]), $scope);

    $tag = $href !== null ? 'a' : 'div';
    // Auto-inject rel="noopener noreferrer" when target="_blank". This component
    // takes an href and echoes the caller's bag onto the element that carries it,
    // so the caller's target passed straight through to a bare anchor. The house
    // rule makes the injection unconditional for exactly that shape. Rendered
    // explicitly with the bag echoed via except('rel'), because
    // $attributes->merge() treats rel as a DEFAULT and a caller-supplied rel
    // would replace the computed value.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href !== null && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<{{ $tag }} data-wk-prose-skip
    @if($href !== null) href="{{ $href }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $interactiveClasses]) }}
>
    {{-- The mark. `shrink-0` so a long name can never squeeze it.
         NOT aria-hidden: this is a slot, and what a developer puts in it is theirs. Hiding it
         unconditionally would silence a logo with a title, or a status dot that means
         something — and the guard that caught this exists because that mistake is invisible
         until somebody who needs it reports it. A decorative avatar in here announces nothing
         on its own anyway; one that does is the developer's decision to make. --}}
    @isset($expanded)
        {{-- Two forms, swapped by the same live conjunction the name uses. The rule is in the
             shipped stylesheet, for the reason stated there: a bare Tailwind class exists only
             where a developer's build scanned this package. --}}
        <span data-wk-rail-brand-mark class="shrink-0">{{ $slot }}</span>
        <span data-wk-rail-brand-mark-wide class="min-w-0 shrink-0">{{ $expanded }}</span>
    @else
        <span class="shrink-0">{{ $slot }}</span>
    @endisset

    @if(filled($name))
        {{-- Visually hidden while the rail is narrow, never absent: the name is what
             identifies the workspace, and a screen-reader user gets no visual mark to fall
             back on.

             The marker does the deciding, not PHP. A boolean derived from `labels` at
             render time would keep the name of an `expandable` rail that starts narrow
             hidden at every width, because widening is an Alpine change that happens long
             after. Like the modules, the mark follows the rail's live attributes.

             The rule is in the shipped stylesheet rather than on this element, and the
             comment there carries the reasoning: the condition is a conjunction of the mode
             and the words-are-settled marker, each half preventing a different regression,
             and a bare Tailwind class exists only where a developer's build scanned this
             package. --}}
        <span data-wk-rail-brand-name class="min-w-0 flex flex-col">
            <span class="{{ $nameClasses }}">{{ $name }}</span>
            @if(filled($description))
                {{-- Drawn only beside the name. In the narrow rail it is dropped entirely
                     rather than read out: "Free plan" with no subject is noise in a screen
                     reader's landmark summary, and the name above already carries the
                     identity.

                     So this one is `display: none` and not merely visually hidden — that
                     distinction IS the paragraph above, and the stylesheet keeps it while
                     moving the decision to the live marker. --}}
                <span data-wk-rail-brand-desc class="{{ $descriptionClasses }}">{{ $description }}</span>
            @endif
        </span>
    @endif

    {{-- The other half of the target="_blank" rule: rel protects the opener, this
         warns the person who cannot see the new tab appear. It sits in the
         CONTENT because this link carries no aria-label — the workspace name
         above is its accessible name, computed from what is written here, so a
         span appended to it is announced. Every sibling that names itself the
         same way places the hint the same way. --}}
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
