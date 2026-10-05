{{-- optimistic-ui: candidate — the quantity field is the only asynchronous surface here, and
     `number-input` already carries `optimistic`/`optimisticArgs`. Deliberately NOT opted in yet:
     the optimistic contract has to be satisfied first, and a rollback that moves focus or
     announces twice is worse than no optimism at all. With `stepper="buttons"` nothing is shown
     ahead of the application either: the quantity there is the one it answered with. --}}
@props([
    // The product name. Also the thing the remove control has to say, which is why it is a prop
    // rather than a slot: ten controls that all announce "Remove" cannot be told apart by anyone
    // using voice control, and a slot cannot be read into an aria-label.
    'name' => null,
    // Thumbnail. `imageAlt` defaults to empty on purpose — the name is already in the row as
    // text, so a repeated alt reads the product twice. Pass one only when the image carries
    // information the name does not.
    'image' => null,
    'imageAlt' => '',
    // Unit price, and the struck-through previous price. Both go straight through to `price`,
    // which already draws the strike AND an sr-only "Previous price" — this component does not
    // re-solve that, and must not.
    'price' => null,
    'compareAt' => null,
    'currency' => config('wirekit.currency', 'USD'),
    'minorUnits' => false,
    // The locale the row writes its numbers in: every amount, handed to each `price` with the
    // currency, and the quantity. Unset, they follow the application's locale, as a `price` of
    // its own would.
    'locale' => null,
    // What the line costs at this quantity. The APPLICATION computes it. A cart that multiplies
    // here would own state, and cart state belongs to Livewire rather than to this library —
    // every second application has to take an assumption like that back out.
    'lineTotal' => null,
    // The quantity column. `min` defaults to 1 because a cart line with zero of something is a
    // removal rather than a quantity, and `max` is the stock ceiling when the caller knows it.
    'quantity' => 1,
    'min' => config('wirekit.components.cart-item.min', 1),
    'max' => null,
    'step' => 1,
    // The size of the stepper. `lg` rather than the `md` default, and that is a deliberate choice:
    // `--size-wk-md` is 2.5rem = 40px, which clears WCAG 2.2 SC 2.5.8 (24px, level AA) with room
    // but misses the 44px of SC 2.5.5 (level AAA) and of platform touch guidance. `--size-wk-lg`
    // is 3rem = 48px and clears both. Raising the token instead would move every component that
    // reads it for one ecommerce case, so the preset carries it and a caller can still override.
    'size' => config('wirekit.components.cart-item.size', 'lg'),
    // A unit suffix for the stepper — "kg", "pair", "m". `number-input` already has `suffix`.
    'unit' => null,
    // Whether the built-in remove control renders. The `remove` slot overrides it entirely.
    'removable' => true,
    // A line that is only shown, never operated: the customer-facing screen of a till, a
    // receipt, an order confirmation. The quantity reads as text with its unit instead of a
    // stepper, which on a screen nobody touches would look operable and do nothing, and the
    // built-in remove control goes. A `remove` slot still renders: writing one is the caller's
    // decision.
    'readonly' => false,
    // A read-only line without its quantity, visibly and for a screen reader: an article whose
    // quantity another device shows, such as the weight a scale displays, where a "1" would read
    // as one piece or one kilogram. An editable line ignores it: its field is its quantity.
    'hideQuantity' => false,
    // The unit price a measured article has to show beside its price, the Grundpreis of the
    // German PAngV: "10,00 € / 1 pair". Handed to `price`, which formats it and puts it where
    // price-marking law wants it; `unitMeasure` is the reference, "kg" or "1 pair".
    'unitPrice' => null,
    'unitMeasure' => null,
    // `compact` sets the line in two rows for a narrow column, a till's for one: the name on one
    // line beside the line total, and under them the variant and the unit price on the left, the
    // quantity and the line's actions on the right. `default` is the row a shop's cart shows.
    'layout' => 'default',
    // `buttons` shows − and + with the quantity between them, and each press dispatches an event
    // for the application to act on: `wirekit:cart-decrement` or `wirekit:cart-increment`, with
    // the line's key. Every press counts, and the quantity shown is the one the application
    // answers with. `field` is the number field a `wire:model` binds.
    'stepper' => 'field',
    // The size of the line total, on the scale of `price`: `xs` to `xl`. A screen read across a
    // counter looks for what a line costs first, and at the default size the total stands no
    // larger than the name. Without it the total takes the size `price` is configured with.
    'totalSize' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this view's
    // imports live in the block below, which does not reach this call.
    \Pushery\WireKit\WireKit::warnUnknownProps('cart-item', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\LocalizedNumber;
    use Pushery\WireKit\WireKit;

    $size = WireKit::validateProp('cart-item', 'size', $size, ['sm', 'md', 'lg']);
    $removable = BooleanProp::from($removable, true);
    $minorUnits = BooleanProp::from($minorUnits, false);
    $readonly = BooleanProp::from($readonly, false);
    // A line whose quantity can be changed ignores it: its field is its quantity.
    $hideQuantity = BooleanProp::from($hideQuantity, false) && $readonly;
    $layoutValue = WireKit::validateProp('cart-item', 'layout', $layout, ['default', 'compact']);
    $stepperValue = WireKit::validateProp('cart-item', 'stepper', $stepper, ['field', 'buttons']);
    $totalSizeValue = $totalSize === null
        ? config('wirekit.components.price.size', 'md')
        : WireKit::validateProp('cart-item', 'totalSize', $totalSize, ['xs', 'sm', 'md', 'lg', 'xl']);

    // The column a thumbnail takes: a picture from `image`, or the `media` slot, which wins. The
    // slot is for what is not a URL, the painted placeholder of an article without a photo, so
    // that the names of a list with and without pictures start in one column. There is no
    // automatic placeholder, as on `product-card`: a line that has no picture and no slot has no
    // column.
    $hasMedia = isset($media) && $media->hasActualContent();

    // The bounds of the press buttons. A − at `min` would ask for a quantity the line refuses, so
    // it is disabled there, as the number field's is. A caller whose − on the last piece takes the
    // line out passes `:min="0"`.
    $atMin = $min !== null && (float) $quantity <= (float) $min;
    $atMax = $max !== null && (float) $quantity >= (float) $max;

    // One live region per row: the line total, except where a press moves the quantity itself,
    // which is then the region (partials/cart-item-quantity).
    $totalLive = $readonly || $stepperValue !== 'buttons';

    // The quantity as a reader sees it: in the locale's own digits and separators, "0,532 kg"
    // for a weighed article in German, with a no-break space so the unit never wraps away.
    $quantityText = LocalizedNumber::format((float) $quantity, maxPrecision: 3, locale: $locale)
        .(filled($unit) ? "\u{00A0}".$unit : '');

    // A stable identity for the row, so the remove dispatch says WHICH line and a Livewire
    // re-render keeps the quantity field's DOM. Derived from the name when the caller gives no
    // id, because `uniqid()` would differ between two renders of the same page and the overlay
    // identifiers of eight components were repaired for exactly that reason in v2.54.0.
    $itemKey = $attributes->get('id') ?: 'wk-cart-item-'.substr(md5((string) ($name ?? '')), 0, 8);

    // A caller's binding belongs on the quantity field and nowhere else, in every form it takes.
    // A fixed list of keys forwarded `wire:model` and `wire:model.live` and left `.blur` and
    // `.live.debounce…` on the row alone, where `blur` never arrives; and the row rendered the
    // whole bag, so even a forwarded binding sat on it too and wrote the property a second time
    // from the input events bubbling up. On a line that is only shown there is no field, and
    // the binding lands nowhere.
    $binding = $attributes->whereStartsWith(['wire:model', 'x-model'])->whereDoesntStartWith('x-modelable');
    $rowAttributes = $attributes->except(['id', ...array_keys($binding->getAttributes())]);

    // A sale is a compare-at that is actually higher. Passing a lower or equal one through would
    // draw a strike over a number that never came down, which reads as a discount that is not
    // one. Same guard `product-card` applies, and the two must not disagree.
    $onSale = $compareAt !== null && $price !== null && (float) $compareAt > (float) $price;

    $classes = WireKit::resolveClasses('cart-item', 'base', implode(' ', [
        // `flex-wrap` plus the container query on the controls below. A cart line has five
        // columns — thumbnail, name, stepper, total, remove — and on a narrow list the trailing
        // cluster of controls keeps its width while the name collapses: `flex-1 min-w-0` gives
        // way to `shrink-0`, so the column that matters is the one that disappears. Below the
        // threshold the cluster takes a row of its own instead.
        'flex flex-wrap items-start gap-[var(--gap-wk-md)]',
        'py-[var(--padding-wk-y-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // The compact line: two rows in one column beside an optional thumbnail, at a tighter rhythm,
    // because a till lists more lines than a shop's cart and its column is narrower.
    $compactClasses = WireKit::resolveClasses('cart-item', 'compact', implode(' ', [
        'flex items-start gap-[var(--gap-wk-sm)]',
        'py-[var(--padding-wk-y-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $nameClasses = WireKit::resolveClasses('cart-item', 'name', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-md)]',
    ]), $scope);

    $metaClasses = WireKit::resolveClasses('cart-item', 'meta', implode(' ', [
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);
@endphp

@if($layoutValue === 'compact')
{{-- Two rows for a narrow column: the name beside what the line costs, then what it is and how
     many on one row. The name stays on one line and ends in an ellipsis; its full text is still
     the element's text, so a screen reader reads all of it, and `title` shows it to a pointer. --}}
<li data-wk-prose-skip
    data-wk-cart-item
    data-wk-cart-item-layout="compact"
    id="{{ $itemKey }}"
    {{ $rowAttributes->class([$compactClasses]) }}
>
    @if($hasMedia || $image)
        {{-- The height of a control, so the thumbnail follows the size scale with the stepper. --}}
        <div data-wk-cart-item-media class="shrink-0 w-[var(--size-wk-md)]">
            @if($hasMedia)
                {{ $media }}
            @else
                <x-wirekit::image :src="$image" :alt="$imageAlt" ratio="1/1" fit="cover" rounded="md" />
            @endif
        </div>
    @endif

    <div class="flex min-w-0 flex-1 flex-col gap-[var(--gap-wk-xs)]">
        <div class="flex min-w-0 items-baseline justify-between gap-[var(--gap-wk-sm)]">
            @if($name)
                <span class="{{ $nameClasses }} min-w-0 truncate" title="{{ $name }}">{{ $name }}</span>
            @endif

            @if($lineTotal !== null)
                <span data-wk-cart-item-total @if($totalLive) aria-live="polite" @endif class="shrink-0 text-end">
                    <x-wirekit::price
                        :amount="$lineTotal"
                        :size="$totalSizeValue"
                        :currency="$currency"
                        :minor-units="$minorUnits"
                        :locale="$locale"
                    />
                </span>
            @endif
        </div>

        {{-- `flex-wrap` for the column that is narrower still: the controls then take a row of
             their own under what the line is, rather than pushing it to a sliver. --}}
        <div class="flex flex-wrap items-center justify-between gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]">
            <div class="flex min-w-0 flex-1 flex-col {{ $metaClasses }}">
                @if($slot->hasActualContent())
                    <span class="truncate">{{ $slot }}</span>
                @endif

                @if($price !== null)
                    <span data-wk-cart-item-unit-price>
                        <x-wirekit::price
                            :amount="$price"
                            :base="$onSale ? $compareAt : null"
                            :unit-price="$unitPrice"
                            :unit-measure="$unitMeasure"
                            :currency="$currency"
                            :minor-units="$minorUnits"
                            :locale="$locale"
                            size="sm"
                        />
                    </span>
                @endif
            </div>

            <div class="flex shrink-0 items-center gap-[var(--gap-wk-xs)]">
                @include('wirekit::components.partials.cart-item-quantity')

                @if(isset($actions) && $actions->hasActualContent())
                    <div data-wk-cart-item-actions class="flex shrink-0 items-center gap-[var(--gap-wk-xs)]">{{ $actions }}</div>
                @endif

                {{-- The slot is named here, where the partial is included, so the catalog reads it
                     as this component's. The partial shares the scope either way. --}}
                @include('wirekit::components.partials.cart-item-remove', ['remove' => isset($remove) ? $remove : null])
            </div>
        </div>

        @if(isset($details) && $details->hasActualContent())
            <div data-wk-cart-item-details class="{{ $metaClasses }}">{{ $details }}</div>
        @endif
    </div>
</li>
@else
<li data-wk-prose-skip
    data-wk-cart-item
    id="{{ $itemKey }}"
    {{ $rowAttributes->class([$classes]) }}
>
    @if($hasMedia || $image)
        <div data-wk-cart-item-media class="shrink-0 w-16">
            @if($hasMedia)
                {{ $media }}
            @else
                <x-wirekit::image :src="$image" :alt="$imageAlt" ratio="1/1" fit="cover" rounded="md" />
            @endif
        </div>
    @endif

    <div class="flex min-w-0 flex-1 flex-col gap-[var(--gap-wk-xs)]">
        @if($name)
            <span class="{{ $nameClasses }}">{{ $name }}</span>
        @endif

        {{-- The variant line — "Size M · Blue". A slot rather than a prop because it is display
             copy the application composes, and because a prop would invite a single string where
             two facts belong. --}}
        @if($slot->hasActualContent())
            <span class="{{ $metaClasses }}">{{ $slot }}</span>
        @endif

        @if($price !== null)
            <span data-wk-cart-item-unit-price>
                <x-wirekit::price
                    :amount="$price"
                    :base="$onSale ? $compareAt : null"
                    :unit-price="$unitPrice"
                    :unit-measure="$unitMeasure"
                    :currency="$currency"
                    :minor-units="$minorUnits"
                    :locale="$locale"
                    size="sm"
                />
            </span>
        @endif

        {{-- Further lines about this line: what it saves, the promotion it is part of. Display
             copy the application composes, for the reason the variant line above is a slot, and
             it may hold components of its own, a badge for the promotion. --}}
        @if(isset($details) && $details->hasActualContent())
            <div data-wk-cart-item-details class="{{ $metaClasses }}">{{ $details }}</div>
        @endif
    </div>

    {{-- `@max-lg` is 32rem = 512px, which is the arithmetic rather than a round number: 64px of
         thumbnail plus 262px of controls plus the gaps leaves a name column under 160px on
         anything narrower, and a product name is not a thing to abbreviate. Outside a
         `cart-list` the named container does not exist, so none of these match and the line
         stays on one row — which is the ordinary behavior, not a broken one. --}}

    {{-- `flex-wrap` on the cluster itself, and it is not redundant with the one on the line
         above: that one wraps the cluster away from the name, this one wraps inside the
         cluster. In a narrow column the stepper, the line total and the remove control can
         need more than the cluster gets, and `justify-between` distributes spare width and
         has none to distribute, so the remove control would paint past the right edge.
         Below that width it takes a row of its own; above it there is room, nothing wraps,
         and a wrap only happens where the content already did not fit. --}}
    <div class="flex shrink-0 flex-wrap items-center gap-[var(--gap-wk-md)] @max-lg/wk-cart-list:w-full @max-lg/wk-cart-list:justify-between">
        @include('wirekit::components.partials.cart-item-quantity')

        {{-- The line total. `aria-live="polite"` sits HERE and on nothing else in the row: a
             quantity change moves this number and the cart's grand total, and announcing both
             speaks twice for one click. The line is the one the person just acted on. With
             `stepper="buttons"` the quantity is the region instead (see the partial). --}}
        @if($lineTotal !== null)
            <span data-wk-cart-item-total @if($totalLive) aria-live="polite" @endif class="min-w-[6ch] text-end">
                <x-wirekit::price
                    :amount="$lineTotal"
                    :size="$totalSizeValue"
                    :currency="$currency"
                    :minor-units="$minorUnits"
                    :locale="$locale"
                />
            </span>
        @endif

        @if(isset($actions) && $actions->hasActualContent())
            <div data-wk-cart-item-actions class="flex shrink-0 items-center gap-[var(--gap-wk-xs)]">{{ $actions }}</div>
        @endif

        {{-- Named here for the catalog, as in the compact layout above. --}}
        @include('wirekit::components.partials.cart-item-remove', ['remove' => isset($remove) ? $remove : null])
    </div>
</li>
@endif
