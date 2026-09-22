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
    //
    // It takes breakpoints the way `cols` does, with the value in brackets because a track
    // list has spaces in it and a breakpoint token cannot: `lg:[minmax(0,1fr)_20rem]`, an
    // underscore standing for each space, which is the arbitrary-value spelling Tailwind
    // already taught anyone writing an arbitrary grid-cols class by hand. Tokens without a prefix are
    // the base. `cols="1" template="lg:[1fr_20rem]"` is one column on a phone and two tracks
    // from `lg` — the layout this form exists for.
    'template' => null, // @example "14rem 1fr 18rem" @example "4.5rem repeat(7, minmax(0, 1fr))" @example "lg:[minmax(0,1fr)_20rem]"
    'gap' => config('wirekit.components.grid.gap', 'md'),
    // Which spacing ladder `gap` names a rung on: `space`, what this prop has always read, or
    // `gap`, the tighter ladder WireKit's own components use inside themselves. The two share
    // the rung names `xs`…`2xl` and differ from `md` up, so a card grid written against
    // `--gap-wk-md` could not become this component without every gap growing by a third.
    // `row` and `stack` have had the same prop since it was first reported for them; the
    // grids were left behind only because that report came from a repository using those two.
    'scale' => 'space',
    'align' => null,
    'as' => 'div',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('grid', $attributes->getAttributes());

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
    $templateBase = null;
    $templateAt = [];
    $responsiveTemplate = false;

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
        $trackValue = '/^[a-zA-Z0-9\s.,%()\[\]_-]+$/';

        // Split the base from the breakpoint tokens. A track list has spaces and a token
        // cannot, so a breakpoint value is bracketed with `_` standing for each space; a
        // base value keeps its spaces and is simply every token that is not `bp:[…]`.
        // Named lines (`[sidebar-start] 14rem`) are brackets WITHOUT a prefix, so they stay
        // in the base, and the greedy match keeps `lg:[[a]_1fr_[b]]` whole.
        $baseTokens = [];

        foreach (preg_split('/\s+/', trim((string) $template)) as $token) {
            if (preg_match('/^(sm|md|lg|xl|2xl):\[(.+)\]$/', $token, $match)) {
                $templateAt[$match[1]] = str_replace('_', ' ', $match[2]);
            } else {
                $baseTokens[] = $token;
            }
        }

        $templateBase = $baseTokens === [] ? null : implode(' ', $baseTokens);

        // Every value is validated on its own, AFTER the prefix is stripped — the `:` of a
        // breakpoint is syntax of this prop and never reaches CSS, so it is not in the set.
        $invalid = collect(array_filter([$templateBase, ...array_values($templateAt)], fn ($v) => $v !== null))
            ->first(fn (string $value) => ! preg_match($trackValue, $value));

        if ($invalid !== null) {
            WireKit::validateProp('grid', 'template', (string) $template, ['a CSS grid-template-columns value such as "14rem 1fr 18rem", optionally with breakpoints such as "1fr lg:[1fr_20rem]"']);
            $trackProp = null;
            $templateAt = [];
        } elseif ($templateAt === []) {
            // No breakpoint: exactly what this prop has always rendered, byte for byte.
            $trackStyle = 'grid-template-columns: '.$templateBase.';';
        } else {
            // With breakpoints the base can NOT stay an inline `grid-template-columns`:
            // an inline declaration beats every class, so the `lg:` rule would never win.
            // Each value rides in its own custom property instead, and a class that is
            // written out literally below reads it inside its media query. The VALUE is
            // arbitrary and the CLASS is not — which is the whole trick, because Tailwind can
            // extract a literal class and cannot extract one assembled at runtime.
            $responsiveTemplate = true;
            $trackStyle = collect(['' => $templateBase] + $templateAt)
                ->filter(fn ($value) => $value !== null)
                ->map(fn (string $value, string $bp) => '--wk-grid-template'.($bp === '' ? '' : '-'.$bp).': '.$value.';')
                ->implode(' ');
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

    $colsTokens = preg_split('/\s+/', trim(is_numeric($cols) ? (string) $cols : $cols));

    if ($responsiveTemplate) {
        // ⚠ With breakpoints `cols` is no longer a fallback — it is the ACTIVE value at every
        // breakpoint the template leaves free, including the default. `cols="1"
        // template="lg:[1fr_20rem]"` needs `grid-cols-1` below `lg`: dropped as an unrequested
        // default (the rule above), the phone would get no column definition at all, and
        // implicit `auto` tracks cannot shrink below their content the way `minmax(0,1fr)`
        // can — a long word would overflow where one column was promised.
        //
        // And at a breakpoint the template DOES occupy, the `cols` class is left out rather
        // than left to compete. Two classes on one property at one breakpoint are decided by
        // the order Tailwind emits them, which is not a precedence anyone should have to
        // know; `template` beats `cols` is the documented rule, so it is made structural.
        $occupied = array_keys($templateAt);

        if ($templateBase !== null) {
            $occupied[] = '';
        }

        $colsTokens = array_filter($colsTokens, fn (string $token) => ! in_array(
            str_contains($token, ':') ? strstr($token, ':', true) : '',
            $occupied,
            true,
        ));
    }

    $colsClasses = ($trackProp !== null && ! $colsRequested && ! $responsiveTemplate)
        ? ''
        : collect($colsTokens)
            ->map(fn (string $token) => $colsMap[$token] ?? WireKit::validateProp('grid', 'cols', $token, array_keys($colsMap)))
            ->implode(' ');

    // Written out rather than assembled, for the reason `$colsMap` is: Tailwind reads source
    // text. The VALUE each one reads is arbitrary and lives in the custom property; the class
    // itself is one of six fixed strings, which is what makes a runtime track list reachable
    // from a media query without a safelist.
    $templateClassMap = [
        '' => 'grid-cols-[var(--wk-grid-template)]',
        'sm' => 'sm:grid-cols-[var(--wk-grid-template-sm)]',
        'md' => 'md:grid-cols-[var(--wk-grid-template-md)]',
        'lg' => 'lg:grid-cols-[var(--wk-grid-template-lg)]',
        'xl' => 'xl:grid-cols-[var(--wk-grid-template-xl)]',
        '2xl' => '2xl:grid-cols-[var(--wk-grid-template-2xl)]',
    ];

    // Only for a breakpoint that was set. A class for one that was not would read an unset
    // property, resolve to `none` and erase whatever `cols` placed there.
    $templateClasses = $responsiveTemplate
        ? collect(['' => $templateBase] + $templateAt)
            ->filter(fn ($value) => $value !== null)
            ->keys()
            ->map(fn (string $bp) => $templateClassMap[$bp])
            ->implode(' ')
        : '';

    // Resolved before the rungs, so an unknown ladder name is reported as what it is rather
    // than silently falling through to the historical one.
    $scale = WireKit::validateProp('grid', 'scale', (string) $scale, ['space', 'gap']);

    // Two maps written out rather than one with a token name assembled at runtime: Tailwind
    // generates an arbitrary utility only from a literal it can read, and a composed class
    // would render a gap that silently does nothing.
    $gapClasses = $scale === 'gap'
        ? match ($gap) {
            'none' => '',
            'xs' => 'gap-[var(--gap-wk-xs,0.25rem)]',
            'sm' => 'gap-[var(--gap-wk-sm,0.5rem)]',
            'md' => 'gap-[var(--gap-wk-md,0.75rem)]',
            'lg' => 'gap-[var(--gap-wk-lg,1rem)]',
            'xl' => 'gap-[var(--gap-wk-xl,1.5rem)]',
            '2xl' => 'gap-[var(--gap-wk-2xl,2rem)]',
            default => WireKit::validateProp('grid', 'gap', $gap, ['none', 'xs', 'sm', 'md', 'lg', 'xl', '2xl']),
        }
        : match ($gap) {
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
        $templateClasses,
        $gapClasses,
        $alignClasses,
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('grid', (string) $as);
@endphp

@php
    // Merged rather than printed, so a developer's own `style` on the call site
    // survives instead of being replaced — the same contract `class` has here.
    $attrs = $attributes->class([$classes]);

    if ($trackStyle !== null) {
        $attrs = $attrs->merge(['style' => $trackStyle]);
    }
@endphp

<{{ $as }} data-wk-prose-skip {{ $attrs }}>
    {{ $slot }}
</{{ $as }}>
