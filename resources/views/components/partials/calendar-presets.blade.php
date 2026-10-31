{{-- optimistic-ui: n/a — sub-component
     Not a component but an @include of a calendar with `range` and `presets`; it holds no state of
     its own and binds to the presets of resources/js/components/calendar.js. --}}
{{-- The presets of a calendar: ranges such as "Last 7 days" a reader picks in one press.

     On a wide screen a `listbox` beside the calendar, one option per preset: the chosen one
     carries `aria-selected`, one outside the calendar's limits `aria-disabled`, and the option that
     holds the tab stop is the chosen one, else the one the keyboard stands on, else the first open
     one. The arrow keys move between open presets, Home and End go to the first and the last, and
     Enter or Space picks. On a narrow screen, and in the modal of a picker on a phone, a native
     select above the calendar offers the same presets. A range picked in the grid marks the preset it equals, or "Custom" when the list
     holds it. A disabled calendar closes every option and keeps no tab stop in the list; a read-only
     one keeps the options in reach, says `aria-readonly` on the list, and picks nothing. --}}
@php
    $presetListLabelId = $presetListLabelId ?? \Pushery\WireKit\WireKit::stableId('wk-calendar-presets-label');

    // The look of a preset in each state, resolved here rather than written into the `:class`
    // binding below, where WireKit::scope() and a personalization of the calendar could not reach
    // it. The classes stay literals in this file, so Tailwind's scanner finds them.
    $presetSelected = \Pushery\WireKit\WireKit::resolveClasses('calendar', 'preset-selected', 'bg-[var(--color-wk-bg-active)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-heading-weight)] cursor-pointer', $scope ?? null);
    $presetOpen = \Pushery\WireKit\WireKit::resolveClasses('calendar', 'preset', 'text-[color:var(--color-wk-text)] cursor-pointer hover:bg-[var(--color-wk-bg-hover)]', $scope ?? null);
    $presetClosed = \Pushery\WireKit\WireKit::resolveClasses('calendar', 'preset-closed', 'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed', $scope ?? null);
@endphp
<div data-wk-calendar-presets class="flex flex-col sm:border-e-[length:var(--border-wk-width)] sm:border-[var(--color-wk-border)] sm:pe-[var(--padding-wk-x-md)]">
    <span id="{{ $presetListLabelId }}" class="sr-only">{{ __('wirekit::Preset ranges') }}</span>

    {{-- A narrow screen: a native select, whose own picker suits a phone. --}}
    <div data-wk-calendar-preset-select class="relative sm:hidden">
        {{-- A native select has no read-only state, so a read-only calendar disables it: the chosen
             preset stays in view, and nothing picks another. --}}
        <select
            aria-labelledby="{{ $presetListLabelId }}"
            @disabled(($disabled ?? false) || ($readonly ?? false))
            x-on:change="choosePreset($event.target.value)"
            class="wk-field block w-full appearance-none disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-input)] h-[var(--size-wk-md)] ps-[var(--padding-wk-x-md)] pe-[calc(var(--padding-wk-x-md)+--spacing(4))] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
        >
            <option value="" disabled x-bind:selected="preset === null">{{ __('wirekit::Preset ranges') }}</option>
            <template x-for="option in presetOptions()" :key="option.key">
                <option :value="option.key" :disabled="option.disabled" :selected="option.selected" x-text="option.label"></option>
            </template>
        </select>
        <svg class="pointer-events-none absolute top-1/2 end-[var(--padding-wk-x-md)] h-4 w-4 -translate-y-1/2 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>
    </div>

    {{-- A wide screen: the list beside the calendar. --}}
    <ul
        data-wk-prose-skip
        x-ref="presetList"
        role="listbox"
        aria-labelledby="{{ $presetListLabelId }}"
        @if($disabled ?? false) aria-disabled="true" @endif
        @if($readonly ?? false) aria-readonly="true" @endif
        x-on:keydown="presetKeydown($event)"
        data-wk-calendar-preset-list
        class="hidden list-none flex-col gap-px sm:flex"
        style="list-style: none; margin: 0; padding: 0;"
    >
        <template x-for="(option, index) in presetOptions()" :key="option.key">
            <li
                data-wk-prose-skip
                role="option"
                :data-wk-preset-option="option.key"
                :aria-selected="option.selected ? 'true' : 'false'"
                :aria-disabled="option.disabled ? 'true' : null"
                :tabindex="option.tabStop ? '0' : '-1'"
                x-on:click="choosePreset(option.key)"
                x-on:focus="presetActive = index"
                x-text="presetLabelAt(index)"
                class="wk-touch-row flex h-[var(--size-wk-sm)] shrink-0 items-center whitespace-nowrap rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]"
                :class="option.selected ? {{ \Pushery\WireKit\Support\AlpinePayload::string($presetSelected) }} : (option.disabled ? {{ \Pushery\WireKit\Support\AlpinePayload::string($presetClosed) }} : {{ \Pushery\WireKit\Support\AlpinePayload::string($presetOpen) }})"
            ></li>
        </template>
    </ul>
</div>
