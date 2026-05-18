@props([
    'label' => null,
    'value' => 0,
    'max' => 5,
    'icon' => 'star',
    'readonly' => false,
    'size' => config('wirekit.components.rating.size', 'md'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $id = $attributes->get('id', $attributes->get('name', 'rating-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    $wrapperClasses = WireKit::resolveClasses('rating', 'base', implode(' ', [
        'inline-flex flex-col gap-1',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Icon size scales with the size prop
    $iconSize = match ($size) {
        'sm' => 'h-4 w-4',
        'lg' => 'h-8 w-8',
        default => 'h-6 w-6',
    };

    // Icon shapes — each entry defines a viewBox and SVG path.
    // All paths are designed for a 24x24 viewBox.
    $iconShapes = [
        'star' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z',
        ],
        'heart' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z',
        ],
        'circle' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z',
        ],
        'square' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M5 3a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5z',
        ],
        'diamond' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M12 2L2 12l10 10 10-10L12 2z',
        ],
        'thumb' => [
            'viewBox' => '0 0 24 24',
            'path' => 'M2 20h2V10H2v10zm20-9a2 2 0 0 0-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L13.17 2 7.59 7.59C7.22 7.95 7 8.45 7 9v10a2 2 0 0 0 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73V11z',
        ],
    ];

    // Fallback to star if icon name is unknown
    $shape = $iconShapes[$icon] ?? $iconShapes['star'];

    // Support fractional values for readonly display (e.g. 3.78 average).
    // Interactive mode always clamps to integers.
    $numericValue = max(0, min((float) $value, (float) $max));
    $clamped = $readonly ? $numericValue : (int) $numericValue;
    $fullStars = (int) floor($numericValue);
    $fraction = $numericValue - $fullStars; // 0.0–0.99 for partial star
@endphp

<div
    {{ $attributes->class([$wrapperClasses]) }}
    x-data="{ rating: {{ $clamped }}, hovered: 0 }"
>
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    {{-- Hidden input for form submission / wire:model --}}
    <input type="hidden" id="{{ $id }}" name="{{ $name }}" :value="rating" {{ $attributes->only('wire:model') }} />

    <div
        role="radiogroup"
        aria-label="{{ $label ?? 'Rating' }}"
        class="inline-flex gap-0.5"
    >
        @for($i = 1; $i <= $max; $i++)
            @if($readonly)
                {{-- Readonly: use <span> instead of <button> — not interactive.
                     Supports fractional fill via clip-path on partial stars. --}}
                @php
                    $isFull = $i <= $fullStars;
                    $isPartial = !$isFull && $i === $fullStars + 1 && $fraction > 0;
                    $isEmpty = !$isFull && !$isPartial;
                @endphp
                <span
                    role="radio"
                    aria-checked="{{ $isFull || $isPartial ? 'true' : 'false' }}"
                    aria-disabled="true"
                    aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                    class="cursor-default"
                >
                    @if($isPartial)
                        {{-- Partial icon: two overlapping SVGs — empty behind, filled clipped in front --}}
                        <span class="relative inline-block {{ $iconSize }}">
                            {{-- Empty icon background --}}
                            <svg aria-hidden="true" class="{{ $iconSize }} text-[color:var(--color-wk-text-subtle)] fill-none absolute inset-0" viewBox="{{ $shape['viewBox'] }}" stroke="currentColor" stroke-width="1.5">
                                <path d="{{ $shape['path'] }}"/>
                            </svg>
                            {{-- Filled icon foreground, clipped to the fractional width --}}
                            <svg aria-hidden="true" class="{{ $iconSize }} text-[color:var(--color-wk-warning)] fill-[var(--color-wk-warning)] absolute inset-0" viewBox="{{ $shape['viewBox'] }}" stroke="currentColor" stroke-width="1.5" style="clip-path: inset(0 {{ (1 - $fraction) * 100 }}% 0 0)">
                                <path d="{{ $shape['path'] }}"/>
                            </svg>
                        </span>
                    @else
                        <svg
                            aria-hidden="true"
                            class="{{ $iconSize }} {{ $isFull ? 'text-[color:var(--color-wk-warning)] fill-[var(--color-wk-warning)]' : 'text-[color:var(--color-wk-text-subtle)] fill-none' }}"
                            viewBox="{{ $shape['viewBox'] }}"
                            stroke="currentColor"
                            stroke-width="1.5"
                        >
                            <path d="{{ $shape['path'] }}"/>
                        </svg>
                    @endif
                </span>
            @else
                {{-- Interactive: clickable buttons with hover/keyboard support.
                     Static aria-checked mirrors the initial value for axe-core's
                     pre-Alpine-init scan; Alpine overrides reactively. --}}
                <button
                    type="button"
                    role="radio"
                    aria-checked="{{ $value >= $i ? 'true' : 'false' }}"
                    :aria-checked="rating >= {{ $i }} ? 'true' : 'false'"
                    aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                    @click="rating = {{ $i }}; $el.closest('[x-data]').querySelector('input[type=hidden]').dispatchEvent(new Event('input', { bubbles: true }))"
                    @mouseenter="hovered = {{ $i }}"
                    @mouseleave="hovered = 0"
                    @keydown.arrow-right.prevent="if (rating < {{ $max }}) { rating++; $nextTick(() => $el.nextElementSibling?.focus()) }"
                    @keydown.arrow-left.prevent="if (rating > 1) { rating--; $nextTick(() => $el.previousElementSibling?.focus()) }"
                    :tabindex="rating === {{ $i }} || (rating === 0 && {{ $i }} === 1) ? '0' : '-1'"
                    class="transition-colors duration-[var(--transition-wk-duration)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)] cursor-pointer"
                >
                    <svg
                        aria-hidden="true"
                        class="{{ $iconSize }} transition-colors duration-[var(--transition-wk-duration)]"
                        :class="(hovered >= {{ $i }} || (!hovered && rating >= {{ $i }}))
                            ? 'text-[color:var(--color-wk-warning)] fill-[var(--color-wk-warning)]'
                            : 'text-[color:var(--color-wk-text-subtle)] fill-none'"
                        viewBox="{{ $shape['viewBox'] }}"
                        stroke="currentColor"
                        stroke-width="1.5"
                    >
                        <path d="{{ $shape['path'] }}"/>
                    </svg>
                </button>
            @endif
        @endfor
    </div>
</div>
