{{-- optimistic-ui: n/a — client-only
     A panel that is shown or not shown. Whatever the content asks of the server declares
     its own optimistic behavior; this wrapper asks for nothing. --}}
@props([
    // Which position in the flow this panel is, 1-based. Required — the container finds a
    // step by this number, and guessing it from DOM order would break the moment a step is
    // rendered conditionally.
    'index' => null,
    // Whether the flow may leave this step. Unset means yes: a wizard whose steps carry no
    // condition is an ordinary next/back flow, and defaulting to blocked would make the
    // simplest use of the component the one that does not work.
    'complete' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('wizard.step', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    $position = (int) ($index ?? 0);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `complete="false"` would mean the opposite of what the call site reads as.
    $isComplete = $complete === null ? null : BooleanProp::from($complete, true);

    $classes = WireKit::resolveClasses('wizard.step', 'base', '', $scope);

    if ($position < 1 && config('app.debug')) {
        $missingIndexWarning = '[wirekit] wizard.step: no `index`. The container finds a step by '
            .'its position, so a step without one can never be shown and never gates anything. '
            .'Number the steps from 1 in the order they appear.';
    }
@endphp

{{-- `x-show` rather than `x-if`, deliberately, and the difference is the whole reason a
     multi-step FORM is different from a tab set: `x-if` destroys the panel, so every field
     in a step you stepped back from loses its value and any `wire:model` binding with it.
     Hidden-but-present keeps what was typed.

     `hidden` follows the same state so the panel is out of the accessibility tree and out
     of the tab order while it is not showing — `x-show` alone leaves it discoverable to a
     screen reader, which would read a flow of four steps as one long page. --}}
<div
    data-wk-wizard-step="{{ $position }}"
    @if($isComplete !== null) data-wk-step-complete="{{ $isComplete ? 'true' : 'false' }}" @endif
    x-show="current === {{ $position }}"
    x-bind:hidden="current === {{ $position }} ? null : true"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</div>

@if(isset($missingIndexWarning))
    <div x-data="wirekitDevWarning({ message: {{ \Pushery\WireKit\Support\AlpinePayload::from($missingIndexWarning) }} })" hidden></div>
@endif
