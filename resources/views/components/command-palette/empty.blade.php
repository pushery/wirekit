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

{{-- `role="status"` (an implicit polite live region), because the appearance of
     this block IS the answer to the reader's search. Without it the palette said
     nothing when a query matched nothing: the input keeps `aria-expanded="true"`
     and reports no active descendant, which is also what an untouched list looks
     like — so "no results" and "you have not typed yet" were the same
     announcement, namely none.

     Belongs in the palette's `empty` slot, NOT the default one: the default slot
     is the inside of `role="listbox"`, whose children are options, and a roleless
     line of prose sitting among them is what axe reports as
     `aria-required-children`. See the slot in command-palette.blade.php.

     Merged rather than written flat so a caller who has a better role for their
     own empty state can still say so. --}}
<div {{ $attributes->merge(['role' => 'status'])->class([$classes]) }}>
    {{ $slot }}
</div>
