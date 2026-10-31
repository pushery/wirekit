{{-- optimistic-ui: supported
     Rich-text content — the same shape as a text field, with more of the reader's
     work in it, so a refusal keeps what was written and says only that it was
     not saved.

     The commit boundary is BLUR, not the change this component emits. That
     one rides a 200ms debounce, so committing on it would mean a request every
     fifth of a second while someone types — a timer wearing an event's name,
     and the boundary has to be a real event. Leaving the editor is the moment
     the writing stopped. --}}
@props([
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk. StrictnessGate did not
    // complain either, because `required` is in its HTML passthrough list — so it looked like
    // a legitimate attribute all the way down. The result was a required field that submits
    // empty, in the same form as a plain input that behaves correctly.
    'required' => false,
    // The Livewire method to call when you leave the editor. A refusal KEEPS
    // what you wrote — see the note above.
    // Needs `wirekit-optimistic.js` on the page beside the bundle, as
    // docs.wirekit.app/extending/optimistic-ui shows; without it no `wirekitOptimistic` exists.
    'optimistic' => null,
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer carries that: it spreads `args` into the call.
    'optimisticArgs' => [],
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'name' => null,
    'id' => null,
    'value' => null,
    'format' => config('wirekit.components.editor.format', 'html'),   // html | json
    'extensions' => null,        // array of editor extension NAMES (hint for window.wirekitEditor)
    // The engine this field opens in, by name: its factory is `window.wirekitEditors[engine]`.
    // An application with several schemas registers one factory per name, and a field that
    // names its engine never takes another's, which after `wire:navigate` could be whichever
    // bundle ran last. Without a name the field takes `window.wirekitEditor`.
    'engine' => null,
    'toolbar' => config('wirekit.components.editor.toolbar', 'basic'), // false | 'basic' | 'full' | 'custom'
    'placeholder' => null,
    'maxLength' => null,
    'editable' => true,
    // Takes the editor out of use: the document stays in its field, dimmed and announced as
    // unavailable (`aria-disabled` on the surface), nothing in it can be changed, and nothing
    // is submitted. Built on the non-editable rendering (`editable` false): no toolbar, no form
    // field, the engine mounted with `editable: false`. Declared rather than left to the
    // attribute bag, where it lands on the wrapper div and nothing reads it.
    'disabled' => false,
    'label' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    'error' => null,
    'size' => config('wirekit.components.editor.size', 'md'),         // sm | md | lg
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

@aware(['announceErrors' => null, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false, 'editor', 'disabled');
    $editable = BooleanProp::from($editable, true, 'editor', 'editable') && ! $disabled;
    $autofocus = BooleanProp::from($autofocus, false, 'editor', 'autofocus');
    $required = BooleanProp::from($required, false, 'editor', 'required');

    // An editor that cannot be edited has no form field, so a caller's `wire:model` has nothing
    // to bind.
    if (! $editable) {
        \Pushery\WireKit\Support\StrictnessGate::warnWireModelInAModeWithoutOne('editor', $attributes->getAttributes(), 'while it cannot be edited', 'Such an editor shows the document given as `value`.');
    }

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;
    use Illuminate\Support\Str;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('editor', $attributes->getAttributes());

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // A caller-supplied `aria-label` names the CONTROL, not the wrapper `{{ $attributes }}`
    // lands on. `<x-wirekit::editor aria-label="…">` put the name on a roleless element,
    // so the control the user actually operates kept no accessible name at all — WCAG
    // 4.1.2, and it looked correct in the markup, which is why nothing caught it.
    $callerLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    // A caller's `aria-labelledby` names the control and wins over the component's own name, as
    // their `aria-label` does; on the wrapper it would name nothing a reader lands on.
    $callerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $callerLabelledBy = is_string($callerLabelledBy) && filled($callerLabelledBy) ? $callerLabelledBy : null;
    $attributes = $attributes->except(['aria-label', 'aria-labelledby']);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    //
    // It has to happen BEFORE `$rest` is derived below: the wrapper echoes `$rest`,
    // not `$attributes`, so a strip applied afterwards would leave the key in the
    // very bag that reaches the element.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);

    // Inside a labeled field the field's label names the editor, by reference as this
    // component's own label does (Support\FieldControl): it gets no `for`, the surface takes its
    // id as `ariaLabelledby`, and the factory wires a click on it to focus the surface.
    $fieldLabelId = blank($label) && ! filled($callerLabel) && $wkField instanceof \Pushery\WireKit\Support\FieldControl
        ? $wkField->takeLabel(null)
        : null;


    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    $formatValue = match ($format) {
        'html', 'json' => $format,
        default => WireKit::validateProp('editor', 'format', $format, ['html', 'json']),
    };
    $sizeValue = match ($size) {
        'sm', 'md', 'lg' => $size,
        default => WireKit::validateProp('editor', 'size', $size, ['sm', 'md', 'lg']),
    };

    // STABLE across re-renders, which a `Str::random(6)` id would not be. Here the two
    // halves are on opposite sides of the server/client line: the <label id> and the
    // error/hint <p id> are re-rendered per request, while aria-labelledby and
    // aria-describedby are serialized into the x-data payload and applied ONCE, at
    // mount, as Tiptap editorProps. A re-minted id therefore leaves the contenteditable
    // announcing with no accessible name at all (WCAG 4.1.2) — and label[for] cannot
    // reach a contenteditable div, so this reference wiring is the only thing naming it.
    $id = \Pushery\WireKit\Support\DomId::unique(
        $id ?? ($name ? 'wk-editor-'.Str::slug($name) : null),
        'wk-editor-'
    );

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    // Error detection: explicit prop OR Laravel validation bag.
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf($name, $attributes);
    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errorName ? ($errors ?? null) : null, $errorName, $error);
    $hasError = $errorMessage !== null;

    // First-paint content (format=html only), rebuilt rather than echoed. The browser parses
    // this copy in the live document before the engine mounts, so the engine's schema never
    // sees it, and the value may be `old('body')` rather than a stored document. EditorSeed
    // keeps the document's elements, escapes every text node and attribute, and drops the rest,
    // so the preview can run nothing. The value the form submits is untouched and still has to
    // be sanitized where it is stored.
    $initialHtml = $formatValue === 'html' && is_string($value)
        ? \Pushery\WireKit\Support\EditorSeed::render($value)
        : '';

    $hintId = "{$id}-hint";
    $errorId = "{$id}-error";
    $describedBy = trim(($hint && ! $hasError ? $hintId : '') . ' ' . ($hasError ? $errorId : ''));
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));
    // The field's help, after its own message: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered.
    $helpId = filled($help) && filled($label) ? $id.'-help' : null;
    // A `field` around this control renders its hint below it, and the control is described by it
    // as by a hint of its own (Support\FieldControl).
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $id, $hint ? $hintId : null, $helpId ?? null);
    // A required `field` around this control hides its asterisk from assistive technology, so
    // the control says it, with `aria-required` and never the browser's own check
    // (Support\FieldControl::requiredBy()).
    $fieldRequired = \Pushery\WireKit\Support\FieldControl::requiredBy($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $required);
    $describedBy = trim($describedBy.' '.($helpId ?? ''));
    // A required editor that a submit found empty says so under it (partials/required-message),
    // unless the server's own message already stands there. A read-only editor sends nothing.
    $requiredMessageId = $required && $editable && ! $hasError ? $id.'-required' : null;
    $describedBy = trim($describedBy.' '.($requiredMessageId ?? ''));

    // Route wire:model to the <textarea x-ref="input"> (the element the editor writes to
    // and fires its input event on), NOT the wrapper div — otherwise Livewire binds to a
    // div that never emits input and the value is silently lost. Modifiers (.live / .blur
    // / .debounce) are preserved; everything else but the description lands on the wrapper.
    // A caller's `x-ref` belongs to the caller's component. The root below is a root of ours and
    // may sit inside the optimistic layer, so the name moves to `x-wk-ref` on the root and the
    // outermost element marks the boundary (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');
    // A caller's `form` goes to the field this component submits, and only there: on the
    // wrapper it is not a valid attribute and joins nothing to the form (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $attributes = $attributes->except('form');
    // What acts only on the field the reader types into goes to the editing surface and to the form
    // field below: on the wrapper the on-screen keyboard reads none of it (Support\FieldAttributes).
    // `maxlength`, the HTML spelling, sets the same soft limit as `maxLength`, which it never reached.
    [$fieldAttributes, $attributes] = \Pushery\WireKit\Support\FieldAttributes::split($attributes, \Pushery\WireKit\Support\FieldAttributes::KEYBOARD);
    $maxLength ??= $attributes->get('maxlength');
    $attributes = $attributes->except('maxlength');
    // A soft limit is a count above zero, and anything else sets none: a condition that reads NAN
    // or INF as it came is a warning on PHP 8.5.
    $maxLength = \Pushery\WireKit\Support\NumericProp::intOrNull($maxLength, 'editor', 'maxLength');
    $maxLength = $maxLength !== null && $maxLength > 0 ? $maxLength : null;

    $wireModel = $attributes->whereStartsWith('wire:model');
    $rest = $attributes->except('aria-describedby')->whereDoesntStartWith('wire:model');

    // Toolbar preset → command vocabulary. A passed <x-slot:toolbar> (a ComponentSlot)
    // selects the 'custom' path so the caller's toolbar actually renders — otherwise the
    // slot silently collapsed to the 'basic' preset and was never shown.
    // Tri-state (false | 'basic' | 'full' | 'custom slot'): `=== false` alone let the
    // unbound string 'false' (truthy) fall to the is_string branch and keep the
    // toolbar — isFalse recognizes the stringly-false spellings so `toolbar="false"`
    // removes it, matching the docs and the bound `:toolbar="false"` form.
    $toolbarValue = BooleanProp::isFalse($toolbar)
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

    // The document as the form field holds it, and the field is where the factory reads it: the
    // config carries no copy. A document is markup or JSON text, so a character reference the page
    // decoded would change what it says, text `&lt;img` becoming an element or an escaped quote
    // ending a JSON string. The field is written with double encoding whatever echo format the
    // application chose, and the browser hands back exactly these bytes. A document given as an
    // array becomes JSON text through the JSON encoder. An editor that cannot be edited has no
    // form field, and its root carries the document instead, written the same way.
    $editorFieldText = is_string($value)
        ? $value
        : ($value !== null ? \Pushery\WireKit\Support\AlpinePayload::json($value) : '');

    // Config handed to the wirekitEditor Alpine factory (→ window.wirekitEditor).
    $jsConfig = [
        // The browser prompt shown when inserting a link. resources/js has no translator,
        // and this string is a question put to the reader.
        'linkPromptLabel' => __('wirekit::Link URL'),
        'format' => $formatValue,
        'editable' => (bool) $editable,
        'extensions' => $extensions ?? config('wirekit.components.editor.extensions', []),
        'engine' => is_string($engine) && trim($engine) !== '' ? trim($engine) : null,
        // Text the engine writes into an empty document, so markup is read as its text.
        'placeholder' => $placeholder instanceof \Illuminate\Contracts\Support\Htmlable ? \Pushery\WireKit\Support\SlotContent::textOf($placeholder) : $placeholder,
        'maxLength' => $maxLength,
        // A VISIBLE label does not name this field, and assuming it did shipped an
        // unnamed textbox to everyone with a real engine. `<x-wirekit::label for>`
        // points at the wrapper, and the editable surface the engine builds inside
        // it is a `div[contenteditable][role=textbox]` — not a labelable element,
        // so `for` never reaches it. Invisible here for as long as the sample had
        // no engine: without one the component renders its plain textarea, which
        // carries its own aria-label and passes.
        //
        // So the label is wired by REFERENCE instead, and aria-label stays the
        // fallback for the unlabeled case.
        'ariaLabelledby' => $callerLabelledBy ?? ($label ? $id.'-label' : $fieldLabelId),
        // `__()` on both halves of the same name. editor.js spreads this onto the
        // contenteditable as its aria-label, so an untranslated literal here would
        // announce the editor in English on the Tiptap path while the textarea path
        // below is translated.
        // `Str::headline($name)` stays untranslated on purpose: it is derived from a
        // developer-supplied name, not a string this package ships.
        //
        // The caller's own `aria-label` comes before either fallback, and it goes to the
        // textbox, not to the content host: with an engine the textbox is the surface the
        // engine builds inside the host, without one the host is hidden. On the host,
        // `aria-label="Release notes"` would leave a textbox announced as "Rich text
        // editor" on a page whose visible label says "Release notes".
        'ariaLabel' => $callerLabelledBy !== null || filled($label) || $fieldLabelId !== null ? null : (filled($callerLabel) ? $callerLabel : ($name ? Str::headline((string) $name) : __('wirekit::Rich text editor'))),
        'ariaDescribedby' => $describedBy !== '' ? $describedBy : null,
        'ariaInvalid' => (bool) $hasError,
        // The same holds one attribute over: `aria-required` goes to the textbox, because a
        // generic element may not carry it, and on the host the textbox would never be
        // announced as required.
        'ariaRequired' => $required || $fieldRequired,
        // The message of an empty required editor stands under the frame, outside this
        // component's scope, so the factory finds it by id and writes it.
        'requiredMessageId' => $requiredMessageId,
        // Plumbed to the Tiptap path too (not just the textarea fallback's
        // data-autofocus) — editor.js focuses the editor in onCreate when set.
        'autofocus' => (bool) $autofocus,
        // The character count is announced from the browser, where the count lives, so its
        // sentence travels as the plural forms of one key and the client picks the form for
        // the number with the application's locale, as the data table's selection does.
        'remainingPhrases' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count character remaining|[2,*] :count characters remaining'),
        'overPhrases' => \Pushery\WireKit\Support\PluralPhrases::from('wirekit::{1} :count character over the limit|[2,*] :count characters over the limit'),
        'locale' => \Pushery\WireKit\Support\IcuLocale::tag(),
    ];
    // Only when set, so the payload of every editor in use stays as it was.
    if ($disabled) {
        $jsConfig['ariaDisabled'] = true;
    }
    // The same for what the caller wrote for the field: the factory writes it onto the surface the
    // engine builds, where the on-screen keyboard reads it.
    if ($fieldAttributes->getAttributes() !== []) {
        $jsConfig['fieldAttributes'] = $fieldAttributes->getAttributes();
    }

    $wrapperClasses = WireKit::resolveClasses('editor', 'wrapper', implode(' ', array_filter([
        'overflow-hidden',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'bg-[var(--color-wk-bg-input)]',
        'shadow-[var(--shadow-wk-sm)]',
        $disabled ? 'opacity-[var(--opacity-wk-disabled)]' : '',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-within:ring-[length:var(--ring-wk-width)]',
        $hasError ? 'focus-within:ring-[var(--color-wk-danger)]' : 'focus-within:ring-[var(--color-wk-ring)]',
    ])), $scope);
    // `value` rather than `bind`: this layer is the OUTERMOST x-data here, so an
    // undeclared property is in no scope at all — the binding would name nothing
    // and init() would disarm, leaving a component that advertises support and
    // does nothing. It seeds the baseline only, which `keep` never writes back.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => '',
        'action' => $optimistic,
        // The field's own error region. Without it the layer's generic "Could not save"
        // is the only thing a listener hears, and it BEATS the specific message the server
        // sent — the whole point of the arbitration is that a specific message wins, and it
        // cannot run against a region nobody pointed at.
        'errorRegion' => \Pushery\WireKit\Support\CssIdentifier::idSelector($errorId),
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'kept' => __('wirekit::Could not save. Your text is still here.'),
        ],
    ]);


    // One value for the style, so a `;` in it carries no declaration of its own (Support\CssValue).
    $maxHeight = \Pushery\WireKit\Support\CssValue::prop($maxHeight, 'editor', 'maxHeight');
    // With the optimistic layer the root stands inside the layer's own root, where `.parent` would
    // find the layer's names: the caller's `x-model` reaches the root as `x-wk-model`, read above
    // the outer element, which marks the boundary (CallerModel::onField()).
    $modelScope = $optimisticConfig && \Pushery\WireKit\Support\CallerModel::binds($rest);
    $rootModel = $modelScope ? \Pushery\WireKit\Support\CallerModel::onField($rest) : \Pushery\WireKit\Support\CallerModel::aroundRoot($rest);
