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
        'text-[length:var(--text-wk-sm)]',
        // The sentence and its symbol take the color of the frames they explain: an unsaved
        // value is a warning, and --color-wk-border-unsaved is the warning text color.
        'text-[color:var(--color-wk-border-unsaved)]',
    ]), $scope);
@endphp

{{-- A polite live region that is always in the page, so the change from saved to unsaved is
     spoken once: `x-wk-unsaved` on the element around it marks it shown and writes the sentence
     again. While nothing is unsaved the stylesheet takes it out of the flow and its sentence out
     of the accessibility tree (dist/wirekit.css, "The sentence of <x-wirekit::unsaved-hint>").
     Outside a scope it stays hidden. A role the caller passes replaces `status` rather than
     standing beside it. --}}
<span {{ $attributes->except('role')->class([$classes]) }} data-wk-unsaved-hint data-wk-unsaved-confirm="{{ $question }}" role="{{ $attributes->get('role', 'status') }}">
    <span data-wk-unsaved-text class="inline-flex items-center gap-1.5">
        {{-- The warning symbol `alert` and `callout` draw, filled in the sentence's color. --}}
        <svg aria-hidden="true" class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" /></svg>
        @if($slot->hasActualContent()){{ $slot }}@else{{ $text }}@endif
    </span>
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