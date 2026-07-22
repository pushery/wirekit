@props([
    // The id (without leading "#") of the landmark this link jumps to.
    // Defaults to "main-content" to match the id the docs tell you to set
    // on the main component via its `id` prop (id="main-content"). Note
    // that `id` is opt-in — the main component leaves it null by default —
    // so you set it explicitly to wire the pair. (No literal component tag
    // in this comment: Blade's tag compiler scans even PHP comments.)
    'target' => 'main-content',
    // Label rendered inside the link when keyboard focus reveals it.
    'label' => __('Skip to main content'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    WireKit::warnUnknownProps('skip-link', $attributes->getAttributes());

    // Visually-hidden by default; on `:focus-visible` the anchor pops out
    // as a high-contrast accent pill in the top-left of the viewport.
    // Tailwind utility list:
    //   - sr-only by default (collapses to a 1x1 clipped rect)
    //   - focus-visible:not-sr-only + absolute positioning to expose it
    //   - high-z-index so the pill sits above app-shell chrome (header,
    //     overlays etc.) when surfaced
    //   - accent surface + accent-fg foreground tracks the active theme
    //   - radius + shadow inherit current theme tokens
    // The `outline-none` is paired with a fully-themed focus ring so
    // forced-colors-mode keyboard users still see a clear focus indicator.
    $classes = WireKit::resolveClasses('skip-link', 'base', implode(' ', [
        'sr-only',
        'focus-visible:not-sr-only',
        'focus-visible:absolute',
        'focus-visible:top-[var(--space-wk-sm,0.5rem)]',
        'focus-visible:left-[var(--space-wk-sm,0.5rem)]',
        'focus-visible:z-[100]',
        'focus-visible:px-[var(--space-wk-md,1rem)]',
        'focus-visible:py-[var(--space-wk-sm,0.5rem)]',
        'focus-visible:rounded-[var(--radius-wk-md)]',
        'focus-visible:bg-[var(--color-wk-accent)]',
        'focus-visible:text-[var(--color-wk-accent-fg)]',
        'focus-visible:shadow-[var(--shadow-wk-lg)]',
        'focus-visible:outline-none',
        'focus-visible:ring-2',
        'focus-visible:ring-[var(--color-wk-accent)]',
        'focus-visible:ring-offset-2',
        'focus-visible:ring-offset-[var(--color-wk-bg)]',
        'focus-visible:font-medium',
        'focus-visible:no-underline',
        'focus-visible:transition-none',
    ]), $scope);
@endphp

<a href="#{{ $target }}" {{ $attributes->class([$classes]) }}>
    {{ $slot->isEmpty() ? $label : $slot }}
</a>