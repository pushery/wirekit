@props([
    'events' => [],                 // [{id,title,start,end?,allDay?,intent?}]
    'dayMarkers' => [],             // [{date,label,type?,blocked?}] day-level holiday/working/note markers
    'view' => config('wirekit.components.event-calendar.view', 'month'), // month | week | agenda
    'date' => null,                 // ISO date the calendar opens on (default today)
    'weekStartsOn' => config('wirekit.components.event-calendar.week-starts-on', 1), // 0 Sun .. 1 Mon
    'ariaLabel' => 'Calendar',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    $view = WireKit::validateProp('event-calendar', 'view', $view, ['month', 'week', 'agenda']);
    $id = $attributes->get('id', 'event-calendar-'.Str::random(6));

    $eventsArr = $events instanceof \Illuminate\Support\Collection ? $events->values()->all() : array_values((array) $events);
    $markersArr = $dayMarkers instanceof \Illuminate\Support\Collection ? $dayMarkers->values()->all() : array_values((array) $dayMarkers);

    // Event intent → block classes (tinted surface + intent left-stripe). Defined
    // here (PHP literals) so Tailwind compiles them AND the drift inventory traces
    // them; the block binds `:class="eventClasses[ev.intent || 'accent']"`. There
    // is no --color-wk-info base token, so info maps onto accent.
    // Stripe color = the intent's *-text token, NOT the base color: a base-color
    // stripe sits on a 16% tint of the SAME hue, so its edge nearly vanishes
    // (its edge reads as borderless) — while accent's near-black stripe popped.
    // The *-text tokens are the AA-on-tint pairings, so the stripe separates on
    // every intent equally, in both themes.
    $eventClasses = [
        'accent' => 'bg-[color-mix(in_oklch,var(--color-wk-accent)_16%,var(--color-wk-bg))] text-[color:var(--color-wk-text)] border-l-2 border-[var(--color-wk-accent)]',
        'info' => 'bg-[color-mix(in_oklch,var(--color-wk-accent)_16%,var(--color-wk-bg))] text-[color:var(--color-wk-text)] border-l-2 border-[var(--color-wk-accent)]',
        'success' => 'bg-[color-mix(in_oklch,var(--color-wk-success)_16%,var(--color-wk-bg))] text-[color:var(--color-wk-success-text)] border-l-2 border-[var(--color-wk-success-text)]',
        'warning' => 'bg-[color-mix(in_oklch,var(--color-wk-warning)_16%,var(--color-wk-bg))] text-[color:var(--color-wk-warning-text)] border-l-2 border-[var(--color-wk-warning-text)]',
        'danger' => 'bg-[color-mix(in_oklch,var(--color-wk-danger)_16%,var(--color-wk-bg))] text-[color:var(--color-wk-danger-text)] border-l-2 border-[var(--color-wk-danger-text)]',
        'neutral' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] border-l-2 border-[var(--color-wk-border)]',
    ];

    // Solid intent fills for the small agenda status-dot. The $eventClasses
    // BLOCK style above carries a `border-l-2` left stripe — applied to an 8px
    // circle it ate half the dot (it read as "cut off"). The dot needs a plain
    // solid fill of the intent color instead. info → accent (no info token).
    $eventDot = [
        'accent' => 'bg-[var(--color-wk-accent)]',
        'info' => 'bg-[var(--color-wk-accent)]',
        'success' => 'bg-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)]',
        'neutral' => 'bg-[var(--color-wk-border)]',
    ];

    // Day-marker band classes by type — a tinted surface distinct from event
    // pills so a holiday/working/note day reads as day-level context, not an
    // event. holiday = danger tint (the established "non-working day" red),
    // working = warning (an exception working day), note = neutral. The JS
    // clamps an unknown type to 'holiday', so the lookup always resolves.
    $markerClasses = [
        'holiday' => 'bg-[color-mix(in_oklch,var(--color-wk-danger)_14%,transparent)] text-[color:var(--color-wk-danger-text)]',
        'working' => 'bg-[color-mix(in_oklch,var(--color-wk-warning)_14%,transparent)] text-[color:var(--color-wk-warning-text)]',
        'note' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text-muted)]',
    ];
    // A `blocked` (unavailable) day overrides the type tint with a muted surface
    // + the diagonal hatch (.wk-day-blocked in dist/wirekit.css). The unavailable
    // state is ALSO surfaced in the accessible name (sr-only) — never hatch-only.
    $markerBlocked = 'wk-day-blocked bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text-muted)]';
    // Blocked markers paint the diagonal hatch ACROSS the band — readable stripes,
    // unreadable text on top of them (the label is hard to read over the hatch). The
    // label (and the agenda type-suffix) therefore sits on a small solid chip that
    // hugs the TEXT, so the hatch stays visible around it as the affordance.
    $markerChip = 'rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-xs)]';

    $base = WireKit::resolveClasses('event-calendar', 'base', 'w-full font-[family-name:var(--font-wk-sans)] space-y-[var(--space-wk-sm)]', $scope);

    $navBtn = 'inline-flex items-center justify-center h-[var(--size-wk-sm)] w-[var(--size-wk-sm)] rounded-[var(--radius-wk-md)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer transition-colors';
    $viewTab = 'px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)] cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset transition-colors';
