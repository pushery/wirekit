@props([
    'lines' => 3,
    'shimmer' => true, // false → pulse mode (see skeleton.blade.php)
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-skeleton)] rounded-[var(--radius-wk-md)]';
    $pulseAttr = filter_var($shimmer, FILTER_VALIDATE_BOOL) ? '' : 'data-pulse="true"';

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'text', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Multi-line text placeholder. Last line is shorter for realism.
     content-visibility: auto + contain-intrinsic-size let the browser
     skip rendering work for off-screen instances entirely. --}}
<div
    role="status"
    aria-live="polite"
    aria-label="Loading"
    aria-busy="true"
    style="width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: auto 80px;"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div class="space-y-2">
        @for($i = 0; $i < $lines; $i++)
            <div class="{{ $baseShimmer }} h-3 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}" {!! $pulseAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
        @endfor
    </div>
    <span class="sr-only">Loading content</span>
</div>
