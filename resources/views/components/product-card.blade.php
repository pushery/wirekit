@props([
    // What it is. Rendered as the heading and used as the image's fallback name.
    'name' => '',
    // Where the product page is. The name becomes the link — see below for why
    // the whole card is not one.
    'href' => null,
    // Cover image URL.
    'image' => null,
    // Accessible name for the image. Defaults to empty, NOT to the product name:
    // the name is already right there in text, so repeating it makes a screen
    // reader read the product twice. Pass it when the image shows something the
    // name does not ("Blue, worn open over a white shirt").
    'imageAlt' => null,
    // Price in the currency's major units, or minor units with :minor-units.
    'price' => null,
    // What it used to cost. Renders struck through, and the saving is announced.
    'compareAt' => null,
    'currency' => config('wirekit.currency', 'USD'),
    'minorUnits' => false,
    // Average rating out of 5, and how many people gave it.
    'rating' => null,
    'reviewCount' => null,
    // Short line under the name.
    'description' => null,
    // 'in-stock' | 'low-stock' | 'out-of-stock'. Out of stock disables the CTA.
    'availability' => 'in-stock',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $availability = WireKit::validateProp('product-card', 'availability', $availability, ['in-stock', 'low-stock', 'out-of-stock']);

    $isOut = $availability === 'out-of-stock';

    // On sale only when the old price is genuinely higher. A compare-at that is
    // equal or lower is not a discount, and striking it through would be a claim
    // the numbers do not support.
    $onSale = $price !== null
        && $compareAt !== null
        && (float) $compareAt > (float) $price;

    $ratingValue = ($rating === null || $rating === '') ? null : (float) $rating;

    $classes = WireKit::resolveClasses('product-card', 'base', implode(' ', [
        'group relative flex h-full flex-col',
        'overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);
@endphp

{{-- An <article>: a product card is a self-contained thing, and a screen reader
     should be able to move between them as units.

     Deliberately NOT one big link. Wrapping the whole card makes the link's
     accessible name the entire card — image, price, rating, button text, all read
     as one breathless sentence — and it swallows the add-to-cart button, which
     cannot legally nest inside an anchor anyway. The NAME is the link. --}}
<article
    data-wk-product-card
    data-availability="{{ $availability }}"
    {{ $attributes->class([$classes]) }}
>
    <div class="relative">
        @if($image)
            <x-wirekit::image
                :src="$image"
                :alt="$imageAlt ?? ''"
                ratio="1/1"
                fit="cover"
                class="w-full"
            />
        @endif

        @if($onSale)
            {{-- The badge is the visual shorthand. It is NOT the accessible story:
                 the price itself announces "was X, N% off" (see price), so this
                 carries no meaning a screen-reader user would miss without it. --}}
            <span data-wk-product-card-sale class="absolute start-[var(--padding-wk-x-sm)] top-[var(--padding-wk-y-sm)] z-10">
                <x-wirekit::badge intent="danger" surface="solid" size="sm">Sale</x-wirekit::badge>
            </span>
        @endif

        @if($isOut)
            {{-- Not a tint over the image: a reader who cannot resolve the wash
                 sees a normal product. The word is the signal. --}}
            <span data-wk-product-card-stock class="absolute end-[var(--padding-wk-x-sm)] top-[var(--padding-wk-y-sm)] z-10">
                <x-wirekit::badge intent="neutral" surface="solid" size="sm">Out of stock</x-wirekit::badge>
            </span>
        @elseif($availability === 'low-stock')
            <span data-wk-product-card-stock class="absolute end-[var(--padding-wk-x-sm)] top-[var(--padding-wk-y-sm)] z-10">
                <x-wirekit::badge intent="warning" surface="solid" size="sm">Low stock</x-wirekit::badge>
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-[var(--gap-wk-sm)] p-[var(--padding-wk-x-md)]">
        <h3 class="text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            @if($href)
                {{-- stretched-link: the whole card is clickable by pointer while
                     the LINK is still just the name. The pointer target and the
                     accessible name do not have to be the same rectangle. --}}
                {{-- On card hover the name dims to 80% — the SAME cue WireKit links
                     use (hover:opacity-80). The whole card is the pointer target
                     (stretched link), so the reaction belongs on the name it points
                     at. Opacity, not an accent color: on the stock theme accent
                     (oklch 20.5%) barely differs from text (14.5%) and in dark mode
                     they are identical, so a color hover would be invisible. --}}
                <a href="{{ $href }}" data-wk-product-card-link class="wk-product-card-link transition-opacity duration-[var(--transition-wk-duration)] group-hover:opacity-80 focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
                    {{ $name }}
                </a>
            @else
                {{ $name }}
            @endif
        </h3>

        @if($description)
            <p class="line-clamp-2 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $description }}</p>
        @endif

        @if($ratingValue !== null)
            <span class="flex items-center gap-[var(--gap-wk-sm)]">
                <x-wirekit::rating :value="$ratingValue" readonly size="sm" data-wk-product-card-rating />
                @if($reviewCount !== null)
                    {{-- The count says what the stars are worth. "4.5 stars" from
                         two people and from two thousand are different claims. --}}
                    <span data-wk-product-card-reviews class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
                        {{ $reviewCount }} {{ (int) $reviewCount === 1 ? 'review' : 'reviews' }}
                    </span>
                @endif
            </span>
        @endif

        @if($price !== null)
            {{-- price already announces "€39, was €59, 34% off" and renders the
                 old amount in a real <del>. Re-implementing the sale story here
                 would be a second copy to keep in step — and the reason the
                 badge above can stay decorative. --}}
            <span data-wk-product-card-price class="mt-auto">
                <x-wirekit::price
                    :amount="$price"
                    :base="$onSale ? $compareAt : null"
                    :currency="$currency"
                    :minor-units="$minorUnits"
                />
            </span>
        @endif

        {{-- The CTA sits above the stretched link, or the link would swallow every
             click meant for the button. --}}
        <div class="relative z-10 mt-[var(--gap-wk-sm)]">
            @if(isset($action))
                {{ $action }}
            @else
                <x-wirekit::button
                    intent="primary"
                    class="w-full"
                    :disabled="$isOut"
                    :aria-label="$isOut ? 'Out of stock: '.$name : 'Add '.$name.' to cart'"
                    data-wk-product-card-cta
                >
                    {{ $isOut ? 'Out of stock' : 'Add to cart' }}
                </x-wirekit::button>
            @endif
        </div>
    </div>
</article>
