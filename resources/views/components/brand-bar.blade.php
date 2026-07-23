{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
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
    // content-edge spine. Defaults to `lg` to match the main wrapper
    // and header chrome at the same tier — the brand's visible-text
    // edge aligns with the article body and TOC strip below.
    'padding' => 'lg',
    // `sticky` — when true, pins the bar to the top of the scroll
    // container via `position: sticky; top: 0`. Default false.
    'sticky' => false,
    // `container` — when true, wraps the inner flex-row in a max-width
    // container so the brand-bar's CHROME (background, border, sticky
    // behavior) stays edge-to-edge while the CONTENT (brand, tagline,
    // actions) aligns with the body's container-wrapped column. Default
    // false preserves the v2.0.0 edge-to-edge content behavior.
    'container' => false,
    // `max` — container max-width tier when `container=true`. One of
    // `sm/md/lg/xl/2xl/full`. Defaults to `xl` (the most common
    // marketing-landing-page content width). Reads the same
    // `--size-wk-container-*` tokens as the container component so
    // brand-bar + body align on the same vertical content-edge spine.
    'max' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $sticky = BooleanProp::from($sticky, false);
    $container = BooleanProp::from($container, false);

    $max ??= config('wirekit.components.brand-bar.max', 'xl');

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

    $isContainerWrapped = filter_var($container, FILTER_VALIDATE_BOOL);
    // No hardcoded fallback values — the `--size-wk-container-*` tokens
    // are the canonical source of truth and ship in dist/wirekit.css.
    $maxClass = match ($max) {
        'sm' => 'max-w-[var(--size-wk-container-sm)]',
        'md' => 'max-w-[var(--size-wk-container-md)]',
        'lg' => 'max-w-[var(--size-wk-container-lg)]',
        'xl' => 'max-w-[var(--size-wk-container-xl)]',
        '2xl' => 'max-w-[var(--size-wk-container-2xl)]',
        'full' => 'max-w-full',
        default => WireKit::validateProp('brand-bar', 'max', $max, ['sm', 'md', 'lg', 'xl', '2xl', 'full']),
    };

    // When container-wrapped, the OUTER element keeps the chrome
    // (background, border, sticky behavior, padding) but loses the
    // flex-row layout — the INNER container takes the layout and the
    // max-width. When edge-to-edge (default), the OUTER element is
    // the flex parent directly.
    $rootClass = WireKit::resolveClasses('brand-bar', 'base', implode(' ', array_filter([
        'wk-brand-bar',
        'w-full',
        $isContainerWrapped ? '' : 'flex flex-row items-center',
        $isContainerWrapped ? '' : 'gap-[var(--space-wk-md)]',
        'py-[var(--padding-wk-y-md)]',
        $paddingClass,
        $dividerClass,
        $stickyClass,
    ])), $scope);

    $innerWrapperClass = "flex flex-row items-center gap-[var(--space-wk-md)] w-full {$maxClass} mx-auto";
@endphp

<{{ $as }} {{ $attributes->class([$rootClass]) }}>
    @if($isContainerWrapped)<div class="{{ $innerWrapperClass }}">@endif
    @isset($brand){{ $brand }}@endisset
    @isset($tagline)<span class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $tagline }}</span>@endisset
    {{ $slot }}
    @isset($actions)<div class="ml-auto flex flex-row items-center gap-[var(--space-wk-sm)]">{{ $actions }}</div>@endisset
    @if($isContainerWrapped)</div>@endif
</{{ $as }}>
