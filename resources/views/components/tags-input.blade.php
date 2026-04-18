@props([
    'label' => null,
    'hint' => null,
    'error' => null,
    'maxTags' => null,
    'placeholder' => 'Add a tag...',
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $id = $attributes->get('id', $attributes->get('name', 'tags-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Container classes — styled like an input field but wraps tag chips + text input
    $containerClasses = WireKit::resolveClasses('tags-input', 'base', implode(' ', [
        'flex flex-wrap items-center gap-1',
        'min-h-[var(--size-wk-md)]',
        'px-[var(--padding-wk-x-md)] py-1',
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
        : 'border-[var(--color-wk-border)]';

    // Tag chip classes — pl for text padding, pr reduced since button has its own padding
    $tagClasses = implode(' ', [
        'inline-flex items-center gap-1',
        'pl-[var(--padding-wk-x-sm)] pr-1 py-1',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
    ]);

    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id . '-input'">{{ $label }}</x-wirekit::label>
    @endif

    <div
        x-data="wirekitTagsInput({ name: '{{ $name }}', maxTags: {{ $maxTags ?? 'null' }} })"
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
                        class="p-0.5 rounded-[var(--radius-wk-sm)] text-[var(--color-wk-text-muted)] hover:text-[var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
                    >
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                    </button>
                </span>
            </template>

            {{-- Text input for new tags --}}
            <input
                type="text"
                id="{{ $id }}-input"
                x-ref="input"
                placeholder="{{ $placeholder }}"
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @keydown.enter.prevent="addTag()"
                @keydown.comma.prevent="addTag()"
                @keydown.backspace="onBackspace($event)"
                class="flex-1 min-w-[80px] bg-transparent text-[var(--color-wk-text)] text-[length:var(--text-wk-md)] placeholder:text-[var(--color-wk-text-placeholder)] outline-none"
            />
        </div>
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
