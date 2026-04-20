@props([
    'scope' => null,
    'close' => true,
])

@php
    use Pushery\WireKit\WireKit;

    // Header classes — top section with title text and bottom border.
    // When the auto close-X is active, the header becomes a flex row so the
    // title sits on the start and the close button on the end. Without the
    // close button the original single-child layout is preserved.
    $classes = WireKit::resolveClasses('modal.header', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'border-b',
        'border-[var(--color-wk-border-subtle)]',
        'text-[length:var(--text-wk-lg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[var(--color-wk-text)]',
        'flex items-center justify-between',
        'gap-[var(--gap-wk-md)]',
    ]), $scope);

    // Close button classes — icon button that invokes the parent modal's
    // close() method. Sized for WCAG 2.5.5 AAA (≥44×44 CSS px hit area).
    $closeClasses = WireKit::resolveClasses('modal.header', 'close', implode(' ', [
        'shrink-0',
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] w-[var(--size-wk-sm)]',
        '-mr-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-sm)]',
        'cursor-pointer',
        'text-[var(--color-wk-text-muted)]',
        'hover:text-[var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus-visible:outline-none',
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
<div {{ $attributes->class([$classes]) }}>
    {{-- Title wrapper — bears the aria-labelledby target ID. The id is read
         from the parent panel's data-wk-title-id to complete the dialog's
         aria-labelledby chain. min-w-0 + flex-1 allow long titles to wrap
         without pushing the close button off the right edge. --}}
    <div
        x-bind:id="$el.closest('[data-wk-title-id]')?.dataset.wkTitleId"
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
            aria-label="Close"
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