@endphp

<div
    {{ $attributes->except(['id', 'class']) }}
    id="{{ $id }}"
    x-data="wirekitEventCalendar({ events: @js($eventsArr), dayMarkers: @js($markersArr), view: '{{ $view }}', @if($date) date: '{{ $date }}', @endif weekStartsOn: {{ (int) $weekStartsOn }} })"
    role="group"
    aria-label="{{ $ariaLabel }}"
    {{-- Delegated truncated-title tooltip: every [data-wk-tip] pill/chip/row shares
         ONE bubble (x-ref="tip" below). Root-level delegation = zero per-item
         listeners; scroll.capture hides it the moment the week grid scrolls (the
         fixed-position bubble would otherwise drift off its anchor). --}}
    @pointerover="tipShow($event)"
    @pointerout="tipHide($event)"
    @focusin="tipShow($event)"
    @focusout="tipHide($event)"
    @scroll.capture="tipHide()"
    @keydown.escape.window="tipHide()"
    {{ $attributes->only('class')->class([$base]) }}
>
    {{-- Header: navigation + title + view switcher --}}
    <div class="flex flex-wrap items-center justify-between gap-[var(--space-wk-sm)]">
        <div class="flex items-center gap-[var(--space-wk-sm)]">
            <div class="inline-flex items-center gap-1">
                <button type="button" @click="prev()" aria-label="Previous" class="{{ $navBtn }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 12L6 8l4-4"/></svg></button>
                <button type="button" @click="today()" class="px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-md)] hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer">Today</button>
                <button type="button" @click="next()" aria-label="Next" class="{{ $navBtn }}"><svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 4l4 4-4 4"/></svg></button>
            </div>
            <h2 class="text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]" aria-live="polite" x-text="title"></h2>
        </div>
        {{-- View switcher — a single-select RADIOGROUP (not tabs: the buttons own
             no tabpanels; the views swap in place). aria-checked + roving tabindex
             + arrows move-and-select via viewMove(), wrapping at the ends. --}}
        <div class="inline-flex rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] overflow-hidden" role="radiogroup" aria-label="Calendar view"
            @keydown.arrow-right.prevent="viewMove(1)"
            @keydown.arrow-down.prevent="viewMove(1)"
            @keydown.arrow-left.prevent="viewMove(-1)"
            @keydown.arrow-up.prevent="viewMove(-1)">
            <button type="button" role="radio" data-view="month" @click="setView('month')" :aria-checked="view === 'month'" :tabindex="view === 'month' ? 0 : -1" :class="view === 'month' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="{{ $viewTab }}">Month</button>
            <button type="button" role="radio" data-view="week" @click="setView('week')" :aria-checked="view === 'week'" :tabindex="view === 'week' ? 0 : -1" :class="view === 'week' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="{{ $viewTab }}">Week</button>
            <button type="button" role="radio" data-view="agenda" @click="setView('agenda')" :aria-checked="view === 'agenda'" :tabindex="view === 'agenda' ? 0 : -1" :class="view === 'agenda' ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-muted)]'" class="{{ $viewTab }}">Agenda</button>
        </div>
    </div>

    {{-- ── Month view ──────────────────────────────────────────────── --}}
    <div x-show="view === 'month'" class="border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] overflow-hidden">
        <div class="grid grid-cols-7 bg-[var(--color-wk-bg-elevated)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
            <template x-for="wd in weekdayLabels" :key="wd">
                <div class="px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)] text-center" x-text="wd"></div>
            </template>
        </div>
        <template x-for="(week, wi) in monthWeeks" :key="wi">
            <div class="grid grid-cols-7">
                <template x-for="day in week" :key="day.date.toISOString()">
                    <div class="min-h-[5.5rem] p-1 border-b-[length:var(--border-wk-width)] border-r-[length:var(--border-wk-width)] border-[var(--color-wk-border)]" :class="day.inMonth ? 'bg-[var(--color-wk-bg)]' : 'bg-[var(--color-wk-bg-subtle)]'">
                        <div class="flex justify-end">
                            <span class="inline-flex items-center justify-center h-5 min-w-5 px-1 text-[length:var(--text-wk-xs)] rounded-[var(--radius-wk-full)]" :class="day.isToday ? 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-text-inverse)] font-[number:var(--font-wk-heading-weight)]' : (day.inMonth ? 'text-[color:var(--color-wk-text)]' : 'text-[color:var(--color-wk-text-subtle)]')" x-text="day.label"></span>
                        </div>
                        {{-- Day-marker band: full-width label at the top of the cell, above
                             the event pills. blocked → muted + hatch + sr-only "unavailable". --}}
                        <template x-for="(m, mi) in day.markers" :key="day.date.toISOString()+'-m'+mi">
                            <div :class="m.blocked ? @js($markerBlocked) : @js($markerClasses)[m.type]" :data-wk-tip="m.label" class="mt-0.5 block w-full truncate rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)]"><span :class="m.blocked ? @js($markerChip) : ''" x-text="m.label"></span><span x-show="m.blocked" class="sr-only"> (unavailable)</span></div>
                        </template>
                        <div class="mt-0.5 space-y-0.5">
                            <template x-for="ev in day.visibleEvents" :key="ev.id">
                                <button type="button" @click="selectEvent(ev)" :aria-label="eventLabel(ev)" :data-wk-tip="ev.title" :class="@js($eventClasses)[ev.intent || 'accent']" class="block w-full truncate text-left px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] rounded-[var(--radius-wk-sm)] text-[length:var(--text-wk-xs)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer" x-text="ev.title"></button>
                            </template>
                            {{-- "+N more" is actionable: it jumps to the week view focused on
                                 that day so the hidden events become visible (showMore). A plain
                                 span gave no affordance — the overflow count read as dead text. --}}
                            <button type="button" x-show="day.overflow > 0" x-cloak @click="showMore(day.date)" :aria-label="day.overflow + ' more events on ' + day.date.toLocaleDateString(undefined, { weekday: 'long', month: 'long', day: 'numeric' }) + ', open week view'" class="block w-full text-left px-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"><span x-text="day.overflow"></span> more</button>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- ── Week view (time grid) ───────────────────────────────────── --}}
    <div x-show="view === 'week'" x-cloak role="region" aria-label="Week schedule" tabindex="0" class="max-h-[30rem] overflow-y-auto wk-scrollbar border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
        {{-- Sticky top region: day-name headers + the all-day band. Both pin to
             the top of the scroll region so they stay visible while the hour grid
             scrolls underneath. --}}
        <div class="sticky top-0 z-[var(--z-wk-sticky)] bg-[var(--color-wk-bg-elevated)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
            {{-- Day-name headers --}}
            <div class="grid grid-cols-[3rem_repeat(7,minmax(0,1fr))]">
                <div></div>
                <template x-for="day in weekDays" :key="day.date.toISOString()">
                    <div class="px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] text-center border-l-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                        <div class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="day.weekday"></div>
                        <div class="text-[length:var(--text-wk-sm)]" :class="day.isToday ? 'text-[color:var(--color-wk-accent)] font-[number:var(--font-wk-heading-weight)]' : 'text-[color:var(--color-wk-text)]'" x-text="day.label"></div>
                    </div>
                </template>
            </div>
            {{-- All-day band: events flagged allDay:true were previously dropped from
                 week view (the _layoutDay overlap engine filters them out, having no
                 hour position). They render here as full-width chips per day. The band
                 is hidden when the focused week has no all-day events. --}}
            <div x-show="weekHasAllDay || weekHasMarkers" x-cloak class="grid grid-cols-[3rem_repeat(7,minmax(0,1fr))] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                {{-- The band shows for all-day events OR day-markers. The "All day"
                     axis label only applies to the former — when the band is present
                     purely for markers, the label would mislabel the row, so it blanks. --}}
                <div class="px-1 py-[var(--padding-wk-y-xs)] text-right text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)]" x-text="weekHasAllDay ? 'All day' : ''"></div>
                <template x-for="day in weekDays" :key="'ad-'+day.date.toISOString()">
                    <div class="min-h-[1.75rem] p-0.5 space-y-0.5 border-l-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                        {{-- Day markers first (day-level context), then all-day events. --}}
                        <template x-for="(m, mi) in day.markers" :key="'m-'+day.date.toISOString()+mi">
                            <div :class="m.blocked ? @js($markerBlocked) : @js($markerClasses)[m.type]" :data-wk-tip="m.label" class="block w-full truncate rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)]"><span :class="m.blocked ? @js($markerChip) : ''" x-text="m.label"></span><span x-show="m.blocked" class="sr-only"> (unavailable)</span></div>
                        </template>
                        <template x-for="ev in day.allDay" :key="ev.id">
                            <button type="button" @click="selectEvent(ev)" :aria-label="eventLabel(ev)" :data-wk-tip="ev.title" :class="@js($eventClasses)[ev.intent || 'accent']" class="block w-full truncate text-left px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] rounded-[var(--radius-wk-sm)] text-[length:var(--text-wk-xs)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer" x-text="ev.title"></button>
                        </template>
                    </div>
                </template>
            </div>
        </div>
        {{-- Time grid: 3rem axis + 7 day columns; each hour row 2.5rem (60rem total).
             The first grid row is an 8px BREATHING STRIP (replacing an earlier
             container pt-2): the hour labels are centered on their gridlines, which
             lifts the first ("12 AM") label half a row up — without this room it
             would hide behind the sticky day-header. As a real grid ROW (not
             container padding) the strip is tinted muted AND carries the per-column
             border-l, so the vertical column lines run CONTINUOUSLY from the sticky
             header into the hour grid — container padding has no children, so the
             lines visibly broke there (the vertical column lines were
             discontinuous in light gray). Its border-b draws the 00:00 gridline the
             "12 AM" label sits on, matching every other hour label. --}}
        <div class="grid grid-cols-[3rem_repeat(7,minmax(0,1fr))]">
            <div class="h-2 bg-[var(--color-wk-bg-muted)]" aria-hidden="true"></div>
            @for ($i = 0; $i < 7; $i++)
                <div class="h-2 bg-[var(--color-wk-bg-muted)] border-l-[length:var(--border-wk-width)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]" aria-hidden="true"></div>
            @endfor
            {{-- Hour axis --}}
            <div>
                <template x-for="h in hours" :key="h">
                    {{-- flex items-center centers the label in its 2.5rem row, and
                         -translate-y-1/2 lifts it by exactly half a row so its
                         vertical center lands ON the hour gridline (robust to the
                         text's line-height, unlike a fixed -translate-y-2). The
                         breathing-strip row above keeps the first ("12 AM") label
                         fully visible below the sticky day-header. --}}
                    <div class="h-[2.5rem] flex items-center justify-end pr-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)] -translate-y-1/2" x-text="hourLabel(h)"></div>
                </template>
            </div>
            {{-- Day columns --}}
            <template x-for="day in weekDays" :key="'col-'+day.date.toISOString()">
                <div class="relative border-l-[length:var(--border-wk-width)] border-[var(--color-wk-border)] h-[60rem]">
                    <template x-for="h in hours" :key="'g-'+h">
                        <div class="h-[2.5rem] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]"></div>
                    </template>
                    {{-- Current-time line --}}
                    <div x-show="day.isToday" x-cloak aria-hidden="true" class="absolute left-0 right-0 h-px bg-[var(--color-wk-danger)] z-10" :style="'top: ' + nowLineTop + '%'"></div>
                    {{-- Event blocks --}}
                    <template x-for="b in day.blocks" :key="b.event.id">
                        {{-- min-h floor guarantees a block is tall enough for title+time
                             even for sub-30-min events, which previously clipped under
                             overflow-hidden (#5/#6). Height/floor history: a full-2.5rem
                             floor made a 1-hour block snap to the full row height (4px too
                             tall); 2.25rem (row − 4px gutter) fixed most of it but left the
                             1-hour block 1px too tall at the bottom — the `% height` calc
                             rounds UP ~1px at this scale and the 2.25rem floor (== the 1-hour
                             calc) pinned it there (the block was 1px too tall). So the
                             vertical gutter is now 5px on height with the floor lowered to
                             2.1875rem (35px): a 1-hour block lands 1px shorter and the bottom
                             inset matches the +2px top. Horizontal gutter stays 2px/-4px
                             (left/right fit). The clipped line is the time (no
                             descenders), so overflow-hidden costs nothing visible. No
                             vertical gutter at all made back-to-back events touch
                             edge-to-edge (back-to-back events visually merged). NOTE the event
                             background ($eventClasses) is now an OPAQUE color-mix over
                             var(--color-wk-bg), not transparent, so the hour gridlines no
                             longer show THROUGH the block (events no longer bleed over the gridlines). --}}
                        <button type="button" @click="selectEvent(b.event)" :aria-label="eventLabel(b.event)" :data-wk-tip="b.event.title" :class="@js($eventClasses)[b.event.intent || 'accent']" :style="'top:calc('+b.top+'% + 2px); height:calc('+b.height+'% - 5px); left:calc('+b.left+'% + 2px); width:calc('+b.width+'% - 4px)'" class="absolute overflow-hidden min-h-[2.1875rem] rounded-[var(--radius-wk-sm)] px-[var(--padding-wk-x-xs)] py-[var(--padding-wk-y-xs)] text-left text-[length:var(--text-wk-xs)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer">
                            <span class="block font-[number:var(--font-wk-heading-weight)] leading-[var(--leading-wk-tight)] truncate" x-text="b.event.title"></span>
                            {{-- Secondary line: a smaller (2xs) tight time so the title
                                 leads and the two lines sit close in the compact block. --}}
                            <span class="block text-[length:var(--text-wk-2xs)] leading-[var(--leading-wk-tight)] truncate" x-text="b.timeLabel"></span>
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </div>

    {{-- ── Agenda view ─────────────────────────────────────────────── --}}
    <div x-show="view === 'agenda'" x-cloak aria-label="Agenda" x-effect="view; agendaDays; $nextTick(() => _measureAgendaTime())" class="relative overflow-hidden border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] divide-y divide-[var(--color-wk-border)]">
        {{-- No vertical spine: each day's events stack cleanly under their day
             heading, so a continuous rule between the time column and the titles
             read as visual noise. The time column is still measured
             (_measureAgendaTime → --wk-agenda-time, via the x-effect above) so every
             row's time shares one width and the titles line up. --}}
        <template x-for="day in agendaDays" :key="day.date.toISOString()">
            <div class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]">
                <p class="mb-1 text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)]" :class="day.isToday ? 'text-[color:var(--color-wk-accent)]' : 'text-[color:var(--color-wk-text)]'" x-text="day.label"></p>
                {{-- Day markers as their own labeled line (R2's "Holiday: …" pattern).
                     The type is shown as text; blocked adds an sr-only "unavailable". --}}
                <template x-for="(m, mi) in day.markers" :key="'am-'+day.date.toISOString()+mi">
                    <div :class="m.blocked ? @js($markerBlocked) : @js($markerClasses)[m.type]" :data-wk-tip="m.label" class="mb-1 flex items-center gap-[var(--space-wk-sm)] rounded-[var(--radius-wk-md)] px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)]">
                        <span class="min-w-0 flex-1 truncate"><span :class="m.blocked ? @js($markerChip) : ''" x-text="m.label"></span></span>
                        <span class="shrink-0 text-[length:var(--text-wk-xs)]" :class="m.blocked ? @js($markerChip) : ''" x-text="'(' + m.type + ')'"></span>
                        <span x-show="m.blocked" class="sr-only">unavailable</span>
                    </div>
                </template>
                <div class="space-y-1">
                    <template x-for="ev in day.events" :key="ev.id">
                        <button type="button" @click="selectEvent(ev)" :aria-label="eventLabel(ev)" :data-wk-tip="ev.title" class="w-full flex items-center gap-[var(--space-wk-sm)] px-[var(--padding-wk-x-sm)] py-1 rounded-[var(--radius-wk-md)] text-left hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer">
                            <span class="shrink-0 w-2 h-2 rounded-full" :class="@js($eventDot)[ev.intent || 'accent']"></span>
                            {{-- Time column: right-aligned + tabular-nums so the colon and
                                 the AM/PM stack into vertical columns. Width is SHARED and
                                 dynamic — _measureAgendaTime() measures the inner
                                 data-agenda-time span (natural inline width) of every row
                                 and sets --wk-agenda-time to the widest, so every row's
                                 time shares one width and the titles align (5rem =
                                 first-paint fallback). --}}
                            <span class="shrink-0 text-right text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] tabular-nums" style="width: var(--wk-agenda-time, 5rem);"><span data-agenda-time class="whitespace-nowrap" x-text="ev.timeLabel"></span></span>
                            {{-- pl gives the title a small, even gap from the time column. --}}
                            <span class="min-w-0 flex-1 pl-[var(--space-wk-sm)] truncate text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]" x-text="ev.title"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>
        <div x-show="agendaEmpty" x-cloak class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">No events in this range</div>
    </div>

    {{-- Shared truncated-title tooltip — ONE bubble for every [data-wk-tip] pill /
         chip / row / marker, shown on hover/focus only when the text is actually
         truncated (tipShow checks scrollWidth). The class set mirrors
         <x-wirekit::tooltip>'s panel verbatim so it IS the house tooltip visually;
         the Blade component itself can't wrap client-side x-for nodes. aria-hidden:
         every target's aria-label already carries the full text, so a described-by
         bubble would double-announce. --}}
    <div x-ref="tip" x-show="tipOpen" x-cloak aria-hidden="true" x-text="tipText" class="fixed w-max z-[var(--z-wk-tooltip)] max-w-[var(--size-wk-tooltip-max)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] bg-[var(--color-wk-tooltip-bg)] text-[color:var(--color-wk-tooltip-text)] text-[length:var(--text-wk-sm)] font-[family-name:var(--font-wk-sans)] rounded-[var(--radius-wk-sm)] shadow-[var(--shadow-wk-md)] pointer-events-none"></div>
</div>
