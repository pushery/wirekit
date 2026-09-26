{{-- optimistic-ui: n/a — passthrough
     Livewire owns the upload itself, including its own progress and error reporting. An optimistic layer here would duplicate a protocol that already reports what it is doing — and a file cannot be shown as stored before it is stored. --}}
@props([
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk. StrictnessGate did not
    // complain either, because `required` is in its HTML passthrough list — so it looked like
    // a legitimate attribute all the way down. The result was a required field that submits
    // empty, in the same form as a plain input that behaves correctly.
    'required' => false,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'name' => null,
    'id' => null,
    'multiple' => config('wirekit.components.file-upload.multiple', false),
    'accept' => config('wirekit.components.file-upload.accept', null),
    // `capture` is its OWN attribute, not a token inside `accept`. The docs table used
    // to sell camera capture as `accept="image/*;capture=camera"`, which is a legacy
    // Android spelling that no current browser reads and which sets a MIME filter no
    // file matches. It could not have worked from the caller's side either: this input
    // takes named attributes and `wire:model*` only, never the whole bag, so a
    // developer passing `capture` themselves got nothing on the element.
    'capture' => config('wirekit.components.file-upload.capture', null),
    'size' => config('wirekit.components.file-upload.size', 'md'),
    // Shape, not chrome. `default` is the full drop AREA — a block-level dashed
    // rectangle that fills its container, which is right for a field in a form.
    // `compact` is the same control at button height, shrink-to-fit, for a dense
    // row beside badges and `size="sm"` buttons where a full-width area would
    // push every neighbor onto its own line. Both keep the native input, the
    // click-to-browse path and the drop handlers — only the shape differs.
    'variant' => 'default',
    'disabled' => false,
    // Null rather than the sentence itself, so the component can tell "the caller
    // said nothing" from "the caller chose this text". The shipped default is
    // written for a large area; in `compact` it would make the control wider than
    // the row it exists to fit, so there it names the control without painting.
    // Resolved below — a null default also keeps TranslatableDefaultsGuardTest
    // satisfied, which is what a hard literal here would fail.
    'label' => null,
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
    // Same contract, different spelling of the default: a `config()` fallback declares a
    // boolean as surely as a literal does. This one decides both the `multiple` attribute on
    // the input and whether the field posts as `name[]`, so an unbound `multiple="false"`
    // changed the shape of the submitted payload as well as the picker.
    $multiple = BooleanProp::from($multiple, false);
    $required = BooleanProp::from($required, false);

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

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // File upload — dropzone UI with click-to-browse fallback. Alpine tracks
    // drag-over state and the list of selected files for live preview.
    //
    // The property the input is bound to, if any. The factory watches it so the list empties
    // when a save sets it back to empty, and it seeds the id below.
    $boundModel = $attributes->whereStartsWith('wire:model')->first();
    $boundModel = is_string($boundModel) && $boundModel !== '' ? $boundModel : null;

    // The id has to be the same on every render. Livewire's morph recognizes an element by it,
    // so an id drawn fresh per render made each round trip REPLACE the input — and Livewire
    // fires its upload events on the element it bound to, which by then had left the page, so
    // no form ever heard `livewire-upload-finish`. Seeded from the bound property when there is
    // no name (`wk-upload-photos`), counted per render order when there is neither.
    $uploadId = $id ?? ($name ? 'wk-upload-' . $name : WireKit::stableId('wk-upload', $boundModel));
    $errorId = $uploadId . '-error';
    $hintId = $uploadId . '-hint';

    // Laravel errors bag check, guarded on the name exactly as field.blade.php
    // does. `MessageBag::has(null)` falls through to `any()`, so an unguarded
    // read makes a file-upload with no `name` report itself invalid the moment
    // ANY unrelated field on the page fails validation — a red border and an
    // `aria-invalid` on a control nobody validated.
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // The paragraph and the idref pointing at it move together. `$hasError` can
    // be true with nothing to say (`error=""` plus a bag hit), and a described-by
    // resolving to an empty element announces the control as invalid without
    // saying why — a WCAG 3.3.1 failure the markup looks fine in.
    $showsError = $hasError && $errorMessage;

    // The described-by list is computed here rather than inline on the input, because an
    // IDREFS attribute is only valid when it names at least one element: with neither a
    // hint nor an error the inline form emitted `aria-describedby=""`, which is an invalid
    // attribute value (axe `aria-valid-attr-value`) rather than an absent description.
    // Same shape as date-picker, which carries the reference implementation.
    // The hint paragraph below renders only `@if($hint && ! $hasError)`, so an
    // error state must not keep naming it: an idref whose element is not in the
    // document is dropped silently by assistive technology, and the field is then
    // described by less than the markup claims — or, with only a hint set, by
    // nothing at all. Compose from what this render actually emits.
    $describedBy = trim(($hint && ! $hasError ? $hintId : '') . ' ' . ($showsError ? $errorId : ''));
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));

    // The HTML spec's two values. `camera` and `camcorder` were an Android-era spelling
    // and are not in it; accepting them silently would put an attribute on the element that
    // the browser ignores, which is the failure the docs row already made once.
    $captureValue = match ($capture) {
        null, 'user', 'environment' => $capture,
        default => WireKit::validateProp('file-upload', 'capture', $capture, ['user', 'environment']),
    };

    $variantValue = match ($variant) {
        'default', 'compact' => $variant,
        default => WireKit::validateProp('file-upload', 'variant', $variant, ['default', 'compact']),
    };

    // The label sentence is the accessible name in BOTH variants. In `compact` it
    // is painted only when the caller chose it: the shipped default reads
    // "Drop files here or click to browse", which describes an area, and a
    // compact control is not one. Hiding it visually keeps the name — `sr-only`
    // clips, it does not remove the node from the accessibility tree, so the
    // <label> still names the input exactly as before.
    $labelText = $label ?? __('wirekit::Drop files here or click to browse');
    $labelIsVisible = $variantValue !== 'compact' || $label !== null;

    // Dropzone sizing per size token.
    $dropzonePadding = match ($size) {
        'sm' => 'p-[var(--padding-wk-y-sm)]',
        'lg' => 'p-[var(--padding-wk-y-lg)]',
        default => 'p-[var(--padding-wk-y-md)]',
    };

    // Compact sizing mirrors <x-wirekit::button> exactly — same height token, same
    // horizontal padding token, same radius token per size — so a compact upload
    // and a `size="sm"` button in the same row share one baseline instead of
    // missing it by a couple of pixels. Deliberately the button's numbers rather
    // than new ones: the whole point of the variant is to stand next to buttons.
    $compactSizing = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] px-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] px-[var(--padding-wk-x-lg)] rounded-[var(--radius-wk-md)]',
        default => 'h-[var(--size-wk-md)] px-[var(--padding-wk-x-md)] rounded-[var(--radius-wk-md)]',
    };

    // Dropzone base: dashed border with drag-highlight state via x-bind:class.
    // w-full ensures the dropzone matches the container width so file list items
    // below never extend beyond the dropzone boundaries.
    //
    // The focus ring sits on the LABEL, driven by `has-[:focus-visible]`, because
    // the only focusable element here is the native input and that input is
    // `sr-only` — a 1x1 clipped box. A ring on the input paints inside that clip
    // and is invisible, so a keyboard user tabbing into the form saw no change
    // whatsoever: same border color, same shadow, a pixel-identical dropzone,
    // and then Enter opened a system file dialog from a position nothing on
    // screen had pointed at. `has-[:focus-visible]` rather than `focus-within`
    // for the same reason the card variants of checkbox and radio use it — those
    // wrap an `sr-only` input in a bordered label exactly like this one, and
    // `focus-within` would also fire on the mouse click that the pointer user
    // can already see the result of.
    //
    // The two shapes share everything that makes this a DROP target — the dashed
    // border, the pointer, the drag transition, the focus ring — and differ only
    // in flow: `default` is a block-level column that fills its container,
    // `compact` is an inline row at button height that shrinks to its content.
    $dropzoneShape = $variantValue === 'compact'
        ? implode(' ', [
            // `inline-flex` (not `flex`) is the whole variant in one word: the
            // control stops claiming the line and sits in the row it was placed in.
            'wk-touch-target inline-flex items-center justify-center shrink-0',
            'max-w-full',
            'gap-[var(--padding-wk-x-sm)]',
            // The token border width rather than `border-2`: at button height a
            // 2px dashed edge reads as a heavier control than the buttons beside it.
            'border-[length:var(--border-wk-width)] border-dashed',
            $compactSizing,
        ])
        : implode(' ', [
            'w-full',
            'flex flex-col items-center justify-center gap-[var(--padding-wk-y-sm)]',
            'text-center',
            'border-2 border-dashed',
            'rounded-[var(--radius-wk-lg)]',
            $dropzonePadding,
        ]);

    $dropzoneClasses = WireKit::resolveClasses('file-upload', 'dropzone', implode(' ', [
        $dropzoneShape,
        'cursor-pointer',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'has-[:focus-visible]:ring-[length:var(--ring-wk-width)]',
        'has-[:focus-visible]:ring-[var(--color-wk-ring)]',
    ]), $scope);

    // Icon + label text styling. The compact icon drops to the catalog's inline
    // glyph size so it sits on the text baseline of the row rather than setting
    // the control's height on its own.
    $iconClasses = ($variantValue === 'compact' ? 'h-4 w-4 shrink-0' : 'w-8 h-8').' text-[color:var(--color-wk-text-subtle)]';
    $labelClasses = 'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]'
        // A compact control lives in a row whose width it does not control, so a
        // long caller-supplied label truncates instead of stretching the row.
        .($variantValue === 'compact' ? ' truncate min-w-0' : '');

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
        // At least as tall as the remove button's touch area. The button draws a small chip and
        // takes taps across that area, centered on it; with rows shorter than the area, the lower
        // part of one button's area lay over the next row's button, and a tap just below a file's
        // own button removed the file under it. `items-center` keeps the button in the middle of
        // its row, so neighbors now stand at least one area apart, plus the list gap.
        'min-h-[var(--size-wk-touch-target)]',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'rounded-[var(--radius-wk-md)]',
    ]);

    // What a screen reader is told once a file is gone. The visible list is the only
    // other feedback a removal gives, which is no feedback at all for a reader who
    // cannot see it.
    //
    // Internal and translated rather than a prop, which is the shape the tags input
    // already uses for the same gesture: the accessible NAME of the remove button is a
    // caller's business, the component's own narration is not, and a new public prop
    // would be API surface for a sentence nobody has asked to reword. Reusing that
    // control's catalog key keeps the two from drifting apart in wording, and adds
    // nothing to the eight catalogs.
    //
    // Assembled from a `:name` placeholder because a sentence concatenated in
    // JavaScript cannot be translated and word order is not the same in every language.
    $removedMessage = __('wirekit::Removed :name');
