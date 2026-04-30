@props([
    'layout' => config('wirekit.components.data-list.layout', 'horizontal'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Data list wraps native <dl> for semantic key-value pairs.
    // Layouts: horizontal (label left, value right), stacked, grid.
    // Inline styles ensure layout works on docs.wirekit.app where Tailwind
    // JIT may not compile sm: responsive classes from vendor views.
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
