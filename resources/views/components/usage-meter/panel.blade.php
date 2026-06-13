@props([
    'title' => null,        // optional panel heading (e.g. "Plan usage")
    'columns' => 1,         // responsive column count: 1 | 2 | 3
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    // A simple vertical stack of <x-wirekit::usage-meter> rows, with an optional
    // multi-column grid on wider viewports. The panel owns layout only — every
    // meter inside keeps its own threshold/intent logic.
    $cols = (int) WireKit::validateProp('usage-meter', 'columns', (string) $columns, ['1', '2', '3']);

    // The visible heading IS the group's accessible name (via aria-labelledby) —
    // not a duplicate aria-label, which would double-announce the title.
    $titleId = 'usage-meter-panel-'.Str::random(6).'-title';

    // Token-driven gap; the grid collapses to a single column on small screens
    // so each meter keeps a readable width (mobile-first).
    $gridClass = match ($cols) {
        2 => 'grid grid-cols-1 sm:grid-cols-2',
        3 => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3',
        default => 'grid grid-cols-1',
    };

    $classes = WireKit::resolveClasses('usage-meter', 'panel',
        $gridClass.' gap-[var(--space-wk-md)] w-full',
        $scope
    );
@endphp

<div {{ $attributes->class([$classes]) }} @if($title) role="group" aria-labelledby="{{ $titleId }}" @endif>
    @if($title)
        {{-- Panel heading spans every column above the grid; tokens-only typography.
             grid-column is set inline (structural layout, like progress' width %) so
             no extra Tailwind utility enters the bundle. The id links the group's
             accessible name to this visible heading. --}}
        <div id="{{ $titleId }}"
             class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]"
             style="grid-column: 1 / -1;">{{ $title }}</div>
    @endif
    {{ $slot }}
</div>
