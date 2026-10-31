{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of a picker that offers times; it holds no state of its own
     and binds to the time list its host spreads into its factory (resources/js/utils/time-list.js). --}}
{{-- The list of times beside a calendar, or below a typed time as its suggestions.

     A listbox, one option per time. Beside a calendar it carries a heading that names it; as the
     suggestions of a typed time it is named by the button that opens it, and fills the box it
     opens in. The chosen time carries `aria-selected` and a check, a closed one `aria-disabled`
     and a name that ends in "unavailable". The option that holds the tab stop is the chosen time,
     else the one the keyboard stands on, else the open time at or after a chosen time between two
     of the list's, else the first open one, and the keyboard is the host's timeListKeydown(). The list scrolls in a box that does not size the row it stands in, so it is
     as tall as the calendar beside it. A host that writes its times in another language than the
     page's names that language in $timeListLang: each option carries it in `lang`, and a closed
     one is named by its text alone, since a name is read in one language. --}}
@php
    $timeListId = $timeListId ?? \Pushery\WireKit\WireKit::stableId('wk-time-list');
    $timeListLabelId = $timeListId.'-label';
    $timeListHeading = $timeListHeading ?? true;
    $timeListLabelledBy = $timeListLabelledBy ?? $timeListLabelId;
    $timeListLang = $timeListLang ?? null;

    // The look of an option in each state, resolved here rather than written into the `:class`
    // binding below, where WireKit::scope() and a personalization of the host could not reach it.
    // The host names itself in $timeListComponent; the classes stay literals in this file, so
    // Tailwind's scanner finds them.
    $timeListComponent = $timeListComponent ?? 'time-picker';
    $timeOptionSelected = \Pushery\WireKit\WireKit::resolveClasses($timeListComponent, 'time-option-selected', 'bg-[var(--color-wk-bg-active)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-heading-weight)] cursor-pointer', $scope ?? null);
    $timeOptionOpen = \Pushery\WireKit\WireKit::resolveClasses($timeListComponent, 'time-option', 'text-[color:var(--color-wk-text)] cursor-pointer hover:bg-[var(--color-wk-bg-hover)]', $scope ?? null);
    $timeOptionClosed = \Pushery\WireKit\WireKit::resolveClasses($timeListComponent, 'time-option-closed', 'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed', $scope ?? null);
@endphp
<div @class(['flex min-h-0 flex-1 flex-col gap-[var(--gap-wk-xs)]', 'sm:w-[var(--size-wk-time-list)]' => $timeListHeading])>
    @if($timeListHeading)
        <div class="flex items-center gap-[var(--gap-wk-xs)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
            <span id="{{ $timeListLabelId }}" class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ __('wirekit::Time') }}</span>
        </div>
    @endif
    <div class="relative min-h-[var(--size-wk-xl)] flex-1">
        <ul
            data-wk-prose-skip
            id="{{ $timeListId }}"
            x-ref="timeList"
            role="listbox"
            aria-labelledby="{{ $timeListLabelledBy }}"
            x-on:keydown="timeListKeydown($event)"
            class="absolute inset-0 flex list-none flex-col gap-px overflow-y-auto overscroll-contain"
            style="list-style: none; margin: 0; padding: 0;"
        >
            <template x-for="(slot, index) in timeListSlots()" :key="slot.value">
                <li
                    data-wk-prose-skip
                    role="option"@if($timeListLang !== null) lang="{{ $timeListLang }}"@endif
                    :aria-selected="slot.selected ? 'true' : 'false'"
                    :aria-disabled="slot.ariaDisabled"
                    :aria-label="slot.name"
                    :tabindex="slot.tabStop ? '0' : '-1'"
                    x-on:click="chooseTimeSlot(index)"
                    x-on:focus="timeListActive = index"
                    class="wk-touch-row flex h-[var(--size-wk-sm)] shrink-0 items-center gap-[var(--gap-wk-xs)] rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-xs)] text-[length:var(--text-wk-sm)] tabular-nums focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]"
                    :class="slot.selected ? {{ \Pushery\WireKit\Support\AlpinePayload::string($timeOptionSelected) }} : (slot.closed ? {{ \Pushery\WireKit\Support\AlpinePayload::string($timeOptionClosed) }} : {{ \Pushery\WireKit\Support\AlpinePayload::string($timeOptionOpen) }})"
                >
                    {{-- The check stands in its own column, so a chosen time keeps its place in the list. --}}
                    <svg class="h-4 w-4 shrink-0" :class="slot.selected ? 'opacity-100' : 'opacity-0'" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span x-text="timeListLabelAt(index)"></span>
                </li>
            </template>
        </ul>
    </div>
</div>
