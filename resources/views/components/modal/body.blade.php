{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Body classes — main content area with padding and text color
    $classes = WireKit::resolveClasses('modal.body', 'base', implode(' ', [
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- Modal body — scrollable content area --}}
<div data-wk-modal-body {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
