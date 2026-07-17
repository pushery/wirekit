@props([
    // Whether the shimmer sweep animates across the text glyphs. Bind it to a
    // Livewire property — :active="$isStreaming" — to run the effect ONLY while
    // streaming / loading and settle to plain text when done. This is the
    // Livewire-native differentiator: the React-only equivalents toggle the
    // effect by conditionally rendering the class; here the server drives it.
    'active' => config('wirekit.components.shimmer.active', true),
    // The wrapper tag. Defaults to an inline <span> so a shimmer drops into a
    // sentence ("Generating response…"); use as="div"/"p" for block text.
    'as' => 'span',
    // Animation duration — any CSS <time>. Overrides the --shimmer-wk-duration
    // token for THIS instance only (slower = calmer, faster = more urgent).
    'duration' => config('wirekit.components.shimmer.duration', null),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // A shimmer wraps text, so only a small allowlist of text tags is sensible.
    // Validate up front so an invalid value resolves to a real allowed tag
    // rather than emitting a broken element.
    $tag = in_array($as, ['span', 'div', 'p', 'strong', 'em'], true)
        ? $as
        : WireKit::validateProp('shimmer', 'as', $as, ['span', 'div', 'p', 'strong', 'em']);

    // .wk-text-shimmer paints an animated gradient over the letterforms via
    // background-clip:text (the glyphs become the mask). It is applied ONLY when
    // active — an inactive shimmer is plain inherited-color text with no
    // transparent fill, so a Livewire :active toggle switches cleanly between
    // the "streaming" and "settled" states with no layout shift.
    $classes = WireKit::resolveClasses('shimmer', 'base', $active ? 'wk-text-shimmer' : '', $scope);

    // Per-instance duration override via the design-token custom property.
    $style = ($active && $duration) ? "--shimmer-wk-duration: {$duration};" : null;

    $attrs = $attributes->class([$classes]);
    if ($style !== null) {
        $attrs = $attrs->merge(['style' => $style]);
    }
@endphp

<{{ $tag }} {{ $attrs }}>{{ $slot }}</{{ $tag }}>
