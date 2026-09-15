{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'level' => null,
    'size' => null,
    'accent' => false,
    'tracking' => 'normal',
    'as' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('heading', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $accent = BooleanProp::from($accent, false);

    // Determine the HTML heading level (h1–h6).
    //
    // ⚠️ RESOLVED TO AN INT IN RANGE, because this value is concatenated into the opening
    // tag below. Blade compiles an unbound attribute to a string and `e()` escapes neither
    // a space nor an `=`, so `level="2 onmouseover=alert(1)"` rendered
    // `<h2 onmouseover=alert(1) …>` — the same attribute injection `as` carried, one prop
    // over, and it survived the fix that closed `as` because only that branch was guarded.
    // An int clamped to the documented 1–6 cannot carry an attribute and cannot name an
    // element that does not exist.
    $headingLevel = min(6, max(1, (int) ($level ?? 2)));
    // `as` is rendered straight into the opening tag, so anything with a space or an
    // `=` in it becomes an ATTRIBUTE — `as="div onmouseover=alert(1)"` shipped a working
    // event handler. tagName() admits a tag name and nothing else; it is the same call
    // text / row / container / link already make.
    $tag = $as === null ? "h{$headingLevel}" : \Pushery\WireKit\WireKit::tagName('heading', (string) $as);

    // Auto-size based on heading level when size is not explicitly set
    $resolvedSize = $size ?? match ((int) $headingLevel) {
        1 => '2xl',
        2 => 'xl',
        3 => 'lg',
        4 => 'base',
        5, 6 => 'sm',
        default => 'xl',
    };

    // extend the heading size scale.
    //   - md accepted as an alias for base (rest of the kit uses md as
    //     the canonical middle tier — heading was the outlier).
    //   - 4xl and 5xl added so hero copy can use the standard hero
    //     scale designers copy from external typography systems.
    $sizeClasses = match ($resolvedSize) {
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md', 'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        '2xl' => 'text-[length:var(--text-wk-2xl,1.5rem)]',
        '3xl' => 'text-[length:var(--text-wk-3xl,1.875rem)]',
        '4xl' => 'text-[length:var(--text-wk-4xl,2.25rem)]',
        '5xl' => 'text-[length:var(--text-wk-5xl,3rem)]',
        default => WireKit::validateProp('heading', 'size', $resolvedSize, ['sm', 'md', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl']),
    };

    $trackingClasses = match ($tracking) {
        'normal' => 'tracking-normal',
        'tight' => 'tracking-tight',
        'tighter' => 'tracking-tighter',
        default => WireKit::validateProp('heading', 'tracking', $tracking, ['normal', 'tight', 'tighter']),
    };

    $colorClasses = $accent
        ? 'text-[color:var(--color-wk-accent-text)]'
        : 'text-[color:var(--color-wk-text)]';

    $classes = WireKit::resolveClasses('heading', 'base', implode(' ', [
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'leading-[var(--font-wk-heading-line-height,1.25)]',
        $sizeClasses,
        $trackingClasses,
        $colorClasses,
    ]), $scope);
@endphp

<{{ $tag }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</{{ $tag }}>
