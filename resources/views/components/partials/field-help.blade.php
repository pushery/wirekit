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

     Beside the `<label>`, never inside it. A named control inside a label becomes part of the
     name of the field the label names, and the field would be announced as "Type Help for
     Type".

     The description is a `hidden` element. A screen reader reading the page in order then
     meets the text once, on the field, rather than again after the label; an element that is
     hidden still describes the control that points at it. --}}
@if($helpButton ?? true)
    <x-wirekit::tooltip as="span" :text="$helpText" :focusable-trigger="false" class="shrink-0" data-wk-field-help>
        <button
            type="button"
            aria-label="{{ filled($helpName ?? null) ? __('wirekit::Help for :label', ['label' => $helpName]) : __('wirekit::Help') }}"
            x-on:click="$dispatch('wirekit:field-help', { name: {{ \Pushery\WireKit\Support\AlpinePayload::string($helpField ?? '') }}, label: {{ \Pushery\WireKit\Support\AlpinePayload::string($helpName ?? '') }} })"
            {{-- 24px, the WCAG 2.2 target size, and a negative block margin of 4px on each side,
                 so the row it shares with the label is exactly as tall as a label without it:
                 fields side by side keep their controls on one line whether or not they have
                 help. A block rather than an inline box, so it opens no line box in the tooltip's
                 wrapper: the page's line height would make that wrapper taller than the label. --}}
            class="-my-1 flex items-center justify-center min-w-[24px] min-h-[24px] rounded-full text-[color:var(--color-wk-text-subtle)] hover:text-[color:var(--color-wk-text-muted)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-help"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z"/>
            </svg>
        </button>
    </x-wirekit::tooltip>
@endif
@if(filled($helpId ?? null))
    <span id="{{ $helpId }}" hidden>{{ $helpText }}</span>
@endif
