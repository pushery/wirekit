{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('modal.footer', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Footer classes — bottom section with top border and right-aligned buttons
    $classes = WireKit::resolveClasses('modal.footer', 'base', implode(' ', [
        // ONE TOKEN ON ALL FOUR SIDES, and the header's. The sides used to take 1.5rem against 1rem
        // above and below, so the title started 1rem from the panel's edge and the content under it
        // 1.5rem, and the content sat further from the sides than from the bottom. Equal padding
        // on one token keeps a single edge down the panel, and a theme cannot pull the sides apart.
        'p-[var(--padding-wk-x-lg)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
        // Wrapping, because what does not fit a row packed to its end leaves on its START side,
        // and nothing scrolls to that side: three actions on a phone cut the first one off the
        // panel, out of reach. Wrapped, they stack at the end; one line on a desktop, as before.
        'flex flex-wrap items-center justify-end gap-x-[var(--gap-wk-md)] gap-y-[var(--gap-wk-md)]',
    ]), $scope);
@endphp

{{-- Modal footer — action buttons area --}}
<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
