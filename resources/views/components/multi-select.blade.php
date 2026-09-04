{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the pill appears (or disappears) the moment
     an option is clicked. A selection is a discrete set of server values, so an
     undo puts back what the server has and destroys nothing the user typed —
     the filter text is separate state and is never rolled back. The optimistic
     scope nests INSIDE this component and binds to `selected`. --}}
@props([
    // Livewire method to call optimistically. It receives the FULL new
    // selection as an array. The pill appears immediately and is removed again
    // if the call fails. Absent -> this component renders exactly as before.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    'error' => null,
    'options' => [],
    'value' => [],          // option keys to pre-select on load (array or comma-separated string)
    'placeholder' => __('wirekit::Select...'),
    'scope' => null,
    'ariaLabel' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('multi-select', $attributes->getAttributes());

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'multi-select-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);

    // `id` and `name` are consumed above and re-emitted where they belong -- the
    // internal combobox input and the hidden inputs. Leaving them in the bag would
    // put a `name` on a <div>, which is not a form control and carries nothing.
    $attributes = $attributes->except(['id', 'name']);
    // When a parent <x-wirekit::field label="..."> wraps this component, the
    // field-emitted <label for="$id"> doesn't reach the internal combobox
    // <input id="$id-input">, so screen readers + axe's label rule report
    // an unlabeled form element. We synthesize an aria-label fallback —
    // explicit `ariaLabel` prop wins, then the field's `label` prop (passed
    // down via attributes scan), then the `name`/`placeholder` as last resort.
    $resolvedAriaLabel = $ariaLabel ?? $attributes->get('aria-label') ?? $label ?? $placeholder ?? $name;

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Container classes — styled like an input field, wraps pills + filter input.
    // py-y-sm (0.375rem ≈ 6px) for visually balanced top/bottom padding around
    // the wrapped pills — py-1 (4px) reads as too tight against the
    // px-x-md (12px) horizontal padding on the sides.
    $containerClasses = WireKit::resolveClasses('multi-select', 'base', implode(' ', [
        'flex flex-wrap items-center gap-1',
        'min-h-[var(--size-wk-md)]',
        'p-[var(--padding-wk-y-sm)]',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-input)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
        'cursor-text',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border-strong)]';

    // Pill classes for selected values — py-1 for balanced vertical padding
    $pillClasses = implode(' ', [
        'inline-flex items-center gap-1',
        'pl-[var(--padding-wk-x-sm)] pr-1 py-1',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
    ]);

    // Dropdown option classes
    /*
     * The two appearance branches of an option row, resolved HERE rather than
     * written into the runtime binding below.
     *
     * A class string that only ever exists inside `:class="…"` is out of reach of
     * WireKit::scope(): resolveClasses runs at render time and can be overridden per
     * scope, an Alpine expression cannot. Same shape as segmented-control's selected /
     * unselected segments, for the same reason.
     */
    $optionHighlightedClasses = WireKit::resolveClasses('multi-select', 'option-highlighted', implode(' ', [
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    $optionSelectedClasses = WireKit::resolveClasses('multi-select', 'option-selected', implode(' ', [
        'font-[number:var(--font-wk-heading-weight)]',
    ]), $scope);

    $optionClasses = implode(' ', [
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
    ]);

    // Empty-state row. Shares the option row's sizing so "No results" scales
    // with the control like the options do, and drops the pointer affordances —
    // the hover tint and the pointer cursor both say "choosable", which this row
    // is not. Built as its own string rather than appended to $optionClasses:
    // two conflicting `cursor-*` utilities in one attribute are resolved by the
    // order they sit in the stylesheet, not the order they are written here.
    $emptyRowClasses = implode(' ', [
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
        'cursor-default',
    ]);

    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));

    // Encode options for Alpine — convert to array of {value, label} objects
    $encodedOptions = collect($options)->map(fn ($label, $optionValue) => [
        'value' => (string) $optionValue,
        'label' => (string) $label,
    ])->values()->all();

    // Normalize the `value` prop to an array of string option keys for
    // pre-selection. Accepts an array (['php', 'js']) or a comma-separated
    // string ('php,js') — mirrors the seeding contract of tags-input. The
    // resulting keys seed the Alpine `selected` array so the matching pills
    // render on load. (Framework-agnostic: works in plain Blade forms and as
    // the initial display alongside a two-way binding.)
    $selectedValues = is_array($value)
        ? array_values(array_map(fn ($v) => (string) $v, $value))
        : (is_string($value) && $value !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''))
            : []);
@endphp

@php
    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the options have to be inside it
    // to reach its `run()`.
    //
    // `after: '_afterToggle'` is the rest of what a pick does — clearing the
    // filter and restoring focus — and it runs on the rollback too, because
    // those are the same courtesy either way.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_afterToggle',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id . '-input'">{{ $label }}</x-wirekit::label>
    @endif

    {{-- `x-modelable` is what makes `wire:model` work here, and without it the control
         failed in the direction that looks like success: the pills confirmed the choice to
         the reader while the server never heard about it. A filter looked set and filtered
         nothing.

         The selection lives only in Alpine and reaches a classic form POST through hidden
         inputs, which is a different contract: `wire:model` on a non-input root listens for
         an `input` event from the subtree, and there was none to hear. `x-modelable` is the
         bridge Alpine provides for exactly this, so the array becomes bindable both to
         Livewire and to a plain `x-model` in an Alpine page. --}}
    <div
        {{ $attributes->class(['relative']) }}
        x-modelable="selected"
        x-data="wirekitMultiSelect({ options: {{ json_encode($encodedOptions) }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }}, value: {{ json_encode($selectedValues) }}, id: {{ \Pushery\WireKit\Support\AlpinePayload::string($id) }} })"
        @click.away="dropdownOpen = false"
        @keydown.escape="dropdownOpen = false"
    >
        @if($optimisticConfig)
            {{-- `display: contents` so the panel keeps `relative` above it as its
                 containing block — an extra box here would move the dropdown. --}}
            <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
        @endif

        {{-- Hidden inputs for form submission --}}
        <template x-for="(val, i) in selected" :key="i">
            <input type="hidden" :name="{{ \Pushery\WireKit\Support\AlpinePayload::string($name.'[]') }}" :value="val" />
        </template>

        {{-- Input container with pills --}}
        <div
            x-ref="field"
            class="{{ $containerClasses }} {{ $stateClasses }}"
            @click="focusAndOpen()"
        >
            {{-- Selected value pills --}}
            <template x-for="(val, i) in selected" :key="'pill-'+val">
                <span class="{{ $pillClasses }}">
                    <span x-text="getLabel(val)"></span>
                    <button
                        type="button"
                        {{-- run(nextWith(val)), not deselect(val): removing a pill is
                             the same server mutation as picking one, so it takes
                             the same path and is undone the same way. --}}
                        @click.stop="{{ $optimisticConfig ? 'run(nextWith(val))' : 'deselect(val)' }}"
                        :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Remove :name')) }}.replace(':name', getLabel(val))"
                        class="p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
                    >
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                    </button>
                </span>
            </template>

            {{-- Filter text input --}}
            <input
                type="text"
                id="{{ $id }}-input"
                x-ref="filterInput"
                x-model="filter"
                @focus="dropdownOpen = true"
                {{-- A fresh filter is a fresh list, so the old index means
                     nothing and the highlight restarts at the top. --}}
                @input="openAndReset()"
                @keydown.backspace="onBackspace($event)"
                {{-- The combobox keyboard model. Focus never leaves this input —
                     the options are `role="option"` with no tab stop — so these
                     keys plus `aria-activedescendant` below are the ONLY way a
                     keyboard reaches the list. Home/End move the highlight
                     rather than the caret, matching the sibling combobox; the
                     filter field holds a word or two, and jumping to the first
                     or last option is what the reader is here for.
                     Space is deliberately NOT bound: this is an editable
                     combobox, and a text field that swallows the space bar
                     cannot be typed into. --}}
                @keydown.arrow-down.prevent="openAndMove(1)"
                @keydown.arrow-up.prevent="openAndMove(-1)"
                @keydown.home.prevent="openAtFirst()"
                @keydown.end.prevent="openAtLast()"
                {{-- runIf, not run: Enter also fires with nothing highlighted
                     and on a list the user has closed, and `run(undefined)`
                     would ask the server for a selection nobody made. --}}
                @keydown.enter.prevent="{{ $optimisticConfig ? 'runIf(enterNext())' : 'onEnter()' }}"
                :aria-activedescendant="activeDescendantId"
                role="combobox"
                aria-haspopup="listbox"
                aria-expanded="false"
                :aria-expanded="dropdownOpen ? 'true' : 'false'"
                aria-controls="{{ $id }}-listbox"
                aria-autocomplete="list"
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                {{-- Wire an aria-label so WCAG 2.1 AA + axe label-rule are     --}}
                {{-- satisfied even when the parent <x-wirekit::field label="..."> --}}
                {{-- doesn't reach this internal combobox input.                 --}}
                aria-label="{{ $resolvedAriaLabel }}"
                :placeholder="selected.length === 0 ? {{ \Pushery\WireKit\Support\AlpinePayload::string($placeholder) }} : ''"
                class="wk-field flex-1 min-w-[80px] bg-transparent text-[color:var(--color-wk-text)] text-[length:var(--text-wk-md)] placeholder:text-[color:var(--color-wk-text-placeholder)] outline-none"
            />
        </div>

        {{-- Dropdown listbox --}}
        {{-- Teleported to <body>. `position: fixed` escapes a clipping ancestor but NOT
             a stacking context — the same trap the combobox and dropdown panels were in. --}}
        <template x-teleport="#wk-overlay-root">
        <div
            {{-- THE MORPH KEY. Livewire identifies a node across an update as
                 `wire:id`, then `wire:key`, then `el.id` — so without this line the
                 id below is the identity, and an id that disagrees between the live
                 node and the incoming template makes the morph SWAP rather than
                 patch: the live node is replaced by a native `cloneNode(true)` with
                 no Alpine expandos, landing in the overlay root, which hangs off
                 <body> in no `x-data`. Alpine's parent walk then finds no scope and
                 `dropdownOpen` resolves against the global object, which does not
                 have it — a `ReferenceError` on every update, from a panel nobody
                 opened, with a stack that names nothing on the page.
                 `$id` falls back to a random value only when the call site supplies
                 neither an id nor a name, so this reaches some callers and not
                 others. STATIC on purpose: the morph patches a teleported node
                 against its own counterpart, one to one, never against a keyed
                 sibling, so several multi-selects on a page do not compete. --}}
            wire:key="wk-multi-select-listbox"
            {{-- Open is open, whether or not anything matched. The panel used to
                 hide itself on an empty list, so a filter that matched nothing
                 and a filter that had not been typed yet looked identical — no
                 panel either way — and the documented "No results" state had
                 nowhere to appear. --}}
            x-show="dropdownOpen"
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-ref="panel"
            id="{{ $id }}-listbox"
            role="listbox"
            aria-multiselectable="true"
            class="fixed z-[var(--z-wk-dropdown)] overflow-y-auto rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-lg)] wk-scrollbar"
            x-cloak
        >
            <template x-for="(opt, idx) in filteredOptions" :key="opt.value">
                <div
                    role="option"
                    {{-- The id half of the pairing whose other half is
                         `aria-activedescendant` on the input above. Both read
                         `optionId()`, so they cannot drift apart. --}}
                    :id="optionId(idx)"
                    :aria-selected="selected.includes(opt.value) ? 'true' : 'false'"
                    class="{{ $optionClasses }}"
                    {{-- One binding, because an attribute can only be bound
                         once, and the two conditions are independent: a row can
                         be highlighted, selected, both or neither. The strings
                         come from the resolver above, so a scope can restyle
                         them the way it restyles every other class here. --}}
                    :class="(idx === highlight ? {{ \Pushery\WireKit\Support\AlpinePayload::string($optionHighlightedClasses) }} : '')
                        + ' '
                        + (selected.includes(opt.value) ? {{ \Pushery\WireKit\Support\AlpinePayload::string($optionSelectedClasses) }} : '')"
                    {{-- Pointing at a row makes it the active one, so a pointer
                         and the arrow keys leave the highlight in the same
                         place instead of each keeping their own idea of it. --}}
                    @mouseenter="hoverOption(idx)"
                    {{-- nextWith() returns a NEW array. toggle() splices in place,
                         and an in-place mutation gives the layer nothing to
                         snapshot — the rollback would restore the array it had
                         just changed. --}}
                    @click="{{ $optimisticConfig ? 'run(nextWith(opt.value))' : 'toggle(opt.value)' }}"
                    @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                >
                    <span x-text="opt.label"></span>
                </div>
            </template>

            {{-- Empty state. Inside the panel and wearing `role="option"`,
                 which is not decoration: `role="listbox"` may contain only
                 options and groups, so a bare paragraph here would be a list
                 with a stray child. `aria-disabled` says it is not a choice,
                 and it never becomes the active descendant because the
                 highlight indexes `filteredOptions`, which is empty exactly
                 when this row is showing.
                 The sibling combobox solves the same problem with a SECOND
                 teleported panel; here that would be a second `fixed` box for
                 _place() to anchor, and an unanchored one sits at the viewport
                 origin. One panel, two contents, one anchor. --}}
            <p
                role="option"
                aria-disabled="true"
                x-show="filteredOptions.length === 0"
                class="{{ $emptyRowClasses }}"
            >{{ __('wirekit::No results') }}</p>
        </div>
        </template>

        {{-- Selection announcer. Outside the listbox — a live region is not an
             option — and present from the first render, carrying whatever the
             `value` prop seeded: a region that arrives together with its text
             is a new node, and nothing is announced at all.
             It exists because the announcement a combobox normally gets for
             free cannot happen here. `filteredOptions` DROPS an option the
             moment it is chosen, so the `aria-selected` flip a reader would
             hear happens on a row that has stopped existing.
             Not rendered on the optimistic path, and that is the arbitration
             rather than an omission: there the pick is already announced,
             hedged, by the optimistic layer, and a second voice on the success
             path is what makes a rollback indistinguishable from a
             confirmation. One speaker per pick, whichever one is there. --}}
        @unless($optimisticConfig)
            <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="selectionAnnouncement"></div>
        @endunless

        @if($optimisticConfig)
            {{-- Outside the listbox — a live region is not an option — and inside
                 the optimistic scope. Rendered unconditionally and starting
                 empty: a region that arrives together with its text is a new
                 node, and nothing is announced at all. --}}
            <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
            </div>
        @endif
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
