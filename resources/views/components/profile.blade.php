@props([
    'avatar' => null,
    'name' => null,
    'scope' => null,
    // Interactive mode: when true, the profile becomes a focusable
    // button-like element with role="button" + tabindex="0" plus
    // Enter / Space keyboard handlers that synthesise a click event.
    // Used when a profile sits inside a dropdown trigger (or any other
    // parent that listens for click + needs a focusable child for
    // keyboard-reachability). Default false preserves the pre-existing
    // presentational div byte-for-byte.
    'interactive' => false,
])

@php
    use Pushery\WireKit\WireKit;

    // Profile — avatar + name display for header areas.
    $classes = WireKit::resolveClasses('profile', 'base', implode(' ', [
        'flex items-center',
        'gap-[var(--gap-wk-sm)]',
        // Add focus-visible ring when interactive — same shape as the
        // canonical button focus state (matches the button component).
        $interactive ? 'cursor-pointer focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[var(--color-wk-ring-offset)] rounded-[var(--radius-wk-sm)]' : '',
    ]), $scope);
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
    @if($avatar)
        <img src="{{ $avatar }}" alt="" class="h-8 w-8 rounded-full object-cover" />
    @endif
    @if($name)
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] font-[number:var(--font-wk-body-weight)]">{{ $name }}</span>
    @endif
    {{ $slot }}
</div>
