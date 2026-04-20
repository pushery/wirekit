@props([
    'container' => false,
    'padding' => config('wirekit.components.main.padding', 'lg'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Main — primary content area in app-shell layouts.
    $classes = WireKit::resolveClasses('main', 'base', implode(' ', [
        'flex-1',
        'overflow-y-auto',
    ]), $scope);

    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'p-[var(--space-wk-sm,0.5rem)]',
        'md' => 'p-[var(--space-wk-md,1rem)]',
        'lg' => 'p-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'p-[var(--space-wk-xl,2.5rem)]',
        default => WireKit::validateProp('main', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl']),
    };
@endphp

<main {{ $attributes->class([$classes, $paddingClasses]) }}>
    @if($container)
        <div class="max-w-[var(--size-wk-container-2xl,96rem)] mx-auto w-full">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</main>
