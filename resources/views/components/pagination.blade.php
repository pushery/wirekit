{{-- optimistic-ui: n/a — query
     A page change is a query round trip, not a mutation. Nobody can show a page nobody has fetched — only the intent could be acknowledged, and that is a different state machine and out of scope. --}}
@props([
    'paginator' => null,
    'variant' => config('wirekit.components.pagination.variant', 'full'), // full | simple | mini
    'justify' => config('wirekit.components.pagination.justify', 'between'), // between | center | end | start
    // The two direction labels. `Previous`/`Next` are correct for a page-ordered list and
    // MISLEADING on a reverse-chronological one, where "next" moves BACKWARD in time — a
    // reader on a changelog or an activity feed is told the opposite of what the button
    // does. Translating the two strings globally would change every paginator in the
    // application, so each paginator takes its own.
    //
    // Null keeps the translated `Previous` and `Next`.
    'previousLabel' => null,
    'nextLabel' => null,
    // Turn pages inside a Livewire component instead of loading a new document. Every control keeps
    // its href, so it is still a link to the page it names and still opens in a new tab. Inside a
    // Livewire component a click calls the component's paging action for this paginator's page name
    // instead, and whatever the component holds outside the URL survives the turn. Outside Livewire
    // the attributes do nothing and the links navigate as they always did.
    'livewire' => false,
    // Where a Livewire page turn scrolls to: a selector, looked up among the pager's ancestors
    // first and then in the whole document, as Livewire's own pager does. False keeps the scroll
    // position. Read only with `livewire`, because a navigation starts at the top anyway.
    'scrollTo' => 'body',
    // Offer a choice of page size beside the summary: `[10, 25, 50, 100]`. Null offers none.
    //
    // With a choice on offer the pager also renders when everything fits one page, as the summary and
    // the choice without page links. Otherwise picking the largest size removes the control that would
    // undo it. An empty list still gets nothing: there the list's own empty state speaks.
    //
    // `wire:model` on the pager moves onto the select and sets that Livewire property; under
    // `livewire` the change also goes back to the first page. Without `wire:model` the choice is a row
    // of links, one per size, each sending it as `perPageName`, keeping the parameters the page links
    // carry and dropping the page number, so the list starts at its first page.
    'perPageOptions' => null,
    'perPageName' => 'per_page',
    // Draw the summary on a list that fits one page too: seven results say there are seven, the
    // same way thirty do. Without it such a list renders nothing, as it always has. An empty
    // list still renders nothing, since its own empty state speaks, and a paginator that does
    // not know its total has no summary to give.
    'summaryOnSinglePage' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('pagination', $attributes->getAttributes());

    // A paginator is an object with the page links every Laravel paginator has, a page or a cursor
    // one. Anything else given here is reported and renders nothing, as none does: a collection
    // from all() rather than paginate() would otherwise show no pages without a word, and a number
    // or a list would end the page in method_exists() below. None, false and an empty text are
    // none, without a report.
    if ($paginator !== null && $paginator !== false && $paginator !== ''
        && ! (is_object($paginator) && method_exists($paginator, 'nextPageUrl') && method_exists($paginator, 'previousPageUrl'))) {
        \Pushery\WireKit\Support\StrictnessGate::reject('pagination', 'paginator', is_string($paginator) || is_int($paginator) || is_float($paginator) ? \Pushery\WireKit\Support\NumericProp::text($paginator) : get_debug_type($paginator), 'a paginator, such as the result of paginate()', '');
        $paginator = null;
    }

    // An unknown `variant` is reported (Support\StrictnessGate) and renders as `full`; a bound
    // `null` or empty string is `full` without a word.
    $variant = \Pushery\WireKit\WireKit::validateProp('pagination', 'variant', \Pushery\WireKit\Support\NumericProp::text($variant ?: 'full'), ['full', 'simple', 'mini']);

    // An unknown `justify` is reported (Support\StrictnessGate) and renders as `between`; a bound
    // `null` or empty string is `between` without a word.
    $justify = \Pushery\WireKit\WireKit::validateProp('pagination', 'justify', \Pushery\WireKit\Support\NumericProp::text($justify ?: 'between'), ['between', 'center', 'end', 'start']);

    // The page sizes on offer, as positive integers, ascending. A string is read for its numbers, so
    // `per-page-options="10,25,50,100"` works as well as a bound array. The paginator's own size joins
    // them when it is missing, or the select would show a size the list is not using.
    $perPageChoices = [];
    $perPageOptions = \Pushery\WireKit\Support\ListProp::from($perPageOptions);

    if (filled($perPageOptions) && $paginator && method_exists($paginator, 'perPage')) {
        $sizes = is_array($perPageOptions)
            ? $perPageOptions
            : (preg_match_all('/\d+/', (string) $perPageOptions, $found) ? $found[0] : []);
        $sizes = array_filter(array_map('intval', array_values($sizes)), static fn (int $size): bool => $size > 0);
        $sizes[] = (int) $paginator->perPage();
        $sizes = array_values(array_unique($sizes));
        sort($sizes);

        foreach ($sizes as $size) {
            $perPageChoices[(string) $size] = (string) $size;
        }
    }

    $offersPerPage = $perPageChoices !== [];

    // A caller's `wire:model` binds the page size, which takes a choice of sizes to offer.
    if (blank($perPageOptions)) {
        \Pushery\WireKit\Support\StrictnessGate::warnWireModelInAModeWithoutOne('pagination', $attributes->getAttributes(), 'without `per-page-options`');
    }

    // Bail early if the paginator is missing, or there is nothing to page through and nothing to
    // choose. A list that fits one page stays when a choice of size is on offer and it has rows.
    $hasRows = $paginator && (method_exists($paginator, 'total')
        ? $paginator->total() > 0
        : (method_exists($paginator, 'isNotEmpty') && $paginator->isNotEmpty()));

    // An unbound `summary-on-single-page="false"` arrives as the string "false", which is truthy.
    $summaryOnSinglePage = BooleanProp::from($summaryOnSinglePage, false, 'pagination', 'summaryOnSinglePage');

    // The summary alone: one page, no choice of size, rows, and a paginator that knows its total.
    $summaryAlone = $summaryOnSinglePage && $hasRows && ! $offersPerPage
        && method_exists($paginator, 'hasPages') && ! $paginator->hasPages()
        && method_exists($paginator, 'total');

    if (! $paginator || ! method_exists($paginator, 'hasPages') || (! $paginator->hasPages() && ! ($offersPerPage && $hasRows) && ! $summaryAlone)) {
        return;
    }

    $singlePage = ! $paginator->hasPages();

    // A paginator that does not know its last page can only drive `mini`, so it gets `mini`
    // whatever was asked for. That is a cursor paginator, and it is also the plain paginator
    // `simplePaginate()` returns: both know whether another page exists, and neither knows how
    // many there are.
    //
    // `hasPages()` above is the wrong question to decide this on. It answers "is there more than
    // one page", which every paginator answers. The check that followed asked for
    // `currentPage()`, which tells a cursor paginator apart and lets a simple one through, and
    // `full` then asked it for `total()` and `simple` for `lastPage()`. A simple paginator has
    // neither, `full` is the default, and so the plain `simplePaginate()` case threw while the
    // documentation said it worked.
    //
    // The failure surfaced as a ViewException out of a collection, a long way from the line
    // where the decision was actually made — which is why this is decided HERE, once, by the
    // capability the richer variants need rather than by the paginator's class.
    //
    // Degrading rather than throwing: both paginators exist for lists that cannot or need not
    // count their rows, and `mini` is precisely previous/next. Everything it needs —
    // hasPages(), previousPageUrl(), nextPageUrl() — both of them have. So the developer gets
    // working pagination instead of a stack trace.
    //
    // It is said out loud all the same. A silent downgrade is its own puzzle later: the
    // developer asked for a total and page numbers and would otherwise be left wondering
    // where they went. Debug only — this is a fact about their code, not about a request.
    $knowsItsLastPage = method_exists($paginator, 'lastPage');

    if (! $knowsItsLastPage && $variant !== 'mini') {
        if (config('app.debug')) {
            logger()->debug(sprintf(
                'WireKit pagination: variant "%s" needs a paginator that knows its total and its page '
                .'numbers, and a simple or cursor paginator knows neither. Rendering "mini" (previous/next) '
                .'instead. Pass variant="mini" to make this explicit.',
                $variant
            ));
        }

        $variant = 'mini';
    }

    // Flex justification — controls how summary text and page buttons are spread.
    // 'between' (default) pushes summary left + buttons right.
    // 'center'/'start'/'end' align all controls together.
    $justifyClass = match ($justify) {
        'center' => 'justify-center',
        'end'    => 'justify-end',
        'start'  => 'justify-start',
        default  => 'justify-between',
    };

    // Base container classes — w-full ensures justify-* has room to spread.
    // `flex-wrap` lets the summary + button-row wrap onto multiple lines
    // on narrow viewports instead of overflowing the page horizontally.
    $navClasses = WireKit::resolveClasses('pagination', 'base', implode(' ', [
        'flex flex-wrap items-center w-full gap-2',
        $justifyClass,
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);

    // Shared control button styles (used for prev/next/numbered page links)
    $buttonBase = implode(' ', [
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'text-[color:var(--color-wk-text)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-active)]',
        'hover:border-[var(--color-wk-border-hover)]',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
    ]);

    // Disabled state for prev/next at list boundaries.
    //
    // No opacity, and that is the point of this list. The muted pair, cursor-not-allowed and the
    // missing hover already say the control is inert. Opacity applies to the ELEMENT, so it
    // composites the text and its own background against the page together and shrinks the
    // distance between them: in the light theme a 4.5:1 pair read at about 1.6:1. Elsewhere in
    // the library opacity IS the only dimming signal and stays; here it only said the state a
    // fourth time, and it was the one saying it that made the label unreadable.
    $buttonDisabled = implode(' ', [
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'bg-[var(--color-wk-bg-subtle)]',
        'text-[color:var(--color-wk-text-subtle)]',
        'cursor-not-allowed',
    ]);

    // Active page highlight — uses accent color for strong visual anchor
    $buttonActive = implode(' ', [
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-accent)]',
        'bg-[var(--color-wk-accent)]',
        'text-[color:var(--color-wk-accent-fg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        // Focusable from a script only (`tabindex="-1"` under Livewire), when a page turn hands
        // the focus back to the navigation, so it shows where the focus is like the links do.
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
    ]);

    // Absolute-ize paginator URLs. Under Livewire's WithPagination the current-path
    // resolver returns a RELATIVE path, so previousPageUrl()/nextPageUrl()/link urls
    // come out relative and 404 on a nested route. url()->to() host-qualifies them and
    // is idempotent on already-absolute URLs.
    $abs = static fn (?string $u): ?string => $u === null ? null : url()->to($u);

    // The nav's accessible name. The `wirekit::` prefix keeps the lookup out of the
    // application's lang groups, so it resolves to a string from the package's JSON catalog.
    $navLabel = __('wirekit::Pagination');

    // Resolved once. `?:` rather than `??` on purpose: an empty string is a caller asking
    // for nothing, and rendering an unlabeled arrow would be worse than the default.
    $previousText = $previousLabel ?: __('wirekit::Previous');
    $nextText = $nextLabel ?: __('wirekit::Next');

    // The Livewire half. Every control calls the paging action for THIS paginator's page name, so
    // two paginated lists in one component turn their own pages. Previous and next go to a page
    // computed here rather than calling previousPage() and nextPage(): a double click then lands on
    // the page the control named, not one page further.
    //
    // A cursor paginator pages by cursor, which Livewire sets with setPage(). The arguments end up
    // in an expression inside an HTML attribute, so each string is made a JavaScript literal first
    // (AlpinePayload::from, the form Alpine's CSP build reads back) and HTML-escaped second, by
    // the echo that prints it.
    $pageName = null;
    $pageAction = null;
    $firstPageAction = null;
    $previousAction = null;
    $nextAction = null;
    $scrollHandler = null;
    $landedHandler = null;

    $livewire = BooleanProp::from($livewire, false, 'pagination', 'livewire');

    if ($livewire) {
        $pagesByCursor = method_exists($paginator, 'getCursorName');
        $pageName = $pagesByCursor ? $paginator->getCursorName() : $paginator->getPageName();
        $pageNameJs = \Pushery\WireKit\Support\AlpinePayload::from($pageName);

        $pageAction = static fn (int $page): string => 'gotoPage('.$page.', '.$pageNameJs.')';

        if ($pagesByCursor) {
            $previousAction = 'setPage('.\Pushery\WireKit\Support\AlpinePayload::from((string) $paginator->previousCursor()?->encode()).', '.$pageNameJs.')';
            $nextAction = 'setPage('.\Pushery\WireKit\Support\AlpinePayload::from((string) $paginator->nextCursor()?->encode()).', '.$pageNameJs.')';
            // No cursor is the first page.
            $firstPageAction = 'setPage(null, '.$pageNameJs.')';
        } else {
            $previousAction = $pageAction($paginator->currentPage() - 1);
            $nextAction = $pageAction($paginator->currentPage() + 1);
            $firstPageAction = $pageAction(1);
        }

        // One call to a magic in each page control's click. It notes the turn, so the focus can land
        // in the navigation the server sends back (the control the reader chose may come back as a
        // span), and scrolls to the target: the nearest matching ancestor, else the first match in
        // the document. Reaching the document inside the expression is refused by Alpine's CSP
        // build, however the expression gets there, and a handler there holds one call.
        $scrollTarget = ($scrollTo !== false && $scrollTo !== null && $scrollTo !== '') ? (string) $scrollTo : '';
        $scrollHandler = '$wkPagerTurn('.$pageNameJs.', '.\Pushery\WireKit\Support\AlpinePayload::from($scrollTarget).')';
        // The elements a turn renders new, the current page and a disabled end, take the focus
        // back from the page once the navigation is in. See utils/pager-focus.js.
        $landedHandler = '$wkPagerLanded('.$pageNameJs.')';
    }

    // The choice of page size, prepared for the partial that draws it.
    $perPageCurrent = null;
    $perPageLabelId = null;
    $perPageSelectAttributes = null;
    $perPageLinks = [];

    if ($offersPerPage) {
        $perPageCurrent = (string) $paginator->perPage();
        $pagingName = method_exists($paginator, 'getCursorName') ? $paginator->getCursorName() : $paginator->getPageName();
        $perPageLabelId = \Pushery\WireKit\Support\DomId::unique('wk-per-page-'.$pagingName, 'wk-per-page-');

        // The pager's own `wire:model` belongs to the select: on the nav it would bind nothing.
        $perPageModel = $attributes->whereStartsWith('wire:model')->getAttributes();
        $attributes = $attributes->whereDoesntStartWith('wire:model');

        // Escaped here because a bag prints its values as they are, backslashing a quote rather
        // than encoding it, and `gotoPage(1, "orders")` then ends the attribute at its first
        // quote. Values from a tag arrive escaped by Blade; these four are built in this file.
        $selectAttributes = ['name' => e($perPageName), 'aria-labelledby' => e($perPageLabelId)];

        if ($perPageModel !== []) {
            $selectAttributes += $perPageModel;

            // The page the reader was on may not exist at the new size, and a page past the end
            // shows no rows and, on a single page, no pager to change the size back with.
            if ($firstPageAction !== null) {
                $selectAttributes['wire:change'] = e($firstPageAction);
            }
        } else {
            // Without Livewire the choice is a row of links, one per size, and never a form of
            // the pager's own: a browser does not nest forms, so inside a form of the developer's
            // the pager's form was dropped, its button sent the developer's form, and the controls
            // after the pager fell out of that form. A link also loads nothing until it is
            // followed, where a select that loads on `change` does it at every arrow key on
            // Windows and Linux (WCAG 3.2.2).
            //
            // Each link goes where the page links go and keeps what they keep: the query the
            // paginator was given (`withQueryString()`, `appends()`), without its own page number,
            // so the list starts at its first page, and with the size it names in place of an
            // earlier one.
            $sample = method_exists($paginator, 'getCursorName')
                ? ($paginator->nextPageUrl() ?? $paginator->previousPageUrl())
                : $paginator->url(1);
            parse_str((string) parse_url((string) $sample, PHP_URL_QUERY), $kept);
            unset($kept[$pagingName], $kept[$perPageName]);

            // The sample already holds a query the path carries of its own, so the link starts
            // from the path without it, and it ends on the fragment the page links end on.
            $perPagePath = $abs(\Illuminate\Support\Str::before($paginator->path(), '?'));
            $perPageFragment = (string) $paginator->fragment();

            foreach ($perPageChoices as $size => $label) {
                $perPageLinks[] = [
                    'label' => $label,
                    'url' => $perPagePath.'?'.\Illuminate\Support\Arr::query($kept + [$perPageName => (string) $size])
                        .($perPageFragment !== '' ? '#'.$perPageFragment : ''),
                    'current' => (string) $size === $perPageCurrent,
                ];
            }
        }

        $perPageSelectAttributes = new \Illuminate\View\ComponentAttributeBag($selectAttributes);
    }
@endphp

{{-- ONE translatable sentence, not four fragments. The earlier
     form concatenated the fragments 'Showing' / 'to' / 'of' / 'results'
     (deliberately written WITHOUT the translation-helper syntax here, so a
     naive grep of this file for translation keys does not pick up four
     phantom keys that never render)
     around the numbers, which handed a translator four context-free words
     ("to" is untranslatable without knowing it sits between two numbers)
     and locked the output into English word order — a locale that puts the
     total first simply could not be expressed.

     The numbers keep their emphasis by passing pre-built markup as the
     placeholder values, so the translator moves the placeholders freely
     and the styling travels with them. {!! !!} is required for that, and
     is safe here: every value is an integer straight off the paginator,
     written as text and escaped before being wrapped. --}}
@php
    $summary = null;

    if (method_exists($paginator, 'total')) {
        // Each number is written the way the application writes one: through its own formatter where
        // it gave one (`WireKit::formatNumbersUsing()`), else grouped the locale's way, `10.514` in
        // German. Escaped after formatting, since a formatter of the application's own is code the
        // view does not know.
        $emphasize = fn (int $value): string => '<span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">'.e(\Pushery\WireKit\Support\LocalizedNumber::integer($value)).'</span>';

        // The markup is substituted AFTER translation, never passed through
        // it. Laravel's translator also honors :Placeholder and :PLACEHOLDER
        // as case variants, applying ucfirst / strtoupper to the value — and
        // an uppercased value here would wreck the markup, since Tailwind
        // classes and CSS custom-property names are case-sensitive. A
        // translator writing ":TOTAL" for emphasis would silently lose the
        // number styling. All-caps sentinels are immune: ucfirst and
        // strtoupper both leave them unchanged, whichever case the
        // translation uses.
        //
        // The total chooses the form, since the noun follows it: a list of one
        // reads "Showing 1 to 1 of 1 result".
        $summary = \Pushery\WireKit\Support\Plural::choice('wirekit::Showing :first to :last of :total results', $paginator->total(), [
            'first' => 'WKPAGEFIRST',
            'last' => 'WKPAGELAST',
            'total' => 'WKPAGETOTAL',
        ]);

        $summary = str_replace(
            ['WKPAGEFIRST', 'WKPAGELAST', 'WKPAGETOTAL'],
            [
                $emphasize($paginator->firstItem() ?? 0),
                $emphasize($paginator->lastItem() ?? 0),
                $emphasize($paginator->total()),
            ],
            $summary,
        );
    }
@endphp

@if($summaryAlone)
    {{-- Everything fits one page, no page size is on offer, and the count was asked for: the
         summary alone. Not a navigation landmark, because there is nothing here to navigate. --}}
    <div {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$navClasses])) }}>
        <div class="text-[color:var(--color-wk-text-muted)]">
            {!! $summary !!}
        </div>
    </div>
    @php return; @endphp
