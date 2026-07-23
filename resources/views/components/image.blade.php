@props([
    // Image URL. The component only renders — the developer supplies a ready URL
    // (a signed / ACL-protected download URL works unchanged).
    'src' => null,
    // Accessible name. REQUIRED for a content image; pass an empty string ONLY
    // for a purely decorative image (renders alt="" so screen readers skip it).
    'alt' => '',
    // Optional visible caption — renders a <figcaption> under the image.
    'caption' => null,
    // Optional intrinsic aspect-ratio ("16/9", "4/3", "1/1", or a number). Sizes
    // the box BEFORE the image loads, so the layout never shifts (CLS-safe) even
    // when the natural dimensions are unknown. Null → the image sizes itself.
    'ratio' => null,
    // object-fit for a ratio-boxed image: 'cover' (fill + crop) or 'contain'
    // (letterbox, no crop). Ignored without a ratio.
    'fit' => 'cover',
    // Corner rounding token: false (none) or true (--radius-wk-md).
    'rounded' => false,
    // Native lazy-loading. Eager only for above-the-fold hero images.
    'loading' => 'lazy',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $rounded = BooleanProp::from($rounded, false);

    $fitValue = match ($fit) {
        'cover', 'contain' => $fit,
        default => WireKit::validateProp('image', 'fit', $fit, ['cover', 'contain']),
    };
    $fitClass = $fitValue === 'contain' ? 'object-contain' : 'object-cover';
    $roundedClass = $rounded ? 'rounded-[var(--radius-wk-md)]' : '';

    $figureClasses = WireKit::resolveClasses('image', 'base', implode(' ', [
        'm-0',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // A ratio boxes the image (absolute-fill inside a sized wrapper) so the space
    // is reserved before load. The ratio lives DIRECTLY on the <img> (modern
    // aspect-ratio + object-fit) rather than on a wrapper with an absolutely-
    // positioned fill image: an absolute-fill image contributes nothing to its
    // parent's intrinsic width, so a `width: fit-content` context (e.g. the docs
    // preview shell) measures the box as 0 and the image collapses to nothing.
    // An in-flow <img> keeps its intrinsic width, so it renders in any context.
    $imgClasses = $ratio
        ? implode(' ', ['block w-full h-auto max-w-full', $fitClass, $roundedClass])
        : implode(' ', ['block h-auto max-w-full', $roundedClass]);
@endphp

<figure {{ $attributes->class([$figureClasses]) }}>
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        decoding="async"
        @if($ratio) style="aspect-ratio: {{ $ratio }}" @endif
        class="{{ $imgClasses }}"
    />

    @if($caption)
        <figcaption class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            {{ $caption }}
        </figcaption>
    @endif
</figure>
