{{-- optimistic-ui: n/a — navigation
     Reclassified after reading the component instead of the idea of it. The
     candidate classification rested on the reasoning that "a favorite or cart
     action on a card is the mutation case" — but this card has no such action.
     Its only interactive element is a link to the product page, and a link
     navigates.

     It does have slots now (`media`, `badge`, `options`), and none changes that: one
     holds a picture, one a word, one links to the product's variants, and a link
     navigates. Put a mutating control in any of them and the classification above
     stops applying — that would be the caller adding an action the component does
     not have, not the component growing one.

     The server-built review count that supposedly blocked it was never in the
     way either: nothing here can change that number, so naming it on the server
     is correct. The blocker and the candidacy were both inferred from what a
     product card usually does rather than from what this one renders. --}}
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
    // The price before the reduction. Renders struck through, and the saving is announced.
    'compareAt' => null,
    // The price is the lowest of several, the cheapest variant's: it reads "from €19.99".
    'from' => false,
    // The unit price (Grundpreis) for goods sold by weight, volume, length or area, and the unit it
    // refers to ("1 kg"), written beside the price as `price` writes it.
    'unitPrice' => null,
    'unitMeasure' => null,
    'currency' => config('wirekit.currency', 'USD'),
    'minorUnits' => false,
    // The locale every amount is written in, handed to each `price` with the currency. Unset, an
    // amount follows the application's locale, as a `price` of its own would.
    'locale' => null,
    // Average rating out of 5, and how many people gave it.
    'rating' => null,
    'reviewCount' => null,
    // Short line under the name.
    'description' => null,
    // 'in-stock' | 'low-stock' | 'out-of-stock'. Out of stock disables the CTA.
    'availability' => 'in-stock',
    // Heading level for the title (1-6). Defaults to 3 — a card in a grid of products — so nothing
    // moves for a caller who never sets it. Set it to match the surrounding document
    // outline: a component cannot know where in the page it sits, and a guessed level
    // is worse than a chosen one. A skipped level is what `heading-order` reports, and
    // that rule lives in axe's best-practice tag rather than the WCAG tags most suites
    // run — so it is invisible to an axe sweep and visible in Lighthouse.
    'level' => 3,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('product-card', $attributes->getAttributes());
    // Echoed into the tag or bound, the URL is written escaped once (Support\UrlProp).
    $href = \Pushery\WireKit\Support\UrlProp::text($href);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $minorUnits = BooleanProp::from($minorUnits, false, 'product-card', 'minorUnits');
    $from = BooleanProp::from($from, false, 'product-card', 'from');

    $availability = WireKit::validateProp('product-card', 'availability', $availability, ['in-stock', 'low-stock', 'out-of-stock']);

    $isOut = $availability === 'out-of-stock';

    // An empty string is no amount, as null is: the value of a field nobody has filled. Left as it
    // is, it would reach `price`, and with a compare-at price the card would announce a sale
    // without a price.
    $price = $price === '' ? null : $price;
    $compareAt = $compareAt === '' ? null : $compareAt;

    // On sale only when the old price is genuinely higher. A compare-at that is
    // equal or lower is not a discount, and striking it through would be a claim
    // the numbers do not support. A compare-at reaches `price` only on a sale, and a text casts
    // to 0, which is never one, so a text that holds no number is reported here.
    \Pushery\WireKit\Support\NumericProp::check($compareAt, 'product-card', 'compareAt');
    $onSale = $price !== null
        && $compareAt !== null
        && (float) $compareAt > (float) $price;

    // A score that is no number is reported and shows no stars, rather than the number PHP reads
    // from it ("4,5" would be 4).
    $ratingValue = \Pushery\WireKit\Support\NumericProp::orNullReported($rating, 'product-card', 'rating');

    // The number of reviews as a whole number, or none where the prop holds no number.
    $reviewTotal = \Pushery\WireKit\Support\NumericProp::intOrNull($reviewCount, 'product-card', 'reviewCount');

    // The badges stand over the picture's corners. A card without a picture has no area for them
    // to stand over, so there they form a row of their own above the name, which they would
    // otherwise cover: the sale and a caller's badges at the start, the stock at the end.
    // A caller's badges count once the slot holds more than whitespace and comments.
    $badgeText = isset($badge) ? \Pushery\WireKit\Support\LocalizedNumber::countOrNull($badge) : null;
    $hasBadge = $onSale || $badgeText !== null || $isOut || $availability === 'low-stock';
    // A media slot that holds nothing but Livewire's markers is none, and the image stands.
    $hasMedia = \Pushery\WireKit\Support\SlotContent::given($media ?? null);
    // The values of an axis, such as the product's colors, stand over the price and take its place
    // at the foot of the card's text.
    $hasOptions = \Pushery\WireKit\Support\SlotContent::given($options ?? null);
    // An empty image address, or one of white space, is no image (Support\UrlProp::source()).
    $image = \Pushery\WireKit\Support\UrlProp::source($image);
    $badgesInFlow = $hasBadge && ! $hasMedia && $image === null;
    $badgesClass = $badgesInFlow
        ? 'flex flex-col items-start gap-[var(--gap-wk-xs)]'
        : 'absolute start-[var(--padding-wk-x-sm)] top-[var(--padding-wk-y-sm)] z-10 flex flex-col items-start gap-[var(--gap-wk-xs)]';
    $stockClass = $badgesInFlow ? 'ms-auto' : 'absolute end-[var(--padding-wk-x-sm)] top-[var(--padding-wk-y-sm)] z-10';

    $classes = WireKit::resolveClasses('product-card', 'base', implode(' ', [
        'group relative flex h-full flex-col',
        'overflow-hidden',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Heading level (1-6). An invalid value is reported through the strictness gate, with a
    // did-you-mean, and where the gate does not throw it renders the default level — never
    // h1, which is what validateProp's own first-allowed fallback would produce and which
    // would break the outline worse than the default does.
    $levelValue = in_array(\Pushery\WireKit\Support\NumericProp::int($level, 0), [1, 2, 3, 4, 5, 6], true) ? \Pushery\WireKit\Support\NumericProp::int($level, 0) : 3;
    if ($levelValue !== \Pushery\WireKit\Support\NumericProp::int($level, 0)) {
        WireKit::validateProp('product-card', 'level', \Pushery\WireKit\Support\NumericProp::text($level), ['1', '2', '3', '4', '5', '6']);
    }
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
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
>
    <div @class(['relative', 'flex items-start gap-[var(--gap-wk-xs)] px-[var(--padding-wk-x-md)] pt-[var(--padding-wk-x-md)]' => $badgesInFlow])>
        {{-- The media area takes a URL or a slot, and the slot wins.

             `image` covers the ordinary case and stays exactly as it was. The slot is for
             everything that is not a URL: a painted placeholder, a `<video>`, a plain color
             field, a chart. Without it this card could only render a product that HAS a photo
             — an adopting page whose tiles draw their own placeholders had no way in at all,
             and rewriting it onto this component would have silently dropped the artwork.

             `@if`/`@elseif` rather than `@isset` with a nested branch, because the fallthrough
             is the point and this way it reads in the order it happens.

             Deliberately NO automatic placeholder when both are absent. A gray box would then
             appear on every card that legitimately has no picture, and a caller who wants one
             now has the slot to put exactly the box they mean. --}}
        @if($hasMedia)
            {{ $media }}
        @elseif($image)
            <x-wirekit::image
                :src="$image"
                :alt="$imageAlt ?? ''"
                ratio="1/1"
                fit="cover"
                class="w-full"
            />
        @endif

        {{-- One start-anchored stack, so a derived badge and a caller's own badge cannot land
             on top of each other. The wrapper carries the position; the inner spans keep their
             own names because those are what a test and a stylesheet reach for.

             The `badge` slot exists because the derived ones answer only two questions — is it
             on sale, is it in stock. "New", "Limited", "Last pair" have no equivalent and no
             way in, and a page that needs one had to drop the component. --}}
        @if($onSale || $badgeText !== null)
            <div
                data-wk-product-card-badges
                class="{{ $badgesClass }}"
            >
                @if($onSale)
                    {{-- The badge is the visual shorthand. It is NOT the accessible story:
                         the price prints the old amount in a real <del> with a visually
                         hidden "Previous price" prefix, so the pair reads as a pair rather
                         than as two unrelated numbers. A reader hears both amounts and the
                         relation between them; the badge adds nothing they would miss.

                         The card passes `:base` and never `:delta`, and `price` derives no
                         percentage from a base — its `$formattedDelta` is guarded on an
                         explicit `delta`. So no discount percentage reaches assistive
                         technology from here; a design that shows one has to announce it
                         too. --}}
                    <span data-wk-product-card-sale>
                        <x-wirekit::badge intent="danger" surface="solid" size="sm">{{ __('wirekit::Sale') }}</x-wirekit::badge>
                    </span>
                @endif

                @if($badgeText !== null)
                    <span data-wk-product-card-badge>{{ $badgeText }}</span>
                @endif
            </div>
        @endif

        @if($isOut)
            {{-- Not a tint over the image: a reader who cannot resolve the wash
                 sees a normal product. The word is the signal. --}}
            <span data-wk-product-card-stock class="{{ $stockClass }}">
                <x-wirekit::badge intent="neutral" surface="solid" size="sm">{{ __('wirekit::Out of stock') }}</x-wirekit::badge>
            </span>
        @elseif($availability === 'low-stock')
            <span data-wk-product-card-stock class="{{ $stockClass }}">
                <x-wirekit::badge intent="warning" surface="solid" size="sm">{{ __('wirekit::Low stock') }}</x-wirekit::badge>
            </span>
        @endif
    </div>

    <div class="flex flex-1 flex-col gap-[var(--gap-wk-sm)] p-[var(--padding-wk-x-md)]">
        <h{{ $levelValue }} data-wk-prose-skip class="text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            @if($href !== null)
                {{-- stretched-link: the whole card is clickable by pointer while
                     the LINK is still just the name. The pointer target and the
                     accessible name do not have to be the same rectangle. --}}
                {{-- On card hover the name dims — the SAME cue WireKit links use,
                     the `--opacity-wk-hover` token. The whole card is the pointer target
                     (stretched link), so the reaction belongs on the name it points
                     at. Opacity, not an accent color: on the stock theme accent
                     (oklch 20.5%) barely differs from text (14.5%) and in dark mode
                     they are identical, so a color hover would be invisible. --}}
                <a data-wk-prose-skip href="{{ $href }}" data-wk-product-card-link class="wk-product-card-link transition-opacity duration-[var(--transition-wk-duration)] group-hover:opacity-[var(--opacity-wk-hover)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
                    {{ $name }}
                </a>
            @else
                {{ $name }}
            @endif
        </h{{ $levelValue }}>

        @if($description)
            {{-- A div: a description slot may hold paragraphs or a list, which a parser moves out of a p.
                 Two lines at most. A card with a link leads to the whole text; one without carries it in
                 its title, where the cut lines can be read whole.
                 A link or a button in it stands above the card's link, which covers the card, so it
                 takes its own clicks; the text around it stays part of the card. --}}
            <div data-wk-product-card-description data-wk-prose-skip @if($href === null) title="{{ \Pushery\WireKit\Support\SlotContent::plain(is_scalar($description) ? (string) $description : $description) }}" @endif class="line-clamp-2 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] [&_a]:relative [&_a]:z-10 [&_button]:relative [&_button]:z-10">{{ $description }}</div>
        @endif

        @if($ratingValue !== null)
            <span @class(['flex items-center gap-[var(--gap-wk-sm)]', 'flex-wrap' => \Pushery\WireKit\Support\SlotContent::given($ratingNote ?? null)])>
                <x-wirekit::rating :value="$ratingValue" readonly size="sm" data-wk-product-card-rating />
                @if($reviewTotal !== null)
                    {{-- The count says what the stars are worth. "4.5 stars" from
                         two people and from two thousand are different claims. --}}
                    <span data-wk-product-card-reviews class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">
                        {{ \Pushery\WireKit\Support\Plural::choice('wirekit::{1} :count review|[2,*] :count reviews', $reviewTotal, ['count' => \Pushery\WireKit\Support\LocalizedNumber::integer($reviewTotal)]) }}
                    </span>
                @endif
@if(\Pushery\WireKit\Support\SlotContent::given($ratingNote ?? null))
                {{-- The `ratingNote` slot stands right after the count, in the same row as the
                     stars: where a shop says whether and how it checks that its reviews come from
                     buyers, or a mark that leads straight to where it says so, which a statement on
                     the product page alone does not replace. A card without stars has nothing for
                     it to qualify and renders none, and a card without the slot no trace of it. Its
                     link stands above the card's link, as in the description. --}}
                <span data-wk-product-card-rating-note class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] [&_a]:relative [&_a]:z-10 [&_button]:relative [&_button]:z-10">{{ $ratingNote }}</span>
@endif
            </span>
        @endif

@if($hasOptions)
        {{-- The `options` slot stands right over the price: the values of an axis, such as the
             product's colors, each a link to its variant, and a link to the rest. It takes the free
             space above it in place of the price, so the price follows it down, and the prices of a
             row of cards stand on one line whether a card has values or not. Its links stand above
             the card's link, as in the description. --}}
        <div data-wk-product-card-options class="mt-auto flex flex-wrap items-center gap-[var(--gap-wk-xs)] [&_a]:relative [&_a]:z-10 [&_button]:relative [&_button]:z-10">{{ $options }}</div>
@endif

        @if($price !== null)
            {{-- price renders the old amount in a real <del> behind a visually hidden
                 "Previous price" prefix — a reader hears "Previous price €59.00, €39.00".
                 Re-implementing the sale story here would be a second copy to keep in
                 step, and this is the reason the badge above can stay decorative.

                 `price` computes a percentage only from an explicit `:delta`, which this
                 card does not pass, so no percentage is announced. Passing one would put a
                 discount figure on every product card that is on sale, which is a visible
                 design change.

                 The `priceNote` slot stands right under the price, apart from `action` and in
                 every availability: the lowest price of the last 30 days beside a reduction, or
                 "VAT included, plus shipping" with its link, in the same field of vision and read
                 right after the price. Its directives open at the start of the line, so a card
                 without the slot renders no trace of it. --}}
            <span data-wk-product-card-price @unless($hasOptions) class="mt-auto" @endunless>
                <x-wirekit::price
                    :amount="$price"
                    :base="$onSale ? $compareAt : null"
                    :currency="$currency"
                    :minor-units="$minorUnits"
                    :locale="$locale"
                    :from="$from"
                    :unit-price="$unitPrice"
                    :unit-measure="$unitMeasure"
                />
            </span>
@if(\Pushery\WireKit\Support\SlotContent::given($priceNote ?? null))
            {{-- Its link to the shipping costs stands above the card's link, as in the description. --}}
            <div data-wk-product-card-price-note class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] [&_a]:relative [&_a]:z-10 [&_button]:relative [&_button]:z-10">{{ $priceNote }}</div>
@endif
        @endif

        {{-- The CTA sits above the stretched link, or the link would swallow every
             click meant for the button. --}}
        <div class="relative z-10 mt-[var(--gap-wk-sm)]">
            @if(\Pushery\WireKit\Support\SlotContent::given($action ?? null))
                {{ $action }}
            @else
                {{-- The product name is APPENDED to the visible label, not substituted into a
                     replacement one. A grid of cards needs names that tell the buttons apart,
                     but an aria-label of "Add Blue Shirt to cart" does not contain the words
                     on the button, and a voice-control user who says "click Add to cart" is
                     then talking to a control by a name it does not answer to (WCAG 2.5.3).
                     Visible text first, name after, so the spoken label always matches — and it
                     stays matching in every locale, because both halves come from the same
                     translated string the button renders. --}}
                <x-wirekit::button
                    intent="primary"
                    class="w-full"
                    :disabled="$isOut"
                    data-wk-product-card-cta
                >
                    {{ $isOut ? __('wirekit::Out of stock') : __('wirekit::Add to cart') }}<span class="sr-only">: {{ $name }}</span>
                </x-wirekit::button>
            @endif
        </div>
    </div>
</article>
