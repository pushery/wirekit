{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'break' => null,        // where an unbroken name may wrap: normal | anywhere | all | address, which breaks an email address, a host or a URL at its seams first. null → break-word, see below
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('code', $attributes->getAttributes());

    // A typed token with no break point, an email address or a URL, breaks rather than running
    // out of the box. Inherited by everything inside; `break-word` leaves every min-content width
    // alone, so a table or a flex row in the content keeps the layout it had. A name inside a flex
    // row needs `anywhere`, which counts toward the min-content width and lets the row shrink it:
    // `break` takes the same values and the same literal classes as on `text` and `heading`,
    // and sets the one rule in place of the default rather than beside it, where the stylesheet's
    // order would decide between them.
    $breakMode = $break === null ? null : WireKit::validateProp('code', 'break', (string) $break, ['normal', 'anywhere', 'all', 'address']);
    $breakClasses = match ($breakMode) {
        'normal' => '[overflow-wrap:normal] [word-break:normal]',
        'anywhere', 'address' => '[overflow-wrap:anywhere]',
        'all' => '[word-break:break-all]',
        null => '[overflow-wrap:break-word]',
    };

    $classes = WireKit::resolveClasses('code', 'base', implode(' ', [
        $breakClasses,
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'text-[length:0.875em]',
        'bg-[var(--color-wk-code-bg)]',
        'text-[color:var(--color-wk-code)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-[var(--padding-wk-x-xs)] py-0.5',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);

    // Code runs from the left inside a sentence of any direction. `dir` isolates it from the text
    // around it, so on a page that reads from the right its closing punctuation stays at its end;
    // a caller's own `dir` wins.
@endphp

<code data-wk-prose-skip {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes]))->merge(['dir' => 'ltr']) }}>{{ $breakMode === 'address' ? \Pushery\WireKit\Support\AddressBreaks::mark($slot) : $slot }}</code>