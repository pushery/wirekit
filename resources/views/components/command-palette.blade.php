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
    // that name. Without one they reach every palette on the page.
    'name' => null,
    // With a `name`, answer only the events that carry it: an event without a name, which
    // reaches every palette otherwise, passes this one by. For a palette that shares a page
    // with others it must never open for, such as a site search beside demos. Without a
    // `name` it changes nothing.
    'namedOnly' => false,
    // Take the hotkey from the other palettes on the page that share it, for as long as this one
    // is on the page: the key then opens and closes this palette, and the others stay quiet until
    // it leaves, as the palette of a selection leaves with the selection. Without a `hotkey` it
    // changes nothing.
    'takesHotkey' => false,
    // Where the overview of keyboard shortcuts on `?` lists the hotkey, under the palette's
    // `label`: `global`, under "Everywhere", since the key opens the palette from anywhere, dialogs
    // included; or `page`, under "On this page", for a palette that belongs to one page. Its key
    // then does nothing while a modal dialog is open over the page, as a shortcut of the page.
    'hotkeyArea' => 'global',
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

    // An unknown `hotkeyArea` is reported (Support\StrictnessGate) and renders as `global`; a bound
    // `null` or empty string is `global` without a word.
    $hotkeyArea = \Pushery\WireKit\WireKit::validateProp('command-palette', 'hotkeyArea', \Pushery\WireKit\Support\NumericProp::text($hotkeyArea ?: 'global'), ['global', 'page']);

    // Page-unique, so two palettes on one page do not share it. With a fixed id the second
    // listbox would duplicate the first one's and the second input's `aria-controls` would
    // resolve to the FIRST palette's list — a combobox pointing at somebody else's options,
    // with neither palette showing anything wrong: the browser simply takes the first match
    // for a duplicate id and moves on.
    $listId = \Pushery\WireKit\Support\DomId::unique(null, 'wk-command-list-');

    // A caller-supplied `aria-label` names the CONTROL, not the wrapper `{{ $attributes }}`
    // lands on. On the wrapper, `<x-wirekit::command-palette aria-label="…">` would put the name
    // on a roleless element and leave the control the user actually operates without an
    // accessible name — WCAG 4.1.2 — while looking correct in the markup.
    $callerLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    // A caller's `aria-labelledby` names the same two elements; the reference outranks a name.
    $callerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $callerLabelledBy = is_string($callerLabelledBy) && filled($callerLabelledBy) ? $callerLabelledBy : null;
    $attributes = $attributes->except(['aria-label', 'aria-labelledby']);


    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $teleport = BooleanProp::from($teleport, true, 'command-palette', 'teleport');
    $lockScroll = BooleanProp::from($lockScroll, true, 'command-palette', 'lockScroll');
    $namedOnly = BooleanProp::from($namedOnly, false, 'command-palette', 'namedOnly');
    $takesHotkey = BooleanProp::from($takesHotkey, false, 'command-palette', 'takesHotkey');
    // The dialog's accessible name, which the overview of keyboard shortcuts names the palette by.
    $dialogLabel = filled($label) ? \Pushery\WireKit\Support\SlotContent::textOf($label) : __('wirekit::Command palette');

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
        // The field takes the kit's ring like every tab stop, inset because the panel clips it.
        'focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // The results list takes a seam like the backdrop, the panel and the input.
    //
    // 288px is a fine default and a poor ceiling. A palette showing ranked search
    // results wants roughly half the viewport — on a 1080p screen that is ~540px,
    // and a fixed height would nearly halve what a reader can see without scrolling.
    //
    // Both doors are open, because they answer different questions. The variable
    // is for "taller, please" and needs no build step; the resolveClasses key is
    // for a scope that restyles the region wholesale, like its three siblings.
    $listClasses = 'wk-scrollbar '.WireKit::resolveClasses('command-palette', 'list', implode(' ', [
        'max-h-[var(--wk-command-palette-list-max-height,18rem)]',
        'relative overflow-y-auto',
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

    // The row above the field while a page is open: Back, and the page's heading.
    $pageBarClasses = WireKit::resolveClasses('command-palette', 'page-bar', implode(' ', [
        'flex items-center',
        'gap-[var(--gap-wk-sm)]',
        'px-[var(--padding-wk-x-md)]',
        'pt-[var(--padding-wk-y-sm)]',
    ]), $scope);

    // The page's name wraps rather than being cut: on a narrow screen a long heading takes a
    // second line, and the whole of it stays readable.
    $pageHeadingClasses = WireKit::resolveClasses('command-palette', 'page-heading', implode(' ', [
        'min-w-0 break-words',
        'text-[length:var(--text-wk-sm)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // Whether an entry can open a page here. The slot is rendered before this view, so a page in it
    // is already markup; a render that adds one renders the palette, and this, again.
    $hasPages = str_contains((string) $slot, 'data-wk-command-page=');

    // The empty state is said from the palette's status region, unless its block carries a role
    // of the caller's own: that role then says it, or with `role="none"` nothing does, for an
    // application that announces the empty state itself. Rendered before this view, the block
    // is already markup.
    $emptyInRegion = \Pushery\WireKit\Support\SlotContent::given($empty ?? null) && ! str_contains((string) $empty, 'data-wk-command-empty-own-role');

    // The announcement of a level names its count, a number that exists in the browser only, so
    // the plural forms travel there and `Intl.PluralRules` picks one in the application's locale.
    $entryPhrases = \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{0} no entries|{1} :count entry|[2,*] :count entries');
    $pluralLocale = \Pushery\WireKit\Support\IcuLocale::tag();

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

    // The palette is teleported, or fixed where it is not, so it takes no room where it is
    // written. Its root generates no box, which a flex or grid container would otherwise count
    // as an empty item with a gap of its own beside it.
    $attributes = $attributes->class(\Pushery\WireKit\Support\CompactClasses::list(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['contents'])));
@endphp

<div
    x-data="wirekitCommandPalette({ hotkey: {{ \Pushery\WireKit\Support\AlpinePayload::string($hotkey) }}, lockScroll: {{ $lockScroll ? 'true' : 'false' }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::from(filled($name) ? (string) $name : null) }}, namedOnly: {{ $namedOnly ? 'true' : 'false' }}, takesHotkey: {{ $takesHotkey ? 'true' : 'false' }}, hotkeyArea: {{ \Pushery\WireKit\Support\AlpinePayload::string($hotkeyArea) }}, label: {{ \Pushery\WireKit\Support\AlpinePayload::string($dialogLabel) }}, placeholder: {{ \Pushery\WireKit\Support\AlpinePayload::string(\Pushery\WireKit\Support\SlotContent::textOf($placeholder)) }}, entryPhrases: {{ \Pushery\WireKit\Support\AlpinePayload::from($entryPhrases) }}, locale: {{ \Pushery\WireKit\Support\AlpinePayload::string($pluralLocale) }}, remote: {{ \Pushery\WireKit\Support\SlotContent::given($loading ?? null) ? 'true' : 'false' }} })"
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
         the panel and nothing happens. Same pattern as
         <x-wirekit::alert-dialog> when `dismissible` is on. It listens with
         `.self`, so only a click on the container itself closes the palette;
         a click inside the panel is not stopped and reaches the document,
         where a listener the page delegates hears it. --}}
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
                x-on:click.self="close()"
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
                    aria-label="{{ $dialogLabel }}"
                    class="{{ $panelClasses }}"
                    @keydown="handleKeydown"
                >
                    {{-- The level the reader is on, while a page is open: Back, and the page's
                         heading. Before the field in the tab order, so Shift+Tab reaches Back; the
                         field keeps the list keys, and Backspace in it goes back as well. --}}
                    <div x-show="atPage" x-cloak class="{{ $pageBarClasses }}">
                        <x-wirekit::button intent="neutral" surface="ghost" size="sm" x-on:click="goBack()">
                            <x-slot:iconLeft><span class="inline-flex rtl:-scale-x-100" aria-hidden="true"><x-wirekit::icon name="chevron-left" size="sm" /></span></x-slot:iconLeft>
                            {{ __('wirekit::Back') }}
                        </x-wirekit::button>
                        <span class="{{ $pageHeadingClasses }}" x-text="pageHeading"></span>
                    </div>

                    {{-- Search input --}}
                    <div class="flex items-center gap-[var(--gap-wk-sm)]">
                        {{-- Search icon, through the icon system rather than hand-drawn, so it
                             follows the developer's icon preset like every other icon in the
                             package. --}}
                        <div class="pl-[var(--padding-wk-x-lg)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">
                            {{ \Pushery\WireKit\Support\IconSvg::html('search', ['class' => 'w-5 h-5']) }}
                        </div>
                        <input
                            {{-- Key presses of an input method composing text stop here, before the keys of the panel (utils/ime.js). --}}
                            x-wk-ime
                            x-ref="input"
                            x-model="query"
                            type="text"
                            role="combobox"
                            {{-- The palette's own input is the control the caller means; without a
                                 name it is announced as an unlabeled combobox. The placeholder is
                                 NOT a name — it disappears the moment the user types. --}}
                            @if($callerLabelledBy) aria-labelledby="{{ $callerLabelledBy }}" @else aria-label="{{ $callerLabel ?: __('wirekit::Search commands') }}" @endif
                            aria-expanded="true"
                            aria-controls="{{ $listId }}"
                            :aria-activedescendant="activeDescendant"
                            aria-autocomplete="list"
                            placeholder="{{ \Pushery\WireKit\Support\SlotContent::textOf($placeholder) }}"
                            {{-- A page may say what its search looks for. --}}
                            x-bind:placeholder="fieldPlaceholder"
                            class="wk-field {{ $inputClasses }}"
                            autocomplete="off"
                            {{-- Emit the query on every keystroke (debounced) so a host can drive
                                 server-side search. Kept as an event rather than a raw wire:model to
                                 avoid double-binding the internal x-model.

                                 emitTypedQuery() reports nothing Enter has sent already, so a host
                                 hears the query before the submit and not after it. It reports
                                 through emitQuery(), which dispatches from the component ROOT, not
                                 from this input: this input is teleported into <body>, so an event
                                 fired here would never pass the root element and a listener placed
                                 on the component would never hear it. Wire it up on the component:

                                   <x-wirekit::command-palette
                                       x-on:wirekit-command-palette-query="search($event.detail.query)" />

                                 or, from Livewire, with the FULL event name:

                                   <x-wirekit::command-palette
                                       wire:wirekit-command-palette-query="search($event.detail.query)" /> --}}
                            x-on:input="markQueryChanged()"
                            x-on:input.debounce.300ms="emitTypedQuery()"
                        />
                    </div>

                    {{-- Filter row — between the input and the list, in that order for Tab as
                         well: the reader types, then narrows. The list keys stay with the input
                         (see handleKeydown), so a control here keeps its own Enter and arrows. --}}
                    @if(\Pushery\WireKit\Support\SlotContent::given($filters ?? null))
                        <div class="{{ $filtersClasses }}">
                            {{ $filters }}
                        </div>
                    @endif

                    {{-- Command list --}}
                    <div
                        x-ref="list"
                        id="{{ $listId }}"
                        role="listbox"
                        {{-- Out of the tab order: the search field keeps the focus and names the
                             active command through `aria-activedescendant`. In Chromium a list that
                             scrolls and holds nothing focusable is a tab stop of its own. --}}
                        tabindex="-1"
                        @if($callerLabelledBy) aria-labelledby="{{ $callerLabelledBy }}" @else aria-label="{{ $callerLabel ?: __('wirekit::Search commands') }}" @endif
                        {{-- Busy while a remote source is answering: the options still showing
                             are the previous answer, and a reader should not take them as the
                             new one. Absent otherwise, which is what `false` means. --}}
                        x-bind:aria-busy="isLoading"
                        {{-- Choosing an option closes the palette. On the list rather than on the
                             option, so the option's own handlers run first. --}}
                        x-on:click="closeAfterChoice"
                        {{-- `wk-command-list` is a MARKER CLASS, documented in the public CSS
                             API as a Stable styling hook, so a developer can scope overrides to
                             it. The list's id is page-unique and is not that hook. --}}
                        class="wk-command-list {{ $listClasses }}"
                    >
                        {{ $slot }}
                    </div>

                    {{-- ONE status region for everything the palette says beside the list, and it
                         is rendered whenever there is something to say: a live region that
                         appears together with its text is announced unreliably, while text
                         appearing inside a region that already exists is announced. The slots
                         therefore take plain content — a `role="status"` of their own inside
                         this one would be announced twice.

                         A SIBLING of the list, and that is the whole point. `role="listbox"`
                         owns its children, and they are options; a line of prose among them is a
                         non-option child of a role that requires them, which is what axe reports
                         as `aria-required-children`.

                         Empty state: the input keeps `aria-expanded="true"` and reports no
                         active descendant, so without this a search that matched nothing would
                         be indistinguishable from a list the reader had not touched. The palette
                         decides WHEN it shows: only while the list holds no option and no remote
                         request is pending or failed. Rendered whenever the slot is passed, the
                         message would sit beneath a full list of results.

                         Remote source — "not here yet" and "failed" are not "nothing matched".
                         The host reports which one applies through the
                         `wirekit:command-palette-state` event (see the factory).

                         A palette with pages says each change of level in the same region, "Set
                         status, 3 entries": the level's name and its count, visually hidden.

                         An empty block with a role of the caller's own stays outside, where that
                         role says it, as it did before the palette said it itself. --}}
                    @if(\Pushery\WireKit\Support\SlotContent::given($empty ?? null) && ! $emptyInRegion)
                        <div x-show="showsEmpty">
                            {{ $empty }}
                        </div>
                    @endif
                    @if($emptyInRegion || \Pushery\WireKit\Support\SlotContent::given($loading ?? null) || \Pushery\WireKit\Support\SlotContent::given($error ?? null) || $hasPages)
                        <div role="status">
                            @if($emptyInRegion)
                                <div x-show="showsEmpty">
                                    {{ $empty }}
                                </div>
                            @endif
                            @if(\Pushery\WireKit\Support\SlotContent::given($loading ?? null))
                                <div x-show="isLoading" class="{{ $stateClasses }}">
                                    {{ $loading }}
                                </div>
                            @endif
                            @if(\Pushery\WireKit\Support\SlotContent::given($error ?? null))
                                <div x-show="hasError" class="{{ $stateClasses }}">
                                    {{ $error }}
                                </div>
                            @endif
                            @if($hasPages)
                                <span class="sr-only" x-text="levelAnnouncement"></span>
                            @endif
                        </div>
                    @endif

                    {{-- Optional footer slot --}}
                    @if(\Pushery\WireKit\Support\SlotContent::given($footer ?? null))
                        <div class="border-t border-[var(--color-wk-border)] p-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
                            {{ $footer }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @if($teleport)
    </template>
    @endif
</div>
