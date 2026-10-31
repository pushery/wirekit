{{-- optimistic-ui: n/a — client-only
     It opens the overview of the keyboard shortcuts. The interaction never leaves the page. --}}
@props([
    // The look of the button, handed to it as written. Null keeps the button's own default.
    'intent' => null,
    'surface' => null,
    'size' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('shortcuts.trigger', $attributes->getAttributes());

    // The trigger stands on its own Alpine root: a caller's `x-ref` is filed above it.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables and
    // `autofocus="false"` focuses — the opposite of what the call site says, with no error
    // either way. Strip such flags when their value reads as false, before the bag is printed.
    $attributes = \Pushery\WireKit\Support\BooleanProp::stripFalseHtmlFlags($attributes);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:click']);

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('shortcuts.trigger', 'base', '', $scope);

    // The overview's dialog, by the name `shortcuts` gives it, and the id the modal derives from it.
    $name = 'wirekit-shortcuts';

    // Only what was given, so the button's own defaults hold for the rest.
    $look = array_filter(
        ['intent' => $intent, 'surface' => $surface, 'size' => $size],
        static fn ($value): bool => $value !== null,
    );
@endphp

{{-- The way to the overview on a device without a keyboard, where `?` cannot be typed.

     The button sits wherever a layout puts it, usually outside every `x-data`, so it brings its own
     scope: the named-overlay trigger says `aria-haspopup="dialog"` and keeps `aria-expanded` in step
     with the dialog. The click asks the overview to open rather than opening the dialog itself,
     because the overview reads the shortcuts that apply before its dialog covers the page. --}}
<x-wirekit::button
    x-data="wirekitModalTrigger({ name: {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }}, panel: {{ \Pushery\WireKit\Support\AlpinePayload::string('wk-modal-dialog-'.$name) }} })"
    x-bind:aria-haspopup="ariaHaspopup"
    x-bind:aria-expanded="ariaExpanded"
    x-bind:aria-controls="ariaControls"
    x-on:click="$dispatch('wirekit:shortcuts-open')"
    {{ $attributes->merge($look)->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>{{ $slot->hasActualContent() ? $slot : __('wirekit::Keyboard shortcuts') }}</x-wirekit::button>
