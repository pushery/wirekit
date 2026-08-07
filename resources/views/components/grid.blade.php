{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'cols' => config('wirekit.components.grid.cols', 1), // @example "1 md:2 lg:4" @example "1 sm:2 md:3 lg:4 xl:6"
    // A grid that counts columns by CONTENT rather than by viewport. `min` is the
    // narrowest a column may be; as many fit as fit, and one on a narrow screen.
    //
    // This is not the same thing as `cols` with breakpoints, which is why it is
    // its own prop rather than a spelling of that one: `cols` measures the
    // VIEWPORT, so it looks identical until the container is narrower than the
    // window — a sidebar opens, a split view, an embedded preview — and then it
    // keeps counting columns that no longer fit.
    'min' => null, // @example "14rem" @example "20ch"
    // An explicit column track list, handed to CSS as written. This is the half
    // `cols` cannot express at all: it only knows equal columns, and the two
    // commonest application layouts there are — the three-pane workspace and the
    // week grid — are neither equal nor expressible as a count.
    'template' => null, // @example "14rem 1fr 18rem" @example "4.5rem repeat(7, minmax(0, 1fr))"
    'gap' => config('wirekit.components.grid.gap', 'md'),
    'align' => null,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Tailwind cannot extract runtime-concatenated class names like "{$bp}:grid-cols-{$n}".
    // Every supported combination must appear as a literal string so the content scanner finds it.
    $colsMap = [
        '1' => 'grid-cols-1', '2' => 'grid-cols-2', '3' => 'grid-cols-3',
        '4' => 'grid-cols-4', '5' => 'grid-cols-5', '6' => 'grid-cols-6',
        '7' => 'grid-cols-7', '8' => 'grid-cols-8', '9' => 'grid-cols-9',
        '10' => 'grid-cols-10', '11' => 'grid-cols-11', '12' => 'grid-cols-12',
        'sm:1' => 'sm:grid-cols-1', 'sm:2' => 'sm:grid-cols-2', 'sm:3' => 'sm:grid-cols-3',
        'sm:4' => 'sm:grid-cols-4', 'sm:5' => 'sm:grid-cols-5', 'sm:6' => 'sm:grid-cols-6',
        'sm:7' => 'sm:grid-cols-7', 'sm:8' => 'sm:grid-cols-8', 'sm:9' => 'sm:grid-cols-9',
        'sm:10' => 'sm:grid-cols-10', 'sm:11' => 'sm:grid-cols-11', 'sm:12' => 'sm:grid-cols-12',
        'md:1' => 'md:grid-cols-1', 'md:2' => 'md:grid-cols-2', 'md:3' => 'md:grid-cols-3',
        'md:4' => 'md:grid-cols-4', 'md:5' => 'md:grid-cols-5', 'md:6' => 'md:grid-cols-6',
        'md:7' => 'md:grid-cols-7', 'md:8' => 'md:grid-cols-8', 'md:9' => 'md:grid-cols-9',
        'md:10' => 'md:grid-cols-10', 'md:11' => 'md:grid-cols-11', 'md:12' => 'md:grid-cols-12',
        'lg:1' => 'lg:grid-cols-1', 'lg:2' => 'lg:grid-cols-2', 'lg:3' => 'lg:grid-cols-3',
        'lg:4' => 'lg:grid-cols-4', 'lg:5' => 'lg:grid-cols-5', 'lg:6' => 'lg:grid-cols-6',
        'lg:7' => 'lg:grid-cols-7', 'lg:8' => 'lg:grid-cols-8', 'lg:9' => 'lg:grid-cols-9',
        'lg:10' => 'lg:grid-cols-10', 'lg:11' => 'lg:grid-cols-11', 'lg:12' => 'lg:grid-cols-12',
        'xl:1' => 'xl:grid-cols-1', 'xl:2' => 'xl:grid-cols-2', 'xl:3' => 'xl:grid-cols-3',
        'xl:4' => 'xl:grid-cols-4', 'xl:5' => 'xl:grid-cols-5', 'xl:6' => 'xl:grid-cols-6',
        'xl:7' => 'xl:grid-cols-7', 'xl:8' => 'xl:grid-cols-8', 'xl:9' => 'xl:grid-cols-9',
        'xl:10' => 'xl:grid-cols-10', 'xl:11' => 'xl:grid-cols-11', 'xl:12' => 'xl:grid-cols-12',
        '2xl:1' => '2xl:grid-cols-1', '2xl:2' => '2xl:grid-cols-2', '2xl:3' => '2xl:grid-cols-3',
        '2xl:4' => '2xl:grid-cols-4', '2xl:5' => '2xl:grid-cols-5', '2xl:6' => '2xl:grid-cols-6',
        '2xl:7' => '2xl:grid-cols-7', '2xl:8' => '2xl:grid-cols-8', '2xl:9' => '2xl:grid-cols-9',
        '2xl:10' => '2xl:grid-cols-10', '2xl:11' => '2xl:grid-cols-11', '2xl:12' => '2xl:grid-cols-12',
    ];

    // `template` beats `min` beats `cols`. Only one column track can exist, so
    // the order is a decision rather than a merge — and it runs from most
    // explicit to least, which is the only order in which the more specific prop
    // is not silently ignored.
    $trackProp = $template !== null ? 'template' : ($min !== null ? 'min' : null);

    // Both arbitrary-value props land in an inline style, because that is what an
    // arbitrary value forces: Tailwind extracts class names from source text, so
    // a track built at runtime has no literal for the scanner to find. `cols`
    // stays a class map for exactly the same reason — its values are a closed
    // set, so they CAN be literals, and a class survives a stricter CSP than an
    // inline style does.
    $trackStyle = null;

    if ($trackProp === 'min') {
        // A CSS length, and nothing else. This string is interpolated into a
        // style attribute, so the allowed shape is stated positively rather than
        // by listing what to strip — a denylist certifies every spelling it has
        // not thought of.
        if (! preg_match('/^\d+(?:\.\d+)?(?:rem|em|px|ch|%|vw|vmin|vmax)$/', trim((string) $min))) {
            WireKit::validateProp('grid', 'min', (string) $min, ['a CSS length such as 14rem, 20ch, 280px']);
            $trackProp = null;
        } else {
            // `min(100%, …)` is the part that is easy to leave out and painful to
            // debug: without it a column narrower than its own minimum overflows
            // the container instead of collapsing to one column, which is exactly
            // the case a content-driven grid exists to handle.
            $trackStyle = 'grid-template-columns: repeat(auto-fit, minmax(min(100%, '.trim((string) $min).'), 1fr));';
        }
    } elseif ($trackProp === 'template') {
        // The CSS track vocabulary: lengths, fr, auto, min-content/max-content,
        // minmax(), repeat(), fit-content(). A semicolon or a quote would end the
        // declaration and start another one, so neither is in the set.
        if (! preg_match('/^[a-zA-Z0-9\s.,%()\[\]_-]+$/', trim((string) $template))) {
            WireKit::validateProp('grid', 'template', (string) $template, ['a CSS grid-template-columns value such as "14rem 1fr 18rem"']);
            $trackProp = null;
        } else {
            $trackStyle = 'grid-template-columns: '.trim((string) $template).';';
        }
    }

    // `cols` is kept as a CSP FALLBACK when a track prop won — but only if you asked
    // for one.
    //
    // `min` and `template` are arbitrary values, so they can only ride in an inline
    // `style`: Tailwind extracts class names from source text, and a track built at
    // runtime leaves the scanner nothing to find. Under a `style-src` policy without
    // `'unsafe-inline'` (Level 3: `style-src-attr`) the browser drops that attribute —
    // and this component used to suppress the cols classes as well, so the column
    // definition disappeared ENTIRELY and the grid stacked into one column. Elsewhere a
    // dropped inline style costs a shade or a width; here it costs the whole statement.
    //
    // The suppression was there for readability — "a class that never applies is one
    // more thing for the next reader to disentangle". That reasoning holds only while
    // the class really never applies, and its premise is exactly what a strict CSP
    // removes. An inline style beats a class on specificity every time it is allowed,
    // so emitting both changes nothing about what renders; it only decides what happens
    // when the style does not arrive.
    //
    // Only when `cols` was actually set, and that is what makes it free: the default is
    // 1, so a fallback nobody asked for would be `grid-cols-1` — which is precisely the
    // single column the CSP failure already produces. Approximate rather than exact by
    // construction (three equal columns are not `10rem 1fr 8rem`), and vastly better
    // than one.
    $colsRequested = (string) $cols !== (string) config('wirekit.components.grid.cols', 1);

    $colsClasses = ($trackProp !== null && ! $colsRequested)
        ? ''
        : collect(preg_split('/\s+/', trim(is_numeric($cols) ? (string) $cols : $cols)))
            ->map(fn (string $token) => $colsMap[$token] ?? WireKit::validateProp('grid', 'cols', $token, array_keys($colsMap)))
            ->implode(' ');

    $gapClasses = match ($gap) {
        'none' => '',
        'xs' => 'gap-[var(--space-wk-xs,0.25rem)]',
        'sm' => 'gap-[var(--space-wk-sm,0.5rem)]',
        'md' => 'gap-[var(--space-wk-md,1rem)]',
        'lg' => 'gap-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'gap-[var(--space-wk-xl,2.5rem)]',
        '2xl' => 'gap-[var(--space-wk-2xl,4rem)]',
        default => WireKit::validateProp('grid', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
    };

    $alignClasses = match ($align) {
        'start' => 'items-start',
        'center' => 'items-center',
        'end' => 'items-end',
        'stretch' => 'items-stretch',
        null => '',
        default => WireKit::validateProp('grid', 'align', $align, ['start', 'center', 'end', 'stretch']),
    };

    $classes = WireKit::resolveClasses('grid', 'base', implode(' ', array_filter([
        'grid',
        $colsClasses,
        $gapClasses,
        $alignClasses,
    ])), $scope);
@endphp

@php
    // Merged rather than printed, so a developer's own `style` on the call site
    // survives instead of being replaced — the same contract `class` has here.
    $attrs = $attributes->class([$classes]);

    if ($trackStyle !== null) {
        $attrs = $attrs->merge(['style' => $trackStyle]);
    }
@endphp

<{{ $as }} {{ $attrs }}>
    {{ $slot }}
</{{ $as }}>
