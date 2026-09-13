{{-- optimistic-ui: n/a — sub-component
     A header slot; the modal owns visibility. --}}
@props([
    'scope' => null,
    'close' => true,
])

@php
    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('modal.header', $attributes->getAttributes());

    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $close = BooleanProp::from($close, true);

    // Header classes — top section with title text and bottom border.
    // When the auto close-X is active, the header becomes a flex row so the
    // title sits on the start and the close button on the end. Without the
    // close button the original single-child layout is preserved.
    $classes = WireKit::resolveClasses('modal.header', 'base', implode(' ', [
        // ⚠️ ONE TOKEN ON ALL FOUR SIDES. The top used to get `--padding-wk-y-md` (0.5rem)
        // against `--padding-wk-x-lg` (1rem) at the sides: compact under a one-line title,
        // cramped the moment the header grows — an avatar, a name and a subtitle read as text
        // stuck to the top edge while the sides kept their air. Equal padding is the
        // requirement, so it is ONE token, and a theme cannot pull the sides apart.
        'p-[var(--padding-wk-x-lg)]',
        'border-b',
        'border-[var(--color-wk-border-subtle)]',
        'text-[length:var(--text-wk-lg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        // ⚠️ `items-start`, NOT `items-center`: the close button belongs in the top corner,
        // however tall the header grows. Centered, it drifted to half the height of a header
        // carrying an avatar and a three-line subtitle.
        'flex items-start justify-between',
        'gap-[var(--gap-wk-md)]',
    ]), $scope);

    // Close button classes — icon button that invokes the parent modal's
    // close() method.
    //
    // The visible box stays at --size-wk-sm (32px); a centered transparent 44×44
    // ::before supplies the WCAG 2.5.5 AAA hit area without changing the render.
    // Same expander the file-upload remove button already ships.
    //
    // This comment used to claim the AAA target outright while the button was
    // 32×32 with nothing widening it — no min-height, no expander — and the
    // coarse-pointer floor does not reach it either, since that rule is
    // element-qualified to .wk-field / .wk-button and this carries neither. A
    // comment asserting a property nothing implements is worse than no comment:
    // it is exactly what stops the next reader from checking.
    //
    // It sits exactly in the corner the header's padding defines, top and end alike, so its
    // glyph is inset by the same distance from both edges. It used to be pulled into the end
    // padding by a negative margin, which only read right while the button was vertically
    // centered — anchored to the top, the same pull put the glyph closer to the side than
    // to the top.
    $closeClasses = WireKit::resolveClasses('modal.header', 'close', implode(' ', [
        'relative shrink-0',
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] w-[var(--size-wk-sm)]',
        "before:absolute before:left-1/2 before:top-1/2 before:h-[var(--size-wk-touch-target)] before:w-[var(--size-wk-touch-target)] before:-translate-x-1/2 before:-translate-y-1/2 before:content-['']",
        'rounded-[var(--radius-wk-sm)]',
        'cursor-pointer',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
    ]), $scope);
@endphp

{{-- No x-cloak needed — this sub-component lives inside the parent modal's
     x-show/x-teleport block, which already gates visibility.

     Modal header — flex row containing the title (left) and an automatic
     close-X button (right). The close button is rendered unconditionally
     at Blade time when `close=true` (the default) and then gated at runtime
     with `x-show="dismissible"` — this keeps non-dismissible confirmation
     dialogs clean (no visible X) while still giving every dismissible modal
     a discoverable, keyboard-reachable close affordance without authors
     having to remember `<x-wirekit::modal.close>`. Opt out via `:close="false"`
     when you want full control over the header layout. --}}
{{-- data-wk-modal-header is the marker the parent modal looks for. The title id
     itself is bound by Alpine at runtime, so it cannot tell the parent anything at
     render time — but the parent has to know whether a header exists at all, or it
     would point aria-labelledby at an id nothing carries and leave the dialog
     nameless with no error. --}}
<div data-wk-modal-header {{ $attributes->class([$classes]) }}>
    {{-- Title wrapper — bears the aria-labelledby target ID. The id is read
         from the parent panel's data-wk-title-id to complete the dialog's
         aria-labelledby chain. min-w-0 + flex-1 allow long titles to wrap
         without pushing the close button off the right edge. --}}
    <div
        x-bind:id="$wkAncestorData('[data-wk-title-id]', 'wkTitleId')"
        class="min-w-0 flex-1"
    >
        {{ $slot }}
    </div>

    @if ($close)
        {{-- Auto close-X — hidden via Alpine when the parent modal is
             non-dismissible, so confirmation dialogs stay escape-free. --}}
        <button
            type="button"
            x-show="dismissible"
            x-on:click="close()"
            aria-label="{{ __('wirekit::Close') }}"
            class="{{ $closeClasses }}"
        >
            {{-- h-4 w-4: standard Tailwind SVG sizing — not a design token candidate --}}
            <svg
                class="h-4 w-4"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    @endif
</div>
