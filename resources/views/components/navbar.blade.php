@props([
    'variant' => config('wirekit.components.navbar.variant', 'default'),
    'sticky' => false,
    // When true the navbar skips the `md:` viewport-breakpoint classes and
    // renders the mobile layout unconditionally (hamburger button visible,
    // desktop item row hidden). Useful for (a) previewing the mobile state
    // in docs without resizing the browser, (b) dedicated mobile app views,
    // and (c) embedding the navbar inside a container that is narrower than
    // the 768px breakpoint. Defaults to `false` so existing developers keep
    // the responsive behavior.
    'forceMobile' => false,
    // `container` — when true, wraps the inner flex-row in a max-width
    // container so the navbar's CHROME (background, border, sticky
    // behavior) stays edge-to-edge while the CONTENT (brand, nav items,
    // actions) aligns with the body's container-wrapped column. Mirrors
    // the brand-bar `container` prop. Default false preserves the prior
    // edge-to-edge content behavior.
    'container' => false,
    // `max` — container max-width tier when `container=true`. One of
    // `sm/md/lg/xl/2xl/full`. Defaults to `xl` (the most common
    // marketing-landing-page content width). Reads the same
    // `--size-wk-container-*` tokens as the container component so
    // navbar + body align on the same vertical content-edge spine.
    'max' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $max ??= config('wirekit.components.navbar.max', 'xl');

    // Navbar — opinionated top navigation bar with responsive mobile menu.
    // Variants: default (with bottom border), bordered, transparent, sticky.
    $navClasses = WireKit::resolveClasses('navbar', 'base', implode(' ', [
        'w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-elevated)]',
    ]), $scope);

    $variantClasses = match ($variant) {
        'bordered' => 'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] shadow-[var(--shadow-wk-sm)]',
        'transparent' => 'bg-transparent',
        default => 'border-b border-[var(--color-wk-border)]',
    };

    $stickyClasses = $sticky ? 'sticky top-0 z-[var(--z-wk-sticky)]' : '';

    $isContainerWrapped = filter_var($container, FILTER_VALIDATE_BOOL);
    // No hardcoded fallback values — the `--size-wk-container-*` tokens
    // are the canonical source of truth and ship in dist/wirekit.css.
    $maxClass = $isContainerWrapped
        ? match ($max) {
            'sm' => 'max-w-[var(--size-wk-container-sm)] mx-auto',
            'md' => 'max-w-[var(--size-wk-container-md)] mx-auto',
            'lg' => 'max-w-[var(--size-wk-container-lg)] mx-auto',
            'xl' => 'max-w-[var(--size-wk-container-xl)] mx-auto',
            '2xl' => 'max-w-[var(--size-wk-container-2xl)] mx-auto',
            'full' => 'max-w-full',
            default => WireKit::validateProp('navbar', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full']),
        }
        : '';

    $containerClasses = WireKit::resolveClasses('navbar', 'container', implode(' ', array_filter([
        'flex items-center justify-between',
        'px-[var(--padding-wk-x-lg)]',
        'h-16',
        $maxClass,
    ])), $scope);

    $mobileMenuClasses = WireKit::resolveClasses('navbar', 'mobile-menu', implode(' ', array_filter([
        'border-t border-[var(--color-wk-border)]',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'space-y-1',
        $maxClass,
    ])), $scope);

    // When $forceMobile is on the desktop row is always hidden and the
    // hamburger is always shown; otherwise we use the `md:` breakpoint
    // classes so the layout is responsive.
    $desktopRowClasses = $forceMobile
        ? 'hidden'
        : 'hidden md:flex md:items-center md:gap-1 flex-1 ml-[var(--padding-wk-x-lg)]';
    $desktopActionsClasses = $forceMobile
        ? 'hidden'
        : 'hidden md:flex md:items-center md:gap-[var(--gap-wk-sm)]';
    $hamburgerClasses = $forceMobile ? '' : 'md:hidden';
    $mobileMenuWrapperHide = $forceMobile ? '' : 'md:hidden';
@endphp

<nav
    x-data="{ mobileOpen: false }"
    aria-label="{{ __('Main navigation') }}"
    {{ $attributes->class([$navClasses, $variantClasses, $stickyClasses]) }}
>
    <div class="{{ $containerClasses }}">
        {{-- Brand / Logo slot --}}
        @isset($brand)
            <div class="shrink-0">
                {{ $brand }}
            </div>
        @endisset

        {{-- Desktop nav items (hidden on mobile, or always hidden when forceMobile) --}}
        <div class="{{ $desktopRowClasses }}">
            {{ $slot }}
        </div>

        {{-- Actions slot (always visible on desktop) --}}
        @isset($actions)
            <div class="{{ $desktopActionsClasses }}">
                {{ $actions }}
            </div>
        @endisset

        {{-- Mobile hamburger button --}}
        <button
            type="button"
            x-on:click="mobileOpen = !mobileOpen"
            :aria-expanded="mobileOpen ? 'true' : 'false'"
            aria-controls="wk-navbar-mobile"
            aria-label="{{ __('Toggle navigation') }}"
            class="{{ $hamburgerClasses }} p-2 cursor-pointer rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-subtle)] focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
        >
            {{-- Hamburger icon (open state) --}}
            <svg x-show="!mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
            </svg>
            {{-- Close icon (open state) --}}
            <svg x-show="mobileOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    {{-- Mobile menu (disclosure) --}}
    <div
        id="wk-navbar-mobile"
        x-show="mobileOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        class="{{ $mobileMenuWrapperHide }} {{ $mobileMenuClasses }}"
        x-cloak
    >
        {{ $slot }}

        @isset($actions)
            <div class="pt-[var(--padding-wk-y-md)] border-t border-[var(--color-wk-border)] mt-[var(--padding-wk-y-md)] flex flex-col gap-[var(--gap-wk-sm)]">
                {{ $actions }}
            </div>
        @endisset
    </div>
</nav>
