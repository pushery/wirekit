@props([
    'scope' => null,
    'close' => true,
])

@php
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
    $classes = WireKit::resolveClasses('drawer.header', 'base', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'border-b',
        'border-[var(--color-wk-border-subtle)]',
        'text-[length:var(--text-wk-lg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[color:var(--color-wk-text)]',
        'flex items-center justify-between',
        'gap-[var(--gap-wk-md)]',
    ]), $scope);

    // Close button classes — mirrors modal.header close to keep both
    // overlay sub-components visually consistent.
    $closeClasses = WireKit::resolveClasses('drawer.header', 'close', implode(' ', [
        'shrink-0',
        'inline-flex items-center justify-center',
        'h-[var(--size-wk-sm)] w-[var(--size-wk-sm)]',
        '-mr-[var(--padding-wk-x-sm)]',
        'rounded-[var(--radius-wk-sm)]',
        'cursor-pointer',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'focus-visible:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors',
    ]), $scope);
@endphp

{{-- No x-cloak needed — this sub-component lives inside the parent drawer's
     x-show/x-teleport block, which already gates visibility.

     Drawer header — flex row containing the title (left) and an automatic
     close-X button (right). The close button is rendered unconditionally
     at Blade time when `close=true` (the default) and then gated at runtime
     with `x-show="dismissible"` so non-dismissible drawers stay escape-free.
     Opt out via `:close="false"` when you want full control over the
     header layout. --}}
<div {{ $attributes->class([$classes]) }}>
    {{-- Title wrapper — bears the aria-labelledby target ID. --}}
    <div
        x-bind:id="$el.closest('[data-wk-title-id]')?.dataset.wkTitleId"
        class="min-w-0 flex-1"
    >
        {{ $slot }}
    </div>

    @if ($close)
        {{-- Auto close-X — hidden via Alpine when the parent drawer is
             non-dismissible. --}}
        <button
            type="button"
            x-show="dismissible"
            x-on:click="close()"
            aria-label="{{ __('Close') }}"
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
