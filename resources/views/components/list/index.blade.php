@props([
    'type' => 'disc',
    'spacing' => 'sm',
    'as' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Determine tag based on type: ordered types use <ol>, unordered use <ul>
    $tag = $as ?? ($type === 'decimal' ? 'ol' : 'ul');

    $typeClasses = match ($type) {
        'disc' => 'list-disc',
        'decimal' => 'list-decimal',
        'none' => 'list-none',
        default => WireKit::validateProp('list', 'type', $type, ['disc', 'decimal', 'none']),
    };

    $spacingClasses = match ($spacing) {
        'none' => 'space-y-0',
        'sm' => 'space-y-1',
        'md' => 'space-y-2',
        default => WireKit::validateProp('list', 'spacing', $spacing, ['none', 'sm', 'md']),
    };

    $classes = WireKit::resolveClasses('list', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'text-[var(--color-wk-text)]',
        'text-[length:var(--text-wk-md)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        $type !== 'none' ? 'pl-[var(--space-wk-lg,1.5rem)]' : '',
        $typeClasses,
        $spacingClasses,
    ]), $scope);
@endphp

<{{ $tag }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $tag }}>
