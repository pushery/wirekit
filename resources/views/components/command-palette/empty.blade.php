{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.empty', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Empty state shown when no command items match the search query.
    $classes = WireKit::resolveClasses('command-palette.empty', 'base', implode(' ', [
        'py-[var(--padding-wk-y-xl)]',
        'text-center',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

{{-- The appearance of this block IS the answer to the reader's search: the input
     keeps `aria-expanded="true"` and reports no active descendant, which is also what
     an untouched list looks like. The palette says it from its one status region,
     which holds the `empty` slot and is in the tree before the block shows, so the
     block carries no role of its own: a region shown together with its text is
     announced unreliably, and a second one inside the palette's would be heard twice.

     Belongs in the palette's `empty` slot, NOT the default one: the default slot
     is the inside of `role="listbox"`, whose children are options, and a roleless
     line of prose sitting among them is what axe reports as
     `aria-required-children`. See the slot in command-palette.blade.php.

     A caller's own `role` still lands on the block, and marks it, so the palette leaves the
     announcement to that role: `role="none"` for an application that says the empty state
     itself, `role="alert"` for one that wants it said at once. --}}
<div @if($attributes->has('role')) data-wk-command-empty-own-role @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</div>
