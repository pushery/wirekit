{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'level' => null,
    'size' => null,
    'accent' => false,
    'tracking' => 'normal',
    'break' => null,        // where an unbroken name may wrap: normal | anywhere | all. null → the default rules, no class
    // Shrinks the heading from its size down to a floor so it takes the fewest lines the floor
    // needs: true for the `--text-wk-fit-floor` token, a length such as `0.75em` for a floor of its
    // own, null or false for none.
    'fit' => null,
    'as' => null,
    'scope' => null,
    // The help of a page or a section: the question mark a `label` shows beside a field, beside
    // the heading, with the text in a tooltip and `wirekit:field-help` on a click.
    'help' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('heading', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $accent = BooleanProp::from($accent, false, 'heading', 'accent');
    $fitFloor = \Pushery\WireKit\Support\FitText::prop($fit, 'heading');

    // Determine the HTML heading level (h1–h6).
    //
    // Resolved to an int in range, because this value is concatenated into the opening
    // tag below. Blade compiles an unbound attribute to a string and `e()` escapes neither
    // a space nor an `=`, so `level="2 onmouseover=alert(1)"` would render
    // `<h2 onmouseover=alert(1) …>`, the same attribute injection `as` is guarded against.
    // An int clamped to the documented 1–6 cannot carry an attribute and cannot name an
    // element that does not exist.
    $headingLevel = min(6, max(1, \Pushery\WireKit\Support\NumericProp::int($level ?? 2, 0, 'heading', 'level')));
    // `as` is rendered straight into the opening tag, so anything with a space or an
    // `=` in it would become an attribute — `as="div onmouseover=alert(1)"` would ship a
    // working event handler. tagName() admits a tag name and nothing else; it is the same call
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

    // The heading size scale:
    //   - md is an alias of base, the name the rest of the kit gives its
    //     middle tier.
    //   - 4xl and 5xl are the hero scale designers copy from external
    //     typography systems.
    $sizeClasses = match (WireKit::validateProp('heading', 'size', $resolvedSize, ['sm', 'md', 'base', 'lg', 'xl', '2xl', '3xl', '4xl', '5xl'])) {
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'md', 'base' => 'text-[length:var(--text-wk-md)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        'xl' => 'text-[length:var(--text-wk-xl,1.25rem)]',
        '2xl' => 'text-[length:var(--text-wk-2xl,1.5rem)]',
        '3xl' => 'text-[length:var(--text-wk-3xl,1.875rem)]',
        '4xl' => 'text-[length:var(--text-wk-4xl,2.25rem)]',
        '5xl' => 'text-[length:var(--text-wk-5xl,3rem)]',
    };

    $trackingClasses = match (WireKit::validateProp('heading', 'tracking', $tracking, ['normal', 'tight', 'tighter'])) {
        'normal' => 'tracking-normal',
        'tight' => 'tracking-tight',
        'tighter' => 'tracking-tighter',
    };

    // A heading often carries a name somebody typed — a template, a repository, a channel —
    // and such names tend to have no point a browser may break at, so they run out of the
    // heading instead. The same three values and the same literal classes as `text`, so the two
    // cannot drift apart: arbitrary properties rather than the named overflow-wrap utilities,
    // which only arrived in Tailwind 4.1. `anywhere` also counts toward the min-content width,
    // which is what lets a heading wrap inside a flex row.
    $breakClasses = match ($break === null ? null : WireKit::validateProp('heading', 'break', (string) $break, ['normal', 'anywhere', 'all'])) {
        'normal' => '[overflow-wrap:normal] [word-break:normal]',
        'anywhere' => '[overflow-wrap:anywhere]',
        'all' => '[word-break:break-all]',
        null => '',
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
        $breakClasses,
        $colorClasses,
    ]), $scope);

    // The fitted text stands inside the heading, so the heading's size is the largest it takes.
    $fitted = $fitFloor === false ? null : \Pushery\WireKit\Support\FitText::box(
        $slot,
        new \Illuminate\View\ComponentAttributeBag(array_filter(['style' => $fitFloor !== '' ? '--wk-fit-floor: '.$fitFloor.';' : null])),
    );

    // The help stands beside the heading element, never inside it, so the heading's name stays
    // its text. The question mark is named by that text, and the event names the heading's `id`.
    // A heading hidden with `sr-only` shows no button: a button nobody can see must not take a
    // tab stop (partials/field-help).
    $hasHelp = filled($help);
    $helpCallerClasses = preg_split('/\s+/', (string) $attributes->get('class', '')) ?: [];
    // A heading written as a phrasing element, `as="span"` in a sentence, keeps the row it shares
    // with its help a span: a `div` there closes the paragraph around it, and the rest of the
    // sentence stands in a paragraph of its own.
    $phrasing = in_array(strtolower($tag), ['a', 'abbr', 'b', 'bdi', 'bdo', 'cite', 'code', 'data', 'del', 'dfn', 'em', 'i', 'ins', 'kbd', 'label', 'mark', 'q', 's', 'samp', 'small', 'span', 'strong', 'sub', 'sup', 'time', 'u', 'var'], true);
    $rowTag = $phrasing ? 'span' : 'div';
@endphp

{{-- The slot sits against both tags. A line break and an indent around it are part of the
     element's content, and a caller who keeps a user's own line breaks with `white-space`
     set to `pre-line` or `pre-wrap` saw them as an empty first line and an indented one. --}}
@if($hasHelp)
    {{-- The heading and its help share a row, and the caller's classes go on the row, as they do
         for a label with help: they place the heading, and the help belongs in the same place.
         A fitted heading takes the rest of the row, since the box it fits its text in takes the
         heading's width; any other stands as wide as its text, with the help after it. --}}
    <{{ $rowTag }} data-wk-prose-skip data-wk-heading-row class="{{ trim(($rowTag === 'span' ? 'inline-flex' : 'flex').' items-center gap-[var(--gap-wk-xs)] '.$attributes->get('class', '')) }}">
        <{{ $tag }} data-wk-prose-skip {{ $attributes->except('class')->class([$classes, 'min-w-0', 'flex-1' => $fitted !== null]) }}>{{ $fitted ?? $slot }}</{{ $tag }}>
        @include('wirekit::components.partials.field-help', [
            'helpText' => $help,
            'helpName' => \Pushery\WireKit\Support\SlotContent::plain($slot),
            'helpId' => null,
            'helpButton' => ! in_array('sr-only', $helpCallerClasses, true),
            'helpField' => (string) $attributes->get('id', ''),
        ])
    </{{ $rowTag }}>@else
<{{ $tag }} data-wk-prose-skip {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>{{ $fitted ?? $slot }}</{{ $tag }}>@endif