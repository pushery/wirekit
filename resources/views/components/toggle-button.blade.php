{{-- optimistic-ui: supported
     A pressed-state button — the same shape as toggle, and it took the same
     rollback exit: the previous state is a discrete choice the server owns, so
     putting it back costs the reader nothing.

     The structural obstacle was real and is resolved by decision rather than by
     cleverness: this component IS the button, so there is nowhere inside it to
     put the announcer — a live region there becomes part of the accessible name.
     A `display: contents` wrapper gives the announcer a sibling without changing
     the layout, and it is the same mechanism four other components already use.

     THE PRICE, and it belongs on this page rather than in a commit message:
     `display: contents` preserves the LAYOUT, not the selector structure. With
     `optimistic` set, the button is no longer a direct child of its container, so
     `.toolbar > button`, `:first-child`, `+` and `~` stop matching it. Without the
     prop the component renders byte-identically, so only opting in pays. --}}
@props([
    // The Livewire method to call when the button should show its new pressed
    // state before the server has agreed to it. A refusal puts the old state
    // back — see the note above about the wrapper this adds.
    'optimistic' => null,
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
    // The layer owns the value: this component holds no Alpine state of its own
    // in the controlled default, so there is no property to bind to. `undo` is
    // the right exit — a pressed state is a discrete choice, and restoring it
    // costs the reader nothing.
    //
    // `optimistic` and `selfToggle` are mutually exclusive by construction: the
    // layer performs the flip, so a second local one would fight it. Setting both
    // lets the layer win rather than producing two writers of one value.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => $isPressed,
        'action' => $optimistic,
        'debug' => (bool) config('app.debug'),
        // A twice-flipped toggle would otherwise resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'reverted' => __('Could not save. Change undone.'),
        ],
    ]);

    if ($optimisticConfig) {
        $attributes = $attributes->merge([
            'x-on:click' => 'toggle()',
            'x-bind:aria-pressed' => "value ? 'true' : 'false'",
            'x-bind:aria-busy' => 'isPending',
        ]);
    } elseif ($selfTogglesLocally) {
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
@if($optimisticConfig)
{{-- `display: contents` so the announcer gets a sibling without the button
     leaving its layout position — the same mechanism calendar, combobox,
     multi-select and segmented-control already use. See the note at the top for
     what it costs a caller's selectors. --}}
<div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
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
@if($optimisticConfig)
    {{-- Rendered unconditionally and starting empty: a live region that arrives
         together with its text is a new node, and nothing is announced at all. --}}
    <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
</div>
@endif
