{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of a datetime picker with `range`; it holds no state of its
     own and binds to the time of one end that resources/js/components/datetime-picker-time.js
     holds, with the time list and its suggestions spread in. --}}
{{-- The time of one end of a range, picked from a list.

     A row named after its end, "Start" or "End", with a field that shows the time, or "Time"
     without one, and opens the list of times below it. The field is a button named by the row and
     the time it shows, `aria-haspopup="listbox"`, and the list is the time list, named by the row,
     placed below the field by the script and kept inside the dialog for its focus trap. A time the
     day of the end closes is said in words under the field, in a live region that stays in the
     page while it is empty. The keyboard and the closing are the suggestions of a typed time
     (resources/js/utils/time-input.js): Escape closes the list first, and a focus that leaves the
     row closes it. In a modal on a phone the field gives way to a select with the same times,
     its closed ones disabled, which the phone opens as its own wheel.

     Variables: $timeSelectId and $timeSelectLabel. --}}
@php
    $timeSelectId = $timeSelectId ?? \Pushery\WireKit\WireKit::stableId('wk-time-select');
    $timeSelectLabel = $timeSelectLabel ?? __('wirekit::Time');
    $timeSelectLabelId = $timeSelectId.'-label';
    $timeSelectValueId = $timeSelectId.'-value';
    $timeSelectErrorId = $timeSelectId.'-error';
    $timeSelectListId = $timeSelectId.'-list';
@endphp
<div
    x-ref="timeInput"
    x-on:keydown.escape="timeInputEscape($event)"
    x-on:click.outside="closeTimeInputList()"
    x-on:focusout="timeInputFocusOut($event)"
    class="flex flex-col"
>
    <div class="flex items-center justify-between gap-[var(--gap-wk-md)]">
        <span id="{{ $timeSelectLabelId }}" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $timeSelectLabel }}</span>
        <div x-ref="timeInputField" x-show="! dialogModal">
            <button
                type="button"
                id="{{ $timeSelectId }}"
                x-ref="timeInputToggle"
                x-on:click="toggleTimeInputList()"
                aria-haspopup="listbox"
                aria-expanded="false"
                x-bind:aria-expanded="timeInputListOpen ? 'true' : 'false'"
                aria-controls="{{ $timeSelectListId }}"
                aria-labelledby="{{ $timeSelectLabelId }} {{ $timeSelectValueId }}"
                aria-describedby="{{ $timeSelectErrorId }}"
                x-bind:aria-invalid="timeInputError !== '' ? 'true' : null"
                class="wk-field wk-touch-row flex h-[var(--size-wk-md)] min-w-[var(--size-wk-time-list)] cursor-pointer items-center gap-[var(--gap-wk-xs)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-input)] shadow-[var(--shadow-wk-sm)] ps-[var(--padding-wk-x-sm)] pe-[var(--padding-wk-x-xs)] text-start text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <svg class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <span id="{{ $timeSelectValueId }}" class="min-w-0 flex-1 truncate tabular-nums">
                    <span x-show="! timeSelectEmpty()" x-text="timeSelectText()"></span>
                    <span x-show="timeSelectEmpty()" class="text-[color:var(--color-wk-text-placeholder)]">{{ __('wirekit::Time') }}</span>
                </span>
                <svg class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                </svg>
            </button>
        </div>
        <div x-show="dialogModal" style="display: none" class="relative">
            <select
                id="{{ $timeSelectId }}-select"
                x-on:change="chooseTimeFromSelect($event)"
                aria-labelledby="{{ $timeSelectLabelId }}"
                aria-describedby="{{ $timeSelectErrorId }}"
                x-bind:aria-invalid="timeInputError !== '' ? 'true' : null"
                class="wk-field block h-[var(--size-wk-md)] min-w-[var(--size-wk-time-list)] appearance-none rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-input)] ps-[var(--padding-wk-x-md)] pe-[var(--size-wk-md)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-sm)] cursor-pointer focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <option value="" disabled x-bind:selected="! timeListValue">{{ __('wirekit::Time') }}</option>
                <template x-for="slot in timeListSlots()" :key="slot.value">
                    <option :value="slot.value" :disabled="slot.closed" :selected="slot.value === timeListValue" x-text="slot.label"></option>
                </template>
            </select>
            <svg class="pointer-events-none absolute top-1/2 end-[var(--padding-wk-x-md)] h-4 w-4 -translate-y-1/2 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>
    <p data-wk-prose-skip id="{{ $timeSelectErrorId }}" aria-live="polite" aria-atomic="true" x-text="timeInputError" class="mt-[var(--space-wk-xs)] text-end text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)] empty:mt-0"></p>
    {{-- The list stands fixed below the field, placed by the script, so the dialog's scrolling box
         does not cut it off; it stays in the dialog for its focus trap. --}}
    <div
        x-ref="timeInputList"
        x-show="timeInputListOpen"
        class="fixed z-[var(--z-wk-dropdown,50)] flex h-[calc(var(--size-wk-sm)*6)] w-[var(--size-wk-time-list)] flex-col rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] p-[var(--padding-wk-x-xs)] shadow-[var(--shadow-wk-lg)]"
    >
        @include('wirekit::components.partials.time-list', ['timeListId' => $timeSelectListId, 'timeListHeading' => false, 'timeListLabelledBy' => $timeSelectLabelId])
    </div>
</div>
