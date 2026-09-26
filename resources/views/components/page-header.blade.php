{{-- optimistic-ui: n/a — presentational
     Renders no interactive element of its own. What a call site puts in the `actions`
     slot may be optimistic; that is the button's contract, not this one's. --}}
@props([
    // The page title. The default slot says the same thing and wins when both are given,
    // because a slot can carry markup a prop cannot — a badge beside the name, a link.
    'title' => null,
    // The heading level. ONE by default, which is the whole reason this component exists:
    // the thirteen screens it was reported from used an h2 in a card, an h1 at `lg` and an
    // h1 at `2xl`, and the cap heights measured 12, 14 and 17px on pages that are siblings.
    // A screen has one title, and that title is the document's heading.
    'level' => 1,
    // Optional sentence under the title. A `description` SLOT takes the same place when
    // the text needs markup.
    'description' => null,
    // The heading's id, settable because something else has to be able to name it: a
    // dialog points `aria-labelledby` at its title, and a skip link needs a target.
    'headingId' => null,
    // Heading size, when the level's own size is not the one this screen wants. Passed
    // through to `heading` unchanged, so the ladder is the one documented there.
    'size' => null,
    // Write `tabindex="-1"` on the heading, so something can send focus to it.
    //
    // A heading is not focusable, and a dialog that returns focus with `focus-return-to`
    // needs a target that is. The case is a delete confirmation that sits in the row it
    // deletes: on confirm, the dialog AND its trigger both disappear, so focus has nowhere
    // to go back to and the page title is the nearest honest place (WCAG 2.4.3).
    //
    // ⚠️ A prop rather than "the overlay sets it at runtime", and the difference is the
    // whole reason this exists. `utils/overlay.js` does set `tabindex="-1"` on a target that
    // has none — but an attribute written at runtime is absent from the template a Livewire
    // morph patches against, so the next update can take it away again. Exactly the class
    // this catalog spent a day on with inline placement styles.
    //
    // Off by default: a `tabindex` nobody asked for is a promise about focus order, and a
    // screen that never returns focus here should not carry one.
    'headingFocusable' => false,
    // Put the actions on their own line below this width, whatever they are wide.
    //
    // Without it the break depends on the LABEL: the title column asks for 16rem and the row
    // wraps when 16rem plus the gap plus the actions no longer fit. Reported from a 375px phone
    // where the sum came to roughly 351px against 343px of content column — the actions dropped,
    // with about 8px to spare. A shorter label, a narrower face or a `sm` button stays beside the
    // title, and then a two-line description lives in 16rem.
    //
    // ⚠️ THE WIDTH IS THE HEADER'S OWN, NOT THE WINDOW'S, and that was measured rather than
    // chosen. The first attempt used viewport breakpoints (`max-sm:`) the way `button.group`
    // does — and in a 375px-wide container inside a wide window it changed nothing at all:
    // `flex-basis` stayed 256px and the actions stayed on the line. A page header lives inside a
    // content column, and beside a sidebar that column is not the window. Same finding as the
    // data table's `hide-below-container`, one component over.
    //
    // ⚠️ SO THE SCALE IS TAILWIND'S CONTAINER LADDER, which shares its NAMES with the viewport one
    // and not its meanings: `sm` here is 24rem of HEADER, where a viewport `sm` is 40rem of
    // window. Null keeps today's behavior exactly, so no existing header moves.
    'stackBelow' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod).
    WireKit::warnUnknownProps('page-header', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `heading-focusable="false"` would mean the opposite of what the call site reads as.
    $headingFocusable = BooleanProp::from($headingFocusable, false);

    /*
     * The forced break, and it works through the SAME mechanism the natural one does: the title
     * column's flex-basis. At a full-width basis the column asks for the whole row, so the actions
     * have nowhere to go but the next line — no second layout, no `flex-direction` switch that
     * would then have to restate the gap.
     *
     * ⚠️ The utility is described rather than spelled, and that is not fussiness: Tailwind scans
     * RAW FILES, so a comment naming a class compiles it. Spelled out, this paragraph put a bare
     * full-basis rule in the shipped stylesheet that no element carries — and the drift guard
     * reported it as a compiled selector with no source, which is exactly its job.
     *
     * ⚠️ AND THE FIRST ATTEMPT AT THIS VERY WARNING SPRANG THE TRAP AGAIN, because it named the
     * class while explaining why not to. The rule has no exception for the sentence that states
     * it: describe the utility, never write its token.
     *
     * Written out per size because Tailwind reads a literal class name and never an assembled
     * one, the same reason `hide-below` spells its five out.
     *
     * The container is NAMED (`@container/wk-page-header` on the root below), for the reason the
     * table's frame carries a name too: an anonymous container would also become the measuring
     * context for every `@`-variant a caller nests in the actions slot, and silently retarget it.
     */
    $stackBelow = filled($stackBelow) ? (string) $stackBelow : null;

    if ($stackBelow !== null && ! in_array($stackBelow, ['sm', 'md', 'lg', 'xl', '2xl', '3xl'], true)) {
        WireKit::validateProp('page-header', 'stackBelow', $stackBelow, ['sm', 'md', 'lg', 'xl', '2xl', '3xl']);
        $stackBelow = null;
    }

    $stackClass = match ($stackBelow) {
        'sm' => '@max-sm/wk-page-header:basis-full',
        'md' => '@max-md/wk-page-header:basis-full',
        'lg' => '@max-lg/wk-page-header:basis-full',
        'xl' => '@max-xl/wk-page-header:basis-full',
        '2xl' => '@max-2xl/wk-page-header:basis-full',
        '3xl' => '@max-3xl/wk-page-header:basis-full',
        default => '',
    };

    /*
     * The row, and `flex-wrap` is the whole layout decision.
     *
     * The actions sit beside the title while there is room and drop under it when there is
     * not — reported as the third of three hand-built shapes, where a screen put its
     * "New role" button in its own `row justify="between"` and every other screen did
     * something else.
     *
     * `basis-[16rem]` on the title column is what makes the break happen at a width worth
     * breaking at: without a basis a flex item's `auto` basis is its content, so a short
     * title keeps a long action row beside it until the row runs off the edge, and a long
     * title wraps to three lines rather than letting the buttons drop. With it, the column
     * asks for 16rem, and anything narrower than that plus the actions puts them on their
     * own line.
     */
    $classes = WireKit::resolveClasses('page-header', 'base', implode(' ', [
        'wk-page-header',
        '@container/wk-page-header',
        'flex flex-wrap items-start justify-between',
        'gap-[var(--gap-wk-md)]',
        'w-full',
    ]), $scope);

    /*
     * The two structures BESIDE the root, named so a call site can reach them.
     *
     * ⚠️ Naming them is the whole point, and it is not cosmetic: a consuming application
     * reported that it had to write a descendant selector against this component's internal
     * markup to tune the title column — the one shape the house rules spend the most words
     * against, because it turns private markup into someone else's public API. `base` was the
     * only resolvable block, so there was no other way in.
     *
     * The defaults are byte-for-byte what these two divs carried as literals, so nothing
     * rendered moves; what changes is that a developer can now say so through
     * `WireKit::personalize()`, a scope, or `components.page-header.classes.{block}`.
     */
    $columnClasses = WireKit::resolveClasses('page-header', 'column', implode(' ', array_filter([
        // `min-w-0` is not decoration: a flex child defaults to `min-width: auto`, so a long
        // unbroken word would push the column past the row instead of wrapping inside it.
        //
        // ⚠️ `grow`, NOT the `flex` shorthand, and the reason is that the pair used to be
        // `flex-1 basis-[16rem]` — a shorthand that sets `flex-basis: 0%` beside a longhand
        // that sets 16rem. Both are single-class selectors, so which one applies is decided
        // by their ORDER in the compiled stylesheet, which this component does not control.
        // Measured: the basis rule is emitted later, so 16rem wins and the intent above holds
        // — but it holds by luck rather than by construction, and a reporter was right to ask.
        // Three longhands say the same thing with nothing left to order.
        'min-w-0 grow basis-[16rem]',
        $stackClass,
    ])), $scope);

    // Whether the title and the description come from their slots, asked as `hasActualContent()`.
    // `filled()` counts the comment markers Livewire writes around an `@if` as content, and a
    // call site that makes its `actions` slot conditional leaves two of them in the DEFAULT slot:
    // the heading would render those instead of the `title` prop. A `description` passed as a
    // slot has the same exposure; passed as a prop it is a string, and `filled()` is right for it.
    $titleFromSlot = $slot->hasActualContent();
    $hasDescription = $description instanceof \Illuminate\View\ComponentSlot
        ? $description->hasActualContent()
        : filled($description);

    // A `meta` slot: short facts about the thing the title names, a status badge or a count,
    // drawn in the title's row and outside the heading. Inside the heading they would become part
    // of its accessible name; in `description` they would cost a second line; in `actions` they
    // would sit at the far end, away from the name they describe. They wrap with the title and
    // never into the actions.
    $hasMeta = isset($meta) && $meta->hasActualContent();

    $actionsClasses = WireKit::resolveClasses('page-header', 'actions', implode(' ', [
        // Wraps inside itself too. Two buttons beside a title on a phone is the case the
        // report measured, and a row that cannot wrap puts the second one off-screen.
        'flex flex-wrap items-center gap-[var(--gap-wk-sm)]',
    ]), $scope);
@endphp

<div data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    <div class="{{ $columnClasses }}">
        {{-- With meta, the heading and the meta share a row that wraps. Without it the heading is
             the column's first child exactly as before, so no existing header changes. --}}
        @if($hasMeta)
            <div data-wk-page-header-title-row class="flex flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]">
        @endif
        <x-wirekit::heading
            :level="$level"
            :size="$size"
            {{-- Blade drops a null attribute, so an unset id emits nothing. A `@if` inside a
                 component tag is not compilable — it ends the template with "unexpected endif". --}}
            :id="$headingId"
            {{-- `null` rather than `false`, for the reason the line above already relies on:
                 Blade drops a null attribute, so "off" emits nothing at all. --}}
            :tabindex="$headingFocusable ? '-1' : null"
            {{-- In the row the heading may shrink, so a title that truncates does so in its space. --}}
            :class="$hasMeta ? 'min-w-0' : null"
            :scope="$scope"
        >{{ $titleFromSlot ? $slot : $title }}</x-wirekit::heading>
        @if($hasMeta)
                <div data-wk-page-header-meta class="flex flex-wrap items-center gap-[var(--gap-wk-xs)]">{{ $meta }}</div>
            </div>
        @endif

        @if($hasDescription)
            {{-- `mt` from the SPACE ladder, which is the family a margin reads — a margin
                 taking a `--gap-wk-*` value is what `SpacingFamilyRatchetTest` freezes, and
                 it caught this line. The tight end of it, because the sentence belongs to
                 the title above it: a distance the size of the row's own gap would read as
                 a third block rather than as the title's second line. --}}
            <x-wirekit::text intent="muted" class="mt-[var(--space-wk-xs)]" :scope="$scope">{{ $description }}</x-wirekit::text>
        @endif
    </div>

    @isset($actions)
        <div data-wk-page-header-actions class="{{ $actionsClasses }}">{{ $actions }}</div>
    @endisset
</div>
