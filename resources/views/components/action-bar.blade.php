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

    // Action Bar — toolbar for bulk actions (shown when items are selected).
    // Uses role="toolbar" + aria-live announcement for screen readers.
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
        ? 'fixed bottom-[var(--padding-wk-y-lg)] left-1/2 -translate-x-1/2 z-[var(--z-wk-sticky)]'
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
    role="toolbar"
    aria-label="{{ __('Bulk actions') }}"
    {{ $attributes->merge(!$visible ? ['style' => 'display: none;'] : [])->class([$classes]) }}
>
    {{-- Live region announces bar appearance --}}
    <div aria-live="polite" class="sr-only">
        @if($visible) Bulk actions available @endif
    </div>

    {{ $slot }}
</div>
