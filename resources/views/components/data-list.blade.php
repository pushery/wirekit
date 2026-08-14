{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'layout' => config('wirekit.components.data-list.layout', 'horizontal'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('data-list', $attributes->getAttributes());

    // Data list wraps native <dl> for semantic key-value pairs.
    // Layouts: horizontal (label left, value right), stacked, grid.
    // Inline styles guarantee layout in environments where the developer's
    // Tailwind JIT may not see vendor view classes — preview iframes,
    // server-side renders without a Tailwind build step, or any context
    // outside the developer's source-tree scan.
    $layoutStyle = match ($layout) {
        'stacked' => 'display: flex; flex-direction: column; gap: 1rem;',
        'grid' => 'display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem;',
        default => '', // horizontal uses border on items
    };

    $classes = WireKit::resolveClasses('data-list', 'base', implode(' ', [
        'w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
    ]), $scope);
@endphp

{{-- Native <dl> element — screen readers announce as definition list --}}
<dl
    data-layout="{{ $layout }}"
    style="{{ $layoutStyle }}"
    {{ $attributes->class([$classes]) }}
>
    {{ $slot }}
</dl>
