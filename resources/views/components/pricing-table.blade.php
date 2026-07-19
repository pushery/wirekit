@props([
    // Accessible name for the group of plans.
    'label' => 'Pricing plans',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('pricing-table', 'base', implode(' ', [
        // list-none: this is a semantic <ul> for assistive tech, not a bulleted
        // list — the UA disc markers would be pure noise beside a plan card.
        'list-none',
        'grid gap-[var(--gap-wk-md)]',
        // One plan per row on a phone; the tiers decide their own count above.
        'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
        'items-stretch',
    ]), $scope);
@endphp

{{-- A list, not a pile of divs: the tiers are a set the reader compares, and a
     screen reader should hear "3 items" before wading in. --}}
{{-- The inline list-style is not redundant with the list-none class: the docs
     sandbox iframe renders previews WITHOUT the developer's Tailwind build, so
     `list-none` is a dead class name there and the plans grow UA bullets.

     It DOES load dist/wirekit.css — that is why every token in this component
     resolves in a preview. (An earlier version of this comment claimed the
     opposite; dist/wirekit.css carries a rule written specifically to fix a
     sandbox-iframe symptom, which could not work if the sheet never loaded.) --}}
<ul
    role="list"
    aria-label="{{ $label }}"
    data-wk-pricing-table
    style="list-style: none; margin: 0; padding: 0;"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</ul>
