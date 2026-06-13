@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Standalone <legend> for when a developer wants rich legend content (markup,
    // a badge, a help icon) inside <x-wirekit::field.set> instead of the plain
    // `legend` string prop. Use ONE or the OTHER, never both.
    $classes = WireKit::resolveClasses('field.legend', 'base', 'mb-3 text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]', $scope);
@endphp

<legend {{ $attributes->class([$classes]) }}>{{ $slot }}</legend>
