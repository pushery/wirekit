{{-- optimistic-ui: n/a — client-only
     Its state is open state, query and highlighted entry. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'hotkey' => config('wirekit.components.command-palette.hotkey', 'cmd+k'),
    'placeholder' => config('wirekit.components.command-palette.placeholder') ?? __('wirekit::Search commands…'),
    // When true (default) the overlay teleports out of the document flow so it sits above
    // every other stacking context. Set to false to render the overlay inline
    // inside the parent element — useful for docs previews or when embedding
    // the palette inside a scoped stacking context (wrapper with
    // `contain: layout`, `transform`, etc.) that should contain the fixed
    // overlay instead of letting it escape to the viewport.
    'teleport' => true,
    // When true (default) opening the palette holds the page still with the counted lock
    // modal and drawer share, so the page behind the overlay can't be scrolled, on iOS too.
    // Set to false when the palette is embedded inside a local container (e.g. a
    // docs preview card) where locking global body scroll would be disruptive and
    // where a backdrop scoped via `contain: layout` already confines the overlay.
    'lockScroll' => true,
    // Addresses this palette: `wirekit-command-palette-show`, `-close` and
    // `wirekit:command-palette-state` with a `name` in their detail reach only the palette of
    // that name. Without one they reach every palette on the page, as they always have.
    'name' => null,
    // With a `name`, answer only the events that carry it: an event without a name, which
    // reaches every palette otherwise, passes this one by. For a palette that shares a page
    // with others it must never open for, such as a site search beside demos. Without a
    // `name` it changes nothing.
    'namedOnly' => false,
    // The dialog's accessible name, announced first when the palette opens. A search
    // is announced as a search this way, and two palettes on one page need not share
    // a name. Empty keeps the translated default.
    'label' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('command-palette', $attributes->getAttributes());

    // Page-unique, because two palettes on one page shared this one. The id was the literal
    // string `wk-command-list`, so the second listbox duplicated the first one's id and the
    // second input's `aria-controls` resolved to the FIRST palette's list — a combobox
    // pointing at somebody else's options. Neither palette showed anything wrong; the
    // browser simply takes the first match for a duplicate id and moves on.
    $listId = \Pushery\WireKit\Support\DomId::unique(null, 'wk-command-list-');

    // A caller-supplied `aria-label` names the CONTROL, not the wrapper `{{ $attributes }}`
    // lands on. `<x-wirekit::command-palette aria-label="…">` put the name on a roleless element,
    // so the control the user actually operates kept no accessible name at all — WCAG
    // 4.1.2, and it looked correct in the markup, which is why nothing caught it.
    $callerLabel = $attributes->get('aria-label');
    $attributes = $attributes->except(['aria-label']);


    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $teleport = BooleanProp::from($teleport, true);
    $lockScroll = BooleanProp::from($lockScroll, true);
    $namedOnly = BooleanProp::from($namedOnly, false);

    // Command Palette — spotlight-style search modal (Cmd/Ctrl+K).
    // Uses combobox + listbox pattern with keyboard navigation.
    $backdropClasses = 'wk-overlay-fixed wk-overlay-layer-modal '.WireKit::resolveClasses('command-palette', 'backdrop', implode(' ', [
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
        'focus:outline-hidden',
    ]), $scope);

    // The results list was the only region here without a seam: backdrop, panel
    // and input all take a `scope` override, the top offset is even a documented
    // variable, and the list carried a bare fixed max-height utility.
    //
    // Which is deliberately DESCRIBED rather than named. Tailwind scans this
    // file as text and does not know a PHP comment from markup, so writing the
    // old class here would re-emit it into the compiled stylesheet — and the
    // reverse-diff would then report a selector no source produces, pointing at
    // a component that no longer uses it.
    //
    // 288px is a fine default and a poor ceiling. A palette showing ranked search
    // results wants roughly half the viewport — on a 1080p screen that is ~540px,
    // so the fixed height nearly halves what a reader can see without scrolling.
    //
    // Both doors are open, because they answer different questions. The variable
    // is for "taller, please" and needs no build step; the resolveClasses key is
    // for a scope that restyles the region wholesale, like its three siblings.
    $listClasses = 'wk-scrollbar '.WireKit::resolveClasses('command-palette', 'list', implode(' ', [
        'max-h-[var(--wk-command-palette-list-max-height,18rem)]',
        'overflow-y-auto',
        'py-[var(--padding-wk-y-xs)]',
    ]), $scope);

    // The row between the input and the list: controls that narrow what the list shows, which
    // is why they sit ABOVE it. In the footer a filter would come after the results it filters.
    $filtersClasses = WireKit::resolveClasses('command-palette', 'filters', implode(' ', [
        'flex flex-wrap items-center',
        'gap-[var(--gap-wk-xs)]',
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-sm)]',
        'border-b border-[var(--color-wk-border)]',
    ]), $scope);

    // The `loading` and `error` slots, set like the empty state so the three read as one family.
    $stateClasses = WireKit::resolveClasses('command-palette', 'state', implode(' ', [
        'px-[var(--padding-wk-x-lg)]',
        'py-[var(--padding-wk-y-xl)]',
        'text-center',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
    ]), $scope);

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
@endphp

