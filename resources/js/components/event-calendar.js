/**
 * WireKit Event Calendar Alpine component.
 *
 * A read-focused scheduling calendar with three views — month (a 7xN day grid
 * with event pills + "+N more" overflow), week (an hour-row time grid with
 * absolutely-positioned, overlap-split event blocks + a current-time line), and
 * agenda (a chronological list grouped by day). Navigation (prev / next / today)
 * and view switching recompute the visible window. Clicking an event emits an
 * `event-click` event; the component never mutates events itself.
 *
 * All date math uses the native Date API — no external dependency. Recurrence
 * (RRULE), drag-editing, the resource view, timezone/DST handling, and ICS
 * export are intentionally out of scope for this build.
 *
 * Lifecycle resources held on `this`:
 *   - _clock (setInterval) — refreshes the current-time line each minute in
 *     week view; cleared in destroy(). The callback null-guards `_clock`.
 *
 * @param {Object} config
 * @param {Array}  config.events - [{id,title,start,end,allDay?,intent?}]
 * @param {Array}  config.dayMarkers - [{date,label,type?,blocked?}] day-level markers
 * @param {string} config.view   - 'month' | 'week' | 'agenda'
 * @param {string} config.date   - ISO date the calendar opens on
 * @param {number} config.weekStartsOn - 0 (Sun) .. 1 (Mon, default)
 * @param {string} [config.allDayLabel] - what an all-day event is CALLED, translated.
 *   The English fallback is for a developer who mounts the factory by hand; the
 *   component passes the catalog string.
 * @param {string} [config.locale] - BCP-47 tag for every date and time this
 *   component prints. Supplied by the component from the application locale.
 */
import { position } from '../utils/floating.js';

