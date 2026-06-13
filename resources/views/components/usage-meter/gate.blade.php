@props([
    'over' => false,        // true = limit reached, gate the action
    'reason' => null,       // why the action is blocked (shown when over)
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // A plan-paywall gate: when the usage limit is reached, the wrapped action
    // (default slot) is dimmed + made inert, an explanatory reason is shown, and
    // an optional upgrade CTA (the `action` slot) is surfaced. Open state renders
    // the slot untouched — zero overhead when the developer is under limit.
    $isOver = filter_var($over, FILTER_VALIDATE_BOOLEAN);

    $classes = WireKit::resolveClasses('usage-meter', 'gate', 'w-full', $scope);
@endphp

<div {{ $attributes->class([$classes]) }} @if($isOver) data-over-limit="true" @endif>
    @if($isOver)
        {{-- Gated: the action is visible but inert (aria-disabled + inert so it is
             skipped by AT and unclickable). Dimmed via the muted opacity token. --}}
        <div aria-disabled="true" inert
             class="pointer-events-none select-none opacity-[var(--opacity-wk-disabled)]">
            {{ $slot }}
        </div>
        {{-- Reason + upgrade CTA. The reason is the accessible explanation that
             pairs with aria-disabled (color alone never conveys the gate — a11y). --}}
        <div class="mt-2 flex flex-wrap items-center justify-between gap-[var(--space-wk-sm)]">
            @if($reason)
                <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $reason }}</span>
            @endif
            @isset($action)
                <span>{{ $action }}</span>
            @endisset
        </div>
    @else
        {{-- Under limit — render the action untouched. --}}
        {{ $slot }}
    @endif
</div>
