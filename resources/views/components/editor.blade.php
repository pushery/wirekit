@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'name' => null,
    'id' => null,
    'value' => null,
    'format' => 'html',          // html | json
    'extensions' => null,        // array of editor extension NAMES (hint for window.wirekitEditor)
    'toolbar' => 'basic',        // false | 'basic' | 'full' | 'custom'
    'placeholder' => null,
    'maxLength' => null,
    'editable' => true,
    'label' => null,
    'hint' => null,
    'error' => null,
    'size' => 'md',              // sm | md | lg
    // Cap the editable area's height (any CSS length, e.g. '20rem' / '50vh'). The
    // content host already scrolls (overflow-y-auto); maxHeight gives it a CEILING
    // so a long document scrolls INSIDE the field instead of growing the page
    // downward. null = grow with content (the size's min-height is the only bound).
    // Width is set the normal way — the editor is w-full, so constrain it with a
    // class/style on the component (e.g. style="max-width: 40rem" or a max-w-* class).
    'maxHeight' => null,
    'autofocus' => false,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config (WIRE-204).
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    $formatValue = match ($format) {
        'html', 'json' => $format,
        default => WireKit::validateProp('editor', 'format', $format, ['html', 'json']),
    };
    $sizeValue = match ($size) {
        'sm', 'md', 'lg' => $size,
        default => WireKit::validateProp('editor', 'size', $size, ['sm', 'md', 'lg']),
    };

    $id = $id ?? ($name ? 'wk-editor-' . Str::slug($name) : 'wk-editor-' . Str::random(6));

    // Error detection: explicit prop OR Laravel validation bag.
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($name ? ($errors ?? null)?->first($name) : null);

    // First-paint content (format=html only). Developer-controlled saved document —
    // the security contract is "sanitize on STORE" (Tiptap re-sanitizes on parse).
    $initialHtml = $formatValue === 'html' && is_string($value) ? $value : '';

    $hintId = "{$id}-hint";
    $errorId = "{$id}-error";
    $describedBy = trim(($hint && ! $hasError ? $hintId : '') . ' ' . ($hasError ? $errorId : ''));

    // Route wire:model to the <textarea x-ref="input"> (the element the editor writes to
    // and fires its input event on), NOT the wrapper div — otherwise Livewire binds to a
    // div that never emits input and the value is silently lost. Modifiers (.live / .blur
    // / .debounce) are preserved; everything else still lands on the wrapper (WIRE-167).
    $wireModel = $attributes->whereStartsWith('wire:model');
    $rest = $attributes->whereDoesntStartWith('wire:model');

    // Toolbar preset → command vocabulary. A passed <x-slot:toolbar> (a ComponentSlot)
    // selects the 'custom' path so the caller's toolbar actually renders — otherwise the
    // slot silently collapsed to the 'basic' preset and was never shown (WIRE-168).
    $toolbarValue = $toolbar === false
        ? false
        : ($toolbar instanceof \Illuminate\View\ComponentSlot ? 'custom' : (is_string($toolbar) ? $toolbar : 'basic'));
    $presetCommands = match ($toolbarValue) {
        'full' => ['bold', 'italic', 'underline', 'strike', 'code', 'link', '|', 'heading-1', 'heading-2', 'heading-3', 'paragraph', 'quote', '|', 'bullet-list', 'ordered-list', 'code-block', 'horizontal-rule', '|', 'undo', 'redo'],
        'basic' => ['bold', 'italic', 'strike', 'link', '|', 'bullet-list', 'ordered-list'],
        default => [],
    };

    // Min content height per size token.
    $minHeight = match ($sizeValue) {
        'sm' => 'min-h-[8rem]',
        'lg' => 'min-h-[18rem]',
        default => 'min-h-[12rem]',
    };

    // Config handed to the wirekitEditor Alpine factory (→ window.wirekitEditor).
    $jsConfig = [
        'value' => $value !== null && ! is_string($value) ? json_encode($value) : $value,
        'format' => $formatValue,
        'editable' => (bool) $editable,
        'extensions' => $extensions ?? config('wirekit.components.editor.extensions', []),
        'placeholder' => $placeholder,
        'maxLength' => $maxLength,
        'ariaLabel' => $label ? null : ($name ? Str::headline((string) $name) : 'Rich text editor'),
        'ariaDescribedby' => $describedBy !== '' ? $describedBy : null,
        'ariaInvalid' => (bool) $hasError,
        // Plumbed to the Tiptap path too (not just the textarea fallback's
        // data-autofocus) — editor.js focuses the editor in onCreate when set.
        'autofocus' => (bool) $autofocus,
    ];

    $wrapperClasses = WireKit::resolveClasses('editor', 'wrapper', implode(' ', [
        'overflow-hidden',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'bg-[var(--color-wk-bg-input)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-within:ring-[length:var(--ring-wk-width)]',
        $hasError ? 'focus-within:ring-[var(--color-wk-danger)]' : 'focus-within:ring-[var(--color-wk-ring)]',
    ]), $scope);
