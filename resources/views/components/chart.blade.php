{{-- Chart component — renders a chart container with Alpine.js lifecycle management.
     The inner mount element branches on the active adapter's rendersTo():
       - 'canvas' for raster libraries (Chart.js)
       - 'div'    for SVG libraries (ApexCharts)
     wire:ignore prevents Livewire's DOM morphing from destroying the chart state.
     role="img" marks the chart as a graphical element for screen readers. --}}
@php
    // The chart is a CLASS-based component (<x-wirekit-chart>, single hyphen) — the
    // Component class supplies the chart data and $height. Rendered anonymously as
    // <x-wirekit::chart> (double colon) there is no class, so fail with a clear,
    // actionable message instead of the cryptic "Undefined variable $height" the
    // view would otherwise throw further down. The class — and the direct
    // Blade-string render tests — always supply $height, so this guard only trips
    // the unsupported anonymous tag.
    if (! isset($height)) {
        throw new \RuntimeException(
            'WireKit: the chart is a class-based component — use <x-wirekit-chart …> '
            .'(single hyphen), not the anonymous <x-wirekit::chart …>. '
            .'See https://docs.wirekit.app/components/chart'
        );
    }

    // Extract caller's aria-label (if any) and render it explicitly so we can
    // supply a fallback; drop it from the bag to avoid emitting a duplicate
    // attribute when the bag is echoed below.
    $chartAriaLabel = $attributes->get('aria-label', 'Chart');

    // Merge caller-supplied inline `style` with the component's own hardcoded
    // style (height + background). Without this manual merge Blade would emit
    // two separate `style=""` attributes on the same element — only the first
    // wins in every browser, so the caller's overrides (e.g. max-width) would
    // silently disappear. We strip `style` from the bag, concatenate the
    // default style first, caller style second (caller wins on duplicate
    // properties because later declarations override earlier ones).
    //
    // overflow: hidden on the chart wrapper is non-negotiable — ApexCharts
    // SVG renders with overflow: visible by default, which lets smooth-line
    // interpolations overshoot the plot area and visibly escape the chart
    // container into surrounding page content. Clipping at the wrapper edge
    // contains the SVG without affecting ApexCharts' tooltip positioning
    // (tooltips are absolute-positioned inside the wrapper but their target
    // points stay within the plot area).
    /*
     * Wrapper width MUST be inline `width: 100%` — NOT the Tailwind
     * `w-full` utility we used to set on this element. In every
     * rendering context that doesn't load the developer's Tailwind
     * bundle (the docs-preview iframe-srcdoc is the canonical
     * example, but any tenant-isolated context with its own CSS
     * scope hits the same gap) the `.w-full` class doesn't match
     * any rule, the wrapper collapses to `width: auto`, and the
     * chart inside resolves its 100% against the auto-shrunk
     * parent — visible as the "chart at 1/3 of preview width" bug
     * reported on multiple `/components/charts-apex/*` and
     * `/components/charts-chartjs/advanced` pages. `min-width: 0`
     * cooperates with flex-grid contexts where children default to
     * `min-width: auto` (= content size). `display: block` belt-and-
     * suspenders against any "inline display" parent (where the
     * Tailwind utility names would be `inline‑flex` and `inline‑grid`,
     * spelled here with a non-breaking hyphen U+2011 so Tailwind v4's
     * content scanner doesn't extract these prose mentions as
     * generated classes — they're only mentioned as documentation
     * here, not actually emitted on any DOM element).
     */
    $callerStyle = (string) $attributes->get('style', '');

    // Inline mode renders the wrapper + inner mount as `<span>` elements so
    // the whole tree is phrasing content and stays legal inside a `<p>` —
    // critical for inline-sparkline usage in running prose where HTML5's
    // parser auto-closes the paragraph on the first `<div>` descendant.
    // The `<span>` shape uses `display: inline-block` so layout dimensions
    // (height, width) still apply.
    $wrapperTag = ($inline ?? false) ? 'span' : 'div';
    $defaultDisplay = ($inline ?? false) ? 'inline-block; vertical-align: middle' : 'block';
    $defaultStyle = "width: 100%; min-width: 0; display: {$defaultDisplay}; height: {$height}; background-color: var(--color-wk-bg); overflow: hidden; position: relative;";
    $mergedStyle = trim($defaultStyle.' '.$callerStyle);
    $chartAttributes = $attributes->except(['aria-label', 'style']);

    // Default the mountElement prop to 'canvas' so existing developers / direct
    // Blade-string tests that don't go through the Component class keep working.
    $resolvedMountElement = $mountElement ?? 'canvas';

    // Wire-streaming attributes — when wireStream is
    // set, the Alpine factory listens for matching x-on:<event>.window events
    // and appends the incoming `point` payload into the chart via the library's
    // imperative API. Three data-* attributes carry the configuration so the
    // factory can read them at init-time without an additional config-merge step.
    $wireStreamEvent = $wireStream ?? null;
    $wireStreamModeAttr = $wireStreamMode ?? 'strict';
    $wireStreamCapAttr = (int) ($wireStreamCap ?? 100);

    // Replay-button contract — docs sites surface a `↻ Replay` button on
    // previews whose root carries `data-replayable="true"`. Auto-opt-in
    // when wireStream is bound (every streaming chart is replay-worthy:
    // clicking replay resets the live ticker), otherwise honor the
    // explicit `replayable` prop. Caller-supplied `data-replayable`
    // attributes on `<x-wirekit-chart>` still pass through via the
    // attribute bag (we don't strip the key), and the first-class
    // prop is the recommended path.
    $emitReplayable = ($replayable ?? false) || $wireStreamEvent !== null;
    // Caller-supplied `data-replayable` (on `<x-wirekit-chart>` directly)
    // still flows through the attribute bag; pull it out so we can emit
    // it on the outer wrapper instead of the inner `x-data` element.
    $callerReplayable = $chartAttributes->get('data-replayable');
    $chartAttributes = $chartAttributes->except(['data-replayable']);
    $needsReplayWrapper = $emitReplayable || $callerReplayable !== null;
