{{-- optimistic-ui: n/a — sub-component
     A partial of the field components. It explains a field and changes nothing. --}}
{{-- The help of a field: a question mark in a circle beside the label, which shows the text in
     a tooltip on hover and on keyboard focus, and the same text as the field's description.
     Included by label.blade.php, and by the fields that write their own label, with:

       $helpText    the explanation
       $helpName    the label's text, which names the button ("Help for Type"); may be empty
       $helpId      the id the control lists in aria-describedby, or null
       $helpButton  false where the label is visually hidden: a button nobody can see must
                    not take a tab stop, and the description still reaches a screen reader
       $helpField   the field's name, or its id where it has none; may be empty

     A click on the button, which Enter and Space make as well, dispatches `wirekit:field-help`
     from the button, bubbling, with the field's name and label: a page that explains a field at
     greater length, in a modal of its own, listens for it. Without a listener nothing happens,
     and the tooltip shows on hover and focus either way.
     The button is a `span` with the role, not a `<button>`: a `<fieldset disabled>` disables every
     form control inside it, and a reader who may only look at a form still needs the explanation
     of its fields. The keyboard model a `<button>` brings is written out below for that reason.

     Beside the `<label>`, never inside it. A named control inside a label becomes part of the
     name of the field the label names, and the field would be announced as "Type Help for
     Type".

     The description is a `hidden` element. A screen reader reading the page in order then
     meets the text once, on the field, rather than again after the label; an element that is
     hidden still describes the control that points at it. --}}
@php
    // The name is one line of text: a label or legend written over several lines in a view hands
    // over its line breaks and indentation, and neither belongs in the button's name or the
    // event's label.
    $helpName = trim((string) preg_replace('/\s+/u', ' ', (string) ($helpName ?? '')));
@endphp
@if($helpButton ?? true)
    <x-wirekit::tooltip as="span" :text="$helpText" :focusable-trigger="false" class="shrink-0" data-wk-field-help>
        <span
            role="button"
            tabindex="0"
            aria-label="{{ filled($helpName ?? null) ? __('wirekit::Help for :label', ['label' => $helpName]) : __('wirekit::Help') }}"
            x-on:click="$dispatch('wirekit:field-help', { name: {{ \Pushery\WireKit\Support\AlpinePayload::string($helpField ?? '') }}, label: {{ \Pushery\WireKit\Support\AlpinePayload::string($helpName ?? '') }} })"
            x-on:keydown.enter.prevent="$el.click()"
            x-on:keydown.space.prevent="$el.click()"
            {{-- 24px, the WCAG 2.2 target size, and a negative block margin of 4px on each side,
                 so the row it shares with the label is exactly as tall as a label without it:
                 fields side by side keep their controls on one line whether or not they have
                 help. A block rather than an inline box, so it opens no line box in the tooltip's
                 wrapper: the page's line height would make that wrapper taller than the label. --}}
            class="-my-1 flex items-center justify-center min-w-[24px] min-h-[24px] rounded-full text-[color:var(--color-wk-text-subtle)] hover:text-[color:var(--color-wk-text-muted)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-help"
        >
            {{-- A filled circle with the mark cut out, the same in every icon preset: Lucide has no
                 filled question mark, and an alias needs a glyph in every preset. The mark sits in
                 the middle of the circle: its height spans 6 to 18 around the center of 12, and its
                 stem and dot stand on the vertical axis. The mark shows what is behind it, so mark and
                 circle contrast as circle and page do, light and dark. --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm11.378-3.917c-.89-.777-2.366-.777-3.255 0a.75.75 0 0 1-.988-1.129c1.454-1.272 3.776-1.272 5.23 0 1.513 1.324 1.513 3.518 0 4.842a3.75 3.75 0 0 1-.837.552c-.676.328-1.028.774-1.028 1.152v.75a.75.75 0 0 1-1.5 0v-.75c0-1.279 1.06-2.107 1.875-2.502.182-.088.351-.199.503-.331.83-.727.83-1.857 0-2.584ZM12 18a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"/>
            </svg>
        </span>
    </x-wirekit::tooltip>
@endif
@if(filled($helpId ?? null))
    <span id="{{ $helpId }}" hidden>{{ $helpText }}</span>
@endif
