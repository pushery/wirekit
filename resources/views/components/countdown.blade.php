{{-- optimistic-ui: n/a — client-only
     A timer. --}}
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
    use Pushery\WireKit\Support\BooleanProp;
    use Carbon\Carbon;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('countdown', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showSeconds = BooleanProp::from($showSeconds, true);
    $separators = BooleanProp::from($separators, true);

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

    $expiredLabel = $expiredText ?? __('wirekit::Overdue');
    $warnSeconds = $warnThreshold !== null ? (int) $warnThreshold : null;

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

    // Localized unit labels for the SEGMENTS variant — a standalone caption under
    // a big number, where the plural noun is the convention regardless of value.
    $unitLabels = [
        'years' => __('wirekit::Years'), 'days' => __('wirekit::Days'), 'hours' => __('wirekit::Hours'),
        'minutes' => __('wirekit::Minutes'), 'seconds' => __('wirekit::Seconds'),
    ];

    // Number-agreeing phrases for the SCREEN-READER text, which reads as a
    // sentence and must agree ("1 second", not "1 seconds").
    //
    // Both forms travel to the client because the count is only known there —
    // the clock ticks in Alpine. The phrase carries :count rather than being
    // concatenated, so a language that puts the number elsewhere (or attaches a
    // suffix to it) can say so in its own catalog. The old code lowercased the
    // label, which is simply wrong for German and any language capitalizing
    // nouns; casing belongs to the translation, never to a transformation.
    // Passing :count back as the replacement keeps the placeholder literal so the
    // client can substitute the live value into whichever form it picked.
    //
    // The keys are written out literally rather than looped over a variable, and
    // that is deliberate: a key reachable only through a variable is invisible to
    // `lang:extract` and to the drift guard that keeps lang/en.json honest — the
    // same blindness that let a hardcoded string sit in an Alpine expression
    // unnoticed. Six lines of repetition buy a key that every tool can see.
    // Every form the locale distinguishes, not just two.
    //
    // These used to be two entries per unit — the singular and the plural —
    // and the factory picked with `value === 1 ? 0 : 1`. That is right for
    // English and German and silently wrong for Polish, Russian and Arabic,
    // which have three to six categories. It also looked right to everyone who
    // read the English output, which is why it survived.
    //
    // PluralPhrases renders the key at every count the supported locales
    // distinguish and the browser chooses with Intl.PluralRules. The keys stay
    // written out literally: one reachable only through a variable is invisible
    // to `lang:extract` and to the drift guard that keeps lang/en.json honest.
    $unitPhrases = [
        'years' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count year|[2,*] :count years'),
        'days' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count day|[2,*] :count days'),
        'hours' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count hour|[2,*] :count hours'),
        'minutes' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count minute|[2,*] :count minutes'),
        'seconds' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count second|[2,*] :count seconds'),
    ];
    // ONE key, carrying both intentions: the prop is honored, and whatever it
    // resolves to is normalized to a language tag.
    //
    // These were two assignments feeding two `locale:` entries in the same x-data
    // object. A JavaScript object literal keeps the LAST of a duplicated key, so the
    // one that read the prop was dead — `locale="fr-FR"` on a German page counted in
    // German, with no error anywhere, and right by coincidence wherever the prop and
    // the app locale agreed.
    //
    // The normalization is not cosmetic and belonged to the surviving line only:
    // Laravel spells it `de_DE`, and an underscore is not a BCP-47 separator, so
    // `Intl.NumberFormat` and `Intl.PluralRules` do not read it as German-Germany.
    // Keeping the prop-reading line as-is would have fixed the visible half and
    // introduced the other.
    $countdownLocale = str_replace('_', '-', $locale ?? app()->getLocale());

    // Resolve the change-animation style. `animate` accepts a bool or one of the
    // strings 'box' / 'text' / 'none'. 'box' (the default when true) pulses the
    // whole box; 'text' flashes only the changing number; anything falsey is off.
    // NOT a boolean prop despite its `true` default: it is tri-state — true,
    // off (false / 'none' / '0' / 0), or the string 'text'. It must NOT go
    // through BooleanProp::from(), which would collapse 'text' to true and
    // silently drop the text-only mode. The match below already handles the
    // unbound-attribute case ('false' as a string), so the Blade stringly-false
    // trap is covered here without a cast.
    $animateStyle = match (true) {
        $animate === false, $animate === 'false', $animate === 'none', $animate === '0', $animate === 0 => 'none',
        $animate === 'text' => 'text',
        default => 'box',
    };
    $animateOn = $animateStyle !== 'none';

    $baseClasses = WireKit::resolveClasses('countdown', 'base', implode(' ', [
        $variantValue === 'segments'
            // flex-wrap, because a segment carries a hard minimum width
            // (min-w-[3.5rem] per box). Without wrapping, a row of five units
            // needs 398px and overruns its parent by 29px on a 393px device —
            // measured, not estimated. Wrapping is the only adjustment that
            // keeps the box size; shrinking would make the digits unreadable.
            // The horizontal padding holds room for the component's own
            // animation: the box pulse runs through transform: scale(1.08), so
            // a 90px box grows by a little over 7px — about 3.6px per side,
            // which is exactly the 3px overflow the mobile sweep reported at
            // the outermost box. Making the pulse smaller would be a design
            // change; reserving the room is not.
            ? 'inline-flex flex-wrap items-stretch justify-center gap-[var(--space-wk-sm)] px-[var(--space-wk-xs)]'
            : 'inline-flex items-baseline gap-[var(--space-wk-xs)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'tabular-nums',
    ]), $scope);
@endphp

<div
    x-modelable="done"
    {{-- The clock, the unit ladder, the expiry event and the screen-reader text
         live in the factory (resources/js/components/countdown.js). Getters and
         method shorthand do not parse under Alpine's CSP build, so the object
         failed to build and the timer rendered once and then stood still. --}}
    x-data="wirekitCountdown({
        target: {{ \Pushery\WireKit\Support\AlpinePayload::from($targetMs) }},
        warnSeconds: {{ \Pushery\WireKit\Support\AlpinePayload::from($warnSeconds) }},
        activeUnits: {{ \Pushery\WireKit\Support\AlpinePayload::from($activeUnits) }},
        autoMode: {{ \Pushery\WireKit\Support\AlpinePayload::from($autoMode) }},
        separators: {{ \Pushery\WireKit\Support\AlpinePayload::from((bool) $separators) }},
        animate: {{ \Pushery\WireKit\Support\AlpinePayload::from($animateOn) }},
        expiredText: {{ \Pushery\WireKit\Support\AlpinePayload::from($expiredLabel) }},
        unitPhrases: {{ \Pushery\WireKit\Support\AlpinePayload::from((object) $unitPhrases) }},
        {{-- The APPLICATION's locale, not the browser's. A German page read on an
             English-configured machine must still pluralize German. --}}
        locale: {{ \Pushery\WireKit\Support\AlpinePayload::from($countdownLocale) }},
    })"
    role="timer"
    aria-label="{{ __('wirekit::Deadline') }}: {{ $humanDeadline }}"
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
                        <span class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-xs)] uppercase tracking-wider text-[color:var(--color-wk-text-muted)]" x-text="{{ \Pushery\WireKit\Support\AlpinePayload::from($unitLabels) }}[seg.unit]"></span>
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