@endphp

<div class="w-full space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id">{{ $label }}</x-wirekit::label>
    @endif

    <div
        x-data="wirekitEditor(@js($jsConfig))"
        {{ $rest->class(['w-full', $wrapperClasses]) }}
    >
        {{-- Toolbar: auto-rendered preset, OR the custom slot when toolbar="custom". --}}
        @if($editable && $toolbarValue === 'custom' && isset($toolbar) && $toolbar instanceof \Illuminate\View\ComponentSlot)
            <div x-ref="toolbar">{{ $toolbar }}</div>
        @elseif($editable && $toolbarValue !== false && ! empty($presetCommands))
            <x-wirekit::editor.toolbar :commands="$presetCommands" x-ref="toolbar" />
        @endif

        {{-- Editor body. The HOST is the scroll viewport + click surface: flex
             column + cursor-text, and [contain:inline-size] so long unwrappable
             content (an H1 line) can never WIDEN the editor — it wraps inside.
             Tiptap's .ProseMirror child carries the wk-editor-content typography
             and fills the box (flex/outline/wrap rules live on .wk-editor-content
             in dist/wirekit.css — REAL CSS, because Tailwind never scans the JS
             config strings), so clicking ANYWHERE in the body focuses the
             document natively and the wrapper's focus-within ring frames the
             whole field. The server-rendered seed shows pre-hydration only:
             init() REMOVES it before mounting, because Tiptap appends its view
             and never empties the element — leaving the seed would render the
             content twice. The value is developer-controlled saved content —
             sanitize it on STORE (see the docs ::: warning). For format="json"
             there's no server renderer, so the seed is empty. --}}
        <div
            x-ref="content"
            id="{{ $id }}"
            class="flex flex-col cursor-text [contain:inline-size] {{ $minHeight }} overflow-y-auto wk-scrollbar px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]"
            @if($maxHeight) style="max-height: {{ $maxHeight }};" @endif
        ><div data-wk-editor-seed class="wk-editor-content">{!! $initialHtml !!}</div></div>

        @if($editable)
            {{-- The form field. It's `hidden` while Tiptap drives the UI (a hidden
                 control still submits its value); if Tiptap is absent the factory
                 un-hides it as the editable textarea fallback. --}}
            <textarea
                x-ref="input"
                @if($name) name="{{ $name }}" @endif
                hidden
                aria-hidden="true"
                {{-- Named so that when Tiptap is ABSENT and the factory un-hides this
                     textarea as the fallback, it still has an accessible name (the
                     <label for> targets the content host, not this field). --}}
                aria-label="{{ $label ?? ($name ? Str::headline((string) $name) : 'Rich text editor') }}"
                @if($autofocus) data-autofocus @endif
                {{-- Mirror the maxHeight cap on the fallback textarea so the absent-Tiptap
                     path scrolls at the same ceiling (a textarea scrolls natively). --}}
                @if($maxHeight) style="max-height: {{ $maxHeight }}; overflow-y: auto;" @endif
                class="wk-field block w-full bg-transparent px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] focus:outline-none {{ $minHeight }}"
                {{-- wire:model binds to the textarea the editor writes to (WIRE-167). --}}
                {{ $wireModel }}
            >{{ is_string($value) ? $value : ($value !== null ? json_encode($value) : '') }}</textarea>
        @endif

        @if(($maxLength && $editable) || isset($bottomBar))
            <div class="flex items-center justify-between gap-3 border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
                <div>{{ $bottomBar ?? '' }}</div>
                @if($maxLength && $editable)
                    {{-- Soft character counter. maxLength is a SOFT limit: the visible
                         count is aria-hidden (it would spam a screen reader on every
                         keystroke), while a debounced sr-only live region announces the
                         remaining count when the user pauses. Hard enforcement requires
                         the developer to configure CharacterCount with a `limit`. --}}
                    <div class="shrink-0 tabular-nums">
                        <span aria-hidden="true" x-text="charCountLabel" :class="{ 'text-[color:var(--color-wk-danger-text)] font-medium': isOverLimit }"></span>
                        <span class="sr-only" aria-live="polite" x-text="charAnnounce"></span>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- Error / hint --}}
    @if($hasError && $errorMessage)
        <p id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $hintId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
