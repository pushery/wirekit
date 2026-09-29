{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'size' => 'base',
    'variant' => 'default', // back-compat alias of `intent`
    'intent' => null,       // canonical color axis: default | muted | subtle | accent | success | warning | danger. null → falls back to `variant`
    'weight' => 'normal',
    'align' => null,
    'truncate' => false,
    'lineClamp' => null,
    'break' => null,        // where an unbroken token may wrap: normal | anywhere | all. null → the default rules, no class
    // The line height: tight | normal | relaxed, on the leading tokens. null keeps the theme's own
    // line height, which is `normal`. A second line under a value in a table cell wants `tight`, so it
    // stands close to the line it belongs to; a class from outside could not say so reliably, because
    // two line-height utilities on one element are decided by stylesheet order.
    'leading' => null,
    // `false` keeps the text on one line without cutting it, where `truncate` cuts it with an
    // ellipsis. For a short second line, a date or an amount, that must not double a row's height on
    // a phone and must not lose its end either.
    'wrap' => true,
    // The typeface: `sans` (the body face, as before) or `mono`, which reads `--font-wk-mono` and
    // drops the body face's letter spacing. A class from outside could not choose it reliably,
    // because the base classes already set a family and two families on one element are decided
    // by stylesheet order.
    'family' => 'sans',
    // Figures of one width, so a column of amounts, dates or counts lines up digit for digit.
    'tabular' => false,
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
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $truncate = BooleanProp::from($truncate, false);
    $wrap = BooleanProp::from($wrap, true);
    $tabular = BooleanProp::from($tabular, false);

    // Literal arms, as for every class here: an assembled class has no rule behind it. The sans
    // tracking belongs to the sans face; a monospaced face spaces its own letters.
    $familyClasses = match (WireKit::validateProp('text', 'family', (string) $family, ['sans', 'mono'])) {
        'mono' => 'font-[family-name:var(--font-wk-mono)]',
        default => 'font-[family-name:var(--font-wk-sans)] tracking-[var(--font-wk-letter-spacing)]',
    };

    $sizeClasses = match (WireKit::validateProp('text', 'size', $size, ['xs', 'sm', 'base', 'lg', 'xl'])) {
        'xs' => 'text-[length:var(--text-wk-xs,0.75rem)]',
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
    };

    // `intent` is the canonical name for the color axis; `variant` is the
    // back-compat alias. This axis is a typographic scale rather than a pure
    // severity set — it has no `primary` — so an intent spelling this component
    // does not carry still fails validation. That is the point: loud beats a
    // silent fallback to the default color.
    $effectiveIntent = $intent ?? $variant;
    // The error names the prop the CALLER wrote, not the canonical one.
    $intentPropName = $intent !== null ? 'intent' : 'variant';

    $variantClasses = match (WireKit::validateProp('text', $intentPropName, $effectiveIntent, ['default', 'muted', 'subtle', 'accent', 'success', 'warning', 'danger'])) {
        'default' => 'text-[color:var(--color-wk-text)]',
        'muted' => 'text-[color:var(--color-wk-text-muted)]',
        'subtle' => 'text-[color:var(--color-wk-text-subtle)]',
        'accent' => 'text-[color:var(--color-wk-accent-text)]',
        'success' => 'text-[color:var(--color-wk-success-text)]',
        'warning' => 'text-[color:var(--color-wk-warning-text)]',
        'danger' => 'text-[color:var(--color-wk-danger-text)]',
    };

    // `normal` is the theme's body weight rather than a fixed 400, so text follows a theme that sets
    // its running text lighter or heavier, as the rest of the body copy does.
    $weightClasses = match (WireKit::validateProp('text', 'weight', $weight, ['normal', 'medium', 'semibold', 'bold'])) {
        'normal' => 'font-[number:var(--font-wk-body-weight)]',
        'medium' => 'font-medium',
        'semibold' => 'font-semibold',
        'bold' => 'font-bold',
    };

    $alignClasses = match ($align === null ? null : WireKit::validateProp('text', 'align', $align, ['left', 'center', 'right'])) {
        'left' => 'text-left',
        'center' => 'text-center',
        'right' => 'text-right',
        null => '',
    };

    $truncateClasses = $truncate ? 'truncate' : '';

    // Literal arms rather than `line-clamp-{$lineClamp}`, and this is the whole prop.
    // Tailwind scans source for complete class names and generates nothing for a name it
    // never sees spelled out, so an interpolated form would emit an attribute with no rule
    // behind it: the class on the element, the paragraph at full height, and nothing red
    // anywhere. It would even seem to work for a value some other file spells out.
    //
    // A `match` (not the interpolation, and not a ternary) for the same reason
    // sticky-panel and stack spell theirs out: a class the scanner can read has to be in
    // the source as text, and a match arm is where this library puts it. Tailwind would build
    // `line-clamp-N` for any whole number, but only for a class it finds written out, so the
    // six arms are the lines this component offers, the range its page documents. A number
    // outside them is reported, and then clamps like the first arm instead of clamping nothing.
    // `$lineClamp ? … : null` keeps the original truthiness gate exactly, so `null`, `0`, `'0'`
    // and `false` still mean "no clamp".
    $lineClampClasses = match ($lineClamp ? WireKit::validateProp('text', 'lineClamp', (string) $lineClamp, ['1', '2', '3', '4', '5', '6']) : null) {
        '1' => 'line-clamp-1',
        '2' => 'line-clamp-2',
        '3' => 'line-clamp-3',
        '4' => 'line-clamp-4',
        '5' => 'line-clamp-5',
        '6' => 'line-clamp-6',
        null => '',
    };

    // An unbroken token — a checksum, a key, a long URL — has no space to wrap at, so it widens
    // its container instead, and `truncate` would hide characters a reader may need in full.
    //
    // Arbitrary PROPERTIES rather than named utilities: Tailwind generates one from the literal
    // class on every v4 release, where the named utilities for `overflow-wrap` only arrived in
    // 4.1, and a class with no rule behind it fails silently, as the lineClamp comment above
    // describes. Literal arms for the same reason. `anywhere` also counts toward
    // the element's min-content width, which is what lets it wrap inside a flex row, where
    // `break-word` would not.
    $breakClasses = match ($break === null ? null : WireKit::validateProp('text', 'break', (string) $break, ['normal', 'anywhere', 'all'])) {
        'normal' => '[overflow-wrap:normal] [word-break:normal]',
        'anywhere' => '[overflow-wrap:anywhere]',
        'all' => '[word-break:break-all]',
        null => '',
    };

    // Literal arms, as for the other props here: an assembled class has no rule behind it.
    $leadingClasses = match ($leading === null ? null : WireKit::validateProp('text', 'leading', (string) $leading, ['tight', 'normal', 'relaxed'])) {
        'tight' => 'leading-[var(--leading-wk-tight)]',
        'relaxed' => 'leading-[var(--leading-wk-relaxed)]',
        'normal', null => 'leading-[var(--font-wk-line-height,1.5)]',
    };

    $classes = WireKit::resolveClasses('text', 'base', implode(' ', array_filter([
        $familyClasses,
        $tabular ? 'tabular-nums' : '',
        $leadingClasses,
        $sizeClasses,
        $variantClasses,
        $weightClasses,
        $alignClasses,
        $truncateClasses,
        $lineClampClasses,
        $breakClasses,
        $wrap ? '' : 'whitespace-nowrap',
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('text', (string) $as);
@endphp

<{{ $as }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $as }}>
