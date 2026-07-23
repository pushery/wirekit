@props([
    // The two-state truth. In the controlled default, bind it to your own state —
    // the pressed state of a formatting control lives in the document, not in the
    // button. It also seeds the initial state in self-toggle mode.
    'pressed' => false,
    // Uncontrolled convenience. When true the button flips its OWN aria-pressed on
    // click (Alpine), so it works standalone — a formatting toolbar, a docs demo —
    // with no Livewire wiring. Default is controlled: a bare click must NOT flip it
    // locally and drift from the document's truth.
    'selfToggle' => false,
    // Visual weight, forwarded to the underlying button.
    'size' => 'md',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $pressed = BooleanProp::from($pressed, false);
    $selfToggle = BooleanProp::from($selfToggle, false);

    $isPressed = filter_var($pressed, FILTER_VALIDATE_BOOLEAN);
    $selfTogglesLocally = filter_var($selfToggle, FILTER_VALIDATE_BOOLEAN);

    // The pressed LOOK is the neutral FILLED surface; unpressed is OUTLINE. Rather
    // than baking the surface in from PHP (which only the initial server render can
    // know), the button always renders the OUTLINE base and the pressed look is
    // applied by CSS from aria-pressed (see dist/wirekit.css). That way the visual
    // follows the state from ANY source — the app in controlled mode, an Alpine
    // binding, or the built-in self-toggle below — and the state is carried by the
    // SHAPE, never a tint alone (WCAG 1.4.1). aria-pressed stays the authoritative
    // signal; it is rendered statically for the no-JS / pre-Alpine paint and, in
    // self-toggle mode, bound reactively.
    //
    // The Alpine wiring is merged into the attribute bag rather than written as
    // @if(...) inside the component tag — Blade's component-tag parser cannot hold
    // a directive between attributes.
    if ($selfTogglesLocally) {
        $attributes = $attributes->merge([
            'x-data' => '{ pressed: '.($isPressed ? 'true' : 'false').' }',
            'x-on:click' => 'pressed = !pressed',
            'x-bind:aria-pressed' => "pressed ? 'true' : 'false'",
        ]);
    }
@endphp

{{-- Composes the button rather than re-implementing it: intents, sizes, focus
     ring, loading and the disabled model all stay in ONE place. The only thing
     added here is the WAI-ARIA toggle-button contract (aria-pressed) and the
     optional self-toggle.

     This is NOT <x-wirekit::toggle> (a form switch, role=switch, with a label)
     and NOT <x-wirekit::segmented-control> (a group of mutually exclusive
     options). It is a single control that stays pressed — the bold/italic/mute
     shape. --}}
<x-wirekit::button
    type="button"
    intent="neutral"
    surface="outline"
    :size="$size"
    :scope="$scope"
    data-wk-toggle-button
    aria-pressed="{{ $isPressed ? 'true' : 'false' }}"
    {{ $attributes }}
>
    {{ $slot }}
</x-wirekit::button>
