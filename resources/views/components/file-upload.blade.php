{{-- optimistic-ui: n/a — passthrough
     Livewire owns the upload itself, including its own progress and error reporting. An optimistic layer here would duplicate a protocol that already reports what it is doing — and a file cannot be shown as stored before it is stored. --}}
@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'name' => null,
    'id' => null,
    'multiple' => config('wirekit.components.file-upload.multiple', false),
    'accept' => config('wirekit.components.file-upload.accept', null),
    'size' => config('wirekit.components.file-upload.size', 'md'),
    'disabled' => false,
    'label' => __('wirekit::Drop files here or click to browse'),
    // Accessible name for each file's remove button. The `:name` placeholder is
    // replaced with the file name at runtime, so translators control word order
    // (some languages put the object before the verb). Overridable per call site.
    'removeLabel' => __('wirekit::Remove :name'),
    'hint' => null,
    'error' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('file-upload', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);

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


    // File upload — dropzone UI with click-to-browse fallback. Alpine tracks
    // drag-over state and the list of selected files for live preview.
    $uploadId = $id ?? ($name ? 'wk-upload-' . $name : 'wk-upload-' . Str::random(6));
    $errorId = $uploadId . '-error';
    $hintId = $uploadId . '-hint';

    // Laravel errors bag check.
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // Dropzone sizing per size token.
    $dropzonePadding = match ($size) {
        'sm' => 'p-[var(--padding-wk-y-sm)]',
        'lg' => 'p-[var(--padding-wk-y-lg)]',
        default => 'p-[var(--padding-wk-y-md)]',
    };

    // Dropzone base: dashed border with drag-highlight state via x-bind:class.
    // w-full ensures the dropzone matches the container width so file list items
    // below never extend beyond the dropzone boundaries.
    $dropzoneClasses = WireKit::resolveClasses('file-upload', 'dropzone', implode(' ', [
        'w-full',
        'flex flex-col items-center justify-center gap-[var(--padding-wk-y-sm)]',
        'text-center',
        'border-2 border-dashed',
        'rounded-[var(--radius-wk-lg)]',
        'cursor-pointer',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $dropzonePadding,
    ]), $scope);

    // Icon + label text styling.
    $iconClasses = 'w-8 h-8 text-[color:var(--color-wk-text-subtle)]';
    $labelClasses = 'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]';

    // File list below the dropzone — w-full prevents long filenames from
    // growing beyond the dropzone width; gap-sm for comfortable vertical spacing.
    // list-none + m-0 + p-0 strip the browser-default <ul> disc markers and
    // marker indent; the file list renders icon + filename rows, bullets would clutter.
    $listClasses = WireKit::resolveClasses('file-upload', 'list', 'list-none m-0 p-0 w-full mt-[var(--padding-wk-y-sm)] flex flex-col gap-[var(--space-wk-sm)]', $scope);
    // min-w-0 prevents flex children from overflowing when filenames are long.
    // group class enables hover-reveal of the remove button.
    $fileItemClasses = implode(' ', [
        'group flex items-center gap-[var(--padding-wk-x-sm)] min-w-0',
        'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'rounded-[var(--radius-wk-md)]',
    ]);
@endphp

{{-- Alpine: tracks drag-over state + an array of selected file metadata for preview.
     We read the files directly from the native input change event. --}}
<div
    {{-- The file list, the byte formatting and the drop handling live in the
         factory (resources/js/components/file-upload.js). The drop handler was
         four statements and a `const`, which Alpine's CSP build does not parse —
         under a strict Content-Security-Policy dropping a file did nothing while
         clicking the label still worked. --}}
    x-data="wirekitFileUpload({ removeLabel: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $removeLabel) }} })"
    {{-- `wire:model` is peeled off here and re-attached to the file input below.
         Livewire decides what a model binding MEANS by reading the element's
         type: on a `<input type="file">` it takes the upload path, and on
         anything else it binds a plain value. On this `<div>` the type is
         undefined, so the binding silently became a value binding reading a
         `.value` that does not exist — the file list filled in, the server
         received nothing, and neither side reported an error. --}}
    {{ $attributes->except('aria-label')->whereDoesntStartWith('wire:model')->class(['w-full']) }}
