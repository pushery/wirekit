{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'container' => false,
    'padding' => config('wirekit.components.main.padding', 'lg'),
    // cap the content width by default so dashboards don't stretch
    // edge-to-edge on 1900px+ monitors. Default '2xl' matches the
    // container component's '2xl' tier — most full-page layouts
    // already wrap the slot in a container with that cap, so adopting
    // the same default here unifies the two surfaces. Opt out with
    // max="none" to preserve the pre-2.3.0 unbounded behavior.
    // Reads from config to allow per-app overrides.
    'max' => null,
    'scope' => null,
    // First-class id prop — pairs with the wirekit::skip-link helper.
    // When set, the main element also becomes programmatically focusable
    // (tabindex="-1") so the skip-link's fragment navigation moves
    // keyboard focus INTO the landmark (browser default would only scroll).
    'id' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $container = BooleanProp::from($container, false);

    // warn on unknown prop keys in dev.
    WireKit::warnUnknownProps('main', $attributes->getAttributes());

    // Main — primary content area in app-shell layouts.
    // `wk-main` marker — load-bearing against developer prose
    // `max-width: 75ch` clamps (see footer.blade.php for the full
    // rationale).
    $classes = WireKit::resolveClasses('main', 'base', implode(' ', [
        'wk-main',
        'flex-1',
        'wk-scrollbar overflow-y-auto',
    ]), $scope);

    // Horizontal padding uses the same `--padding-wk-x-{size}` tokens as
    // `<x-wirekit::header>`, so a sibling Header + Main pair (the canonical
    // app-shell layout) shares one vertical alignment line. Vertical padding
    // stays on the generic `--space-wk-{size}` scale for breathing room.
    $paddingClasses = match ($padding) {
        'none' => '',
        'sm' => 'px-[var(--padding-wk-x-sm)] py-[var(--space-wk-sm,0.5rem)]',
        'md' => 'px-[var(--padding-wk-x-md)] py-[var(--space-wk-md,1rem)]',
        'lg' => 'px-[var(--padding-wk-x-lg)] py-[var(--space-wk-lg,1.5rem)]',
        'xl' => 'px-[var(--padding-wk-x-xl)] py-[var(--space-wk-xl,2.5rem)]',
        default => WireKit::validateProp('main', 'padding', $padding, ['none', 'sm', 'md', 'lg', 'xl']),
    };

    // Resolve max-width tier. `null` reads config (default `2xl`);
    // explicit `none` (or empty string) skips the cap wrapper entirely
    // for back-compat. Anything else maps to a `--size-wk-container-*`
    // token via the same scale as `<x-wirekit::container>`.
    $resolvedMax = $max ?? config('wirekit.components.main.max', '2xl');
    $maxClass = match ($resolvedMax) {
        'none', '' => null,
        'sm' => 'max-w-[var(--size-wk-container-sm)]',
        'md' => 'max-w-[var(--size-wk-container-md)]',
        'lg' => 'max-w-[var(--size-wk-container-lg)]',
        'xl' => 'max-w-[var(--size-wk-container-xl)]',
        '2xl' => 'max-w-[var(--size-wk-container-2xl,96rem)]',
        'full' => 'max-w-full',
        default => WireKit::validateProp('main', 'max', (string) $resolvedMax, ['none', 'sm', 'md', 'lg', 'xl', '2xl', 'full']),
    };
@endphp

<main
    @if($id !== null) id="{{ $id }}" tabindex="-1" @endif
    {{ $attributes->class([$classes, $paddingClasses]) }}
>
    @if($container || $maxClass !== null)
        <div class="{{ $maxClass ?? 'max-w-[var(--size-wk-container-2xl,96rem)]' }} mx-auto w-full">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</main>
