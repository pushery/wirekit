@props([
    'fields' => [],                 // [{key,label,type,operators?,options?}]; type: text|number|select|date|bool
    'value' => [],                  // active filters [{field,op,value}] (two-way via wire:model bridge)
    'name' => null,                 // hidden-input name for plain-form submission
    'searchable' => config('wirekit.components.filter-builder.searchable', false), // free-text search box
    'searchPlaceholder' => config('wirekit.components.filter-builder.search-placeholder', 'Search…'),
    'addLabel' => config('wirekit.components.filter-builder.add-label', 'Add filter'),
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    $id = $attributes->get('id', 'filter-builder-'.Str::random(6));
    $name = $name ?? $attributes->get('name');

    // Normalize to plain arrays for the @js() seed (accepts Collections too).
    $fieldsArr = $fields instanceof \Illuminate\Support\Collection ? $fields->values()->all() : array_values((array) $fields);
    $valueArr = $value instanceof \Illuminate\Support\Collection ? $value->values()->all() : array_values((array) $value);

    $popoverTitleId = $id.'-popover-title';

    $base = WireKit::resolveClasses('filter-builder', 'base',
        'w-full font-[family-name:var(--font-wk-sans)]',
        $scope
    );

    // Active-filter chip — a pill carrying "field op value", click to edit.
    // Height matches the toolbar controls (search input + Add-filter button):
    // py-[var(--padding-wk-y-sm)] for the same vertical padding, plus a transparent
    // 1px border so the chip's border-box equals the bordered controls' box
    // exactly (otherwise the chip reads visibly shorter than the Add-filter
    // button beside it).
    $chipClasses = implode(' ', [
        'inline-flex items-center gap-1',
        'pl-[var(--padding-wk-x-sm)] pr-1 py-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-sm)]',
        'border-[length:var(--border-wk-width)] border-transparent',
        'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-full)]',
    ]);

    // Shared styling for the popover's typed operator / value controls so they
    // match <x-wirekit::input> / <x-wirekit::select> without re-rendering those
    // components inside an x-for/x-model context.
    $control = implode(' ', [
        'w-full',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)] text-[length:var(--text-wk-sm)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)]',
        'rounded-[var(--radius-wk-md)]',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]);

    // Select variant of $control: hide the native dropdown arrow
    // (appearance-none) and reserve right-edge space (pr-8) so the custom
    // chevron overlay sits where <x-wirekit::select> puts it — otherwise the
    // raw native arrow renders flush at the far-right edge, inconsistent with
    // the rest of WireKit. Each <select> using this MUST sit in a `relative`
    // wrapper alongside the chevron overlay below.
    $selectControl = $control.' appearance-none pr-8';

    $controlLabel = 'block text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text-muted)] mb-1';
@endphp

