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
        {{-- The control says what it does on hover, not only to a screen reader.
             Reported for every view that has one: the chevron alone is a guess until
             you click it, and the name that answers the question was already here in
             `aria-label` — reachable by assistive technology and by nobody else.

             `focusable-trigger="false"` because the button is already a tab stop; the
             tooltip's default would add a second one in front of it. Its own prop
             documentation names exactly this case.

             The text is bound rather than passed, because it changes with the state
             and `text` renders once. The binding resolves against the column's own
             Alpine scope, which this partial already reads for `:aria-expanded`. --}}
        <x-wirekit::tooltip :focusable-trigger="false">
            <x-slot:content>
                <span x-text="collapsed
                    ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Expand sidebar')) }}
                    : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Collapse sidebar')) }}">{{ $collapsed ? __('wirekit::Expand sidebar') : __('wirekit::Collapse sidebar') }}</span>
            </x-slot:content>
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
            {{-- A stable hook for the control that folds the column. Everything else on this
                 button is either translatable (`aria-label`) or shared with other widgets
                 (`aria-expanded` is on every collapsible group too), so a test or an
                 application reaching for THIS control had to guess — "the last button in the
                 column" was the guess, and it holds on three previews out of twenty. --}}
            data-wk-sidebar-collapse
            aria-expanded="{{ $collapsed ? 'false' : 'true' }}"
            aria-label="{{ $collapsed ? __('wirekit::Expand sidebar') : __('wirekit::Collapse sidebar') }}"
            :aria-expanded="collapsed ? 'false' : 'true'"
            :aria-label="collapsed ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Expand sidebar')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Collapse sidebar')) }}"
            class="{{ $collapseBtnClasses }}"
        >
            {{-- A panel rather than a double chevron: the app rail's toggle draws the double chevron,
                 and in one shell the two stood at the foot of neighboring columns reading as two
                 versions of one button while they fold different things. The frame is the column,
                 the rule inside it stands on the column's own side, and only the arrow turns. It
                 points the way the column is about to move, so on the trailing side the two states
                 swap, and the edge stays where the column is in both. --}}
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h12A2.25 2.25 0 0 1 20.25 6v12A2.25 2.25 0 0 1 18 20.25H6A2.25 2.25 0 0 1 3.75 18V6Z" />
                <path stroke-linecap="round" d="{{ $collapseGlyphEdge }}" />
                <path stroke-linecap="round" stroke-linejoin="round" class="origin-center [transform-box:fill-box] transition-transform duration-[var(--transition-wk-duration)]" :class="{{ $chevronFlip }}" d="{{ $collapseGlyphArrow }}" />
            </svg>
        </button>
        </x-wirekit::tooltip>
