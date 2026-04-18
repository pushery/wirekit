@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-md)]';

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'avatar', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Avatar placeholder: circular image + two short text lines (name + subtitle) --}}
<div
    role="status"
    aria-live="polite"
    aria-label="Loading"
    aria-busy="true"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div class="flex items-center gap-3">
        <div class="{{ $baseShimmer }} h-10 w-10 rounded-[var(--radius-wk-full)]"></div>
        <div class="flex flex-col gap-2 flex-1">
            <div class="{{ $baseShimmer }} h-3 w-1/3"></div>
            <div class="{{ $baseShimmer }} h-2 w-1/4"></div>
        </div>
    </div>
    <span class="sr-only">Loading content</span>
</div>
