@props([
    'align' => 'left', // left | center | right
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $alignClass = match ($align) {
        'center' => 'text-center',
        'right' => 'text-right',
        default => 'text-left',
    };

    // Base td styling — standard padding, body text weight, compact-aware padding
    $classes = WireKit::resolveClasses('table.td', 'base', implode(' ', [
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'font-[number:var(--font-wk-body-weight)]',
        $alignClass,
        // Compact variant: reduce vertical padding to match th
        '[table[data-wk-compact]_&]:py-[var(--padding-wk-y-sm)]',
    ]), $scope);
@endphp

<td {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</td>
