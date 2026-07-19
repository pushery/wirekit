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
    <x-wirekit::avatar
        :src="$avatar"
        :alt="$name"
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
