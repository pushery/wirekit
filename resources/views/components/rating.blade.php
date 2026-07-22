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
    use Pushery\WireKit\Support\LocalizedNumber;

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

    // What a readonly rating announces. The component renders a partial star via
    // clip-path, so rounding here would make the picture and the words disagree —
    // it draws 4.2 and would say "4". maxPrecision keeps that precision while
    // dropping a trailing zero, so a clean 4 does not announce as "4.0"; the
    // trim this replaces assumed "." was the decimal separator and would have
    // mangled a localized "4,0".
    $announcedValue = $readonly ? LocalizedNumber::format((float) $numericValue, maxPrecision: 1) : $numericValue;
    $fullStars = (int) floor($numericValue);
    $fraction = $numericValue - $fullStars; // 0.0–0.99 for partial star
@endphp

<div
    {{ $attributes->except('aria-label')->whereDoesntStartWith('wire:model')->class([$wrapperClasses]) }}
    x-data="{ rating: {{ $clamped }}, hovered: 0 }"
>
    @if($label)
        @if($readonly)
            {{-- Plain text, not <label>. A readonly rating renders no form field
                 for a label to point at, so a <label for="…"> here would name an
                 element that does not exist — and a <label> with no control is
                 not a label at all, it is an orphan that assistive tech may drop
                 on the floor along with the text inside it. --}}
            <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}</span>
        @else
            <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
        @endif
    @endif

    {{-- Hidden input for form submission / wire:model.

         Only when the rating is a CONTROL. A readonly rating is a record of what
         someone gave — displaying it must not put a form field on the page. It
         used to regardless, which meant a product grid shipped one stray field
         per card, with a name regenerated on every render that the developer
         could not even exclude from a submit. --}}
    @unless($readonly)
        <input type="hidden" id="{{ $id }}" name="{{ $name }}" :value="rating" {{ $attributes->whereStartsWith('wire:model') }} />
    @endunless

    {{-- Two different things wear the same stars.

         A READONLY rating is a picture of a score: role="img" with one name, and
         nothing inside it to operate. It used to claim role="radiogroup" and mark
         every filled star aria-checked="true" — but a radiogroup is single-select
         by definition, so a 4-star rating announced FOUR simultaneously selected
         radios ("4 stars, selected, 3 stars, selected, …"). Nonsense, and it
         invited the reader to interact with something inert.

         An INTERACTIVE rating really is a radiogroup: picking a score IS choosing
         one of five. That path is unchanged. --}}
    <div
        @if($readonly)
            role="img"
            {{-- The SCORE, not the label. A visible label is already read as text
                 right above these stars, so naming the image with it would say it
                 twice AND lose the number — "Average rating, image" tells the
                 reader nothing about what was rated. Read together it comes out
                 as "Average rating — 4.2 out of 5 stars".

                 An explicit aria-label still wins: the caller knows their page. --}}
            aria-label="{{ $attributes->get('aria-label') ?? __(':value out of :max stars', ['value' => $announcedValue, 'max' => $max]) }}"
        @else
            role="radiogroup"
            aria-label="{{ $label ?? $attributes->get('aria-label') ?? __('Rating') }}"
        @endif
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
                {{-- Silent: the container above already said "4.2 out of 5
                     stars". Naming each star would repeat the score five times. --}}
                <span aria-hidden="true" class="cursor-default">
                    @if($isPartial)
                        {{-- Partial icon: two overlapping SVGs — empty behind, filled clipped in front.

                             `block`, NOT `inline-block`: every full/empty star is a
                             bare <svg>, which Preflight renders display:block, so they
                             sit flush at the top of the flex item. An inline-block
                             wrapper instead baseline-aligns against the surrounding
                             line box, so whenever the strut's ascent (driven by the
                             INHERITED line-height) exceeds the icon height, the partial
                             star drops by the difference — measured 4px low at
                             size="sm" inside a product card. Block-level makes its box
                             model identical to its siblings. --}}
                        <span class="relative block {{ $iconSize }}">
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
                    aria-checked="{{ (int) $value === $i ? 'true' : 'false' }}"
                    :aria-checked="rating >= {{ $i }} ? 'true' : 'false'"
                    aria-label="{{ $i }} {{ $i === 1 ? 'star' : 'stars' }}"
                    @click="rating = {{ $i }}; $el.closest('[x-data]').querySelector('input[type=hidden]').dispatchEvent(new Event('input', { bubbles: true }))"
                    @mouseenter="hovered = {{ $i }}"
                    @mouseleave="hovered = 0"
                    {{-- Radiogroup keyboard model (APG): both axes move the
                         selection, Home/End jump to the ends. ArrowUp aliases
                         ArrowRight (more), ArrowDown aliases ArrowLeft (less). --}}
                    @keydown.arrow-right.prevent="if (rating < {{ $max }}) { rating++; $nextTick(() => $el.nextElementSibling?.focus()) }"
                    @keydown.arrow-up.prevent="if (rating < {{ $max }}) { rating++; $nextTick(() => $el.nextElementSibling?.focus()) }"
                    @keydown.arrow-left.prevent="if (rating > 1) { rating--; $nextTick(() => $el.previousElementSibling?.focus()) }"
                    @keydown.arrow-down.prevent="if (rating > 1) { rating--; $nextTick(() => $el.previousElementSibling?.focus()) }"
                    @keydown.home.prevent="rating = 1; $nextTick(() => $el.parentElement.firstElementChild?.focus())"
                    @keydown.end.prevent="rating = {{ $max }}; $nextTick(() => $el.parentElement.lastElementChild?.focus())"
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