>
    <label
        for="{{ $uploadId }}"
        :class="dragging
            ? 'border-[var(--color-wk-accent)] bg-[var(--color-wk-bg-muted)]'
            : {{ \Pushery\WireKit\Support\AlpinePayload::string(($hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]').' hover:border-[var(--color-wk-accent)]') }}"
        class="{{ $dropzoneClasses }}"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="handleDrop($event)"
    >
        {{-- Upload icon — decorative; the label text describes the action. --}}
        <svg class="{{ $iconClasses }}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v6.69l2.72-2.72a.75.75 0 111.06 1.06l-4 4a.75.75 0 01-1.06 0l-4-4a.75.75 0 111.06-1.06l2.72 2.72V3.75A.75.75 0 0110 3z" clip-rule="evenodd"/>
            <path d="M3.75 13a.75.75 0 01.75.75v2.5a.75.75 0 00.75.75h9.5a.75.75 0 00.75-.75v-2.5a.75.75 0 011.5 0v2.5a2.25 2.25 0 01-2.25 2.25h-9.5A2.25 2.25 0 013 16.25v-2.5a.75.75 0 01.75-.75z"/>
        </svg>
        <span class="{{ $labelClasses }}">{{ $label }}</span>

        {{-- Hidden native input — click on label triggers it, drag-drop replaces files. --}}
        <input
            type="file"
            x-ref="input"
            @if($name) name="{{ $multiple ? $name . '[]' : $name }}" @endif
            id="{{ $uploadId }}"
            @if($multiple) multiple @endif
            @if($accept) accept="{{ $accept }}" @endif
            @if($disabled) disabled @endif
            @if($hasError) aria-invalid="true" @endif
            @if($attributes->get('aria-label')) aria-label="{{ $attributes->get('aria-label') }}" @endif
            {{-- The binding belongs on the control, not the wrapper — same shape
                 as segmented-control's hidden input. `whereStartsWith` keeps the
                 modifiers (`wire:model.live`, `.blur`) attached to it. --}}
            {{ $attributes->whereStartsWith('wire:model') }}
            aria-describedby="{{ trim(($hint ? $hintId : '') . ' ' . ($hasError ? $errorId : '')) }}"
            @change="handleFiles($event.target.files)"
            class="sr-only"
        />
    </label>

    {{-- Selected files list — rendered only when files exist.
         Each item shows filename (truncated), size, and a remove button on hover.
         Inline style is REQUIRED for the sandbox-iframe rendering context
         where Tailwind is out of scope — `list-none` / `m-0` / `p-0` classes
         wouldn't resolve, browser UA disc bullets + UA margin would show.
         Margin-top carries the dropzone-gap token directly via inline style so
         the gap resolves in BOTH contexts (developer app + sandbox iframe; both
         load wirekit.css so the CSS variable resolves). The class-based
         `mt-[var(--padding-wk-y-sm)]` in $listClasses is now redundant but kept
         for documentation parity. Enforced by ListStyleAntiDriftTest. --}}
    <ul class="{{ $listClasses }}" style="list-style: none; padding: 0; margin: var(--padding-wk-y-sm) 0 0 0;" x-show="files.length > 0" x-cloak>
        <template x-for="(file, index) in files" :key="file.name">
            <li class="{{ $fileItemClasses }}">
                {{-- Filename — flex-1 grows to fill available space so the size + X get
                     pushed to the right edge of the row (standard file-uploader UX).
                     min-w-0 + truncate prevents long names from blowing out the flex line. --}}
                <span class="flex-1 truncate min-w-0" x-text="file.name"></span>
                {{-- File size — fixed width so it doesn't shift when remove button appears --}}
                <span class="text-[color:var(--color-wk-text-muted)] tabular-nums shrink-0" x-text="formatBytes(file.size)"></span>
                {{-- Remove button — chip-style X aligned with <x-wirekit::tags-input>:
                     always visible, subtle rounded background on hover, danger text on hover. --}}
                {{-- The visible chip stays small (p-0.5 + a 14px X), but a centered
                     44x44 ::before expands the CLICKABLE target to the WCAG 2.5.5 AAA
                     size — `relative` anchors it, `before:content-['']` renders it,
                     h-11/w-11 = 44px. The hover background + focus ring stay on the
                     small visual chip; only the pointer/touch target is enlarged. --}}
                <button
                    type="button"
                    @click="removeFile(index)"
                    class="relative shrink-0 p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-pointer before:absolute before:left-1/2 before:top-1/2 before:h-11 before:w-11 before:-translate-x-1/2 before:-translate-y-1/2 before:content-['']"
                    :aria-label="removeLabel.replace(':name', file.name)"
                >
                    {{-- X icon — decorative, label is on the button. Matches the
                         12x12 viewBox + 3.5 sizing used by tags-input for visual parity. --}}
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 12" fill="currentColor" aria-hidden="true">
                        <path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/>
                    </svg>
                </button>
            </li>
        </template>
    </ul>

    @if($hint && !$hasError)
        <p id="{{ $hintId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($hasError)
        {{-- Error message — aria-describedby'd above, and visually distinguished. --}}
        <p id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
