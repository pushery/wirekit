{{-- optimistic-ui: n/a — client-only
     The click runs `toggle()` in Alpine and nothing else; the collapsed state never
     leaves the browser, so there is no server answer for an optimistic update to
     anticipate. Same claim as `sidebar.blade.php`, which is where this button used
     to live. --}}
{{-- The collapse control, extracted so it can be rendered in two places.

     With a footer zone it rides ON that band; without one it is the column's last row.
     Two call sites, one button — the alternative was eighty lines of markup duplicated,
     which is how two spellings of the same control drift apart.

     Reads `$collapsed` and `$collapseBtnClasses` from the including scope, the same way
     `sidebar-zones` reads `$header` and `$footer`. --}}
        <button
            type="button"
            x-on:click="toggle()"
            {{-- Emitted STATICALLY as well as bound, for the same reason `data-collapsed`
                 above is. Until Alpine boots, `:aria-label` has not run — so the only
                 content of this button is a decorative <svg>, and it reaches a screen
                 reader as a bare "button". A server-side accessibility check never gets
                 past that point at all, so for one it is nameless permanently.
                 Alpine owns both attributes after init and rewrites them on every toggle,
                 so the static pair can never disagree with the bound one. Same __() keys,
                 so the translation is maintained once. --}}
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-label="{{ $collapsed ? __('wirekit::Expand sidebar') : __('wirekit::Collapse sidebar') }}"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :aria-label="collapsed ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Expand sidebar')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Collapse sidebar')) }}"
            class="{{ $collapseBtnClasses }}"
        >
            {{-- The chevron points the way the column is about to move, so on the trailing
                 side the two states swap: a panel there closes to the RIGHT, and the arrow
                 that means "collapse" on a leading column means "expand" on this one. --}}
            <svg class="h-5 w-5 transition-transform duration-[var(--transition-wk-duration)]" :class="{{ $chevronFlip }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 4.5 11.25 12l7.5 7.5m-7.5-15L3.75 12l7.5 7.5" />
            </svg>
        </button>
