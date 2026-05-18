@props([
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'size' => config('wirekit.components.combobox.size', 'md'),
    'placeholder' => config('wirekit.components.combobox.placeholder', 'Select...'),
    'disabled' => false,
    'error' => null,
    'scope' => null,
])

@php
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
    $normalized = [];
    foreach ($options as $key => $opt) {
        if (is_array($opt)) {
            $normalized[] = [
                'value' => (string) ($opt['value'] ?? $key),
                'label' => (string) ($opt['label'] ?? $opt['value'] ?? $key),
                'disabled' => (bool) ($opt['disabled'] ?? false),
            ];
        } elseif (is_int($key)) {
            $normalized[] = ['value' => (string) $opt, 'label' => (string) $opt, 'disabled' => false];
        } else {
            $normalized[] = ['value' => (string) $key, 'label' => (string) $opt, 'disabled' => false];
        }
    }

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // Sizing.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
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
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border)]',
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
        'max-h-60 overflow-auto',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'py-1',
    ]), $scope);

    // Shared padding/typography for the empty-state row. Option <li>
    // entries inline their own classes since they need conditional cursor
    // rules (cursor-pointer for enabled, cursor-not-allowed for disabled).
    $emptyRowClasses = 'p-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)]';
@endphp

{{-- Alpine state: `query` mirrors the text input, `open` controls visibility,
     `selected` holds the chosen option value, `highlight` tracks keyboard focus
     index within the *filtered* list. --}}
<div
    x-data="{
        open: false,
        query: '',
        selected: @js($value),
        highlight: 0,
        allOptions: @js($normalized),
        get filtered() {
            if (this.query === '') return this.allOptions;
            const q = this.query.toLowerCase();
            return this.allOptions.filter(o => o.label.toLowerCase().includes(q));
        },
        init() {
            // Seed the query with the label of the initial value, if any.
            const match = this.allOptions.find(o => o.value === this.selected);
            if (match) this.query = match.label;
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
    class="relative w-full"
    {{ $attributes }}
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
        @keydown.enter.prevent="activateHighlighted()"
        @keydown.escape="open = false"
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" aria-describedby="{{ $errorId }}" @endif
        class="{{ $inputClasses }}"
    />

    {{-- Clear button — visible only when a value is selected. Positioned left of the chevron. --}}
    @if(!$disabled)
        <button
            type="button"
            x-show="selected"
            x-cloak
            @click.stop="clearSelection()"
            class="absolute right-8 top-1/2 -translate-y-1/2 p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
            aria-label="Clear selection"
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
        @click.stop="open = ! open; if (open) $refs.cbxInput?.focus();"
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
                class="p-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)]"
                @click="selectOption(opt)"
                @mouseenter="if (! opt.disabled) highlight = idx"
                x-text="opt.label"
            ></li>
        </template>
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
        <p id="{{ $errorId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
