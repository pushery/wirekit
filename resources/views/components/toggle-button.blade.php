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
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
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
    // One glyph, the same in both states. The pressed surface carries the state, as it always has.
    'icon' => null,
    // A glyph per state, swapped as the state changes. Both are needed: one alone would swap with
    // nothing. The same alias works in every icon preset, where an outline and a filled variant of
    // one glyph would not, so a second alias is how a state gets its own shape.
    'onIcon' => null,
    'offIcon' => null,
    // A hint shown on hover and focus. On a toggle with no visible label it is ALSO the accessible
    // name, because a glyph is not a name and a hover never happens on a touch screen.
    'tooltip' => null,
    // The color of the icon while pressed, from the canonical intents. Color is never the state on
    // its own here: the surface changes either way.
    'activeIntent' => 'accent',
    // A label per state, such as "Sound on" and "Sound off". A button whose label says its state
    // is not a toggle in the ARIA sense, so it renders WITHOUT aria-pressed: announcing "Sound on,
    // pressed" would say the state twice, and the APG button pattern rules the pairing out.
    'onLabel' => null,
    'offLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('toggle-button', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $pressed = BooleanProp::from($pressed, false);
    $selfToggle = BooleanProp::from($selfToggle, false);

    $isPressed = filter_var($pressed, FILTER_VALIDATE_BOOLEAN);
    $selfTogglesLocally = filter_var($selfToggle, FILTER_VALIDATE_BOOLEAN);

    $activeIntent = WireKit::validateProp('toggle-button', 'activeIntent', (string) $activeIntent, ['accent', 'success', 'warning', 'danger', 'info']);

    // A developer mistake that would otherwise render something half-working says so: loudly
    // where it can be fixed, as a log line in a request, the split every validator here makes.
    $refuse = function (string $message): void {
        if (\Pushery\WireKit\Support\StrictnessGate::shouldThrowOnInvalid()) {
            throw new \InvalidArgumentException('wirekit::toggle-button: '.$message);
        }

        if (function_exists('logger')) {
            logger()->warning('[WireKit] toggle-button: '.$message);
        }
    };

    // State labels come as a pair; one alone would leave the other state with no label at all.
    $labelsChange = filled($onLabel) && filled($offLabel);
    if (filled($onLabel) !== filled($offLabel)) {
        $refuse('`on-label` and `off-label` go together, and only one of them is set.');
    }

    // State icons come as a pair for the same reason; one alone falls back to a constant icon.
    $swapsIcons = filled($onIcon) && filled($offIcon);
    if (filled($onIcon) !== filled($offIcon)) {
        $refuse('`on-icon` and `off-icon` go together, and only one of them is set.');
        $icon = $icon ?? ($onIcon ?? $offIcon);
    }

    $hasIcon = $swapsIcons || filled($icon);
    $hasVisibleLabel = $labelsChange || $slot->hasActualContent();
    $callerNamed = filled($attributes->get('aria-label')) || filled($attributes->get('aria-labelledby'));

    // A label that changes with the state IS the name, so a constant aria-label beside it would
    // name the control with words that are not on it (WCAG 2.5.3 Label in Name).
    if ($labelsChange && $callerNamed) {
        $refuse('`aria-label` would replace the visible `on-label`/`off-label` as the name. The visible label is the name, so the attribute is dropped.');
        $attributes = $attributes->except(['aria-label', 'aria-labelledby']);
        $callerNamed = false;
    }

    // With no visible label the tooltip names the control, and then must not also DESCRIBE it:
    // a control described by its own name is read twice.
    $tooltipIsName = filled($tooltip) && ! $hasVisibleLabel && ! $callerNamed;
    if ($tooltipIsName) {
        $attributes = $attributes->merge(['aria-label' => $tooltip]);
    }

    if ($hasIcon && ! $hasVisibleLabel && ! $callerNamed && blank($tooltip)) {
        $refuse('an icon-only toggle has no accessible name. Give it a `tooltip`, which also names it, or an `aria-label`.');
    }

    // The attribute the state lives in. A toggle with state labels has no aria-pressed, so its
    // look and its label follow `data-wk-state` instead; everything else keeps aria-pressed.
    $stateAttribute = $labelsChange ? 'data-wk-state' : 'aria-pressed';
    $stateBinding = fn (string $expression): string => $labelsChange
        ? "{$expression} ? 'on' : 'off'"
        : "{$expression} ? 'true' : 'false'";

    // `wire:model` binds a boolean property to the pressed state in both directions, which needs a
    // local state for Livewire's `x-model` to reach through `x-modelable`. A hand-written `x-model`
    // -- how an Alpine parent embeds this button -- asks for exactly the same thing, so it counts:
    // without it the seam was never emitted at all, and the binding sat on the element pointing at
    // a property no `x-data` here declares.
    $hasModel = $attributes->whereStartsWith(['wire:model', 'x-model'])
        ->whereDoesntStartWith('x-modelable')->getAttributes() !== [];

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
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A twice-flipped toggle would otherwise resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);

    if ($optimisticConfig) {
        // Two writers of one value would race. The layer calls the server itself, so a model
        // binding next to it is the one that goes, and the developer hears why.
        if ($hasModel) {
            $refuse('`optimistic` and `wire:model`/`x-model` both write the pressed state. `optimistic` calls the server itself, so the model binding is dropped.');
            $attributes = $attributes->whereDoesntStartWith(['wire:model', 'x-model']);
        }

        $attributes = $attributes->merge([
            'x-on:click' => 'toggle()',
            'x-bind:'.$stateAttribute => $stateBinding('value'),
            'x-bind:aria-busy' => 'isPending',
        ]);
    } elseif ($hasModel || $selfTogglesLocally) {
        // A model binding flips locally like self-toggle does, and `x-modelable` hands the local
        // state to the `x-model` that `wire:model` compiles to, on this same element.
        $attributes = $attributes->merge(array_filter([
            'x-data' => '{ pressed: '.($isPressed ? 'true' : 'false').' }',
            'x-modelable' => $hasModel ? 'pressed' : null,
            'x-on:click' => 'pressed = !pressed',
            'x-bind:'.$stateAttribute => $stateBinding('pressed'),
        ]));
    }

    // The icon follows the button's size one step down, the proportion a button keeps elsewhere.
    $iconSize = $size === 'lg' ? 'md' : 'sm';
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
@if(filled($tooltip))
{{-- The button is the focusable trigger, so the tooltip adds no tab stop of its own. When the
     tooltip text is the name, it does not also describe the button. --}}
