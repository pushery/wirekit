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
    // The tile as a link into the figure's report: the whole tile becomes an `<a>` with the kit's
    // focus ring and a raised shadow on hover, and stays one item of a `stats` grid. Its name is
    // what it shows, label and value first. Null keeps the tile a `<div>`.
    'href' => null,
    // The tile as a toggle, a figure above a list that sets the filter it counts: true or false
    // renders a `<button type="button">` with `aria-pressed`, and a pressed tile draws the frame
    // of a selection in place of the neutral border. A caller's `wire:click`, `wire:key` and
    // `data-*` land on the button, and its name is what it shows, as a linked tile's is. Null
    // keeps the tile as it is.
    'pressed' => null,
    // Shrinks the value from `value` down to a floor so it fits its line: true for the
    // `--text-wk-fit-floor` token, a length such as `1rem` for a floor of its own, null or false for
    // none. A value written in the slot keeps its size.
    'fit' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $animate = BooleanProp::from($animate, false, 'stat', 'animate');
    $descriptionDeferred = BooleanProp::from($descriptionDeferred, false, 'stat', 'descriptionDeferred');
    $descriptionAnimate = BooleanProp::from($descriptionAnimate, false, 'stat', 'descriptionAnimate');

    // The value is shown as given. A float that is not finite is written as PHP has always written
    // it, `NAN` or `INF`, without the warning PHP 8.5 raises when one is cast to text, which Laravel
    // turns into an exception that ends the page.
    if (is_float($value) && ! is_finite($value)) {
        $value = \Pushery\WireKit\Support\NumericProp::text($value);
    }

    // The value's fitted box, on one line. While it counts up, the copies keep the final value, so the
    // value has its final size from the first frame and does not jump as it counts.
    $fitFloor = \Pushery\WireKit\Support\FitText::prop($fit, 'stat');
    $fitBag = $fitFloor === false ? null : new \Illuminate\View\ComponentAttributeBag(array_filter([
        'style' => $fitFloor !== '' ? '--wk-fit-floor: '.$fitFloor.';' : null,
    ]));

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('stat', $attributes->getAttributes());

    // No `trend`, or one of these. An unknown one is reported (Support\StrictnessGate) and leaves
    // the trend unset, as no `trend` does, rather than taking the first of the list.
    $trend = filled($trend) ? \Pushery\WireKit\Support\NumericProp::text($trend) : null;
    if ($trend !== null && \Pushery\WireKit\WireKit::validateProp('stat', 'trend', $trend, ['up', 'down', 'neutral']) !== $trend) {
        $trend = null;
    }

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

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // stat, so they go on the outermost element (see Support\OuterAttributes). With the entrance
    // wrapper that is the wrapper; without it the root is outermost and keeps them in its bag.
    [$outerAttributes, $withoutOuterAttributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    if ($needsEntranceWrapper) {
        $attributes = $withoutOuterAttributes;
    } else {
        $outerAttributes = new \Illuminate\View\ComponentAttributeBag([]);
    }

    // ── The counter's MACHINE value, resolved here rather than in JavaScript ──
    //
    // `value` is a DISPLAY string — "€31.200", "$1,250.50", "42%", "10000". The
    // counter needs a number to count to, the pieces around it to put back, and
    // the number of decimals to keep. Re-deriving all three in JavaScript from the
    // display string with one grammar gets both halves wrong, one of them silently:
    // a German page writing "€31.200" — the ordinary spelling of 31,200 euros —
    // would count to 31.2, off by a factor of a thousand, because a grouping period
    // reads as a decimal point; and a leading currency symbol would come back on
    // the trailing side, "$1,250.50" settling as "1250.50$" in every locale.
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
        // keeps its text, which is the honest answer:
        // there is nothing to count.
        $targetValue = is_numeric($core) ? (float) $core : 0.0;
    }

    // BCP-47 for the in-flight grouping, since `toLocaleString()` with no
    // argument reads the reader's browser rather than the application. Laravel
    // spells a regional locale `pt_BR`; Intl reads `pt-BR`.
    $statLocale = \Pushery\WireKit\Support\IcuLocale::tag();

    // The application's own grouping and decimal marks, where it gives WireKit a number format of
    // its own (WireKit::formatNumbersUsing()). The value was read with them above, so the counter
    // writes them too; null leaves the locale's.
    $statMarks = $animate ? \Pushery\WireKit\Support\LocalizedNumber::separators() : null;

    // A link the way a card is one: the URL as written, Blade's escape undone (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);
    $href = $href === '' ? null : $href;

    // A link is not pressed: given both, the tile stays the link and the state is reported.
    $pressed = BooleanProp::orNull($pressed);

    if ($pressed !== null && $href !== null) {
        \Pushery\WireKit\Support\StrictnessGate::reject('stat', 'pressed', $pressed ? 'true' : 'false', 'a stat without href', '');
        $pressed = null;
    }

    $tag = match (true) {
        $href !== null => 'a',
        $pressed !== null => 'button',
        default => 'div',
    };

    // A button holds phrasing content only, so its parts are spans. Each is a flex item of the
    // tile or of its top row, which lays a span out as it lays out a div.
    $part = $tag === 'button' ? 'span' : 'div';

    // The card's treatment of a card that links or acts: the pointer, the shadow raised on hover
    // and the kit's focus ring standing off the tile, so the tile answers the pointer and the
    // keyboard. `wk-touch-row` holds a tile at the size of a fingertip at least, which its padding
    // and its value already exceed, and keeps the floor where a personalization of the class block
    // cannot reach it.
    $controlClasses = $tag !== 'div' ? implode(' ', [
        'wk-touch-row cursor-pointer',
        'transition-shadow duration-[var(--transition-wk-duration)] ease-[var(--transition-wk-easing)]',
        'hover:shadow-[var(--shadow-wk-lg)]',
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
    ]) : '';

    // A button tile stands as wide as its place with its text at the start, as the div does.
    // Pressed, it draws the frame a chosen card of a radio or checkbox set draws, in the accent
    // color, and twice as thick as the border, so the state is an edge as well as a color
    // (WCAG 1.4.1). Keyed on `aria-pressed`, so the frame outranks the neutral border rather than
    // competing with it in the stylesheet's order, and follows the state wherever it is set: this
    // prop, or a caller's binding. `wk-state-button` frames it in forced colors, which paint
    // neither.
    $pressedClasses = $tag === 'button' ? implode(' ', [
        'wk-state-button w-full text-start',
        'aria-pressed:border-[var(--color-wk-accent)]',
        'aria-pressed:inset-ring-[length:var(--border-wk-width)] aria-pressed:inset-ring-[var(--color-wk-accent)]',
    ]) : '';

    // A tile that opens a new tab says so, and keeps the page that opened it out of reach, as a
    // card does. The rel is written out rather than merged: a caller's rel would replace it.
    $opensNewTab = $href !== null && str_contains((string) $attributes->get('target', ''), '_blank');
    $relAttr = (string) $attributes->get('rel', '');
    $computedRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : ($relAttr !== '' ? $relAttr : null);

    // Container: card-like surface with padding + elevated background + border
    $classes = WireKit::resolveClasses('stat', 'base', implode(' ', [
        'flex flex-col gap-1',
        'bg-[var(--color-wk-bg-elevated)]',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-x-lg)]',
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
        // Validate first (reported through the strictness gate, which falls back to the
        // first allowed value where it does not throw), then map the canonical value to
        // its color token.
        $validIntent = match ($intent) {
            'primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral' => $intent,
            default => WireKit::validateProp('stat', 'intent', $intent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']),
        };
        $intentToken = match ($validIntent) {
            'success' => 'var(--color-wk-success)',
            'warning' => 'var(--color-wk-warning)',
            'danger' => 'var(--color-wk-danger)',
            'neutral' => 'var(--color-wk-text-muted)',
            'info' => 'var(--color-wk-info-tone)',
            default => 'var(--color-wk-accent)', // primary + accent
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
     * `trend` is a sentiment in this component, not a direction. `stat.md` tells callers so in as many words — "for metrics where down is
     * good (churn, errors, bounce rate), flip the semantics" — and its own preview passes
     * `trend="down"` beside `change="+0.3%"`, because rising churn is bad. That is the
     * documented model, and it is fine for the two things `trend` really drives: the red
     * and the arrow, both of which a sighted reader takes in beside the visible "+0.3%".
     *
     * The sr-only expansion is not those. It states a fact — "decreased" — and a listener
     * has nothing to reconcile it against, so read from `trend` the tile would announce
     * "Churn, 2.4%, decreased, +0.3%", the opposite of its own number.
     *
     * So the direction is taken from the change, which is where the direction lives, and
     * `trend` keeps the color and the glyph.
     *
     * It requires an explicit sign, and that is the whole subtlety. A bare "12%" is a
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
    <div {!! $animateAttr !!} data-replayable="true" {{ $outerAttributes }}>
@endif

<{{ $tag }} data-wk-prose-skip
    @if($href !== null) href="{{ $href }}" @endif
    @if($computedRel !== null) rel="{{ $computedRel }}" @endif
    @if($tag === 'button') aria-pressed="{{ $pressed ? 'true' : 'false' }}" @endif
    {{-- `type` as a default, so a caller's own, a filter that submits its form, is kept. --}}
    {{ $attributes->except(['rel'])->merge(array_filter(['style' => $intentTileStyle !== '' ? $intentTileStyle : null, 'type' => $tag === 'button' ? 'button' : null], static fn (?string $value): bool => $value !== null))->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes, $controlClasses, $pressedClasses])) }} data-wk-sr-host
    {{-- A tile with an intent is a group named by its label. A tile that links or toggles is a
         control, named by what it shows, and a group role would take that role away. --}}
    @if($intent !== null && $label && $tag === 'div') role="group" aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($label) }}" @endif
    @if($animate)
        {{-- Counter scope on root. Description spans inside read $root.animating
             / $root.progress. When $needsEntranceWrapper is also true, the
             outer wrapper carries the entrance animateAttr — separate scope. --}}
        x-data="wirekitStatAnimate"
        {{-- `data-target` stays the DISPLAY string: it is the documented handle a
             page uses to reach a stat and replay it. The four below are what the
             counter reads — the machine value, the pieces to put back on the
             right sides, how many decimals the display keeps, and the locale that
             groups the in-flight number, with the application's own marks where it
             has them. --}}
        data-target="{{ $value }}"
        data-target-value="{{ $targetValue !== null ? rtrim(rtrim(number_format($targetValue, 6, '.', ''), '0'), '.') : '' }}"
        @if($targetPrefix !== '') data-target-prefix="{{ $targetPrefix }}" @endif
        @if($targetSuffix !== '') data-target-suffix="{{ $targetSuffix }}" @endif
        data-target-decimals="{{ $targetDecimals }}"
        data-locale="{{ $statLocale }}"
        @if($statMarks !== null) data-number-group="{{ $statMarks['group'] }}" data-number-decimal="{{ $statMarks['decimal'] }}" @endif
        @unless($needsEntranceWrapper) data-replayable="true" @endunless
    @elseif($animateAttr)
        {{-- animateIn only (no counter): root carries the entrance reveal directly. --}}
        {!! $animateAttr !!}
        data-replayable="true"
    @endif