@endphp

{{-- Alpine: tracks drag-over state + an array of selected file metadata for preview.
     We read the files directly from the native input change event. --}}
<div
    {{-- The file list, the byte formatting and the drop handling live in the
         factory (resources/js/components/file-upload.js). The drop handler was
         four statements and a `const`, which Alpine's CSP build does not parse —
         under a strict Content-Security-Policy dropping a file did nothing while
         clicking the label still worked. --}}
    x-data="wirekitFileUpload({ removeLabel: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $removeLabel) }}, removedMessage: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $removedMessage) }}, model: {{ \Pushery\WireKit\Support\AlpinePayload::from($boundModel) }} })"
    {{-- `wire:model` is peeled off here and re-attached to the file input below.
         Livewire decides what a model binding MEANS by reading the element's
         type: on a `<input type="file">` it takes the upload path, and on
         anything else it binds a plain value. On this `<div>` the type is
         undefined, so the binding silently became a value binding reading a
         `.value` that does not exist — the file list filled in, the server
         received nothing, and neither side reported an error. --}}
    {{-- The wrapper follows the variant's flow. `w-full` on a compact control
         would hand back exactly the line the variant exists to give up, so it
         becomes an inline column that hugs its content — the file list, hint and
         error still stack beneath the control, just no wider than they need. --}}
    {{ $attributes->except(['aria-label', 'aria-describedby'])->whereDoesntStartWith('wire:model')->class([
        'w-full' => $variantValue !== 'compact',
        'inline-flex max-w-full flex-col items-start align-middle' => $variantValue === 'compact',
    ]) }}
