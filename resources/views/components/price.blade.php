@props([
    'amount' => null,
    'currency' => config('wirekit.currency', 'USD'),
    'locale' => null,
    'base' => null,
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

    // Format using PHP NumberFormatter for locale-aware currency display
    $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);
    $formattedAmount = $formatter->formatCurrency((float) $displayAmount, $currency);
    $formattedBase = $displayBase !== null
        ? $formatter->formatCurrency((float) $displayBase, $currency)
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
            'text-[var(--color-wk-success-text)]',
            'font-[number:var(--font-wk-heading-weight)]',
        ]),
        'danger' => implode(' ', [
            'text-[var(--color-wk-danger-text)]',
            'font-[number:var(--font-wk-heading-weight)]',
        ]),
        default => implode(' ', [
            'text-[var(--color-wk-text-muted)]',
            'font-[number:var(--font-wk-heading-weight)]',
        ]),
    };
@endphp

<span {{ $attributes->class([$baseClasses]) }} aria-label="{{ $ariaLabel }}">
    {{-- Strike-through original price --}}
    @if($formattedBase !== null)
        <del class="text-[var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)] no-underline line-through decoration-[var(--color-wk-border)]">
            <bdi>{{ $formattedBase }}</bdi>
        </del>
    @endif

    {{-- Primary price --}}
    <bdi class="{{ $sizeClasses }} font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text)]">
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
        <span class="text-[var(--color-wk-text-muted)] text-[length:var(--text-wk-sm)]">
            {{ $suffix }}
        </span>
    @endif
</span>
