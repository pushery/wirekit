@props([
    'sticky' => false,
    'density' => 'comfortable',
    'align' => 'between',
    'ariaLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $densityValue = match ($density) {
        'comfortable', 'compact' => $density,
        default => WireKit::validateProp('toolbar', 'density', $density, ['comfortable', 'compact']),
    };

    $alignValue = match ($align) {
        'between', 'start', 'end' => $align,
        default => WireKit::validateProp('toolbar', 'align', $align, ['between', 'start', 'end']),
    };

    $justifyClass = match ($alignValue) {
        'between' => 'justify-between',
        'start' => 'justify-start',
        'end' => 'justify-end',
    };

    $paddingClass = match ($densityValue) {
        'compact' => 'py-[var(--space-wk-xs,0.25rem)]',
        default => 'py-[var(--space-wk-sm,0.5rem)]',
    };

    $gapClass = match ($densityValue) {
        'compact' => 'gap-[var(--space-wk-sm,0.5rem)]',
        default => 'gap-[var(--space-wk-md,1rem)]',
    };

    $stickyClasses = $sticky
        ? 'sticky top-0 z-[var(--z-wk-sticky,10)] bg-[var(--color-wk-bg)]'
        : '';

    $baseClasses = WireKit::resolveClasses('toolbar', 'base', implode(' ', array_filter([
        'flex flex-wrap items-center',
        $justifyClass,
        $gapClass,
        $paddingClass,
        $stickyClasses,
        'font-[family-name:var(--font-wk-sans)]',
    ])), $scope);
@endphp

<div
    role="toolbar"
    @if($ariaLabel) aria-label="{{ $ariaLabel }}" @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{-- Leading slot (search, primary controls) --}}
    @if(isset($leading))
        <div class="flex items-center gap-[var(--space-wk-sm,0.5rem)] min-w-0">
            {{ $leading }}
        </div>
    @endif

    {{-- Filters slot (badges, selects, chips) --}}
    @if(isset($filters))
        <div class="flex flex-wrap items-center gap-[var(--space-wk-sm,0.5rem)]">
            {{ $filters }}
        </div>
    @endif

    {{-- Default/trailing slot (action buttons) --}}
    @if(isset($trailing))
        <div class="flex items-center gap-[var(--space-wk-sm,0.5rem)]">
            {{ $trailing }}
        </div>
    @elseif(!$slot->isEmpty())
        <div class="flex items-center gap-[var(--space-wk-sm,0.5rem)]">
            {{ $slot }}
        </div>
    @endif
</div>
