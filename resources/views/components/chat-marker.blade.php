@props([
    // default    — a centered inline status / system note row
    // border     — the same row with a hairline under it (row boundaries)
    // separator  — a centered label flanked by rules (a date break)
    'variant' => 'default',
    // Optional leading glyph (decorative — the row text carries the meaning).
    'icon' => null,
    // Renders the row as a live region so a text swap inside it is ANNOUNCED.
    // Use for streaming / progress rows ("Thinking…", "Explored 4 files").
    'status' => false,
    // Shimmers the label — the "work is in flight" affordance. Implies busy.
    'shimmer' => false,
    // Semantic color for system notes ("Rate limit reached").
    'intent' => 'neutral',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $status = BooleanProp::from($status, false);
    $shimmer = BooleanProp::from($shimmer, false);

    $variantValue = in_array($variant, ['default', 'border', 'separator'], true)
        ? $variant
        : WireKit::validateProp('chat-marker', 'variant', $variant, ['default', 'border', 'separator']);

    $intentValue = in_array($intent, ['neutral', 'info', 'success', 'warning', 'danger'], true)
        ? $intent
        : WireKit::validateProp('chat-marker', 'intent', $intent, ['neutral', 'info', 'success', 'warning', 'danger']);

    // Full literal class strings resolved through a match so the drift auditor
    // can statically harvest every one of them.
    $intentClass = match ($intentValue) {
        'info' => 'text-[color:var(--color-wk-info-text)]',
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'warning' => 'text-[color:var(--color-wk-warning-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
        default => 'text-[color:var(--color-wk-text-muted)]',
    };

    $variantClass = match ($variantValue) {
        'border' => 'border-b border-[var(--color-wk-border)] pb-[var(--space-wk-sm)]',
        default => '',
    };

    $classes = WireKit::resolveClasses('chat-marker', 'base', implode(' ', array_filter([
        'flex w-full items-center justify-center gap-[var(--gap-wk-sm)]',
        'text-[length:var(--text-wk-xs)]',
        $intentClass,
        $variantClass,
    ])), $scope);
@endphp

@if($variantValue === 'separator')
    {{-- Delegate to the divider rather than re-implementing label-between-rules:
         one implementation, no drift.

         The divider is a `role="separator"`, and assistive technology commonly
         announces that role WITHOUT reading its inner text — which would make a
         date break ("Today") silent in a transcript. Giving the separator an
         explicit accessible NAME fixes that without changing the divider's own
         contract: AT announces "Today, separator". --}}
    <x-wirekit::divider
        :label="trim((string) $slot)"
        :aria-label="trim((string) $slot)"
        {{ $attributes }}
    />
@else
    <div
        data-wk-chat-marker
        data-variant="{{ $variantValue }}"
        @if($status)
            {{-- The live region is rendered up front and stays in the DOM, so a
                 wire:stream / wire:poll text swap inside it actually announces.
                 A region that appears at the same moment its text does is inert
                 to assistive technology — the gotcha this bakes out. --}}
            role="status"
            aria-live="polite"
        @endif
        {{-- aria-busy tracks shimmer, and is global — it does NOT depend on the
             live region. A `status` region carries it either way so the settled
             state reads as an explicit "not busy" that flips to "busy" when work
             starts; `shimmer` on its own also carries it, because the docs say
             shimmer implies aria-busy and it used to sit inside the @if above,
             emitting nothing at all without `status`. Present whenever there is
             something to say about — a region to settle, or a shimmer to flag. --}}
        @if($status || $shimmer) aria-busy="{{ $shimmer ? 'true' : 'false' }}" @endif
        {{ $attributes->class([$classes]) }}
    >
        @if($icon)
            <x-wirekit::icon :name="$icon" size="sm" aria-hidden="true" class="shrink-0" />
        @endif

        <span data-wk-chat-marker-content>
            @if($shimmer)
                <x-wirekit::shimmer>{{ $slot }}</x-wirekit::shimmer>
            @else
                {{ $slot }}
            @endif
        </span>
    </div>
@endif
