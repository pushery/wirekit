{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('code', $attributes->getAttributes());

    $classes = WireKit::resolveClasses('code', 'base', implode(' ', [
        // A typed token with no break point, an email address or a URL, breaks rather than running
        // out of the box. Inherited by everything inside; `break-word` leaves every min-content width
        // alone, so a table or a flex row in the content keeps the layout it had.
        '[overflow-wrap:break-word]',
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'text-[length:0.875em]',
        'bg-[var(--color-wk-code-bg)]',
        'text-[color:var(--color-wk-code)]',
        'rounded-[var(--radius-wk-sm)]',
        'px-1.5 py-0.5',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-subtle)]',
    ]), $scope);
@endphp

<code data-wk-prose-skip {{ $attributes->class([$classes]) }}>{{ $slot }}</code>