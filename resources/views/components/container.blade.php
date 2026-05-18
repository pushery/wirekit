@props([
    'max' => config('wirekit.components.container.max', 'xl'),
    'padding' => config('wirekit.components.container.padding', 'md'),
    'center' => true,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $maxClasses = match ($max) {
        'sm' => 'max-w-[var(--size-wk-container-sm,40rem)]',
        'md' => 'max-w-[var(--size-wk-container-md,48rem)]',
        'lg' => 'max-w-[var(--size-wk-container-lg,64rem)]',
        'xl' => 'max-w-[var(--size-wk-container-xl,80rem)]',
        '2xl' => 'max-w-[var(--size-wk-container-2xl,96rem)]',
        'full' => 'max-w-full',
        default => WireKit::validateProp('container', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full']),
    };

    // Inline padding reads from `--padding-wk-x-*` so a
    // `<x-wirekit::container>` nested inside `<x-wirekit::main padding="lg">`
    // (which uses the same `--padding-wk-x-lg` token) inherits a single
    // content-edge spine — the inner content's visible-text left edge
    // sits exactly where main's would. Vertical section padding still
    // reads from `--space-wk-*` (consumers apply `py-*` themselves on
    // the container when they want a section rhythm).
    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--padding-wk-x-sm,0.625rem)]',
        'md' => 'px-[var(--padding-wk-x-md,0.75rem)]',
        'lg' => 'px-[var(--padding-wk-x-lg,1rem)]',
        'xl' => 'px-[var(--padding-wk-x-xl,1.5rem)]',
        default => WireKit::validateProp('container', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl']),
    };

    $classes = WireKit::resolveClasses('container', 'base', implode(' ', array_filter([
        'w-full',
        $maxClasses,
        $center ? 'mx-auto' : '',
        $paddingClasses,
    ])), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
