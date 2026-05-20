@props([
    'padding' => config('wirekit.components.section.padding', 'xl'),
    'background' => 'default',
    'divider' => 'none',
    'as' => 'section',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'py-[var(--space-wk-sm,0.5rem)]',
        'md' => 'py-[var(--space-wk-md,1rem)]',
        'lg' => 'py-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'py-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'py-[var(--space-wk-2xl,4rem)]',
        default => WireKit::validateProp('section', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl', '2xl']),
    };

    $bgClasses = match ($background) {
        'default' => '',
        'muted' => 'bg-[var(--color-wk-bg-muted)]',
        'subtle' => 'bg-[var(--color-wk-bg-subtle)]',
        'inverse' => 'bg-[var(--color-wk-bg-inverse)] text-[color:var(--color-wk-text-inverse)]',
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        default => WireKit::validateProp('section', 'background', $background, ['default', 'muted', 'subtle', 'inverse', 'accent']),
    };

    $dividerValue = match ($divider) {
        'none', 'top', 'bottom', 'both' => $divider,
        default => WireKit::validateProp('section', 'divider', $divider, ['none', 'top', 'bottom', 'both']),
    };

    $dividerClasses = match ($dividerValue) {
        'top' => 'border-t border-[var(--color-wk-border)]',
        'bottom' => 'border-b border-[var(--color-wk-border)]',
        'both' => 'border-y border-[var(--color-wk-border)]',
        default => '',
    };

    // `wk-section` marker — load-bearing against developer prose
    // `max-width: 75ch` clamps (see footer.blade.php for the full
    // rationale).
    $classes = WireKit::resolveClasses('section', 'base', implode(' ', array_filter([
        'wk-section',
        // `w-full` keeps the section full-width inside docs.wirekit.app
        // flex-row preview wrapper (see footer.blade.php for rationale).
        'w-full',
        $paddingClasses,
        $bgClasses,
        $dividerClasses,
    ])), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
