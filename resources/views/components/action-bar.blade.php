{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'visible' => false,
    'mode' => 'floating',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('action-bar', $attributes->getAttributes());

    // An unknown `mode` is reported (Support\StrictnessGate) and renders as `floating`; a bound
    // `null` or empty string is `floating` without a word.
    $mode = \Pushery\WireKit\WireKit::validateProp('action-bar', 'mode', \Pushery\WireKit\Support\NumericProp::text($mode ?: 'floating'), ['floating', 'static']);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $visible = BooleanProp::from($visible, false, 'action-bar', 'visible');

    // Action Bar — the bar of bulk actions shown when items are selected.
    // Uses role="group" + an aria-live announcement for screen readers.
    //
    // NOT role="toolbar", and the distinction is the whole reason this comment exists.
    // The toolbar role is a composite-widget promise: one tab stop for the whole bar,
    // Left/Right (or Up/Down) moving between the controls inside it, Home/End jumping
    // to the ends. This component renders no keyboard model — it is a layout wrapper
    // whose children are ordinary buttons, so Tab walks each of them and the arrows do
    // nothing. Announcing "toolbar" told a screen-reader user to press keys that were
    // never bound, and no automated check reports it: axe has no rule for a composite
    // role without its keyboard model. `role="group"` says exactly what is true — a
    // named set of related controls, each reached with Tab. The roving model stays
    // where the catalog already puts it, in the dedicated toolbar component.
    //
    // Two layout modes:
    //   - 'floating' (default) — pinned to bottom-center of the viewport via
    //     `position: fixed`. Best for list pages where the bar should hover
    //     over the content while the user scrolls.
    //   - 'static' — flows inline with the surrounding content. Useful when
    //     the bar is part of a card / panel / dashboard rather than a
    //     viewport-floating overlay. Drops the fixed positioning + the
    //     viewport-centering transforms; keeps the same visual chrome.
    $isFloating = $mode !== 'static';

    $positioningClasses = $isFloating
        // The bottom of the viewport is not the bottom of the usable screen on a phone with
        // a home indicator, whose inset is 34px on a notched iPhone. The same expression keeps
        // `.wk-fab` and `.wk-bottom-nav` clear of it. `env()` resolves to 0 wherever there is
        // no inset, so it costs nothing elsewhere.
        //
        // No browser test can catch this: `env(safe-area-inset-*)` is 0 in headless
        // Playwright, which is why it survived every green mobile run.
        ? 'fixed bottom-[calc(var(--padding-wk-y-lg)_+_env(safe-area-inset-bottom,0px))] left-1/2 -translate-x-1/2 z-[var(--z-wk-sticky)]'
        : '';

    // One display class, chosen here once: `flex` beside `inline-flex` left the choice to the
    // stylesheet's order. A bar that is not visible takes `hidden`, a class rather than an inline
    // `display: none`, which a `style-src` policy drops: the bar would then stand on the page with
    // nothing selected. A Livewire render that flips `visible` swaps the class.
    $display = ! $visible ? 'hidden' : ($isFloating ? 'flex' : 'inline-flex');

    $classes = WireKit::resolveClasses('action-bar', 'base', implode(' ', [
        $positioningClasses,
        $display,
        'items-center gap-[var(--gap-wk-md)]',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-x-lg)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // The count of what the bar acts on, "3 selected": shown first in the bar and said by its live
    // region. A slot that renders nothing counts as none, so the region keeps its own sentence.
    $hasSummary = isset($summary) && trim(strip_tags((string) $summary)) !== '';
    $summaryClasses = WireKit::resolveClasses('action-bar', 'summary', implode(' ', [
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'whitespace-nowrap',
    ]), $scope);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole bar.
    // The announcer stands beside the bar, outside the part that hides, so with any of them a
    // `contents` wrapper holds both and takes them (Support\OuterAttributes).
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);
@endphp

{{-- The live region sits OUTSIDE the bar, and that is the whole point.

     When `$visible` is false the bar is not displayed, and an undisplayed subtree is not in the
     accessibility tree at all. A region inside the bar would therefore not CHANGE
     on the flip — it would APPEAR, already populated, and a live region that appears with its
     text already in it announces nothing.

     Out here it is always in the tree, always empty until the bar is shown, and the flip is
     the text change a live region reacts to. --}}
@if($outerAttributes->isNotEmpty())<div class="contents" {{ $outerAttributes }}>@endif
{{-- With a `summary`, the region says it instead of the bar's presence: "3 selected", then "4
     selected" as the count changes, each change being a change of the region's text. Atomic, so the
     number is said with the words it belongs to. --}}
<div aria-live="polite" aria-atomic="true" class="sr-only" data-wk-announcer>
    @if($visible) {{ $hasSummary ? $summary : __('wirekit::Bulk actions available') }} @endif
</div>

<div
    @unless($attributes->has('role')) role="group" @endunless
    @unless($attributes->has('aria-label') || $attributes->has('aria-labelledby')) aria-label="{{ __('wirekit::Bulk actions') }}" @endunless
    @if($isFloating) data-wk-scroll-inset="bottom" @endif
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    {{-- Why the announcement is translated, and when it fires.
         The text is translated for the same reason the label above it is: this is read
         aloud to somebody, and a literal here is read aloud in English to everybody,
         inside an interface that is otherwise in their language.

         It announces on the SERVER-driven path — `:visible` flipping false → true in a
         Livewire re-render swaps empty text for filled text inside a region that was
         already in the accessibility tree, which is what a live region reacts to. Under
         Alpine-controlled visibility (`x-show` with `:visible="true"`) the text is
         present from first paint and never changes, so nothing is announced and the
         announcement is the caller's to make; the docs page says so at the tip that
         introduces that mode. --}}
    {{-- The count as a reader sees it. Hidden from screen readers: the region before the bar says
         it, and a second copy would be read twice on the way through the page. --}}
    @if($hasSummary)
        <span aria-hidden="true" data-wk-action-bar-summary class="{{ $summaryClasses }}">{{ $summary }}</span>
    @endif
    {{ $slot }}
</div>
@if($outerAttributes->isNotEmpty())</div>@endif
