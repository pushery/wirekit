@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]';

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'card', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Card placeholder: image area + title + body text lines --}}
<div
    role="status"
    aria-live="polite"
    aria-label="Loading"
    aria-busy="true"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div class="space-y-3">
        <div class="{{ $baseShimmer }} h-32 w-full"></div>
        <div class="{{ $baseShimmer }} h-4 w-3/4"></div>
        <div class="space-y-2">
            <div class="{{ $baseShimmer }} h-3 w-full"></div>
            <div class="{{ $baseShimmer }} h-3 w-5/6"></div>
        </div>
    </div>
    <span class="sr-only">Loading content</span>
</div>
