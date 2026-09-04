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
    \Pushery\WireKit\WireKit::warnUnknownProps('field.legend', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Standalone <legend> for a hand-built <fieldset> — the caption element on its
    // own, with the library's typography and the configuration seam already wired.
    //
    // ⚠️ NOT for the DEFAULT slot of <x-wirekit::field.set>. That slot sits inside a
    // spacing <div>, so a <legend> written there is the fieldset's GRANDCHILD, which
    // makes it an ordinary inline box rather than the group's caption: the <fieldset>
    // ends up with no accessible name and nothing is announced before the controls.
    // The text still renders where the author put it, so the loss is invisible.
    // This docblock claimed the opposite until 2026-09-04 and the claim was the bug.
    //
    // Rich content inside a <x-wirekit::field.set> goes through its named caption slot,
    // which renders ahead of that <div>:
    //
    //     <x-wirekit::field.set>
    //         <x-slot:legend>Permissions <x-wirekit::badge>New</x-wirekit::badge></x-slot:legend>
    //         …controls…
    //     </x-wirekit::field.set>
    //
    // Use the `legend` prop, the `legend` slot, or this component in your own
    // <fieldset> — one of the three, never two at once.
    $classes = WireKit::resolveClasses('field.legend', 'base', 'mb-3 text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]', $scope);
@endphp

<legend {{ $attributes->class([$classes]) }}>{{ $slot }}</legend>
