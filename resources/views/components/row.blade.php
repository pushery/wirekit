@props([
    'gap' => config('wirekit.components.row.gap', 'md'),
    'align' => 'center',
    'justify' => 'start',
    'wrap' => false,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $wrap = BooleanProp::from($wrap, false);

    $gapClasses = match ($gap) {
        'none' => '',
        'xs' => 'gap-[var(--space-wk-xs,0.25rem)]',
        'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
        'md' => 'gap-[var(--space-wk-md,1rem)]',
        'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'gap-[var(--space-wk-2xl,4rem)]',
        default => WireKit::validateProp('row', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
    };

    $alignClasses = match ($align) {
        'start' => 'items-start',
        'center' => 'items-center',
        'end' => 'items-end',
        'stretch' => 'items-stretch',
        'baseline' => 'items-baseline',
        default => WireKit::validateProp('row', 'align', $align, ['start', 'center', 'end', 'stretch', 'baseline']),
    };

    $justifyClasses = match ($justify) {
        'start' => 'justify-start',
        'center' => 'justify-center',
        'end' => 'justify-end',
        'between' => 'justify-between',
        'around' => 'justify-around',
        'evenly' => 'justify-evenly',
        default => WireKit::validateProp('row', 'justify', $justify, ['start', 'center', 'end', 'between', 'around', 'evenly']),
    };

    $classes = WireKit::resolveClasses('row', 'base', implode(' ', array_filter([
        'flex flex-row',
        $gapClasses,
        $alignClasses,
        $justifyClasses,
        $wrap ? 'flex-wrap' : '',
    ])), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
