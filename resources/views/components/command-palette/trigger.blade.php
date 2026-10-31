{{-- optimistic-ui: n/a — client-only
     A field that opens the command palette and hands it what was typed. What it holds is text on
     its way to the palette, not a value a server owns. --}}
@props([
    // The palette to open, by its `name`. Without one the field opens every palette on the page
    // that answers an unnamed show event.
    'for' => null,
    // The field's accessible name, given as a visually hidden label. The placeholder is not one:
    // it disappears with the first key.
    'label' => null,
    'placeholder' => null,
    // A shortcut shown at the end of the field, such as "⌘K". Shown only: the palette binds its
    // own `hotkey`.
    'shortcut' => null,
    // The palette's hotkey, written as the palette takes it (`cmd+k`, `ctrl+shift+p`, `/`), which
    // the field states to assistive technology as `aria-keyshortcuts`. The default is the
    // palette's own; a palette given another `hotkey` wants the same one here. Empty states none.
    'hotkey' => config('wirekit.components.command-palette.hotkey', 'cmd+k'),
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.trigger', $attributes->getAttributes());

    // The trigger stands on its own Alpine root: a caller's `x-ref` is filed above it.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:click']);

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('command-palette.trigger', 'base', 'block w-full', $scope);

    // The shortcut in the form ARIA takes, on the field rather than on the wrapper a caller's
    // attributes reach: the `kbd` at the end is hidden from assistive technology, so this is the
    // only place a screen reader learns the key that opens the palette.
    $keyShortcuts = \Pushery\WireKit\Support\KeyShortcuts::aria(is_string($hotkey) ? $hotkey : null);

    // The factory holds the statement and binds it to the field, leaving out a shortcut while
    // another palette on the page has taken it: the key then opens that palette.
    $config = \Pushery\WireKit\Support\AlpinePayload::from([
        'for' => filled($for) ? (string) $for : null,
        'keyShortcuts' => $keyShortcuts,
    ]);

    // A caller's name for the trigger, `aria-label` or `aria-labelledby`, belongs on the search field;
    // on the wrapper, a `div` with no role, it names nothing (Support\CallerName). An `aria-label`
    // becomes the field's hidden label.
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
    // A caller's `aria-describedby` goes with the name: a description is announced only for the
    // element that carries it.
    [$callerDescribedBy, $attributes] = \Pushery\WireKit\Support\CallerName::description($attributes);
    // The keys of the shortcut hint, one text or a list (Support\KeyShortcuts::hintKeys()),
    // drawn as one line, a space between the keys.
    $shortcutText = implode(' ', \Pushery\WireKit\Support\KeyShortcuts::hintKeys($shortcut));
@endphp

{{-- A search field rather than a button styled as one, because the reader is meant to type into
     it: the text goes to the palette as it is typed, and a field edits it the way the reader's
     keyboard and input method expect. `aria-haspopup="dialog"` says what pressing it opens.
     Enter and the Down arrow open the palette; `x-wk-ime` keeps the Enter that confirms an
     input method's conversion from opening it. A click opens it from anywhere in the frame:
     the handler sits on the wrapper, where a click on the field and one on the magnifier beside
     it both arrive, once each. In a narrow header the magnifier is most of the frame.

     The field takes `name=""` and so renders no name: what it holds goes to the palette, and a
     form around the trigger has nothing to send for it. --}}
<div data-wk-command-palette-trigger x-data="wirekitCommandPaletteTrigger({{ $config }})" x-on:click="openPalette()" {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    <x-wirekit::input
        type="search"
        name=""
        size="sm"
        :label="$callerLabel ?? (filled($label) ? $label : __('wirekit::Search commands'))"
        :aria-labelledby="$callerLabelledBy"
        :aria-describedby="$callerDescribedBy"
        hide-label
        :placeholder="filled($placeholder) ? $placeholder : __('wirekit::Search…')"
        autocomplete="off"
        aria-haspopup="dialog"
        :aria-keyshortcuts="$keyShortcuts"
        x-bind:aria-keyshortcuts="keyShortcuts"
        x-wk-ime
        x-ref="field"
        x-on:keydown.enter.prevent="openPalette()"
        x-on:keydown.down.prevent="openPalette()"
        x-on:input="type($event)"
        x-on:compositionend="type()"
        x-on:focusout="clear($event)"
    >
        <x-slot:leading>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd"/>
            </svg>
        </x-slot:leading>
        @if($shortcutText !== '')
            <x-slot:trailing>
                <x-wirekit::kbd aria-hidden="true" x-show="shortcutShown">{{ $shortcutText }}</x-wirekit::kbd>
            </x-slot:trailing>
        @endif
    </x-wirekit::input>
</div>
