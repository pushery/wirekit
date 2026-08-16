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
    \Pushery\WireKit\WireKit::warnUnknownProps('context-menu.separator', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('context-menu.separator', 'base', implode(' ', [
        'my-[var(--padding-wk-y-xs)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<div role="separator" {{ $attributes->class([$classes]) }}></div>
