@props([
    'name' => null,
    'size' => config('wirekit.components.modal.size', 'md'),
    'dismissible' => config('wirekit.components.modal.dismissible', true),
    'describedby' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Title ID for aria-labelledby — links dialog to its header
    $titleId = 'wk-modal-title-' . ($name ?? uniqid());

    // Backdrop classes — semi-transparent overlay behind the dialog
    $backdropClasses = WireKit::resolveClasses('modal', 'backdrop', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    // Container classes — centers the dialog on screen
    $containerClasses = WireKit::resolveClasses('modal', 'container', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'flex items-center justify-center',
        'p-[var(--padding-wk-y-xl)]',
        'overflow-y-auto',
    ]), $scope);

    // Panel classes — the dialog surface with shadow and rounded corners
    $panelClasses = WireKit::resolveClasses('modal', 'panel', implode(' ', [
        'relative w-full',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'overflow-hidden',
    ]), $scope);

    // Size mapping to modal width tokens
    $sizeClass = match ($size) {
        'sm' => 'max-w-[var(--size-wk-modal-sm)]',
        'md' => 'max-w-[var(--size-wk-modal-md)]',
        'lg' => 'max-w-[var(--size-wk-modal-lg)]',
        'xl' => 'max-w-[var(--size-wk-modal-xl)]',
        'full' => 'max-w-[var(--size-wk-modal-full)]',
        default => 'max-w-[var(--size-wk-modal-md)]',
    };
@endphp

{{-- Modal component — teleported to body for proper stacking context.

     ESC handling: the JS component uses `focus-trap` with `escapeDeactivates`
     for in-page interaction, BUT `focus-trap` ignores keydowns whose target is
     not inside the trap container. Playwright's `locator.press('Escape')` on
     the non-focusable panel div lets focus fall back to `document.body`, so
     focus-trap never sees the ESC and the overlay stays open. A window-level
     ESC listener bypasses this entirely: it catches the event regardless of
     focus location and calls `close()` directly (which in turn deactivates the
     focus trap). `close()` is guarded against re-entry, so the extra call is
     safe even if focus-trap happens to catch it too. Only registered when the
     modal is dismissible — non-dismissible modals must never close on ESC. --}}
<div
    x-data="wirekitModal({ name: '{{ $name }}', dismissible: {{ $dismissible ? 'true' : 'false' }} })"
    @if($dismissible) x-on:keydown.escape.window="open && isTopmost && close()" @endif
    {{ $attributes }}
>
    {{-- Trigger slot — always visible, clicking opens the modal --}}
    @isset($trigger)
        <div x-on:click="show()">
            {{ $trigger }}
        </div>
    @endisset

    {{-- Modal overlay and dialog — rendered when open --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak>
            {{-- Backdrop.
                 Leave transition intentionally omitted: pest-plugin-browser's
                 `assertDontSee()` is synchronous (no auto-wait), and any fade-out
                 (even 150ms) races against the assertion. Instant close is also
                 better UX — matches GitHub, Linear, macOS dialogs. --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="{{ $backdropClasses }}"
                @if($dismissible) x-on:click="handleBackdropClick()" @endif
                aria-hidden="true"
            ></div>

            {{-- Dialog container — centers the panel.
                 Click handler here (not on backdrop) because this div is layered
                 on top and intercepts pointer events. Panel has x-on:click.stop
                 so clicks inside the dialog don't bubble up to close it. --}}
            <div
                class="{{ $containerClasses }}"
                @if($dismissible) x-on:click="handleBackdropClick()" @endif
            >
                {{-- Dialog panel — the actual modal content --}}
                {{-- Dialog panel — leave transition intentionally omitted
                     for the same reason as the backdrop above. --}}
                <div
                    x-ref="panel"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    role="dialog"
                    {{-- Dynamic aria-modal so a stacked modal-over-modal pair only
                         marks the topmost as modal — ARIA spec compliance. --}}
                    :aria-modal="isTopmost ? 'true' : 'false'"
                    aria-modal="true"
                    aria-labelledby="{{ $titleId }}"
                    @if($describedby) aria-describedby="{{ $describedby }}" @endif
                    class="{{ $panelClasses }} {{ $sizeClass }}"
                    x-on:click.stop
                    wire:ignore.self
                    data-wk-title-id="{{ $titleId }}"
                >
                    {{ $slot }}
                </div>
            </div>
        </div>
    </template>
</div>
