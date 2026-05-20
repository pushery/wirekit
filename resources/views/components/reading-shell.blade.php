@props([
    'bookmarkKey' => null,
    // Toggles default to null so the density preset's per-primitive default
    // wins. An explicit bool (`:spine="false"`) overrides the density's
    // baseline. Net effect for the developer: `density="minimal"` actually
    // hides things; passing the toggle explicitly always wins.
    'progress' => null,
    'spine' => null,
    'minimap' => null,
    'toc' => null,
    'bookmark' => null,
    'meta' => null,
    'density' => 'comfortable',
    'previewMode' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Reading-shell — sugar wrapper composing the reading-* primitives in one
    // tag. The 80% case: pass `bookmarkKey` and let the shell render progress
    // + spine + bookmark with sensible defaults. Power-users compose the
    // primitives directly (see "Composition" in docs/components/reading.md)
    // when they need primitive-level customization beyond the density preset.
    //
    // No new state machine — this is purely Blade composition. Each child
    // component carries its own Alpine state independently. Footgun guard:
    // tests assert that opting out of, say, the bookmark via :bookmark="false"
    // also drops the bookmark Blade tag from the output (no orphaned Alpine
    // init that would silently break).
    //
    // Density preset (comfortable | compact | minimal) is the starting-point
    // shape. Per-primitive toggles WIN over density — passing
    // `density="minimal" :spine="true"` shows the spine even though the
    // density-derived default would hide it.

    // Validate the density preset.
    $densityValue = match ($density) {
        'comfortable', 'compact', 'minimal' => $density,
        default => WireKit::validateProp(
            'reading-shell',
            'density',
            $density,
            ['comfortable', 'compact', 'minimal']
        ),
    };

    // Density preset → per-primitive defaults. Each preset's `defaults` tuple
    // controls which primitives render by DEFAULT — explicit toggles
    // override (handled below). The `progressHeight` + `spineExpand` keys
    // configure the rendered primitives' behaviour.
    // toc defaults to false in every density — reading-toc is a marketing-
    // landing affordance, not a default for blog posts. Marketing pages
    // explicitly opt in via :toc="true" :spine="false".
    $densityDefaults = match ($densityValue) {
        'compact' => [
            'progress' => true, 'spine' => true,  'minimap' => false, 'toc' => false, 'meta' => false,
            'progressHeight' => 'sm', 'spineExpand' => 'always-md',
        ],
        'minimal' => [
            'progress' => true, 'spine' => false, 'minimap' => false, 'toc' => false, 'meta' => false,
            'progressHeight' => 'sm', 'spineExpand' => 'hover',
        ],
        default => [ // comfortable
            'progress' => true, 'spine' => true,  'minimap' => false, 'toc' => false, 'meta' => false,
            'progressHeight' => 'md', 'spineExpand' => 'hover',
        ],
    };

    // Toggle resolution: explicit bool prop wins over density default.
    // null = "no explicit value passed", use density baseline.
    $resolveToggle = static fn ($explicit, bool $densityDefault): bool => $explicit === null
        ? $densityDefault
        : filter_var($explicit, FILTER_VALIDATE_BOOL);

    $renderProgress = $resolveToggle($progress, $densityDefaults['progress']);
    $renderSpine = $resolveToggle($spine, $densityDefaults['spine']);
    $renderMinimap = $resolveToggle($minimap, $densityDefaults['minimap']);
    $renderToc = $resolveToggle($toc, $densityDefaults['toc']);
    $renderMeta = $resolveToggle($meta, $densityDefaults['meta']);
    // Bookmark default is "true if a key is set" — independent of density.
    $bookmarkExplicit = $bookmark === null ? true : filter_var($bookmark, FILTER_VALIDATE_BOOL);
    $renderBookmark = $bookmarkExplicit && $bookmarkKey !== null;
@endphp

<div {{ $attributes->class(['wk-reading-shell']) }}>
    @if ($renderProgress)
        <x-wirekit::reading-progress
            :height="$densityDefaults['progressHeight']"
            :scope="$scope"
        />
    @endif

    @if ($renderSpine)
        <x-wirekit::reading-spine
            :expand="$densityDefaults['spineExpand']"
            :scope="$scope"
        />
    @endif

    @if ($renderMinimap)
        <x-wirekit::reading-minimap :scope="$scope" />
    @endif

    @if ($renderToc)
        <x-wirekit::reading-toc :scope="$scope" />
    @endif

    @if ($renderBookmark)
        <x-wirekit::reading-bookmark
            :key="$bookmarkKey"
            :previewMode="$previewMode"
            :scope="$scope"
        />
    @endif

    @if ($renderMeta)
        <x-wirekit::reading-meta :scope="$scope" />
    @endif

    {{ $slot }}
</div>
