@props([
    'required' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Label classes: all values reference design tokens — no hardcoded colors or sizes
    $classes = WireKit::resolveClasses('label', 'base', implode(' ', [
        'block',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-body-weight)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'text-[length:var(--text-wk-md)]',
        'font-medium',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<label {{ $attributes->class([$classes]) }}>
    {{ $slot }}
    {{-- Required indicator uses danger-text variable (auto dark mode, no dark: needed) --}}
    @if($required)
        <span class="text-[color:var(--color-wk-danger-text)] ml-0.5" aria-hidden="true">*</span>
    @endif
</label>
