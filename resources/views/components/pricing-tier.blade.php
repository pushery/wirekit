@props([
    'name' => '',
    // Raw amount — formatted by the price component, so a plan never
    // hard-codes a currency string.
    'price' => null,
    // Interval-keyed amounts for a table that offers a billing toggle, e.g.
    // :prices="['monthly' => 4900, 'annual' => 49000]". Every amount is rendered
    // and FORMATTED SERVER-SIDE; the surrounding pricing-table's toggle only
    // switches which one is visible, so the currency/locale/minor-unit logic is
    // never reimplemented in JavaScript. `price` still works on its own.
    'prices' => null,
    'currency' => null,
    // "mo", "year", "seat/mo" — rendered next to the amount.
    'period' => null,
    // Per-interval period labels, e.g. :periods="['monthly' => 'mo', 'annual' => 'yr'].
    // Falls back to `period` for any interval not listed.
    'periods' => null,
    // Copy under the plan name.
    'description' => null,
    // The recommended plan. Highlighted with a badge AND a ring — never a tint
    // alone (WCAG 1.4.1).
    'featured' => false,
    // Text for the featured badge.
    'featuredLabel' => __('Most popular'),
    // Shown instead of an amount ("Let's talk") for a contact-us tier.
    'priceLabel' => null,
    // Forwarded to the inner price component so a tier can render minor-unit
    // amounts (e.g. 4900 -> EUR 49.00), locale-format them, and size the amount —
    // previously the wrapper hardcoded size="lg" and dropped the rest (WIRE-191).
    'minorUnits' => false,
    'locale' => null,
    'priceSize' => 'lg',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $isFeatured = filter_var($featured, FILTER_VALIDATE_BOOLEAN);

    // The contact-us placeholder ("Let's talk") must not out-shout a real price.
    // It used a fixed --text-wk-2xl (1.5rem) while an actual amount rendered at
    // priceSize (lg = 1rem), so the tier WITHOUT a number looked the loudest —
    // backwards. Match the placeholder to priceSize instead (WIRE-191).
    $priceLabelTextClass = match ($priceSize) {
        'xs' => 'text-[length:var(--text-wk-xs)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md' => 'text-[length:var(--text-wk-md)]',
        'xl' => 'text-[length:var(--text-wk-xl)]',
        default => 'text-[length:var(--text-wk-lg)]',
    };

    // The featured plan is lifted with a ring, not scaled up: scaling a card
    // reflows the row and makes the other plans look broken on a phone.
    $featuredClasses = $isFeatured
        ? 'ring-2 ring-[color:var(--color-wk-accent)]'
        : '';

    $classes = WireKit::resolveClasses('pricing-tier', 'base', implode(' ', array_filter([
        'flex h-full flex-col',
        'rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        $featuredClasses,
    ])), $scope);
@endphp

<li
    data-wk-pricing-tier
    @if($isFeatured) data-featured="true" @endif
    {{ $attributes->class([$classes]) }}
>
    <div class="flex items-center gap-[var(--gap-wk-sm)]">
        <span data-wk-pricing-name class="text-[length:var(--text-wk-lg)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $name }}</span>

        @if($isFeatured)
            {{-- The badge is the accessible half of "featured": the ring alone
                 would be invisible to a screen reader and to anyone who cannot
                 tell the accent color apart. --}}
            <x-wirekit::badge intent="primary" size="sm" data-wk-pricing-featured>{{ $featuredLabel }}</x-wirekit::badge>
        @endif
    </div>

    @if($description)
        <p data-wk-pricing-description class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $description }}</p>
    @endif

    <div data-wk-pricing-price class="mt-[var(--space-wk-md)] flex items-baseline gap-[var(--space-wk-xs)]">
        @if($priceLabel !== null)
            {{-- A contact-us tier has no number, and inventing one would be a lie. --}}
            <span class="{{ $priceLabelTextClass }} font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $priceLabel }}</span>
        @elseif(is_array($prices) && $prices !== [])
            {{-- One rendered, server-formatted amount per billing interval. The
                 surrounding pricing-table owns `interval` in its Alpine scope and
                 this reads it through the DOM, so switching costs no round trip and
                 no client-side money formatting.

                 Only the first is visible before Alpine boots (the rest carry an
                 inline display:none), so the page never flashes every price at once
                 and no x-cloak rule is required. --}}
            @foreach($prices as $intervalKey => $intervalAmount)
                <span
                    x-show="interval === @js((string) $intervalKey)"
                    @unless($loop->first) style="display: none;" @endunless
                    class="flex items-baseline gap-[var(--space-wk-xs)]"
                    data-wk-pricing-interval="{{ $intervalKey }}"
                >
                    <x-wirekit::price :amount="$intervalAmount" :currency="$currency" :minor-units="$minorUnits" :locale="$locale" :size="$priceSize" />
                    @php($intervalPeriod = is_array($periods) ? ($periods[$intervalKey] ?? $period) : $period)
                    @if($intervalPeriod)
                        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">/{{ $intervalPeriod }}</span>
                    @endif
                </span>
            @endforeach
        @else
            <x-wirekit::price :amount="$price" :currency="$currency" :minor-units="$minorUnits" :locale="$locale" :size="$priceSize" />
            @if($period)
                <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">/{{ $period }}</span>
            @endif
        @endif
    </div>

    @isset($features)
        <div data-wk-pricing-features class="mt-[var(--space-wk-md)] flex-1">
            {{ $features }}
        </div>
    @endisset

    @isset($action)
        {{-- mt-auto pins every plan's CTA to the same baseline, however long the
             feature lists are — a row of buttons at different heights is the
             tell of a hand-built pricing grid. --}}
        <div data-wk-pricing-action class="mt-auto pt-[var(--space-wk-md)]">
            {{ $action }}
        </div>
    @endisset
</li>
