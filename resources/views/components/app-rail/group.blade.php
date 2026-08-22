{{-- optimistic-ui: n/a — presentational
     A cluster of navigation entries. It renders no interactive element of its own, so
     there is no action whose result could be shown early. --}}
@props([
    // An optional heading. It is drawn only where the rail draws labels at all — in the
    // icon-only rail there is no room for it, and a section title that does not fit is
    // worse than none — the labels themselves never truncate, so a heading there would
    // wrap to three or four lines and push the modules off the screen. It stays the group's accessible name in every mode regardless, which
    // is what lets a screen-reader user tell two clusters of icons apart.
    'label' => null,
    // A separator line above the group. The rails these come from cluster modules with
    // a rule rather than a heading — a heading costs a row of height that a narrow
    // column does not have. Off for the first group, which needs no line above it.
    'separated' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('app-rail.group', $attributes->getAttributes());

    $separated = BooleanProp::from($separated, false);

    $classes = WireKit::resolveClasses('app-rail.group', 'base', implode(' ', [
        'flex flex-col gap-[2px]',
    ]), $scope);

    $separatorClasses = $separated
        ? 'mt-[var(--space-wk-sm,0.5rem)] pt-[var(--space-wk-sm,0.5rem)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-rail-border)]'
        : '';

    // `sr-only` is the resting state so the heading is always the group's visible-or-not
    // name, matching how the item's own label behaves. Uppercase tracking is the
    // functional section-label treatment the sidebar already uses.
    $labelClasses = WireKit::resolveClasses('app-rail.group', 'label', implode(' ', [
        'sr-only',
        'group-data-[labels=below]/wk-rail:not-sr-only',
        // Wraps rather than truncating, for the reason the item's label records: a clipped
        // section name names nothing.
        'group-data-[labels=below]/wk-rail:break-words',
        'group-data-[labels=below]/wk-rail:text-center',
        'group-data-[labels=inline]/wk-rail:not-sr-only',
        'group-data-[labels=inline]/wk-rail:px-[var(--padding-wk-x-sm)]',
        'group-data-[labels=below]/wk-rail:pb-[2px]',
        'group-data-[labels=inline]/wk-rail:pb-[2px]',
        'group-data-[labels=below]/wk-rail:text-[length:var(--text-wk-xs)]',
        'group-data-[labels=inline]/wk-rail:text-[length:var(--text-wk-xs)]',
        'group-data-[labels=below]/wk-rail:uppercase',
        'group-data-[labels=inline]/wk-rail:uppercase',
        'group-data-[labels=below]/wk-rail:tracking-wider',
        'group-data-[labels=inline]/wk-rail:tracking-wider',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-rail-muted)]',
    ]), $scope);
@endphp

<div role="group" @if($label) aria-label="{{ $label }}" @endif {{ $attributes->class([$classes, $separatorClasses]) }}>
    @if($label)
        <div class="{{ $labelClasses }}">{{ $label }}</div>
    @endif
    {{ $slot }}
</div>
