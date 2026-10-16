{{-- optimistic-ui: n/a — client-only
     A sentence that appears while a form holds values its server does not have, and the dialog
     that asks before a visit leaves them behind. It sends nothing: Stay closes the dialog and Leave
     goes on with the visit, so there is no action whose result could be shown early. --}}
@props([
    // The sentence shown while a field in the scope is unsaved. The slot replaces it.
    'text' => null,
    // What `x-wk-unsaved.confirm` asks before the reader leaves with unsaved fields, unless the
    // scope names its own question in `data-wk-unsaved-confirm`. A browser asks a reload or a
    // closed tab in its own words, so this is the question before a `wire:navigate` visit, asked
    // in the dialog below.
    'question' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only: flags an unknown prop in debug, silent in production.
    WireKit::warnUnknownProps('unsaved-hint', $attributes->getAttributes());

    $text = filled($text) ? $text : __('wirekit::Unsaved changes');
    $question = filled($question) ? $question : __('wirekit::You have unsaved changes. Leave this page anyway?');

    $classes = WireKit::resolveClasses('unsaved-hint', 'base', implode(' ', [
        'inline-flex items-center',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- A polite live region that is always in the page, so the change from saved to unsaved is
     spoken once: `x-wk-unsaved` on the element around it marks it shown and writes the sentence
     again. While nothing is unsaved the stylesheet takes it out of the flow and its sentence out
     of the accessibility tree (dist/wirekit.css, "The sentence of <x-wirekit::unsaved-hint>").
     Outside a scope it stays hidden. A role the caller passes replaces `status` rather than
     standing beside it.

     It also names the words `x-wk-unsaved` writes beside the label of an unsaved field, in the
     reader's language, as `data-wk-unsaved-words`; one the caller passes takes their place. --}}
<span {{ $attributes->except('role')->merge(['data-wk-unsaved-words' => __('wirekit::Unsaved change')])->class([$classes]) }} data-wk-unsaved-hint data-wk-unsaved-confirm="{{ $question }}" role="{{ $attributes->get('role', 'status') }}">
    {{-- A soft badge in the warning color, without a symbol. The color of the unsaved state stands
         here, beside the button, and a field takes only a light line and the words beside its
         label, so nothing unsaved looks refused. It wraps: the sentence may be one of the
         developer's own, and on a phone a long one would otherwise run out of its row. --}}
    <x-wirekit::badge intent="warning" surface="soft" size="sm" wrap data-wk-unsaved-text>@if($slot->hasActualContent()){{ $slot }}@else{{ $text }}@endif</x-wirekit::badge>
</span>
{{-- The dialog the question before a `wire:navigate` visit is asked in, as a template:
     utils/unsaved.js puts it into the page the first time a scope asks, writes the scope's
     question into its title and opens it. Stay is its Cancel, focused when it opens and the answer
     Escape gives; Leave dispatches `wirekit:unsaved-leave`, and the visit goes on. A template, not
     the dialog itself, because it may stand wherever the sentence does: in a paragraph, a dialog's
     element would end the paragraph where it stands. --}}
<template data-wk-unsaved-question>
    <x-wirekit::alert-dialog :describedby="false" :scope="$scope">
        <x-wirekit::alert-dialog.title><span data-wk-unsaved-question-text>{{ $question }}</span></x-wirekit::alert-dialog.title>
        <x-wirekit::alert-dialog.actions>
            <x-wirekit::alert-dialog.cancel>
                <x-wirekit::button intent="neutral" surface="ghost" size="sm">{{ __('wirekit::Stay') }}</x-wirekit::button>
            </x-wirekit::alert-dialog.cancel>
            <x-wirekit::button intent="danger" size="sm" x-on:click="$dispatch('wirekit:unsaved-leave')">{{ __('wirekit::Leave') }}</x-wirekit::button>
        </x-wirekit::alert-dialog.actions>
    </x-wirekit::alert-dialog>
</template>