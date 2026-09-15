{{-- optimistic-ui: n/a — client-only
     Its state is the count-up animation. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'label' => null,
    'value' => null,
    'change' => null,
    'trend' => null, // 'up' | 'down' | 'neutral' | null
    'icon' => null,
    'description' => null,
    'citation' => null,
    // Opt-in counter animation. When true, the value text is wrapped in
    // an Alpine x-data="wirekitStatAnimate" handler that animates 0 → value
    // over 1.2s once the stat scrolls into view. Respects
    // prefers-reduced-motion (snaps to value, no animation).
    'animate' => config('wirekit.components.stat.animate', false),
    // Description animation Option A — defer fade-in. When true AND animate=true,
    // the description span is hidden via x-show while the counter runs (~1.2s)
    // and fades in once the animation settles. Mutually exclusive with descriptionAnimate.
    'descriptionDeferred' => false,
    // Description animation Option C — synchronous color count-up. When true AND animate=true,
    // the description text color interpolates from --color-wk-text-muted → --color-wk-text
    // on the same 1.2s timeline as the value. Mutually exclusive with descriptionDeferred.
    'descriptionAnimate' => false,
    // Optional reveal animation when stat scrolls into view (separate from
    // the value count-up `animate` prop). Null = no reveal (default).
    'animateIn' => null,
    // KPI-tile chrome: when set, the stat gains an intent-colored 4-sided
    // border + a faint intent-tinted body — the pattern dashboard blueprints
    // hand-rolled per tile. null = the existing plain card surface, unchanged.
    // primary | success | warning | danger | info | neutral.
    'intent' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $animate = BooleanProp::from($animate, false);
    $descriptionDeferred = BooleanProp::from($descriptionDeferred, false);
    $descriptionAnimate = BooleanProp::from($descriptionAnimate, false);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('stat', $attributes->getAttributes());

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'stat');

    // Mutual exclusion check — Option A and Option C cannot combine.
    if ($descriptionDeferred && $descriptionAnimate) {
        throw new \InvalidArgumentException(
            '<x-wirekit::stat> descriptionDeferred and descriptionAnimate are mutually exclusive. Pick one (or neither for the static default).'
        );
    }

    // Animation wiring is gated on animate=true — Options A/C are no-ops if the parent counter isn't running.
    $hasDescriptionAnim = $animate && ($descriptionDeferred || $descriptionAnimate);

    // When animate (counter) AND animateIn (entrance reveal) are BOTH set,
    // two Alpine x-data scopes are needed — they cannot share one element.
    // Wrap the existing root in an outer <div> carrying the entrance
    // reveal; the inner root keeps the counter scope.
    $needsEntranceWrapper = $animate && $animateAttr;

    // ── The counter's MACHINE value, resolved here rather than in JavaScript ──
    //
    // `value` is a DISPLAY string — "€31.200", "$1,250.50", "42%", "10000". The
    // counter needs a number to count to, the pieces around it to put back, and
    // the number of decimals to keep. JavaScript used to re-derive all three from
    // the display string with one grammar: strip everything but digits, `.` and
    // `-`, then concatenate whatever was left AFTER the number.
    //
    // Both halves of that were wrong, and one of them silently. A German page
    // writing "€31.200" — the ordinary spelling of 31,200 euros — counted to
    // 31.2, off by a factor of a thousand, because a grouping period was read as
    // a decimal point. And a leading currency symbol came back on the trailing
    // side: "$1,250.50" settled as "1250.50$" in every locale, including English.
    //
    // PHP is where the application locale is known, so the split happens here and
    // the browser is handed pieces rather than a puzzle.
    $targetValue = null;
    $targetPrefix = '';
    $targetSuffix = '';
    $targetDecimals = 0;

    if ($animate && $value !== null) {
        $raw = (string) $value;

        // The separators this application actually uses, discovered by formatting
        // a probe rather than by carrying a locale table that would go stale.
        // 1234.5 renders "1,234.5" in en and "1.234,5" in de, so the LAST
        // non-digit is the decimal separator and the other one groups.
        $probe = preg_replace('/\d/u', '', \Pushery\WireKit\Support\LocalizedNumber::format(1234.5, precision: 1)) ?? '.';
        $decimalSeparator = mb_substr($probe, -1) ?: '.';
        $groupSeparator = mb_strlen($probe) > 1 ? mb_substr($probe, 0, 1) : '';

        // Everything before the first digit is the prefix, everything after the
        // last one is the suffix, and what is between them is the number. By
        // POSITION, so a symbol goes back on the side it came from — the whole
        // point, since concatenating both onto the end is what moved the `$`.
        $core = '';
        if (preg_match_all('/\d/u', $raw, $digits, PREG_OFFSET_CAPTURE) > 0) {
            $start = $digits[0][0][1];
            $end = $digits[0][count($digits[0]) - 1][1] + 1;

            // A sign directly in front of the first digit belongs to the number,
            // not to the prefix — otherwise a negative delta counts upward.
            if ($start > 0 && ($raw[$start - 1] === '-' || $raw[$start - 1] === '+')) {
                $start--;
            }

            $targetPrefix = substr($raw, 0, $start);
            $core = substr($raw, $start, $end - $start);
            $targetSuffix = substr($raw, $end);
        } else {
            $targetSuffix = $raw;
        }

        // No locale spells a decimal separator with whitespace, so every kind of
        // it groups — including the narrow no-break space fr and ru use, which a
        // developer types as a plain space and no probe would match.
        $core = preg_replace('/[\p{Zs}\x{00A0}\x{202F}]/u', '', $core) ?? '';

        if ($groupSeparator !== '') {
            $core = str_replace($groupSeparator, '', $core);
        }

        $decimalPosition = $decimalSeparator === '' ? false : mb_strrpos($core, $decimalSeparator);
        $targetDecimals = $decimalPosition === false ? 0 : mb_strlen($core) - $decimalPosition - 1;
        $core = $decimalSeparator === '' ? $core : str_replace($decimalSeparator, '.', $core);

        // A value with no digits at all ("N/A", an em dash) counts to zero and
        // keeps its text, which is what it did before and is the honest answer:
        // there is nothing to count.
        $targetValue = is_numeric($core) ? (float) $core : 0.0;
    }

    // BCP-47 for the in-flight grouping, since `toLocaleString()` with no
    // argument reads the reader's browser rather than the application. Laravel
    // spells a regional locale `pt_BR`; Intl reads `pt-BR`.
    $statLocale = str_replace('_', '-', app()->getLocale());

    // Container: card-like surface with padding + elevated background + border
    $classes = WireKit::resolveClasses('stat', 'base', implode(' ', [
        'flex flex-col gap-1',
        'bg-[var(--color-wk-bg-elevated)]',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // KPI-tile chrome. When `intent` is set, resolve its color token and paint a
    // 4-sided tinted border + an 8%-tint body via inline style (so it overrides
    // the base neutral border + bg-elevated without a per-intent class explosion).
    // A one-sided accent stripe reads as generic dashboard chrome, so the intent
    // cue uses the balanced 4-sided tinted border instead. Mirrors badge's intent
    // palette: info/primary share accent; neutral uses the muted text token.
    $intentTileStyle = '';
    if ($intent !== null) {
        // Validate first (throws in debug / falls back to first-allowed in
        // prod), then map the canonical value to its color token.
        $validIntent = match ($intent) {
            'primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral' => $intent,
            default => WireKit::validateProp('stat', 'intent', $intent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']),
        };
        $intentToken = match ($validIntent) {
            'success' => 'var(--color-wk-success)',
            'warning' => 'var(--color-wk-warning)',
            'danger' => 'var(--color-wk-danger)',
            'neutral' => 'var(--color-wk-text-muted)',
            default => 'var(--color-wk-accent)', // primary + accent + info
        };
        $intentTileStyle = "border-color: color-mix(in srgb, {$intentToken} 40%, var(--color-wk-border)); background-color: color-mix(in srgb, {$intentToken} 8%, var(--color-wk-bg-elevated));";
    }

    // Trend color + arrow glyph — mapped to semantic tokens
    [$trendColor, $trendIcon, $trendLabel] = match ($trend) {
        // The third slot is the sr-only expansion of the arrow glyph — the ONLY way a
        // non-visual reader learns the direction, so it goes through the catalog exactly
        // as the sibling ticker's `wirekit::Change: …` labels do.
        'up' => ['text-[color:var(--color-wk-success-text)]', '▲', __('wirekit::increased')],
        'down' => ['text-[color:var(--color-wk-danger-text)]', '▼', __('wirekit::decreased')],
        'neutral' => ['text-[color:var(--color-wk-text-muted)]', '→', __('wirekit::unchanged')],
        default => [null, null, null],
    };

    /*
     * ⚠️ `trend` IS A SENTIMENT IN THIS COMPONENT, AND THE ANNOUNCEMENT ABOVE READ IT AS A
     * DIRECTION. `stat.md` tells callers so in as many words — "for metrics where down is
     * good (churn, errors, bounce rate), flip the semantics" — and its own preview passes
     * `trend="down"` beside `change="+0.3%"`, because rising churn is bad. That is the
     * documented model, and it is fine for the two things `trend` really drives: the red
     * and the arrow, both of which a sighted reader takes in beside the visible "+0.3%".
     *
     * The sr-only expansion is not those. It states a fact — "decreased" — and a listener
     * has nothing to reconcile it against, so the tile reads out "Churn, 2.4%, decreased,
     * +0.3%". Eight tiles across the catalog said the opposite of their own number.
     *
     * So the direction is taken from the CHANGE, which is where the direction lives, and
     * `trend` keeps the color and the glyph.
     *
     * ⚠️ It requires an EXPLICIT SIGN, and that is the whole subtlety. A bare "12%" is a
     * magnitude, not a direction — the caller who writes it is leaning on `trend` to say
     * which way, and reading it as positive would silently overrule them. Only a leading
     * `+` or `-`, or a change that is plainly zero, carries a direction of its own; a word,
     * an unsigned figure or anything unparseable leaves the trend word standing, because
     * there is then nothing for it to contradict.
     */
    $changeDirection = null;

    if ($change !== null) {
        $changeTrimmed = ltrim((string) $change);
        $changeNumber = (float) preg_replace('/[^\d.\-]/', '', $changeTrimmed);

        $changeDirection = match (true) {
            str_starts_with($changeTrimmed, '-') => __('wirekit::decreased'),
            str_starts_with($changeTrimmed, '+') && $changeNumber !== 0.0 => __('wirekit::increased'),
            // "0", "0%", "0.0" — no sign needed, and nothing a caller could have meant
            // differently by it.
            $changeTrimmed !== '' && $changeNumber === 0.0 && preg_match('/\d/', $changeTrimmed) === 1 => __('wirekit::unchanged'),
            default => null,
        };
    }

    $trendLabel = $changeDirection ?? $trendLabel;
@endphp

@if($needsEntranceWrapper)
    {{-- Outer wrapper: owns the entrance reveal (wirekitAnimate scope) when
         animate=true AND animateIn is set. Inner root keeps the counter
         scope (wirekitStatAnimate). Two scopes, two elements — Alpine's
         contract is one x-data per element. The replayable contract
         attaches here too so the docs site can re-mount the entrance
         animation on click of the replay button. --}}
    <div {!! $animateAttr !!} data-replayable="true">
@endif

<div
    {{ $attributes->merge($intentTileStyle ? ['style' => $intentTileStyle] : [])->class([$classes]) }}
    @if($intent !== null && $label) role="group" aria-label="{{ $label }}" @endif
    @if($animate)
        {{-- Counter scope on root. Description spans inside read $root.animating
             / $root.progress. When $needsEntranceWrapper is also true, the
             outer wrapper carries the entrance animateAttr — separate scope. --}}
        x-data="wirekitStatAnimate"
        {{-- `data-target` stays the DISPLAY string: it is the documented handle a
             page uses to reach a stat and replay it. The four below are what the
             counter reads — the machine value, the pieces to put back on the
             right sides, how many decimals the display keeps, and the locale that
             groups the in-flight number. --}}
        data-target="{{ $value }}"
        data-target-value="{{ $targetValue !== null ? rtrim(rtrim(number_format($targetValue, 6, '.', ''), '0'), '.') : '' }}"
        @if($targetPrefix !== '') data-target-prefix="{{ $targetPrefix }}" @endif
        @if($targetSuffix !== '') data-target-suffix="{{ $targetSuffix }}" @endif
        data-target-decimals="{{ $targetDecimals }}"
        data-locale="{{ $statLocale }}"
        @unless($needsEntranceWrapper) data-replayable="true" @endunless
    @elseif($animateAttr)
        {{-- animateIn only (no counter): root carries the entrance reveal directly. --}}
        {!! $animateAttr !!}
        data-replayable="true"
    @endif
>
    {{-- Top row: label + optional icon --}}
    <div class="flex items-center justify-between gap-2">
        @if($label)
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] font-[number:var(--font-wk-heading-weight)]">
                {{ $label }}
            </span>
        @endif
        @if($icon)
            <x-wirekit::icon :name="$icon" size="sm" class="text-[color:var(--color-wk-text-subtle)]" />
        @elseif(isset($iconSlot))
            <div class="text-[color:var(--color-wk-text-subtle)]">{{ $iconSlot }}</div>
        @endif
    </div>

    {{-- Main metric value — large, heading-weight for visual emphasis.
         When animate=true, wrap in x-data="wirekitStatAnimate" with the
         target value on data-target so the Alpine plugin can read it +
         animate 0 → target on scroll-into-view.

         When descriptionDeferred OR descriptionAnimate is also true,
         the WHOLE stat root receives x-data so the description span
         can read `animating` from $root scope. --}}
    @if($value !== null)
        @if($animate)
            {{-- Counter scope is on the root. Value div stays as the typography
                 wrapper; the <span x-text="value"> reads the root's
                 wirekitStatAnimate state. --}}
            <div
                class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] tabular-nums"
            >
                <span x-text="value">{{ $value }}</span>
            </div>
        @else
            <div class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] tabular-nums">
                {{ $value }}
            </div>
        @endif
    @elseif($slot->hasActualContent())
        {{-- Fallback: slot allows rich value rendering (e.g. mixed currency + icon) --}}
        <div class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            {{ $slot }}
        </div>
    @endif

    {{--: structural slots between the value and the change row.
         Pure pass-through — no styling, no Alpine. Use these to inject sparklines,
         progress bars, or any inline visualization without rebuilding the card. --}}
    @isset($sparkline)
        <div>{{ $sparkline }}</div>
    @endisset
    @isset($progress)
        <div>{{ $progress }}</div>
    @endisset

    {{-- Bottom row: change indicator + optional description --}}
    @if($change !== null || $description)
        <div class="flex items-center gap-2 text-[length:var(--text-wk-sm)]">
            @if($change !== null)
                {{-- sr-only label expands the arrow glyph for screen readers --}}
                <span class="inline-flex items-center gap-1 {{ $trendColor ?? 'text-[color:var(--color-wk-text-muted)]' }} font-[number:var(--font-wk-heading-weight)]">
                    @if($trendIcon)
                        <span aria-hidden="true">{{ $trendIcon }}</span>
                        <span class="sr-only">{{ $trendLabel }}</span>
                    @endif
                    {{ $change }}
                </span>
            @endif
            @if($description)
                @if($descriptionDeferred && $animate)
                    {{-- Option A — defer fade-in. Description hides while counter runs;
                         fades in after settle. aria-hidden mirrors visibility for SR contract. --}}
                    <span
                        x-show="!animating"
                        x-transition.opacity.duration.200ms
                        x-bind:aria-hidden="animating ? 'true' : null"
                        class="text-[color:var(--color-wk-text-muted)]"
                    >{{ $description }}</span>
                @elseif($descriptionAnimate && $animate)
                    {{-- Option C — synchronous color count-up. Text color interpolates
                         from muted → text on the same 1.2s timeline as the value via
                         the `progress` reactive (0 = start, 1 = settled). --}}
                    <span
                        x-bind:style="'color: color-mix(in srgb, var(--color-wk-text-muted) ' + ((1 - progress) * 100) + '%, var(--color-wk-text) ' + (progress * 100) + '%)'"
                    >{{ $description }}</span>
                @else
                    <span class="text-[color:var(--color-wk-text-muted)]">{{ $description }}</span>
                @endif
            @endif
        </div>
    @endif

    {{--: optional citation footnote (smaller + subtle). --}}
    @if($citation)
        <span class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)]">
            {{ $citation }}
        </span>
    @endif
</div>

@if($needsEntranceWrapper)
    </div>
@endif
