{{-- optimistic-ui: n/a — client-only
     The thumbnails switch which image is shown. All of it happens in the browser. --}}
@props([
    // The empty state. `empty` REPLACES the body rather than sitting beside it: the screen a
    // new user sees FIRST is the one with no data, and a single muted sentence can only say
    // that nothing is here — it cannot say what to do about it, which is the whole job of that
    // screen. `emptyText` is the default, so a caller that does not care changes nothing.
    // Same shape as data-table, which is where the reasoning was first written down.
    'emptyText' => __('wirekit::Nothing here yet'),
    // List of images: each an array with 'src' (required), 'alt' (required for a
    // content image), and optional 'caption' and 'full'. A plain string is treated
    // as a src with an empty alt (decorative) — pass the array form for real content.
    // 'altLang' and 'captionLang' name the language of either text where it differs from the
    // page's, in the grid and in the lightbox; a 'caption' may be an Htmlable.
    //
    // 'full' is the address the LIGHTBOX loads, and it defaults to 'src'. A grid tile is a
    // few hundred pixels wide with ten of them on a page; the zoom view is the whole screen.
    // Serving one address to both is the compromise an image ladder exists to end.
    'images' => [],
    // Responsive grid column spec, forwarded to the grid component (e.g.
    // "2 md:3 lg:4"). Literal handling lives in grid — never interpolated here.
    'columns' => '2 md:3 lg:4',
    'gap' => 'md',
    // Thumbnail aspect-ratio (CLS-safe). Null → images size themselves.
    'ratio' => '1/1',
    // object-fit forwarded to each thumbnail. 'cover' (default) crops to a
    // uniform grid — right for mixed-orientation photo sets. Use 'contain' when
    // a single image must be shown whole (e.g. one portrait shot the crop would
    // slice) — the thumbnail then letterboxes inside its ratio box instead.
    'fit' => 'cover',
    // Enable the click-to-zoom lightbox. When false the grid is static.
    'lightbox' => true,
    // Per-item overlay render-callback: a closure `fn($item, $i)` that
    // returns the markup to layer OVER thumbnail #$i (a badge, a report control, an
    // "AI-generated" label). It renders as a sibling of the zoom trigger — not nested
    // inside it — so the overlay wrapper is pointer-events-none and the thumbnail still
    // opens the lightbox; interactive controls inside the overlay opt back in with
    // `pointer-events-auto`. Return a view or HtmlString for HTML (a plain string is
    // escaped). Null → no overlay.
    // It covers the THUMBNAIL only; the zoom view takes `zoomOverlay` below.
    'itemOverlay' => null,
    // The zoom view's own overlay: the same `fn($item, $i)` shape as itemOverlay, laid over the
    // image in the lightbox instead of over the thumbnail. A separate callback because the two
    // surfaces differ (an overlay written for a square tile can sit wrong over a full-screen
    // photo) and itemOverlay never reached the zoom view. A label that has to travel with the
    // image, an "AI-generated" marking or a license note, goes to both. Ignored on a static grid,
    // which has no zoom view. Null → no overlay.
    'zoomOverlay' => null,
    // Zoom inside the image the lightbox shows: buttons, the keys `+` `-` `0`, the wheel, a double
    // click or a double tap, a pinch, and dragging the enlarged image. Handed to the lightbox with
    // `maxZoom`, the largest scale as a multiple of the fitted size. Ignored on a static grid.
    'zoomable' => false,
    'maxZoom' => 4,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('image-gallery', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $lightbox = BooleanProp::from($lightbox, true, 'image-gallery', 'lightbox');
    $zoomable = BooleanProp::from($zoomable, false, 'image-gallery', 'zoomable');

    // Normalize each entry to ['src', 'alt', 'caption', 'full'].
    //
    // `full` is the zoom's address, not a second thumbnail. A grid tile is a few hundred
    // pixels wide and a page carries ten of them; the lightbox is the whole screen. That
    // difference is the entire reason an image ladder exists, and with one address per image a
    // developer has to pick a side: the small step makes the zoom blurry, the large one makes
    // every tile in a feed pay the zoom's resolution.
    //
    // Absent — or present and empty — it falls back to `src`. `??` alone would NOT do that: an
    // entry carrying `'full' => ''`
    // is a developer's own empty variable, and honoring it would point the lightbox at nothing.
    //
    // An entry without an address of its own takes its zoom as the thumbnail, and one with neither
    // is left out: written as it came, it is a tile and a slide with the sign of a broken image.
    // Empty, `false` and white space are no address (Support\UrlProp::source()).
    $address = static fn ($value): ?string => is_string($value) || $value instanceof \Stringable || is_bool($value) ? \Pushery\WireKit\Support\UrlProp::source($value) : null;
    $items = [];
    foreach (\Pushery\WireKit\Support\ListProp::records($images, 'image-gallery', 'images') as $img) {
        if (is_array($img)) {
            $full = $address($img['full'] ?? null);
            $src = $address($img['src'] ?? null) ?? $full;
            if ($src === null) {
                continue;
            }
            $full ??= $src;

            $items[] = [
                'src' => $src,
                'alt' => (string) ($img['alt'] ?? ''),
                // An Htmlable stays one, so the grid writes its markup rather than escaping it.
                'caption' => isset($img['caption'])
                    ? ($img['caption'] instanceof \Illuminate\Contracts\Support\Htmlable ? $img['caption'] : (string) $img['caption'])
                    : null,
                'full' => $full,
                'altLang' => \Pushery\WireKit\Support\LanguageTag::of($img['altLang'] ?? null),
                'captionLang' => \Pushery\WireKit\Support\LanguageTag::of($img['captionLang'] ?? null),
            ];
        } else {
            $plain = $address($img);
            if ($plain === null) {
                continue;
            }

            $items[] = ['src' => $plain, 'alt' => '', 'caption' => null, 'full' => $plain, 'altLang' => null, 'captionLang' => null];
        }
    }

    // The lightbox normalizes its own entries on `src`, so the zoom address is handed over
    // under that name. The grid below keeps reading `$item['src']`, which is the thumbnail —
    // mapping here rather than teaching the lightbox a second key keeps ITS contract at one
    // address per slide, which is right for a component that only ever shows the large one.
    $lightboxItems = array_map(
        static fn (array $item): array => array_replace($item, ['src' => $item['full']]),
        $items,
    );

    // The zoom overlay reaches the lightbox as its per-slide callback, handed the gallery's own
    // item rather than the lightbox's slide, so both overlays receive the same `$item`: the
    // thumbnail in `src`, the zoom address in `full`.
    $slideOverlay = is_callable($zoomOverlay)
        ? static fn (array $slide, int $i) => $zoomOverlay($items[$i], $i)
        : null;

    // Counted rather than random: a fresh id on every render is a fresh Alpine component to
    // a Livewire morph, so an unrelated update discarded the open lightbox and the scroll
    // position. See DomId::unique()'s docblock — it exists for exactly this.
    $galleryId = \Pushery\WireKit\Support\DomId::unique(null, 'wk-gallery-');
    // Without the lightbox the gallery's own wrapper is its root, and a caller's `id` names it: written
    // beside the gallery's own, the parser kept the first and the caller's was gone. With the lightbox
    // the bag goes on to the lightbox's root, which carries the caller's `id` itself.
    $rootId = \Pushery\WireKit\Support\AttributeText::get($attributes, 'id');
    $rootId = is_string($rootId) && trim($rootId) !== '' ? $rootId : $galleryId;
    $count = count($items);

    $wrapperClasses = WireKit::resolveClasses('image-gallery', 'base', '', $scope);
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

    // With the lightbox, the attributes land on the lightbox's own Alpine root, which holds no
    // value to bind, so an `x-model` is removed here, where the warning can name this component
    // (Support\UnboundModel).
    if ($lightbox && $count > 0) {
        \Pushery\WireKit\Support\UnboundModel::drop('image-gallery', $attributes);
    }
    // A caller's `aria-labelledby` or `aria-label` names the grid of images, below; on this
    // wrapper, which has no role, ARIA prohibits a name (Support\CallerName).
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
    $gridNamed = $callerLabelledBy !== null || $callerLabel !== null;
@endphp

@if($count === 0)
    {{-- The empty state comes FIRST, before either render branch: both of them produce a grid
         with nothing in it, which reads as a broken layout rather than as "no images yet". The
         `empty` slot replaces the sentence entirely — see data-table, where the reasoning for
         that shape was first written down. --}}
    <div id="{{ $rootId }}" {{ $attributes->except('id')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$wrapperClasses, 'flex flex-col items-center justify-center gap-1 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center'])) }}>
        @if(\Pushery\WireKit\Support\SlotContent::given($empty ?? null))
            {{ $empty }}
        @else
            <p data-wk-prose-skip class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
        @endif
    </div>
