@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Footer — landing page footer with brand, columns, and legal slots.
    $classes = WireKit::resolveClasses('footer', 'base', implode(' ', [
        'bg-[var(--color-wk-bg-elevated)]',
        'border-t border-[var(--color-wk-border)]',
        'py-[var(--space-wk-section-sm,3rem)]',
        'px-[var(--padding-wk-x-lg)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[var(--color-wk-text-muted)]',
        'text-[length:var(--text-wk-sm)]',
    ]), $scope);
@endphp

<footer {{ $attributes->class([$classes]) }}>
    <div class="max-w-[var(--size-wk-container-xl,80rem)] mx-auto">
        @isset($columns)
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-[var(--space-wk-xl,2.5rem)] mb-[var(--space-wk-xl,2.5rem)]">
                {{ $columns }}
            </div>
        @endisset

        <div class="flex flex-col sm:flex-row items-center justify-between gap-[var(--space-wk-md,1rem)] pt-[var(--space-wk-lg,1.5rem)] border-t border-[var(--color-wk-border-subtle)]">
            @isset($brand)
                <div>{{ $brand }}</div>
            @endisset

            @isset($legal)
                <div class="flex flex-wrap gap-[var(--gap-wk-md)] text-[length:var(--text-wk-xs,0.75rem)]">
                    {{ $legal }}
                </div>
            @endisset
        </div>

        {{ $slot }}
    </div>
</footer>
