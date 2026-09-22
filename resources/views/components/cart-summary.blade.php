{{-- optimistic-ui: n/a — presentational
     Every amount here is computed by the application and handed in. There is no action on
     this component whose result could be shown early; the optimistic surface of a cart is the
     quantity field, which belongs to `cart-item`. --}}
@props([
    // The amounts. ALL of them come from the application, and none is derived here — the same
    // rule `cart-item` follows for `lineTotal`, and for the same reason: totals are where tax
    // rules, tiered pricing, gift cards and currency rounding live, and a library that guessed
    // at them would be wrong in every second shop.
    'subtotal' => null,
    // `null` hides the row entirely. `0` renders as "Free" rather than as a zero amount, which
    // is the one piece of interpretation this component does and the reason it is written here
    // rather than left implicit: a shop that would rather show 0.00 passes `shippingNote`.
    'shipping' => null,
    'tax' => null,
    // A reduction. Rendered as a negative amount, because a positive number beside the word
    // "Discount" reads as an addition to anyone scanning the column.
    'discount' => null,
    'total' => null,
    // Forwarded straight to `price`, which owns every formatting decision in this package.
    'currency' => config('wirekit.currency', 'USD'),
    'minorUnits' => false,
    // Replaces the shipping AMOUNT with a phrase — "Calculated at checkout", "Free over €50".
    // Wins over `shipping` when both are given, because a shop that says both means the words.
    'shippingNote' => null,
    // Accessible name for the panel. It is a `<section>` with a name rather than a bare box, so
    // a reader can reach the totals directly instead of arrowing through every cart line.
    'label' => __('wirekit::Order summary'),
    // The heading level is the CALLER's, not this component's. A cart page whose outline already
    // has an <h2> above the summary needs an <h3> here, and a component that writes its tag
    // literally forces a broken outline on it. Default 2 keeps every existing caller identical.
    'level' => 2,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade hands `minor-units="false"` through as the STRING "false", which is truthy —
    // so `minor-units="false"` does not silently read amounts as cents.
    $minorUnits = BooleanProp::from($minorUnits, false);

    // Clamped rather than trusted: an invalid level must never reach the tag, because
    // `<h9>` is not a heading to any reader and the failure is silent.
    $levelValue = (int) $level;
    $levelValue = $levelValue >= 1 && $levelValue <= 6 ? $levelValue : 2;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list auto-derived from
    // this component's @props. Fully qualified: this view's imports may live in a later @php
    // block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('cart-summary', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('cart-summary', 'base', implode(' ', [
        'flex flex-col gap-[var(--gap-wk-md)]',
        'rounded-[var(--radius-wk-lg)]',
        'border border-[color:var(--color-wk-border)]',
        'bg-[color:var(--color-wk-bg-subtle)]',
        'p-[var(--padding-wk-x-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $headingClasses = WireKit::resolveClasses('cart-summary', 'heading', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-md)]',
    ]), $scope);

    // The grand total is the one row that is read before anything else on the panel, so it is
    // emphasized on TWO channels rather than one: size and weight. Color alone would be the
    // color-only signal this repository bans for prices.
    // \u26a0 `--text-wk-lg` rather than `-md`, and that is measured rather than chosen. The parts
    // render at `md`, so `md` here would leave the two channels at one: a browser check
    // comparing the computed styles found weight 600 against 400 and size 14px against 14px,
    // which is exactly the single-channel emphasis the claim above says it is not.
    $totalLabelClasses = WireKit::resolveClasses('cart-summary', 'total-label', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[length:var(--text-wk-lg)]',
    ]), $scope);
@endphp

<section
    data-wk-cart-summary
    aria-label="{{ $label }}"
    {{ $attributes->class([$classes]) }}
>
    <h{{ $levelValue }} data-wk-prose-skip class="{{ $headingClasses }}">{{ $label }}</h{{ $levelValue }}>

    <x-wirekit::data-list layout="summary">
        @if($subtotal !== null)
            <x-wirekit::data-list.item :label="__('wirekit::Subtotal')">
                <span data-wk-cart-summary-subtotal>
                    <x-wirekit::price :amount="$subtotal" :currency="$currency" :minor-units="$minorUnits" size="sm" />
                </span>
            </x-wirekit::data-list.item>
        @endif

        @if($discount !== null)
            <x-wirekit::data-list.item :label="__('wirekit::Discount')">
                <span data-wk-cart-summary-discount>
                    {{-- The minus sign is rendered beside the formatted amount rather than folded
                         into it: `price` formats a value, and handing it a negative number would
                         make the sign a currency-formatting decision in every locale. --}}
                    <span aria-hidden="true">&minus;</span><x-wirekit::price :amount="$discount" :currency="$currency" :minor-units="$minorUnits" size="sm" />
                </span>
            </x-wirekit::data-list.item>
        @endif

        @if($shippingNote !== null || $shipping !== null)
            <x-wirekit::data-list.item :label="__('wirekit::Shipping')">
                <span data-wk-cart-summary-shipping>
                    @if($shippingNote !== null)
                        {{ $shippingNote }}
                    @elseif((float) $shipping === 0.0)
                        {{ __('wirekit::Free') }}
                    @else
                        <x-wirekit::price :amount="$shipping" :currency="$currency" :minor-units="$minorUnits" size="sm" />
                    @endif
                </span>
            </x-wirekit::data-list.item>
        @endif

        @if($tax !== null)
            <x-wirekit::data-list.item :label="__('wirekit::Tax')">
                <span data-wk-cart-summary-tax>
                    <x-wirekit::price :amount="$tax" :currency="$currency" :minor-units="$minorUnits" size="sm" />
                </span>
            </x-wirekit::data-list.item>
        @endif

        {{-- Rows the application adds — a gift card, a second tax rate, a deposit. They sit
             BEFORE the rule, because the rule is what separates the parts from the whole. --}}
        {{ $slot }}
    </x-wirekit::data-list>

    @if($total !== null)
        {{-- `data-list` deliberately draws no row separators, and its own comment says why: a
             totals block rules the line above the grand total, not every line. So the rule
             lives here, outside the list, and the total is a list of its own. --}}
        <x-wirekit::divider />

        <x-wirekit::data-list layout="summary">
            <x-wirekit::data-list.item>
                <x-slot:label>
                    <span class="{{ $totalLabelClasses }}">{{ __('wirekit::Total') }}</span>
                </x-slot:label>
                <span data-wk-cart-summary-total class="{{ $totalLabelClasses }}">
                    <x-wirekit::price :amount="$total" :currency="$currency" :minor-units="$minorUnits" size="lg" />
                </span>
            </x-wirekit::data-list.item>
        </x-wirekit::data-list>
    @endif

    @if(isset($actions) && $actions->hasActualContent())
        <div class="flex flex-col gap-[var(--gap-wk-sm)]">
            {{ $actions }}
        </div>
    @endif
</section>
