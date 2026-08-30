{{-- optimistic-ui: n/a — client-only
     A field whose only reader is the dialog it sits in. Nothing is asked of the server, so
     there is no result to show early and nothing to roll back. --}}
@props([
    'scope' => null,
])

{{-- Alert dialog confirmation field — the brake in front of an irreversible action.

     Renders NOTHING unless the parent alert-dialog was given a `confirmation-phrase`,
     so dropping it into a dialog that does not ask for one costs nothing and changes
     nothing. Place it in the dialog BODY, next to the description — not inside
     `alert-dialog.actions`, which is a flex row and would put a text field beside the
     buttons.

     It shows the phrase while you type it, which is the whole point: a brake you have to
     remember the wording of is a brake that gets abandoned, and one that hides its target
     teaches people to paste from somewhere else.

     Example — the field's own placement. The full composition, including what the confirm
     control is wired to, is on the docs page rather than repeated here:

       <x-wirekit::alert-dialog confirmation-phrase="delete production">
           <x-wirekit::alert-dialog.title>Delete this environment?</x-wirekit::alert-dialog.title>
           <x-wirekit::alert-dialog.description>This cannot be undone.</x-wirekit::alert-dialog.description>
           <x-wirekit::alert-dialog.confirmation />
           …
       </x-wirekit::alert-dialog> --}}
@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog.confirmation', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    $classes = WireKit::resolveClasses('alert-dialog.confirmation', 'base', implode(' ', [
        'mt-[var(--padding-wk-y-lg)] flex flex-col gap-[var(--padding-wk-y-sm)]',
    ]), $scope);

    $labelClasses = WireKit::resolveClasses('alert-dialog.confirmation', 'label', implode(' ', [
        'text-[length:var(--text-wk-sm)] text-[var(--color-wk-text)]',
    ]), $scope);

    $phraseClasses = WireKit::resolveClasses('alert-dialog.confirmation', 'phrase', implode(' ', [
        'font-mono font-semibold text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- x-if rather than x-show: a field that is merely hidden is still focusable and still
     reachable in the tab order, and it would ship an <input> with a label into every
     dialog that never asked for one. --}}
<template x-if="confirmationPhrase !== null">
    <div {{ $attributes->class([$classes]) }}>
        {{-- The phrase itself, visible while it is being typed. Its own element rather
             than part of the label, so it can carry the monospace treatment: a phrase with
             a doubled letter or a trailing space has to be readable character by character.
             `x-text` rather than a Blade echo — the value lives in the dialog's Alpine
             state, which is the one copy of it. --}}
        <span class="{{ $labelClasses }}">{{ __('To continue, type:') }}</span>
        <span class="{{ $phraseClasses }}" x-text="confirmationPhrase"></span>

        {{-- The field owns its own label and its own page-unique id (Support\DomId). A
             hand-rolled <label for> here would have to guess that id, and would point at
             the wrong input as soon as a page carried two of these dialogs. The label is
             sr-only because the two lines above already say the same thing on screen —
             repeating it visibly would read as a stutter. --}}
        <x-wirekit::input
            :label="__('Confirmation phrase')"
            hide-label
            x-model="typed"
            autocomplete="off"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
        />
    </div>
</template>
