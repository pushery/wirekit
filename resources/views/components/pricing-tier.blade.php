@props([
    'name' => '',
    // Raw amount — formatted by the price component, so a plan never
    // hard-codes a currency string.
    'price' => null,
    'currency' => null,
    // "mo", "year", "seat/mo" — rendered next to the amount.
    'period' => null,
    // Copy under the plan name.
    'description' => null,
    // The recommended plan. Highlighted with a badge AND a ring — never a tint
    // alone (WCAG 1.4.1).
    'featured' => false,
    // Text for the featured badge.
    'featuredLabel' => 'Most popular',
    // Shown instead of an amount ("Let's talk") for a contact-us tier.
    'priceLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $isFeatured = filter_var($featured, FILTER_VALIDATE_BOOLEAN);

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
            <span class="text-[length:var(--text-wk-2xl)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $priceLabel }}</span>
        @else
            <x-wirekit::price :amount="$price" :currency="$currency" size="lg" />
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
