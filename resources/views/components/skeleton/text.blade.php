@props([
    'lines' => 3,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Shimmer base class shared with parent skeleton
    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]';

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'text', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Multi-line text placeholder. Last line is shorter for realism. --}}
<div
    role="status"
    aria-live="polite"
    aria-label="Loading"
    aria-busy="true"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div class="space-y-2">
        @for($i = 0; $i < $lines; $i++)
            <div class="{{ $baseShimmer }} h-3 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}"></div>
        @endfor
    </div>
    <span class="sr-only">Loading content</span>
</div>