<x-wirekit::tooltip :text="$tooltip" :focusable-trigger="false" :describes="! $tooltipIsName">
    @include('wirekit::components.partials.toggle-button-control', [
    'attributes' => $attributes,
    'slot' => $slot,
    'size' => $size,
    'scope' => $scope,
    'hasIcon' => $hasIcon,
    'activeIntent' => $activeIntent,
    'labelsChange' => $labelsChange,
    'isPressed' => $isPressed,
    'swapsIcons' => $swapsIcons,
    'onIcon' => $onIcon,
    'offIcon' => $offIcon,
    'icon' => $icon,
    'iconSize' => $iconSize,
    'onLabel' => $onLabel,
    'offLabel' => $offLabel,
])
</x-wirekit::tooltip>
@else
    @include('wirekit::components.partials.toggle-button-control', [
    'attributes' => $attributes,
    'slot' => $slot,
    'size' => $size,
    'scope' => $scope,
    'hasIcon' => $hasIcon,
    'activeIntent' => $activeIntent,
    'labelsChange' => $labelsChange,
    'isPressed' => $isPressed,
    'swapsIcons' => $swapsIcons,
    'onIcon' => $onIcon,
    'offIcon' => $offIcon,
    'icon' => $icon,
    'iconSize' => $iconSize,
    'onLabel' => $onLabel,
    'offLabel' => $offLabel,
])
@endif
@if($optimisticConfig)
    {{-- Rendered unconditionally and starting empty: a live region that arrives
         together with its text is a new node, and nothing is announced at all. --}}
    <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
</div>
@endif
