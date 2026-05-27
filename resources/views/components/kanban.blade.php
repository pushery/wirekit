@props([
    'orientation' => 'horizontal',
    'sortable' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $orientationValue = match ($orientation) {
        'horizontal', 'vertical' => $orientation,
        default => WireKit::validateProp('kanban', 'orientation', $orientation, ['horizontal', 'vertical']),
    };

    $layoutClasses = $orientationValue === 'horizontal'
        ? 'wk-scrollbar flex flex-row overflow-x-auto scroll-snap-x-mandatory gap-[var(--space-wk-md,1rem)]'
        : 'flex flex-col gap-[var(--space-wk-md,1rem)]';

    $baseClasses = WireKit::resolveClasses('kanban', 'base', implode(' ', [
        $layoutClasses,
        'font-[family-name:var(--font-wk-sans)]',
        'min-h-0',
        '-mx-[var(--space-wk-sm,0.5rem)] px-[var(--space-wk-sm,0.5rem)]',
        'pb-[var(--space-wk-sm,0.5rem)]',
    ]), $scope);
@endphp

<div
    role="list"
    @if($sortable) data-sortable @endif
    {{ $attributes->class([$baseClasses]) }}
>
    {{ $slot }}
</div>
