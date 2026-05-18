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
    x-bind:id="$el.closest('[data-wk-desc-id]')?.dataset.wkDescId"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</p>
