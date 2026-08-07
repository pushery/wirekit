{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the date lands the moment a day is clicked.
     A discrete server value, and the previous one is the server's — so an undo
     destroys nothing the user typed. The optimistic scope nests INSIDE this
     component and binds to `selected`; the DISPLAYED month is deliberately
     outside the snapshot, so a refused pick leaves you looking at the month you
     paged to rather than snapping back. --}}
@props([
    // Livewire method to call optimistically. The date appears selected
    // immediately and is put back if the call fails. Absent -> this component
    // renders exactly as it did before, down to the byte.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    'value' => null,
    // Multi-month display — render N consecutive months side by side (1 = the
    // classic single grid). Clamped 1..4 in the Alpine component.
    // Two-click range selection. `value` then reads and writes
    // `YYYY-MM-DD/YYYY-MM-DD`, the spelling the date-picker's own range flag
    // already uses — a value moved between the two keeps its meaning.
    //
    // Written WITHOUT the component tag on purpose: Blade compiles component tags
    // across the whole file before a PHP comment means anything, so naming one
    // here compiles it, and the failure arrives as `Undefined variable $component`
    // from a line that is not code.
    'range' => false,
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
    use Illuminate\Support\Js;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $selectableHeader = BooleanProp::from($selectableHeader, false);
    $range = BooleanProp::from($range, false);

    // Split for the pre-Alpine markup only. The component reads the same string
    // again in JS — one parse per side rather than a value threaded through two
    // formats, which is how the two would drift.
    [$rangeStart, $rangeEnd] = array_pad(explode('/', (string) $value, 2), 2, '');

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

    // Built here rather than assembled inside the attribute, for two reasons —
    // and the second one is not cosmetic.
    //
    // An apostrophe used to end the JS string it sat in. `{{ }}` escapes it to
    // &#039;, the browser decodes it back to ' inside the attribute, and
    // everything after it is read as more of the expression: a name like
    // "o'clock" was developer-controlled source injected into an evaluated
    // directive. Js::from escapes it.
    //
    // And the literals keep a double quote out of the DIRECTIVE. An inner "
    // ends the attribute for anything scanning the template, which is how this
    // expression stayed invisible to the CSP audit — it was measuring a
    // truncated fragment and reporting the component as broken.
    //
    // The (string) cast matters: without it Js::from would take the
    // JsonSerializable path for a Carbon value and emit JSON.parse('…'), which
    // is a global the CSP evaluator cannot resolve.
    // AlpinePayload, not Js::from: inside a directive the latter emits \u escapes
    // for non-ASCII, and Alpine's CSP tokenizer drops the backslash while keeping the
    // letters — a name with an umlaut would arrive mangled with nothing logged.
    $valueLiteral = $value ? \Pushery\WireKit\Support\AlpinePayload::from((string) $value) : 'null';
    $nameLiteral = \Pushery\WireKit\Support\AlpinePayload::from($name);

    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the day buttons have to be inside
    // it to reach its `run()`.
    //
    // `after: '_notify'` keeps a plain HTML form honest — the hidden input is
    // synced there, and without the call a rollback would leave the form
    // submitting the date that was just taken back.
    //
    // `viewYear` / `viewMonth` are NOT in the snapshot, and that is the design:
    // a reader who paged to March and had their pick refused should still be
    // looking at March. The date rolls back; the place you are looking does not.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_notify',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'reverted' => __('Could not save. Change undone.'),
        ],
    ]);
@endphp

<div
    x-data="wirekitCalendar({ value: {{ $valueLiteral }}, name: {{ $nameLiteral }}, months: {{ (int) $months }}, weekStartsOn: {{ (int) $weekStartsOn }}, range: {{ $range ? 'true' : 'false' }} })"
    {{ $attributes->class([$classes]) }}
