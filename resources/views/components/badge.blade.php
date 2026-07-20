@props([
    'intent' => config('wirekit.components.badge.intent', 'neutral'),
    'size' => config('wirekit.components.badge.size', 'md'),
    // Surface treatment within the chosen intent:
    //   'soft'    — tinted background + intent text (default; back-compat — the
    //               lifted chip with the themeable inset ring depth).
    //   'solid'   — filled intent background + on-color foreground text.
    //   'outline' — transparent background + intent ring + intent text.
    'surface' => config('wirekit.components.badge.surface', 'soft'),
    'dot' => false,
    // Discoverable tooltip — surfaces the status explanation through
    // WireKit's own tooltip component (hover / focus / touch / keyboard,
    // aria-describedby) instead of the browser's native title attribute.
    // The text is the badge label; the tooltip is the supplementary
    // explanation (e.g. "Build failed" on a "Failed" badge).
    'tooltip' => null,
    // Optional leading status glyph (icon alias, e.g. 'check' / 'clock' /
    // 'x-circle' / 'shield-check'). Rendered decoratively before the label —
    // the text is the accessible name, so the icon is aria-hidden.
    'leadingIcon' => null,
    // Optional trailing glyph — mirror of leadingIcon, rendered after the label
    // (e.g. an external-link arrow on a link badge). Also decorative.
    'trailingIcon' => null,
    // When true, renders a trailing close button that removes the badge (Alpine
    // x-show) and dispatches a `wirekit:badge-dismissed` event. Keyboard- and
    // screen-reader-operable; label set via dismissLabel. A dismissible badge
    // also emits data-replayable="true" so the docs preview frame offers a
    // "↻ Replay" reset (inert in a developer app — no replay-button is injected
    // there).
    'dismissible' => false,
    'dismissLabel' => 'Remove',
    // Multi-line mode. A badge is a fixed-height single-line pill by default. Set
    // `wrap` for a long label that must break across lines: the fixed height is
    // swapped for `min-h-*` + vertical padding so the pill GROWS with the text
    // instead of the second line spilling below the surface (WIRE-175).
    'wrap' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('badge', $attributes->getAttributes());

    // Accept the British spelling as a runtime alias (house enum-alias contract:
    // any component exposing `outline` must also accept `outlined`).
    $surfaceAliases = ['outlined' => 'outline'];
    $surface = $surfaceAliases[$surface] ?? $surface;

    // Validate surface up front so an unknown value fails loudly (not silently
    // falling through to soft).
    $surface = in_array($surface, ['soft', 'solid', 'outline'], true)
        ? $surface
        : WireKit::validateProp('badge', 'surface', $surface, ['soft', 'solid', 'outline']);

    // Base classes: layout, typography, transitions. Border WIDTH is applied
    // per-surface below: 'soft' uses the themeable --border-wk-badge-width (so a
    // theme like Aurora can flatten the chip to width 0); 'outline' forces the
    // global --border-wk-width so the outline stays visible even under a theme
    // that flattens soft badges; 'solid' uses the badge-width token (the fill
    // carries the shape, so a flattened border is fine).
    // Normalize `wrap` up front — a stringly `wrap="false"` would otherwise be
    // truthy (Blade passes it as the string "false"). See the stringly-false trap.
    $wrap = filter_var($wrap, FILTER_VALIDATE_BOOLEAN);

    $baseClasses = WireKit::resolveClasses('badge', 'base', implode(' ', [
        'inline-flex items-center gap-x-1',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        // Single-line by default; wrap mode lets long labels break across lines.
        $wrap ? 'whitespace-normal' : 'whitespace-nowrap',
    ]), $scope);

    $borderWidthClass = $surface === 'outline'
        ? 'border-[length:var(--border-wk-width)]'
        : 'border-[length:var(--border-wk-badge-width,var(--border-wk-width))]';

    // SOFT (default): tinted backgrounds via color-mix for a subtle look.
    // Border and text colors from same token family for cohesion.
    $softClasses = match ($intent) {
        'primary' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-accent)_12%,var(--color-wk-bg))]',
            'text-[color:var(--color-wk-accent-content)]',
            'border-[color-mix(in_srgb,var(--color-wk-accent)_25%,transparent)]',
        ]),
        // 'accent' is the high-contrast filled variant — the developer asks
        // for the badge to stand out against surrounding chrome (CTA hero,
        // pricing-card "Most popular" pill, marketing "New" eyebrow). Uses
        // the canonical accent fill + accent-fg foreground for maximum
        // contrast in both light and dark themes.
        'accent' => implode(' ', [
            'bg-[var(--color-wk-accent)]',
            'text-[color:var(--color-wk-accent-fg)]',
            'border-[var(--color-wk-accent)]',
        ]),
        'success' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-success)_12%,var(--color-wk-bg))]',
            // -text variant calibrated for ≥4.5:1 on the soft-tone bg
            'text-[color:var(--color-wk-success-text)]',
            'border-[color-mix(in_srgb,var(--color-wk-success)_25%,transparent)]',
        ]),
        'warning' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-warning)_12%,var(--color-wk-bg))]',
            // -text variant calibrated for ≥4.5:1 on the soft-tone bg
            'text-[color:var(--color-wk-warning-text)]',
            'border-[color-mix(in_srgb,var(--color-wk-warning)_25%,transparent)]',
        ]),
        'danger' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-danger)_12%,var(--color-wk-bg))]',
            'text-[color:var(--color-wk-danger-text)]',
            'border-[color-mix(in_srgb,var(--color-wk-danger)_25%,transparent)]',
        ]),
        'info' => implode(' ', [
            'bg-[color-mix(in_srgb,var(--color-wk-accent)_8%,var(--color-wk-bg))]',
            'text-[color:var(--color-wk-accent-content)]',
            'border-[color-mix(in_srgb,var(--color-wk-accent)_20%,transparent)]',
        ]),
        'neutral' => implode(' ', [
            'bg-[var(--color-wk-bg-muted)]',
            'text-[color:var(--color-wk-text)]',
            'border-[var(--color-wk-border-subtle)]',
        ]),
        default => WireKit::validateProp('badge', 'intent', $intent, ['primary', 'accent', 'success', 'warning', 'danger', 'info', 'neutral']),
    };

    // SOLID: filled intent background + on-color foreground. `info` has no own
    // base color (it is a soft accent), so it borrows the accent fill; `neutral`
    // becomes a dark filled chip (text-bg pair). Literal class strings (not
    // interpolated) so the Tailwind text scanner / drift audit see them.
    $solidClasses = match ($intent) {
        'primary', 'accent', 'info' => 'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] border-[var(--color-wk-accent)]',
        'success' => 'bg-[var(--color-wk-success)] text-[color:var(--color-wk-success-fg)] border-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)] text-[color:var(--color-wk-warning-fg)] border-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)] text-[color:var(--color-wk-danger-fg)] border-[var(--color-wk-danger)]',
        'neutral' => 'bg-[var(--color-wk-text)] text-[color:var(--color-wk-bg)] border-[var(--color-wk-text)]',
        default => '',
    };

    // OUTLINE: transparent background + intent ring + intent text. Border color
    // is the intent base; text uses the soft-tone text token (AA on the page bg).
    $outlineClasses = match ($intent) {
        'primary', 'info' => 'bg-transparent text-[color:var(--color-wk-accent-content)] border-[color:var(--color-wk-accent)]',
        'accent' => 'bg-transparent text-[color:var(--color-wk-accent-content)] border-[color:var(--color-wk-accent)]',
        'success' => 'bg-transparent text-[color:var(--color-wk-success-text)] border-[color:var(--color-wk-success)]',
        'warning' => 'bg-transparent text-[color:var(--color-wk-warning-text)] border-[color:var(--color-wk-warning)]',
        'danger' => 'bg-transparent text-[color:var(--color-wk-danger-text)] border-[color:var(--color-wk-danger)]',
        'neutral' => 'bg-transparent text-[color:var(--color-wk-text)] border-[color:var(--color-wk-border)]',
        default => '',
    };

    // Pick the surface treatment. `soft` is the default + back-compat path.
    $intentClasses = match ($surface) {
        'solid' => $solidClasses,
        'outline' => $outlineClasses,
        default => $softClasses,
    };

    // Size classes: height, padding, font size, radius.
    // Single-line badges are full-radius pills. In wrap mode the fixed height is swapped
    // for min-height + a little more vertical padding so the pill grows with the wrapped
    // text (WIRE-175); the radius also drops to the medium token, because a full radius —
    // half the single-line height — reads as an oversized stadium once the badge is two+
    // lines tall. The reduced radius makes a multi-line badge a tidy rounded rectangle.
    $pillRadius = $wrap ? 'rounded-[var(--radius-wk-md)]' : 'rounded-[var(--radius-wk-full)]';
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            $wrap ? 'min-h-5 py-1 px-2' : 'h-5 px-2',
            'text-[length:var(--text-wk-sm)]',
            $pillRadius,
        ]),
        'md' => implode(' ', [
            $wrap ? 'min-h-6 py-1 px-2.5' : 'h-6 px-2.5',
            'text-[length:var(--text-wk-sm)]',
            $pillRadius,
        ]),
        'lg' => implode(' ', [
            $wrap ? 'min-h-7 py-1.5 px-3' : 'h-7 px-3',
            'text-[length:var(--text-wk-md)]',
            $pillRadius,
        ]),
        default => WireKit::validateProp('badge', 'size', $size, ['sm', 'md', 'lg']),
    };

    // Dot indicator color matches intent text color for cohesion. On a SOLID
    // fill the dot reads on a colored field, so it switches to the on-fill
    // foreground (matches how the always-filled 'accent' intent already works).
    $dotColorClass = $surface === 'solid'
        ? match ($intent) {
            'success' => 'bg-[var(--color-wk-success-fg)]',
            'warning' => 'bg-[var(--color-wk-warning-fg)]',
            'danger' => 'bg-[var(--color-wk-danger-fg)]',
            'neutral' => 'bg-[var(--color-wk-bg)]',
            default => 'bg-[var(--color-wk-accent-fg)]',
        }
        : match ($intent) {
            // For 'accent' (the filled-background variant), the dot reads on a
            // colored field — use accent-fg so it contrasts with the bg.
            'accent' => 'bg-[var(--color-wk-accent-fg)]',
            'primary', 'info' => 'bg-[var(--color-wk-accent)]',
            'success' => 'bg-[var(--color-wk-success)]',
            'warning' => 'bg-[var(--color-wk-warning)]',
            'danger' => 'bg-[var(--color-wk-danger)]',
            'neutral' => 'bg-[var(--color-wk-text-muted)]',
            default => 'bg-[var(--color-wk-text-muted)]',
        };

    // Depth (SOFT surface only): a subtle inset currentColor ring (adapts per
    // intent) + a faint 1px drop shadow lift the chip off the surface. Driven by
    // the --shadow-wk-badge token so a theme can flatten it (set it to `none`).
    // SOLID carries its own fill and OUTLINE its own ring, so neither needs the
    // soft-chip depth — applying it would double the edge.
    $depthStyle = $surface === 'soft'
        ? 'box-shadow: var(--shadow-wk-badge, inset 0 0 0 1px color-mix(in srgb, currentColor 30%, transparent), 0 1px 1px color-mix(in srgb, var(--color-wk-text) 4%, transparent));'
        : '';

    // Trailing close button (dismissible). Inherits the badge text color via
    // currentColor; a focus ring + hover dim make it operable + discoverable.
    // A small leading margin separates it from the label.
    $dismissBtnClasses = implode(' ', [
        'shrink-0 ms-1 inline-flex items-center justify-center',
        'rounded-[var(--radius-wk-full)]',
        'opacity-70 hover:opacity-100',
        'transition-opacity duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
    ]);
