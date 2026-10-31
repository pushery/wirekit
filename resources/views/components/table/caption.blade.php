{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('table.caption', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Caption styling — muted text below the table, aligned start. At most as wide as the frame
    // around the table, `@container/wk-table`: a table that scrolls sideways is wider than what
    // shows of it, and a caption as wide as the table would break its sentences at that width and
    // be cut mid-word by the scroll area. A table that fits keeps a caption as wide as itself.
    $classes = WireKit::resolveClasses('table.caption', 'base', implode(' ', [
        'caption-bottom',
        'mt-2',
        'max-w-[100cqi]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'text-left',
    ]), $scope);
@endphp

<caption {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}>
    {{ $slot }}
</caption>
