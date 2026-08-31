{{-- optimistic-ui: n/a — client-only
     A button that closes the dialog. Nothing is asked of the server. --}}
@props([
    'scope' => null,
])

{{-- Alert dialog cancel — pre-wired to close the parent alert-dialog
     via x-on:click="close()". The parent provides the close() method
     via x-data="wirekitAlertDialog(...)".

     Use this instead of a bare <x-wirekit::button> when you want the
     Cancel control to actually close the dialog without manually
     wiring `$dispatch('wirekit-alert-dialog-close', { name })`.

     Defaults: renders a neutral filled button reading "Cancel". Pass
     a default slot to override the button label, OR pass any inner
     <x-wirekit::button> to customize variant / size / icon.

     Example:
       <x-wirekit::alert-dialog.cancel />
       <x-wirekit::alert-dialog.cancel>Back</x-wirekit::alert-dialog.cancel>
       <x-wirekit::alert-dialog.cancel>
           <x-wirekit::button intent="neutral" surface="ghost">Discard</x-wirekit::button>
       </x-wirekit::alert-dialog.cancel> --}}
@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog.cancel', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('alert-dialog.cancel', 'base', '', $scope);
@endphp

{{-- data-wk-alert-cancel is what the dialog looks for to place initial focus on
     the least destructive action (the APG alertdialog rule). It sits on the
     wrapper because the wrapper is what this component always renders; the
     dialog then focuses the control inside it. --}}
<div
    x-on:click="close()"
    data-wk-alert-cancel
    {{ $attributes->class([$classes]) }}
>
    @if(trim((string) $slot) === '')
        {{-- Translated, and the key already existed. `Cancel` sat here as a literal while
             lang/en.json carried "Cancel" and lang/de.json carried "Abbrechen" — so a German
             app rendered a fully translated dialog with an English cancel button, and the
             catalog that could have fixed it was already installed. --}}
        <x-wirekit::button intent="neutral" surface="filled">{{ __('Cancel') }}</x-wirekit::button>
    @elseif(preg_match('/<(?:button|a)[\\s>]/i', (string) $slot) === 1)
        {{-- The caller supplied their own control. Matched on the RENDERED markup,
             because that is what a slot holds: by the time it is cast to a string
             Blade has already compiled `<x-wirekit::button>` into `<button>`, so a
             test for the tag NAME never matched and every caller fell through to the
             branch below -- which wrapped their finished button in a second one.
             Nested buttons are invalid HTML, and a browser repairing them tears the
             surrounding structure apart: the control rendered as an empty box beside
             its own label, and on the documentation page every heading after it
             stopped being a heading. --}}
        {{-- Caller supplied a full WireKit component (typically a
             button) — use it verbatim. The x-on:click on the parent
             <div> handles the close event so the caller's button
             doesn't need its own wire:click. --}}
        {{ $slot }}
    @else
        {{-- Plain text slot — wrap it in the default Cancel button so
             keyboard + screen-reader semantics stay correct. --}}
        <x-wirekit::button intent="neutral" surface="filled">{{ $slot }}</x-wirekit::button>
    @endif
</div>
