@props([
    'rows' => 5,
    'cols' => 4,
    'shimmer' => true, // false → pulse mode (see skeleton.blade.php)
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $baseShimmer = 'wk-skeleton';
    $pulseAttr = filter_var($shimmer, FILTER_VALIDATE_BOOL) ? '' : 'data-pulse="true"';

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'table', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Vary column widths for realism (percentage values for inline styles)
    $colWidths = ['100%', '75%', '50%', '66%', '83%', '33%'];
    // content-visibility intrinsic size hint scales with row count.
    $intrinsicHeight = max(80, ($rows + 1) * 28).'px';
@endphp

{{-- Table placeholder: header row + N body rows, each with M cells.
     Uses inline styles for sizing/layout to ensure rendering in all environments.
     content-visibility: auto + contain-intrinsic-size let the browser skip
     rendering for off-screen instances entirely. --}}
<div
    role="status"
    aria-live="polite"
    aria-label="Loading"
    aria-busy="true"
    style="width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: auto {{ $intrinsicHeight }};"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        {{-- Header row — slightly taller, full-width --}}
        <div style="display: flex; gap: 1rem;">
            @for($c = 0; $c < $cols; $c++)
                <div class="{{ $baseShimmer }}" {!! $pulseAttr !!} style="height: 1rem; flex: 1; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            @endfor
        </div>

        {{-- Separator --}}
        <div class="{{ $baseShimmer }}" {!! $pulseAttr !!} style="height: 1px; width: 100%; opacity: 0.5; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>

        {{-- Body rows --}}
        @for($r = 0; $r < $rows; $r++)
            <div style="display: flex; gap: 1rem;">
                @for($c = 0; $c < $cols; $c++)
                    <div class="{{ $baseShimmer }}" {!! $pulseAttr !!} style="height: 0.75rem; flex: 1; max-width: {{ $colWidths[($r + $c) % count($colWidths)] }}; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
                @endfor
            </div>
        @endfor
    </div>
    <span class="sr-only">Loading content</span>
</div>
