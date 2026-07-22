@props([
    'items' => [],                  // [{id,type,title,body?,timeLabel?,read?,group?,href?,actionLabel?}]
    'groupBy' => config('wirekit.components.notification-center.group-by', 'none'), // none | time | type ('time' groups by each item's `group` label)
    'title' => __('Notifications'),
    'filters' => config('wirekit.components.notification-center.filters', false), // show type-filter tabs
    'realtimeEvent' => null,        // window event name to listen for new items
    'open' => false,                // start with the panel open (inline embeds / demos)
    'seeAllHref' => null,           // footer "see all" link
    'seeAllLabel' => 'See all',
    'emptyText' => "You're all caught up",
    'name' => null,                 // hidden-input name mirroring the unread count
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    $groupBy = WireKit::validateProp('notification-center', 'groupBy', $groupBy, ['none', 'time', 'type']);
    $id = $attributes->get('id', 'notification-center-'.Str::random(6));
    $name = $name ?? $attributes->get('name');

    $itemsArr = $items instanceof \Illuminate\Support\Collection ? $items->values()->all() : array_values((array) $items);
    // Server-side unread count for the no-flash initial badge.
    $serverUnread = collect($itemsArr)->reject(fn ($i) => (bool) (((array) $i)['read'] ?? false))->count();

    $titleId = $id.'-title';

    $base = WireKit::resolveClasses('notification-center', 'base', 'relative inline-block font-[family-name:var(--font-wk-sans)]', $scope);

    $tab = 'px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-xs)] rounded-[var(--radius-wk-full)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer transition-colors';

    // Shared row chrome for both row variants (a real <a> when the item carries
    // an href, a <button> otherwise) — ONE interactive element per row.
    $row = 'w-full flex items-start gap-[var(--space-wk-sm)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-left hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset transition-colors cursor-pointer border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]';
@endphp

<div
    {{ $attributes->except(['id', 'name', 'class'])->whereDoesntStartWith('wire:model') }}
    id="{{ $id }}"
    {{-- State-mutating demo (open/close, mark-read, realtime): opt into the docs
         replay affordance so a "used-up" preview can be reset (mirrors alert/badge). --}}
    data-replayable="true"
    x-data="wirekitNotificationCenter({ items: @js($itemsArr), groupBy: '{{ $groupBy }}', open: {{ filter_var($open, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false' }}@if($realtimeEvent), realtimeEvent: '{{ $realtimeEvent }}'@endif })"
    {{-- click.outside lives on the teleported panel (it's no longer in this subtree);
         escape stays here (window-scoped, teleport-agnostic). --}}
    x-on:keydown.escape.window="open && close(true)"
    {{ $attributes->only('class')->class([$base]) }}
>
    @if($name)
        {{-- Mirrors the live unread count for a wire:model bridge. --}}
        <input type="hidden" x-ref="model" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} :value="unreadCount" />
    @endif

    {{-- Screen-reader announcement for new notifications. Fed from the SAME
         Alpine state as the badge; the text carries the LATEST item's title (items[0])
         so a fresh notification re-announces even when the count is unchanged — a
         count-only region would stay silent when one notification replaces another.
         Polite (never assertive): a notification must not interrupt the user. --}}
    <p class="sr-only" role="status" aria-live="polite" aria-atomic="true"
       x-text="unreadCount > 0 ? unreadCount + ' {{ __('unread. Latest:') }} ' + (items[0]?.title ?? '') : ''"></p>

    {{-- Bell trigger. The accessible name carries the live unread count so a
         screen reader announces "Notifications, 3 unread" (not color/dot alone). --}}
    <button
        type="button"
        x-ref="bell"
        @click="toggle()"
        :aria-expanded="open"
        aria-haspopup="dialog"
        aria-label="{{ $title }}"
        :aria-label="unreadCount > 0 ? '{{ $title }}, ' + unreadCount + ' unread' : '{{ $title }}, none unread'"
        class="relative inline-flex items-center justify-center h-[var(--size-wk-md)] w-[var(--size-wk-md)] rounded-[var(--radius-wk-md)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
    >
        <svg aria-hidden="true" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg>
        {{-- Unread count pill (decorative — the count is in the bell's aria-label).

             INTENTIONALLY hand-rolled, NOT <x-wirekit::indicator> + <x-wirekit::badge>
             (maintainer decision 2026-07-18): a bell count wants a compact pill
             anchored at the BUTTON corner. The indicator would anchor at the icon
             corner (~10px inward) and badge's smallest size is a step taller — both
             are visible changes we deliberately declined. Do NOT "dedupe" this into
             the indicator; the compact custom pill is the choice.

             (No literal Tailwind class names in this comment on purpose — Tailwind's
             @source scans comments as text and would compile any class named here.) --}}
        <span
            x-show="unreadCount > 0"
            x-cloak
            aria-hidden="true"
            class="absolute -top-0.5 -right-0.5 min-w-[1rem] h-4 px-1 inline-flex items-center justify-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-inverse)] bg-[var(--color-wk-danger)] rounded-[var(--radius-wk-full)]"
        ><span x-text="unreadCount > 99 ? '99+' : unreadCount">{{ $serverUnread }}</span></span>
    </button>

    {{-- Panel — teleported to <body> + Floating-UI positioned (see
         wirekitNotificationCenter._anchor) so it escapes any clipping/stacking
         ancestor and opens toward the inline-end (rightward) into available space,
         shifting back on-screen when the edge would overflow. click.outside lives
         HERE (on the panel), not the root, because teleporting moves the panel out
         of the root's subtree. Near-full-width on small screens. --}}
    <template x-teleport="body">
    <div
        x-show="open"
        x-cloak
        x-ref="panel"
        tabindex="-1"
        x-transition.origin.top.left
        x-on:click.outside="close()"
        role="dialog"
        aria-labelledby="{{ $titleId }}"
        class="fixed z-[var(--z-wk-dropdown)] w-[22rem] max-w-[calc(100vw-2rem)] bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] shadow-[var(--shadow-wk-lg)] focus-visible:outline-none overflow-hidden"
    >
        {{-- Header --}}
        <div class="flex items-center justify-between gap-2 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
            <p id="{{ $titleId }}" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ $title }}</p>
            <button
                type="button"
                x-show="unreadCount > 0"
                x-cloak
                @click="markAllRead()"
                {{-- Ghost button (subtle hover surface), not a hover-underline
                     text-link — matches WireKit's other in-panel actions
                     (e.g. data-table "Clear", filter-builder "Clear all"). --}}
                class="px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-accent-text)] rounded-[var(--radius-wk-md)] hover:bg-[var(--color-wk-bg-muted)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer transition-colors"
            >Mark all read</button>
        </div>

        @if($filters)
            {{-- Type filter — a single-select RADIOGROUP, not tabs: the buttons
                 filter one list in place and own no tabpanels, so radio semantics
                 (aria-checked + roving tabindex + arrows move-and-select, wrapping)
                 are the honest contract. --}}
            <div x-show="types.length > 0" x-cloak role="radiogroup" aria-label="{{ __('Filter notifications') }}"
                @keydown.arrow-right.prevent="filterMove(1)"
                @keydown.arrow-down.prevent="filterMove(1)"
                @keydown.arrow-left.prevent="filterMove(-1)"
                @keydown.arrow-up.prevent="filterMove(-1)"
                class="flex flex-wrap items-center gap-1 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)]">
                <button type="button" role="radio" data-filter="all" @click="setFilter('all')" :aria-checked="activeFilter === 'all'" :tabindex="activeFilter === 'all' ? 0 : -1" :class="activeFilter === 'all' ? 'bg-[var(--color-wk-bg-inverse)] text-[color:var(--color-wk-text-inverse)]' : 'text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-muted)]'" class="{{ $tab }}">All</button>
                <template x-for="t in types" :key="t">
                    <button type="button" role="radio" :data-filter="t" @click="setFilter(t)" :aria-checked="activeFilter === t" :tabindex="activeFilter === t ? 0 : -1" x-text="t" :class="activeFilter === t ? 'bg-[var(--color-wk-bg-inverse)] text-[color:var(--color-wk-text-inverse)]' : 'text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-muted)]'" class="{{ $tab }} capitalize"></button>
                </template>
            </div>
        @endif

        {{-- List — a labeled, keyboard-reachable scroll region (WCAG 2.1.1). --}}
        <div role="region" aria-label="{{ $title }} list" tabindex="0" class="max-h-[24rem] overflow-y-auto wk-scrollbar focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]">
            {{-- Empty state --}}
            <div x-show="isEmpty" x-cloak class="flex flex-col items-center justify-center gap-2 px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-xl)] text-center">
                <svg aria-hidden="true" class="h-8 w-8 text-[color:var(--color-wk-text-subtle)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg>
                <p class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyText }}</p>
            </div>

            {{-- Grouped notification list --}}
            <template x-for="group in groups" :key="group.label ?? 'all'">
                <div>
                    <p x-show="group.label" x-cloak class="sticky top-0 px-[var(--padding-wk-x-md)] py-1 text-[length:var(--text-wk-xs)] uppercase tracking-wide font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-subtle)] bg-[var(--color-wk-bg-elevated)]" x-text="group.label"></p>
                    <template x-for="item in group.items" :key="item.id">
                        {{-- One interactive element per row. Items WITH href render as a
                             real <a> (native navigation + middle-click work) and items
                             without one as a <button>; BOTH route through activate(item),
                             which marks the row read and dispatches the bubbling
                             `notification-action` event ({id, href}) the developer
                             listens for. actionLabel renders as the row's call-to-action
                             line — never a nested control (button-in-button is invalid). --}}
                        <div class="contents">
                            <template x-if="item.href">
                                <a
                                    :href="item.href"
                                    @click="activate(item)"
                                    :aria-label="(item.read ? '' : 'Unread. ') + item.title + (item.actionLabel ? ', ' + item.actionLabel : '')"
                                    class="{{ $row }}"
                                >
                                    {{-- Unread dot — paired with the "Unread." prefix in aria-label (not color alone). --}}
                                    <span class="mt-1.5 shrink-0 h-2 w-2 rounded-full" :class="item.read ? 'bg-transparent' : 'bg-[var(--color-wk-accent)]'"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block leading-snug text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]" :class="item.read ? '' : 'font-[number:var(--font-wk-heading-weight)]'" x-text="item.title"></span>
                                        <span x-show="item.body" x-cloak class="block leading-snug text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="item.body"></span>
                                        <span x-show="item.timeLabel" x-cloak class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)]" x-text="item.timeLabel"></span>
                                        <span x-show="item.actionLabel" x-cloak class="mt-0.5 block text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-accent-text)]" x-text="item.actionLabel"></span>
                                    </span>
                                </a>
                            </template>
                            <template x-if="!item.href">
                                <button
                                    type="button"
                                    @click="activate(item)"
                                    :aria-label="(item.read ? '' : 'Unread. ') + item.title + (item.actionLabel ? ', ' + item.actionLabel : '')"
                                    class="{{ $row }}"
                                >
                                    {{-- Unread dot — paired with the "Unread." prefix in aria-label (not color alone). --}}
                                    <span class="mt-1.5 shrink-0 h-2 w-2 rounded-full" :class="item.read ? 'bg-transparent' : 'bg-[var(--color-wk-accent)]'"></span>
                                    <span class="min-w-0 flex-1">
                                        {{-- leading-snug tightens the title's line-box so the
                                             time/body sit closer to the name (the default
                                             line-height left too generous a vertical gap). --}}
                                        <span class="block leading-snug text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]" :class="item.read ? '' : 'font-[number:var(--font-wk-heading-weight)]'" x-text="item.title"></span>
                                        <span x-show="item.body" x-cloak class="block leading-snug text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]" x-text="item.body"></span>
                                        <span x-show="item.timeLabel" x-cloak class="block text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-subtle)]" x-text="item.timeLabel"></span>
                                        <span x-show="item.actionLabel" x-cloak class="mt-0.5 block text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-accent-text)]" x-text="item.actionLabel"></span>
                                    </span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        @if($seeAllHref)
            {{-- Footer — ghost action (subtle full-width hover surface), NOT a
                 hover-underline text-link, matching the "Mark all read" header
                 action and WireKit's in-panel-action standard. --}}
            <a href="{{ $seeAllHref }}" class="block px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-center text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-accent-text)] hover:bg-[var(--color-wk-bg-muted)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-inset transition-colors">{{ $seeAllLabel }}</a>
        @endif
    </div>
    </template>
</div>
