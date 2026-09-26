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
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog.actions', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Alert dialog actions — footer area with cancel/confirm buttons.
    // Cancel button should appear first for safety (initial focus lands there).
    $classes = WireKit::resolveClasses('alert-dialog.actions', 'base', implode(' ', [
        // Wrapping, because what does not fit a row packed to its end leaves on its START side,
        // and nothing scrolls to that side: three actions on a phone cut the first one off the
        // panel, out of reach. Wrapped, they stack at the end; one line on a desktop, as before.
        'flex flex-wrap items-center justify-end gap-3',
        'mt-[var(--padding-wk-y-lg)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
