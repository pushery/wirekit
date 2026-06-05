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
    'animate' => false,
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
    // KPI-tile chrome: when set, the stat gains an intent-colored left
    // stripe + a faint intent-tinted body — the pattern dashboard blueprints
    // hand-rolled per tile. null = the existing plain card surface, unchanged.
    // primary | success | warning | danger | info | neutral.
    'intent' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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

    // KPI-tile chrome. When `intent` is set, resolve its color token and
    // paint a 3px left stripe + an 8%-tint body via inline style (so it
    // overrides the base border-left + bg-elevated without a per-intent
    // class explosion). Mirrors badge's intent palette: info/primary share
    // accent; neutral uses the muted text token.
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
        $intentTileStyle = "border-left-width: 3px; border-left-color: {$intentToken}; background-color: color-mix(in srgb, {$intentToken} 8%, var(--color-wk-bg-elevated));";
    }

    // Trend color + arrow glyph — mapped to semantic tokens
    [$trendColor, $trendIcon, $trendLabel] = match ($trend) {
        'up' => ['text-[color:var(--color-wk-success-text)]', '▲', 'increased'],
        'down' => ['text-[color:var(--color-wk-danger-text)]', '▼', 'decreased'],
        'neutral' => ['text-[color:var(--color-wk-text-muted)]', '→', 'unchanged'],
        default => [null, null, null],
    };
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
    {{ $attributes->class([$classes]) }}
    @if($intentTileStyle) style="{{ $intentTileStyle }}" @endif
    @if($intent !== null && $label) role="group" aria-label="{{ $label }}" @endif
    @if($animate)
        {{-- Counter scope on root. Description spans inside read $root.animating
             / $root.progress. When $needsEntranceWrapper is also true, the
             outer wrapper carries the entrance animateAttr — separate scope. --}}
        x-data="wirekitStatAnimate"
        data-target="{{ $value }}"
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
    @elseif(trim($slot->toHtml()) !== '')
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
