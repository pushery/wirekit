@props([
    'value' => null,
    // Multi-month display — render N consecutive months side by side (1 = the
    // classic single grid). Clamped 1..4 in the Alpine component.
    'months' => 1,
    // Replace the static month label with native month + year <select> jump
    // controls for fast navigation. Opt-in; default keeps the label byte-identical.
    'selectableHeader' => false,
    // First day of the week: 0 (Sun) .. 1 (Mon, default) — matches the house
    // convention + the event-calendar default. Configurable via config/wirekit.php.
    'weekStartsOn' => config('wirekit.components.calendar.week-starts-on', 1),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $selectableHeader = BooleanProp::from($selectableHeader, false);

    // Calendar — standalone month grid for date selection.
    // Uses role="grid" with keyboard navigation: arrows, PageUp/Down, Home/End.
    $name = $attributes->get('name', 'date');

    $classes = WireKit::resolveClasses('calendar', 'base', implode(' ', [
        // wk-calendar: on phones (<640px) the dist/wirekit.css rule flips this to
        // block + width:100% so the grid fills the container instead of sitting at
        // its fixed ~312px content width (which reads as "too narrow" and can clip
        // the trailing weekday column in a padding-squeezed container). Desktop
        // keeps the compact inline-block content-width grid — render unchanged.
        'wk-calendar inline-block',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'shadow-[var(--shadow-wk-md)]',
        'p-[var(--padding-wk-x-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $headerClasses = WireKit::resolveClasses('calendar', 'header', implode(' ', [
        'flex items-center justify-between',
        'mb-[var(--padding-wk-y-sm)]',
    ]), $scope);

    $navBtnClasses = implode(' ', [
        'p-1',
        'cursor-pointer',
        'rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
    ]);

    $dayBtnClasses = implode(' ', [
        'flex items-center justify-center',
        'w-9 h-9',
        'rounded-[var(--radius-wk-sm)]',
        'text-[length:var(--text-wk-sm)]',
        'tabular-nums',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]);

    // Native month/year selects (selectableHeader) — token-styled.
    $headerSelectClasses = implode(' ', [
        // appearance-none + a custom chevron overlay (below) so the open arrow
        // matches the select component instead of the browser's far-right default.
        'appearance-none',
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-input)]',
        'pl-[var(--padding-wk-x-sm)] pr-7 py-1',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        'cursor-pointer',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]);

    // Base order is Sunday-first; rotate so the configured start day leads
    // (Monday by default, per the house convention).
    $weekdays = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    $wkStart = ((int) $weekStartsOn) % 7;
    $weekdays = array_merge(array_slice($weekdays, $wkStart), array_slice($weekdays, 0, $wkStart));
@endphp

<div
    x-data="wirekitCalendar({ value: {{ $value ? "'" . $value . "'" : 'null' }}, name: '{{ $name }}', months: {{ (int) $months }}, weekStartsOn: {{ (int) $weekStartsOn }} })"
    {{ $attributes->class([$classes]) }}