<div
    {{ $attributes->except(['id', 'name', 'class'])->whereDoesntStartWith('wire:model') }}
    id="{{ $id }}"
    x-data="wirekitFilterBuilder({ fields: @js($fieldsArr), value: @js($valueArr) })"
    {{-- click.outside lives on the teleported panel (it's no longer in this subtree);
         escape stays here (window-scoped, teleport-agnostic). --}}
    x-on:keydown.escape.window="open && close(true)"
    {{ $attributes->only('class')->class([$base]) }}
>
    {{-- JSON bridge: forwards wire:model + serves plain-form submission. The
         normalized filter array is mirrored here as JSON on every change. --}}
    <input
        type="hidden"
        x-ref="model"
        @if($name) name="{{ $name }}" @endif
        {{ $attributes->whereStartsWith('wire:model') }}
        :value="JSON.stringify(filters)"
    />

    <div class="flex flex-wrap items-center gap-[var(--gap-wk-sm)]">
        @if($searchable)
            {{-- Free-text search — dispatches a bubbling `search-change` event
                 with the current term so the host can bind it independently of
                 the structured filters (keyword search vs. field filters). --}}
            <input
                type="search"
                x-ref="search"
                placeholder="{{ $searchPlaceholder }}"
                aria-label="{{ $searchPlaceholder }}"
                @input="$dispatch('search-change', { value: $event.target.value })"
                class="wk-field {{ $control }} max-w-[16rem]"
            />
        @endif

        {{-- Active-filter chips --}}
        <template x-for="(filter, i) in filters" :key="i">
            <span class="{{ $chipClasses }}">
                {{-- The chip body opens the edit popover. --}}
                <button
                    type="button"
                    @click="openEdit(i)"
                    :aria-label="'Edit filter: ' + chipText(filter)"
                    class="cursor-pointer focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] rounded-[var(--radius-wk-sm)]"
                >
                    <span x-text="chipText(filter)"></span>
                </button>
                {{-- Remove (×) — keyboard reachable, named per WCAG. --}}
                <button
                    type="button"
                    @click="remove(i)"
                    :aria-label="'Remove filter: ' + chipText(filter)"
                    class="p-0.5 rounded-[var(--radius-wk-full)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
                >
                    <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                </button>
            </span>
        </template>

        {{-- Add-filter popover trigger + panel (anchored to the trigger). --}}
        <div class="relative inline-block">
            <button
                type="button"
                x-ref="trigger"
                @click="open ? close() : openAdd()"
                :aria-expanded="open"
                aria-haspopup="dialog"
                class="inline-flex items-center gap-1 px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] border-dashed rounded-[var(--radius-wk-full)] hover:text-[color:var(--color-wk-text)] hover:border-[var(--color-wk-border-strong-hover)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
            >
                <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M7 2v10M2 7h10"/></svg>
                <span>{{ $addLabel }}</span>
            </button>

            {{-- Teleported to <body> + Floating-UI positioned (see wirekitFilterBuilder)
                 so the panel escapes any clipping/stacking ancestor and flips/shifts to
                 stay on-screen. click.outside lives HERE (on the panel), not the root,
                 because teleporting moves the panel out of the root's subtree. --}}
            <template x-teleport="body">
            <div
                x-show="open"
                x-cloak
                x-ref="panel"
                x-transition.origin.top.left
                x-on:click.outside="close()"
                role="dialog"
                aria-labelledby="{{ $popoverTitleId }}"
                class="fixed z-[var(--z-wk-dropdown)] w-[18rem] max-w-[calc(100vw-2rem)] p-[var(--padding-wk-x-md)] bg-[var(--color-wk-bg-elevated)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] rounded-[var(--radius-wk-lg)] shadow-[var(--shadow-wk-lg)] space-y-[var(--space-wk-sm)]"
            >
                <p id="{{ $popoverTitleId }}" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]"
                   x-text="editIndex === null ? '{{ $addLabel }}' : 'Edit filter'"></p>

                {{-- Field --}}
                <label class="block">
                    <span class="{{ $controlLabel }}">Field</span>
                    <div class="relative">
                        <select x-ref="fieldSelect" x-model="draft.field" @change="onFieldChange()" class="wk-field {{ $selectControl }}">
                            <template x-for="f in fields" :key="f.key">
                                <option :value="f.key" x-text="f.label"></option>
                            </template>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                            <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>
                </label>

                {{-- Operator (typed by field) --}}
                <label class="block">
                    <span class="{{ $controlLabel }}">Condition</span>
                    <div class="relative">
                        <select x-model="draft.op" class="wk-field {{ $selectControl }}">
                            <template x-for="o in operatorsFor(draft.field)" :key="o.op">
                                <option :value="o.op" x-text="o.label"></option>
                            </template>
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                            <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                        </div>
                    </div>
                </label>

                {{-- Value editor (typed by field) --}}
                <label class="block">
                    <span class="{{ $controlLabel }}">Value</span>
                    <template x-if="draftValueType() === 'select'">
                        <div class="relative">
                            <select x-model="draft.value" class="wk-field {{ $selectControl }}">
                                <option value="" disabled>Choose…</option>
                                <template x-for="opt in draftOptions()" :key="opt.value">
                                    <option :value="opt.value" x-text="opt.label"></option>
                                </template>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                                <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                            </div>
                        </div>
                    </template>
                    <template x-if="draftValueType() === 'bool'">
                        <div class="relative">
                            {{-- .boolean is REQUIRED: a non-multiple <select> x-model reads
                                 the option's DOM value, which is ALWAYS a string ("false").
                                 Without it the model holds the truthy string "false" → the
                                 chip shows "Yes" and the emitted JSON carries a string, not a
                                 boolean. apply() also coerces as a defensive net. --}}
                            <select x-model.boolean="draft.value" class="wk-field {{ $selectControl }}">
                                <option :value="true">Yes</option>
                                <option :value="false">No</option>
                            </select>
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
                                <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                            </div>
                        </div>
                    </template>
                    <template x-if="draftValueType() === 'number'">
                        <input type="number" x-model.number="draft.value" class="wk-field {{ $control }}" />
                    </template>
                    <template x-if="draftValueType() === 'date'">
                        <input type="date" x-model="draft.value" class="wk-field {{ $control }}" />
                    </template>
                    <template x-if="draftValueType() === 'text'">
                        <input type="text" x-model="draft.value" @keydown.enter.prevent="apply()" class="wk-field {{ $control }}" />
                    </template>
                </label>

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-[var(--gap-wk-sm)] pt-1">
                    <button type="button" @click="close(true)" class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] rounded-[var(--radius-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer">Cancel</button>
                    <button type="button" @click="apply()" :disabled="!canApply()" class="px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-inverse)] bg-[var(--color-wk-accent)] rounded-[var(--radius-wk-md)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer" x-text="editIndex === null ? 'Add' : 'Apply'"></button>
                </div>
            </div>
            </template>
        </div>

        {{-- Clear all (only when filters exist) --}}
        <button
            type="button"
            x-show="filters.length > 0"
            x-cloak
            @click="clearAll()"
            class="px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] rounded-[var(--radius-wk-md)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
        >Clear all</button>
    </div>

    {{-- Optional result-count / status slot — wrap it in aria-live in your app
         so screen readers hear the count change as filters are applied. --}}
    @isset($status)
        <div class="mt-2 text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]" aria-live="polite">{{ $status }}</div>
    @endisset
</div>