export default function wirekitEventCalendar(config = {}) {
    // The APPLICATION's locale, not the browser's — the component receives it
    // from Blade, the way calendar and countdown do. `undefined` is the
    // deliberate fallback and not a placeholder: it is what `Intl` reads as
    // "use the browser's own preference", so a calendar mounted by hand
    // without the config still names its months in a language the reader
    // chose rather than in the one this file happens to be written in.
    //
    // Every other word on this calendar comes out of the translation catalog,
    // so leaving these to the browser made one page two languages: German
    // chrome — "Heute", "Kalenderansicht" — over an English month heading and
    // English weekday columns, for a reader whose laptop happens to be set to
    // English. Nothing was wrong with the page; it was the same page in two
    // languages at once, and no prop could correct it.
    const locale = config.locale || undefined;

    // Built once per instance rather than per read. Every format below depends
    // only on the locale, which does not change for the life of the component —
    // while `weekdayLabels`, `weekDays` and `monthWeeks` are re-read on every
    // keystroke that moves focus, so a formatter constructed inside one of them
    // is constructed again on each of those keystrokes.
    const monthYearFormat = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric' });
    const rangeStartFormat = new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric' });
    const rangeEndFormat = new Intl.DateTimeFormat(locale, { month: 'short', day: 'numeric', year: 'numeric' });
    const weekdayShortFormat = new Intl.DateTimeFormat(locale, { weekday: 'short' });
    const hourFormat = new Intl.DateTimeFormat(locale, { hour: 'numeric' });
    const timeFormat = new Intl.DateTimeFormat(locale, { hour: 'numeric', minute: '2-digit' });
    const agendaDayFormat = new Intl.DateTimeFormat(locale, { weekday: 'long', month: 'short', day: 'numeric' });
    const fullDateFormat = new Intl.DateTimeFormat(locale, { weekday: 'long', month: 'long', day: 'numeric' });

    return {
        events: Array.isArray(config.events) ? config.events.map((e) => ({ ...e })) : [],
        // Day-level markers (holidays / working days / notes) — a SEPARATE dimension
        // from timed events. `date` is parsed date-only at LOCAL midnight (slice to
        // 10 chars + 'T00:00:00') so a YYYY-MM-DD string never drifts a day across a
        // timezone boundary. `type` is clamped to the three known kinds; `blocked`
        // is a hard boolean. WireKit owns the visual/semantic state only — the host
        // enforces any booking logic behind a `blocked` day.
        dayMarkers: Array.isArray(config.dayMarkers) ? config.dayMarkers.map((m) => ({
            label: m.label || '',
            type: ['holiday', 'working', 'note'].includes(m.type) ? m.type : 'holiday',
            blocked: !!m.blocked,
            _date: new Date(String(m.date).slice(0, 10) + 'T00:00:00'),
        })) : [],
        view: config.view || 'month',
        weekStartsOn: Number.isInteger(config.weekStartsOn) ? config.weekStartsOn : 1,
        focusedDate: config.date ? new Date(config.date) : new Date(),
        now: new Date(),
        _clock: null,

        // What an all-day event is CALLED. The agenda row prints it and `eventLabel`
        // puts it in every event's accessible name, so a literal here is a word the
        // catalog cannot reach: the week view's own axis label goes through `__()` two
        // files over, and a German app would read "Ganztägig" above a list of events
        // announced as "All day". A blank catalog entry — which happens while a
        // language is being translated — falls back to the English rather than to an
        // empty string, because a time slot with no word at all reads as a missing value.
        _allDayLabel: config.allDayLabel || 'All day',

        init() {
            // The current-time line only matters in week view; refresh each minute.
            this._clock = setInterval(() => {
                if (!this._clock) return; // post-destroy guard
                this.now = new Date();
            }, 60000);
        },
        destroy() {
            if (this._clock) {
                clearInterval(this._clock);
                this._clock = null;
            }
        },

        // ── Date helpers ─────────────────────────────────────────────────
        _startOfDay(d) {
            const x = new Date(d);
            x.setHours(0, 0, 0, 0);
            return x;
        },
        _sameDay(a, b) {
            return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        },
        _addDays(d, n) {
            const x = new Date(d);
            x.setDate(x.getDate() + n);
            return x;
        },
        _startOfWeek(d) {
            const x = this._startOfDay(d);
            const diff = (x.getDay() - this.weekStartsOn + 7) % 7;
            return this._addDays(x, -diff);
        },
        _eventStart(e) {
            return new Date(e.start);
        },
        _eventEnd(e) {
            return e.end ? new Date(e.end) : new Date(new Date(e.start).getTime() + 3600000);
        },
        _eventsOnDay(day) {
            return this.events
                .filter((e) => this._sameDay(this._eventStart(e), day))
                .sort((a, b) => this._eventStart(a) - this._eventStart(b));
        },

        // ── Title + navigation ───────────────────────────────────────────
        get title() {
            // Week + agenda show the visible RANGE (not just the month) so the
            // header never lies about which days are on screen. Month shows the
            // month + year. The end side ALWAYS formats month+day+year: a
            // day+year-only option set is an invalid ICU combination that real
            // browsers render as fallback garbage ("2026 (day: 14)") even though
            // Node's ICU happens to produce something sane. Only a real browser
            // shows it, which is why the header formatting is asserted there.
            if (this.view === 'month') {
                return monthYearFormat.format(this.focusedDate);
            }
            const start = this.view === 'week' ? this._startOfWeek(this.focusedDate) : this._startOfDay(this.focusedDate);
            const span = this.view === 'week' ? 6 : 13; // week = 7 days, agenda = 14
            const end = this._addDays(start, span);
            const startStr = rangeStartFormat.format(start);
            const endStr = rangeEndFormat.format(end);
            return `${startStr} – ${endStr}`;
        },
        setView(v) {
            this.view = v;
            this.$dispatch('view-change', { view: v });
        },
        // Radio-group keyboard model for the view switcher: arrows move AND
        // select (selection follows focus, per the ARIA radio pattern),
        // wrapping at the ends. Focus lands on the newly active radio.
        viewMove(dir) {
            const order = ['month', 'week', 'agenda'];
            const i = Math.max(0, order.indexOf(this.view));
            const next = order[(i + dir + order.length) % order.length];
            this.setView(next);
            this.$nextTick(() => {
                const radio = this.$root && this.$root.querySelector(`[data-view="${next}"]`);
                if (radio) radio.focus();
            });
        },
        // Agenda time-column auto-width: measure the widest RENDERED time label
        // and expose it as --wk-agenda-time on the root. A fixed 5rem column left
        // a large gap before short locale times ("9:00") and would clip long ones
        // ("12:30 PM"); the timeline spine and every row consume the same var, so
        // the vertical line stays straight AND hugs the actual content width
        // Only measures while the agenda view is visible — hidden
        // elements measure 0 and would collapse the column.
        /**
         * Re-measure the agenda's time column when the view or its days change.
         *
         * Bound to `x-effect`. The template used to read both dependencies and
         * then defer the measurement — `view; agendaDays; $nextTick(() => …)` —
         * which is three statements and an arrow function, none of which the CSP
         * build parses. The reads have to stay, and stay first: an effect tracks
         * only what it touches, and the deferral means the measurement itself
         * runs after the DOM has caught up rather than against the old one.
         */
        measureAgendaOnChange() {
            const tracked = [this.view, this.agendaDays];

            this.$nextTick(() => this._measureAgendaTime());

            return tracked.length;
        },

        _measureAgendaTime() {
            if (this.view !== 'agenda' || !this.$root || typeof this.$root.querySelectorAll !== 'function') {
                return;
            }
            const labels = Array.from(this.$root.querySelectorAll('[data-agenda-time]'));
            const max = labels.reduce((m, el) => Math.max(m, el.getBoundingClientRect().width), 0);
            if (max > 0) {
                this.$root.style.setProperty('--wk-agenda-time', `${Math.ceil(max)}px`);
            } else {
                // No event rows (marker-only / empty agenda) — fall back to the
                // stylesheet default rather than pinning a stale or zero width.
                this.$root.style.removeProperty('--wk-agenda-time');
            }
        },
        today() {
            this.focusedDate = new Date();
        },
        prev() {
            this.focusedDate = this.view === 'week'
                ? this._addDays(this.focusedDate, -7)
                : this.view === 'agenda'
                    ? this._addDays(this.focusedDate, -14)
                    : new Date(this.focusedDate.getFullYear(), this.focusedDate.getMonth() - 1, 1);
        },
        next() {
            this.focusedDate = this.view === 'week'
                ? this._addDays(this.focusedDate, 7)
                : this.view === 'agenda'
                    ? this._addDays(this.focusedDate, 14)
                    : new Date(this.focusedDate.getFullYear(), this.focusedDate.getMonth() + 1, 1);
        },

        // ── Month grid ───────────────────────────────────────────────────
        get monthWeeks() {
            const first = new Date(this.focusedDate.getFullYear(), this.focusedDate.getMonth(), 1);
            const gridStart = this._startOfWeek(first);
            const weeks = [];
            let cursor = gridStart;
            for (let w = 0; w < 6; w += 1) {
                const days = [];
                for (let d = 0; d < 7; d += 1) {
                    const dayEvents = this._eventsOnDay(cursor);
                    days.push({
                        date: cursor,
                        label: cursor.getDate(),
                        inMonth: cursor.getMonth() === this.focusedDate.getMonth(),
                        isToday: this._sameDay(cursor, this.now),
                        events: dayEvents,
                        visibleEvents: dayEvents.slice(0, 3),
                        overflow: Math.max(0, dayEvents.length - 3),
                        markers: this._markersFor(cursor),
                    });
                    cursor = this._addDays(cursor, 1);
                }
                weeks.push(days);
                // Stop after the week that completes the month (5 or 6 rows).
                if (cursor.getMonth() !== this.focusedDate.getMonth() && w >= 3) break;
            }
            return weeks;
        },
        get weekdayLabels() {
            const base = this._startOfWeek(new Date());
            return Array.from({ length: 7 }, (_, i) => weekdayShortFormat.format(this._addDays(base, i)));
        },

        // ── Week time grid ───────────────────────────────────────────────
        get weekDays() {
            const start = this._startOfWeek(this.focusedDate);
            return Array.from({ length: 7 }, (_, i) => {
                const date = this._addDays(start, i);
                return {
                    date,
                    weekday: weekdayShortFormat.format(date),
                    label: date.getDate(),
                    isToday: this._sameDay(date, this.now),
                    blocks: this._layoutDay(date),
                    allDay: this._allDayFor(date),
                    markers: this._markersFor(date),
                };
            });
        },
        // Day markers (holiday / working / note) falling on a given day.
        _markersFor(day) {
            return this.dayMarkers.filter((m) => this._sameDay(m._date, day));
        },
        // True when ANY day in the focused week carries a marker — markers share the
        // all-day band, so the band must also open for a marker-only week.
        get weekHasMarkers() {
            const start = this._startOfWeek(this.focusedDate);
            for (let i = 0; i < 7; i += 1) {
                if (this._markersFor(this._addDays(start, i)).length > 0) return true;
            }
            return false;
        },
        // All-day events for a day, sorted by start. Kept SEPARATE from the timed
        // _layoutDay (which filters !allDay): all-day events have no hour position,
        // so they render in the dedicated all-day band, not the hour grid. Before
        // this band existed they were silently dropped from week view entirely.
        _allDayFor(day) {
            return this.events
                .filter((e) => e.allDay && this._sameDay(this._eventStart(e), day))
                .sort((a, b) => this._eventStart(a) - this._eventStart(b));
        },
        // True when ANY day in the focused week carries an all-day event — gates the
        // band so a week with none doesn't render an empty row. Computed directly
        // (not via weekDays) to avoid re-running the per-day overlap layout.
        get weekHasAllDay() {
            const start = this._startOfWeek(this.focusedDate);
            for (let i = 0; i < 7; i += 1) {
                if (this._allDayFor(this._addDays(start, i)).length > 0) return true;
            }
            return false;
        },
        hours: Array.from({ length: 24 }, (_, h) => h),
        hourLabel(h) {
            const d = new Date();
            d.setHours(h, 0, 0, 0);
            return hourFormat.format(d);
        },
        // Overlap-split layout: greedily assign each day's timed events to the
        // first free column, then size every block to 1/columns width.
        //
        // Collision uses an EFFECTIVE end — max(actual end, start + MIN_BLOCK_MIN) —
        // not the raw end. Reason: the blade floors every block to min-h-[2.5rem]
        // (= MIN_BLOCK_MIN of grid height) so a sub-hour event is still tall enough
        // for its title+time. Without the effective-end floor, a 15-min event and
        // the event immediately after it land in the SAME column (their real times
        // don't overlap), but the floored 15-min block renders ~1h tall and visually
        // collides with the next block's text. Treating each block as occupying its
        // rendered footprint pushes such pairs into side-by-side columns instead.
        _layoutDay(day) {
            const MIN_BLOCK_MIN = 60; // matches min-h-[2.5rem] (60rem grid / 24h = 2.5rem/h)
            const timed = this._eventsOnDay(day).filter((e) => !e.allDay);
            const cols = []; // each entry = the EFFECTIVE end occupying that column
            const placed = timed.map((e) => {
                const s = this._eventStart(e);
                const en = this._eventEnd(e);
                const effEnd = new Date(Math.max(en.getTime(), s.getTime() + MIN_BLOCK_MIN * 60000));
                let col = cols.findIndex((endTime) => endTime <= s);
                if (col === -1) {
                    col = cols.length;
                    cols.push(effEnd);
                } else {
                    cols[col] = effEnd;
                }
                return { event: e, start: s, end: en, col };
            });
            const colCount = Math.max(1, cols.length);
            return placed.map((p) => {
                const topMin = p.start.getHours() * 60 + p.start.getMinutes();
                const endMin = p.end.getHours() * 60 + p.end.getMinutes();
                const durMin = Math.max(30, endMin - topMin);
                return {
                    event: p.event,
                    top: (topMin / 1440) * 100,
                    height: (durMin / 1440) * 100,
                    left: (p.col / colCount) * 100,
                    width: (1 / colCount) * 100,
                    timeLabel: timeFormat.format(p.start),
                };
            });
        },
        get nowLineTop() {
            return ((this.now.getHours() * 60 + this.now.getMinutes()) / 1440) * 100;
        },
        nowInWeek() {
            return this.weekDays.some((d) => d.isToday);
        },

        // ── Agenda ───────────────────────────────────────────────────────
        get agendaDays() {
            const start = this._startOfDay(this.focusedDate);
            const end = this._addDays(start, 14);
            const byDay = new Map();
            const bucket = (date) => {
                const key = this._startOfDay(date).toISOString();
                if (!byDay.has(key)) byDay.set(key, { events: [], markers: [] });
                return byDay.get(key);
            };
            this.events
                .filter((e) => {
                    const s = this._eventStart(e);
                    return s >= start && s < end;
                })
                .sort((a, b) => this._eventStart(a) - this._eventStart(b))
                .forEach((e) => bucket(this._eventStart(e)).events.push(e));
            // Markers in range surface as their own agenda line — including on a day
            // with no events at all (a marker-only day still appears in the list).
            this.dayMarkers
                .filter((m) => m._date >= start && m._date < end)
                .forEach((m) => bucket(m._date).markers.push(m));
            return [...byDay.entries()]
                // Buckets are inserted events-first then markers, so re-sort by date.
                .sort((a, b) => new Date(a[0]) - new Date(b[0]))
                .map(([key, { events, markers }]) => {
                    const date = new Date(key);
                    return {
                        date,
                        label: agendaDayFormat.format(date),
                        isToday: this._sameDay(date, this.now),
                        markers,
                        events: events.map((e) => ({
                            ...e,
                            timeLabel: e.allDay ? this._allDayLabel : timeFormat.format(this._eventStart(e)),
                        })),
                    };
                });
        },
        get agendaEmpty() {
            return this.agendaDays.length === 0;
        },

        // ── Event interaction ────────────────────────────────────────────
        eventLabel(e) {
            const s = this._eventStart(e);
            const time = e.allDay ? this._allDayLabel : timeFormat.format(s);
            return `${e.title}, ${fullDateFormat.format(s)}, ${time}`;
        },
        // The spoken form of one day, for the month view's "+N more" control.
        //
        // A method rather than an inline `toLocaleDateString` in the template: the
        // template has no way to reach the instance's formatters, so the one date
        // that was formatted there was the one date still spoken in the browser's
        // language after everything else had moved to the application's.
        longDate(date) {
            return fullDateFormat.format(date);
        },
        // The start time a MONTH pill shows to the right of its title.
        //
        // Empty for an all-day event, and that is the whole decision here rather than an
        // omission: a day cell already IS the date, so an "All day" chip would spend the
        // pill's scarcest resource — its width — restating what the reader can see. The
        // agenda view says "All day" because a flat list has no cell to say it for it.
        //
        // Same format as `eventLabel` and the agenda, so a reader comparing the two views
        // sees one clock rather than two. The pill truncates its TITLE to make room; the
        // time is the part that must stay whole, because a clipped time is unreadable
        // while a clipped title is still recognizable.
        pillTime(e) {
            return e.allDay ? '' : timeFormat.format(this._eventStart(e));
        },
        selectEvent(e) {
            this.$dispatch('event-click', { id: e.id });
        },
        // Month "+N more" → jump to the week view focused on that day so the
        // hidden events become visible. A read-focused calendar needs no popover
        // infra for this; switching to the hour grid reveals every event.
        showMore(date) {
            this.focusedDate = new Date(date);
            this.setView('week');
        },

        // ── Truncated-title tooltip (one shared bubble) ──────────────────
        // Event pills / chips / agenda rows are client-side x-for nodes, so the
        // Blade <x-wirekit::tooltip> cannot wrap them. ONE shared bubble (x-ref
        // "tip", styled identically to the tooltip component's panel) serves
        // every [data-wk-tip] target: shown on hover/focus ONLY when the text is
        // actually truncated, anchored by the shared floating util. The bubble
        // is aria-hidden — every target's aria-label already carries the full
        // text, so a described-by bubble would double-announce. Listeners are
        // Alpine bindings on the component root (delegation; auto-cleaned).
        tipOpen: false,
        tipText: '',
        _tipTarget: null,

        _isTruncated(target) {
            const els = (typeof target.matches === 'function' && target.matches('.truncate'))
                ? [target]
                : Array.from(target.querySelectorAll('.truncate'));
            // +1 tolerates sub-pixel rounding so an exactly-fitting line never tips.
            return els.some((el) => el.scrollWidth > el.clientWidth + 1);
        },
        async tipShow(e) {
            const target = e.target && typeof e.target.closest === 'function'
                ? e.target.closest('[data-wk-tip]')
                : null;
            if (!target) return;
            if (target === this._tipTarget) return; // already showing for this node
            if (!this._isTruncated(target)) {
                this.tipHide();
                return;
            }
            this._tipTarget = target;
            this.tipText = target.getAttribute('data-wk-tip') || '';
            this.tipOpen = true;
            await this.$nextTick();
            if (this.$refs.tip) {
                await position(target, this.$refs.tip, { placement: 'top', offset: 6, crossAxisShift: true });
            }
        },
        tipHide(e) {
            // pointerout fires when moving INTO a child of the same target — ignore.
            if (e && this._tipTarget && e.relatedTarget && this._tipTarget.contains
                && this._tipTarget.contains(e.relatedTarget)) {
                return;
            }
            this.tipOpen = false;
            this._tipTarget = null;
        },
    };
}
