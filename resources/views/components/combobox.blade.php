@props([
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
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config (WIRE-204).
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

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
    // the input, so a caller description reaches the labelable control (WIRE-162) and
    // never collides with the error id as two attributes (the WIRE-118 double bug).
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
        'absolute z-[var(--z-wk-dropdown)] mt-1 w-full',
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
<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$comboId" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif
<div
    x-data="{
        open: false,
        query: '',
        selected: @js($value),
        highlight: 0,
        allOptions: @js($normalized),
        // Cross-instance coordination — when ANY combobox on the page opens it
        // broadcasts a `wirekit:combobox-open` event carrying a stable Symbol;
        // every OTHER instance closes itself on receipt. Without this, two
        // open comboboxes can visually overlap (a long option list spills into
        // the next combobox's territory on a docs page). Matches the
        // context-menu cross-close pattern. The Symbol is created in init()
        // so each instance gets its own unforgeable identity that survives
        // Alpine's Proxy wrap-and-unwrap.
        _uid: null,
        _otherOpenCleanup: null,
        get filtered() {
            if (this.query === '') return this.allOptions;
            const q = this.query.toLowerCase();
            return this.allOptions.filter(o => o.label.toLowerCase().includes(q));
        },
        // Groups the FILTERED options by their `group` label, preserving
        // first-seen group order and each option's index into `filtered` (as
        // `_idx`) so highlight + aria-activedescendant keep using the flat
        // keyboard model unchanged. Empty groups never appear (built from
        // `filtered`, so a group whose options all filtered out is absent).
        get filteredGroups() {
            const groups = [];
            const byLabel = new Map();
            this.filtered.forEach((opt, idx) => {
                const label = opt.group || null;
                let bucket = byLabel.get(label);
                if (! bucket) {
                    bucket = { label, options: [] };
                    byLabel.set(label, bucket);
                    groups.push(bucket);
                }
                bucket.options.push({ ...opt, _idx: idx });
            });

            return groups;
        },
        init() {
            // Seed the query with the label of the initial value, if any.
            const match = this.allOptions.find(o => o.value === this.selected);
            if (match) this.query = match.label;

            this._uid = Symbol('wirekitCombobox');
            this._otherOpenCleanup = (event) => {
                if (event.detail?.source !== this._uid && this.open) {
                    this.open = false;
                }
            };
            window.addEventListener('wirekit:combobox-open', this._otherOpenCleanup);
            // Broadcast on every transition into the open state so siblings can close.
            this.$watch('open', (val) => {
                if (val) {
                    window.dispatchEvent(new CustomEvent('wirekit:combobox-open', {
                        detail: { source: this._uid },
                    }));
                }
            });
        },
        destroy() {
            if (this._otherOpenCleanup) {
                window.removeEventListener('wirekit:combobox-open', this._otherOpenCleanup);
            }
        },
        selectOption(opt) {
            if (opt.disabled) return;
            this.selected = opt.value;
            this.query = opt.label;
            this.open = false;
        },
        moveHighlight(delta) {
            const max = this.filtered.length - 1;
            if (max < 0) return;
            // Skip disabled options when navigating with arrow keys.
            // Walk in `delta` direction until we find an enabled option
            // or wrap-back to where we started (no enabled options →
            // bail without changing highlight).
            let next = this.highlight;
            for (let step = 0; step < this.filtered.length; step++) {
                next = Math.max(0, Math.min(max, next + delta));
                if (! this.filtered[next].disabled) {
                    this.highlight = next;
                    return;
                }
                if (next === 0 && delta < 0) return;
                if (next === max && delta > 0) return;
            }
        },
        highlightFirst() {
            // Jump to the first ENABLED option (WAI-ARIA combobox Home key).
            const max = this.filtered.length - 1;
            if (max < 0) return;
            for (let i = 0; i <= max; i++) {
                if (! this.filtered[i].disabled) { this.highlight = i; return; }
            }
        },
        highlightLast() {
            // Jump to the last ENABLED option (WAI-ARIA combobox End key).
            const max = this.filtered.length - 1;
            if (max < 0) return;
            for (let i = max; i >= 0; i--) {
                if (! this.filtered[i].disabled) { this.highlight = i; return; }
            }
        },
        activateHighlighted() {
            if (this.filtered[this.highlight] && ! this.filtered[this.highlight].disabled) {
                this.selectOption(this.filtered[this.highlight]);
            }
        },
        clearSelection() {
            this.selected = null;
            this.query = '';
            this.open = false;
            // Trigger input event so wire:model picks up the cleared value.
            const hidden = this.$el.querySelector('input[type=hidden]');
            if (hidden) { hidden.value = ''; hidden.dispatchEvent(new Event('input', { bubbles: true })); }
        }
    }"
    @click.outside="open = false"
    {{-- The roleless wrapper carries ONLY layout — every caller attribute
         (aria-describedby, data-*, autocomplete, required, …) is routed to the
         role="combobox" input below, never left stranded on this <div> (WIRE-162). --}}
    {{ $attributes->only(['style'])->class(['relative w-full']) }}
>
    {{-- Hidden input holding the selected *value* for form submission. --}}
    @if($name)
        <input type="hidden" name="{{ $name }}" :value="selected ?? ''" />
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
        @input="open = true; highlight = 0"
        @keydown.arrow-down.prevent="open = true; moveHighlight(1)"
        @keydown.arrow-up.prevent="moveHighlight(-1)"
        @keydown.home.prevent="open = true; highlightFirst()"
        @keydown.end.prevent="open = true; highlightLast()"
        @keydown.enter.prevent="activateHighlighted()"
        @keydown.escape="open = false"
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{-- Accessible name: a visible <label for> wins; otherwise aria-label
             from the ariaLabel prop / caller attribute lands on this input (the
             labelable role="combobox" control), never the roleless wrapper. --}}
        @if(! $label && $resolvedAriaLabel) aria-label="{{ $resolvedAriaLabel }}" @endif
        {{-- Every OTHER caller attribute (data-*, autocomplete, required, readonly …)
             reaches the actual control here, not the wrapper (WIRE-162). --}}
        {{ $attributes->except(['aria-label', 'class', 'style', 'aria-describedby']) }}
        class="wk-field {{ $inputClasses }}"
    />

    {{-- Clear button — visible only when a value is selected. Positioned left of the chevron. --}}
    @if(!$disabled)
        <button
            type="button"
            x-show="selected"
            x-cloak
            @click.stop="clearSelection()"
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
        @click.stop="open = ! open; $refs.cbxInput?.focus();"
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
    <ul
        id="{{ $listId }}"
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
        <template x-for="grp in filteredGroups" :key="grp.label ?? '__wk_ungrouped'">
            <li role="group" :aria-label="grp.label || 'Options'" style="list-style: none;">
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
                            @click="selectOption(opt)"
                            @mouseenter="if (! opt.disabled) highlight = opt._idx"
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
                @click="selectOption(opt)"
                @mouseenter="if (! opt.disabled) highlight = idx"
                x-text="opt.label"
            ></li>
        </template>
        @endif
    </ul>

    {{-- Empty state when filter produces no matches. --}}
    <div
        class="{{ $listClasses }}"
        x-show="open && filtered.length === 0 && query !== ''"
        x-cloak
    >
        <p class="{{ $emptyRowClasses }} text-[color:var(--color-wk-text-muted)]">No results</p>
    </div>

    @if($hasError)
        <p id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>

{{-- Close the always-present root wrapper. --}}
</div>
