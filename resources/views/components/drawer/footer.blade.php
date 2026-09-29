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
    \Pushery\WireKit\WireKit::warnUnknownProps('drawer.footer', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Footer classes — bottom section with top border and right-aligned buttons
    $classes = WireKit::resolveClasses('drawer.footer', 'base', implode(' ', [
        // One token on all four sides, and the header's. Wider side padding would start the content
        // further in than the title above it and sit it further from the sides than from the
        // bottom; equal padding on one token keeps a single edge down the panel, and a theme
        // cannot pull the sides apart.
        'p-[var(--padding-wk-x-lg)]',
        'border-t',
        'border-[var(--color-wk-border-subtle)]',
        // Wrapping, because what does not fit a row packed to its end leaves on its START side,
        // and nothing scrolls to that side: three actions on a phone cut the first one off the
        // panel, out of reach. Wrapped, they stack at the end; one line on a desktop, as before.
        'flex flex-wrap items-center justify-end gap-x-[var(--gap-wk-md)] gap-y-[var(--gap-wk-md)]',
    ]), $scope);
@endphp

{{-- Drawer footer — action buttons area --}}
<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
