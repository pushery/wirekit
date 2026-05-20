{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'container' => false,
    'padding' => config('wirekit.components.main.padding', 'lg'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Main — primary content area in app-shell layouts.
    // `wk-main` marker — load-bearing against developer prose
    // `max-width: 75ch` clamps (see footer.blade.php for the full
    // rationale).
    $classes = WireKit::resolveClasses('main', 'base', implode(' ', [
        'wk-main',
        'flex-1',
        'overflow-y-auto',
    ]), $scope);

    // Horizontal padding uses the same `--padding-wk-x-{size}` tokens as
    // `<x-wirekit::header>`, so a sibling Header + Main pair (the canonical
    // app-shell layout) shares one vertical alignment line. Vertical padding
    // stays on the generic `--space-wk-{size}` scale for breathing room.
    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--padding-wk-x-sm)] py-[var(--space-wk-sm,0.5rem)]',
        'md' => 'px-[var(--padding-wk-x-md)] py-[var(--space-wk-md,1rem)]',
        'lg' => 'px-[var(--padding-wk-x-lg)] py-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'px-[var(--padding-wk-x-xl)] py-[var(--space-wk-xl,2.5rem)]',
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
