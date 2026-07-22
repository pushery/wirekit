@props([
    // The ABSOLUTE target instant — a Carbon, an ISO-8601 string, or a unix
    // timestamp. Absolute, never a duration: a duration drifts the moment the
    // tab sleeps or the page is cached; an absolute instant is recomputed from
    // the real clock on every tick.
    'until' => null,
    // Text shown once the deadline has passed. Null → a translatable default.
    'expiredText' => null,
    // Seconds-remaining threshold below which the countdown enters its "urgent"
    // (warning-tinted) state. Null → no urgent state.
    'warnThreshold' => null,
    // Which units to show. 'auto' (default): the largest meaningful unit down to
    // the smallest, dropping leading zero-units — years appear only once the
    // remaining time actually reaches a year (no more "26831d"). Or an explicit
    // ordered subset, e.g. "days hours minutes" or "years days": the largest
    // listed unit carries all the overflow above it, the smallest truncates.
    'units' => 'auto',
    // Include seconds in 'auto' mode (ignored when `units` is an explicit list).
    'showSeconds' => true,
    // 'inline' (default): "73y 190d 12h 15m 09s". 'segments': each unit in its
    // own boxed block with a label — the classic dashboard countdown look.
    'variant' => 'inline',
    // Locale-aware thousands separators on large unit values (like price). Turn
    // off with :separators="false".
    'separators' => true,
    // BCP-47 locale for the separators. Null → the app locale.
    'locale' => null,
    // Change animation. For the segments variant, choose the style:
    //   true / "box"  → the whole box pulses (border + accent flash + scale pop)
    //   "text"        → only the changing number briefly flashes the accent color
    //   false / "none" → no motion
    // The inline variant animates with a rise + fade whenever it is on.
    'animate' => true,
    'scope' => null,
])

@php
    use Carbon\Carbon;
    use Pushery\WireKit\WireKit;

    $variantValue = match ($variant) {
        'inline', 'segments' => $variant,
        default => WireKit::validateProp('countdown', 'variant', $variant, ['inline', 'segments']),
    };

    // Resolve the target to a Carbon. Invalid / null `until` degrades to "now" so
    // a misconfigured deadline reads as immediately-overdue rather than throwing.
    $target = null;
    if ($until !== null) {
        $target = $until instanceof Carbon
            ? $until
            : (is_numeric($until) ? Carbon::createFromTimestamp((int) $until) : Carbon::parse((string) $until));
    }
    $targetMs = $target ? $target->getTimestampMs() : Carbon::now()->getTimestampMs();
    $targetIso = ($target ?? Carbon::now())->toIso8601String();
    $humanDeadline = ($target ?? Carbon::now())->isoFormat('LLL');

    $expiredLabel = $expiredText ?? __('Overdue');
    $warnSeconds = $warnThreshold !== null ? (int) $warnThreshold : null;
    $resolvedLocale = $locale ?? app()->getLocale();

    // Resolve the active units, in largest-to-smallest order. 'auto' shows the
    // full ladder (seconds optional) and drops leading zero-units client-side;
    // an explicit list is honored verbatim in canonical order.
    $unitOrder = ['years', 'days', 'hours', 'minutes', 'seconds'];
    if ($units === 'auto') {
        $activeUnits = ['years', 'days', 'hours', 'minutes'];
        if ($showSeconds) {
            $activeUnits[] = 'seconds';
        }
        $autoMode = true;
    } else {
        $requested = preg_split('/[\s,]+/', trim((string) $units)) ?: [];
        $activeUnits = array_values(array_filter($unitOrder, fn ($u) => in_array($u, $requested, true)));
        if ($activeUnits === []) {
            $activeUnits = ['days', 'hours', 'minutes', 'seconds'];
        }
        $autoMode = false;
    }

    // Localized unit labels for the segments variant + the screen-reader text.
    $unitLabels = [
        'years' => __('Years'), 'days' => __('Days'), 'hours' => __('Hours'),
        'minutes' => __('Minutes'), 'seconds' => __('Seconds'),
    ];

    // Resolve the change-animation style. `animate` accepts a bool or one of the
    // strings 'box' / 'text' / 'none'. 'box' (the default when true) pulses the
    // whole box; 'text' flashes only the changing number; anything falsey is off.
    $animateStyle = match (true) {
        $animate === false, $animate === 'false', $animate === 'none', $animate === '0', $animate === 0 => 'none',
        $animate === 'text' => 'text',
        default => 'box',
    };
    $animateOn = $animateStyle !== 'none';

    $baseClasses = WireKit::resolveClasses('countdown', 'base', implode(' ', [
        $variantValue === 'segments'
            // flex-wrap, weil die Segmente eine harte Mindestbreite tragen
            // (min-w-[3.5rem] je Box). Ohne Umbruch braucht die Zeile mit fünf
            // Einheiten 398px und ragt auf einem 393px-Gerät 29px über ihren
            // Elternteil hinaus — gemessen, nicht geschätzt. Umbrechen ist die
            // einzige Anpassung, die die Boxgröße erhält; Schrumpfen würde die
            // Ziffern unlesbar machen.
            // Das px-… hält Platz fuer die eigene Animation vor: der Box-Puls
            // laeuft ueber transform: scale(1.08), eine 90px-Box waechst dadurch
            // um gut 7px, also ~3.6px je Seite — genau der 3px-Ueberlauf, den der
            // Mobile-Sweep an der aeussersten Box gemeldet hat. Den Puls kleiner
            // zu machen waere eine Designaenderung; Raum vorzuhalten ist keine.
            ? 'inline-flex flex-wrap items-stretch justify-center gap-[var(--space-wk-sm)] px-[var(--space-wk-xs)]'
            : 'inline-flex items-baseline gap-[var(--space-wk-xs)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'tabular-nums',
    ]), $scope);
