@props([
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Each item is a <dt>/<dd> pair displayed side-by-side.
    // Uses inline styles for layout to guarantee rendering in docs-app
    // where Tailwind JIT may not compile sm: responsive classes.
    $itemClasses = WireKit::resolveClasses('data-list', 'item', implode(' ', [
        'py-[var(--padding-wk-y-sm)]',
    ]), $scope);
@endphp

<div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; border-bottom: 1px solid var(--color-wk-border-subtle);" {{ $attributes->class([$itemClasses]) }}>
    {{-- Label: the "key" in the key-value pair --}}
    @if($label)
        <dt style="width: 33%; flex-shrink: 0; white-space: nowrap;" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[var(--color-wk-text-muted)]">
            {{ $label }}
        </dt>
    @endif

    {{-- Value: the content slot --}}
    <dd style="flex: 1; text-align: right;" class="text-[var(--color-wk-text)]">
        {{ $slot }}
    </dd>
</div>
