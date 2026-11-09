{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
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

    // First + last initial, read where every component that draws a person from a
    // name reads them, so the same name gets the same letters everywhere.
    $derivedInitials = $initials ?? \Pushery\WireKit\Support\Initials::from($name);

    // An empty portrait address, or one of white space, is no portrait, so the avatar draws the
    // initials in their own color (Support\UrlProp::source()).
    $avatar = \Pushery\WireKit\Support\UrlProp::source($avatar);

    $classes = WireKit::resolveClasses('team-member', 'base', implode(' ', [
        'flex flex-col items-center gap-[var(--gap-wk-sm)] text-center',
    ]), $scope);
@endphp

<li data-wk-prose-skip data-wk-team-member {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{-- The portrait is decorative here, because the name it would announce is the
         very next thing in the card: named, a row would read the person twice, and a
         roster multiplies that by everyone on it.

         `alt=""` marks a portrait and derived initials decorative, and `aria-hidden`
         on the element covers whichever of the avatar's branches renders, the
         fallback silhouette included. Nothing focusable lives inside it, which is the
         condition that makes hiding a subtree legal. --}}
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
        @wirekitLast($role)
            <span data-wk-team-member-role class="block text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $role }}</span>
        @endwirekitLast
    </span>

    {{-- Links slot: put real <x-wirekit::link> elements here. Icon-only links
         need their own accessible name — see the docs page. --}}
    @wirekitLast($slot->hasActualContent())
        <span data-wk-team-member-links class="flex items-center gap-[var(--gap-wk-sm)]">
            {{ $slot }}
        </span>
    @endwirekitLast
</li>
