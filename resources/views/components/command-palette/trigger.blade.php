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
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette.trigger', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('command-palette.trigger', 'base', 'block w-full', $scope);

    $config = \Pushery\WireKit\Support\AlpinePayload::from([
        'for' => filled($for) ? (string) $for : null,
    ]);
@endphp

{{-- A search field rather than a button styled as one, because the reader is meant to type into
     it: the text goes to the palette as it is typed, and a field edits it the way the reader's
     keyboard and input method expect. `aria-haspopup="dialog"` says what pressing it opens.
     Enter and the Down arrow open the palette; `x-wk-ime` keeps the Enter that confirms an
     input method's conversion from opening it. --}}
<div data-wk-command-palette-trigger x-data="wirekitCommandPaletteTrigger({{ $config }})" {{ $attributes->class([$classes]) }}>
    <x-wirekit::input
        type="search"
        size="sm"
        :label="filled($label) ? $label : __('wirekit::Search commands')"
        hide-label
        :placeholder="filled($placeholder) ? $placeholder : __('wirekit::Search…')"
        autocomplete="off"
        aria-haspopup="dialog"
        x-wk-ime
        x-ref="field"
        x-on:click="openPalette()"
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
        @if(filled($shortcut))
            <x-slot:trailing>
                <x-wirekit::kbd aria-hidden="true">{{ $shortcut }}</x-wirekit::kbd>
            </x-slot:trailing>
        @endif
    </x-wirekit::input>
</div>
