{{-- optimistic-ui: n/a — presentational
     Renders no interactive element of its own. What a call site puts in the `actions`
     slot may be optimistic; that is the button's contract, not this one's. --}}
@props([
    // The page title. The default slot says the same thing and wins when both are given,
    // because a slot can carry markup a prop cannot — a badge beside the name, a link.
    'title' => null,
    // The heading level. One by default, which is the whole reason this component exists:
    // sibling screens that each build their own title end up with different levels and
    // sizes. A screen has one title, and that title is the document's heading.
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
    // A prop rather than "the overlay sets it at runtime", and the difference is the
    // whole reason this exists. `utils/overlay.js` does set `tabindex="-1"` on a target that
    // has none — but an attribute written at runtime is absent from the template a Livewire
    // morph patches against, so the next update can take it away again.
    //
    // Off by default: a `tabindex` nobody asked for is a promise about focus order, and a
    // screen that never returns focus here should not carry one.
    'headingFocusable' => false,
    // Put the actions on their own line below this width, whatever they are wide.
    //
    // Without it the break depends on the LABEL: the title column asks for 16rem and the row
    // wraps when 16rem plus the gap plus the actions no longer fit, which on a phone comes down to
    // a few pixels either way. A shorter label, a narrower face or a `sm` button stays beside the
    // title, and then a two-line description lives in 16rem.
    //
    // The width is the header's own, not the window's. A page header lives inside a content
    // column, and beside a sidebar that column is not the window: viewport breakpoints
    // (`max-sm:`, as `button.group` uses) would change nothing in a 375px-wide container inside
    // a wide window. The data table's `hide-below-container` answers the same way.
    //
    // So the scale is Tailwind's container ladder, which shares its NAMES with the viewport one
    // and not its meanings: `sm` here is 24rem of HEADER, where a viewport `sm` is 40rem of
    // window. Null keeps today's behavior exactly, so no existing header moves.
    'stackBelow' => null,
    // Stick to the top of the box the page scrolls in, `--wk-strip-inset` below a strip of chrome.
    // Resting there, the header keeps as much space above its content as below it and fades in a
    // bottom border, so what scrolls under it is set apart; in the flow of the page it draws no
    // border. The resting state is `data-wk-stuck`, which `x-wk-stuck` writes.
    'sticky' => false,
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
    $sticky = BooleanProp::from($sticky, false);

    // A sticky header is an Alpine root of its own, for the directive that marks its resting state,
    // and a caller's `x-model` on it would take the value of a field in its actions slot.
    if ($sticky) {
        \Pushery\WireKit\Support\UnboundModel::drop('page-header', $attributes);
    }

    /*
     * The forced break, and it works through the SAME mechanism the natural one does: the title
     * column's flex-basis. At a full-width basis the column asks for the whole row, so the actions
     * have nowhere to go but the next line — no second layout, no `flex-direction` switch that
     * would then have to restate the gap.
     *
     * The utility is described rather than spelled: Tailwind scans raw files, so a comment
     * naming a class compiles it, and the shipped stylesheet would carry a bare full-basis rule
     * that no element uses. That includes the sentence explaining why: describe the utility,
     * never write its token.
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
     * The sticky header, in its own block so a call site can retune the resting state without
     * rewriting the row above.
     *
     * The same padding above and below at all times, so the header is as tall resting as in the
     * flow and nothing under it moves when it starts to stick; resting at its line, the space
     * above its content is the padding, as below it. The page's background, so what scrolls under
     * it does not show through. The border is there all along and transparent until
     * `data-wk-stuck`, so fading it in moves nothing either; at least a pixel wide, so a preset
     * that sets `--border-wk-width` to 0 still separates the header from what scrolls under it.
     *
     * Two knobs, read with a fallback and declared nowhere: `--wk-page-header-top` moves the line
     * the header sticks to, for a box that scrolls with a padding of its own, and
     * `--wk-page-header-rest-gap` the padding above and below.
     */
    $stickyClasses = $sticky ? WireKit::resolveClasses('page-header', 'sticky', implode(' ', [
        'wk-page-header-sticky',
        'sticky top-[var(--wk-page-header-top,var(--wk-strip-inset,0px))]',
        'z-[var(--z-wk-sticky)]',
        'bg-[var(--color-wk-bg)]',
        'py-[var(--wk-page-header-rest-gap,var(--padding-wk-y-md))]',
        'border-b-[length:max(1px,var(--border-wk-width))] border-b-transparent',
        'data-[wk-stuck]:border-b-[var(--color-wk-border)]',
        'transition-[border-color] duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]',
    ]), $scope) : '';

    /*
     * The two structures BESIDE the root, named so a call site can reach them.
     *
     * Naming them is the point: without a resolvable block for the title column, the only way
     * to tune it is a descendant selector against this component's internal markup, which
     * turns private markup into someone else's public API.
     *
     * The defaults are byte-for-byte what these two divs carried as literals, so nothing
     * rendered moves; what changes is that a developer can now say so through
     * `WireKit::personalize()`, a scope, or `components.page-header.classes.{block}`.
     */
    // Whether the title and the description come from their slots, asked as `hasActualContent()`.
    // `filled()` counts the comment markers Livewire writes around an `@if` as content, and a
    // call site that makes its `actions` slot conditional leaves two of them in the DEFAULT slot:
    // the heading would render those instead of the `title` prop. A `description` passed as a
    // slot has the same exposure; passed as a prop it is a string, and `filled()` is right for it.
    $titleFromSlot = $slot->hasActualContent();
    $hasDescription = $description instanceof \Illuminate\View\ComponentSlot
        ? $description->hasActualContent()
        : filled($description);

    // Actions beside a title with no description sit centered on the title and add nothing to
    // the header's height (`.wk-page-header-actions-frame` in the stylesheet says how). With a
    // description the column is the taller part anyway, and the actions keep to its top.
    $actionsBesideTitle = isset($actions) && ! $hasDescription;

    $columnClasses = WireKit::resolveClasses('page-header', 'column', implode(' ', array_filter([
        // `min-w-0` is not decoration: a flex child defaults to `min-width: auto`, so a long
        // unbroken word would push the column past the row instead of wrapping inside it.
        //
        // `grow`, not the `flex` shorthand: `flex-1` sets `flex-basis: 0%`, and beside a
        // `basis-[16rem]` longhand both are single-class selectors, so which one applies would
        // be decided by their order in the compiled stylesheet, which this component does not
        // control. Three longhands say the same thing with nothing left to order.
        //
        // Beside the actions frame, which grows too, the column grows 999 times faster: it keeps
        // all but a hair of the free space, as it did when it was the only thing growing, and the
        // frame stays as wide as its buttons until it wraps to a line of its own.
        $actionsBesideTitle ? 'min-w-0 grow-[999] basis-[16rem]' : 'min-w-0 grow basis-[16rem]',
        $stackClass,
    ])), $scope);

    // A `meta` slot: short facts about the thing the title names, a status badge or a count,
    // drawn in the title's row and outside the heading. Inside the heading they would become part
    // of its accessible name; in `description` they would cost a second line; in `actions` they
    // would sit at the far end, away from the name they describe. They wrap with the title and
    // never into the actions.
    $hasMeta = isset($meta) && $meta->hasActualContent();

    $actionsClasses = WireKit::resolveClasses('page-header', 'actions', implode(' ', [
        // Wraps inside itself too: with two buttons beside a title on a phone, a row that
        // cannot wrap puts the second one off-screen.
        'flex flex-wrap items-center gap-[var(--gap-wk-sm)]',
    ]), $scope);
@endphp

<div data-wk-prose-skip @if($sticky) x-data x-wk-stuck data-wk-scroll-inset="top" @endif {{ $attributes->class([$classes, $stickyClasses]) }}>
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
                 a third block rather than as the title's second line. The sentence keeps to the
                 wide reading measure: in a page's full width it ran to two lines of about 190
                 characters, which the eye cannot follow back to the start of the next. --}}
            <x-wirekit::text intent="muted" measure="wide" class="mt-[var(--space-wk-xs)]" :scope="$scope">{{ $description }}</x-wirekit::text>
        @endif
    </div>

    @isset($actions)
        @if($actionsBesideTitle)
            {{-- The frame grows to fill its line, which is how the stylesheet tells beside the
                 title from wrapped below it. The row keeps its name and its block, so a call site
                 that reaches for `[data-wk-page-header-actions]` finds the same element. --}}
            <div data-wk-page-header-actions-frame class="wk-page-header-actions-frame">
                <div data-wk-page-header-actions class="{{ $actionsClasses }}">{{ $actions }}</div>
            </div>
        @else
            <div data-wk-page-header-actions class="{{ $actionsClasses }}">{{ $actions }}</div>
        @endif
    @endisset
</div>
