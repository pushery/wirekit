@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Alert dialog actions — footer area with cancel/confirm buttons.
    // Cancel button should appear first for safety (initial focus lands there).
    $classes = WireKit::resolveClasses('alert-dialog.actions', 'base', implode(' ', [
        'flex items-center justify-end gap-3',
        'mt-[var(--padding-wk-y-lg)]',
    ]), $scope);
@endphp

<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
