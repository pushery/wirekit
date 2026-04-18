@props([
    'value' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Calendar — standalone month grid for date selection.
    // Uses role="grid" with keyboard navigation: arrows, PageUp/Down, Home/End.
    $name = $attributes->get('name', 'date');

    $classes = WireKit::resolveClasses('calendar', 'base', implode(' ', [
        'inline-block',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-lg)]',
        'shadow-[var(--shadow-wk-md)]',
        'p-[var(--padding-wk-x-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);

    $headerClasses = WireKit::resolveClasses('calendar', 'header', implode(' ', [
        'flex items-center justify-between',
        'mb-[var(--padding-wk-y-sm)]',
    ]), $scope);

    $navBtnClasses = implode(' ', [
        'p-1',
        'cursor-pointer',
        'rounded-[var(--radius-wk-sm)]',
        'text-[var(--color-wk-text-muted)]',
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

@endphp

<div
    x-data="wirekitCalendar({ value: {{ $value ? "'" . $value . "'" : 'null' }}, name: '{{ $name }}' })"
    {{ $attributes->class([$classes]) }}
>
    {{-- Hidden input for form submission --}}
    <input type="hidden" name="{{ $name }}" x-ref="hiddenInput" :value="selected" />

    {{-- Month navigation header --}}
    <div class="{{ $headerClasses }}">
        <button type="button" x-on:click="prevMonth()" class="{{ $navBtnClasses }}" aria-label="Previous month">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </button>

        <span class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-md)]" x-text="monthLabel" aria-live="polite"></span>

        <button type="button" x-on:click="nextMonth()" class="{{ $navBtnClasses }}" aria-label="Next month">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
    </div>

    {{-- Calendar grid --}}
    <table role="grid" class="w-full" @keydown="handleKeydown($event)">
        <thead>
            <tr>
                @foreach(['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'] as $day)
                    <th class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[var(--color-wk-text-muted)]" scope="col">{{ $day }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <template x-for="(week, weekIdx) in Array.from({ length: Math.ceil(days.length / 7) }, (_, i) => days.slice(i * 7, i * 7 + 7))" :key="weekIdx">
                <tr role="row">
                    <template x-for="day in week" :key="day.date">
                        <td role="gridcell" class="p-0.5 text-center">
                            <button
                                type="button"
                                x-on:click="day.isCurrentMonth && selectDate(day.date)"
                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay ? '0' : '-1'"
                                :aria-selected="day.isSelected ? 'true' : 'false'"
                                :disabled="!day.isCurrentMonth"
                                class="{{ $dayBtnClasses }}"
                                :class="{
                                    'bg-[var(--color-wk-accent)] text-[var(--color-wk-accent-fg)]': day.isSelected,
                                    'font-[number:var(--font-wk-heading-weight)] ring-1 ring-[var(--color-wk-accent)]': day.isToday && !day.isSelected,
                                    'cursor-pointer hover:bg-[var(--color-wk-bg-subtle)]': day.isCurrentMonth && !day.isSelected,
                                    'text-[var(--color-wk-text-muted)] opacity-40 cursor-default': !day.isCurrentMonth,
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
