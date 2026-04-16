@props([
    'time' => null,
    'icon' => null,
    'variant' => 'default', // default | success | warning | danger
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Each timeline item: vertical connector line + dot/icon + content area.
    // The connector line is drawn via a pseudo-element on the dot container.
    $classes = WireKit::resolveClasses('timeline.item', 'base', implode(' ', [
        'relative',
        'flex',
        'gap-[var(--padding-wk-x-md)]',
    ]), $scope);

    // Dot color per variant — matches component color tokens
    $dotColor = match ($variant) {
        'success' => 'var(--color-wk-success)',
        'warning' => 'var(--color-wk-warning)',
        'danger' => 'var(--color-wk-danger)',
        default => 'var(--color-wk-accent)',
    };
@endphp

<li {{ $attributes->class([$classes]) }}>
    {{-- Vertical connector line + dot indicator.
         Uses inline styles for flex layout to ensure reliable rendering. --}}
    <div style="position: relative; display: flex; flex-direction: column; align-items: center;">
        {{-- Dot or icon indicator --}}
        <div
            style="z-index: 10; display: flex; height: var(--size-wk-xs, 1.5rem); width: var(--size-wk-xs, 1.5rem); flex-shrink: 0; align-items: center; justify-content: center; border-radius: 9999px; background: {{ $dotColor }};"
            aria-hidden="true"
        >
            @if($icon)
                <x-wirekit::icon :name="$icon" class="h-3 w-3 text-[var(--color-wk-accent-fg)]" />
            @else
                {{-- Default inner dot --}}
                <div style="height: 0.5rem; width: 0.5rem; border-radius: 9999px; background: var(--color-wk-accent-fg);"></div>
            @endif
        </div>

        {{-- Vertical connector line — solid line between items.
             Hidden on the last item so the timeline ends cleanly. --}}
        <div
            style="flex-grow: 1; width: 1px; background: var(--color-wk-border);"
            class="[li:last-child_&]:hidden"
            aria-hidden="true"
        ></div>
    </div>

    {{-- Content area: title slot, time, and body text.
         Bottom padding creates visual spacing between items while
         keeping the connector line continuous (no margin gaps).
         Padding is applied via dist/wirekit.css using the
         `data-wk-timeline-item-content` selector instead of inline
         style + `[li:last-child_&]:pb-0`. The inline style previously
         beat the class (no `!important`), so the padding-bottom-zero
         NEVER actually applied — not on the normal last item, and
         especially not when `after="true"` moved the real last item
         to `:nth-last-child(2)`. The CSS rule uses `:has()` to match
         both cases cleanly. --}}
    <div data-wk-timeline-item-content>
        @if(isset($title))
            <div class="text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">
                {{ $title }}
            </div>
        @endif

        @if($time)
            <time class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">
                {{ $time }}
            </time>
        @endif

        @if($slot->isNotEmpty())
            <div style="margin-top: 0.25rem;" class="text-[length:var(--text-wk-md)] text-[var(--color-wk-text)]">
                {{ $slot }}
            </div>
        @endif
    </div>
</li>
