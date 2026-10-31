{{-- optimistic-ui: n/a — client-only
     It keeps the reader's place in a list the server re-renders, and its hints scroll the page
     without reaching the server. --}}
{{-- A list that keeps the reader's place while entries arrive, and says where new ones landed out
     of sight. Rows carry `data-wk-live-row`; a row the application marks `data-wk-live-fresh` is
     new or moved. `live` says that this render answers a live event, a broadcast the page
     re-rendered for: then the first row in view stays where it was, and fresh rows out of sight
     raise a hint above or below. A filter or the next page renders without `live`, and the list
     leaves the scroll alone. See resources/js/components/live-list.js. --}}
@props([
    // This render answers a live event: keep the reader's place and report fresh rows out of sight.
    'live' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props.
    WireKit::warnUnknownProps('live-list', $attributes->getAttributes());

    $live = BooleanProp::from($live, false, 'live-list', 'live');

    $aboveLabel = __('wirekit::New entries above');
    $belowLabel = __('wirekit::New entries below');

    // A hint is a strip of no height, sticky at the edge of what the reader sees: shown, it lays
    // over the rows instead of pushing them, which would move the very row the list keeps in place.
    $hintClasses = WireKit::resolveClasses('live-list', 'hint', 'sticky z-[var(--z-wk-sticky)] flex h-0 justify-center overflow-visible pointer-events-none', $scope);

    // The caller's attributes land on the list's own Alpine root, where a caller's `x-ref` would be
    // filed inside the component; CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp

<div
    data-wk-live-list
    data-wk-live="{{ $live ? 'true' : 'false' }}"
    x-data="wirekitLiveList({{ \Pushery\WireKit\Support\AlpinePayload::from(['aboveSaid' => $aboveLabel, 'belowSaid' => $belowLabel]) }})"
    x-bind:style="insetStyle"
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['relative'])) }}
>
    {{-- Below whatever pins itself to the top of the list's scroller: the scroll padding the script
         reads there, which for the page holds a strip of announcement-banner and every pinned bar
         of the kit, or an application's own fixed bar through `--wk-live-list-top`, which takes
         its place, as page-header's `--wk-page-header-top` does. The stylesheet computes that inset
         on the list as `--wk-live-list-inset`, and the script reads the same value: a fresh row
         under the strip or the bar is out of sight. --}}
    <div data-wk-live-hint="above" x-show="hasAbove" x-cloak class="{{ $hintClasses }} top-[calc(var(--wk-live-list-inset,0px)_+_var(--space-wk-sm))] items-start">
        <x-wirekit::button type="button" size="sm" intent="primary" class="pointer-events-auto" x-on:click="revealAbove()">{{ $aboveLabel }}</x-wirekit::button>
    </div>
    <div x-ref="rows">{{ $slot }}</div>
    {{-- Where the list scrolls with the page, the hint rests at the bottom of the screen, so it
         keeps clear of a phone's home indicator. --}}
    <div data-wk-live-hint="below" x-show="hasBelow" x-cloak class="{{ $hintClasses }} bottom-[calc(var(--space-wk-sm)_+_env(safe-area-inset-bottom,0px))] items-end">
        <x-wirekit::button type="button" size="sm" intent="primary" class="pointer-events-auto" x-on:click="revealBelow()">{{ $belowLabel }}</x-wirekit::button>
    </div>
    <div data-wk-prose-skip role="status" aria-live="polite" aria-atomic="true" class="sr-only" x-text="announcement"></div>
</div>
