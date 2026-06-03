@props([
    'intent' => config('wirekit.components.badge.intent', 'neutral'),
    'size' => config('wirekit.components.badge.size', 'md'),
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
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Base classes: layout, typography, border, transitions
    // All values reference design tokens — no hardcoded colors or sizes
    $baseClasses = WireKit::resolveClasses('badge', 'base', implode(' ', [
        'inline-flex items-center gap-x-1',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'border-[length:var(--border-wk-width)]',
        'whitespace-nowrap',
    ]), $scope);

    // Intent classes: tinted backgrounds via color-mix for subtle look.
    // Border and text colors from same token family for cohesion.
    $intentClasses = match ($intent) {
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

    // Size classes: height, padding, font size, radius
    // Full rounded corners for pill-style badges
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-5 px-2',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-full)]',
        ]),
        'md' => implode(' ', [
            'h-6 px-2.5',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-full)]',
        ]),
        'lg' => implode(' ', [
            'h-7 px-3',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-full)]',
        ]),
        default => WireKit::validateProp('badge', 'size', $size, ['sm', 'md', 'lg']),
    };

    // Dot indicator color matches intent text color for cohesion
    $dotColorClass = match ($intent) {
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

    // Depth: a subtle inset currentColor ring (adapts per intent) + a faint
    // 1px drop shadow lift the badge off the surface — previously it read
    // flat. currentColor resolves to the intent's text/fg colour, so the
    // ring tints itself; the drop shadow uses the text token at 4%.
    $depthStyle = 'box-shadow: inset 0 0 0 1px color-mix(in srgb, currentColor 30%, transparent), 0 1px 1px color-mix(in srgb, var(--color-wk-text) 4%, transparent);';
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
        <span {{ $attributes->class([$baseClasses, $intentClasses, $sizeClasses]) }} style="{{ $depthStyle }}">
            @if($dot)
                <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full {{ $dotColorClass }}"></span>
            @endif
            @if($leadingIcon)
                <x-wirekit::icon :name="$leadingIcon" size="xs" aria-hidden="true" class="shrink-0" />
            @endif
            {{ $slot }}
        </span>
    </x-wirekit::tooltip>
@else
    <span {{ $attributes->class([$baseClasses, $intentClasses, $sizeClasses]) }} style="{{ $depthStyle }}">
        @if($dot)
            <span aria-hidden="true" class="inline-block h-1.5 w-1.5 rounded-full {{ $dotColorClass }}"></span>
        @endif
        @if($leadingIcon)
            <x-wirekit::icon :name="$leadingIcon" size="xs" aria-hidden="true" class="shrink-0" />
        @endif
        {{ $slot }}
    </span>
@endif