>
    {{-- The removal's own live region. Unconditional and starting EMPTY, for the reason
         the pattern states everywhere it appears: a live region that arrives together
         with its text is a new node, and a new node announces nothing. The only other
         `aria-live` in this file belongs to the error paragraph, which renders only when
         there IS an error — so before this, a removal was silent by construction. --}}
    <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="fileAnnouncement"></div>

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
        {{-- `sr-only` rather than omitted when compact carries no caller label:
             the <label> must still name the input, and a control whose only
             content is an aria-hidden icon has no accessible name at all. --}}
        <span class="{{ $labelIsVisible ? $labelClasses : 'sr-only' }}">{{ $labelText }}@if($required)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</span>

        {{-- Hidden native input — click on label triggers it, drag-drop replaces files. --}}
        <input
            type="file"
            x-ref="input"
            @if($name) name="{{ $multiple ? $name . '[]' : $name }}" @endif
            id="{{ $uploadId }}"
            @if($multiple) multiple @endif
            {{-- Native, on the file input itself: it is a real form control, so the browser's
                 own constraint validation is the strongest carrier available. --}}
            @if($required) required aria-required="true" @endif
            @if($accept) accept="{{ $accept }}" @endif
            @if($captureValue) capture="{{ $captureValue }}" @endif
            @if($disabled) disabled @endif
            @if($hasError) aria-invalid="true" @endif
            @if($attributes->get('aria-label')) aria-label="{{ $attributes->get('aria-label') }}" @endif
            {{-- The binding belongs on the control, not the wrapper — same shape
                 as segmented-control's hidden input. `whereStartsWith` keeps the
                 modifiers (`wire:model.live`, `.blur`) attached to it. --}}
            {{ $attributes->whereStartsWith('wire:model') }}
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
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
    <ul data-wk-prose-skip role="list" class="{{ $listClasses }}" style="list-style: none; padding: 0; margin: var(--padding-wk-y-sm) 0 0 0;" x-show="files.length > 0" x-cloak>
        <template x-for="(file, index) in files" :key="file.name">
            <li data-wk-prose-skip class="{{ $fileItemClasses }}">
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
                     and it takes its size from --size-wk-touch-target rather than a
                     literal, so a project that moves the floor moves this with it.
                     The hover background + focus ring stay on the
                     small visual chip; only the pointer/touch target is enlarged. --}}
                <button
                    type="button"
                    {{-- What `_focusAfterRemoval` queries for. The removal destroys this
                         very button, so focus has to be handed to the one that took the
                         row's place — and a marker the template does not carry is the
                         quiet half of that bug: the query returns nothing, the move falls
                         through to its last resort, and focus lands somewhere plausible
                         enough that nobody notices it is the wrong somewhere. --}}
                    data-wk-file-remove
                    @click="removeFile(index)"
                    class="relative shrink-0 p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-pointer before:absolute before:left-1/2 before:top-1/2 before:h-[var(--size-wk-touch-target)] before:w-[var(--size-wk-touch-target)] before:-translate-x-1/2 before:-translate-y-1/2 before:content-['']"
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
        <p data-wk-prose-skip id="{{ $hintId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($showsError)
        {{-- Error message — aria-describedby'd above, and visually distinguished. --}}
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif
</div>