@endphp

<div
    x-modelable="done"
    x-data="{
        target: @js($targetMs),
        warnSeconds: @js($warnSeconds),
        activeUnits: @js($activeUnits),
        autoMode: @js($autoMode),
        separators: @js((bool) $separators),
        locale: @js($resolvedLocale),
        animate: @js($animateOn),
        expiredText: @js($expiredLabel),
        unitSuffix: { years: 'y', days: 'd', hours: 'h', minutes: 'm', seconds: 's' },
        _div: { years: 31536000, days: 86400, hours: 3600, minutes: 60, seconds: 1 },
        now: Date.now(),
        _timer: null,
        // Completion state. `done` is a plain reactive prop (so x-modelable
        // can bind it, unlike the read-only `expired` getter); `_fired` de-dupes the
        // one-shot event.
        _fired: false,
        done: false,
        init() {
            this.now = Date.now();
            // Client-side tick — no wire:poll (a visual clock does not need a
            // server round-trip).
            this._timer = setInterval(() => { this.now = Date.now(); }, 1000);
            // Fire `wirekit-countdown-expired` + flip `done` exactly once at (or past)
            // zero, so a sibling control can react and x-model can observe. $dispatch
            // bubbles from the root, mirroring the wirekit-lightbox-open convention.
            const fire = () => {
                if (this.expired && ! this._fired) {
                    this._fired = true;
                    this.done = true;
                    this.$dispatch('wirekit-countdown-expired');
                }
            };
            fire(); // an already-past deadline still notifies
            this.$watch('now', () => fire());
        },
        destroy() {
            if (this._timer) { clearInterval(this._timer); this._timer = null; }
        },
        get remainingMs() { return this.target - this.now; },
        get expired() { return this.remainingMs <= 0; },
        // Full remaining-time breakdown for a HEADLESS display: a
        // developer whose app renders its own copy around the number (e.g. a localized
        // 'Resend in N seconds', with its own pluralization) reads this instead of
        // rebuilding the clock/resync/expiry core. Unlike `computed` — which is
        // filtered to the active units + variant and drops leading zeros — this is
        // the ALWAYS-COMPLETE canonical ladder plus the totals, so `remaining.seconds`
        // and `remaining.totalSeconds` are stable regardless of the `units` prop.
        get remaining() {
            const totalMs = Math.max(0, this.remainingMs);
            let s = Math.floor(totalMs / 1000);
            const totalSeconds = s;
            const years = Math.floor(s / 31536000); s -= years * 31536000;
            const days = Math.floor(s / 86400); s -= days * 86400;
            const hours = Math.floor(s / 3600); s -= hours * 3600;
            const minutes = Math.floor(s / 60); s -= minutes * 60;
            return { years, days, hours, minutes, seconds: s, totalSeconds, totalMs };
        },
        get urgent() {
            return this.warnSeconds !== null && ! this.expired && this.remainingMs <= this.warnSeconds * 1000;
        },
        // Break the remaining time across the active units. The FIRST active unit
        // carries all overflow above it (so units='hours' shows total hours), the
        // rest cascade down. A YEAR is a 365-day approximation — a running
        // deadline reads in whole years/days, not calendar-exact leap math.
        get computed() {
            let s = Math.max(0, Math.floor(this.remainingMs / 1000));
            const segs = [];
            for (const u of this.activeUnits) {
                const div = this._div[u];
                const value = Math.floor(s / div);
                s -= value * div;
                segs.push({ unit: u, value });
            }
            // auto mode: drop leading zero-units, keeping at least the last one.
            if (this.autoMode) {
                let start = 0;
                while (start < segs.length - 1 && segs[start].value === 0) start++;
                return segs.slice(start);
            }
            return segs;
        },
        // Format one unit value: the leading unit gets locale separators (it can
        // be large — years/days); the rest are zero-padded to two digits for a
        // stable clock rhythm.
        segValue(seg, index) {
            if (index === 0) {
                return this.separators
                    ? new Intl.NumberFormat(this.locale).format(seg.value)
                    : String(seg.value);
            }
            return String(seg.value).padStart(2, '0');
        },
        // Per-value key so a changed value re-mounts its node and the enter
        // transition fires (the change animation). Stable per-unit when animation
        // is off, so nothing re-mounts.
        segKey(seg) {
            return this.animate ? seg.unit + '-' + seg.value : seg.unit;
        },
        // Coarse, screen-reader text — NOT a per-second live region (role=timer
        // is aria-live=off), so it is read on navigation, never announced every
        // tick.
        get srText() {
            if (this.expired) return this.expiredText;
            const names = @js($unitLabels);
            const bits = this.computed
                .filter((seg, i) => seg.value > 0 || i === this.computed.length - 1)
                .map(seg => seg.value + ' ' + (names[seg.unit] || seg.unit).toLowerCase());
            return bits.join(', ');
        },
    }"
    role="timer"
    aria-label="{{ __('Deadline') }}: {{ $humanDeadline }}"
    :class="expired
        ? 'text-[color:var(--color-wk-danger-text)]'
        : (urgent ? 'text-[color:var(--color-wk-warning-text)]' : 'text-[color:var(--color-wk-text)]')"
    {{ $attributes->class([$baseClasses]) }}
