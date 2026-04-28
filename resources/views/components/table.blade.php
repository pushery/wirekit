@props([
    'striped' => config('wirekit.components.table.striped', false),
    'hoverable' => config('wirekit.components.table.hoverable', false),
    'compact' => config('wirekit.components.table.compact', false),
    'responsive' => config('wirekit.components.table.responsive', true),
    'stickyHeader' => false,
    'alpineSort' => false, // enable client-side Alpine sorting (no Livewire needed)
    // WCAG 2.1.1 (Keyboard) — when stickyHeader makes the table body
    // scroll-confined, the wrapper becomes a focusable scrollable region
    // and gets a name so screen-reader users can recognise it.
    'tableLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Base table classes — full width, collapse borders, use design tokens for typography
    $classes = WireKit::resolveClasses('table', 'base', implode(' ', [
        'w-full',
        'border-collapse',
        'text-left',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);

    // Flag classes passed via data attributes so sub-components (row, td, th) can
    // react to them via CSS selectors. This keeps logic in one place and avoids
    // prop drilling through @aware across 6 different sub-components.
    $tableAttrs = [];
    if ($striped) {
        $tableAttrs[] = 'data-wk-striped';
    }
    if ($hoverable) {
        $tableAttrs[] = 'data-wk-hoverable';
    }
    if ($compact) {
        $tableAttrs[] = 'data-wk-compact';
    }
    if ($stickyHeader) {
        $tableAttrs[] = 'data-wk-sticky-header';
    }
@endphp

{{-- Wrap in responsive container for horizontal scroll on narrow screens --}}
@if($responsive)
<div
    class="w-full overflow-x-auto wk-scrollbar {{ $stickyHeader ? 'max-h-96 overflow-y-auto' : '' }}"
    @if($stickyHeader)
        tabindex="0"
        role="region"
        aria-label="{{ $tableLabel ?? 'Scrollable table' }}"
    @endif
>
@endif
    <table {{ $attributes->class([$classes]) }} @foreach($tableAttrs as $attr) {{ $attr }} @endforeach @if($alpineSort) x-data="wirekitTableSort()" @endif>
        {{ $slot }}
    </table>
@if($responsive)
</div>
@endif
