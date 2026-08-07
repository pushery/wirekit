{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the choice lands the moment an option is
     picked. A discrete value from a fixed list, and the previous one is the
     server's — so an undo destroys nothing the user typed. The optimistic scope
     nests INSIDE this component and binds to `selected`; the text field follows
     via `after`, which derives its label from the value rather than from the
     clicked option, so a rollback restores the PREVIOUS selection's label. --}}
@props([
    // Livewire method to call optimistically. The choice appears immediately
    // and is put back if the call fails. Absent -> this component renders
    // exactly as it did before, down to the byte.
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
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'size' => config('wirekit.components.combobox.size', 'md'),
    'placeholder' => config('wirekit.components.combobox.placeholder', 'Select...'),
    'disabled' => false,
    'error' => null,
    // Accessible name for the combobox. Mirrors select / multi-select: a visible
    // `label` renders an associated x-wirekit::label (for={comboId}); `hideLabel`
    // keeps it in the DOM for assistive tech but visually hidden (compact
    // toolbar / header fields); `ariaLabel` sets aria-label directly on the
    // role="combobox" input for the label-less case. All default to today's
    // behavior (no label at all), so existing comboboxes render byte-identically.
    'label' => null,
    'hideLabel' => false,
    'ariaLabel' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);
    $hideLabel = BooleanProp::from($hideLabel, false);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Combobox = searchable select. Follows WAI-ARIA 1.2 combobox pattern:
    //   https://www.w3.org/WAI/ARIA/apg/patterns/combobox/
    // Key behavior: user types to filter options, uses arrow keys to navigate
    // the filtered list, Enter to select, Escape to close.
    $comboId = $id ?? ($name ? 'wk-combobox-' . $name : 'wk-combobox-' . Str::random(6));
    $listId = $comboId . '-list';
    $errorId = $comboId . '-error';

    // Normalize options: accept ['key' => 'label'] assoc or list of
    // ['value' => .., 'label' => .., 'disabled' => bool] or plain strings.
    // The `disabled` flag (default false) renders the option visually
    // dimmed + not-allowed cursor and prevents click + keyboard activation.
    // A GROUP is an array value with neither a 'label' nor a 'value' key — i.e.
    // a nested map of sub-options (mirrors <x-wirekit::select>: `['Europe' =>
    // ['de' => 'Germany', ...]]`). The extra `value`-key guard keeps the legacy
    // single-option shape `['value' => 'x']` (no label) working as an option.
    // Grouped options carry a `group` key; ungrouped options omit it, so a
    // group-free combobox normalizes byte-identically to before.
    $normalizeOption = function ($key, $opt) {
        if (is_array($opt)) {
            return [
                'value' => (string) ($opt['value'] ?? $key),
                'label' => (string) ($opt['label'] ?? $opt['value'] ?? $key),
                'disabled' => (bool) ($opt['disabled'] ?? false),
            ];
        }

        return is_int($key)
            ? ['value' => (string) $opt, 'label' => (string) $opt, 'disabled' => false]
            : ['value' => (string) $key, 'label' => (string) $opt, 'disabled' => false];
    };

    $normalized = [];
    foreach ($options as $key => $opt) {
        $isGroup = is_array($opt) && ! array_key_exists('label', $opt) && ! array_key_exists('value', $opt);
        if ($isGroup) {
            foreach ($opt as $subKey => $subOpt) {
                $entry = $normalizeOption($subKey, $subOpt);
                $entry['group'] = (string) $key;
                $normalized[] = $entry;
            }
        } else {
            $entry = $normalizeOption($key, $opt);
            if (is_array($opt) && ! empty($opt['group'])) {
                $entry['group'] = (string) $opt['group'];
            }
            $normalized[] = $entry;
        }
    }

    $hasGroups = false;
    foreach ($normalized as $o) {
        if (! empty($o['group'])) {
            $hasGroups = true;
            break;
        }
    }

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // Accessible name resolution. A visible label associates via <label for>
    // (label wins, no aria-label needed). Otherwise fall back to the ariaLabel
    // prop, then a caller-passed aria-label attribute — applied to the VISIBLE
    // role="combobox" input (the labelable control), never the roleless wrapper.
    $callerAriaLabel = $attributes->get('aria-label');
    $resolvedAriaLabel = $ariaLabel ?? $callerAriaLabel;

    // Merge a caller aria-describedby with our own error target into ONE attribute on
    // the input, so a caller description reaches the labelable control and
    // never collides with the error id as two attributes.
    $ownDescribedBy = $hasError ? $errorId : null;
    $callerDescribedBy = $attributes->get('aria-describedby');
    $describedBy = trim(((string) ($ownDescribedBy ?? '')).' '.((string) ($callerDescribedBy ?? '')));
    $describedBy = $describedBy !== '' ? $describedBy : null;

    // Sizing.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    // Option-row sizing — scales the DROPDOWN with `size` so the open panel
    // matches its trigger (a `lg` combobox had `sm`-sized options before, which
    // read as a mismatch). Text size mirrors the trigger; padding scales with it.
    $optionRowClasses = match ($size) {
        'sm' => 'p-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)]',
        'lg' => 'p-[var(--padding-wk-y-md)] text-[length:var(--text-wk-lg)]',
        default => 'p-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-md)]',
    };

    // Text input styling — identical to other form controls for visual cohesion.
    $inputClasses = WireKit::resolveClasses('combobox', 'input', implode(' ', [
        'w-full',
        'px-[var(--padding-wk-x-md)]',
        'pr-[var(--size-wk-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'rounded-[var(--radius-wk-md)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus:border-[var(--color-wk-accent)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    // Options list — dropdown panel.
    // list-none removes browser-default bullet points from the <ul>.
    $listClasses = WireKit::resolveClasses('combobox', 'list', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',  // fixed escapes a clipping card; width + height come from _place()
        'list-none',
        'wk-scrollbar max-h-60 overflow-auto',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'py-1',
    ]), $scope);

    // Empty-state row shares the option-row sizing so "No results" scales with
    // the combobox like the options do.
    $emptyRowClasses = $optionRowClasses;
@endphp

{{-- Alpine state: `query` mirrors the text input, `open` controls visibility,
     `selected` holds the chosen option value, `highlight` tracks keyboard focus
     index within the *filtered* list. --}}
{{-- Single always-present root wrapper (mirrors <x-wirekit::select>). The label,
     when set, associates with the combobox input via `for={comboId}`; without a
     label the wrapper is a layout-neutral div (space-y-1.5 applies no margin to a
     single child, so no visual change). A single stable root keeps the anonymous
     component's $attributes / $component scope intact. --}}
@php
    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the options have to be inside it
    // to reach its `run()`.
    //
    // `after: '_syncQuery'` is what makes the rollback readable. The field shows
    // the chosen option's label, and after an undo it must show the PREVIOUS
    // one's — an option nobody clicked, so it can only come from the value.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_syncQuery',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'reverted' => __('Could not save. Change undone.'),
        ],
    ]);
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$comboId" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif
<div
    x-data="wirekitCombobox({ value: {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }}, options: {{ \Pushery\WireKit\Support\AlpinePayload::from($normalized) }}, listId: '{{ $listId }}', emptyId: '{{ $listId }}-empty', inputId: '{{ $comboId }}' })"
    @click.outside="open = false"
    {{-- The roleless wrapper carries ONLY layout — every caller attribute
         (aria-describedby, data-*, autocomplete, required, …) is routed to the
         role="combobox" input below, never left stranded on this <div>. --}}
    {{ $attributes->only(['style'])->class(['relative w-full']) }}
>
    @if($optimisticConfig)
        {{-- `display: contents` — this element's `relative` is the containing
             block the listbox is positioned against, and a real box here would
             move the panel. --}}
        <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
    @endif

    {{-- Hidden input holding the selected *value* for form submission. --}}
    @if($name)
        {{-- Static value as well as the bound one: the field is empty until Alpine
             boots, and a form submitted in that window sends nothing while the
             visible control already shows the value. Both come from the same PHP
             expression that feeds the factory, so they cannot drift. --}}
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="submittedValue" />
    @endif

    {{-- Visible text input — role=combobox + aria-expanded + aria-controls
         satisfies the WAI-ARIA 1.2 combobox pattern. --}}
    <input
        type="text"
        x-ref="cbxInput"
        id="{{ $comboId }}"
        role="combobox"
        aria-expanded="false"
        :aria-expanded="open"
        aria-controls="{{ $listId }}"
        :aria-activedescendant="open && filtered[highlight] ? '{{ $listId }}-opt-' + highlight : null"
        aria-autocomplete="list"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        x-model="query"
        @focus="open = true"
        @input="openAndReset()"
        @keydown.arrow-down.prevent="openAndMove(1)"
        @keydown.arrow-up.prevent="moveHighlight(-1)"
        @keydown.home.prevent="openAtFirst()"
        @keydown.end.prevent="openAtLast()"
        {{-- runIf, not run: Enter can fire with nothing highlighted, and
             `run(undefined)` would send the server a value nobody chose and then
             roll back from it. --}}
        @keydown.enter.prevent="{{ $optimisticConfig ? 'runIf(highlightedValue())' : 'activateHighlighted()' }}"
        @keydown.escape="open = false"
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{-- Accessible name: a visible <label for> wins; otherwise aria-label
             from the ariaLabel prop / caller attribute lands on this input (the
             labelable role="combobox" control), never the roleless wrapper. --}}
        @if(! $label && $resolvedAriaLabel) aria-label="{{ $resolvedAriaLabel }}" @endif
        {{-- Every OTHER caller attribute (data-*, autocomplete, required, readonly …)
             reaches the actual control here, not the wrapper. --}}
        {{ $attributes->except(['aria-label', 'class', 'style', 'aria-describedby']) }}
        class="wk-field {{ $inputClasses }}"
    />

    {{-- Clear button — visible only when a value is selected. Positioned left of the chevron. --}}
    @if(!$disabled)
        <button
            type="button"
            x-show="selected"
            x-cloak
            {{-- run(null), not runIf: clearing IS a choice — "none of them" — and it
                 is a mutation the server has to hear about. `undefined` would be
                 the absence of a choice; null is a choice. --}}
            @click.stop="{{ $optimisticConfig ? 'run(null)' : 'clearSelection()' }}"
            class="absolute right-8 top-1/2 -translate-y-1/2 inline-flex items-center justify-center min-w-[24px] min-h-[24px] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
            aria-label="{{ __('Clear selection') }}"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
            </svg>
        </button>
    @endif

    {{-- Chevron — clickable button that toggles the dropdown. Carries
         `cursor-pointer` so the user gets the right hover affordance, and
         delegates focus to the input on click so the input's keyboard
         contract continues to work. tabindex="-1" keeps the chevron out
         of the natural tab order — the input itself is the focusable
         control per the WAI-ARIA combobox pattern. --}}
    <button
        type="button"
        {{-- Always return focus to the input — on close too, not only on open.
             This button is aria-hidden + tabindex=-1 (decorative; the input is
             the focusable combobox control). If a click leaves focus ON this
             button (which happens when it toggles the panel closed), the browser
             flags "aria-hidden on a focused element". Refocusing the input every
             time keeps focus on the real control and clears that warning. --}}
        @click.stop="toggleAndFocus()"
        @if($disabled) disabled @endif
        tabindex="-1"
        aria-hidden="true"
        class="absolute right-3 top-1/2 -translate-y-1/2 p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-transform duration-[var(--transition-wk-duration)] cursor-pointer disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)]"
        :class="open ? 'rotate-180' : ''"
    >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </button>

    {{-- Listbox — filtered options rendered via x-for. Each option gets a
         unique id + role=option so AT can announce them as the user navigates. --}}
    {{-- Teleported to <body>, for the reason command-palette's overlay already
         states: `position: fixed` escapes a clipping ancestor but NOT a stacking
         context. Any ancestor with `contain: layout`, a transform or a filter
         scopes this panel's z-index inside itself, and anything painted after
         that ancestor then covers the list however high the z-index goes.
         Reported from the documentation site, whose preview area carries
         `contain: layout`: the open list rendered UNDER the code block below it.
         `$refs` do NOT survive the teleport, and the comment that used to say so
         here was an assumption nobody had measured. Once the panel moves to
         `<body>`, `$refs.cbxList` is null — so `_place()` looped over two nulls,
         positioned nothing, and left a `fixed` panel at its static position:
         measured at 0,1117 while the field sat at 12,451. It never corrected,
         because the positioner had not run at all.
         `_place()` resolves both panels by id now, handed in through the factory
         config. An id survives anything a teleport can do to a node. --}}
    <template x-teleport="#wk-overlay-root">
    <ul
        id="{{ $listId }}"
        x-ref="cbxList"
        role="listbox"
        class="{{ $listClasses }}"
        style="list-style: none; margin: 0; padding: 0;"
        x-show="open && filtered.length > 0"
        x-cloak
    >
        @if($hasGroups)
        {{-- Grouped options: each group is role="group" with an aria-label; the
             visible heading is decorative (aria-hidden) since the group's
             aria-label supplies its name. The inner list is role="none" so the
             options remain effective children of the group in the a11y tree.
             The flat keyboard model is untouched — selection + highlight key off
             opt._idx (each option's index into the flat `filtered` list). --}}
        <template x-for="grp in filteredGroups" :key="groupKey(grp)">
            <li role="group" :aria-label="grp.label || {{ \Pushery\WireKit\Support\AlpinePayload::from(__('Options')) }}" style="list-style: none;">
                <template x-if="grp.label">
                    <div aria-hidden="true" class="px-[var(--padding-wk-x-md)] pt-[var(--padding-wk-y-sm)] pb-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] uppercase tracking-wider text-[color:var(--color-wk-text-muted)]" x-text="grp.label"></div>
                </template>
                <ul role="none" style="list-style: none; margin: 0; padding: 0;">
                    <template x-for="opt in grp.options" :key="opt.value">
                        <li
                            role="option"
                            :id="'{{ $listId }}-opt-' + opt._idx"
                            :aria-selected="selected === opt.value"
                            :aria-disabled="opt.disabled ? 'true' : null"
                            :class="opt.disabled
                                ? 'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed'
                                : (opt._idx === highlight
                                    ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] cursor-pointer'
                                    : 'text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)] cursor-pointer')"
                            class="{{ $optionRowClasses }}"
                            @click="{{ $optimisticConfig ? 'run(opt.value)' : 'selectOption(opt)' }}"
                            @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                            @mouseenter="hoverOption(opt, opt._idx)"
                            x-text="opt.label"
                        ></li>
                    </template>
                </ul>
            </li>
        </template>
        @else
        <template x-for="(opt, idx) in filtered" :key="opt.value">
            <li
                role="option"
                :id="'{{ $listId }}-opt-' + idx"
                :aria-selected="selected === opt.value"
                :aria-disabled="opt.disabled ? 'true' : null"
                :class="opt.disabled
                    ? 'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed'
                    : (idx === highlight
                        ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] cursor-pointer'
                        : 'text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)] cursor-pointer')"
                class="{{ $optionRowClasses }}"
                @click="{{ $optimisticConfig ? 'run(opt.value)' : 'selectOption(opt)' }}"
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                @mouseenter="hoverOption(opt, idx)"
                x-text="opt.label"
            ></li>
        </template>
        @endif
    </ul>
    </template>

    {{-- Empty state when filter produces no matches. Teleported for the same
         reason as the list above — it is the same panel wearing different content,
         and leaving it behind would fix the case with results and keep the bug for
         the case without. --}}
    <template x-teleport="#wk-overlay-root">
    <div
        id="{{ $listId }}-empty"
        class="{{ $listClasses }}"
        x-ref="cbxEmpty"
        x-show="open && filtered.length === 0 && query !== ''"
        x-cloak
    >
        <p class="{{ $emptyRowClasses }} text-[color:var(--color-wk-text-muted)]">{{ __('No results') }}</p>
    </div>
    </template>

    @if($hasError)
        <p id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif

    @if($optimisticConfig)
        {{-- Outside the listbox — a live region is not an option — and inside
             the optimistic scope. Rendered unconditionally and starting empty: a
             region that arrives together with its text is a new node, and
             nothing is announced at all.

             It sits AFTER the error paragraph on purpose: where that paragraph
             is present and speaking, the layer's own rollback stays silent and
             leaves it the floor. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
        </div>
    @endif
</div>

{{-- Close the always-present root wrapper. --}}
</div>
