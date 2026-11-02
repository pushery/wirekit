{{-- optimistic-ui: n/a — presentational
     A heading over the entries it groups. The entries are the controls, and each answers for
     itself. --}}
@props([
    // The heading over the group's entries, which also names the group: "Account" over its pages.
    'label' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('dropdown.group', $attributes->getAttributes());

    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\Support\SlotContent;
    use Pushery\WireKit\WireKit;

    // Seeded from the heading, so a Livewire morph finds the same id on the next render. The
    // deduper gives a repeat of the same heading its own, so each group names its own heading.
    $labelText = SlotContent::textOf($label);
    $headingId = $labelText !== ''
        ? DomId::unique(WireKit::stableId('wk-dropdown-group', $labelText), 'wk-dropdown-group-').'-heading'
        : null;

    // The role is coupled to the name, as in sidebar.group: a group nothing names announces a
    // boundary it cannot explain. A caller's own name on the tag counts as one.
    $named = $headingId !== null || $attributes->has('aria-label') || $attributes->has('aria-labelledby');

    $classes = WireKit::resolveClasses('dropdown.group', 'base', 'flex flex-col', $scope);

    // The look of a section heading, small and muted, spaced as the label of sidebar.group is.
    $headingClasses = WireKit::resolveClasses('dropdown.group', 'heading', implode(' ', [
        'px-[var(--padding-wk-x-sm)] pt-[var(--padding-wk-y-sm)] pb-[2px]',
        'text-[length:var(--text-wk-xs)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-text-subtle)]',
    ]), $scope);
@endphp

{{-- The group of the APG menu pattern: `role="group"`, named by its heading. The heading is no
     menu entry (`role="presentation"`), so the arrow keys and the type-ahead, which walk the
     menu roles only, never stop on it. --}}
<div
    @if($named) role="group" @endif
    @if($headingId !== null && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby')) aria-labelledby="{{ $headingId }}" @endif
    {{ $attributes->except('role')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    @if($headingId !== null)
        <div id="{{ $headingId }}" role="presentation" class="{{ $headingClasses }}">{{ $label }}</div>
    @endif
    {{ $slot }}
</div>
