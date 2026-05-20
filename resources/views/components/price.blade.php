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
    use Pushery\WireKit\WireKit;

    $locale = $locale ?? app()->getLocale();

    // Convert minor units (cents) to major units
    $displayAmount = $minorUnits ? $amount / 100 : $amount;
    $displayBase = ($base !== null && $minorUnits) ? $base / 100 : $base;
    $displayUnitPrice = ($unitPrice !== null && $minorUnits) ? $unitPrice / 100 : $unitPrice;

    // Format using PHP NumberFormatter for locale-aware currency display
    $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    $formattedAmount = $formatter->formatCurrency((float) $displayAmount, $currency);
    $formattedBase = $displayBase !== null
        ? $formatter->formatCurrency((float) $displayBase, $currency)
        : null;

    // Unit price (Grundpreis) — required by EU Price Indication Directive
    // 98/6/EC and German PAngV (Preisangabenverordnung) for pre-packaged
    // goods sold by weight / volume / length / area. Reference units are
    // kg, L, m, m² (or 100 g / 100 ml for nominal quantities ≤ 250 g/ml).
    // Format: "(€8.99 / L)" alongside the main price, in same currency,
    // same field of vision, clearly readable. The component does NOT
    // enforce or validate the chosen reference unit — that is the
    // developer's call based on the jurisdiction and product type.
    $formattedUnitPrice = ($displayUnitPrice !== null && $unitMeasure)
        ? $formatter->formatCurrency((float) $displayUnitPrice, $currency)
        : null;

    // Delta formatting
    $formattedDelta = null;
    $deltaIntent = 'neutral';
    if ($delta !== null) {
        $deltaIntent = $delta < 0 ? 'success' : ($delta > 0 ? 'danger' : 'neutral');
        $sign = $delta > 0 ? '+' : '';
        $formattedDelta = $deltaFormat === 'percent'
            ? "{$sign}{$delta}%"
            : "{$sign}{$delta}";
    }

    // Aria label combining all parts
    $ariaLabel = $formattedAmount;
    if ($formattedBase !== null) {
        $ariaLabel .= ", was {$formattedBase}";
    }
    if ($formattedDelta !== null) {
        $deltaLabel = $deltaFormat === 'percent' ? abs($delta) . ' percent' : abs($delta);
        $ariaLabel .= $delta < 0 ? ", {$deltaLabel} off" : ", {$deltaLabel} more";
    }
    if ($formattedUnitPrice !== null) {
        $ariaLabel .= ", {$formattedUnitPrice} per {$unitMeasure}";
    }

    // Size classes for the primary amount
    $sizeClasses = match ($size) {
        'xs' => 'text-[length:var(--text-wk-xs)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl)]',
        default => WireKit::validateProp('price', 'size', $size, ['xs', 'sm', 'md', 'lg', 'xl']),
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

<span {{ $attributes->class([$baseClasses]) }} aria-label="{{ $ariaLabel }}">
    {{-- Strike-through compare-at price (UVP / RRP / MSRP).
         Uses inline `text-decoration` to bypass Tailwind v4 class-ordering
         where `no-underline` could shadow `line-through` on the same element,
         and to pin the decoration colour to text-muted (border-token is too
         faint at 91% lightness to read as a strike). --}}
    @if($formattedBase !== null)
        <del
            class="text-[color:var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)]"
            style="text-decoration: line-through; text-decoration-color: var(--color-wk-text-muted); text-decoration-thickness: from-font;"
        >
            <bdi>{{ $formattedBase }}</bdi>
        </del>
    @endif

    {{-- Primary price --}}
    <bdi class="{{ $sizeClasses }} font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
        {{ $formattedAmount }}
    </bdi>

    {{-- Delta badge --}}
    @if($formattedDelta !== null)
        <span class="{{ $deltaClasses }} text-[length:var(--text-wk-xs)]">
            {{ $formattedDelta }}
        </span>
    @endif

    {{-- Suffix slot (e.g. "per month") --}}
    @if(isset($suffix))
        <span class="text-[color:var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)]">
            {{ $suffix }}
        </span>
    @endif

    {{-- Unit price (Grundpreis) — formatted as "(€8.99 / L)" alongside the
         main price. Marked aria-hidden because the same information is
         already woven into the wrapper's aria-label, so screen readers
         hear it once instead of twice. --}}
    @if($formattedUnitPrice !== null)
        <span
            class="text-[color:var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)]"
            aria-hidden="true"
        >
            ({{ $formattedUnitPrice }} / {{ $unitMeasure }})
        </span>
    @endif
</span>
