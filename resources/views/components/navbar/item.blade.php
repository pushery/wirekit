@props([
    'href' => '#',
    'active' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Navbar item — navigation link with active state indicator.
    $classes = WireKit::resolveClasses('navbar.item', 'base', implode(' ', [
        'inline-flex items-center',
        'px-[var(--padding-wk-x-sm)]',
        'py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'rounded-[var(--radius-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $colorClasses = $active
        ? 'text-[color:var(--color-wk-accent)] font-[number:var(--font-wk-heading-weight)]'
        : 'text-[color:var(--color-wk-text)]';

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See sidebar/item.blade.php for the rationale on except('rel') + explicit
    // rel attribute — $attributes->merge treats rel as a default, so a caller
    // that passes rel="prev" would silently defeat our security injection.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);
@endphp

<a
    href="{{ $href }}"
    @if($active) aria-current="page" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes, $colorClasses]) }}
>
    {{ $slot }}
    @if($opensNewTab)
        <span class="sr-only">{{ __('(opens in new tab)') }}</span>
    @endif
</a>
