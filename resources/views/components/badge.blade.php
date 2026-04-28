@props([
    'variant' => config('wirekit.components.badge.variant', 'neutral'),
    'intent' => null,
    'surface' => null,
    'size' => config('wirekit.components.badge.size', 'md'),
    'dot' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Base classes: layout, typography, border, transitions
    // All values reference design tokens — no hardcoded colors or sizes
    $baseClasses = WireKit::resolveClasses('badge', 'base', implode(' ', [
        'inline-flex items-center gap-x-1',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'border-[length:var(--border-wk-width)]',
        'whitespace-nowrap',
    ]), $scope);

    // Map intent to variant when intent is provided (v2.0 API)
    if ($intent !== null) {
        $variant = match ($intent) {
            'primary' => 'primary',
            'neutral' => 'neutral',
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            'info' => 'info',
            default => $intent,
        };
    }

    // Variant classes: tinted backgrounds via color-mix for subtle look
    // Border and text colors from same token family for cohesion
    $variantClasses = match ($variant) {
        'primary' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))]',
            'text-[var(--color-wk-accent-content)]',
            'border-[color-mix(in_srgb,var(--color-wk-accent)_25%,transparent)]',
        ]),
        'success' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg))]',
            // -text variant calibrated for ≥4.5:1 on the soft-tone bg
            'text-[var(--color-wk-success-text)]',
            'border-[color-mix(in_srgb,var(--color-wk-success)_25%,transparent)]',
        ]),
        'warning' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg))]',
            // -text variant calibrated for ≥4.5:1 on the soft-tone bg
            'text-[var(--color-wk-warning-text)]',
            'border-[color-mix(in_srgb,var(--color-wk-warning)_25%,transparent)]',
        ]),
        'danger' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg))]',
            'text-[var(--color-wk-danger-text)]',
            'border-[color-mix(in_srgb,var(--color-wk-danger)_25%,transparent)]',
        ]),
        'info' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-accent)_8%,var(--color-wk-bg))]',
            'text-[var(--color-wk-accent-content)]',
            'border-[color-mix(in_srgb,var(--color-wk-accent)_20%,transparent)]',
        ]),
        'neutral' => implode(' ', [
            'bg-[var(--color-wk-bg-muted)]',
            'text-[var(--color-wk-text)]',
            'border-[var(--color-wk-border-subtle)]',
        ]),
        default => WireKit::validateProp('badge', 'variant', $variant, ['primary', 'success', 'warning', 'danger', 'info', 'neutral']),
    };

    // Size classes: height, padding, font size, radius
    // Full rounded corners for pill-style badges
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-5 px-2',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-full)]',
        ]),
        'md' => implode(' ', [
            'h-6 px-2.5',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-full)]',
        ]),
        'lg' => implode(' ', [
            'h-7 px-3',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-full)]',
        ]),
        default => WireKit::validateProp('badge', 'size', $size, ['sm', 'md', 'lg']),
    };

    // Dot indicator color matches variant text color for cohesion
    $dotColorClass = match ($variant) {
        'primary', 'info' => 'bg-[var(--color-wk-accent)]',
        'success' => 'bg-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)]',
        'neutral' => 'bg-[var(--color-wk-text-muted)]',
        default => 'bg-[var(--color-wk-text-muted)]',
    };
@endphp

<span {{ $attributes->class([$baseClasses, $variantClasses, $sizeClasses]) }}>
    @if($dot)
        <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full {{ $dotColorClass }}"></span>
    @endif
    {{ $slot }}
</span>
