{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'animation' => config('wirekit.components.skeleton.animation', 'shimmer'), // shimmer | pulse | none
    'shimmer' => true, // legacy bool — false → pulse (see skeleton.blade.php)
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('skeleton.card', $attributes->getAttributes());

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

    $wrapperClasses = WireKit::resolveClasses('skeleton', 'card', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- Card placeholder: image area + title + body text lines.
     content-visibility: auto + intrinsic-size hint skip off-screen work. --}}
<div
    role="status"
    aria-live="polite"
    aria-label="{{ __('wirekit::Loading') }}"
    {{-- No aria-busy here, deliberately. This element IS the live region, and
         WAI-ARIA defines aria-busy on a live region as "wait before exposing
         this to the user" — set to true and never flipped back, it tells
         assistive technology to withhold the very announcement the region
         exists to make, and the skeleton is removed from the page rather than
         marked done, so the flip never comes. The busy signal belongs on the
         container whose content is still missing, which is the developer's
         element; leaving the attribute off is also what lets them put it there
         and have the shimmer's own pause rule see it. --}}
    {{ $attributes->merge(['style' => 'width: 100%; min-width: 12rem; content-visibility: auto; contain-intrinsic-size: auto 200px;'])->class([$wrapperClasses]) }}
>
    <div class="space-y-3">
        <div class="{{ $baseShimmer }} h-32 w-full" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
        <div class="{{ $baseShimmer }} h-4 w-3/4" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
        <div class="space-y-2">
            <div class="{{ $baseShimmer }} h-3 w-full" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
            <div class="{{ $baseShimmer }} h-3 w-5/6" {!! $animAttr !!} style="background: var(--color-wk-bg-skeleton); border-radius: var(--radius-wk-md);"></div>
        </div>
    </div>
    <span class="sr-only">Loading content</span>
</div>
