{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'label' => null,
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('data-list.item', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Each item is a <dt>/<dd> pair displayed side-by-side.
    // Uses inline styles for layout to guarantee rendering in environments
    // where the developer's Tailwind JIT may not see vendor view classes
    // (preview iframes, SSR without a Tailwind build step, embeds).
    // `wk-data-list-item` carries the inter-row separator via a shipped
    // dist/wirekit.css rule (`.wk-data-list-item:not(:last-child)`) rather
    // than an inline border-bottom — so the LAST row omits the rule and no
    // longer doubles against the container's own bottom border. The rest of the layout stays inline for preview-iframe / SSR
    // robustness.
    $itemClasses = WireKit::resolveClasses('data-list', 'item', implode(' ', [
        'wk-data-list-item',
        'py-[var(--padding-wk-y-sm)]',
    ]), $scope);
@endphp

<div {{ $attributes->merge(['style' => 'display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;'])->class([$itemClasses]) }}>
    {{-- Label: the "key" in the key-value pair. min-width: 0 +
         overflow-wrap: anywhere let long single-word labels (e.g.
         "Berufsunfähigkeitsversicherung", "Mietpreisbremse" — German
         compound nouns are common in real-estate / insurance content)
         wrap inside the 33% track on narrow viewports instead of
         bleeding past their cell and pushing the parent's scrollWidth
         past the viewport. --}}
    @if($label)
        <dt style="width: 33%; flex-shrink: 1; min-width: 0; overflow-wrap: anywhere;" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)]">
            {{ $label }}
        </dt>
    @endif

    {{-- Value: the content slot. overflow-wrap: anywhere covers the
         symmetric case where the VALUE is a long single token (URL,
         compound German noun, file path). --}}
    <dd style="flex: 1; min-width: 0; text-align: right; overflow-wrap: anywhere;" class="text-[color:var(--color-wk-text)]">
        {{ $slot }}
    </dd>
</div>
