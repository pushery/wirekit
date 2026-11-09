{{-- optimistic-ui: n/a — presentational
     The read state at the start of one notification row; it holds no value a server could accept
     or refuse. --}}
{{-- Drawn the same in the link row and the button row. An unread notification shows a dot in the
     accent, with "Unread." for a screen reader, so the state is never color alone. A read one shows
     a check in the muted text color, with "Read.", unless the center was asked for no read marker:
     then a read row keeps an empty place the size of the dot, so its title starts where an
     unread row's does.

     With the marker, both marks stand in one box the size of the check, so a title starts at the
     same place whether its row is read or not. The forced-colors rule reads `wk-unread-dot` on the
     unread dot and keeps it filled.

     Reads `$readMarker` from the including scope. --}}
@if($readMarker)
    <span class="sr-only" x-show="item.read" x-cloak>{{ __('wirekit::Read.') }}</span>
    <span class="sr-only" x-show="!item.read" x-cloak>{{ __('wirekit::Unread.') }}</span>
    <span class="mt-[var(--space-wk-xs)] shrink-0 inline-flex h-3 w-3 items-center justify-center" aria-hidden="true">
        <span x-show="!item.read" class="h-2 w-2 rounded-full wk-unread-dot bg-[var(--color-wk-accent)]"></span>
        <svg data-wk-read-marker x-show="item.read" x-cloak class="h-3 w-3 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
        </svg>
    </span>
@else
    <span class="sr-only" x-show="!item.read" x-cloak>{{ __('wirekit::Unread.') }}</span>
    <span class="mt-1.5 shrink-0 h-2 w-2 rounded-full" :class="item.read ? 'bg-transparent' : 'wk-unread-dot bg-[var(--color-wk-accent)]'"></span>
@endif
