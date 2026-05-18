@props([
    // `as` — the semantic element. Defaults to `header` for page chrome
    // (the brand-bar sits at the top of a section / page and announces
    // the brand). Set to `nav` if the bar IS the primary navigation.
    'as' => 'header',
    // `divider` — optional bottom-border treatment. Default `bottom`
    // paints a 1 px line across the bar's bottom edge using
    // `--color-wk-border`. Pass `none` for a borderless variant on
    // backgrounds that already provide separation.
    'divider' => 'bottom',
    // `padding` — inline padding tier from the `--padding-wk-x-*`
    // content-edge spine. Defaults to `lg` (= 1 rem) to match the
    // main wrapper and header chrome at the same tier — the brand's
    // visible-text edge aligns with the article body and TOC strip
    // below.
    'padding' => 'lg',
    // `sticky` — when true, pins the bar to the top of the scroll
    // container via `position: sticky; top: 0`. The bar stays visible
    // during scroll without leaving its in-flow vertical space, so the
    // article body below doesn't need a matching `padding-top`
    // reservation. Default `false` keeps the bar in normal flow
    // (scrolls away with content).
    'sticky' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Brand-bar — page-chrome wrapper for the canonical "logo + name +
    // (optional tagline) + (optional actions)" header pattern. Sits at
    // a page edge, carries the content-edge spine padding, optionally
    // sticky during scroll.
    //
    // Composition contract: the brand-bar exposes three named slots in
    // a flex-row layout (`brand`, `tagline`, `actions`), with the default
    // slot acting as additional flex children if the consumer needs
    // custom layout. Slot ordering is deterministic — brand → tagline →
    // spacer (via `margin-left: auto` on actions) → actions.

    $paddingClass = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--padding-wk-x-sm)]',
        'md' => 'px-[var(--padding-wk-x-md)]',
        'lg' => 'px-[var(--padding-wk-x-lg)]',
        'xl' => 'px-[var(--padding-wk-x-xl)]',
        default => WireKit::validateProp(
            'brand-bar',
            'padding',
            $padding,
            ['none', 'sm', 'md', 'lg', 'xl']
        ),
    };

    $dividerClass = match ($divider) {
        'none' => '',
        default => 'border-b border-[var(--color-wk-border)]',
    };

    $stickyClass = filter_var($sticky, FILTER_VALIDATE_BOOL)
        ? 'sticky top-0 z-[var(--z-wk-sticky)] bg-[var(--color-wk-bg)]'
        : '';

    $rootClass = WireKit::resolveClasses('brand-bar', 'base', implode(' ', array_filter([
        'wk-brand-bar',
        // `w-full` keeps the brand-bar full-width inside the docs-site
        // flex-row preview wrapper (see footer.blade.php for rationale).
        'w-full',
        'flex flex-row items-center',
        'gap-[var(--space-wk-md)]',
        'py-[var(--padding-wk-y-md)]',
        $paddingClass,
        $dividerClass,
        $stickyClass,
    ])), $scope);
@endphp

<{{ $as }} {{ $attributes->class([$rootClass]) }}>
    {{-- Brand slot — logo + name combo. Defaults to the `<x-wirekit::brand>`
         primitive composition if the consumer doesn't override. When the
         brand slot is empty AND the default slot is empty, the bar still
         renders so it can be composed declaratively from a parent
         template that injects the brand later via slot stacking. --}}
    @isset($brand)
        {{ $brand }}
    @endisset

    {{-- Tagline slot — secondary descriptive text after the brand, e.g.
         "Ship faster, in less time." or a customer-quote pull. Renders
         only when the consumer fills it. Uses `--color-wk-text-muted`
         for visual hierarchy below the brand name. --}}
    @isset($tagline)
        <span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $tagline }}</span>
    @endisset

    {{-- Default slot — additional flex children between tagline and
         actions. Use for inline content the consumer wants centered in
         the bar that isn't strictly brand / tagline / actions. --}}
    {{ $slot }}

    {{-- Actions slot — right-edge anchored via `margin-left: auto` on a
         wrapper div so the actions always sit at the bar's content
         right-edge regardless of how much brand / tagline content
         precedes them. Sign-in links, theme toggles, account widgets,
         etc. live here. --}}
    @isset($actions)
        <div class="ml-auto flex flex-row items-center gap-[var(--space-wk-sm)]">
            {{ $actions }}
        </div>
    @endisset
</{{ $as }}>
