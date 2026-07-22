@props([
    // List of images: each an array with 'src' (required), 'alt' (required for a
    // content image), and optional 'caption'. A plain string is treated as a src
    // with an empty alt (decorative) — pass the array form for real content.
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
    'itemOverlay' => null,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Normalize each entry to ['src', 'alt', 'caption'].
    $items = [];
    foreach ($images as $img) {
        if (is_array($img)) {
            $items[] = [
                'src' => (string) ($img['src'] ?? ''),
                'alt' => (string) ($img['alt'] ?? ''),
                'caption' => isset($img['caption']) ? (string) $img['caption'] : null,
            ];
        } else {
            $items[] = ['src' => (string) $img, 'alt' => '', 'caption' => null];
        }
    }

    $galleryId = 'wk-gallery-'.Str::random(6);
    $count = count($items);

    $wrapperClasses = WireKit::resolveClasses('image-gallery', 'base', '', $scope);
@endphp

@if($lightbox && $count > 0)
    {{-- The gallery IS a lightbox instance: the thumbnail buttons live in the
         lightbox's default slot, so they share its Alpine scope and call
         openAt(i) directly. The dialog / focus-trap / keyboard / captions all
         come from the shared <x-wirekit::lightbox> component — the gallery no
         longer carries its own overlay markup. --}}
    <x-wirekit::lightbox :name="$galleryId" :items="$items" {{ $attributes->class([$wrapperClasses]) }}>
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
                        aria-label="{{ __('View image :n', ['n' => $i + 1]) }}{{ $item['alt'] !== '' ? ': '.$item['alt'] : '' }}"
                        class="group block w-full cursor-zoom-in appearance-none border-0 bg-transparent p-0 rounded-[var(--radius-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
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
