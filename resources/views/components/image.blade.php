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
    // would produce identical markup — and the forgotten one is a content image that
    // announces nothing, which no automated check can see either, because `alt=""` IS
    // valid HTML and IS the correct answer for the other case.
    'alt' => null,
    // Optional visible caption — renders a <figcaption> under the image.
    'caption' => null,
    // The language of `alt` and of `caption` where either differs from the page's, written on the
    // <img> and on the <figcaption> alone: `lang` on the tag lands on the <figure> and would reach
    // both. A caption may be an Htmlable as well, which is written as it is.
    'altLang' => null,
    'captionLang' => null,
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
    // Art direction: another file, often another crop, for a media condition. Each entry is an
    // array with `srcset` and, as it needs them, `media`, `sizes`, `type` (`image/avif`) and the
    // file's `width` and `height` in pixels. They render as `<source>` elements in a `<picture>`
    // before the image, and the browser loads the first that matches, else `src`. With `width`
    // and `height` on each, the box takes the ratio of the source the browser chose before the
    // file arrives, so a portrait crop on a phone and a landscape one on a desk do not move the
    // page as they load. `alt`, `loading` and `fetchpriority` stay on the image.
    'sources' => [],
    // The element the image roots in. A `figure` by default. `span` where the image stands in
    // text-level content, such as inside a button, which takes no figure; the root then renders
    // as a block, and a caption as a plain line under the image.
    'as' => 'figure',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('image', $attributes->getAttributes());

    // The languages of the alternative text and the caption as HTML reads them: `pt_BR`, the form
    // a locale of the application takes, is the tag `pt-BR`, and a blank one is none.
    $altLang = \Pushery\WireKit\Support\LanguageTag::of($altLang);
    $captionLang = \Pushery\WireKit\Support\LanguageTag::of($captionLang);

    // Interpolated into the opening and the closing tag, where Blade's escaping lets a space or an
    // `=` through: the shape is checked, as for `tooltip` and `card`.
    $as = WireKit::tagName('image', (string) $as);
    // A figcaption belongs to a figure alone; under another root the caption is a line of its own.
    $captionTag = $as === 'figure' ? 'figcaption' : 'span';
    // Echoed into the tag or bound, the URL is written escaped once, and an empty one or one of
    // white space is no address (Support\UrlProp::source()).
    $src = \Pushery\WireKit\Support\UrlProp::source($src);

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
    $rounded = BooleanProp::from($rounded, false, 'image', 'rounded');

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

    // The sources that name a file. One without `srcset` would select nothing, so it is left out,
    // and a value of the wrong kind is read as no source at all. The picture below opens and
    // closes in directives at the start of their lines, so an image without sources renders
    // the image alone, with no trace of the picture, and `contents` takes the picture's own box
    // out of the layout: the image sizes against the figure as it does without one.
    $sources = \Pushery\WireKit\Support\ListProp::renumbered($sources, 'image', 'sources');
    $pictureSources = array_values(array_filter(
        is_array($sources) ? $sources : [],
        static fn ($source): bool => is_array($source) && filled($source['srcset'] ?? null),
    ));

    // An image with no address of any kind has nothing to show, and an `<img>` with an empty `src`
    // shows the sign of a broken image: nothing is drawn, as a badge without text draws nothing.
    // With a `srcset` or a source and no `src`, the image is drawn without the attribute.
    $hasAddress = $src !== null || filled($srcset) || $pictureSources !== [];

    $figureClasses = WireKit::resolveClasses('image', 'base', implode(' ', [
        'm-0',
        // A figure is a block of its own; another root is made one, so the image sits the same.
        $as === 'figure' ? '' : 'block',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // A ratio reserves the image's space before load. The ratio lives directly on
    // the <img> (modern
    // aspect-ratio + object-fit) rather than on a wrapper with an absolutely-
    // positioned fill image: an absolute-fill image contributes nothing to its
    // parent's intrinsic width, so a `width: fit-content` context (e.g. the docs
    // preview shell) measures the box as 0 and the image collapses to nothing.
    // An in-flow <img> keeps its intrinsic width, so it renders in any context.
    // One value for the ratio, so a `;` in it carries no declaration of its own (Support\CssValue).
    $ratio = \Pushery\WireKit\Support\CssValue::prop($ratio, 'image', 'ratio');
    $imgClasses = $ratio
        ? implode(' ', ['block w-full h-auto max-w-full', $fitClass, $roundedClass])
        : implode(' ', ['block h-auto max-w-full', $roundedClass]);

    // A width or height that is no number, NAN or INF, is no dimension: the attribute is left out
    // rather than written as text, which PHP 8.5 warns about for NAN.
    $width = is_float($width) && ! is_finite($width) ? null : $width;
    $height = is_float($height) && ! is_finite($height) ? null : $height;
@endphp

@if($hasAddress)
<{{ $as }} data-wk-prose-skip {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$figureClasses])) }}>
@if($pictureSources !== [])
    <picture class="contents">
@foreach($pictureSources as $source)
        <source srcset="{{ $source['srcset'] }}"@if(filled($source['media'] ?? null)) media="{{ $source['media'] }}"@endif @if(filled($source['sizes'] ?? null)) sizes="{{ $source['sizes'] }}"@endif @if(filled($source['type'] ?? null)) type="{{ $source['type'] }}"@endif @if(filled($source['width'] ?? null)) width="{{ $source['width'] }}"@endif @if(filled($source['height'] ?? null)) height="{{ $source['height'] }}"@endif>
@endforeach
@endif
    <img data-wk-prose-skip
        @if($src !== null) src="{{ $src }}" @endif
        alt="{{ $alt ?? '' }}"
        @if(filled($altLang)) lang="{{ $altLang }}" @endif
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
@if($pictureSources !== [])
    </picture>
@endif

    @if($caption)
        <{{ $captionTag }} data-wk-prose-skip @if(filled($captionLang)) lang="{{ $captionLang }}" @endif @class(['block' => $captionTag === 'span', 'mt-[var(--space-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]'])>
            {{ $caption }}
        </{{ $captionTag }}>
    @endif
</{{ $as }}>
@endif