>
    {{-- Hidden input for form submission --}}
    <input type="hidden" name="{{ $name }}" x-ref="hiddenInput" :value="selected" />

    {{-- Month navigation header --}}
    <div class="{{ $headerClasses }}">
        <button type="button" x-on:click="prevMonth()" class="{{ $navBtnClasses }}" aria-label="{{ __('Previous month') }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>

        @if($selectableHeader)
            {{-- Native month + year selects: full keyboard + AT support for free,
                 bound straight to the view state so changing them re-renders the
                 grid(s). aria-live mirror keeps the change announced. --}}
            <div class="flex items-center gap-[var(--padding-wk-x-sm)]">
                <label class="sr-only" for="{{ $name }}-month">Month</label>
                <div class="relative">
                    <select id="{{ $name }}-month" x-model.number="viewMonth" aria-label="{{ __('Month') }}" class="wk-field {{ $headerSelectClasses }}">
                        @foreach(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $i => $monthName)
                            <option value="{{ $i }}">{{ $monthName }}</option>
                        @endforeach
                    </select>
                    {{-- Chevron overlay — same glyph + token color as the select component. --}}
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                        <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <label class="sr-only" for="{{ $name }}-year">Year</label>
                <div class="relative">
                    <select id="{{ $name }}-year" x-model.number="viewYear" aria-label="{{ __('Year') }}" class="wk-field {{ $headerSelectClasses }}">
                        <template x-for="y in yearRange" :key="y">
                            <option :value="y" x-text="y"></option>
                        </template>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                        <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <span class="sr-only" aria-live="polite" x-text="monthLabel"></span>
            </div>
        @else
            <span class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-md)]" x-text="monthLabel" aria-live="polite"></span>
        @endif

        <button type="button" x-on:click="nextMonth()" class="{{ $navBtnClasses }}" aria-label="{{ __('Next month') }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>

    @if($months > 1)
        {{-- Multi-month: N grids side by side. Each grid carries data-wk-month so
             the keyboard model (focusOffset) can scope focus to the right grid.
             The shared keydown handler lives on the row wrapper. --}}
        <div class="flex flex-wrap gap-[var(--gap-wk-md)]" @keydown="handleKeydown($event)">
            <template x-for="month in monthsView" :key="month.offset">
                <div :data-wk-month="month.offset">
                    <div class="text-center mb-[var(--padding-wk-y-sm)] font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-sm)]" x-text="month.label"></div>
                    <table role="grid" class="w-full">
                        <thead>
                            <tr>
                                @foreach($weekdays as $day)
                                    <th class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col">{{ $day }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(week, weekIdx) in Array.from({ length: Math.ceil(month.days.length / 7) }, (_, i) => month.days.slice(i * 7, i * 7 + 7))" :key="weekIdx">
                                <tr role="row">
                                    <template x-for="day in week" :key="day.date">
                                        <td role="gridcell" class="p-0.5 text-center" :aria-selected="day.isSelected ? 'true' : 'false'">
                                            <button
                                                type="button"
                                                x-on:click="day.isCurrentMonth && selectDate(day.date)"
                                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay && month.offset === focusOffset ? '0' : '-1'"
                                                :disabled="!day.isCurrentMonth"
                                                class="{{ $dayBtnClasses }}"
                                                :class="{
                                                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]': day.isSelected,
                                                    'font-[number:var(--font-wk-heading-weight)] ring-1 ring-[var(--color-wk-accent)]': day.isToday && !day.isSelected,
                                                    'cursor-pointer hover:bg-[var(--color-wk-bg-subtle)]': day.isCurrentMonth && !day.isSelected,
                                                    'text-[color:var(--color-wk-text-muted)] opacity-40 cursor-default': !day.isCurrentMonth,
                                                    'cursor-pointer': day.isCurrentMonth && day.isSelected,
                                                }"
                                                x-text="day.dayOfMonth"
                                            ></button>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    @else
    {{-- Calendar grid --}}
    <table role="grid" class="w-full" @keydown="handleKeydown($event)">
        <thead>
            <tr>
                @foreach($weekdays as $day)
                    <th class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col">{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <template x-for="(week, weekIdx) in Array.from({ length: Math.ceil(days.length / 7) }, (_, i) => days.slice(i * 7, i * 7 + 7))" :key="weekIdx">
                <tr role="row">
                    <template x-for="day in week" :key="day.date">
                        {{-- aria-selected lives on the gridcell, NOT the button.
                             Per the WAI-ARIA grid pattern, `aria-selected` is
                             only allowed on gridcell/option/row/rowheader/tab/
                             treeitem roles — placing it on a <button> fails
                             axe-core's aria-allowed-attr (critical). --}}
                        <td role="gridcell" class="p-0.5 text-center" :aria-selected="day.isSelected ? 'true' : 'false'">
                            <button
                                type="button"
                                x-on:click="day.isCurrentMonth && selectDate(day.date)"
                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay ? '0' : '-1'"
                                :disabled="!day.isCurrentMonth"
                                class="{{ $dayBtnClasses }}"
                                :class="{
                                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]': day.isSelected,
                                    'font-[number:var(--font-wk-heading-weight)] ring-1 ring-[var(--color-wk-accent)]': day.isToday && !day.isSelected,
                                    'cursor-pointer hover:bg-[var(--color-wk-bg-subtle)]': day.isCurrentMonth && !day.isSelected,
                                    'text-[color:var(--color-wk-text-muted)] opacity-40 cursor-default': !day.isCurrentMonth,
                                    'cursor-pointer': day.isCurrentMonth && day.isSelected,
                                }"
                                x-text="day.dayOfMonth"
                            ></button>
                        </td>
                    </template>
                </tr>
            </template>
        </tbody>
    </table>
    @endif
</div>
