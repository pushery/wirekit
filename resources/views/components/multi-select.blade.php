@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => config('wirekit.a11y.announce_error', true),
    'label' => null,
    'hint' => null,
    'error' => null,
    'options' => [],
    'value' => [],          // option keys to pre-select on load (array or comma-separated string)
    'placeholder' => 'Select...',
    'scope' => null,
    'ariaLabel' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('multi-select', $attributes->getAttributes());

    $id = $attributes->get('id', $attributes->get('name', 'multi-select-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);
    // When a parent <x-wirekit::field label="..."> wraps this component, the
    // field-emitted <label for="$id"> doesn't reach the internal combobox
    // <input id="$id-input">, so screen readers + axe's label rule report
    // an unlabelled form element. We synthesize an aria-label fallback —
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
        : 'border-[var(--color-wk-border)]';

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
    $optionClasses = implode(' ', [
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
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

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id . '-input'">{{ $label }}</x-wirekit::label>
    @endif

    <div
        x-data="wirekitMultiSelect({ options: {{ json_encode($encodedOptions) }}, name: '{{ $name }}', value: {{ json_encode($selectedValues) }} })"
        class="relative"
        @click.away="dropdownOpen = false"
        @keydown.escape="dropdownOpen = false"
    >
        {{-- Hidden inputs for form submission --}}
        <template x-for="(val, i) in selected" :key="i">
            <input type="hidden" :name="'{{ $name }}[]'" :value="val" />
        </template>

        {{-- Input container with pills --}}
        <div
            class="{{ $containerClasses }} {{ $stateClasses }}"
            @click="$refs.filterInput.focus(); dropdownOpen = true"
        >
            {{-- Selected value pills --}}
            <template x-for="(val, i) in selected" :key="'pill-'+val">
                <span class="{{ $pillClasses }}">
                    <span x-text="getLabel(val)"></span>
                    <button
                        type="button"
                        @click.stop="deselect(val)"
                        :aria-label="'Remove ' + getLabel(val)"
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
                @input="dropdownOpen = true"
                @keydown.backspace="onBackspace($event)"
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
                :placeholder="selected.length === 0 ? '{{ $placeholder }}' : ''"
                class="wk-field flex-1 min-w-[80px] bg-transparent text-[color:var(--color-wk-text)] text-[length:var(--text-wk-md)] placeholder:text-[color:var(--color-wk-text-placeholder)] outline-none"
            />
        </div>

        {{-- Dropdown listbox --}}
        <div
            x-show="dropdownOpen && filteredOptions.length > 0"
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            id="{{ $id }}-listbox"
            role="listbox"
            aria-multiselectable="true"
            class="absolute z-[var(--z-wk-dropdown)] mt-1 w-full max-h-48 overflow-y-auto rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-lg)] wk-scrollbar"
            x-cloak
        >
            <template x-for="opt in filteredOptions" :key="opt.value">
                <div
                    role="option"
                    :aria-selected="selected.includes(opt.value) ? 'true' : 'false'"
                    class="{{ $optionClasses }}"
                    :class="selected.includes(opt.value) ? 'font-[number:var(--font-wk-heading-weight)]' : ''"
                    @click="toggle(opt.value)"
                >
                    <span x-text="opt.label"></span>
                </div>
            </template>
        </div>
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
