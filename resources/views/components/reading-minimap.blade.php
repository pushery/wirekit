@props([
    'target' => null,
    'itemSelector' => 'a, h2, h3, [data-minimap-item]',
    'width' => '60px',
    'side' => 'right',
    'draggable' => true,
    'hideBelow' => 'lg',
    'scope' => null,
    // rendered-mode — rendered mode (literal scaled-down page preview)
    'mode' => 'stripes',         // 'stripes' (default, back-compat) | 'rendered'
    'renderTarget' => null,      // CSS selector for the source to clone (defaults to `target`)
    // stripe-mode visual: 'line' (default, thin 2px stripes per item) | 'block' (taller skeleton-style gray rectangles whose height tracks each source item's natural height — gives the minimap a content-texture instead of a sparse list of lines)
    'itemStyle' => 'line',
    // rendered-mode Extensions
    'hoverPreview' => false,     // E1 — magnified popover near cursor
    'showBookmarks' => true,     // E2 — bookmark marker (auto-wires if a reading-bookmark sibling is present)
    'headingAnchors' => false,   // E3 — clickable mini-anchors on the outboard edge
    'headingLevels' => '2,3',    // E3 — CSV of heading levels to surface as anchors
    'autoFadeIdle' => true,      // E4 — fade to --reading-minimap-idle-opacity after 3s idle
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $draggable = BooleanProp::from($draggable, true);
    $hoverPreview = BooleanProp::from($hoverPreview, false);
    $showBookmarks = BooleanProp::from($showBookmarks, true);
    $headingAnchors = BooleanProp::from($headingAnchors, false);
    $autoFadeIdle = BooleanProp::from($autoFadeIdle, true);

    // Reading-minimap — every-item density overview of a scrollable container.
    // Sibling primitive to reading-spine in the reading-* family.
    //
    // Two rendering modes:
    //
    //   mode="stripes" (default) — one colored stripe per matched item.
    //     Lightweight, no iframe, suitable for any developer.
    //
    //   mode="rendered" — abstract content-texture canvas (code-editor-style
    //     minimap). Walks the source DOM via TreeWalker,
    //     draws per-line rectangles on a DPR-aware canvas at minimap
    //     scale. Visually mirrors the source page's content rhythm so
    //     developers read "where am I in THIS specific layout" rather
    //     than "what's the abstract content distribution".
    //
    // Four optional extensions layer on top of either mode:
    //   - hoverPreview   — magnified popover near cursor
    //   - showBookmarks  — marker from a reading-bookmark sibling
    //   - headingAnchors — clickable mini-labels on the outboard edge
    //   - autoFadeIdle   — fade after 3s of inactivity

    // Validate the side prop (left | right).
    $sideValue = match ($side) {
        'left', 'right' => $side,
        default => WireKit::validateProp(
            'reading-minimap',
            'side',
            $side,
            ['left', 'right']
        ),
    };

    // Validate the mode prop (stripes | rendered).
    $modeValue = match ($mode) {
        'stripes', 'rendered' => $mode,
        default => WireKit::validateProp(
            'reading-minimap',
            'mode',
            $mode,
            ['stripes', 'rendered']
        ),
    };

    $itemStyleValue = match ($itemStyle) {
        'line', 'block' => $itemStyle,
        default => WireKit::validateProp(
            'reading-minimap',
            'itemStyle',
            $itemStyle,
            ['line', 'block']
        ),
    };

    // hideBelow — Tailwind responsive prefix that toggles display.
    // Mobile (< breakpoint) never sees the minimap — it's a desktop
    // affordance; fingers don't have hover and the column would crowd
    // narrow viewports. 'none' opts out for developers who want to manage
    // visibility themselves.
    $hideBelowClass = match ($hideBelow) {
        'sm' => 'hidden sm:block',
        'md' => 'hidden md:block',
        'lg' => 'hidden lg:block',
        'xl' => 'hidden xl:block',
        'none' => '',
        default => 'hidden lg:block',
    };

    // Position class — left or right edge of the host.
    $positionClass = match ($sideValue) {
        'left' => 'left-0',
        default => 'right-0',
    };

    $rootClass = WireKit::resolveClasses('reading-minimap', 'base', implode(' ', [
        'wk-reading-minimap',
        'absolute top-0 bottom-0 z-[var(--z-wk-sticky)]',
        $positionClass,
        $hideBelowClass,
    ]), $scope);

    // headingLevels — CSV → int[] for the plugin.
    $headingLevelsArray = collect(explode(',', (string) $headingLevels))
        ->map(fn ($v) => (int) trim($v))
        ->filter(fn ($v) => $v >= 1 && $v <= 6)
        ->values()
        ->all();

    $alpineOptions = json_encode([
        'target' => $target,
        'itemSelector' => $itemSelector,
        'side' => $sideValue,
        'draggable' => filter_var($draggable, FILTER_VALIDATE_BOOL),
        // rendered-mode — rendered mode + extensions
        'mode' => $modeValue,
        'itemStyle' => $itemStyleValue,
        'renderTarget' => $renderTarget,
        'hoverPreview' => filter_var($hoverPreview, FILTER_VALIDATE_BOOL),
        'showBookmarks' => filter_var($showBookmarks, FILTER_VALIDATE_BOOL),
        'headingAnchors' => filter_var($headingAnchors, FILTER_VALIDATE_BOOL),
        'headingLevels' => $headingLevelsArray,
        'autoFadeIdle' => filter_var($autoFadeIdle, FILTER_VALIDATE_BOOL),
    ], JSON_THROW_ON_ERROR);

    $autoFadeFlag = filter_var($autoFadeIdle, FILTER_VALIDATE_BOOL) ? 'true' : 'false';
@endphp

<div
    x-data="wirekitReadingMinimap({{ $alpineOptions }})"
    aria-hidden="true"
    data-side="{{ $sideValue }}"
    data-mode="{{ $modeValue }}"
    data-item-style="{{ $itemStyleValue }}"
    data-auto-fade="{{ $autoFadeFlag }}"
    {{-- Idle-state attribute toggled by the controller (E4). Starts false. --}}
    x-bind:data-idle="idle ? 'true' : 'false'"
    {{-- Catch mousemove on the wrapper rather than on each <li> stripe.
         The viewport-overlay rectangle (z-index: 1) sits on top of the
         stripes, so a listener on <li> never fires while the cursor is
         over the overlay — which is most of the column once scroll
         position is non-zero. The wrapper itself has `pointer-events:
         none` (so it isn't a hit target), but mousemove events that
         fire on its descendants (stripes, overlay) bubble up through
         it and trigger this listener. trackTooltip() short-circuits
         when no tooltipText is set, so the global capture is cheap. --}}
    @mousemove="trackTooltip($event)"
    {{ $attributes->class([$rootClass]) }}
    style="width: {{ $width }};"
>
    {{-- Rendered-mode iframe — only mounted after the IntersectionObserver
         fires the first intersection callback. `aria-hidden` + `tabindex=-1`
         keep it out of the SR tree + tab order. `pointer-events: none` on
         the iframe forwards clicks to the wrapper, which translates them
         to parent-window scroll via the Alpine click handler. --}}
    @if ($modeValue === 'rendered')
        <div
            class="wk-reading-minimap__rendered"
            x-ref="renderedContainer"
            data-test="reading-minimap-rendered"
        ></div>
    @endif

    {{-- Stripe column — one stripe per matched item. In `rendered` mode the
         CSS rule `[data-mode="rendered"] .wk-reading-minimap__stripes`
         sets `display: none`, so this template is rendered but invisible.
         Kept in the DOM because the stripe state machine (active index,
         tooltip) still runs and may be re-enabled at runtime via a
         debug toggle from the host application. --}}
    <ol class="wk-reading-minimap__stripes relative h-full p-0 m-0 list-none" style="list-style: none; margin: 0; padding: 0;">
        <template x-for="(item, idx) in items" :key="idx">
            <li
                class="wk-reading-minimap__stripe absolute left-0 right-0 cursor-pointer"
                :class="idx === activeIndex ? 'wk-reading-minimap__stripe--active' : ''"
                {{-- `top` percentage = source position; `height` per item
                     respects --reading-minimap-stripe-height in line-mode
                     (default thin 2px) and `item.heightFraction * 100%` in
                     block-mode (per-item proportional to source item's
                     natural height, giving a skeleton-style content
                     texture rather than a sparse list of equal-height
                     lines). The controller populates item.heightFraction
                     when itemStyle === 'block'. --}}
                :style="`top: ${item.fraction * 100}%; height: ${itemStyle === 'block' && item.heightFraction ? (item.heightFraction * 100) + '%' : 'var(--reading-minimap-stripe-height)'}; margin-bottom: var(--reading-minimap-stripe-gap);`"
                @click="scrollToItem(item)"
                @mouseenter="showTooltip(item, $event)"
                @mouseleave="hideTooltip()"
            ></li>
        </template>
    </ol>

    {{-- Viewport overlay rectangle — translucent box showing the host's
         visible region. Drag-pan when draggable is true. Painted above
         the iframe (z-index: 1 in CSS) so it stays visible in rendered mode. --}}
    <div
        class="wk-reading-minimap__viewport absolute left-0 right-0 pointer-events-auto"
        :style="`top: ${viewportTop}px; height: ${viewportHeight}px;`"
        @if (filter_var($draggable, FILTER_VALIDATE_BOOL))
            @pointerdown="startDrag($event)"
            @pointermove="moveDrag($event)"
            @pointerup="endDrag($event)"
            @pointercancel="endDrag($event)"
            style="touch-action: none;"
        @endif
    ></div>

    {{-- Extension E2 — bookmark marker. Renders only when a saved bookmark
         exists; controller writes the `top` percentage inline. --}}
    @if (filter_var($showBookmarks, FILTER_VALIDATE_BOOL))
        <div
            class="wk-reading-minimap__bookmark-marker"
            x-show="bookmarkPct !== null"
            x-cloak
            :style="`top: ${bookmarkPct * 100}%;`"
            data-test="reading-minimap-bookmark-marker"
        ></div>
    @endif

    {{-- Extension E3 — heading anchors. Real <a> elements inside a sibling
         <nav> with an accessible name; survives the wrapper's aria-hidden. --}}
    @if (filter_var($headingAnchors, FILTER_VALIDATE_BOOL))
        <nav
            class="wk-reading-minimap__anchors"
            aria-label="{{ __('Page sections') }}"
            data-test="reading-minimap-anchors"
        >
            <template x-for="(anchor, idx) in headingAnchorsList" :key="anchor.id">
                <a
                    class="wk-reading-minimap__anchor"
                    :href="`#${anchor.id}`"
                    :style="`top: ${anchor.fraction * 100}%;`"
                    :data-collapsed="anchor.collapsed ? 'true' : 'false'"
                    x-text="anchor.label"
                    @click="scrollToAnchor(anchor, $event)"
                ></a>
            </template>
        </nav>
    @endif

    {{-- Tooltip — shows hovered item's label in stripe mode. Hidden on touch.
         `transform: translateY(-50%)` vertically centers the tooltip on the
         `top` coordinate (= cursor's Y inside the wrapper). Without it,
         `top: Npx` would place the tooltip's TOP EDGE at the cursor and
         the text would visually sit ~half-tooltip-height BELOW the cursor.
         Centering gives a "pointer-at-the-tooltip-arrow" feel even though
         there's no literal arrow. --}}
    <div
        class="wk-reading-minimap__tooltip absolute pointer-events-none px-2 py-1 text-xs rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-tooltip-bg)] text-[color:var(--color-wk-tooltip-text)] truncate max-w-[16rem]"
        :class="tooltipText ? '' : 'hidden'"
        :style="`top: ${tooltipTop}px; transform: translateY(-50%); ${side === 'left' ? 'left: 100%; margin-left: 0.5rem' : 'right: 100%; margin-right: 0.5rem'};`"
        x-text="tooltipText"
    ></div>

    {{-- Extension E1 — hover preview popover. Position-fixed; controller
         writes top/left + data-visible on pointermove. --}}
    @if (filter_var($hoverPreview, FILTER_VALIDATE_BOOL))
        <div
            class="wk-reading-minimap__preview"
            x-ref="hoverPreview"
            aria-hidden="true"
            data-test="reading-minimap-preview"
        ></div>
    @endif
</div>
