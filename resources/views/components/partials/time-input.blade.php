{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of a picker that takes a typed time; it holds no state of its
     own and binds to the time input its host spreads into its factory
     (resources/js/utils/time-input.js), and to the time list it shows as suggestions. --}}
{{-- A time typed in segments, with the list of times as suggestions.

     A field with a clock, the hour, the minute and the period of the day in the locale's order
     with the text between them, and a button that opens the list of times below it. Below a
     calendar the field stands in a row named "Time", or "Start" and "End" for the two ends of a
     range; as a time picker's own field it stands alone,
     named by the picker's label and described by its hint and error. Each segment is an `<input>`
     with the role of a spin button, named after what it holds, with its range and its value; an
     empty one shows a placeholder. The script knows the locale's order, so it writes the segments
     into the frame the server renders, as the time list writes its times. Each segment stands on
     an invisible copy of the widest text it can show, which gives it its width. Why a typed time
     cannot be taken is said in words under the field, in a live region that stays in the page
     while it is empty.

     Variables: $timeInputId, $timeInputLabel (the name of the row, "Time" by default); and for a
     field of its own, $timeInputRow false with $timeInputLabelledBy, $timeInputDescribedBy,
     $timeInputSize (`sm`, `md` or `lg`), $timeInputServerError, $timeInputDisabled,
     $timeInputReadonly (the segments can be read and moved between, not changed, and the
     suggestions do not open) and $timeInputFirstId (the id the first segment takes, which a label
     points at, or null). --}}
@php
    $timeInputId = $timeInputId ?? \Pushery\WireKit\WireKit::stableId('wk-time-input');
    $timeInputLabelId = $timeInputId.'-label';
    $timeInputErrorId = $timeInputId.'-error';
    $timeInputToggleId = $timeInputId.'-toggle';
    $timeInputListId = $timeInputId.'-list';
    $timeInputRow = $timeInputRow ?? true;
    $timeInputLabel = $timeInputLabel ?? __('wirekit::Time');
    $timeInputLabelledBy = $timeInputLabelledBy ?? $timeInputLabelId;
    $timeInputSegmentsDescribedBy = trim($timeInputErrorId.' '.($timeInputDescribedBy ?? ''));
    $timeInputSize = $timeInputSize ?? 'md';
    $timeInputHeight = match ($timeInputSize) {
        'sm' => 'h-[var(--size-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)]',
        default => 'h-[var(--size-wk-md)]',
    };
    // The frame and each segment take the text size as a utility of their own, which is what the
    // touch floor of `wk-field` lifts to 16px: a size a segment inherited would stay below it, and
    // iOS zooms the page on a field below 16px.
    $timeInputText = match ($timeInputSize) {
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        default => 'text-[length:var(--text-wk-md)]',
    };
    $timeInputServerError = (bool) ($timeInputServerError ?? false);
    $timeInputDisabled = (bool) ($timeInputDisabled ?? false);
    $timeInputReadonly = (bool) ($timeInputReadonly ?? false);
    $timeInputFirstId = $timeInputFirstId ?? null;
@endphp
<div
    x-ref="timeInput"
    x-on:keydown.escape="timeInputEscape($event)"
    x-on:click.outside="closeTimeInputList()"
    x-on:focusout="timeInputFocusOut($event)"
    class="flex flex-col"
