{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'padding' => config('wirekit.components.section.padding', 'xl'),
    'background' => 'default',
    'divider' => 'none',
    'as' => 'section',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('section', $attributes->getAttributes());

    $paddingClasses = match (WireKit::validateProp('section', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl', '2xl'])) {
        'none' => '',
        'sm' => 'py-[var(--space-wk-sm,0.5rem)]',
        'md' => 'py-[var(--space-wk-md,1rem)]',
        'lg' => 'py-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'py-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'py-[var(--space-wk-2xl,4rem)]',
    };

    // `inverse` and `accent` fill the section and set its text color. A heading, a link, a prose
    // and a text read `--wk-fill-ink` before the page's colors, which would stand dark on dark
    // here, so they take this color; `--wk-fill-surface` takes the page's muted patch off the code
    // in a prose. The focus ring takes the same color, without an offset band, since the page ring
    // is the fill's own color in the default preset. A component with a surface of its own reads
    // neither, and a card takes all of it back for what it holds (see announcement-banner.blade.php).
    $bgClasses = match (WireKit::validateProp('section', 'background', $background, ['default', 'muted', 'subtle', 'inverse', 'accent'])) {
        'default' => '',
        'muted' => 'bg-[var(--color-wk-bg-muted)]',
        'subtle' => 'bg-[var(--color-wk-bg-subtle)]',
        'inverse' => 'bg-[var(--color-wk-bg-inverse)] text-[color:var(--color-wk-text-inverse)] [--color-wk-ring:var(--color-wk-text-inverse)] [--color-wk-ring-offset:transparent] [--wk-fill-ink:currentColor] [--wk-fill-surface:transparent]',
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] [--color-wk-ring:var(--color-wk-accent-fg)] [--color-wk-ring-offset:transparent] [--wk-fill-ink:currentColor] [--wk-fill-surface:transparent]',
    };

    // A filled section says so with `data-wk-fill` (see announcement-banner.blade.php).
    $handsItsForeground = str_contains($bgClasses, '[--wk-fill-ink:currentColor]');

    $dividerValue = match ($divider) {
        'none', 'top', 'bottom', 'both' => $divider,
        default => WireKit::validateProp('section', 'divider', $divider, ['none', 'top', 'bottom', 'both']),
    };

    $dividerClasses = match ($dividerValue) {
        'top' => 'border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bottom' => 'border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'both' => 'border-y-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
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

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('section', (string) $as);
@endphp

<{{ $as }} data-wk-prose-skip @if($handsItsForeground) data-wk-fill @endif {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</{{ $as }}>