@props([
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Body classes — main content area with padding and text color
    $classes = WireKit::resolveClasses('modal.body', 'base', implode(' ', [
        'px-[var(--padding-wk-x-xl)] py-[var(--padding-wk-y-xl)]',
        'text-[length:var(--text-wk-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[var(--color-wk-text)]',
    ]), $scope);
@endphp

{{-- Modal body — scrollable content area --}}
<div {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</div>
