@props([
    'name' => null,
    'id' => null,
    'multiple' => config('wirekit.components.file-upload.multiple', false),
    'accept' => config('wirekit.components.file-upload.accept', null),
    'size' => config('wirekit.components.file-upload.size', 'md'),
    'disabled' => false,
    'label' => 'Drop files here or click to browse',
    'hint' => null,
    'error' => null,
    'scope' => null,
])

@php
    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

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
        'p-[var(--padding-wk-y-xs)]',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'rounded-[var(--radius-wk-md)]',
    ]);
@endphp

{{-- Alpine: tracks drag-over state + an array of selected file metadata for preview.
     We read the files directly from the native input change event. --}}
<div
    x-data="{
        dragging: false,
        files: [],
        _rawFiles: [],
        formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return (bytes / Math.pow(k, i)).toFixed(1) + ' ' + sizes[i];
        },
        handleFiles(fileList) {
            // Store raw File objects so we can rebuild the input's FileList on remove.
            this._rawFiles = Array.from(fileList);
            this.files = this._rawFiles.map(f => ({ name: f.name, size: f.size }));
        },
        removeFile(index) {
            // Remove from both display list and raw File array.
            this._rawFiles.splice(index, 1);
            this.files.splice(index, 1);
            // Rebuild the native input's FileList via DataTransfer.
            const dt = new DataTransfer();
            this._rawFiles.forEach(f => dt.items.add(f));
            this.$refs.input.files = dt.files;
            // Trigger change so Livewire picks up the updated file list.
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }"
    {{ $attributes->class(['w-full']) }}
>
    <label
        for="{{ $uploadId }}"
        :class="dragging
            ? 'border-[var(--color-wk-accent)] bg-[var(--color-wk-bg-muted)]'
            : '{{ $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border)]' }} hover:border-[var(--color-wk-accent)]'"
        class="{{ $dropzoneClasses }}"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="
            dragging = false;
            const dt = $event.dataTransfer;
            if (dt && dt.files) {
                $refs.input.files = dt.files;
                handleFiles(dt.files);
                $refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        "
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
            aria-describedby="{{ trim(($hint ? $hintId : '') . ' ' . ($hasError ? $errorId : '')) }}"
            @change="handleFiles($event.target.files)"
            class="sr-only"
        />
    </label>

    {{-- Selected files list — rendered only when files exist.
         Each item shows filename (truncated), size, and a remove button on hover. --}}
    <ul class="{{ $listClasses }}" style="list-style: none; margin: 0; padding: 0;" x-show="files.length > 0" x-cloak>
        <template x-for="(file, index) in files" :key="file.name">
            <li class="{{ $fileItemClasses }}">
                {{-- Filename — min-w-0 + truncate prevents long names from expanding the container --}}
                <span class="truncate min-w-0" x-text="file.name"></span>
                {{-- File size — fixed width so it doesn't shift when remove button appears --}}
                <span class="text-[color:var(--color-wk-text-muted)] tabular-nums shrink-0" x-text="formatBytes(file.size)"></span>
                {{-- Remove button — chip-style X aligned with <x-wirekit::tags-input>:
                     always visible, subtle rounded background on hover, danger text on hover. --}}
                <button
                    type="button"
                    @click="removeFile(index)"
                    class="shrink-0 p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
                    :aria-label="'Remove ' + file.name"
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
        <p id="{{ $errorId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
