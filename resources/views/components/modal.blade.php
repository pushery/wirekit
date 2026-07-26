@props([
    'name' => null,
    'size' => config('wirekit.components.modal.size', 'md'),
    'dismissible' => config('wirekit.components.modal.dismissible', true),
    'describedby' => null,
    // Names the dialog when it has no header sub-component. A dialog with no
    // accessible name is announced as just "dialog", and the failure is silent —
    // see the aria-labelledby block below for why one had to be added.
    // (No component tag in this comment: Blade compiles tags inside comments too,
    // and one here breaks the @props array it sits in.)
    'ariaLabel' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Title ID for aria-labelledby — links dialog to its header
    $titleId = 'wk-modal-title-' . ($name ?? uniqid());

    // HOW THE DIALOG GETS ITS NAME, and why this is not just `aria-labelledby`.
    //
    // The panel used to carry aria-labelledby unconditionally, but only
    // the header sub-component ever puts that id on an element. A modal built
    // without a header — a confirmation, a media lightbox, anything whose heading
    // is its own markup — therefore pointed aria-labelledby at an id nothing
    // carried. Per ARIA that resolves to no name at all, so the dialog announced
    // as bare "dialog", and nothing anywhere said so: the markup looked complete
    // and axe cannot see a reference that is merely unfulfilled at runtime.
    //
    // Three sources, in priority order, and only one is ever emitted:
    //   1. a caller `aria-label` attribute,
    //   2. the `ariaLabel` prop,
    //   3. the header, via aria-labelledby.
    // A caller attribute wins over the prop because it is the more specific
    // instruction; both win over the header because if someone named the dialog
    // explicitly, that is the name they meant.
    $callerAriaLabel = $attributes->get('aria-label');
    $resolvedAriaLabel = $callerAriaLabel ?? $ariaLabel;

    // Is there a header to point at? The header's own id is bound by Alpine at
    // runtime and so is invisible here; the marker attribute is what makes the
    // question answerable at render time. The slot is already rendered by now.
    $hasHeader = str_contains((string) $slot, 'data-wk-modal-header');

    if ($resolvedAriaLabel === null && ! $hasHeader) {
        // Say it where the developer will see it — throws in debug, logs in
        // production, the house strictness gate. Nothing downstream can recover a
        // name that was never given, and a nameless dialog is a WCAG 4.1.2 failure
        // that no automated scan of the page will catch.
        WireKit::validateProp('modal', 'ariaLabel', '', [
            'a non-empty label, or an <x-wirekit::modal.header> to name the dialog',
        ]);
    }

    // aria-* belongs on the element that carries role="dialog", not on the
    // x-data wrapper. `aria-label` on a roleless <div> is prohibited by ARIA and
    // simply does not reach assistive tech — the same defect that was fixed on
    // combobox, one component over.
    $ariaAttributes = collect($attributes->getAttributes())
        ->filter(fn ($value, string $key): bool => str_starts_with($key, 'aria-'))
        ->all();
    $attributes = $attributes->except(array_keys($ariaAttributes));

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
        'wk-scrollbar overflow-y-auto',
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
                    @if($resolvedAriaLabel !== null)
                        aria-label="{{ $resolvedAriaLabel }}"
                    @else
                        aria-labelledby="{{ $titleId }}"
                    @endif
                    @if($describedby) aria-describedby="{{ $describedby }}" @endif
                    {{-- aria-* the caller passed, moved here from the roleless wrapper.
                         aria-label is excluded: it is resolved above so an explicit name
                         and the header can never both be emitted. --}}
                    @foreach($ariaAttributes as $ariaKey => $ariaValue)
                        @if($ariaKey !== 'aria-label') {{ $ariaKey }}="{{ $ariaValue }}" @endif
                    @endforeach
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
