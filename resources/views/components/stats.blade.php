@props([
    'cols' => null,
    // Cascade entrance animations across direct children.
    // null = no stagger (default), true = 75ms step, int = custom ms step.
    'stagger' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Stats grid: responsive columns for <x-wirekit::stat> children.
    // Default: auto-fit grid (each stat ≥ 200px). Override with cols prop.
    $colClasses = match ($cols) {
        '2' => 'grid-cols-1 sm:grid-cols-2',
        '3' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        '4' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4',
        '5' => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5',
        default => 'grid-cols-[repeat(auto-fit,minmax(200px,1fr))]',
    };

    // Stagger — see dist/wirekit.css `.wk-stagger` rules.
    $hasStagger = $stagger !== null && $stagger !== false;
    $staggerStep = is_int($stagger) ? $stagger : 75;

    $classes = WireKit::resolveClasses('stats', 'base', implode(' ', [
        'grid',
        'gap-4',
        'font-[family-name:var(--font-wk-sans)]',
        $hasStagger ? 'wk-stagger' : '',
    ]), $scope);
@endphp

{{-- Stats grid: wraps multiple <x-wirekit::stat> children in a responsive grid --}}
<div
    {{ $attributes->class([$classes, $colClasses]) }}
    @if($hasStagger) style="--wk-stagger-step: {{ $staggerStep }}ms" @endif
>
    {{ $slot }}
</div>
