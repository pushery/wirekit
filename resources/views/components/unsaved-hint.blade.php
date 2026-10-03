{{-- optimistic-ui: n/a — presentational
     A sentence that appears while a form holds values its server does not have. It sends nothing,
     so there is no action whose result could be shown early. --}}
@props([
    // The sentence shown while a field in the scope is unsaved. The slot replaces it.
    'text' => null,
    // What `x-wk-unsaved.confirm` asks before the reader leaves with unsaved fields, unless the
    // scope names its own question in `data-wk-unsaved-confirm`. A browser asks a reload or a
    // closed tab in its own words, so this is the question before a `wire:navigate` visit.
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
        'text-[color:var(--color-wk-text-muted)]',
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
        <span aria-hidden="true" class="inline-block h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--color-wk-border-unsaved)]"></span>
        @if($slot->hasActualContent()){{ $slot }}@else{{ $text }}@endif
    </span>
</span>