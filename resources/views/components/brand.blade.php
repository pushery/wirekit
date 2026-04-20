@props([
    'logo' => null,
    'name' => null,
    'href' => '/',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Brand — logo + name combo for header and sidebar.
    $classes = WireKit::resolveClasses('brand', 'base', implode(' ', [
        'flex items-center shrink-0',
        'gap-[var(--gap-wk-sm)]',
        'text-[var(--color-wk-text)]',
        'no-underline',
    ]), $scope);
    // Auto-inject rel="noopener noreferrer" when target="_blank"
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr . ' noopener noreferrer')
        : $relAttr;
@endphp

<a href="{{ $href }}" {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$classes]) }}>
    @if($logo)
        <img src="{{ $logo }}" alt="" class="h-8 w-auto" aria-hidden="true" />
    @endif
    @if($name)
        <span class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-lg)]">{{ $name }}</span>
    @endif
    @if(!$logo && !$name)
        {{ $slot }}
    @endif
    @if($opensNewTab)
        <span class="sr-only">(opens in new tab)</span>
    @endif
</a>
