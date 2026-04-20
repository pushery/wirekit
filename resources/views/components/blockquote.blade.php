@props([
    'cite' => null,
    'variant' => 'default',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $variantClasses = match ($variant) {
        'default' => 'border-[var(--color-wk-border)]',
        'accent' => 'border-[var(--color-wk-primary)]',
        default => WireKit::validateProp('blockquote', 'variant', $variant, ['default', 'accent']),
    };

    $classes = WireKit::resolveClasses('blockquote', 'base', implode(' ', [
        'border-l-4',
        'pl-[var(--space-wk-md,1rem)]',
        'py-[var(--space-wk-xs,0.25rem)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[var(--color-wk-text)]',
        'text-[length:var(--text-wk-md)]',
        'italic',
        $variantClasses,
    ]), $scope);
@endphp

<blockquote {{ $attributes->class([$classes]) }}>
    {{ $slot }}
    @if($cite)
        <footer class="mt-[var(--space-wk-sm,0.5rem)] text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)] not-italic">
            — {{ $cite }}
        </footer>
    @endif
</blockquote>
