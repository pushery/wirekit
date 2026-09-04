{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // The person. Without a name there is nobody here.
    'name' => '',
    // Their role ("Head of Platform").
    'role' => null,
    // Portrait. Without one, the initials are derived — never an empty disc.
    'avatar' => null,
    'initials' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('team-member', $attributes->getAttributes());

    // First + last initial, mb_* throughout: names are exactly where non-ASCII
    // lives, and substr() would slice a codepoint in half and emit broken UTF-8.
    $derivedInitials = $initials;

    if ($derivedInitials === null && $name !== '') {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $derivedInitials = match (count($words)) {
            0 => null,
            1 => mb_strtoupper(mb_substr($words[0], 0, 1)),
            default => mb_strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1)),
        };
    }

    $classes = WireKit::resolveClasses('team-member', 'base', implode(' ', [
        'flex flex-col items-center gap-[var(--gap-wk-sm)] text-center',
    ]), $scope);
@endphp

<li data-wk-team-member {{ $attributes->class([$classes]) }}>
    {{-- The portrait is decorative HERE, and only here, because the name it would
         announce is the very next thing in the card. Named, this row read "Ada
         Lovelace, Ada Lovelace, Head of Platform" — once from the avatar and once
         from the text — and a roster multiplies that by everyone on it.

         BOTH attributes are needed, and `alt=""` alone would make it worse. The
         avatar has three branches: a portrait (`alt=""` is the decorative-image
         marker it wants), derived initials, and the fallback silhouette. The
         initials branch hides itself only WHILE a name is present — drop the name
         and it stops hiding, so the reader hears "A L" instead. `aria-hidden` on
         the element covers whichever branch renders. Nothing focusable lives
         inside it, which is the condition that makes hiding a subtree legal. --}}
    <x-wirekit::avatar
        :src="$avatar"
        alt=""
        aria-hidden="true"
        :initials="$derivedInitials"
        :from-initials="$avatar === null"
        size="xl"
    />

    <span class="min-w-0">
        <span data-wk-team-member-name class="block text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $name }}</span>
        @if($role)
            <span data-wk-team-member-role class="block text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $role }}</span>
        @endif
    </span>

    {{-- Links slot: put real <x-wirekit::link> elements here. Icon-only links
         need their own accessible name — see the docs page. --}}
    @if(filled(trim($slot->toHtml())))
        <span data-wk-team-member-links class="flex items-center gap-[var(--gap-wk-sm)]">
            {{ $slot }}
        </span>
    @endif
</li>
