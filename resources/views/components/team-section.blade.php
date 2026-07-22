@props([
    // Accessible name for the roster, so a screen reader announces how many
    // people are in it rather than reading a run of names with no boundary.
    'label' => __('Team'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('team-section', 'base', implode(' ', [
        'list-none',
        'grid gap-[var(--space-wk-lg,1.5rem)]',
        'grid-cols-2 md:grid-cols-3 lg:grid-cols-4',
        'items-start',
    ]), $scope);
@endphp

{{-- The inline list-style is not redundant with list-none: the docs sandbox iframe
renders previews WITHOUT the developer's Tailwind build, so `list-none` is a dead class
name there (it DOES load dist/wirekit.css — that is why the tokens in this component
resolve). --}}
<ul
    role="list"
    aria-label="{{ $label }}"
    data-wk-team-section
    style="list-style: none; margin: 0; padding: 0;"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</ul>
