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
        // Token, not the raw gap-4 (1rem == --space-wk-md; no visual change, and
        // it stops violating the "no hardcoded sizes" rule).
        'gap-[var(--space-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        $hasStagger ? 'wk-stagger' : '',
    ]), $scope);

    // Merge auto-emitted `--wk-stagger-step` with developer `style=""`
    // so the rendered <div> has ONE style attribute — see feature-grid
    // for the full rationale. Developer style comes second so
    // later-declaration-wins lets explicit overrides take effect.
    $stylePieces = [];
    if ($hasStagger) {
        $stylePieces[] = "--wk-stagger-step: {$staggerStep}ms";
    }
    $developerStyle = trim((string) $attributes->get('style', ''));
    if ($developerStyle !== '') {
        $stylePieces[] = rtrim($developerStyle, '; ');
    }
    $mergedStyle = implode('; ', $stylePieces);
@endphp

{{-- Stats grid: wraps multiple <x-wirekit::stat> children in a responsive grid --}}
<div
    {{ $attributes->except('style')->class([$classes, $colClasses]) }}
    @if($mergedStyle !== '') style="{{ $mergedStyle }}" @endif
>
    {{ $slot }}
</div>
