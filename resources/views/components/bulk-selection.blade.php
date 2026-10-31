{{-- optimistic-ui: n/a — client-only
     The rows' checkboxes carry the application's own binding and send each change as the reader's
     click. The block's state, every match or the page, is a choice the reader makes here and the
     application reads; there is no server value to anticipate. --}}
@props([
    // The number of matches on all pages. Without it, or when the page holds every match, the block
    // offers nothing beyond the page.
    'total' => null,
    // Anything that names the filter the rows answer: a search text, a status, a key built from
    // both. A render with another value while every match is selected clears that state and says so.
    'filter' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\PluralPhrases;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('bulk-selection', $attributes->getAttributes());

    // A count of matches: a whole number, at least zero. Anything else offers nothing, NAN and INF
    // included, which a cast to int warns about.
    $count = \Pushery\WireKit\Support\NumericProp::orNull($total, 'bulk-selection', 'total');
    $total = $count !== null && $count >= 0 && $count == floor($count) ? \Pushery\WireKit\Support\NumericProp::intOrNull($count) : null;

    // The filter is compared as text, so a list, a Collection or a number names it as well as a
    // string does. A list travels as JSON, which AlpinePayload::json() writes for a script to read.
    $filter = \Pushery\WireKit\Support\ListProp::from($filter);
    $filter = is_array($filter) ? AlpinePayload::json($filter) : \Pushery\WireKit\Support\NumericProp::text($filter ?? '');

    // The action's words as the server can know them, so the document names the button before
    // Alpine runs; the binding takes over after init. The offer of every match is the first action
    // a reader meets, and without a total the notice never shows.
    $actionText = $total !== null
        ? \Pushery\WireKit\Support\Plural::choice('wirekit::{1} Select the :count match on all pages|[2,*] Select all :count matches on all pages', $total, ['count' => \Pushery\WireKit\Support\LocalizedNumber::integer($total)])
        : __('wirekit::Select only this page');

    // The counts exist in the browser, so the plural forms travel there and `Intl.PluralRules`
    // picks one in the application's locale.
    $pagePhrases = PluralPhrases::from('wirekit::{1} The :count row on this page is selected.|[2,*] The :count rows on this page are selected.');
    $offerPhrases = PluralPhrases::from('wirekit::{1} Select the :count match on all pages|[2,*] Select all :count matches on all pages');
    $allPhrases = PluralPhrases::from('wirekit::{1} The :count match on all pages is selected.|[2,*] All :count matches on all pages are selected.');

    // The notice: one line above the rows, the number it names and the action it offers.
    $noticeClasses = WireKit::resolveClasses('bulk-selection', 'notice', implode(' ', [
        'flex flex-wrap items-center',
        'gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]',
        'mb-[var(--space-wk-sm)]',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // The action reads as a link in the line it ends, and is a button: it changes the selection,
    // it goes nowhere. `wk-touch-row` gives it its touch height and stands outside the block, so a
    // personalization that replaces the block keeps it.
    $actionClasses = 'wk-touch-row '.WireKit::resolveClasses('bulk-selection', 'action', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-accent-text)]',
        'underline underline-offset-2',
        'rounded-[var(--radius-wk-sm)]',
        'cursor-pointer',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's `x-model` on this root is read in the scope around it, where the caller's names
    // are: on the root it would find this component's own names first (CallerModel).
    $attributes = \Pushery\WireKit\Support\CallerModel::aroundRoot($attributes);
@endphp

{{-- `x-data` renders the same on every update: a morph that changed it would have Alpine set the
     block up again and forget every match. The total and the filter change with each render and
     travel on attributes of their own, which the factory reads after each one.

     `x-modelable` makes `allMatches` what a `wire:model` or an `x-model` on the block binds, in both
     directions. `x-wk-range-select` picks a range of rows with Shift held; the header checkbox
     stands outside the range. --}}
<div
    x-data="wirekitBulkSelection({ pagePhrases: {{ AlpinePayload::from($pagePhrases) }}, offerPhrases: {{ AlpinePayload::from($offerPhrases) }}, allPhrases: {{ AlpinePayload::from($allPhrases) }}, onlyText: {{ AlpinePayload::string(__('wirekit::Select only this page')) }}, filterClearedText: {{ AlpinePayload::string(__('wirekit::The filter changed, so the selection of all matches was cleared.')) }}, locale: {{ AlpinePayload::string(\Pushery\WireKit\Support\IcuLocale::tag()) }}, separators: {{ AlpinePayload::from(\Pushery\WireKit\Support\LocalizedNumber::separators()) }} })"
    x-modelable="allMatches"
    x-wk-range-select
    data-wk-bulk-total="{{ $total ?? '' }}"
    data-wk-bulk-filter="{{ $filter }}"
    {{ $attributes }}
>
    <div x-show="noticeShows" x-cloak class="{{ $noticeClasses }}">
        <span x-text="noticeText"></span>
        <button type="button" class="{{ $actionClasses }}" x-on:click="noticeAction()" x-text="noticeActionText">{{ $actionText }}</button>
    </div>

    {{-- Each change of the selection's reach, said once: the page selected with every match on
         offer, every match taken, back to the page, a filter that cleared it. Rendered before its
         first sentence, because a region that appears together with its text is not spoken. --}}
    <div role="status" aria-atomic="true" class="sr-only" data-wk-announcer x-text="announcement"></div>

    {{ $slot }}
</div>
