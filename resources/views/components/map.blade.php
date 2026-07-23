@props([
    'center' => [0, 0],             // [lat, lng]
    'zoom' => config('wirekit.components.map.zoom', 2),
    'markers' => [],                // [{id,lat,lng,label,body?,intent?}]
    'provider' => config('wirekit.components.map.provider', 'maplibre'), // maplibre | leaflet (peer dependency)
    'styleUrl' => null,             // tile/style URL (provider-specific)
    'attribution' => null,          // tile attribution HTML, e.g. '© OpenStreetMap contributors' — shown by Leaflet's attribution control; required by some tile sources (OSM)
    'height' => '24rem',            // map canvas height (CSS length)
    'ariaLabel' => __('Map'),
    'listLabel' => __('Locations'),
    // Show the marker-list sidebar. Set list="false" for a map-only surface — the
    // list stays in the DOM as an sr-only accessible companion (a map is opaque to
    // assistive tech, so the locations must remain reachable), only the visual
    // sidebar is hidden and the canvas fills the width.
    'list' => true,
    // Selected-row highlight: 'ring' (inset frame — the default) or 'fill'
    // (tinted row background), colored via highlightColor (intent vocabulary).
    // Both variants are demonstrated side by side on the docs MapLibre page.
    'highlight' => config('wirekit.components.map.highlight', 'ring'),
    'highlightColor' => config('wirekit.components.map.highlight-color', 'accent'),
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $list = BooleanProp::from($list, true);

    $provider = WireKit::validateProp('map', 'provider', $provider, ['maplibre', 'leaflet']);
    $highlight = WireKit::validateProp('map', 'highlight', $highlight, ['ring', 'fill']);
    $highlightColor = WireKit::validateProp('map', 'highlightColor', $highlightColor, ['accent', 'success', 'warning', 'danger', 'neutral']);
    $id = $attributes->get('id', 'map-'.Str::random(6));
    // Map-only mode: hide the visual sidebar but keep the list in the DOM (sr-only)
    // so assistive tech can still reach the locations.
    $showList = filter_var($list, FILTER_VALIDATE_BOOLEAN);

    $centerArr = array_values((array) $center);
    $markersArr = $markers instanceof \Illuminate\Support\Collection ? $markers->values()->all() : array_values((array) $markers);

    // Marker dot intents (PHP literals → Tailwind-compiled + drift-traced). The
    // list pairs the dot color with the text label, so color is never the sole
    // signal (a11y).
    $dotClasses = [
        'accent' => 'bg-[var(--color-wk-accent)]',
        'success' => 'bg-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)]',
        // info has no base --color-wk-info token (only --color-wk-info-text), so the
        // dot aliases to accent — matching the map pin's _intentColor info→accent.
        'info' => 'bg-[var(--color-wk-accent)]',
        'neutral' => 'bg-[var(--color-wk-text-muted)]',
    ];

    // Selected-row highlight classes (PHP literals → Tailwind-compiled + drift-
    // traced). 'ring' = the inset frame; 'fill' = a tinted tile background (14%
    // mix, the marker-band strength). The color is choosable so the highlight
    // doesn't have to be the theme accent (near-black on the stock theme).
    $highlightClasses = [
        'ring' => [
            'accent' => 'bg-[var(--color-wk-bg-subtle)] ring-[length:var(--ring-wk-width)] ring-inset ring-[var(--color-wk-accent)]',
            'success' => 'bg-[var(--color-wk-bg-subtle)] ring-[length:var(--ring-wk-width)] ring-inset ring-[var(--color-wk-success)]',
            'warning' => 'bg-[var(--color-wk-bg-subtle)] ring-[length:var(--ring-wk-width)] ring-inset ring-[var(--color-wk-warning)]',
            'danger' => 'bg-[var(--color-wk-bg-subtle)] ring-[length:var(--ring-wk-width)] ring-inset ring-[var(--color-wk-danger)]',
            'neutral' => 'bg-[var(--color-wk-bg-subtle)] ring-[length:var(--ring-wk-width)] ring-inset ring-[var(--color-wk-border-hover)]',
        ],
        'fill' => [
            'accent' => 'bg-[color-mix(in_oklch,var(--color-wk-accent)_14%,transparent)]',
            'success' => 'bg-[color-mix(in_oklch,var(--color-wk-success)_14%,transparent)]',
            'warning' => 'bg-[color-mix(in_oklch,var(--color-wk-warning)_14%,transparent)]',
            'danger' => 'bg-[color-mix(in_oklch,var(--color-wk-danger)_14%,transparent)]',
            'neutral' => 'bg-[var(--color-wk-bg-muted)]',
        ],
    ];
    $selectedClasses = $highlightClasses[$highlight][$highlightColor];

    $base = WireKit::resolveClasses('map', 'base', 'w-full font-[family-name:var(--font-wk-sans)]', $scope);
@endphp

<div
    {{ $attributes->except(['id', 'class']) }}
    id="{{ $id }}"
    x-data="wirekitMap({ center: @js($centerArr), zoom: {{ (int) $zoom }}, markers: @js($markersArr), provider: '{{ $provider }}'@if($styleUrl), styleUrl: '{{ $styleUrl }}'@endif @if($attribution), attribution: {{ \Illuminate\Support\Js::from($attribution) }}@endif })"
    role="group"
    aria-label="{{ $ariaLabel }}"
    {{-- NO flex gap between canvas and list: the list's own divider border (a top
         border when stacked, a left border when side by side) IS the separator. A
         flex gap here adds a dead strip of page background between the map and the
         list on BOTH engines (a visible gap appears) — the two panes must sit
         flush inside the rounded, clipped card, split only by that border. (Plain
         prose, no bare utility tokens: Tailwind scans Blade comments too, so a
         literal class name here would emit a dead selector and trip the drift audit.) --}}
    {{ $attributes->only('class')->class([$base.' flex flex-col sm:flex-row border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] overflow-hidden']) }}
>
    {{-- Map canvas — the library draws here. Falls back to a placeholder + the
         list when no peer library is loaded (progressive enhancement). --}}
    <div class="relative flex-1 bg-[var(--color-wk-bg-muted)]" style="min-height: {{ $height }};">
        {{-- The mount carries h-full w-full IN ADDITION to absolute inset-0, and
             that is load-bearing: MapLibre adds its `maplibregl-map` class to this
             element at construction, and maplibre-gl.css declares
             `.maplibregl-map { position: relative }` — same single-class
             specificity as Tailwind's `.absolute`, so whichever stylesheet loads
             LAST wins. When maplibre-gl.css wins, the mount loses absolute
             positioning, its only children are out-of-flow, and it collapses to
             height 0 — MapLibre then falls back to a 300px canvas (clientHeight
             || 300) and the GL canvas paints blank at the wrong size, with
             resize() re-reading 0 forever. h-full/w-full (100% of the sized
             wrapper above) resolve correctly under EITHER computed position, so
             the mount is immune to that cascade collision (verified live: mount
             0px → 384px, canvas buffer 300px → 384px). --}}
        {{-- role="application" is load-bearing beyond a11y: docs.wirekit.app's
             preview link-block (a capture-phase click handler that preventDefaults
             demo-navigation anchors) excludes anchors inside [role="application"],
             so Leaflet's <a href="#"> zoom/attribution controls keep working inside
             a preview. Keep this role on the mount — dropping it silently kills
             Leaflet map controls on the docs site (asserted in MapRenderTest). --}}
        {{-- min-height floor (matches the wrapper above) is load-bearing on its
             own. When maplibre-gl.css's `.maplibregl-map { position: relative }`
             wins the cascade, `absolute inset-0` goes inert and the mount falls
             back to `h-full` (height: 100%). A percentage height does NOT resolve
             against a parent's `min-height`, and in a flex-COLUMN (the mobile
             stack — the outer wrapper is `flex flex-col sm:flex-row`) the parent
             has no definite height to resolve against, so `h-full` collapses to 0
             and MapLibre uses its `clientHeight || 300` fallback — painting a
             blank, under-sized 300px canvas. The flex-ROW desktop layout hides
             this because `align-items: stretch` gives the column a definite
             height, so `h-full` resolves there. The explicit min-height keeps
             clientHeight ≥ the wrapper height under EITHER computed position AND
             EITHER flex direction, so MapLibre never hits the 300px fallback. --}}
        <div x-ref="canvas" class="absolute inset-0 h-full w-full" style="min-height: {{ $height }};" role="application" aria-label="{{ $ariaLabel }} (interactive)" tabindex="0"></div>
        <div x-show="!available" x-cloak class="absolute inset-0 flex flex-col items-center justify-center gap-1 p-[var(--padding-wk-x-md)] text-center pointer-events-none">
            <svg aria-hidden="true" class="h-8 w-8 text-[color:var(--color-wk-text-subtle)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 20l-5.5 2.5V6L9 3.5m0 16.5l6 2.5m-6-2.5V3.5m6 19l5.5-2.5V2.5L15 5m0 17.5V5m0 0L9 3.5"/><circle cx="12" cy="9" r="2.5"/></svg>
            <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">Interactive map needs a map library — the locations are listed alongside.</p>
        </div>
    </div>

    {{-- Marker list — the accessible companion (the screen-reader path). Always
         present; selecting an item pans the map (when available) and emits
         marker-click. --}}
    <div role="region" aria-label="{{ $listLabel }}" @if($showList)tabindex="0"@endif class="{{ $showList ? 'w-full sm:w-[16rem] sm:shrink-0 max-h-[24rem] overflow-y-auto wk-scrollbar border-t-[length:var(--border-wk-width)] sm:border-t-0 sm:border-l-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]' : 'sr-only' }}">
        <p class="sticky top-0 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] bg-[var(--color-wk-bg-elevated)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)]">
            {{ $listLabel }} (<span x-text="markerCount"></span>)
        </p>
        <ul class="list-none divide-y divide-[var(--color-wk-border)]" style="list-style: none; margin: 0; padding: 0;">
            <template x-for="m in markers" :key="m.id">
                <li>
                    <button
                        type="button"
                        @click="selectMarker(m.id)"
                        :aria-label="m.label + (m.body ? ', ' + m.body : '') + ', latitude ' + m.lat + ', longitude ' + m.lng"
                        :aria-current="selectedId === m.id ? 'true' : null"
                        :class="selectedId === m.id ? @js($selectedClasses) : ''"
                        class="w-full flex items-start gap-[var(--space-wk-sm)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-left hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset cursor-pointer transition-colors"
                    >
                        <span class="mt-1 shrink-0 h-2.5 w-2.5 rounded-full" :class="@js($dotClasses)[m.intent || 'accent']"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] truncate" x-text="m.label"></span>
                            <span x-show="m.body" x-cloak class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)] truncate" x-text="m.body"></span>
                            <span class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)] tabular-nums" x-text="Number(m.lat).toFixed(4) + ', ' + Number(m.lng).toFixed(4)"></span>
                        </span>
                    </button>
                </li>
            </template>
            <li x-show="markerCount === 0" x-cloak class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">No locations</li>
        </ul>
    </div>
</div>
