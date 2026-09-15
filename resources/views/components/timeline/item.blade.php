{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
{{-- WHY `datetime` IS A SEPARATE PROP, AND WHY THE ELEMENT CHANGES WITHOUT IT.

     HTML is explicit about this: a `<time>` without a `datetime` attribute must have text
     content that is itself a valid datetime string. Every call site in this repository passes
     a human phrase — "2 hours ago", "Just now", "10 min ago" — so the element was invalid in
     every single usage, and an invalid `<time>` conveys nothing to anything reading the page.

     The three sibling components that emit `<time>` all derive the attribute from a Carbon
     instance (`countdown`, `message`, `date-separator`). This one cannot: its prop is display
     copy, and a phrase like "Just now" has no machine value to derive.

     So the attribute arrives on its own, and the element follows it. With a machine-readable
     value the item renders `<time datetime="…">` like its siblings; without one it renders a
     `<span>`, which is what the phrase actually is. Measured before changing it: no stylesheet
     in this package targets the element, and no call site passes anything parseable — so the
     `<span>` branch is what every existing caller gets, rendering identically to what an
     invalid `<time>` already rendered. --}}
@props([
    'time' => null,
    'datetime' => null,
    'icon' => null,
    'variant' => 'default', // back-compat alias of `intent`
    'intent' => null,       // canonical color axis: default | success | warning | danger. null → falls back to `variant`
    'scope' => null,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('timeline.item', $attributes->getAttributes());

    use Pushery\WireKit\WireKit;

    // Each timeline item: vertical connector line + dot/icon + content area.
    // The connector line is drawn via a pseudo-element on the dot container.
    $classes = WireKit::resolveClasses('timeline.item', 'base', implode(' ', [
        'relative',
        'flex',
        'gap-[var(--padding-wk-x-md)]',
    ]), $scope);

    // `intent` is the canonical name for this axis; `variant` is the
    // back-compat alias. When both are given the canonical one decides.
    $effectiveIntent = $intent ?? $variant;

    // Dot color per intent — matches component color tokens
    $dotColor = match ($effectiveIntent) {
        'success' => 'var(--color-wk-success)',
        'warning' => 'var(--color-wk-warning)',
        'danger' => 'var(--color-wk-danger)',
        default => 'var(--color-wk-accent)',
    };

    // The spoken outcome, for every intent that HAS one.
    //
    // The dot is the only thing that changes between a deployment that succeeded
    // and a pipeline that failed, and the dot is `aria-hidden` — so without this
    // the difference reaches neither a screen reader nor a reader who cannot
    // separate the red from the green. WCAG 1.4.1 does not permit color to be
    // the sole carrier, and this component's own documentation recommends the
    // intent for exactly that job.
    //
    // Same wording as alert's variant label, deliberately: an item whose outcome
    // is a failure should be announced with the word a developer already read on
    // the alert that reported it.
    //
    // `default` yields null and emits nothing. A plain entry signals no outcome,
    // and announcing one on every row of a long log is the noise that teaches a
    // reader to skip past the row that matters.
    $intentLabel = match ($effectiveIntent) {
        'success' => __('wirekit::Success'),
        'warning' => __('wirekit::Warning'),
        'danger' => __('wirekit::Error'),
        default => null,
    };
@endphp

<li data-wk-prose-skip {{ $attributes->class([$classes]) }}>
    {{-- Vertical connector line + dot indicator.
         Uses inline styles for flex layout to ensure reliable rendering. --}}
    <div style="position: relative; display: flex; flex-direction: column; align-items: center;">
        {{-- Dot or icon indicator --}}
        <div
            style="z-index: 10; display: flex; height: var(--size-wk-xs, 1.5rem); width: var(--size-wk-xs, 1.5rem); flex-shrink: 0; align-items: center; justify-content: center; border-radius: 9999px; background: {{ $dotColor }};"
            aria-hidden="true"
        >
            @if($icon)
                <x-wirekit::icon :name="$icon" size="xs" class="text-[color:var(--color-wk-accent-fg)]" />
            @else
                {{-- Default inner dot --}}
                <div style="height: 0.5rem; width: 0.5rem; border-radius: 9999px; background: var(--color-wk-accent-fg);"></div>
            @endif
        </div>

        {{-- Vertical connector line — solid line between items.
             Hidden on the last item so the timeline ends cleanly. --}}
        <div
            style="flex-grow: 1; width: 1px; background: var(--color-wk-border);"
            class="[li:last-child_&]:hidden"
            aria-hidden="true"
        ></div>
    </div>

    {{-- Content area: title slot, time, and body text.
         Bottom padding creates visual spacing between items while
         keeping the connector line continuous (no margin gaps).
         Padding is applied via dist/wirekit.css using the
         `data-wk-timeline-item-content` selector instead of inline
         style + `[li:last-child_&]:pb-0`. The inline style previously
         beat the class (no `!important`), so the padding-bottom-zero
         NEVER actually applied — not on the normal last item, and
         especially not when `after="true"` moved the real last item
         to `:nth-last-child(2)`. The CSS rule uses `:has()` to match
         both cases cleanly. --}}
    <div data-wk-timeline-item-content>
        {{-- Ahead of the title, so the outcome is heard as a prefix to the event
             rather than trailing after the body text. --}}
        @if($intentLabel !== null)
            <span class="sr-only">{{ __('wirekit::Status: :status', ['status' => $intentLabel]) }}</span>
        @endif

        @if(isset($title))
            <div class="text-[length:var(--text-wk-md)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
                {{ $title }}
            </div>
        @endif

        @if($time)
            @php
                // One class list, two elements — so the branch below cannot drift into two
                // different sizes for the same row.
                $timeClasses = 'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]';
            @endphp

            @if(filled($datetime))
                <time datetime="{{ $datetime }}" class="{{ $timeClasses }}">{{ $time }}</time>
            @else
                <span class="{{ $timeClasses }}">{{ $time }}</span>
            @endif
        @endif

        @if($slot->hasActualContent())
            <div style="margin-top: 0.25rem;" class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]">
                {{ $slot }}
            </div>
        @endif
    </div>
</li>
