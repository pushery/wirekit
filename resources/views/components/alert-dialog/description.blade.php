{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Alert dialog description — linked to the dialog via aria-describedby.
    // Provides context about what will happen if the user confirms.
    $classes = WireKit::resolveClasses('alert-dialog.description', 'base', implode(' ', [
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
        'mt-2',
    ]), $scope);
@endphp

<p
    x-bind:id="$wkAncestorData('[data-wk-desc-id]', 'wkDescId')"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</p>
