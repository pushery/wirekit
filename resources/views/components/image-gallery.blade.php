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
    // escaped). Null → no overlay (galleries without it are byte-identical to before).
    // It covers the THUMBNAIL only; the zoom view takes `zoomOverlay` below.
    'itemOverlay' => null,
    // The zoom view's own overlay: the same `fn($item, $i)` shape as itemOverlay, laid over the
    // image in the lightbox instead of over the thumbnail. A separate callback because the two
    // surfaces differ (an overlay written for a square tile can sit wrong over a full-screen
    // photo) and itemOverlay never reached the zoom view. A label that has to travel with the
    // image, an "AI-generated" marking or a license note, goes to both. Ignored on a static grid,
    // which has no zoom view. Null → no overlay.
    'zoomOverlay' => null,
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
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $lightbox = BooleanProp::from($lightbox, true);

    // Normalize each entry to ['src', 'alt', 'caption', 'full'].
    //
    // ⚠️ `full` IS THE ZOOM'S ADDRESS, NOT A SECOND THUMBNAIL. A grid tile is a few hundred
    // pixels wide and a page carries ten of them; the lightbox is the whole screen. That
    // difference is the entire reason an image ladder exists, and with one address per image a
    // developer has to pick a side: the small step makes the zoom blurry, the large one makes
    // every tile in a feed pay the zoom's resolution. Reported from an application that chose
    // the large one and wrote the compromise into its own template.
    //
    // Absent — or present and empty — it falls back to `src`, so every gallery shipping today
    // renders byte-identically. `??` alone would NOT do that: an entry carrying `'full' => ''`
    // is a developer's own empty variable, and honoring it would point the lightbox at nothing.
    $items = [];
    foreach ($images as $img) {
        if (is_array($img)) {
            $src = (string) ($img['src'] ?? '');
            $full = trim((string) ($img['full'] ?? ''));

            $items[] = [
                'src' => $src,
                'alt' => (string) ($img['alt'] ?? ''),
                'caption' => isset($img['caption']) ? (string) $img['caption'] : null,
                'full' => $full === '' ? $src : $full,
            ];
        } else {
            $items[] = ['src' => (string) $img, 'alt' => '', 'caption' => null, 'full' => (string) $img];
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
    $count = count($items);

    $wrapperClasses = WireKit::resolveClasses('image-gallery', 'base', '', $scope);
@endphp

@if($count === 0)
    {{-- The empty state comes FIRST, before either render branch: both of them produce a grid
         with nothing in it, which reads as a broken layout rather than as "no images yet". The
         `empty` slot replaces the sentence entirely — see data-table, where the reasoning for
         that shape was first written down. --}}
    <div id="{{ $galleryId }}" {{ $attributes->class([$wrapperClasses, 'flex flex-col items-center justify-center gap-1 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center']) }}>
        @isset($empty)
            {{ $empty }}
        @else
            <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
        @endisset
    </div>
@elseif($lightbox && $count > 0)
    {{-- The gallery IS a lightbox instance: the thumbnail buttons live in the
         lightbox's default slot, so they share its Alpine scope and call
         openAt(i) directly. The dialog / focus-trap / keyboard / captions all
         come from the shared <x-wirekit::lightbox> component — the gallery no
         longer carries its own overlay markup. --}}
    <x-wirekit::lightbox :name="$galleryId" :items="$lightboxItems" :slide-overlay="$slideOverlay" {{ $attributes->class([$wrapperClasses]) }}>
        <x-wirekit::grid :cols="$columns" :gap="$gap">
            @foreach($items as $i => $item)
                {{-- Each thumbnail is a real button so the lightbox is
                     keyboard-operable; focus returns here on close. The relative
                     wrapper is the positioning context for the optional per-item
                     overlay rendered as a SIBLING below. --}}
                <div class="relative">
                    <button
                        type="button"
                        x-on:click="openAt({{ $i }})"
                        aria-haspopup="dialog"
                        aria-label="{{ __('wirekit::View image :n', ['n' => $i + 1]) }}{{ $item['alt'] !== '' ? ': '.$item['alt'] : '' }}"
                        class="group block w-full cursor-zoom-in appearance-none border-0 bg-transparent p-0 rounded-[var(--radius-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :ratio="$ratio" :fit="$fit" rounded />
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
    <div id="{{ $galleryId }}" {{ $attributes->class([$wrapperClasses]) }}>
        <x-wirekit::grid :cols="$columns" :gap="$gap">
            @foreach($items as $i => $item)
                @if(is_callable($itemOverlay))
                    {{-- static grid also supports the per-item overlay. --}}
                    <div class="relative">
                        <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :caption="$item['caption']" :ratio="$ratio" :fit="$fit" rounded />
                        <div class="pointer-events-none absolute inset-0">{{ $itemOverlay($item, $i) }}</div>
                    </div>
                @else
                    <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :caption="$item['caption']" :ratio="$ratio" :fit="$fit" rounded />
                @endif
            @endforeach
        </x-wirekit::grid>
    </div>
@endif