<div
    x-data="wirekitCommandPalette({ hotkey: {{ \Pushery\WireKit\Support\AlpinePayload::string($hotkey) }}, lockScroll: {{ $lockScroll ? 'true' : 'false' }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::from(filled($name) ? (string) $name : null) }}, namedOnly: {{ $namedOnly ? 'true' : 'false' }} })"
    {{ $attributes }}
>
    {{-- Overlay markup. Wrapped in `<template x-teleport="#wk-overlay-root">` by default so
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
    <template x-teleport="#wk-overlay-root">
    @endif
        <div x-show="isOpen" x-cloak>
            {{-- Backdrop --}}
            <div
                x-show="isOpen"
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
                class="wk-scrollbar fixed inset-0 z-[var(--z-wk-modal)] flex items-start justify-center pt-[var(--wk-command-palette-offset-top,20vh)] px-[var(--padding-wk-x-lg)] overflow-y-auto"
                x-on:click="close()"
            >
                <div
                    x-ref="panel"
                    x-show="isOpen"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ filled($label) ? $label : __('wirekit::Command palette') }}"
                    class="{{ $panelClasses }}"
                    x-on:click.stop
                    @keydown="handleKeydown"
                >
                    {{-- Search input --}}
                    <div class="flex items-center gap-[var(--gap-wk-sm)]">
                        {{-- Search icon, through the icon system rather than hand-drawn, so it
                             follows the developer's icon preset like every other icon in the
                             package. --}}
                        <div class="pl-[var(--padding-wk-x-lg)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
                            @if(function_exists('svg'))
                                {{ svg(\Pushery\WireKit\WireKit::icon('search'), ['class' => 'w-5 h-5']) }}
                            @endif
                        </div>
                        <input
                            x-ref="input"
                            x-model="query"
                            type="text"
                            role="combobox"
                            {{-- The palette's own input is the control the caller means; without a
                                 name it is announced as an unlabeled combobox. The placeholder is
                                 NOT a name — it disappears the moment the user types. --}}
                            aria-label="{{ $callerLabel ?: __('wirekit::Search commands') }}"
                            aria-expanded="true"
                            aria-controls="{{ $listId }}"
                            :aria-activedescendant="activeDescendant"
                            aria-autocomplete="list"
                            placeholder="{{ $placeholder }}"
                            class="wk-field {{ $inputClasses }}"
                            autocomplete="off"
                            {{-- Emit the query on every keystroke (debounced) so a host can drive
                                 server-side search. Kept as an event rather than a raw wire:model to
                                 avoid double-binding the internal x-model.

                                 emitQuery() dispatches from the component ROOT, not from this input:
                                 this input is teleported into <body>, so an event fired here would
                                 never pass the root element and a listener placed on the component
                                 would never hear it. Wire it up on the component:

                                   <x-wirekit::command-palette
                                       x-on:wirekit-command-palette-query="search($event.detail.query)" />

                                 or, from Livewire, with the FULL event name:

                                   <x-wirekit::command-palette
                                       wire:wirekit-command-palette-query="search($event.detail.query)" /> --}}
                            x-on:input.debounce.300ms="emitQuery()"
                        />
                    </div>

                    {{-- Filter row — between the input and the list, in that order for Tab as
                         well: the reader types, then narrows. The list keys stay with the input
                         (see handleKeydown), so a control here keeps its own Enter and arrows. --}}
                    @isset($filters)
                        <div class="{{ $filtersClasses }}">
                            {{ $filters }}
                        </div>
                    @endisset

                    {{-- Command list --}}
                    <div
                        x-ref="list"
                        id="{{ $listId }}"
                        role="listbox"
                        aria-label="{{ $callerLabel ?: __('wirekit::Search commands') }}"
                        {{-- Busy while a remote source is answering: the options still showing
                             are the previous answer, and a reader should not take them as the
                             new one. Absent otherwise, which is what `false` means. --}}
                        x-bind:aria-busy="isLoading"
                        {{-- Choosing an option closes the palette. On the list rather than on the
                             option, so the option's own handlers run first. --}}
                        x-on:click="closeAfterChoice"
                        {{-- `wk-command-list` is a MARKER CLASS, and it is documented in
                             `public-css-api.md` as a Stable styling hook. It never existed as
                             one: the id happened to carry the same string, and the drift guard
                             greps templates for the literal, so a documented class contract was
                             satisfied by an unrelated `id=`. Making the id page-unique broke
                             the coincidence and showed the promise had no backing.
                             The promise is kept rather than withdrawn — a developer may already
                             be scoping overrides to it. --}}
                        class="wk-command-list {{ $listClasses }}"
                    >
                        {{ $slot }}
                    </div>

                    {{-- Empty state — a SIBLING of the list, and that is the whole point.
                         `role="listbox"` owns its children, and they are options; a line of
                         prose among them is a non-option child of a role that requires them,
                         which is what axe reports as `aria-required-children`. Rendered in the
                         default slot, `<x-wirekit::command-palette.empty>` was exactly that.

                         It also announced nothing there, being a roleless <div>: the input
                         keeps `aria-expanded="true"` and reports no active descendant, so a
                         search that matched nothing was indistinguishable from a list the
                         reader had not touched. The sub-component carries `role="status"`
                         for that half; this slot is the other half.

                         The palette decides WHEN it shows: only while the list holds no option
                         and no remote request is pending or failed. Rendered whenever the slot
                         is passed, the message would sit beneath a full list of results. --}}
                    @isset($empty)
                        <div x-show="showsEmpty">
                            {{ $empty }}
                        </div>
                    @endisset

                    {{-- Remote source — "not here yet" and "failed" are not "nothing matched".
                         The host reports which one applies through the
                         `wirekit:command-palette-state` event (see the factory).

                         ONE region for both, and it is always rendered: a live region that
                         appears together with its text is announced unreliably, while text
                         appearing inside a region that already exists is announced. The slots
                         therefore take plain content — a `role="status"` of their own inside
                         this one would be announced twice. --}}
                    @if(isset($loading) || isset($error))
                        <div role="status">
                            @isset($loading)
                                <div x-show="isLoading" class="{{ $stateClasses }}">
                                    {{ $loading }}
                                </div>
                            @endisset
                            @isset($error)
                                <div x-show="hasError" class="{{ $stateClasses }}">
                                    {{ $error }}
                                </div>
                            @endisset
                        </div>
                    @endif

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
