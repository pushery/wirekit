{{-- wirekit:spine-participant — this component joins the page-edge content spine. See docs/extending/spine-contract.md --}}
@props([
    'sticky' => false,
    'container' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Header — top-level page header for app-shell layouts.
    //
    // Inline `display: flex; align-items: center` is the load-bearing layout
    // primitive — same lesson as the chart-wrapper, sparkline, reading-spine
    // fixes: utility classes for decoration, inline style for layout that
    // must work even when developer Tailwind isn't in scope (the docs-sandbox
    // iframe is the canonical example, but any tenant-isolated CSS context
    // hits the same gap). Without `display: flex` inline, the slot children
    // stack vertically in the sandbox and `<x-wirekit::spacer />` /
    // `margin-left: auto` idioms can't push items to the right edge because
    // the container isn't actually a flex parent.
    $headerStyle = 'display: flex; align-items: center; width: 100%; gap: var(--gap-wk-md);';
    // `wk-header` marker — see `dist/wirekit.css` notes for why this is
    // load-bearing against developer prose `max-width: 75ch` clamps.
    $classes = WireKit::resolveClasses('header', 'base', implode(' ', [
        'wk-header',
        // `w-full` keeps the header full-width inside docs.wirekit.app
        // flex-row preview wrapper (see footer.blade.php for full rationale).
        'w-full',
        'flex items-center',
        'h-16',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-b border-[var(--color-wk-border)]',
        'px-[var(--padding-wk-x-lg)]',
        'gap-[var(--gap-wk-md)]',
    ]), $scope);

    $stickyClasses = $sticky ? 'sticky top-0 z-[var(--z-wk-sticky)]' : '';

    // When `:container="true"`, the inner wrapper takes 100% of the header
    // content area up to the 2xl container limit and centres itself with
    // auto margins. The flex + width / max-width / margin properties are
    // inlined for the same reason as the outer header.
    $innerStyle = 'display: flex; align-items: center; width: 100%; max-width: var(--size-wk-container-2xl, 96rem); margin-left: auto; margin-right: auto; gap: var(--gap-wk-md);';
@endphp

<header {{ $attributes->class([$classes, $stickyClasses]) }} style="{{ $headerStyle }}">
    @if($container)
        <div class="flex items-center w-full max-w-[var(--size-wk-container-2xl,96rem)] mx-auto gap-[var(--gap-wk-md)]" style="{{ $innerStyle }}">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif
</header>