>
    {{-- Hidden input for form submission --}}
    {{-- Static value as well as the bound one: the field is empty until Alpine boots, and a form submitted in that window sends nothing while the visible control already shows the value. Both come from the same PHP expression that feeds the factory, so they cannot drift. --}}
    <input type="hidden" name="{{ $name }}" x-ref="hiddenInput" value="{{ $value }}" :value="rangeValue" />
    @if($range)
        {{-- The two ends as named fields as well, matching date-picker's
             `name[start]` / `name[end]`. The combined field above stays, so a
             handler written for a single date still receives something it
             understands when a calendar is switched to range mode. --}}
        <input type="hidden" name="{{ $name }}[start]" x-ref="hiddenStart" value="{{ $rangeStart }}" :value="rangeStartValue" />
        <input type="hidden" name="{{ $name }}[end]" x-ref="hiddenEnd" value="{{ $rangeEnd }}" :value="rangeEndValue" />
    @endif

    @if($optimisticConfig)
        {{-- `display: contents` so the grid and header keep their place in this
             element's layout — an extra box between them and it would change the
             calendar's spacing without changing a class. --}}
        <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
    @endif

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
                <label class="sr-only" for="{{ $name }}-month">{{ __('Month') }}</label>
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
                <label class="sr-only" for="{{ $name }}-year">{{ __('Year') }}</label>
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
                            <template x-for="(week, weekIdx) in weeksOf(month.days)" :key="weekIdx">
                                <tr role="row">
                                    <template x-for="day in week" :key="day.date">
                                        <td role="gridcell" class="p-0.5 text-center" :aria-selected="(day.isSelected || day.isInRange || day.isProvisionalEnd) ? 'true' : 'false'">
                                            <button
                                                type="button"
                                                x-on:click="day.isCurrentMonth && {{ $optimisticConfig ? 'run' : 'selectDate' }}(day.date)"
                                                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay && month.offset === focusOffset ? '0' : '-1'"
                                                :disabled="!day.isCurrentMonth"
                                                class="{{ $dayBtnClasses }}"
                                                x-on:mouseenter="hoverDay(day.date)"
                                :data-wk-in-range="day.rangeMarker"
                                                :class="{
                                                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]': day.isSelected,
                                                                    'font-[number:var(--font-wk-heading-weight)] ring-1 ring-[var(--color-wk-accent)]': day.isToday && !day.isSelected,
                                                    'cursor-pointer hover:bg-[var(--color-wk-bg-subtle)]': day.isCurrentMonth && !day.isSelected && !day.isInRange && !day.isProvisionalEnd,
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
            <template x-for="(week, weekIdx) in weeksOf(days)" :key="weekIdx">
                <tr role="row">
                    <template x-for="day in week" :key="day.date">
                        {{-- aria-selected lives on the gridcell, NOT the button.
                             Per the WAI-ARIA grid pattern, `aria-selected` is
                             only allowed on gridcell/option/row/rowheader/tab/
                             treeitem roles — placing it on a <button> fails
                             axe-core's aria-allowed-attr (critical). --}}
                        <td role="gridcell" class="p-0.5 text-center" :aria-selected="(day.isSelected || day.isInRange || day.isProvisionalEnd) ? 'true' : 'false'">
                            <button
                                type="button"
                                x-on:click="day.isCurrentMonth && {{ $optimisticConfig ? 'run' : 'selectDate' }}(day.date)"
                                                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay ? '0' : '-1'"
                                :disabled="!day.isCurrentMonth"
                                class="{{ $dayBtnClasses }}"
                                {{-- The in-between days get a TINT, not the accent, so the two ends
                                     stay what the eye lands on. --}}
                                x-on:mouseenter="hoverDay(day.date)"
                                :data-wk-in-range="day.rangeMarker"
                                :class="{
                                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]': day.isSelected,
                                    'font-[number:var(--font-wk-heading-weight)] ring-1 ring-[var(--color-wk-accent)]': day.isToday && !day.isSelected,
                                    'cursor-pointer hover:bg-[var(--color-wk-bg-subtle)]': day.isCurrentMonth && !day.isSelected && !day.isInRange && !day.isProvisionalEnd,
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

    @if($optimisticConfig)
        {{-- Outside the grid — a live region is not a gridcell — and inside the
             optimistic scope. Rendered unconditionally and starting empty: a
             region that arrives together with its text is a new node, and
             nothing is announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
        </div>
    @endif
</div>
