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

    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--space-wk-sm,0.5rem)]',
        'md' => 'px-[var(--space-wk-md,1rem)]',
        'lg' => 'px-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'px-[var(--space-wk-xl,2.5rem)]',
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
