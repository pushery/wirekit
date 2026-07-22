@props([
    'label' => null,                 // visible metric name (e.g. "Orders this month")
    'used' => 0,                     // current usage (numeric)
    'limit' => null,                 // plan limit; null = unlimited
    'unit' => null,                  // unit suffix (e.g. "orders", "seats")
    'period' => null,                // reset cadence hint: 'day'|'week'|'month'|'year'
    'resetAt' => null,               // explicit reset note (overrides period hint)
    'warn' => config('wirekit.components.usage-meter.warn', 0.8),   // warning threshold ratio
    'danger' => config('wirekit.components.usage-meter.danger', 1.0), // danger/over threshold ratio
    'showValue' => true,             // show "X / Y (Z%)" readout
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Pushery\WireKit\Support\LocalizedNumber;
    use Illuminate\Support\Str;

    // The app supplies used/limit (never recompute usage in the view — see plan
    // pitfalls). limit === null is the "Unlimited" plan tier (no bar, no %).
    $usedNum = (float) $used;
    $isUnlimited = $limit === null;
    $limitNum = $isUnlimited ? null : max(0.0, (float) $limit);

    $ratio = (! $isUnlimited && $limitNum > 0) ? $usedNum / $limitNum : 0.0;
    // Real % can exceed 100 (over-limit) — show it in text; the bar clamps to 100.
    $rawPercent = (! $isUnlimited && $limitNum > 0) ? (int) round($ratio * 100) : null;
    // Mutually-exclusive bands: over > danger > warn > ok. The danger band only
    // exists when `danger` is configured below 1.0 (the docs recommend 0.95); with
    // the default danger=1.0, $isDanger is always false (ratio>=1.0 is already
    // $isOver), so default behavior is unchanged. Each band has its OWN text state
    // below, so the danger band is never signaled by the red bar color alone (a11y).
    $isOver = ! $isUnlimited && $limitNum > 0 && $usedNum >= $limitNum;
    $isDanger = ! $isOver && ! $isUnlimited && $limitNum > 0 && $ratio >= (float) $danger;
    $isWarn = ! $isOver && ! $isDanger && ! $isUnlimited && $ratio >= (float) $warn;

    // Threshold → intent (ok → warn → danger → over). ALSO conveyed by the text
    // line below (never color alone — a11y). Mirrors progress' intent palette.
    $intent = match (true) {
        $isUnlimited => 'neutral',
        $isOver, $isDanger => 'danger',
        $isWarn => 'warning',
        default => 'primary',
    };

    // Reset hint: explicit resetAt wins; else derive from the period cadence.
    $resetNote = $resetAt ?: match ($period) {
        'day' => __('Resets daily'),
        'week' => __('Resets weekly'),
        'month' => __('Resets monthly'),
        'year' => __('Resets yearly'),
        default => null,
    };

    $labelId = 'usage-meter-'.Str::random(6).'-label';
    $fmt = fn ($n) => LocalizedNumber::format((float) $n);

    // Precompute the bar's accessible name (a visible label wins; else a
    // generic "Usage"). Computed here — NOT as inline @if inside the
    // <x-wirekit::progress> tag, which the component-tag compiler can't parse.
    $barLabelledby = $label ? $labelId : null;
    $barAriaLabel = $label ? null : __('Usage');

    $classes = WireKit::resolveClasses('usage-meter', 'base',
        'w-full font-[family-name:var(--font-wk-sans)]',
        $scope
    );
@endphp

<div {{ $attributes->class([$classes]) }} @if($isOver) data-over-limit="true" @endif>
    {{-- Header: label + "X / Y (Z%)" readout (or "Unlimited") --}}
    @if($label || $showValue)
        <div class="mb-1 flex items-baseline justify-between gap-2 text-[length:var(--text-wk-sm)]">
            @if($label)
                <span id="{{ $labelId }}" class="text-[color:var(--color-wk-text)] font-[number:var(--font-wk-heading-weight)]">{{ $label }}</span>
            @else
                <span></span>
            @endif
            @if($showValue)
                <span class="tabular-nums text-[color:var(--color-wk-text-muted)]">
                    @if($isUnlimited)
                        {{ $fmt($usedNum) }}@if($unit) {{ $unit }}@endif
                        <span class="text-[color:var(--color-wk-text-subtle)]">· {{ __('Unlimited') }}</span>
                    @else
                        {{ $fmt($usedNum) }} / {{ $fmt($limitNum) }}@if($unit) {{ $unit }}@endif
                        @if($rawPercent !== null)
                            <span class="text-[color:var(--color-wk-text-subtle)]">({{ $rawPercent }}%)</span>
                        @endif
                    @endif
                </span>
            @endif
        </div>
    @endif

    {{-- The bar — reuses <x-wirekit::progress> (its role="progressbar" + aria-value*
         is the WCAG contract). Omitted for the unlimited tier (no meaningful fill). --}}
    @unless($isUnlimited)
        <x-wirekit::progress
            :value="$usedNum"
            :max="$limitNum"
            :intent="$intent"
            size="sm"
            :aria-labelledby="$barLabelledby"
            :aria-label="$barAriaLabel"
        />
    @endunless

    {{-- Status line — threshold conveyed by TEXT (not color alone). The colored
         token is redundant reinforcement on top of the explicit wording. --}}
    @if($isOver || $isDanger || $isWarn || $resetNote)
        <div class="mt-1 flex items-center justify-between gap-2 text-[length:var(--text-wk-xs)]">
            <span>
                @if($isOver)
                    <span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-danger-text)]">{{ __('Over limit') }}</span>
                @elseif($isDanger)
                    {{-- Danger band [danger, 1.0): the red bar gets matching red TEXT so
                         the band is never conveyed by color alone. More urgent wording
                         than the warning band's "Approaching limit". --}}
                    <span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-danger-text)]">{{ __('Near limit') }}</span>
                @elseif($isWarn)
                    <span class="font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-warning-text)]">{{ __('Approaching limit') }}</span>
                @endif
            </span>
            @if($resetNote)
                <span class="text-[color:var(--color-wk-text-subtle)]">{{ $resetNote }}</span>
            @endif
        </div>
    @endif

    {{-- Optional upgrade CTA hook (slot). The usage-meter owns the meter + the CTA
         hook only; the upgrade/plan-compare surface is a pricing-table recipe. --}}
    @isset($action)
        <div class="mt-2">{{ $action }}</div>
    @endisset
</div>
