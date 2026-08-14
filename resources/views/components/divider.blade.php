{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'orientation' => 'horizontal',
    'variant' => 'default',
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('divider', $attributes->getAttributes());

    $orientationValue = match ($orientation) {
        'horizontal', 'vertical' => $orientation,
        default => WireKit::validateProp('divider', 'orientation', $orientation, ['horizontal', 'vertical']),
    };

    $variantValue = match ($variant) {
        'default', 'subtle', 'bold' => $variant,
        default => WireKit::validateProp('divider', 'variant', $variant, ['default', 'subtle', 'bold']),
    };

    $borderColor = match ($variantValue) {
        'subtle' => 'border-[var(--color-wk-border-subtle)]',
        'bold' => 'border-[var(--color-wk-border-strong,var(--color-wk-border))]',
        default => 'border-[var(--color-wk-border)]',
    };

    $isVertical = $orientationValue === 'vertical';
@endphp

@if($label && !$isVertical)
    {{-- Horizontal divider with centered label --}}
    <div
        role="separator"
        {{ $attributes->class([
            WireKit::resolveClasses('divider', 'base', implode(' ', [
                'flex items-center',
                'text-[length:var(--text-wk-sm)]',
                'text-[color:var(--color-wk-text-muted)]',
                'font-[family-name:var(--font-wk-sans)]',
            ]), $scope),
        ]) }}
    >
        <span @class(['grow border-t', $borderColor])></span>
        <span class="shrink-0 px-[var(--space-wk-sm,0.5rem)]">{{ $label }}</span>
        <span @class(['grow border-t', $borderColor])></span>
    </div>
@elseif($isVertical)
    {{-- Vertical divider --}}
    <div
        role="separator"
        aria-orientation="vertical"
        {{ $attributes->class([
            WireKit::resolveClasses('divider', 'base', implode(' ', [
                'self-stretch border-l',
                $borderColor,
            ]), $scope),
        ]) }}
    ></div>
@else
    {{-- Horizontal divider (no label) --}}
    <hr
        role="separator"
        {{ $attributes->class([
            WireKit::resolveClasses('divider', 'base', implode(' ', [
                'border-t border-b-0 border-l-0 border-r-0',
                $borderColor,
            ]), $scope),
        ]) }}
    />
@endif
