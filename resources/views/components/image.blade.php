{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    // Image URL. The component only renders — the developer supplies a ready URL
    // (a signed / ACL-protected download URL works unchanged).
    'src' => null,
    // Accessible name. REQUIRED for a content image; pass an empty string ONLY
    // for a purely decorative image (renders alt="" so screen readers skip it).
    //
    // The default is null rather than '' so the two cases can be told apart. With ''
    // as the default, "the developer marked this decorative" and "the developer forgot"
    // produced byte-identical markup — and the forgotten one is a content image that
    // announces nothing, which no automated check can see either, because `alt=""` IS
    // valid HTML and IS the correct answer for the other case.
    'alt' => null,
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
    // Responsive sources and the width the image takes in the layout, written on the <img>:
    // srcset="a-800.jpg 800w, a-1600.jpg 1600w" with sizes="(min-width: 60rem) 50vw, 100vw".
    'srcset' => null,
    'sizes' => null,
    // The image's intrinsic size in pixels, written on the <img>. The browser reserves the box
    // from their ratio before the file arrives, which is what keeps a layout without `ratio`
    // from shifting.
    'width' => null,
    'height' => null,
    // Fetch priority for the image a page shows first: 'high', 'low' or 'auto'. Null leaves the
    // choice to the browser.
    'fetchpriority' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('image', $attributes->getAttributes());

    // A content image with no name is the failure this component cannot see for itself, so
    // it says so where a developer will read it. Debug only, and a log line rather than an
    // exception: nine of this repo's own twenty-two call sites omit `alt` on decorative
    // images, and throwing would make the correct decorative case unusable to make the
    // incorrect one visible.
    if ($src !== null && $alt === null && config('app.debug')) {
        \Illuminate\Support\Facades\Log::warning(
            'WireKit [image]: no `alt` given, so the image renders as decorative (alt=""). '
            .'Pass alt="…" for a content image, or alt="" to say decorative on purpose. '
            .'src: '.\Pushery\WireKit\Support\LogValue::quote((string) $src)
        );
    }

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $rounded = BooleanProp::from($rounded, false);

    $fitValue = match ($fit) {
        'cover', 'contain' => $fit,
        default => WireKit::validateProp('image', 'fit', $fit, ['cover', 'contain']),
    };
    $fitClass = $fitValue === 'contain' ? 'object-contain' : 'object-cover';

    $fetchpriorityValue = match (true) {
        $fetchpriority === null || $fetchpriority === '' => null,
        in_array($fetchpriority, ['auto', 'high', 'low'], true) => $fetchpriority,
        default => WireKit::validateProp('image', 'fetchpriority', (string) $fetchpriority, ['auto', 'high', 'low']),
    };
    $roundedClass = $rounded ? 'rounded-[var(--radius-wk-md)]' : '';

    $figureClasses = WireKit::resolveClasses('image', 'base', implode(' ', [
        'm-0',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // A ratio reserves the image's space before load. The ratio lives directly on
    // the <img> (modern
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
    <img data-wk-prose-skip
        src="{{ $src }}"
        alt="{{ $alt ?? '' }}"
        loading="{{ $loading }}"
        decoding="async"
        @if(filled($srcset)) srcset="{{ $srcset }}" @endif
        @if(filled($sizes)) sizes="{{ $sizes }}" @endif
        @if(filled($width)) width="{{ $width }}" @endif
        @if(filled($height)) height="{{ $height }}" @endif
        @if($fetchpriorityValue !== null) fetchpriority="{{ $fetchpriorityValue }}" @endif
        @if($ratio) style="aspect-ratio: {{ $ratio }}" @endif
        class="{{ $imgClasses }}"
    />

    @if($caption)
        <figcaption class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            {{ $caption }}
        </figcaption>
    @endif
</figure>
