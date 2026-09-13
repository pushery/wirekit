{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'icon' => null,
    'title' => null,
    // The canonical color axis, the one button, badge, progress, alert and callout all
    // spell: primary | neutral | info | success | warning | danger. `tone` below is its
    // back-compat alias and keeps working unchanged.
    'intent' => null,
    'tone' => null,
    'size' => null,
    // Optional reveal animation when the feature card scrolls into view.
    // Null = no animation (default, v1.5.0-identical).
    'animateIn' => null,
    // Heading level for the title (1-6). Defaults to 3 — a card in a grid of features — so nothing
    // moves for a caller who never sets it. Set it to match the surrounding document
    // outline: a component cannot know where in the page it sits, and a guessed level
    // is worse than a chosen one. A skipped level is what `heading-order` reports, and
    // that rule lives in axe's best-practice tag rather than the WCAG tags most suites
    // run — so it is invisible to an axe sweep and visible in Lighthouse.
    'level' => 3,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('feature', $attributes->getAttributes());

    $animateAttr = WireKit::resolveAnimateIn($animateIn, 'feature');

    // Feature — individual feature card for feature-grid.
    $classes = WireKit::resolveClasses('feature', 'base', implode(' ', [
        'flex flex-col',
        'gap-[var(--gap-wk-sm)]',
    ]), $scope);

    // Resolved defaults: prop > config('wirekit.components.feature.{key}') > component default.
    // `intent` first: it is the name the kit's vocabulary is moving to, and a call site that
    // sets both is half migrated. Preferring the older name there would make that call site
    // look finished to whoever is doing the migration.
    $axis = $intent !== null ? 'intent' : 'tone';
    $tone = $intent ?? $tone ?? config('wirekit.components.feature.tone', 'accent');
    $size ??= config('wirekit.components.feature.size', 'md');

    // Tone map. accent uses the auto-switching --color-wk-accent-fg foreground (replaces the
    // hardcoded text-white from v1.2.x — same contrast bug class fixed in CTA + Hero).
    // soft / success / warning / danger use color-mix(in_srgb, …) tinted backgrounds, the
    // same pattern already proven in alert/badge/callout/message/reaction/toast-region.
    $toneMap = [
        'accent' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]',
        'neutral' => 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]',
        'soft' => 'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-accent-text)]',
        'success' => 'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-success-text)]',
        'warning' => 'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-warning-text)]',
        'danger' => 'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg))] text-[color:var(--color-wk-danger-text)]',
    ];

    // the canonical intent vocabulary
    // (button / badge / progress) uses `primary` and `info` — feature
    // didn't accept either, so `<x-wirekit::feature tone="primary">`
    // crashed even though the rest of the kit accepts that spelling.
    // We alias `primary → accent` (the same color role) and `info →
    // soft` (the same tinted-accent treatment) so the canonical
    // vocabulary works on feature too. See the prop-naming-conventions
    // docs page for the full vocabulary contract.
    $toneAliases = [
        'primary' => 'accent',
        'info'    => 'soft',
    ];
    $tone = $toneAliases[$tone] ?? $tone;

    $validTone = isset($toneMap[$tone])
        ? $tone
        // Named after the prop the CALL SITE used. A message about `tone` sends a developer
        // who wrote `intent` looking for a prop they did not set.
        : WireKit::validateProp('feature', $axis, $tone, array_keys($toneMap));
    $toneClasses = $toneMap[$validTone];

    // Size map → [chipDimensionClasses, iconSizeProp].
    // The icon size prop replaces the icon's default h-5 w-5 cleanly.
    // xl is for hero-row features (64×64 chip, 32×32 icon); sm covers dense
    // feature lists; md is the historical default.
    $sizeMap = [
        'sm' => ['w-8 h-8 rounded-[var(--radius-wk-sm)]', 'sm'],
        'md' => ['w-10 h-10 rounded-[var(--radius-wk-md)]', 'md'],
        'lg' => ['w-12 h-12 rounded-[var(--radius-wk-lg)]', 'lg'],
        'xl' => ['w-16 h-16 rounded-[var(--radius-wk-xl)]', 'xl'],
    ];
    $validSize = isset($sizeMap[$size])
        ? $size
        : WireKit::validateProp('feature', 'size', $size, array_keys($sizeMap));
    [$chipSizeClasses, $iconSizeProp] = $sizeMap[$validSize];

    $hasIconSlot = isset($iconSlot) && $iconSlot->isNotEmpty();

    // Heading level (1-6). An invalid value signals in debug (validateProp throws with a
    // did-you-mean) and falls back to the default in production — never to h1, which is
    // what validateProp's own first-allowed fallback would produce and which would break
    // the outline worse than the default does.
    $levelValue = in_array((int) $level, [1, 2, 3, 4, 5, 6], true) ? (int) $level : 3;
    if ($levelValue !== (int) $level) {
        WireKit::validateProp('feature', 'level', (string) $level, ['1', '2', '3', '4', '5', '6']);
    }
@endphp

<div {{ $attributes->class([$classes]) }} @if($animateAttr) {!! $animateAttr !!} data-replayable="true" @endif>
    @if($hasIconSlot)
        {{-- Escape hatch: caller supplied their own marker (custom SVG, illustration, badge, …). --}}
        {{ $iconSlot }}
    @elseif($icon)
        <div class="flex items-center justify-center {{ $chipSizeClasses }} {{ $toneClasses }} mb-[var(--space-wk-xs,0.25rem)]">
            <x-wirekit::icon :name="$icon" :size="$iconSizeProp" />
        </div>
    @endif

    @if($title)
        <h{{ $levelValue }} class="text-[length:var(--text-wk-lg)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            {{ $title }}
        </h{{ $levelValue }}>
    @endif

    @if($slot->isNotEmpty())
        <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] leading-relaxed">
            {{ $slot }}
        </p>
    @endif
</div>
