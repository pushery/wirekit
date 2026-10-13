{{-- optimistic-ui: n/a — client-only
     A reading layout. The controls it places keep their state in the browser: the current
     heading, the current section and the bookmark are not values a server owns, and the
     shell adds no action of its own. --}}
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
    // `boundary` — pass-through to the composed primitives (reading-progress,
    // reading-spine, reading-bookmark). Default `null` keeps the viewport-
    // pinned behavior. Set to `'container'` to scope every composed
    // primitive to the shell's nearest positioned ancestor — required when
    // embedding the entire reading surface inside a modal body, sidebar
    // pane, preview iframe, or Livewire panel.
    'boundary' => null,
    // `layout` — the spine's. `ticks` pins it to the page's edge; `rail` makes it an outline in
    // a column beside the slot, which the shell builds (see below).
    'layout' => 'ticks',
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reading-shell', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $previewMode = BooleanProp::from($previewMode, false);

    // Reading-shell — sugar wrapper composing the reading-* primitives in one
    // tag. The 80% case: pass `bookmarkKey` and let the shell render progress
    // + spine + bookmark with sensible defaults. Power-users compose the
    // primitives directly (see "Composition" in docs/components/reading.md)
    // when they need primitive-level customization beyond the density preset.
    //
    // No new state machine — this is purely Blade composition. Each child
    // component carries its own Alpine state independently. Opting out of,
    // say, the bookmark via :bookmark="false" drops the bookmark Blade tag
    // from the output, so no orphaned Alpine init is left to break silently.
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
    // configure the rendered primitives' behavior.
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

    // A rail stands in the page's flow, in a column of its own, where the default spine is pinned
    // to an edge and takes no room. So with `layout="rail"` the shell puts the slot and the rail
    // side by side from `md`, the rail in the 14.375rem column its documentation uses, and below
    // `md` the slot takes the whole width while the rail stays hidden. The rail takes no
    // `expand` and no `boundary`: it is always expanded, and it stays in its column. An unknown
    // layout raises in debug and reads as `ticks` in production.
    $layout = WireKit::validateProp('reading-shell', 'layout', (string) $layout, ['ticks', 'rail']);
    $renderRail = $renderSpine && $layout === 'rail';
@endphp

<div {{ $attributes->class([WireKit::resolveClasses('reading-shell', 'base', 'wk-reading-shell', $scope)]) }}>
    @if ($renderProgress)
        <x-wirekit::reading-progress
            :height="$densityDefaults['progressHeight']"
            :boundary="$boundary"
            :scope="$scope"
        />
    @endif

    @if ($renderSpine && ! $renderRail)
        <x-wirekit::reading-spine
            :expand="$densityDefaults['spineExpand']"
            :boundary="$boundary"
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
            :boundary="$boundary"
            :scope="$scope"
        />
    @endif

    @if ($renderMeta)
        <x-wirekit::reading-meta :scope="$scope" />
    @endif

    @if ($renderRail)
        <x-wirekit::grid cols="1" template="md:[minmax(0,1fr)_14.375rem]" gap="lg">
            {{-- `min-w-0`, so a wide code block in the article scrolls inside it rather than
                 widening the column and pushing the rail off the page. --}}
            <div class="min-w-0">
                {{ $slot }}
            </div>
            <x-wirekit::reading-spine layout="rail" :scope="$scope" />
        </x-wirekit::grid>
    @else
        {{ $slot }}
    @endif
</div>
