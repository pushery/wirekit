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
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog.title', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Alert dialog title — linked to the dialog via aria-labelledby.
    // The id comes from the parent alert-dialog component's data-wk-title-id attribute.
    $classes = WireKit::resolveClasses('alert-dialog.title', 'base', implode(' ', [
        'text-[length:var(--text-wk-lg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-text)]',
        'leading-[var(--leading-wk-tight)]',
    ]), $scope);
@endphp

<h2
    x-bind:id="$wkAncestorData('[data-wk-title-id]', 'wkTitleId')"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</h2>
