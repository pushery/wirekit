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
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod).
    WireKit::warnUnknownProps('page-header', $attributes->getAttributes());

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
        'flex flex-wrap items-start justify-between',
        'gap-[var(--gap-wk-md)]',
        'w-full',
    ]), $scope);
@endphp

<div data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    {{-- `min-w-0` is not decoration: a flex child defaults to `min-width: auto`, so a long
         unbroken word would push the column past the row instead of wrapping inside it. --}}
    <div class="min-w-0 flex-1 basis-[16rem]">
        <x-wirekit::heading
            :level="$level"
            :size="$size"
            {{-- Blade drops a null attribute, so an unset id emits nothing. A `@if` inside a
                 component tag is not compilable — it ends the template with "unexpected endif". --}}
            :id="$headingId"
            :scope="$scope"
        >{{ filled($slot) ? $slot : $title }}</x-wirekit::heading>

        @if(filled($description))
            {{-- `mt` from the SPACE ladder, which is the family a margin reads — a margin
                 taking a `--gap-wk-*` value is what `SpacingFamilyRatchetTest` freezes, and
                 it caught this line. The tight end of it, because the sentence belongs to
                 the title above it: a distance the size of the row's own gap would read as
                 a third block rather than as the title's second line. --}}
            <x-wirekit::text intent="muted" class="mt-[var(--space-wk-xs)]" :scope="$scope">{{ $description }}</x-wirekit::text>
        @endif
    </div>

    @isset($actions)
        {{-- Wraps inside itself too. Two buttons beside a title on a phone is the case the
             report measured, and a row that cannot wrap puts the second one off-screen. --}}
        <div data-wk-page-header-actions class="flex flex-wrap items-center gap-[var(--gap-wk-sm)]">{{ $actions }}</div>
    @endisset
</div>
