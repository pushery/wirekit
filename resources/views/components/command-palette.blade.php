@props([
    'hotkey' => 'cmd+k',
    'placeholder' => 'Search commands...',
    // When true (default) the overlay teleports to <body> so it sits above
    // every other stacking context. Set to false to render the overlay inline
    // inside the parent element — useful for docs previews or when embedding
    // the palette inside a scoped stacking context (wrapper with
    // `contain: layout`, `transform`, etc.) that should contain the fixed
    // overlay instead of letting it escape to the viewport.
    'teleport' => true,
    // When true (default) opening the palette sets `document.body.style.overflow = 'hidden'`
    // so the page behind the overlay can't be scrolled — standard modal behavior.
    // Set to false when the palette is embedded inside a local container (e.g. a
    // docs preview card) where locking global body scroll would be disruptive and
    // where a backdrop scoped via `contain: layout` already confines the overlay.
    'lockScroll' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Command Palette — spotlight-style search modal (Cmd/Ctrl+K).
    // Uses combobox + listbox pattern with keyboard navigation.
    $backdropClasses = WireKit::resolveClasses('command-palette', 'backdrop', implode(' ', [
        'fixed inset-0',
        'z-[var(--z-wk-modal)]',
        'bg-[var(--color-wk-overlay)]',
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('command-palette', 'panel', implode(' ', [
        'relative w-full',
        'max-w-[var(--size-wk-modal-md)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-xl)]',
        'shadow-[var(--shadow-wk-lg)]',
        'overflow-hidden',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $inputClasses = WireKit::resolveClasses('command-palette', 'input', implode(' ', [
        'w-full',
        'border-0 border-b border-[var(--color-wk-border)]',
        'bg-transparent',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-md)]',
        'text-[length:var(--text-wk-lg)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'focus:outline-none',
    ]), $scope);
@endphp

<div
    x-data="wirekitCommandPalette({ hotkey: '{{ $hotkey }}', lockScroll: {{ $lockScroll ? 'true' : 'false' }} })"
    {{ $attributes }}
>
    {{-- Overlay markup. Wrapped in `<template x-teleport="body">` by default so
         the overlay escapes to <body> and sits above every other stacking
         context. When `$teleport === false` (e.g. in docs previews) the
         template wrapper is skipped and the overlay renders inline inside the
         component root, so an ancestor with `contain: layout` / `transform` can
         contain the fixed overlay instead of letting it escape to the viewport.
         The container layer (wrapping the panel) carries its own
         `x-on:click="close()"` because it actually intercepts pointer events —
         clicks on the "whitespace" around the panel land on the container, not
         on the underlying backdrop. Without this the user can click outside
         the panel and nothing happens. Same fix pattern as
         <x-wirekit::alert-dialog> when `dismissible` is on. The panel itself
         uses `x-on:click.stop` so clicks inside the palette do NOT propagate
         to the container and accidentally close the palette. --}}
    @if($teleport)
    <template x-teleport="body">
    @endif
        <div x-show="open" x-cloak>
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="{{ $backdropClasses }}"
                x-on:click="close()"
                aria-hidden="true"
            ></div>

            {{-- Command palette container — clicks here (outside the panel) also
                 close the palette, because the container sits on top of the
                 backdrop and intercepts clicks. --}}
            <div
                class="fixed inset-0 z-[var(--z-wk-modal)] flex items-start justify-center pt-[var(--wk-command-palette-offset-top,20vh)] px-[var(--padding-wk-x-lg)] overflow-y-auto"
                x-on:click="close()"
            >
                <div
                    x-ref="panel"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Command palette"
                    class="{{ $panelClasses }}"
                    x-on:click.stop
                    @keydown="handleKeydown"
                >
                    {{-- Search input --}}
                    <div class="flex items-center gap-[var(--gap-wk-sm)]">
                        {{-- Search icon --}}
                        <div class="pl-[var(--padding-wk-x-lg)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input
                            x-ref="input"
                            x-model="query"
                            type="text"
                            role="combobox"
                            aria-expanded="true"
                            aria-controls="wk-command-list"
                            :aria-activedescendant="activeDescendant"
                            aria-autocomplete="list"
                            placeholder="{{ $placeholder }}"
                            class="{{ $inputClasses }}"
                            autocomplete="off"
                        />
                    </div>

                    {{-- Command list --}}
                    <div
                        x-ref="list"
                        id="wk-command-list"
                        role="listbox"
                        class="max-h-72 overflow-y-auto py-[var(--padding-wk-y-xs)]"
                    >
                        {{ $slot }}
                    </div>

                    {{-- Optional footer slot --}}
                    @isset($footer)
                        <div class="border-t border-[var(--color-wk-border)] p-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
                            {{ $footer }}
                        </div>
                    @endisset
                </div>
            </div>
        </div>
    @if($teleport)
    </template>
    @endif
</div>
