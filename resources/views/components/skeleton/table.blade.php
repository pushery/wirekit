@props([
    'rows' => 5,
    'cols' => 4,
    'animation' => config('wirekit.components.skeleton.animation', 'shimmer'), // shimmer | pulse | none
    'shimmer' => true, // legacy bool — false → pulse (see skeleton.blade.php)
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $shimmer = BooleanProp::from($shimmer, true);

    $baseShimmer = 'wk-skeleton';
    // Animation: shimmer (default) | pulse | none. Legacy `shimmer=false` → pulse.
    $wkAnim = in_array($animation, ['pulse', 'none'], true) ? $animation
        : (filter_var($shimmer, FILTER_VALIDATE_BOOL) ? 'shimmer' : 'pulse');
    $animAttr = $wkAnim === 'pulse' ? 'data-pulse="true"' : ($wkAnim === 'none' ? 'data-animation="none"' : '');

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
    aria-label="{{ __('Loading') }}"
    aria-busy="true"
    style="width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: auto {{ $intrinsicHeight }};"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
        {{-- Header row — slightly taller, full-width --}}
        <div style="display: flex; gap: 1rem;">
            @for($c = 0; $c < $cols; $c++)
                <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 1rem; flex: 1; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            @endfor
        </div>

        {{-- Separator --}}
        <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 1px; width: 100%; opacity: 0.5; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>

        {{-- Body rows --}}
        @for($r = 0; $r < $rows; $r++)
            <div style="display: flex; gap: 1rem;">
                @for($c = 0; $c < $cols; $c++)
                    <div class="{{ $baseShimmer }}" {!! $animAttr !!} style="height: 0.75rem; flex: 1; max-width: {{ $colWidths[($r + $c) % count($colWidths)] }}; background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
                @endfor
            </div>
        @endfor
    </div>
    <span class="sr-only">Loading content</span>
</div>