@elseif($lightbox && $count > 0)
    {{-- The gallery IS a lightbox instance: the thumbnail buttons live in the
         lightbox's default slot, so they share its Alpine scope and call
         openAt(i) directly. The dialog / focus-trap / keyboard / captions all
         come from the shared <x-wirekit::lightbox> component — the gallery
         carries no overlay markup of its own. --}}
    <x-wirekit::lightbox :name="$galleryId" :items="$lightboxItems" :slide-overlay="$slideOverlay" :zoomable="$zoomable" :max-zoom="$maxZoom" {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$wrapperClasses])) }}>
        <x-wirekit::grid :cols="$columns" :gap="$gap" :role="$gridNamed ? 'group' : null" :aria-labelledby="$callerLabelledBy" :aria-label="$callerLabelledBy === null ? $callerLabel : null">
            @foreach($items as $i => $item)
                {{-- Each thumbnail is a real button so the lightbox is
                     keyboard-operable; focus returns here on close. The relative
                     wrapper is the positioning context for the optional per-item
                     overlay rendered as a SIBLING below. --}}
                <div class="relative">
                    <button
                        type="button"
                        x-on:click="openAt({{ (int) $i }})"
                        aria-haspopup="dialog"
                        aria-label="{{ __('wirekit::View image :n', ['n' => $i + 1]) }}{{ $item['alt'] !== '' ? ': '.$item['alt'] : '' }}"
                        class="group block w-full cursor-zoom-in appearance-none border-0 bg-transparent p-0 rounded-[var(--radius-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        {{-- A span: a button takes text-level content alone, and a figure is none. --}}
                        <x-wirekit::image as="span" :src="$item['src']" :alt="$item['alt']" :alt-lang="$item['altLang']" :ratio="$ratio" :fit="$fit" rounded />
                    </button>
                    @if(is_callable($itemOverlay))
                        {{-- per-item overlay, a sibling of (not nested in) the
                             zoom trigger so its controls don't fire openAt(). Wrapper is
                             pointer-events-none; controls opt back in with pointer-events-auto. --}}
                        <div class="pointer-events-none absolute inset-0">{{ $itemOverlay($item, $i) }}</div>
                    @endif
                </div>
            @endforeach
        </x-wirekit::grid>
    </x-wirekit::lightbox>
@else
    <div id="{{ $rootId }}" {{ $attributes->except('id')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$wrapperClasses])) }}>
        <x-wirekit::grid :cols="$columns" :gap="$gap" :role="$gridNamed ? 'group' : null" :aria-labelledby="$callerLabelledBy" :aria-label="$callerLabelledBy === null ? $callerLabel : null">
            @foreach($items as $i => $item)
                @if(is_callable($itemOverlay))
                    {{-- static grid also supports the per-item overlay. --}}
                    <div class="relative">
                        <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :alt-lang="$item['altLang']" :caption="$item['caption']" :caption-lang="$item['captionLang']" :ratio="$ratio" :fit="$fit" rounded />
                        <div class="pointer-events-none absolute inset-0">{{ $itemOverlay($item, $i) }}</div>
                    </div>
                @else
                    <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :alt-lang="$item['altLang']" :caption="$item['caption']" :caption-lang="$item['captionLang']" :ratio="$ratio" :fit="$fit" rounded />
                @endif
            @endforeach
        </x-wirekit::grid>
    </div>
@endif
