@props([
    'lines' => 3,
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

    $baseShimmer = 'wk-skeleton bg-[var(--color-wk-bg-skeleton)] rounded-[var(--radius-wk-md)]';
    // Animation: shimmer (default) | pulse | none. Legacy `shimmer=false` → pulse.
    $wkAnim = in_array($animation, ['pulse', 'none'], true) ? $animation
        : (filter_var($shimmer, FILTER_VALIDATE_BOOL) ? 'shimmer' : 'pulse');
    $animAttr = $wkAnim === 'pulse' ? 'data-pulse="true"' : ($wkAnim === 'none' ? 'data-animation="none"' : '');

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
    aria-label="{{ __('Loading') }}"
    aria-busy="true"
    style="width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: auto 80px;"
    {{ $attributes->class([$wrapperClasses]) }}
>
    <div class="space-y-2">
        @for($i = 0; $i < $lines; $i++)
            <div class="{{ $baseShimmer }} h-3 {{ $i === $lines - 1 ? 'w-2/3' : 'w-full' }}" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
        @endfor
    </div>
    <span class="sr-only">Loading content</span>
</div>
