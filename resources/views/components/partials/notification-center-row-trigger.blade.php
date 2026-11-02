{{-- optimistic-ui: n/a — client-only
     It is the row trigger of `notification-center.blade.php`, which opens the panel in the
     browser and reaches no server. --}}
{{-- The row trigger: the bell, the title and the unread count as an entry of a navigation, for a
     center among the entries of a sidebar. Extracted so that a tooltip can wrap it in a sidebar
     that collapses to an icon rail, as the row of a sidebar item is wrapped.

     The name is the bell's, so the visible title begins it: "Notifications, 3 unread". The count
     is drawn as a sidebar item draws its badge and is decorative here, since the name carries it.
     In a collapsed rail the title goes, the icon takes the center and the count becomes a dot, as
     on a sidebar item.

     Inside the tooltip, `x-wk-ref` files the bell on the center's root rather than on the
     tooltip's, so the panel still finds its anchor and gives the focus back to it
     (resources/js/utils/caller-ref.js). `aria-expanded` reads `panelOpen`, since an expression
     here reads the nearest `isOpen`, and the tooltip has one of its own. --}}
<button
    type="button"
    @if($bellInTip) x-wk-ref="bell" @else x-ref="bell" @endif
    @click="toggle()"
    aria-expanded="false"
    :aria-expanded="panelOpen"
    aria-haspopup="dialog"
    aria-label="{{ \Pushery\WireKit\Support\SlotContent::textOf($titleResolved) }}"
    :aria-label="unreadCount > 0 ? bellName() : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit:::title, none unread', ['title' => \Pushery\WireKit\Support\SlotContent::textOf($titleResolved)])) }}"
    data-wk-notification-trigger-row
    class="wk-touch-row {{ $rowTriggerClasses }}"
>
    <span class="shrink-0" data-wk-icon-nudge aria-hidden="true"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg></span>
    {{-- Wraps rather than truncating, as a sidebar item's label does. --}}
    <span class="min-w-0 flex-1 break-words text-start wk-rail-hide">{{ $titleResolved }}</span>
    <span
        x-show="unreadCount > 0"
        x-cloak
        aria-hidden="true"
        class="shrink-0 ms-auto inline-flex items-center justify-center px-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-full)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]
        group-data-[collapsed]/wk-sidebar:absolute
        group-data-[collapsed]/wk-sidebar:top-[var(--padding-wk-y-sm)]
        group-data-[collapsed]/wk-sidebar:end-[var(--padding-wk-x-sm)]
        group-data-[collapsed]/wk-sidebar:ms-0
        group-data-[collapsed]/wk-sidebar:p-0
        group-data-[collapsed]/wk-sidebar:h-2
        group-data-[collapsed]/wk-sidebar:w-2"
    ><span class="wk-rail-hide" x-text="unreadCount > 99 ? '99+' : unreadCount">{{ $serverUnread }}</span></span>
</button>