>
    {{-- Top row: label + optional icon --}}
    <{{ $part }} data-wk-prose-skip class="flex items-center justify-between gap-2">
        @if($label)
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] font-[number:var(--font-wk-heading-weight)]">
                {{ $label }}
            </span>
        @endif
        @if($icon)
            <x-wirekit::icon :name="$icon" size="sm" class="text-[color:var(--color-wk-text-subtle)]" />
        @elseif(\Pushery\WireKit\Support\SlotContent::given($iconSlot ?? null))
            <{{ $part }} data-wk-prose-skip class="text-[color:var(--color-wk-text-subtle)]">{{ $iconSlot }}</{{ $part }}>
        @endif
    </{{ $part }}>

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
            <{{ $part }} data-wk-prose-skip
                class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] tabular-nums"
            >
                @if($fitBag !== null)
                    {{ \Pushery\WireKit\Support\FitText::box((string) $value, $fitBag, false, null, new \Illuminate\View\ComponentAttributeBag(['x-text' => 'value'])) }}
                @else
                    <span x-text="value">{{ $value }}</span>
                @endif
            </{{ $part }}>
        @else
            <{{ $part }} data-wk-prose-skip class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)] tabular-nums">
                {{ $fitBag !== null ? \Pushery\WireKit\Support\FitText::box((string) $value, $fitBag, false) : $value }}
            </{{ $part }}>
        @endif
    @elseif($slot->hasActualContent())
        {{-- Fallback: slot allows rich value rendering (e.g. mixed currency + icon) --}}
        <{{ $part }} data-wk-prose-skip class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            {{ $slot }}
        </{{ $part }}>
    @endif

    {{--: structural slots between the value and the change row.
         Pure pass-through — no styling, no Alpine. Use these to inject sparklines,
         progress bars, or any inline visualization without rebuilding the card. --}}
    @if(\Pushery\WireKit\Support\SlotContent::given($sparkline ?? null))
        <{{ $part }} data-wk-prose-skip>{{ $sparkline }}</{{ $part }}>
    @endif
    @if(\Pushery\WireKit\Support\SlotContent::given($progress ?? null))
        <{{ $part }} data-wk-prose-skip>{{ $progress }}</{{ $part }}>
    @endif

    {{-- Bottom row: change indicator + optional description --}}
    @if($change !== null || $description)
        <{{ $part }} data-wk-prose-skip class="flex items-center gap-2 text-[length:var(--text-wk-sm)]">
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
            {{-- The description is a flex item beside the change, never narrower than its longest
                 word: `anywhere` lets a typed token in it, an email address, wrap, and the change
                 keeps its width. --}}
            @if($description)
                @if($descriptionDeferred && $animate)
                    {{-- Option A — defer fade-in. Description hides while counter runs;
                         fades in after settle. aria-hidden mirrors visibility for SR contract. --}}
                    <span
                        x-show="!animating"
                        x-transition.opacity.duration.200ms
                        x-bind:aria-hidden="animating ? 'true' : null"
                        class="text-[color:var(--color-wk-text-muted)] [overflow-wrap:anywhere]"
                    >{{ $description }}</span>
                @elseif($descriptionAnimate && $animate)
                    {{-- Option C — synchronous color count-up. Text color interpolates
                         from muted → text on the same 1.2s timeline as the value via
                         the `progress` reactive (0 = start, 1 = settled). An object, which
                         Alpine sets property by property, where a string it would write
                         with `setAttribute()`, which a `style-src` without
                         `'unsafe-inline'` refuses. --}}
                    <span
                        x-bind:style="{ color: 'color-mix(in srgb, var(--color-wk-text-muted) ' + ((1 - progress) * 100) + '%, var(--color-wk-text) ' + (progress * 100) + '%)' }"
                        class="[overflow-wrap:anywhere]"
                    >{{ $description }}</span>
                @else
                    <span class="text-[color:var(--color-wk-text-muted)] [overflow-wrap:anywhere]">{{ $description }}</span>
                @endif
            @endif
        </{{ $part }}>
    @endif

    {{--: optional citation footnote (smaller + subtle). --}}
    @if($citation)
        <span class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)]">
            {{ $citation }}
        </span>
    @endif
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>

@if($needsEntranceWrapper)
    </div>
@endif