@endphp

{{-- The badge body. When a tooltip is requested it is wrapped in the
     WireKit tooltip component so the explanation surfaces through WireKit's
     own accessible tooltip (hover / focus / touch / keyboard,
     aria-describedby) rather than the browser's native title attribute. The
     wrap is conditional so a badge WITHOUT a tooltip stays a bare span with
     zero Alpine overhead. The if/else duplicates the span deliberately —
     Blade pairs anonymous component tags at compile time, so the wrapper
     cannot be split across a runtime conditional. (Note: literal component
     tag syntax is kept out of this comment on purpose — Blade's component
     compiler scans comments too and an unbalanced tag here would break the
     pairing.) --}}
@if($tooltip)
    <x-wirekit::tooltip :text="$tooltip" :scope="$scope">
        <span
            @if($dismissible) x-data="{ shown: true }" x-show="shown" data-replayable="true" @endif
            {{ $attributes->class([$baseClasses, $borderWidthClass, $intentClasses, $sizeClasses]) }}
            style="{{ $depthStyle }}"
        >
            @if($dot)
                <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full {{ $dotColorClass }}"></span>
            @endif
            @if($leadingIcon)
                <x-wirekit::icon :name="$leadingIcon" size="xs" aria-hidden="true" class="shrink-0" />
            @endif
            {{ $slot }}
            @if($trailingIcon)
                <x-wirekit::icon :name="$trailingIcon" size="xs" aria-hidden="true" class="shrink-0" />
            @endif
            @if($dismissible)
                <button type="button" x-on:click="shown = false; $dispatch('wirekit:badge-dismissed')" aria-label="{{ $dismissLabel }}" class="{{ $dismissBtnClasses }}">
                    <x-wirekit::icon name="x-mark" size="xs" aria-hidden="true" class="shrink-0" />
                </button>
            @endif
        </span>
    </x-wirekit::tooltip>
@else
    <span
        @if($dismissible) x-data="{ shown: true }" x-show="shown" data-replayable="true" @endif
        {{ $attributes->class([$baseClasses, $borderWidthClass, $intentClasses, $sizeClasses]) }}
        style="{{ $depthStyle }}"
    >
        @if($dot)
            <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full {{ $dotColorClass }}"></span>
        @endif
        @if($leadingIcon)
            <x-wirekit::icon :name="$leadingIcon" size="xs" aria-hidden="true" class="shrink-0" />
        @endif
        {{ $slot }}
        @if($trailingIcon)
            <x-wirekit::icon :name="$trailingIcon" size="xs" aria-hidden="true" class="shrink-0" />
        @endif
        @if($dismissible)
            <button type="button" x-on:click="shown = false; $dispatch('wirekit:badge-dismissed')" aria-label="{{ $dismissLabel }}" class="{{ $dismissBtnClasses }}">
                <x-wirekit::icon name="x-mark" size="xs" aria-hidden="true" class="shrink-0" />
            </button>
        @endif
    </span>
@endif
