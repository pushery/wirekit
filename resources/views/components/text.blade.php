{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'size' => 'base',
    'variant' => 'default', // back-compat alias of `intent`
    'intent' => null,       // canonical color axis: default | muted | subtle | accent | success | warning | danger. null → falls back to `variant`
    'weight' => 'normal',
    'align' => null,
    'truncate' => false,
    'lineClamp' => null,
    'break' => null,        // where an unbroken token may wrap: normal | anywhere | all. null → the default rules, no class
    'as' => 'p',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('text', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $truncate = BooleanProp::from($truncate, false);

    $sizeClasses = match ($size) {
        'xs' => 'text-[length:var(--text-wk-xs,0.75rem)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        default => WireKit::validateProp('text', 'size', $size, ['xs', 'sm', 'base', 'lg', 'xl']),
    };

    // `intent` is the canonical name for the color axis; `variant` is the
    // back-compat alias. This axis is a typographic scale rather than a pure
    // severity set — it has no `primary` — so an intent spelling this component
    // does not carry still fails validation. That is the point: loud beats a
    // silent fallback to the default color.
    $effectiveIntent = $intent ?? $variant;
    // The error names the prop the CALLER wrote, not the canonical one.
    $intentPropName = $intent !== null ? 'intent' : 'variant';

    $variantClasses = match ($effectiveIntent) {
        'default' => 'text-[color:var(--color-wk-text)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'accent' => 'text-[color:var(--color-wk-accent-text)]',
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'warning' => 'text-[color:var(--color-wk-warning-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
        default => WireKit::validateProp('text', $intentPropName, $effectiveIntent, ['default', 'muted', 'subtle', 'accent', 'success', 'warning', 'danger']),
    };

    $weightClasses = match ($weight) {
        'normal' => 'font-normal',
        'medium' => 'font-medium',
        'semibold' => 'font-semibold',
        'bold' => 'font-bold',
        default => WireKit::validateProp('text', 'weight', $weight, ['normal', 'medium', 'semibold', 'bold']),
    };

    $alignClasses = match ($align) {
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
        null => '',
        default => WireKit::validateProp('text', 'align', $align, ['left', 'center', 'right']),
    };

    $truncateClasses = $truncate ? 'truncate' : '';

    // Literal arms rather than `line-clamp-{$lineClamp}`, and this is the whole prop.
    // Tailwind scans SOURCE for complete class names and generates nothing for a name it
    // never sees spelled out, so the interpolated form emitted an attribute with no rule
    // behind it: DevTools showed `line-clamp-3`, the paragraph rendered at full height, and
    // nothing was red anywhere — the class is absent from the compiled stylesheet, so both
    // sides of the drift diff agreed on it. Measured 2026-09-06: the only `line-clamp-N`
    // literal in the whole tree was `line-clamp-2` in product-card, which is the entire
    // reason `:lineClamp="2"` appeared to work and every other value did not.
    //
    // A `match` (not the interpolation, and not a ternary) for the same reason
    // sticky-panel and stack spell theirs out: a class the scanner can read has to be in
    // the source as text, and a match arm is where this library puts it. Tailwind ships
    // `line-clamp-1` … `line-clamp-6`, so the arms are the whole utility rather than an
    // arbitrary cut, and a number outside it is reported instead of silently clamping
    // nothing. `$lineClamp ? … : null` keeps the original truthiness gate exactly, so
    // `null`, `0`, `'0'` and `false` still mean "no clamp".
    $lineClampClasses = match ($lineClamp ? (string) $lineClamp : null) {
        '1' => 'line-clamp-1',
        '2' => 'line-clamp-2',
        '3' => 'line-clamp-3',
        '4' => 'line-clamp-4',
        '5' => 'line-clamp-5',
        '6' => 'line-clamp-6',
        null => '',
        default => WireKit::validateProp('text', 'lineClamp', (string) $lineClamp, ['1', '2', '3', '4', '5', '6']),
    };

    // An unbroken token — a checksum, a key, a long URL — has no space to wrap at, so it widens
    // its container instead, and `truncate` would hide characters a reader may need in full.
    //
    // Arbitrary PROPERTIES rather than named utilities: Tailwind generates one from the literal
    // class on every v4 release, where the named utilities for `overflow-wrap` only arrived in
    // 4.1, and a class with no rule behind it fails silently — the lineClamp comment above is
    // that failure, measured. Literal arms for the same reason. `anywhere` also counts toward
    // the element's min-content width, which is what lets it wrap inside a flex row, where
    // `break-word` would not.
    $breakClasses = match ($break === null ? null : (string) $break) {
        'normal' => '[overflow-wrap:normal] [word-break:normal]',
        'anywhere' => '[overflow-wrap:anywhere]',
        'all' => '[word-break:break-all]',
        null => '',
        default => WireKit::validateProp('text', 'break', (string) $break, ['normal', 'anywhere', 'all']),
    };

    $classes = WireKit::resolveClasses('text', 'base', implode(' ', array_filter([
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'leading-[var(--font-wk-line-height,1.5)]',
        $sizeClasses,
        $variantClasses,
        $weightClasses,
        $alignClasses,
        $truncateClasses,
        $lineClampClasses,
        $breakClasses,
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('text', (string) $as);
@endphp

<{{ $as }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
