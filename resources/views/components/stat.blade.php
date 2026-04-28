@props([
    'label' => null,
    'value' => null,
    'change' => null,
    'trend' => null, // 'up' | 'down' | 'neutral' | null
    'icon' => null,
    'description' => null,
    'citation' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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

<div {{ $attributes->class([$classes]) }}>
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

    {{-- Main metric value — large, heading-weight for visual emphasis.: replaced inline style="font-size:1.5rem;line-height:2rem"
         with token-based --text-wk-2xl (1.5rem) + heading line-height. --}}
    @if($value !== null)
        <div class="text-[length:var(--text-wk-2xl)] leading-[var(--font-wk-heading-line-height,1.25)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)] tabular-nums">
            {{ $value }}
        </div>
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
                <span class="text-[var(--color-wk-text-muted)]">{{ $description }}</span>
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
