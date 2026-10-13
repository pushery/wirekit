{{-- optimistic-ui: n/a — client-only
     With `truncate="tooltip"` the text is the trigger of a tooltip, and a cut text in no
     control takes a tab stop; both only show the tooltip, in the browser. Otherwise it
     renders no interactive element. No action reaches the server whose result could be
     shown early. --}}
@props([
    'size' => 'base',
    'variant' => 'default', // back-compat alias of `intent`
    'intent' => null,       // canonical color axis: default | muted | subtle | accent | success | warning | danger. null → falls back to `variant`
    'weight' => 'normal',
    'align' => null,
    // `true` cuts the text at the end of one line with an ellipsis. `'tooltip'` cuts it the same way
    // and shows the whole text in a tooltip while, and only while, it is cut.
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
    // Shrinks the text from its size down to a floor so it fits its line, or the fewest lines the
    // floor needs while it may wrap: true for the `--text-wk-fit-floor` token, a length such as
    // `0.75em` for a floor of its own, null or false for none. A text that `truncate` or `lineClamp`
    // cuts keeps its size, and `fit` beside either is reported.
    'fit' => null,
    // The typeface: `sans` (the body face, the default) or `mono`, which reads `--font-wk-mono` and
    // drops the body face's letter spacing. A class from outside could not choose it reliably,
    // because the base classes already set a family and two families on one element are decided
    // by stylesheet order.
    'family' => 'sans',
    // Figures of one width, so a column of amounts, dates or counts lines up digit for digit.
    'tabular' => false,
    // A reading width for running text, such as a page's introduction under its title: `default`
    // keeps to `--measure-wk` (about 65 characters) and `wide` to `--measure-wk-wide` (about 78),
    // as on `prose`. null runs as wide as the container, which is right in a table cell or a card.
    'measure' => null,
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
    // Three states: false, true (cut with an ellipsis) and 'tooltip' (cut, with the whole text in a
    // tooltip while it is cut). 'tooltip' is read first and the other two go through BooleanProp,
    // so an unbound `truncate="false"` still reads as off. The cast never goes back into
    // `$truncate`: 'tooltip' is a truthy string, and the cast would keep the cut and drop the
    // tooltip.
    $truncateTooltip = $truncate === 'tooltip';
    $truncateLine = $truncateTooltip || BooleanProp::from($truncate, false);
    $wrap = BooleanProp::from($wrap, true);
    $tabular = BooleanProp::from($tabular, false);
    $fitFloor = \Pushery\WireKit\Support\FitText::prop($fit, 'text');

    // A cut text keeps its size: the cut was asked for, and it keeps the text inside its box, where a
    // fitted text on one line runs past it below its floor.
    if ($fitFloor !== false && ($truncateLine || $lineClamp)) {
        \Pushery\WireKit\Support\StrictnessGate::reject(
            'text',
            'fit',
            $fit === true ? 'true' : (is_scalar($fit) ? (string) $fit : get_debug_type($fit)),
            'no fit on a text that truncate or lineClamp cuts',
            'false',
        );
        $fitFloor = false;
    }

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

    $truncateClasses = $truncateLine ? 'truncate' : '';

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

    // Literal arms, as for the other props here: an assembled class has no rule behind it.
    $measureClasses = match ($measure === null ? null : WireKit::validateProp('text', 'measure', (string) $measure, ['default', 'wide', 'none'])) {
        'default' => 'max-w-[var(--measure-wk)]',
        'wide' => 'max-w-[var(--measure-wk-wide)]',
        'none', null => '',
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
        $measureClasses,
        $wrap ? '' : 'whitespace-nowrap',
    ])), $scope);

    // `as` is interpolated straight into the opening tag, and Blade's escaping does
    // not stop a space or an `=` — so an unvalidated value renders as an attribute.
    $as = \Pushery\WireKit\WireKit::tagName('text', (string) $as);

    // The fitted text stands inside the element, so the element's size is the largest it takes, and
    // `wrap` decides whether it keeps to one line or to the fewest lines its floor needs.
    $fitted = $fitFloor === false ? null : \Pushery\WireKit\Support\FitText::box(
        $slot,
        new \Illuminate\View\ComponentAttributeBag(array_filter(['style' => $fitFloor !== '' ? '--wk-fit-floor: '.$fitFloor.';' : null])),
        $wrap,
    );

    if ($truncateTooltip) {
        // The element carries the tooltip's listeners and becomes an Alpine root of its own. A
        // caller's listener for the same event goes in the other spelling, so both run
        // (Support\CallerListeners), and a caller's `x-ref` still reaches the caller's scope
        // (Support\CallerRef).
        $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:mouseenter', 'x-on:mouseleave', 'x-on:focusin', 'x-on:focusout', 'x-on:pointerdown', 'x-on:pointerup', 'x-on:pointerleave', 'x-on:keydown.escape.window.capture']);
        $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);

        // A caller who gave the element a tab stop keeps it, and the tooltip shows on its focus.
        $callerTabStop = $attributes->has('tabindex') || $attributes->has('x-bind:tabindex');

        // The tooltip's timing and placement are the site's tooltip settings.
        $tipPlacement = config('wirekit.components.tooltip.placement', 'top');
        $tipOffset = config('wirekit.components.tooltip.offset', 6);
        $tipDelayShow = config('wirekit.components.tooltip.delay-show', 300);
        $tipDelayHide = config('wirekit.components.tooltip.delay-hide', 100);

        // The panel of `tooltip` on its default surface, under that component's personalization,
        // so the two read as one tooltip and their class lists stay equal. A code or a key has no
        // space to wrap at, so it breaks inside the panel's width instead of running out of it.
        $tipClasses = WireKit::resolveClasses('tooltip', 'panel', implode(' ', [
            'fixed',
            'w-max',
            'z-[var(--z-wk-tooltip)]',
            'max-w-[var(--size-wk-tooltip-max)]',
            'px-[var(--padding-wk-x-sm)]',
            'py-[var(--padding-wk-y-xs)]',
            'bg-[var(--color-wk-tooltip-bg)]',
            'text-[color:var(--color-wk-tooltip-text)]',
            'text-[length:var(--text-wk-sm)]',
            'font-[family-name:var(--font-wk-sans)]',
            'rounded-[var(--radius-wk-sm)]',
            'shadow-[var(--shadow-wk-md)]',
        ])).' [overflow-wrap:anywhere]';
    }
