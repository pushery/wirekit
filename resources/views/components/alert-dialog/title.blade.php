{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    // Heading level for the dialog title (1-6). Defaults to 2 so nothing moves for a caller
    // who never sets it. A dialog's heading is part of the document's outline like any other,
    // so a page whose surrounding levels differ can say so here rather than around it.
    'level' => 2,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('alert-dialog.title', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Alert dialog title — linked to the dialog via aria-labelledby.
    // The id comes from the parent alert-dialog component's data-wk-title-id attribute.
    $classes = WireKit::resolveClasses('alert-dialog.title', 'base', implode(' ', [
        'text-[length:var(--text-wk-lg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-text)]',
        'leading-[var(--leading-wk-tight)]',
    ]), $scope);

    // Heading level (1-6). An invalid value signals in debug and falls back to the default in
    // production — never to h1, which is what validateProp's own first-allowed fallback would
    // produce and which would break the outline worse than the default does.
    $levelValue = in_array((int) $level, [1, 2, 3, 4, 5, 6], true) ? (int) $level : 2;
    if ($levelValue !== (int) $level) {
        WireKit::validateProp('alert-dialog.title', 'level', (string) $level, ['1', '2', '3', '4', '5', '6']);
    }
@endphp

<h{{ $levelValue }} data-wk-prose-skip
    x-bind:id="$wkAncestorData('[data-wk-title-id]', 'wkTitleId')"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</h{{ $levelValue }}>