@endphp

<div {{ $outerAttributes }} class="relative w-full space-y-1.5" @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif>
    @if($label)
        {{-- No `for`: the element it could name is the content host, a div no label can name,
             and the surface a reader types into is built in the browser. The label names that
             surface by reference (`ariaLabelledby` below), and the factory wires a click on it
             to focus the surface, as a label does for a field. --}}
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name ?? $id" :id="$id.'-label'" :required="$required">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @endif

@if($optimisticConfig)
    {{-- OUTSIDE the component here, which is the opposite of every other
         component that takes this layer — and the reason is where the commit
         comes from. Elsewhere a DOM handler inside the layer calls `run()`, so
         the layer can nest within the component. Here the commit comes from
         Tiptap's own `onBlur`, where `this` is the component scope: a nested
         layer would be invisible to it. Alpine resolves a child's lookups
         through its parent and never the reverse, so the layer has to be the
         parent for the factory to see `run` at all.

         `display: contents` so the stack spacing is untouched. --}}
    <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
    <div
        x-data="wirekitEditor({{ \Pushery\WireKit\Support\AlpinePayload::from($jsConfig) }})"
        {{-- A caller's `x-model` binds to the document through this, in both directions.
             Without it the model sat on this div as a plain Alpine model and read the
             editing surface's own input events, whose target has no `value`, so the bound
             value turned `undefined` on every keystroke. `wire:model` is unaffected: it is
             routed to the form field below. --}}
        x-modelable="content"
        {{-- The document for the engine where there is no form field to read it from, so a
             read-only or disabled editor mounts with its document, not an empty one. --}}
        @unless($editable) data-wk-editor-document="{!! e($editorFieldText) !!}" @endunless
        @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
        {{ $rootModel->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['w-full', $wrapperClasses])) }}
        {{-- `focus` and `blur` fire here as the reader enters and leaves the control as a whole, so a
             listener written on the tag hears one field (utils/focus-unit.js). --}}
        x-wk-focus-unit
        @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
    >
        {{-- Toolbar: auto-rendered preset, OR the custom slot when toolbar="custom". --}}
        @if($editable && $toolbarValue === 'custom' && $toolbar instanceof \Illuminate\View\ComponentSlot && \Pushery\WireKit\Support\SlotContent::given($toolbar))
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
             content twice. The seed is the value rebuilt by EditorSeed, so it
             can run nothing before init() removes it; the value itself still
             has to be sanitized on STORE (see the docs ::: warning). For
             format="json" there's no server renderer, so the seed is empty. --}}
        <div
            x-ref="content"
            {{-- Paper does not scroll: on paper the host grows to the whole text. --}}
            data-wk-print-expand
            {{-- The engine builds the textbox inside this host and takes its attributes once, at
                 mount, so the invalid state of an empty required editor is written onto it here. --}}
            @if($requiredMessageId) x-effect="_syncRequiredInvalid()" @endif
            id="{{ $id }}"
            {{-- No name and no `aria-required` here. This host is never the textbox: with an
                 engine the textbox is the surface the engine builds inside it, and that surface
                 takes both from `ariaLabel` and `ariaRequired` in the config above; without one
                 the host is hidden and the form field below is the control. --}}
            class="flex flex-col {{ $disabled ? 'cursor-not-allowed' : 'cursor-text' }} [contain:inline-size] {{ $minHeight }} relative overflow-y-auto wk-scrollbar px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]"
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
                {{-- The caller's own name first. This is the control a reader without the
                     editor engine actually types into, so a name that stops at the
                     wrapper never reaches them. --}}
                @if($callerLabelledBy) aria-labelledby="{{ $callerLabelledBy }}" @else aria-label="{{ \Pushery\WireKit\Support\BlankName::first($label, $callerLabel, $fieldLabelId !== null ? $wkField->labelText() : null, filled($name) ? Str::headline((string) $name) : null, __('wirekit::Rich text editor')) }}" @endif
                {{-- The invalid state and its description, SERVER-SIDE. Both were passed
                     only into the Tiptap x-data payload, which editor.js applies to the
                     contenteditable at mount — so the document the server sent carried no
                     `aria-invalid` at all, and this textarea is the control a reader
                     without the editor engine actually types into. A validation failure
                     showed its message and announced nothing.

                     The binding stays and takes over after init; this is the static half,
                     the same shape the icon-only guard asks of every bound name. --}}
                @if($hasError)
                    aria-invalid="true"
                @elseif($requiredMessageId)
                    x-bind:aria-invalid="requiredMessage !== '' && requiredValue === '' ? 'true' : null"
                @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                {{-- `aria-required`, never the native attribute: while the engine runs this
                     field is hidden, and a hidden required control stops the form submitting. --}}
                @if($required || $fieldRequired) aria-required="true" @endif
                @if($autofocus) data-autofocus @endif
                @if($formOwner) form="{{ $formOwner }}" @endif
                {{ $fieldAttributes }}
                {{-- Mirror the maxHeight cap on the fallback textarea so the absent-Tiptap
                     path scrolls at the same ceiling (a textarea scrolls natively). --}}
                @if($maxHeight) style="max-height: {{ $maxHeight }}; overflow-y: auto;" @endif
                class="wk-field block w-full bg-transparent px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] focus:outline-hidden {{ $minHeight }}"
                {{-- wire:model binds to the textarea the editor writes to. --}}
                {{ $wireModel }}
            >{!! e($editorFieldText) !!}</textarea>
            {{-- The form field is hidden while the engine runs, and a hidden required field would
                 stop the form without a word, so a required editor checks through a stand-in. --}}
            @if($required)
                @include('wirekit::components.partials.required-check', ['requiredFormOwner' => $formOwner, 'requiredDisabled' => $disabled])
            @endif
        @endif

        @if(($maxLength && $editable) || \Pushery\WireKit\Support\SlotContent::given($bottomBar ?? null))
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

    @if($requiredMessageId)
        @include('wirekit::components.partials.required-message', ['requiredMessageId' => $requiredMessageId, 'requiredMessageBound' => false])
    @endif

    {{-- Error / hint --}}
    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $hintId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
@if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    </div>
@endif
</div>
