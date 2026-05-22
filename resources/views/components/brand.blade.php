@props([
    'logo' => null,
    'mobileLogo' => null,
    // Tailwind breakpoint at which the responsive-logo swap flips back to
    // the full `$logo`. Values: 'sm' / 'md' / 'lg' / 'xl'. Default 'sm'
    // (640px) — wide wordmark logos in a brand-bar typically clear sm+
    // viewports. When `$mobileLogo` is null, this prop is a no-op.
    'mobileBreakpoint' => 'sm',
    'name' => null,
    'href' => '/',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Brand — logo + name combo for header and sidebar.
    // The `wk-brand` marker class drives the doubled-class anti-prose-
    // typography selector in `dist/wirekit.css` that defeats developer
    // prose `<a>` styling (typical pattern: `.{prose-class} a { text-
    // decoration: underline }`) without resorting to `!important`. The
    // Tailwind `no-underline` utility alone loses on specificity to a
    // developer prose-stylesheet rule that targets `<a>` inside a prose
    // wrapper — the doubled-class `.wk-brand.wk-brand` selector wins
    // on specificity (0,2,0) against `.{prose-class} a` (0,1,1).
    $classes = WireKit::resolveClasses('brand', 'base', implode(' ', [
        'wk-brand',
        'flex items-center shrink-0',
        'gap-[var(--gap-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
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

    // Responsive-logo swap. When $mobileLogo is set, render TWO <img> tags:
    //   - mobile <img>: visible below the breakpoint, hidden at + breakpoint
    //   - main <img>:   hidden below the breakpoint, visible at + breakpoint
    // When $mobileLogo is null, only the main <img> renders (back-compat).
    // Validates the breakpoint against the Tailwind responsive enum;
    // unknown values throw in debug, fall back to 'sm' in prod via the
    // central strictness gate.
    $resolvedBreakpoint = $logo && $mobileLogo
        ? WireKit::validateProp('brand', 'mobileBreakpoint', $mobileBreakpoint, ['sm', 'md', 'lg', 'xl'])
        : 'sm';
@endphp

<a
    href="{{ $href }}"
    @if($logoOnlyNeedsLabel) aria-label="Home" @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$classes]) }}
>
    @if($logo && $mobileLogo)
        {{-- Responsive logo swap: mobile-first wordmark below the breakpoint,
             full-width wordmark at + breakpoint. Both images carry the same
             accessibility shape (alt="" + aria-hidden="true") — the <a>'s
             aria-label handles the accessible name. --}}
        <img src="{{ $mobileLogo }}" alt="" class="h-8 w-auto {{ $resolvedBreakpoint }}:hidden" aria-hidden="true" />
        <img src="{{ $logo }}" alt="" class="hidden h-8 w-auto {{ $resolvedBreakpoint }}:block" aria-hidden="true" />
    @elseif($logo)
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
