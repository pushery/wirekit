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

    // Accessibility — when the brand renders as a link (it always does, via
    // the `<a href>` root) AND the only visible content is the logo image
    // (no name, no slot content), the link has no accessible name. The img
    // is decorative (`alt=""` + `aria-hidden`) by design — the URL alone
    // doesn't describe the destination. We auto-inject `aria-label="Home"`
    // for the logo-only case so screen readers announce a usable target.
    // Caller-provided `aria-label` always wins (`merge()` treats it as
    // default). Empty `name` + empty slot = logo-only; presence of either
    // means an accessible name is already provided by the visible text.
    $hasVisibleName = $name !== null || trim((string) $slot) !== '';
    $logoOnlyNeedsLabel = $logo && ! $hasVisibleName && ! $attributes->has('aria-label');
@endphp

<a
    href="{{ $href }}"
    @if($logoOnlyNeedsLabel) aria-label="Home" @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$classes]) }}
>
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
