{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('card.footer', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Footer section — action area with top border and muted background
    $classes = WireKit::resolveClasses('card.footer', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'border-t-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
        'bg-[var(--color-wk-bg-subtle)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
