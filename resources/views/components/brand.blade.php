{{-- optimistic-ui: n/a — navigation
     A logo that links home. Navigation, not a mutation. --}}
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
    // Intrinsic aspect ratio of `$logo` / `$darkLogo`, as `'width/height'` — e.g. `'4/1'`
    // for a wide wordmark.
    //
    // Every logo here is `h-8 w-auto`: the height is fixed and the WIDTH is whatever the
    // image turns out to be. Until its bytes arrive there is nothing to derive that width
    // from, so the element is 0 px wide and everything beside it — the product name, the
    // navigation, the whole header row — sits further left than it will a moment later,
    // then jumps. On an uncached view that is a visible shift on the most prominent row
    // of the page, and it happens on every page load rather than once.
    //
    // The ratio is the missing input and the only one: `width`/`height` attributes would
    // say the same thing less directly, since the `h-8 w-auto` this component ships
    // overrides both and leaves the browser using them for their ratio anyway.
    //
    // Unset is not left to shift: the images carry a min-width of one logo height, so the
    // worst case is a square reservation rather than nothing. Almost every wordmark is
    // wider than tall, which makes that a floor rather than an over-reservation.
    'logoAspect' => null,
    // Same, for `$mobileLogo`. Defaults to `$logoAspect` — a responsive pair is often the
    // same mark at two widths — but a square app icon beside a wide wordmark is exactly
    // the case that needs its own value.
    'mobileLogoAspect' => null,
    'name' => null,
    'href' => '/',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('brand', $attributes->getAttributes());

    // `aspect-ratio` when the caller declared one, and a square floor either way. Both are
    // inline rather than utility classes: the ratio is caller data, and a Tailwind class
    // cannot be built from a runtime value.
    $wkLogoAspect = $logoAspect ? 'aspect-ratio: '.e($logoAspect).'; ' : '';
    $wkMobileAspect = ($mobileLogoAspect ?: $logoAspect)
        ? 'aspect-ratio: '.e($mobileLogoAspect ?: $logoAspect).'; '
        : '';
    $wkLogoStyle = $wkLogoAspect.'min-width: 2rem;';
    $wkMobileStyle = $wkMobileAspect.'min-width: 2rem;';

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
    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // Rendered EXPLICITLY, bag echoed with except('rel'): $attributes->merge()
    // treats a non-class attribute as a DEFAULT, so a caller writing rel="me"
    // (the ordinary IndieAuth/Mastodon verification on a brand link) silently
    // replaced the computed value. See dropdown/item.blade.php.
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr . ' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    // Accessibility — when the brand renders as a link (it always does, via
    // the `<a href>` root) AND the only visible content is the logo image
    // (no name, no slot content), the link has no accessible name. The img
    // is decorative (`alt=""` + `aria-hidden`) by design — the URL alone
    // doesn't describe the destination. We auto-inject `aria-label="{{ __('wirekit::Home') }}"`
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
    // view. Same precedent as grid.blade.php's `$colsMap`. A guard in the package's own
    // suite fails the build if an interpolated variant class reappears here.
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
    @if($logoOnlyNeedsLabel) aria-label="{{ __('wirekit::Home') }}" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$classes]) }}
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
        <img src="{{ $mobileLogo }}" alt="" class="h-8 w-auto {{ $bpHidden }}" style="{{ $wkMobileStyle }}" aria-hidden="true" />
        <span class="hidden {{ $bpInlineFlex }} items-center">
            <img src="{{ $logo }}" alt="" class="wk-light-only h-8 w-auto" style="{{ $wkLogoStyle }}" aria-hidden="true" />
            <img src="{{ $darkLogo }}" alt="" class="wk-dark-only h-8 w-auto" style="{{ $wkLogoStyle }}" aria-hidden="true" />
        </span>
    @elseif($logo && $mobileLogo)
        {{-- Responsive logo swap: mobile-first wordmark below the breakpoint,
             full-width wordmark at + breakpoint. Both images carry the same
             accessibility shape (alt="" + aria-hidden="true") — the <a>'s
             aria-label handles the accessible name. --}}
        <img src="{{ $mobileLogo }}" alt="" class="h-8 w-auto {{ $bpHidden }}" style="{{ $wkMobileStyle }}" aria-hidden="true" />
        <img src="{{ $logo }}" alt="" class="hidden h-8 w-auto {{ $bpBlock }}" style="{{ $wkLogoStyle }}" aria-hidden="true" />
    @elseif($logo && $darkLogo)
        {{-- Mode-aware logo swap: light wordmark in light mode, dark wordmark
             under the `.dark` class (via the wk-light-only / wk-dark-only
             visibility pair in dist/wirekit.css). Both images carry the same
             accessibility shape (alt="" + aria-hidden="true") — the <a>'s
             aria-label / visible name handles the accessible name. --}}
        <img src="{{ $logo }}" alt="" class="wk-light-only h-8 w-auto" style="{{ $wkLogoStyle }}" aria-hidden="true" />
        <img src="{{ $darkLogo }}" alt="" class="wk-dark-only h-8 w-auto" style="{{ $wkLogoStyle }}" aria-hidden="true" />
    @elseif($logo)
        <img src="{{ $logo }}" alt="" class="h-8 w-auto" style="{{ $wkLogoStyle }}" aria-hidden="true" />
    @endif
    @if($name)
        {{-- Same rule as the sidebar row and the profile row beside it: in a collapsed
             rail the word goes sr-only rather than wrapping. Measured at 55px, "Acme
             Console" broke across two lines and made the brand row the tallest thing in
             a column of 32px icons.

             It costs nothing outside a sidebar — the group selector only matches inside
             one, so a brand in a header or a footer is untouched. --}}
        <span class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-lg)] wk-rail-hide">{{ $name }}</span>
    @endif
    {{-- Children render ALONGSIDE the logo and the name, not instead of them.
         This used to be `@if(!$logo && !$name)`, which dropped them silently
         whenever either was set — the documented use (a workspace-switcher
         chevron, a product badge) is exactly the case that was thrown away.

         It also left the link with NO accessible name: `$hasVisibleName` above
         counts slot content, so a `logo` + children brand suppressed the
         `aria-label` fallback and then rendered nothing to replace it. The
         `<a>` held one `alt=""` `aria-hidden` image and nothing else.

         Additive: with no logo and no name the output is byte-identical, and
         with either set the slot rendered nothing before. --}}
    @if(trim((string) $slot) !== '')
        {{ $slot }}
    @endif
    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</a>