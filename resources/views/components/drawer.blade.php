@props([
    'name' => null,
    'position' => config('wirekit.components.drawer.position', 'right'),
    'size' => config('wirekit.components.drawer.size', 'md'),
    'dismissible' => config('wirekit.components.drawer.dismissible', true),
    'describedby' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Title ID for aria-labelledby — links dialog to its header
    $titleId = 'wk-drawer-title-' . ($name ?? uniqid());

    // Backdrop classes — semi-transparent overlay behind the drawer
    $backdropClasses = WireKit::resolveClasses('drawer', 'backdrop', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-drawer)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    // Panel classes — the drawer surface
    $panelClasses = WireKit::resolveClasses('drawer', 'panel', implode(' ', [
        'fixed',
        'z-[var(--z-wk-drawer)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'shadow-[var(--shadow-wk-lg)]',
        'wk-scrollbar overflow-y-auto',
        'flex flex-col',
    ]), $scope);

    // Position classes — inset positioning per side
    $positionClasses = match ($position) {
        'left' => 'inset-y-0 left-0',
        'right' => 'inset-y-0 right-0',
        'top' => 'inset-x-0 top-0',
        'bottom' => 'inset-x-0 bottom-0',
        default => 'inset-y-0 right-0',
    };

    // Size classes — width for left/right, height for top/bottom
    $isHorizontal = in_array($position, ['left', 'right']);
    $sizeClass = match (true) {
        $isHorizontal && $size === 'sm' => 'w-[var(--size-wk-drawer-sm)]',
        $isHorizontal && $size === 'md' => 'w-[var(--size-wk-drawer-md)]',
        $isHorizontal && $size === 'lg' => 'w-[var(--size-wk-drawer-lg)]',
        ! $isHorizontal && $size === 'sm' => 'h-[var(--size-wk-drawer-sm)]',
        ! $isHorizontal && $size === 'md' => 'h-[var(--size-wk-drawer-md)]',
        ! $isHorizontal && $size === 'lg' => 'h-[var(--size-wk-drawer-lg)]',
        default => $isHorizontal ? 'w-[var(--size-wk-drawer-md)]' : 'h-[var(--size-wk-drawer-md)]',
    };

    // Slide transition classes — direction depends on position
    $enterStart = match ($position) {
        'left' => '-translate-x-full',
        'right' => 'translate-x-full',
        'top' => '-translate-y-full',
        'bottom' => 'translate-y-full',
        default => 'translate-x-full',
    };
    $enterEnd = match ($position) {
        'left', 'right' => 'translate-x-0',
        'top', 'bottom' => 'translate-y-0',
        default => 'translate-x-0',
    };
@endphp

{{-- Drawer component — slides in from the edge of the screen.

     ESC handling: the JS component uses `focus-trap` with `escapeDeactivates`
     for in-page interaction, BUT `focus-trap` ignores keydowns whose target is
     not inside the trap container. Playwright's `locator.press('Escape')` on
     the non-focusable panel div lets focus fall back to `document.body`, so
     focus-trap never sees the ESC and the overlay stays open. A window-level
     ESC listener bypasses this entirely: it catches the event regardless of
     focus location and calls `close()` directly (which in turn deactivates the
     focus trap). `close()` is guarded against re-entry, so the extra call is
     safe even if focus-trap happens to catch it too. Only registered when the
     drawer is dismissible — non-dismissible drawers must never close on ESC. --}}
<div
    x-data="wirekitDrawer({ name: '{{ $name }}', dismissible: {{ $dismissible ? 'true' : 'false' }} })"
    @if($dismissible) x-on:keydown.escape.window="open && isTopmost && close()" @endif
    {{ $attributes }}
>
    {{-- Drawer overlay and panel — teleported to body --}}
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

            {{-- Drawer panel — leave transition intentionally omitted
                 for the same reason as the backdrop above. --}}
            <div
                x-ref="panel"
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="{{ $enterStart }}"
                x-transition:enter-end="{{ $enterEnd }}"
                role="dialog"
                {{-- Dynamic aria-modal so a stacked drawer-over-modal (or
                     nested drawer) pair only marks the topmost as modal. --}}
                :aria-modal="isTopmost ? 'true' : 'false'"
                aria-modal="true"
                aria-labelledby="{{ $titleId }}"
                @if($describedby) aria-describedby="{{ $describedby }}" @endif
                class="{{ $panelClasses }} {{ $positionClasses }} {{ $sizeClass }}"
                x-on:click.stop
                wire:ignore.self
                data-wk-title-id="{{ $titleId }}"
            >
                {{ $slot }}
            </div>
        </div>
    </template>
</div>
