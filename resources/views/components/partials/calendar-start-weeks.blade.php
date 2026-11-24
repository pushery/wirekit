{{-- optimistic-ui: n/a — sub-component
     The weeks of one month of a calendar as the page loads, for the moment before the script starts
     and for a page that runs none (Support\CalendarStart). The grid's own rows come from a
     template, which renders nothing until then, and the script hides these as it starts. A day is
     no button here, since without the script a press picks nothing, and the copy is a plain table
     rather than a grid: a reader of the page, and a script that asks for the grid's rows or cells,
     finds the grid the script draws and only that one. The weekdays head the copy as the first
     row of its body, with no `thead` and no `abbr`, so the grid's head is the one a script asking
     for a calendar's column headers finds; their long names stand beside them for a screen reader.

     Variables: $startMonth, $weekdays, $weekNumbers, $dayBtnClasses, $dayCellClasses and $disabled. --}}
<tr>
    @if($weekNumbers)
        <th class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col"><span aria-hidden="true">#</span><span class="sr-only">{{ __('wirekit::Week number') }}</span></th>
    @endif
    @foreach($weekdays as $startWeekday)
        <th class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col"><span aria-hidden="true">{{ $startWeekday['short'] }}</span><span class="sr-only">{{ $startWeekday['long'] }}</span></th>
    @endforeach
</tr>
@foreach($startMonth['weeks'] as $startWeek)
    <tr>
        @if($weekNumbers)
            <th scope="row" class="text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)] tabular-nums">{{ \Pushery\WireKit\Support\CalendarStart::weekNumber($startWeek) }}</th>
        @endif
        @foreach($startWeek as $startDay)
            @php
                // The marks the script's class binding sets on a day it starts with, without the
                // ones that answer a pointer or need the reader's own day.
                $startDayClasses = implode(' ', array_keys(array_filter([
                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]' => $startDay['selected'],
                    'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed' => $startDay['inMonth'] && $startDay['closed'] && ! $startDay['selected'],
                    'text-[color:var(--color-wk-text-muted)] opacity-40 cursor-default' => ! $startDay['inMonth'],
                    'opacity-[var(--opacity-wk-disabled)] cursor-not-allowed' => $disabled && $startDay['inMonth'],
                ])));
            @endphp
            <td class="{{ $dayCellClasses }}" @if($startDay['band'] !== null) data-wk-band="{{ $startDay['band'] }}" @endif><span class="wk-calendar-day {{ $dayBtnClasses }} {{ $startDayClasses }}" @if($startDay['inRange']) data-wk-in-range @endif>{{ $startDay['day'] }}</span></td>
        @endforeach
    </tr>
@endforeach
