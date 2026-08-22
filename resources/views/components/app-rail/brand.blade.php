{{-- optimistic-ui: n/a — presentational
     A workspace mark and its name. It renders no interactive element of its own unless the
     developer gives it an href, and a link is navigation rather than a mutation. --}}
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

    // The name is drawn ONLY in the wide rail. In `below` mode the column is sized for a
    // module caption of about ten characters — a workspace name would wrap to four lines and
    // push the modules off the screen, which is worse than not drawing it. It stays the
    // accessible name in every mode either way.
    $namesVisible = $labels === 'inline';

    $classes = WireKit::resolveClasses('app-rail.brand', 'base', implode(' ', [
        'flex w-full min-w-0 items-center',
        // Centered while the rail is narrow — the mark is all there is, and a mark pushed to
        // one side of a 56px column reads as a mistake.
        'justify-center gap-0',
        'group-data-[labels=inline]/wk-rail:justify-start',
        'group-data-[labels=inline]/wk-rail:gap-[var(--gap-wk-sm,0.5rem)]',
        // THE MARK SHARES THE MODULES' CENTER LINE, NOT THEIR LEFT EDGE — and the difference
        // between those two readings is this whole rule.
        //
        // It used to use the rail's plain inline tier on both sides, which put the mark's LEFT
        // edge on the icons' left edge. That is the same vertical line only for two things of
        // the same width, and these are not: the mark is `--size-wk-sm` (2rem) and a module
        // icon is 1.25rem. Sharing an edge therefore puts the mark's mass six pixels right of
        // the column the icons make, and an eye reads a circle by its center. Reported as
        // "indented a bit too far right", measured at exactly that: mark left 28, icon left
        // 28, icon center 38, mark center 44.
        //
        // The narrow rail never had the problem — there both are centered in the column, so
        // they already share a center (measured 40 and 40). This makes the wide rail agree.
        //
        // The subtraction assumes a mark the size the component ships with. That assumption is
        // held by a browser guard that compares the two CENTERS rather than the two edges, so
        // a change to either size fails loudly instead of drifting six pixels at a time.
        'group-data-[labels=inline]/wk-rail:ps-[calc(var(--padding-wk-x-sm)-((var(--size-wk-sm,2rem)-1.25rem)/2))]',
        'group-data-[labels=inline]/wk-rail:pe-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'text-[color:var(--color-wk-rail-text)]',
    ]), $scope);

    // Only a link gets interactive affordances. A plain block that lit up on hover would
    // promise something it does not do.
    $interactiveClasses = $href !== null
        ? implode(' ', [
            'hover:bg-[var(--color-wk-rail-hover-bg)]',
            'focus-visible:outline-none',
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
@endphp

<{{ $tag }}
    @if($href !== null) href="{{ $href }}" @endif
    {{ $attributes->class([$classes, $interactiveClasses]) }}
>
    {{-- The mark. `shrink-0` so a long name can never squeeze it.
         NOT aria-hidden: this is a slot, and what a developer puts in it is theirs. Hiding it
         unconditionally would silence a logo with a title, or a status dot that means
         something — and the guard that caught this exists because that mistake is invisible
         until somebody who needs it reports it. A decorative avatar in here announces nothing
         on its own anyway; one that does is the developer's decision to make. --}}
    <span class="shrink-0">{{ $slot }}</span>

    @if(filled($name))
        {{-- `sr-only` while the rail is narrow, never absent: the name is what identifies the
             workspace, and a screen-reader user gets no visual mark to fall back on. --}}
        <span @class([
            'min-w-0 flex flex-col',
            'sr-only' => ! $namesVisible,
        ])>
            <span class="{{ $nameClasses }}">{{ $name }}</span>
            @if(filled($description))
                {{-- Drawn only beside the name. In the narrow rail it is dropped entirely
                     rather than read out: "Free plan" with no subject is noise in a screen
                     reader's landmark summary, and the name above already carries the
                     identity. --}}
                @if($namesVisible)
                    <span class="{{ $descriptionClasses }}">{{ $description }}</span>
                @endif
            @endif
        </span>
    @endif
</{{ $tag }}>
