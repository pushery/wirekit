{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'amount' => null,
    'currency' => config('wirekit.currency', 'USD'),
    'locale' => null,
    'base' => null,
    'unitPrice' => null,
    'unitMeasure' => null,
    'delta' => null,
    'deltaFormat' => 'percent',
    'size' => config('wirekit.components.price.size', 'md'),
    'minorUnits' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\LocalizedNumber;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('price', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $minorUnits = BooleanProp::from($minorUnits, false);

    $locale = $locale ?? app()->getLocale();

    // Convert minor units (cents) to major units
    $displayAmount = $minorUnits ? $amount / 100 : $amount;
    $displayBase = ($base !== null && $minorUnits) ? $base / 100 : $base;
    $displayUnitPrice = ($unitPrice !== null && $minorUnits) ? $unitPrice / 100 : $unitPrice;

    // Locale-aware currency display, through the helper that also knows what to write without
    // the intl extension: WireKit does not require it, and a formatter built here directly
    // would not exist in an application that lacks it, so the whole page would fail to render.
    $money = static fn ($value): string => LocalizedNumber::currency((float) $value, (string) $currency, $locale);
    $formattedAmount = $money($displayAmount);
    $formattedBase = $displayBase !== null ? $money($displayBase) : null;

    // Unit price (Grundpreis), required beside the selling price for goods offered by
    // weight, volume, length or area: EU Price Indication Directive 98/6/EC, and in
    // Germany the PAngV (Preisangabenverordnung). The reference unit is 1 kg, 1 l, 1 m,
    // 1 m² or 1 m³, or a unit a member state customarily uses for a product; in Germany,
    // loose goods sold by weight or volume may use 100 g or 100 ml where that is the
    // custom. Format: "(€8.99 / L)" beside the main price, in the same currency and the
    // same field of vision. The component does not validate the reference unit: which
    // one applies depends on the jurisdiction and the product.
    $formattedUnitPrice = ($displayUnitPrice !== null && $unitMeasure) ? $money($displayUnitPrice) : null;

    // Delta formatting
    $formattedDelta = null;
    $deltaIntent = 'neutral';
    if ($delta !== null) {
        $deltaIntent = $delta < 0 ? 'success' : ($delta > 0 ? 'danger' : 'neutral');
        $sign = $delta > 0 ? '+' : '';
        // The number in the locale's own writing, beside an amount that already is: `-12,5 %`
        // in German rather than `-12.5%`. The plus stays this component's, a minus comes with
        // the number.
        $formattedDelta = $sign.($deltaFormat === 'percent'
            ? LocalizedNumber::percent((float) $delta, 2, $locale)
            : LocalizedNumber::format((float) $delta, maxPrecision: 2, locale: $locale));
    }

    // The meaning is carried by CONTENT, not by an `aria-label` on the wrapper below. The
    // wrapper is a roleless <span>, and ARIA prohibits `aria-label` on `generic`, so such a
    // name is not required to reach assistive tech at all; a unit price hidden behind it
    // would be a legally required figure (Price Indication Directive 98/6/EC, PAngV) that
    // nothing guarantees is spoken.
    //
    // The struck compare-at price gets a visually-hidden prefix, because <del> maps to
    // `role="deletion"` and screen readers
    // do not announce it by default; the unit price is simply read out, being visible
    // text; and the delta keeps its own sign, which is exactly what tells a sighted
    // reader the direction too.

    // Size classes for the primary amount
    $sizeClasses = match (WireKit::validateProp('price', 'size', $size, ['xs', 'sm', 'md', 'lg', 'xl'])) {
        'xs' => 'text-[length:var(--text-wk-xs)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl)]',
    };

    // Base classes
    $baseClasses = WireKit::resolveClasses('price', 'base', implode(' ', [
        'inline-flex items-baseline gap-x-1.5',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // Delta intent classes
    $deltaClasses = match ($deltaIntent) {
        'success' => implode(' ', [
            'text-[color:var(--color-wk-success-text)]',
            'font-[number:var(--font-wk-heading-weight)]',
        ]),
        'danger' => implode(' ', [
            'text-[color:var(--color-wk-danger-text)]',
            'font-[number:var(--font-wk-heading-weight)]',
        ]),
        default => implode(' ', [
            'text-[color:var(--color-wk-text-muted)]',
            'font-[number:var(--font-wk-heading-weight)]',
        ]),
    };
@endphp

<span {{ $attributes->class([$baseClasses]) }}>
    {{-- Strike-through compare-at price (UVP / RRP / MSRP).
         Uses inline `text-decoration` to bypass Tailwind v4 class-ordering
         where `no-underline` could shadow `line-through` on the same element,
         and to pin the decoration color to text-muted (border-token is too
         faint at 91% lightness to read as a strike). --}}
    @if($formattedBase !== null)
        {{-- The compare-at matches the main price SIZE (same $sizeClasses); the
             de-emphasis comes from the muted color + the strike, not a smaller
             font. A one-step-smaller old price read as odd next to the current
             one — equal size keeps the pair balanced while the strike still says
             which is the old number. --}}
        <del
            class="text-[color:var(--color-wk-text-muted)] {{ $sizeClasses }}"
            style="text-decoration: line-through; text-decoration-color: var(--color-wk-text-muted); text-decoration-thickness: from-font;"
        >
            {{-- The strike is the only thing that says "old price", and it says it to the
                 eye alone: <del> exposes `role="deletion"`, which screen readers do not
                 announce by default. This prefix is what makes the pair readable as a
                 pair rather than as two unrelated amounts. --}}
            <span class="sr-only">{{ __('wirekit::Previous price') }}</span>
            <bdi>{{ $formattedBase }}</bdi>
        </del>
    @endif

    {{-- Primary price --}}
    <bdi class="{{ $sizeClasses }} font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
        {{ $formattedAmount }}
    </bdi>

    {{-- Delta badge --}}
    @if($formattedDelta !== null)
        {{-- <bdi> like the two amounts above, and for a sharper reason: the delta carries a
             LEADING SIGN. A `+` or `-` next to a number takes its direction from the
             surrounding paragraph, so in a right-to-left line the sign jumps to the other
             end and "+12%" reads as "12%+" — or worse, as a minus in the reader's eye. --}}
        <span class="{{ $deltaClasses }} text-[length:var(--text-wk-xs)]">
            <bdi>{{ $formattedDelta }}</bdi>
        </span>
    @endif

    {{-- Suffix slot (e.g. "per month") --}}
    @if(isset($suffix))
        <span class="text-[color:var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)]">
            {{ $suffix }}
        </span>
    @endif

    {{-- Unit price (Grundpreis) — formatted as "(€8.99 / L)" alongside the
         main price. It is read out like any other visible text, not hidden
         behind the wrapper's aria-label: that label sits on a roleless <span>,
         where ARIA prohibits it, so the figure the Price Indication Directive
         requires would hang on a name nothing guarantees is spoken. --}}
    @if($formattedUnitPrice !== null)
        <span class="text-[color:var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)]">
            (<bdi>{{ $formattedUnitPrice }}</bdi> / {{ $unitMeasure }})
        </span>
    @endif
</span>