>
    @if($slot->isNotEmpty())
    {{-- Headless mode: the developer's markup renders its own copy around the
         live number and owns the a11y text, while WireKit keeps the clock tick,
         resync, expiry event, and `done` state. Their Alpine directives resolve
         against this scope, so `remaining` (full breakdown + totalSeconds),
         `expired`, `urgent`, `done`, `srText`, and `expiredText` are all
         available — e.g. <span x-text="`Resend in ${remaining.totalSeconds}s`">.
         The default sr <time>/units are intentionally NOT rendered here so the
         developer's own copy is the single source of truth. --}}
    {{ $slot }}
    @else
    {{-- Machine-readable target instant + coarse remaining time for assistive
         tech and crawlers. --}}
    <time datetime="{{ $targetIso }}" class="sr-only" x-text="srText"></time>

    {{-- Overdue state: a single label, no ticking units. --}}
    <span aria-hidden="true" x-show="expired" x-text="expiredText"></span>

    {{-- Live units. Decorative (aria-hidden) — the value lives in the sr <time>.
         Each unit is its own node keyed by value, so a changed value re-mounts
         and its enter transition plays (the change animation). --}}
    <template x-if="! expired">
        <span aria-hidden="true" class="{{ $variantValue === 'segments' ? 'inline-flex flex-wrap items-stretch justify-center gap-[var(--space-wk-sm)] px-[var(--space-wk-xs)]' : 'inline-flex items-baseline gap-[var(--space-wk-xs)]' }}">
            <template x-for="(seg, index) in computed" :key="segKey(seg)">
                @if($variantValue === 'segments')
                    {{-- The box re-mounts on each value change (segKey includes the
                         value when animate is on), replaying the change animation.
                         Style 'box' → wk-countdown-pulse on the box (border +
                         accent-tint flash + scale pop). Style 'text' → the box
                         stays still and wk-countdown-text-flash flashes only the
                         number's color. Both are gated for prefers-reduced-motion
                         in dist/wirekit.css. --}}
                    <span
                        @class(['wk-countdown-pulse' => $animateStyle === 'box', 'flex min-w-[3.5rem] flex-col items-center rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]'])
                    >
                        <span @class(['wk-countdown-text-flash' => $animateStyle === 'text', 'text-[length:var(--text-wk-2xl)] font-[number:var(--font-wk-heading-weight)] leading-none tabular-nums']) x-text="segValue(seg, index)"></span>
                        <span class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-xs)] uppercase tracking-wider text-[color:var(--color-wk-text-muted)]" x-text="{{ Js::from($unitLabels) }}[seg.unit]"></span>
                    </span>
                @else
                    <span
                        @if($animateOn)
                        x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        @endif
                        class="tabular-nums"
                    ><span x-text="segValue(seg, index)"></span><span x-text="unitSuffix[seg.unit]"></span></span>
                @endif
            </template>
        </span>
    </template>
    @endif
</div>
