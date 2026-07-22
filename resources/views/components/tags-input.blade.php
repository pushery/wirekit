@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    'error' => null,
    'value' => [],
    'maxTags' => null,
    'placeholder' => __('Add a tag...'),
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    $id = $attributes->get('id', $attributes->get('name', 'tags-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Normalize the initial value into an array of strings. Accepts a real
    // array (e.g. `:value="['Laravel', 'Livewire']"`) or a comma-separated
    // string (e.g. `value="Laravel,Livewire"`); both shapes appear in
    // existing developer codebases.
    if (is_string($value)) {
        $initialTags = array_values(array_filter(array_map('trim', explode(',', $value)), fn ($t) => $t !== ''));
    } elseif (is_array($value)) {
        $initialTags = array_values(array_map(fn ($t) => (string) $t, $value));
    } else {
        $initialTags = [];
    }

    // Container classes — styled like an input field but wraps tag chips + text input.
    // Tight container padding (`p-1` = 4 px on every side) keeps the chips
    // hugging the input-box border instead of floating inside a large
    // inset frame. The chips already carry their own internal padding;
    // the container only needs enough room for the focus ring and a
    // single-pixel border without doubling the visual whitespace.
    $containerClasses = WireKit::resolveClasses('tags-input', 'base', implode(' ', [
        'flex flex-wrap items-center gap-1',
        'min-h-[var(--size-wk-md)]',
        'p-1',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-input)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border-strong)]';

    /*
     * Tag chip classes — `px-2 py-1` (8 px horizontal, 4 px vertical).
     * Symmetric on each axis (left == right, top == bottom) but more
     * generous horizontally than vertically so the label has breathing
     * room around its trailing X-button without inflating the chip
     * height. The earlier `p-1` (uniform 4 px) made chips read as
     * cramped against multi-word labels; the previous-previous
     * `pl-x-sm pr-1 py-1` was asymmetric left-vs-right which looked
     * lopsided. `px-2 py-1` keeps both axes symmetric AND gives the
     * label enough horizontal slack to read comfortably.
     */
    $tagClasses = implode(' ', [
        'inline-flex items-center gap-1',
        'px-2 py-1',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
    ]);

    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id . '-input'">{{ $label }}</x-wirekit::label>
    @endif

    <div
        x-data="wirekitTagsInput({ name: '{{ $name }}', maxTags: {{ $maxTags ?? 'null' }}, tags: @js($initialTags) })"
        {{ $attributes->only('class') }}
    >
        {{-- Hidden inputs for form submission — one per tag --}}
        <template x-for="(tag, i) in tags" :key="i">
            <input type="hidden" :name="'{{ $name }}[]'" :value="tag" />
        </template>

        <div class="{{ $containerClasses }} {{ $stateClasses }}" @click="$refs.input.focus()">
            {{-- Tag chips --}}
            <template x-for="(tag, i) in tags" :key="'tag-'+i">
                <span class="{{ $tagClasses }}">
                    <span x-text="tag"></span>
                    <button
                        type="button"
                        @click="removeTag(i)"
                        :aria-label="'Remove ' + tag"
                        class="p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
                    >
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                    </button>
                </span>
            </template>

            {{--
                Text input for new tags — carries its own `px-2` padding so the
                placeholder text reads as comfortably indented from the input-
                box border (matching a regular `<x-wirekit::input>`), without
                inflating the OUTER container's gutter and pushing the tag
                chips away from the border. The container stays tight (`p-1`)
                so chips hug the edge; this `px-2` only affects the text-input
                slot, giving the empty-state placeholder its expected
                breathing room.
            --}}
            <input
                type="text"
                id="{{ $id }}-input"
                x-ref="input"
                placeholder="{{ $placeholder }}"
                {{-- When no visible <label> is rendered (no `label` prop), the
                     type-a-tag input would have no accessible name — a
                     placeholder is not a name (WCAG 2.1 AA / axe `label`).
                     Fall back to the placeholder as the aria-label so the
                     control is always named; when a label IS present the
                     <label for> above owns the name and we must NOT override
                     it with aria-label. --}}
                @if($attributes->get('aria-label')) aria-label="{{ $attributes->get('aria-label') }}" @elseif(! $label) aria-label="{{ $placeholder }}" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @keydown.enter.prevent="addTag()"
                @keydown.comma.prevent="addTag()"
                @keydown.backspace="onBackspace($event)"
                class="wk-field flex-1 min-w-[80px] px-2 bg-transparent text-[color:var(--color-wk-text)] text-[length:var(--text-wk-md)] placeholder:text-[color:var(--color-wk-text-placeholder)] outline-none"
            />
        </div>
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
