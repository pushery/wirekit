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
    // Description animation Option C — synchronous colour count-up. When true AND animate=true,
    // the description text colour interpolates from --color-wk-text-muted → --color-wk-text
    // on the same 1.2s timeline as the value. Mutually exclusive with descriptionDeferred.
    'descriptionAnimate' => false,
    // Optional reveal animation when stat scrolls into view (separate from
    // the value count-up `animate` prop). Null = no reveal (default).
    'animateIn' => null,
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

    // Trend color + arrow glyph — mapped to semantic tokens
    [$trendColor, $trendIcon, $trendLabel] = match ($trend) {
        'up' => ['text-[var(--color-wk-success-text)]', '▲', 'increased'],
        'down' => ['text-[var(--color-wk-danger-text)]', '▼', 'decreased'],
        'neutral' => ['text-[var(--color-wk-text-muted)]', '→', 'unchanged'],
        default => [null, null, null],
    };
@endphp

<div
    {{ $attributes->class([$classes]) }}
    @if($hasDescriptionAnim)
        x-data="wirekitStatAnimate"
        data-target="{{ $value }}"
    @elseif($animateAttr)
        {!! $animateAttr !!}
    @endif
>
    {{-- Top row: label + optional icon --}}
    <div class="flex items-center justify-between gap-2">
        @if($label)
            <span class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)] font-[number:var(--font-wk-heading-weight)]">
                {{ $label }}
            </span>
        @endif
        @if($icon)
            <x-wirekit::icon :name="$icon" size="sm" class="text-[var(--color-wk-text-subtle)]" />
        @elseif(isset($iconSlot))
            <div class="text-[var(--color-wk-text-subtle)]">{{ $iconSlot }}</div>
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
            {{-- When description anim is on, the parent root carries x-data; value div skips it
                 to avoid double-binding. When description is static (default), the value div
                 owns its own scope (v1.5.0-identical render). --}}
            <div
                @if(! $hasDescriptionAnim)
                    x-data="wirekitStatAnimate"
                    data-target="{{ $value }}"
                @endif
                class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)] tabular-nums"
            >
                <span x-text="value">{{ $value }}</span>
            </div>
        @else
            <div class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)] tabular-nums">
                {{ $value }}
            </div>
        @endif
    @elseif(trim($slot->toHtml()) !== '')
        {{-- Fallback: slot allows rich value rendering (e.g. mixed currency + icon) --}}
        <div class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">
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
                <span class="inline-flex items-center gap-1 {{ $trendColor ?? 'text-[var(--color-wk-text-muted)]' }} font-[number:var(--font-wk-heading-weight)]">
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
                        class="text-[var(--color-wk-text-muted)]"
                    >{{ $description }}</span>
                @elseif($descriptionAnimate && $animate)
                    {{-- Option C — synchronous colour count-up. Text colour interpolates
                         from muted → text on the same 1.2s timeline as the value via
                         the `progress` reactive (0 = start, 1 = settled). --}}
                    <span
                        x-bind:style="'color: color-mix(in srgb, var(--color-wk-text-muted) ' + ((1 - progress) * 100) + '%, var(--color-wk-text) ' + (progress * 100) + '%)'"
                    >{{ $description }}</span>
                @else
                    <span class="text-[var(--color-wk-text-muted)]">{{ $description }}</span>
                @endif
            @endif
        </div>
    @endif

    {{--: optional citation footnote (smaller + subtle). --}}
    @if($citation)
        <span class="text-[length:var(--text-wk-xs)] text-[var(--color-wk-text-subtle)]">
            {{ $citation }}
        </span>
    @endif
</div>
