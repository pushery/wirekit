@props([
    'avatar' => null,
    'name' => null,
    'scope' => null,
    // Interactive mode: when true, the profile becomes a focusable
    // button-like element with role="button" + tabindex="0" plus
    // Enter / Space keyboard handlers that synthesize a click event.
    // Used when a profile sits inside a dropdown trigger (or any other
    // parent that listens for click + needs a focusable child for
    // keyboard-reachability). Default false preserves the pre-existing
    // presentational div byte-for-byte.
    'interactive' => false,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $interactive = BooleanProp::from($interactive, false);

    // Profile — avatar + name display for header areas.
    $classes = WireKit::resolveClasses('profile', 'base', implode(' ', [
        'flex items-center',
        'gap-[var(--gap-wk-sm)]',
        // Add focus-visible ring when interactive — same shape as the
        // canonical button focus state (matches the button component).
        $interactive ? 'cursor-pointer focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[var(--color-wk-ring-offset)] rounded-[var(--radius-wk-sm)]' : '',
    ]), $scope);

    // accept either a string URL
    // OR an array shape `['src' => ..., 'initials' => ...]` (matching
    // `message.author` and the avatar-component convention). Pre-fix,
    // passing an array crashed with `htmlspecialchars(): Argument #1
    // must be of type string, array given` because the renderer did
    // `<img src="{{ $avatar }}">` without a normalizer.
    $avatarSrc = is_array($avatar) ? ($avatar['src'] ?? null) : (is_string($avatar) ? $avatar : null);
    $avatarInitials = is_array($avatar) ? ($avatar['initials'] ?? null) : null;
    $avatarAlt = is_array($avatar) ? ($avatar['alt'] ?? '') : '';
@endphp

<div
    @if($interactive)
        tabindex="0"
        role="button"
        x-on:keydown.enter.prevent="$el.click()"
        x-on:keydown.space.prevent="$el.click()"
    @endif
    {{ $attributes->class([$classes]) }}
>
    @if($avatarSrc)
        <img src="{{ $avatarSrc }}" alt="{{ $avatarAlt }}" class="h-8 w-8 rounded-full object-cover" />
    @elseif($avatarInitials)
        {{-- Initials fallback — same deterministic-palette shape as the --}}
        {{-- canonical avatar primitive uses for non-image avatars.       --}}
        <span
            aria-label="{{ $name ?? $avatarInitials }}"
            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-[var(--color-wk-bg-muted)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text)]"
        >{{ $avatarInitials }}</span>
    @endif
    @if($name)
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-body-weight)]">{{ $name }}</span>
    @endif
    {{ $slot }}
</div>