@endphp

{{-- The slot sits against both tags. A line break and an indent around it are part of the
     element's content, and a caller who keeps a user's own line breaks with `white-space`
     set to `pre-line` or `pre-wrap` saw them as an empty first line and an indented one. --}}
@if($truncateTooltip)
{{-- `truncate="tooltip"`. The element is the tooltip's trigger as well as its text, so the
     tooltip's listeners sit on it: a wrapper would change the element a layout places. The panel
     takes its words from the element when it shows (resources/js/components/tooltip.js), and it
     is hidden from assistive technology, which reads the whole text here already: once, with no
     description that repeats it. The focus ring is for the tab stop a cut text takes when it
     stands in no control. `data-wk-cut-tip` is the stylesheet's hook: on a touch screen the line
     stands at least 24px tall while it is cut, since a tap on it shows the tooltip. --}}
<{{ $as }} data-wk-prose-skip data-wk-cut-tip
    x-data="wirekitTooltip({
        placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($tipPlacement) }},
        offset: {{ (int) $tipOffset }},
        delayShow: {{ (int) $tipDelayShow }},
        delayHide: {{ (int) $tipDelayHide }},
        whenTruncated: true
    })"
    x-ref="trigger"
    @unless($callerTabStop) x-bind:tabindex="tabStop" @endunless
    x-on:mouseenter="mouseenter()"
    x-on:mouseleave="mouseleave()"
    x-on:focusin="focusin($event)"
    x-on:focusout="focusout()"
    x-on:pointerdown="pointerdown($event)"
    x-on:pointerup="pointerup($event)"
    x-on:pointerleave="pointerleave($event)"
    x-on:keydown.escape.window.capture="keydownEscape($event)"
    {{ $attributes->class([$classes, 'focus-visible:outline-hidden focus-visible:rounded-[var(--radius-wk-sm)] focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]']) }}
>{{ $slot }}<template x-teleport="#wk-overlay-root">
    <div
        x-ref="tooltip"
        x-on:mouseenter="mouseenter()"
        x-on:mouseleave="mouseleave()"
        x-show="isOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        x-cloak
        role="tooltip"
        aria-hidden="true"
        {{-- The tooltip's marker, outside the resolved classes as on the tooltip itself: under
             forced colors the panel's fill is repainted, and the marker gives it an edge. --}}
        class="wk-tooltip-panel {{ $tipClasses }}"
    ></div>
</template></{{ $as }}>
@else
<{{ $as }} data-wk-prose-skip {{ $attributes->class([$classes]) }}>{{ $fitted ?? $slot }}</{{ $as }}>
@endif
