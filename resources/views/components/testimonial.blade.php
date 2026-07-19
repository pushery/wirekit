@props([
    // Who said it. An unattributed testimonial is just marketing copy in
    // quotation marks, so this carries the whole credibility of the block.
    'author' => '',
    // Their role / company ("CTO, Acme").
    'role' => null,
    // Avatar image for the author. Without one, the author's initials are
    // derived and rendered instead — never an empty gray circle.
    'avatar' => null,
    // Override the derived initials (non-Latin names, mononyms, brand accounts).
    'initials' => null,
    // Company logo shown beside the attribution.
    'logo' => null,
    // Accessible name for the logo. Leave null when the author is already named
    // in text (the logo is then decoration and stays silent); pass it when the
    // logo is the ONLY attribution.
    'logoAlt' => null,
    // Optional star rating (0..5). Always read-only — a testimonial is a record,
    // not an input.
    'rating' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $ratingValue = ($rating === null || $rating === '') ? null : (float) $rating;

    // Derive initials from the author name so an avatar-less testimonial still
    // shows a person, not a blank disc. Take the first letter of the first and
    // last word — "Ada Lovelace" -> "AL", "Ada" -> "A". mb_* throughout: names
    // are exactly where non-ASCII lives, and substr() would slice a codepoint
    // in half and emit broken UTF-8.
    $derivedInitials = $initials;

    if ($derivedInitials === null && $author !== '') {
        $words = preg_split('/\s+/u', trim($author), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $derivedInitials = match (count($words)) {
            0 => null,
            1 => mb_strtoupper(mb_substr($words[0], 0, 1)),
            default => mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)),
        };
    }

    $classes = WireKit::resolveClasses('testimonial', 'base', implode(' ', [
        'flex h-full flex-col gap-[var(--gap-wk-md)]',
        'rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- figure + blockquote + figcaption is the real semantic shape of a quotation
     with an attribution: assistive tech announces it as a quote and ties the
     attribution to it. A stack of divs says nothing.

     Deliberately NOT built on the message component — that is a chat bubble
     (author, timestamp, side, reactions). This is a cited quotation. --}}
<figure data-wk-testimonial {{ $attributes->class([$classes]) }}>
    @if($ratingValue !== null)
        <x-wirekit::rating :value="$ratingValue" readonly size="sm" data-wk-testimonial-rating />
    @endif

    <blockquote data-wk-testimonial-quote class="flex-1 text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]">
        {{ $slot }}
    </blockquote>

    <figcaption data-wk-testimonial-author class="flex items-center gap-[var(--gap-wk-sm)]">
        @if($avatar || $derivedInitials)
            <x-wirekit::avatar
                :src="$avatar"
                :alt="$author"
                :initials="$derivedInitials"
                :from-initials="$avatar === null"
                size="sm"
            />
        @endif

        <span class="min-w-0 flex-1">
            <span class="block truncate text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $author }}</span>
            @if($role)
                <span data-wk-testimonial-role class="block truncate text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $role }}</span>
            @endif
        </span>

        @if($logo)
            {{-- A bare img, not the image component: that one always wraps its
                 output in a figure, and a figure nested inside this figcaption
                 would make a screen reader announce the company mark as its own
                 "figure" in the middle of the attribution. --}}
            <img
                src="{{ $logo }}"
                alt="{{ $logoAlt ?? '' }}"
                data-wk-testimonial-logo
                class="h-6 w-auto shrink-0 opacity-70"
            />
        @endif
    </figcaption>
</figure>