@endphp
@if ($needsReplayWrapper)
    {{-- Outer wrapper carries `data-replayable="true"` for docs.wirekit.app
         replay button. CRITICAL: the attribute MUST sit on a DIFFERENT
         element than the chart's `x-data` scope. The docs.wirekit.app replay
         path does `target.innerHTML = ''; target.innerHTML = saved;
         Alpine.initTree(target)` — if `target` is the same element that
         carries `x-data`, the wrapper itself persists with its already-
         initialized Alpine scope, only its children get re-injected,
         and the chart's `init()` (which creates the ApexCharts instance
         and fires the entrance animation) never re-runs. Wrapping the
         chart in a non-Alpine, non-wire:ignore div makes the ENTIRE
         chart subtree get destroyed and re-created on replay — fresh
         Alpine init, fresh ApexCharts.render(), fresh entrance
         animation. --}}
    <{{ $wrapperTag }}
        data-replayable="true"
        style="display: {{ ($inline ?? false) ? 'inline-block' : 'block' }}; width: 100%;"
    >
@endif
<{{ $wrapperTag }}
    x-data="{{ $alpineComponent }}(@js($chartConfig))"
    @if ($wireStreamEvent)
        data-wire-stream-event="{{ $wireStreamEvent }}"
        data-wire-stream-mode="{{ $wireStreamModeAttr }}"
        data-wire-stream-cap="{{ $wireStreamCapAttr }}"
    @endif
    {{-- Tailwind utilities still applied as a courtesy for developers
         whose CSS bundle DOES load `relative` / `w-full` (the inline
         style above is the source of truth for either class's behavior;
         these merely keep the rendered class= tidy for any styling that
         developers might want to override on top). --}}
    {{ $chartAttributes->class(['relative w-full']) }}
    style="{{ $mergedStyle }}"
    wire:ignore
    role="img"
    aria-label="{{ $chartAriaLabel }}"
>
    {{--
        Inner mount element — hidden from assistive tech; the parent div
        carries the semantic role. The Alpine factory references this via
        x-ref="canvas" or x-ref="mount" depending on which library is active.

        Inline `width: 100%; height: 100%;` on the SVG-mount div instead of
        Tailwind utilities so the height resolves CORRECTLY in every
        rendering context — including a docs-preview iframe-srcdoc where
        the developer's Tailwind utilities aren't necessarily loaded. With
        the previous `class="h-full w-full"`, the mount div ended up at 0
        px tall whenever Tailwind wasn't in the bundle (no `.h-full` rule
        to resolve), which made ApexCharts' `height: '100%'` setting fall
        back to its built-in default (~495 px) — visibly larger than the
        chart wrapper's inline 380 px height, producing charts that
        overflowed below the preview frame on every page under
        /components/charts-apex/. The Chart.js canvas mount uses the
        adapter's `responsive: true; maintainAspectRatio: false;` so its
        sizing is driven by the wrapper's clientHeight; no inline-style
        rescue needed there, but added for parity / defensive consistency.
    --}}
    @if ($resolvedMountElement === 'div')
        <{{ $wrapperTag }} x-ref="mount" aria-hidden="true" style="width: 100%; height: 100%; display: block;"></{{ $wrapperTag }}>
    @else
        <canvas x-ref="canvas" aria-hidden="true" style="width: 100%; height: 100%;"></canvas>
    @endif
</{{ $wrapperTag }}>
@if ($needsReplayWrapper)
    </{{ $wrapperTag }}>
@endif
