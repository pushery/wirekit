{{-- optimistic-ui: n/a — navigation
     Choosing a scope is a page change, not a value a server accepts or refuses. There is
     nothing to anticipate: the new page either arrives or it does not. --}}
@props([
    // Stable id base. REQUIRED, and not by convention: every option's id is built from it,
    // `aria-activedescendant` points at those ids, and a Livewire morph that regenerates
    // them silently breaks the link between the input and the row it claims is active.
    'name' => null,
    // What is being switched — "Server", "Team". Names the control, the dialog and the
    // search field, and stands in as the trigger's text when nothing is selected yet.
    'label' => '',
    /** @var list<array<string, mixed>> */
    'items' => [],
    // `key` of the item the page is currently showing, or null.
    'current' => null,
    // ['label' => …, 'url' => …, 'icon' => …] — the footer action. Omit for no footer.
    'create' => null,
    'searchPlaceholder' => null,
    'emptyText' => null,
    'placement' => null,
    'width' => null,
    'listMaxHeight' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\WireKit;

    WireKit::warnUnknownProps('scope-switcher', $attributes->getAttributes());

    if (! is_string($name) || $name === '') {
        throw new InvalidArgumentException(
            'wirekit::scope-switcher requires a `name`. Every option id is built from it, and '
            .'aria-activedescendant points at those ids — without a stable name a Livewire '
            .'morph silently severs the link between the search field and the row it says is active.'
        );
    }

    $placement = $placement ?? config('wirekit.components.scope-switcher.placement', 'bottom-start');
    $width = $width ?? config('wirekit.components.scope-switcher.width', '20rem');
    $listMaxHeight = $listMaxHeight ?? config('wirekit.components.scope-switcher.list_max_height', '22rem');
    $offset = (int) config('wirekit.components.scope-switcher.offset', 8);
    $prefetch = BooleanProp::from(config('wirekit.components.scope-switcher.prefetch_on_hover', true), true);

    $id = DomId::unique($name, 'scope-switcher-');

    $labelText = $label !== '' ? $label : __('wirekit::Scope');
    $dialogLabel = __('wirekit::Switch :label', ['label' => $labelText]);
    $searchLabel = $searchPlaceholder ?? __('wirekit::Search :label…', ['label' => $labelText]);
    $emptyLabel = $emptyText ?? __('wirekit::No results.');

    // Fold accents ONCE, on the server, so a reader typing `munchen` finds `München`.
    // Per keystroke in the browser this would be the same work repeated for every character
    // typed, over every row.
    //
    // NFD then strip the combining marks — the same two steps the Alpine side takes, which
    // is what makes the two agree. Decomposition splits a letter into its base plus its
    // marks; dropping the marks leaves the base. The alternative, a table of replacements,
    // is a list that is wrong for the next language somebody uses.
    //
    // NOT iconv//TRANSLIT: it is platform-dependent and wrong here. On this machine it
    // renders `München` as `M"unchen`, inserting a quote where the umlaut was — so the
    // search text would contain a character nobody will ever type.
    //
    // `ext-intl` is not in this package's require list, so its absence degrades rather than
    // fails: the text stays as written and an accented row is then found by typing the
    // accent. Everything else about the component still works.
    $normalize = static function (string $value) : string {
        if (class_exists(\Normalizer::class)) {
            $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D);

            if (is_string($decomposed)) {
                $value = (string) preg_replace('/\p{M}/u', '', $decomposed);
            }
        }

        return mb_strtolower(trim($value));
    };

    $seen = [];
    $rows = [];

    foreach ($items as $item) {
        if (! is_array($item) || ! isset($item['key'], $item['label'], $item['url'])) {
            throw new InvalidArgumentException(
                'wirekit::scope-switcher: every item needs at least `key`, `label` and `url`.'
            );
        }

        $key = (string) $item['key'];

        if (isset($seen[$key])) {
            throw new InvalidArgumentException(
                "wirekit::scope-switcher: duplicate item key \"{$key}\". Keys become DOM ids and "
                .'aria-activedescendant targets, so two rows sharing one make the active row ambiguous.'
            );
        }

        $seen[$key] = true;

        $status = $item['status'] ?? null;

        if ($status !== null && ! in_array($status, ['success', 'warning', 'danger', 'neutral'], true)) {
            throw new InvalidArgumentException(
                "wirekit::scope-switcher: unknown status \"{$status}\" on item \"{$key}\". "
                .'Expected one of: success, warning, danger, neutral.'
            );
        }

        $rows[] = [
            'key' => $key,
            'label' => (string) $item['label'],
            'url' => (string) $item['url'],
            'icon' => $item['icon'] ?? null,
            'image' => $item['image'] ?? null,
            'status' => $status,
            'meta' => $item['meta'] ?? null,
            'group' => $item['group'] ?? null,
            'search' => $normalize(implode(' ', array_filter([
                (string) $item['label'],
                (string) ($item['meta'] ?? ''),
                implode(' ', (array) ($item['keywords'] ?? [])),
            ]))),
        ];
    }

    // A count nobody set out to reach. Shipping every row to filter a handful of them is
    // work that grows with the list while the reader's need does not.
    $clientMax = (int) config('wirekit.components.scope-switcher.client_filter_max', 300);

    if (count($rows) > $clientMax && function_exists('app') && app()->hasDebugModeEnabled()) {
        logger()->debug(sprintf(
            'WireKit scope-switcher "%s" renders %d items and filters them in the browser. Past '
            .'roughly %d that means shipping the whole list to search a few of them.',
            $name,
            count($rows),
            $clientMax
        ));
    }

    // Preserve the server's order inside each group, and the order the groups first appear.
    $grouped = [];

    foreach ($rows as $row) {
        $grouped[$row['group'] ?? ''][] = $row;
    }

    $currentRow = null;

    foreach ($rows as $row) {
        if ($current !== null && $row['key'] === (string) $current) {
            $currentRow = $row;
        }
    }

    // The four states a scope can be in, as a fill and as a word. Both maps are keyed by
    // the same validated set, so a status that passed the check above always resolves —
    // there is no fallback branch to keep in step.
    $statusDotClasses = [
        'success' => 'bg-[var(--color-wk-success)]',
        'warning' => 'bg-[var(--color-wk-warning)]',
        'danger' => 'bg-[var(--color-wk-danger)]',
        'neutral' => 'bg-[var(--color-wk-text-muted)]',
    ];

    $statusLabels = [
        'success' => __('wirekit::Healthy'),
        'warning' => __('wirekit::Needs attention'),
        'danger' => __('wirekit::Failing'),
        'neutral' => __('wirekit::Inactive'),
    ];

    $itemClasses = WireKit::resolveClasses('scope-switcher', 'item', implode(' ', [
        'flex w-full items-center gap-[var(--gap-wk-sm)]',
        'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]',
        'no-underline cursor-pointer',
        'hover:bg-[var(--color-wk-bg-subtle)]',
        // The keyboard's position is its own state, published by the input through
        // aria-activedescendant. It is deliberately NOT aria-selected — that says "this is
        // the chosen one", which here means the scope the page is already showing.
        'data-[active]:bg-[var(--color-wk-bg-muted)]',
    ]), $scope);
@endphp

<x-wirekit::popover
    :placement="$placement"
    :offset="$offset"
    :label="$dialogLabel"
    :padded="false"
    :scope="$scope"
    {{ $attributes->except(['class']) }}
    data-wk-scope-switcher
>
    <x-slot:trigger>
        {{-- Named by its own two children rather than by an aria-label, and the order of the
             IDREFs is the order the name is read in: the current scope first, its purpose
             second. An aria-label sat here and REPLACED the visible text, so the button was
             called "Switch Server" while the word on it said "Production" — a voice-control
             user saying "click Production" matched nothing on the page (WCAG 2.5.3 Label in
             Name), and a screen-reader user was never told which scope they were in. The
             reference also keeps the name honest for free: `_showChosen` rewrites the visible
             text the moment a row is picked, and a name assembled from that element follows
             it, where a copy baked into an attribute would have gone stale on the same click. --}}
        <x-wirekit::button
            intent="neutral"
            surface="ghost"
            size="sm"
            :aria-labelledby="$id.'-current-label '.$id.'-switch-purpose'"
        >
            @if($currentRow && $currentRow['image'])
                <img src="{{ $currentRow['image'] }}" alt="" class="h-4 w-4 shrink-0 rounded-[var(--radius-wk-sm)] object-cover" />
            @elseif($currentRow && $currentRow['icon'])
                <x-wirekit::icon :name="$currentRow['icon']" class="h-4 w-4 shrink-0" />
            @endif

            {{-- The id is how the LIST reaches this text. The panel is teleported to the
                 overlay root, so it is not a descendant of this button in the DOM and shares
                 no Alpine scope with it; an id is the one handle that survives both. --}}
            <span
                id="{{ $id }}-current-label"
                @class([
                    'truncate max-w-[12rem]',
                    'text-[color:var(--color-wk-text-muted)]' => $currentRow === null,
                ])
            >{{ $currentRow['label'] ?? $labelText }}</span>

            {{-- The second half of the button's name, and the only reason it is a rendered
                 element instead of an attribute: a name assembled from IDREFs can only cite
                 nodes. Hidden from sight because the purpose is already obvious to anyone who
                 can see the stacked chevrons beside it. --}}
            <span id="{{ $id }}-switch-purpose" class="sr-only">{{ $dialogLabel }}</span>

            {{-- The pop-up-button marker: a stacked pair of chevrons says "this shows the
                 current choice and there are others", where a single downward one would say
                 "this opens a list of actions". Decorative — the button is already named. --}}
            <x-wirekit::icon name="chevron-up-down" class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" aria-hidden="true" />
        </x-wirekit::button>
    </x-slot:trigger>

    {{-- The state lives HERE, on the panel, and not on the popover wrapper.
         The wrapper already carries `x-data="wirekitPopover(…)"`, and an element has exactly
         one Alpine scope — a second x-data passed through the attribute bag does not merge
         with the first, it is simply never applied. The component then appears to render
         perfectly and does nothing at all: no filtering, no active row, and no errors. --}}
    <div
        class="flex flex-col"
        style="width: {{ $width }}; max-width: calc(100vw - 2rem);"
        x-data="wirekitScopeSwitcher({ idPrefix: {{ \Pushery\WireKit\Support\AlpinePayload::string($id) }} })"
        {{-- Reset from the popover's own state, not from an event.
             The overlay event vocabulary has show/close pairs for modal, drawer,
             alert-dialog and toast — the popover is not among them, so there is no
             close event to listen for. `open` belongs to the popover's scope and is
             visible here through Alpine's scope inheritance, which survives the
             teleport, so watching it is both simpler and true whatever closed the
             panel: Escape, a click outside, or choosing a row. --}}
        x-effect="syncOpen(open)"
    >
        {{-- Header. First focusable element in the panel, so the popover's focus trap lands
             here on open and a reader can type immediately. --}}
        <div class="flex items-center gap-[var(--gap-wk-sm)] border-b-[length:var(--border-wk-width)] border-[var(--color-wk-border)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)]">
            <x-wirekit::icon name="search" class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" aria-hidden="true" />

            <input
                type="text"
                role="combobox"
                x-ref="search"
                x-model="query"
                x-on:input="filter()"
                {{-- preventDefault, or the caret jumps to the ends of the query while the
                     list moves — the two would be steering the same key. --}}
                x-on:keydown.down.prevent="moveActive(1)"
                x-on:keydown.up.prevent="moveActive(-1)"
                x-on:keydown.home.prevent="moveEdge(false)"
                x-on:keydown.end.prevent="moveEdge(true)"
                x-on:keydown.enter.prevent="activateActive()"
                aria-autocomplete="list"
                aria-expanded="true"
                aria-controls="{{ $id }}-listbox"
                x-bind:aria-activedescendant="activeId()"
                aria-label="{{ $searchLabel }}"
                placeholder="{{ $searchLabel }}"
                autocomplete="off"
                spellcheck="false"
                autocapitalize="off"
                class="wk-field w-full border-0 bg-transparent p-0 text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] placeholder:text-[color:var(--color-wk-text-muted)] focus:outline-none focus:ring-0"
            />
        </div>

        {{-- Body. `overscroll-contain` stops a flick at the end of this list from scrolling
             the page behind the panel.

             `relative` is the containing block for the two edge shadows below. The list
             caps at `list-max-height` and clips silently, so a long scope list read as a
             complete list that happened to end — the same problem the sidebar solved, and
             the same two CSS classes are reused so the affordance is identical rather than
             merely similar. --}}
        <div class="relative">
        <div
            x-ref="list"
            role="listbox"
            id="{{ $id }}-listbox"
            aria-label="{{ $labelText }}"
            class="wk-scrollbar overflow-y-auto py-[var(--padding-wk-y-sm)]"
            style="max-height: {{ $listMaxHeight }}; overscroll-behavior: contain;"
        >
            {{-- The observer needs a target with real height — a zero-height element is not
                 dependably "intersecting" — and that pixel is real layout. The negative
                 margin gives the observer its pixel and the reader none. --}}
            <div x-ref="topSentinel" aria-hidden="true" class="h-px -mb-px"></div>
            @foreach($grouped as $groupName => $groupRows)
                @php $groupId = $id.'-group-'.\Illuminate\Support\Str::slug((string) $groupName ?: 'ungrouped'); @endphp

                @if($groupName !== '')
                    <div role="group" aria-labelledby="{{ $groupId }}">
                        <div id="{{ $groupId }}" class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)]">{{ $groupName }}</div>
                @endif

                @foreach($groupRows as $row)
                    @php $isCurrent = $currentRow !== null && $row['key'] === $currentRow['key']; @endphp

                    <a
                        href="{{ $row['url'] }}"
                        role="option"
                        id="{{ $id }}-option-{{ $row['key'] }}"
                        wire:key="{{ $id }}-{{ $row['key'] }}"
                        @if($prefetch) wire:navigate.hover @else wire:navigate @endif
                        data-key="{{ $row['key'] }}"
                        data-search="{{ $row['search'] }}"
                        {{-- Present ONLY on the current row, never as `false` elsewhere. A
                             single-select listbox needs `aria-selected="true"` on the chosen
                             option and nothing on the others; spelling out `false` is valid
                             and makes NVDA and JAWS say "not selected" on every row a reader
                             arrows past, which is noise on the one control whose whole job is
                             to say which entry is the current one. --}}
                        @if($isCurrent) aria-selected="true" @endif
                        tabindex="-1"
                        {{-- pointermove, not mouseenter: scrolling the list under a resting
                             cursor fires mouseenter on every row that passes beneath it, and
                             the highlight would chase the scroll instead of the reader. --}}
                        x-on:pointermove="setActive({{ \Pushery\WireKit\Support\AlpinePayload::string($row['key']) }}, false)"
                        data-label="{{ $row['label'] }}"
                        x-on:click="onItemClick($event, {{ $isCurrent ? 'true' : 'false' }})"
                        class="{{ $itemClasses }}"
                    >
                        @if($row['image'])
                            <img src="{{ $row['image'] }}" alt="" class="h-5 w-5 shrink-0 rounded-[var(--radius-wk-sm)] object-cover" />
                        @elseif($row['icon'])
                            @if($row['status'])
                                <x-wirekit::indicator position="bottom-end" class="shrink-0">
                                    <x-wirekit::icon :name="$row['icon']" class="h-5 w-5" />
                                    {{-- A status here is a state, not a count, so the badge slot
                                         holds a dot rather than a number. The dot is decorative on
                                         purpose: the same word is in the row's text below it, which
                                         is what a screen reader reads. --}}
                                    <x-slot:badge>
                                        <span aria-hidden="true" class="block h-2 w-2 rounded-[var(--radius-wk-full)] {{ $statusDotClasses[$row['status']] }}"></span>
                                    </x-slot:badge>
                                </x-wirekit::indicator>
                            @else
                                <x-wirekit::icon :name="$row['icon']" class="h-5 w-5 shrink-0" />
                            @endif
                        @endif

                        <span class="truncate">{{ $row['label'] }}</span>

                        @if($row['status'])
                            <x-wirekit::visually-hidden>{{ $statusLabels[$row['status']] }}</x-wirekit::visually-hidden>
                        @endif

                        @if($row['meta'])
                            <span class="ms-auto shrink-0 text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $row['meta'] }}</span>
                        @endif

                        @if($isCurrent)
                            <x-wirekit::icon name="check" @class([
                                'h-4 w-4 shrink-0 text-[color:var(--color-wk-accent-text)]',
                                'ms-auto' => ! $row['meta'],
                            ]) aria-hidden="true" />
                        @endif
                    </a>
                @endforeach

                @if($groupName !== '')
                    </div>
                @endif
            @endforeach

            <div x-show="visibleCount === 0" x-cloak class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $emptyLabel }}</div>

            <div x-ref="bottomSentinel" aria-hidden="true" class="h-px -mt-px"></div>
        </div>

        {{-- Decorative, and it must stay that way: the shadow says "there is more this
             way", which the scrollbar and the keyboard already say to anyone not looking
             at it. Same two classes the sidebar uses, so the two surfaces cannot drift
             into looking almost-alike. --}}
        <div aria-hidden="true" x-cloak x-show="topShadow" x-transition.opacity class="wk-scroll-shadow-top"></div>
        <div aria-hidden="true" x-cloak x-show="bottomShadow" x-transition.opacity class="wk-scroll-shadow-bottom"></div>
        </div>

        {{-- The count, for a reader who cannot see the list shrink. Throttled in the plugin
             so a fast typist is not read out letter by letter. --}}
        <x-wirekit::visually-hidden role="status" aria-live="polite">
            <span x-text="announcement === 1 ? {{ \Pushery\WireKit\Support\AlpinePayload::string(__('wirekit::1 result')) }} : announcement + {{ \Pushery\WireKit\Support\AlpinePayload::string(__('wirekit::results')) }}"></span>
        </x-wirekit::visually-hidden>

        @if($create)
            {{-- Outside the listbox on purpose. It is an action, not one of the things being
                 chosen between, so it must not be an `option` — it is reached with Tab, and
                 the arrow keys stay in the list. --}}
            <div class="border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] py-[var(--padding-wk-y-xs)]">
                {{-- A ROW, not a button, and the difference is visible.
                     This was `<x-wirekit::button surface="ghost">`, whose hover surface is
                     the same token the rows use — but a button brings its own geometry with
                     it: `px-sm` against the rows' `px-md`, a corner radius where the rows are
                     square, a smaller type size and a fixed height. The tint on hover
                     therefore landed on a shorter, narrower, rounded band that lined up with
                     nothing above it, which reads as the wrong color even though the color
                     was right. It is the last row of the same list and is now shaped like one.
                     The accent stays on the text, because this is an action and the rows
                     are not. --}}
                <a
                    href="{{ $create['url'] }}"
                    wire:navigate
                    class="{{ $itemClasses }} text-[color:var(--color-wk-accent-content)]"
                >
                    <x-wirekit::icon :name="$create['icon'] ?? 'plus'" class="h-5 w-5 shrink-0" aria-hidden="true" />
                    {{ $create['label'] }}
                </a>
            </div>
        @endif
    </div>
</x-wirekit::popover>
