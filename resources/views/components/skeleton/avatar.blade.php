@props([
    'animation' => config('wirekit.components.skeleton.animation', 'shimmer'), // shimmer | pulse | none
    'shimmer' => true, // legacy bool — false → pulse (see skeleton.blade.php)
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-skeleton)] rounded-[var(--radius-wk-md)]';
    // Animation: shimmer (default) | pulse | none. Legacy `shimmer=false` → pulse.
    $wkAnim = in_array($animation, ['pulse', 'none'], true) ? $animation
        : (filter_var($shimmer, FILTER_VALIDATE_BOOL) ? 'shimmer' : 'pulse');
    $animAttr = $wkAnim === 'pulse' ? 'data-pulse="true"' : ($wkAnim === 'none' ? 'data-animation="none"' : '');

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'avatar', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Avatar placeholder: circular image + two short text lines (name + subtitle).
     content-visibility: auto + intrinsic-size hint skip off-screen work. --}}
<div
    role="status"
    aria-live="polite"
    aria-label="{{ __('Loading') }}"
    aria-busy="true"
    style="width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: auto 60px;"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div class="flex items-center gap-3">
        <div class="{{ $baseShimmer }} h-10 w-10 rounded-[var(--radius-wk-full)]" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton);"></div>
        <div class="flex flex-col gap-2 flex-1">
            <div class="{{ $baseShimmer }} h-3 w-1/3" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            <div class="{{ $baseShimmer }} h-2 w-1/4" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
        </div>
    </div>
    <span class="sr-only">Loading content</span>
</div>
