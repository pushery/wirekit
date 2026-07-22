@props([
    'logo' => null,
    'mobileLogo' => null,
    // Dark-mode logo. When set, the desktop logo swaps light↔dark by the
    // `.dark` class via the `wk-light-only` / `wk-dark-only` visibility pair
    // in dist/wirekit.css — components never use the Tailwind `dark:` variant
    // (an image-src swap has no design-token mechanism, so the toggle lives in
    // CSS like every other `.dark`-scoped WireKit rule). Composes with
    // `$mobileLogo`: the mobile mark stays mode-neutral below the breakpoint;
    // the light/dark swap applies to the desktop logo at + breakpoint. Null →
    // single-logo behavior (byte-identical back-compat).
    'darkLogo' => null,
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
    // doesn't describe the destination. We auto-inject `aria-label="{{ __('Home') }}"`
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

    // Responsive show/hide classes resolved to FULL literal strings — never
    // `"{$resolvedBreakpoint}:hidden"`. Tailwind v4's content scanner reads the
    // raw template TEXT and cannot resolve a PHP variable, so an interpolated
    // class name (`sm:inline-flex` assembled at render time) is never generated
    // in a developer's CSS-first build — it appears only by accident when the
    // same literal exists elsewhere in their `@source` corpus, and vanishes on a
    // WireKit bump, taking the logo's visibility with it. Emitting the literals
    // here keeps every variant statically discoverable in the scanned vendor
    // view. Same precedent as grid.blade.php's `$colsMap`. Guarded by
    // tests/Feature/InterpolatedVariantClassGuardTest.php.
    $bpHidden = match ($resolvedBreakpoint) {
        'sm' => 'sm:hidden', 'md' => 'md:hidden', 'lg' => 'lg:hidden', 'xl' => 'xl:hidden',
    };
    $bpBlock = match ($resolvedBreakpoint) {
        'sm' => 'sm:block', 'md' => 'md:block', 'lg' => 'lg:block', 'xl' => 'xl:block',
    };
    $bpInlineFlex = match ($resolvedBreakpoint) {
        'sm' => 'sm:inline-flex', 'md' => 'md:inline-flex', 'lg' => 'lg:inline-flex', 'xl' => 'xl:inline-flex',
    };
@endphp

<a
    href="{{ $href }}"
    @if($logoOnlyNeedsLabel) aria-label="{{ __('Home') }}" @endif
    {{ $attributes->merge($opensNewTab ? ['rel' => $finalRel] : [])->class([$classes]) }}
>
    @if($logo instanceof \Illuminate\View\ComponentSlot)
        {{-- A NAMED slot `<x-slot:logo>...</x-slot:logo>` was passed
             instead of the URL prop — render its raw markup so callers
             can drop in a custom SVG / colored div / icon without
             being forced into the URL-string contract. Blueprint authors
             routinely reach for this shape. Pre-fix this code path
             crashed the <a>'s HTML because the @elseif below stringified
             the slot inside <img src="..."> attribute, leaving the rest
             of the attribute string visible as text in the rendered DOM.
             Branch placed FIRST so it wins against the URL branches
             below; the URL branches' `truthy-string` test no longer
             matches a ComponentSlot when this @if eats it. --}}
        {{ $logo }}
    @elseif($logo && $mobileLogo && $darkLogo)
        {{-- Responsive + mode-aware. Mode-neutral mobile mark below the
             breakpoint; at + breakpoint the desktop wordmark swaps light↔dark
             by the `.dark` class. The breakpoint lives on the wrapper <span>
             and the mode swap on the inner <img>s (via wk-light-only /
             wk-dark-only) so the two axes never combine on ONE element — a
             single element carrying both `{bp}:block` and `wk-dark-only` is a
             0,1,0 specificity tie decided by stylesheet load order (fragile).
             Splitting them onto the span vs the imgs keeps it deterministic. --}}
        <img src="{{ $mobileLogo }}" alt="" class="h-8 w-auto {{ $bpHidden }}" aria-hidden="true" />
        <span class="hidden {{ $bpInlineFlex }} items-center">
            <img src="{{ $logo }}" alt="" class="wk-light-only h-8 w-auto" aria-hidden="true" />
            <img src="{{ $darkLogo }}" alt="" class="wk-dark-only h-8 w-auto" aria-hidden="true" />
        </span>
    @elseif($logo && $mobileLogo)
        {{-- Responsive logo swap: mobile-first wordmark below the breakpoint,
             full-width wordmark at + breakpoint. Both images carry the same
             accessibility shape (alt="" + aria-hidden="true") — the <a>'s
             aria-label handles the accessible name. --}}
        <img src="{{ $mobileLogo }}" alt="" class="h-8 w-auto {{ $bpHidden }}" aria-hidden="true" />
        <img src="{{ $logo }}" alt="" class="hidden h-8 w-auto {{ $bpBlock }}" aria-hidden="true" />
    @elseif($logo && $darkLogo)
        {{-- Mode-aware logo swap: light wordmark in light mode, dark wordmark
             under the `.dark` class (via the wk-light-only / wk-dark-only
             visibility pair in dist/wirekit.css). Both images carry the same
             accessibility shape (alt="" + aria-hidden="true") — the <a>'s
             aria-label / visible name handles the accessible name. --}}
        <img src="{{ $logo }}" alt="" class="wk-light-only h-8 w-auto" aria-hidden="true" />
        <img src="{{ $darkLogo }}" alt="" class="wk-dark-only h-8 w-auto" aria-hidden="true" />
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
        <span class="sr-only">{{ __('(opens in new tab)') }}</span>
    @endif
</a>