@endif

<nav @unless($attributes->has('role')) role="navigation" @endunless @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ $navLabel }}" @endunless {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$navClasses])) }}>

    @if($singlePage)
        {{-- Everything fits one page, and a choice of page size is on offer: the summary and the
             choice, without page links that would all lead to this page. --}}
        @if($summary !== null)
            <div class="text-[color:var(--color-wk-text-muted)]">
                {!! $summary !!}
            </div>
        @endif

        @include('wirekit::components.partials.pagination-per-page')
    @elseif($variant === 'simple' || $variant === 'mini')
        {{-- Simple: prev + next only (optionally with a "page X of Y" label) --}}
        <div class="flex items-center gap-2">
            {{-- A boundary edge is a link that cannot be followed, and this package has
                 already decided how one of those is announced: `button` withholds the href
                 and adds `role="link"` + `aria-disabled="true"` so the control is heard as
                 the disabled link it is "rather than as anonymous text" (its own words).
                 `dropdown.item` uses the same shape on its <a> branch.

                 These four edges did the opposite. `aria-hidden="true"` removes the element
                 from the accessibility tree entirely, so a reader on page 1 is not told that
                 there is no previous page — the dimmed control they would see is simply not
                 there. Two of the four carried an `aria-label` as well, which was dead twice
                 over: the element is not in the tree, and a bare <span> is role `generic`,
                 which PROHIBITS a name. The scanner cannot report the second problem either,
                 because it skips what is hidden from assistive technology.

                 The glyphs are ornament beside a word that already says everything, so they
                 are hidden and the name is the word alone. Without that the `simple` and
                 `mini` controls computed "« Previous" while `full` computed "Previous" —
                 one component answering to two names for one control. `full` needs no such
                 wrapper: its aria-label already overrides content.

                 The ellipsis separator below keeps its `aria-hidden` on purpose. It carries
                 no state — the numbers on either side already say a range was elided — so it
                 is the one span here that really is decoration. --}}
            @if($paginator->onFirstPage())
                <span class="{{ $buttonDisabled }}" role="link" aria-disabled="true" @if($landedHandler) x-init="{{ $landedHandler }}" @endif><span aria-hidden="true">&laquo;</span> {{ $previousText }}</span>
            @else
                <a data-wk-prose-skip href="{{ $abs($paginator->previousPageUrl()) }}" rel="prev" class="{{ $buttonBase }}" @if($previousAction) wire:click.prevent="{{ $previousAction }}" @endif @if($scrollHandler) x-on:click="{{ $scrollHandler }}" @endif><span aria-hidden="true">&laquo;</span> {{ $previousText }}</a>
            @endif
        </div>

        @if($variant === 'simple')
            {{-- Centered "Page X of Y" label — hidden on mini variant for tighter footprint --}}
            <span class="text-[color:var(--color-wk-text-muted)]">
                {{ __('wirekit::Page :current of :last', [
                    'current' => $paginator->currentPage(),
                    'last' => $paginator->lastPage(),
                ]) }}
            </span>
        @endif

        <div class="flex items-center gap-2">
            @if($paginator->hasMorePages())
                <a data-wk-prose-skip href="{{ $abs($paginator->nextPageUrl()) }}" rel="next" class="{{ $buttonBase }}" @if($nextAction) wire:click.prevent="{{ $nextAction }}" @endif @if($scrollHandler) x-on:click="{{ $scrollHandler }}" @endif>{{ $nextText }} <span aria-hidden="true">&raquo;</span></a>
            @else
                <span class="{{ $buttonDisabled }}" role="link" aria-disabled="true" @if($landedHandler) x-init="{{ $landedHandler }}" @endif>{{ $nextText }} <span aria-hidden="true">&raquo;</span></span>
            @endif
        </div>

        @if($offersPerPage)
            @include('wirekit::components.partials.pagination-per-page')
        @endif
    @else
        {{-- Full: prev + numbered pages + next (standard Laravel paginator links) --}}
        @if($offersPerPage)
            <div class="flex flex-wrap items-center gap-x-[var(--gap-wk-lg)] gap-y-[var(--gap-wk-sm)]">
                <div class="text-[color:var(--color-wk-text-muted)]">
                    {!! $summary !!}
                </div>

                @include('wirekit::components.partials.pagination-per-page')
            </div>
        @else
            <div class="text-[color:var(--color-wk-text-muted)]">
                {!! $summary !!}
            </div>
        @endif

        <div class="flex flex-wrap items-center gap-1">
            {{-- Glyph-only, so the name has to come from the label — see the note on the
                 simple variant above for why the label needs a role that admits one. --}}
            @if($paginator->onFirstPage())
                <span class="{{ $buttonDisabled }}" role="link" aria-disabled="true" aria-label="{{ $previousText }}" @if($landedHandler) x-init="{{ $landedHandler }}" @endif>&laquo;</span>
            @else
                <a data-wk-prose-skip href="{{ $abs($paginator->previousPageUrl()) }}" rel="prev" class="{{ $buttonBase }}" aria-label="{{ $previousText }}" @if($previousAction) wire:click.prevent="{{ $previousAction }}" @endif @if($scrollHandler) x-on:click="{{ $scrollHandler }}" @endif>&laquo;</a>
            @endif

            {{-- Numbered links: linkCollection() returns {url, label, active} per entry.
                 We skip the framework-generated prev/next (we render our own above)
                 POSITIONALLY, with `->slice(1, -1)` on the line below — not by matching
                 their labels. Worth being exact about: this file now renders &laquo; and
                 &raquo; itself, so a reader who believed the label-matching story would
                 expect our own glyphs to be filtered out, and they are not.

                 The `label` field is unescaped via {!! !!} below — safe because
                 Laravel's paginator constructs `label` as a numeric page-index
                 string OR a pre-encoded HTML entity (`&laquo;` / `&raquo;` /
                 `…`). The value never originates from user input or query
                 parameters — it's generated entirely from `$paginator->currentPage()`
                 and the framework's internal range calculation. If a downstream
                 developer ever extends the paginator to produce user-influenced
                 labels, the unescaped render becomes an XSS vector and this
                 emission must be wrapped in `e($link['label'])` or equivalent. --}}
            @foreach($paginator->linkCollection()->slice(1, -1) as $link)
                @if($link['url'] === null)
                    {{-- null url = separator (ellipsis) --}}
                    <span class="{{ $buttonDisabled }}" aria-hidden="true" @if($pageAction) wire:key="{{ 'paginator-'.$pageName.'-gap'.$loop->index }}" @endif>{!! $link['label'] !!}</span>
                @elseif($link['active'])
                    <span class="wk-pagination-current {{ $buttonActive }}" aria-current="page" @if($pageAction) wire:key="{{ 'paginator-'.$pageName.'-page'.$link['label'] }}" tabindex="-1" @endif @if($landedHandler) x-init="{{ $landedHandler }}" @endif>{!! $link['label'] !!}</span>
                @else
                    <a data-wk-prose-skip href="{{ $abs($link['url']) }}" class="{{ $buttonBase }}" aria-label="{{ __('wirekit::Go to page :page', ['page' => $link['label']]) }}" @if($pageAction) wire:click.prevent="{{ $pageAction((int) $link['label']) }}" wire:key="{{ 'paginator-'.$pageName.'-page'.$link['label'] }}" @endif @if($scrollHandler) x-on:click="{{ $scrollHandler }}" @endif>{!! $link['label'] !!}</a>
                @endif
            @endforeach

            @if($paginator->hasMorePages())
                <a data-wk-prose-skip href="{{ $abs($paginator->nextPageUrl()) }}" rel="next" class="{{ $buttonBase }}" aria-label="{{ $nextText }}" @if($nextAction) wire:click.prevent="{{ $nextAction }}" @endif @if($scrollHandler) x-on:click="{{ $scrollHandler }}" @endif>&raquo;</a>
            @else
                <span class="{{ $buttonDisabled }}" role="link" aria-disabled="true" aria-label="{{ $nextText }}" @if($landedHandler) x-init="{{ $landedHandler }}" @endif>&raquo;</span>
            @endif
        </div>
    @endif
</nav>
