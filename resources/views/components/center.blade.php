@props([
    'inline' => false,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Default to `flex w-full` so the Center fills its parent and the
    // centering is actually visible (without `w-full`, a bare block-level
    // `display:flex` div in some prose / preview wrappers collapses to its
    // intrinsic content width — defeating the component's purpose).
    // Inline mode keeps the natural `inline-flex` shrink-to-content sizing
    // since it's used for inline badges / chips inside text flow.
    $classes = WireKit::resolveClasses('center', 'base', implode(' ', [
        $inline ? 'inline-flex' : 'flex w-full',
        'items-center justify-center',
    ]), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
