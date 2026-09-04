{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
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

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $visible = BooleanProp::from($visible, false);

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
        // ⚠️ The bottom of the viewport is NOT the bottom of the usable screen on a phone
        // with a home indicator — the inset is 34px on a notched iPhone, and this control
        // sat inside it. The library states the rule in dist/wirekit.css and already ships
        // this exact expression for `.wk-fab` and `.wk-bottom-nav`; these offsets were
        // simply never given the term. `env()` resolves to 0 wherever there is no inset,
        // so it costs nothing elsewhere.
        //
        // No browser test can catch this: `env(safe-area-inset-*)` is 0 in headless
        // Playwright, which is why it survived every green mobile run.
        ? 'fixed bottom-[calc(var(--padding-wk-y-lg)_+_env(safe-area-inset-bottom,0px))] left-1/2 -translate-x-1/2 z-[var(--z-wk-sticky)]'
        : 'inline-flex';

    $classes = WireKit::resolveClasses('action-bar', 'base', implode(' ', [
        $positioningClasses,
        'flex items-center gap-[var(--gap-wk-md)]',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-sm)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<div
    role="group"
    aria-label="{{ __('wirekit::Bulk actions') }}"
    {{ $attributes->merge(!$visible ? ['style' => 'display: none;'] : [])->class([$classes]) }}
>
    {{-- Live region announcing that the bar has appeared.
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
    <div aria-live="polite" class="sr-only">
        @if($visible) {{ __('wirekit::Bulk actions available') }} @endif
    </div>

    {{ $slot }}
</div>
