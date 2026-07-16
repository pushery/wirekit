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
    // Enable the click-to-zoom lightbox. When false the grid is static.
    'lightbox' => true,
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
                     keyboard-operable; focus returns here on close. --}}
                <button
                    type="button"
                    x-on:click="openAt({{ $i }})"
                    aria-haspopup="dialog"
                    aria-label="{{ __('View image :n', ['n' => $i + 1]) }}{{ $item['alt'] !== '' ? ': '.$item['alt'] : '' }}"
                    class="group block w-full cursor-zoom-in appearance-none border-0 bg-transparent p-0 rounded-[var(--radius-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                >
                    <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :ratio="$ratio" fit="cover" rounded />
                </button>
            @endforeach
        </x-wirekit::grid>
    </x-wirekit::lightbox>
@else
    <div id="{{ $galleryId }}" {{ $attributes->class([$wrapperClasses]) }}>
        <x-wirekit::grid :cols="$columns" :gap="$gap">
            @foreach($items as $item)
                <x-wirekit::image :src="$item['src']" :alt="$item['alt']" :caption="$item['caption']" :ratio="$ratio" fit="cover" rounded />
            @endforeach
        </x-wirekit::grid>
    </div>
@endif