>
    <div @class(['flex items-center', 'justify-between gap-[var(--gap-wk-md)]' => $timeInputRow])>
        @if($timeInputRow)
            <span id="{{ $timeInputLabelId }}" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $timeInputLabel }}</span>
        @endif
        <div
            x-ref="timeInputField"
            data-wk-time-segments
            role="group"
            aria-labelledby="{{ $timeInputLabelledBy }}"
            @class([
                'wk-field-frame flex items-center gap-[var(--gap-wk-xs)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] bg-[var(--color-wk-bg-input)] shadow-[var(--shadow-wk-sm)] ps-[var(--padding-wk-x-sm)] pe-[var(--padding-wk-x-xs)] text-[color:var(--color-wk-text)] focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
                $timeInputHeight,
                $timeInputText,
                'w-full' => ! $timeInputRow,
                'border-[var(--color-wk-border-error)]' => $timeInputServerError,
                'opacity-[var(--opacity-wk-disabled)]' => $timeInputDisabled,
            ])
            @unless($timeInputServerError) :class="timeInputError !== '' ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]'" @endunless
        >
            <svg class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span @class(['flex items-center tabular-nums', 'flex-1' => ! $timeInputRow])>
                <template x-for="part in timeInputParts()" :key="part.key">
                    <span class="inline-grid">
                        <span aria-hidden="true" class="invisible col-start-1 row-start-1 whitespace-pre px-[calc(var(--padding-wk-x-xs)/2)]" x-text="part.sizer"></span>
                        <template x-if="! part.segment">
                            <span aria-hidden="true" class="col-start-1 row-start-1 whitespace-pre" x-text="part.text"></span>
                        </template>
                        <template x-if="part.segment">
                            <input
                                type="text"
                                role="spinbutton"
                                x-wk-ime
                                autocomplete="off"
                                spellcheck="false"
                                {{-- The invisible copy above sets the segment's width. A text field is twenty characters
                                     wide on its own, and that width counts where the box is sized to its content, which
                                     `w-full` does not: without `size` a segment is as wide as a whole field. --}}
                                size="1"
                                placeholder="--"
                                aria-describedby="{{ $timeInputSegmentsDescribedBy }}"
                                @if($timeInputDisabled) disabled @endif
                                @if($timeInputReadonly) readonly @endif
                                @if($timeInputServerError) aria-invalid="true" @else :aria-invalid="timeInputError !== '' ? 'true' : null" @endif
                                @if($timeInputFirstId !== null) :id="part.first ? {{ \Pushery\WireKit\Support\AlpinePayload::string($timeInputFirstId) }} : null" @endif
                                :inputmode="part.inputmode"
                                :data-wk-time-segment="part.type"
                                :aria-label="part.name"
                                :aria-valuemin="part.min"
                                :aria-valuemax="part.max"
                                :aria-valuenow="timeInputValueNow(part.type)"
                                :aria-valuetext="timeInputValueText(part.type)"
                                :value="timeInputText(part.type)"
                                x-on:focus="timeInputFocus(part.type)"
                                x-on:keydown="timeInputKeydown($event, part.type)"
                                x-on:beforeinput="timeInputBeforeInput($event, part.type)"
                                x-on:input="timeInputInput($event, part.type)"
                                class="wk-field wk-time-segment {{ $timeInputText }} col-start-1 row-start-1 w-full min-w-0 rounded-[var(--radius-wk-sm)] bg-transparent px-[calc(var(--padding-wk-x-xs)/2)] text-center text-[color:var(--color-wk-text)] caret-transparent focus:outline-hidden placeholder:text-[color:var(--color-wk-text-placeholder)] focus:bg-[var(--color-wk-accent)] focus:text-[color:var(--color-wk-accent-fg)] focus:placeholder:text-[color:var(--color-wk-accent-fg)] disabled:cursor-not-allowed"
                            />
                        </template>
                    </span>
                </template>
            </span>
            {{-- In the frame beside the segments the button takes the touch size itself on a touch screen
                 (`wk-field-affordance`), as a field's clear button does: a 44px patch around it would reach
                 into the segment before it and take the taps meant for it. --}}
            <button
                type="button"
                id="{{ $timeInputToggleId }}"
                x-ref="timeInputToggle"
                x-on:click="toggleTimeInputList()"
                aria-haspopup="listbox"
                aria-expanded="false"
                x-bind:aria-expanded="timeInputListOpen ? 'true' : 'false'"
                aria-controls="{{ $timeInputListId }}"
                aria-label="{{ __('wirekit::Suggested times') }}"
                @if($timeInputDisabled) disabled @endif
                @if($timeInputReadonly) aria-disabled="true" @endif
                class="wk-field-affordance flex h-[var(--size-wk-target-min)] w-[var(--size-wk-target-min)] shrink-0 cursor-pointer items-center justify-center rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-subtle)] hover:bg-[var(--color-wk-bg-hover)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:cursor-not-allowed aria-disabled:cursor-default"
            >
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
    </div>
    <p data-wk-prose-skip id="{{ $timeInputErrorId }}" aria-live="polite" aria-atomic="true" x-text="timeInputError" @class(['mt-[var(--space-wk-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)] empty:mt-0', 'text-end' => $timeInputRow])></p>
    {{-- The suggestions stand fixed below the field, placed by the script, so a dialog's scrolling
         box around them does not cut them off; inside a dialog they stay in it for its focus
         trap. --}}
    <div
        x-ref="timeInputList"
        x-show="timeInputListOpen"
        class="fixed z-[var(--z-wk-dropdown,50)] flex h-[calc(var(--size-wk-sm)*6)] w-[var(--size-wk-time-list)] flex-col rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] p-[var(--padding-wk-x-xs)] shadow-[var(--shadow-wk-lg)]"
    >
        @include('wirekit::components.partials.time-list', ['timeListId' => $timeInputListId, 'timeListHeading' => false, 'timeListLabelledBy' => $timeInputToggleId])
    </div>
</div>
