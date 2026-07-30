{{-- optimistic-ui: candidate
     It already has a save seam with its own error and morph handling — the closest thing in the catalog to an optimistic component, and the one whose enablement needs the most care not to duplicate what it does. --}}
@props([
    'name' => null,
    'label' => null,
    'value' => '',
    'hint' => null,
    'error' => null,
    // Names the RECORD as well as the field. "Edit: Title" is useless in a list
    // of twenty rows — a screen reader then reads twenty identical entries and
    // the user cannot tell which one they are on.
    'context' => null,
    // What the reader SEES. There is deliberately no `none`: plain text is not
    // focusable, so a value with no button is unreachable by keyboard entirely
    // (WCAG 2.1.1). `focus-only` gives the quiet look without that defect.
    'trigger' => 'always',
    // Whether clicking the VALUE opens the editor. Separate axis from `trigger`
    // — one is what you see, the other is what acts.
    'openOnValueClick' => true,
    'exclusive' => true,
    // Which control the editor renders.
    'control' => 'text',
    // Options for control="select" — key => label.
    'options' => [],
    // What READ MODE shows when the stored value is not the readable one: a
    // select stores a key, an amount stores 1299, a date stores an ISO string.
    // Defaults to the value, and resolves itself for a select.
    'displayValue' => null,
    // Read-mode text when there is no value. Deliberately NOT called
    // `placeholder`: on input, textarea and select that name already means the
    // HTML attribute, and a second `placeholder` meaning something else would
    // damage the prop vocabulary rather than extend it.
    'emptyText' => null,
    // `explicit` — only the confirm control commits. `blur` additionally commits
    // when the control loses focus. Forced to explicit for a select, see below.
    'commitOn' => 'explicit',
    'actions' => true,
    // sm / md / lg only. Measured against all four control components rather
    // than assumed: `md-compact` exists on input and select but NOT on textarea
    // or number-input, so offering it here would throw for two of the four.
    'size' => 'md',
    // Cross-axis behavior. `auto` measures the content, so the width DOES jump
    // when the editor opens and that is the honest choice for a short value in
    // running text. `full` takes the container width, and then read and edit
    // boxes are the same width — which is the half of layout stability that can
    // actually be promised.
    'width' => 'full',
    'loading' => false,
    // NOTE: `loadingTarget`, not `target` — the same distinction the button
    // draws, so a page using both does not have to remember two spellings.
    'loadingTarget' => null,
    'announceError' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\WireKit;

    $openOnValueClick = BooleanProp::from($openOnValueClick, true);
    $exclusive = BooleanProp::from($exclusive, true);
    $actions = BooleanProp::from($actions, true);
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);
    $loading = BooleanProp::from($loading, false);

    // A validation failure returns as re-rendered HTML with a filled bag, NOT as
    // a transport error — so the bag is where the message actually is. Read the
    // same way the form controls read it, under this field's name.
    $hasError = (bool) $error || (bool) ($errors ?? null)?->has($name);
    $errorMessage = $error ?: ($errors ?? null)?->first($name);

    $attributes = $attributes->except(['announceErrors', 'announce-errors']);
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    $trigger = in_array($trigger, ['always', 'hover', 'focus-only'], true) ? $trigger : 'always';
    $control = in_array($control, ['text', 'textarea', 'number', 'select'], true) ? $control : 'text';
    $size = in_array($size, ['sm', 'md', 'lg'], true) ? $size : 'md';
    $width = in_array($width, ['auto', 'full'], true) ? $width : 'full';

    // Read mode carries the SAME padding, text size and border width as the
    // control it will be replaced by, with the border transparent at rest. The
    // box is therefore already reserved: opening the editor recolors a border
    // rather than adding two pixels, and the focus ring lands on a box that was
    // always that size.
    //
    // The measurements come from the same token ladder the controls use, so the
    // two cannot drift apart the way a hand-matched copy would.
    $mirrorMetrics = match ($size) {
        'sm' => 'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)] min-h-[var(--size-wk-sm)]',
        'lg' => 'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-lg)] text-[length:var(--text-wk-lg)] min-h-[var(--size-wk-lg)]',
        default => 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)] min-h-[var(--size-wk-md)]',
    };
    $mirrorBox = $mirrorMetrics.' border-[length:var(--border-wk-width)] border-transparent rounded-[var(--radius-wk-md)]';

    // ── The three forced rules, one per control type ─────────────────────
    //
    // A textarea CANNOT have its action buttons switched off. Enter has to
    // insert a line there, so the only keyboard commit is Cmd/Ctrl+Enter — and
    // with the buttons gone and blur deliberately not committing, a reader who
    // does not know that shortcut has NO way to save at all. The exits left are
    // Escape, which discards. That is a data-loss shape, so the library refuses
    // to render it rather than documenting around it.
    $actionsForced = false;
    if ($control === 'textarea' && ! $actions) {
        $actions = true;
        $actionsForced = true;
    }

    // A select commits explicitly, whatever was asked for. Keyboard users move
    // through a native select's options with the arrow keys, and each step fires
    // a change — so committing on change or blur would save every option they
    // passed over on the way. WCAG failure F36 describes exactly this.
    $commitOn = in_array($commitOn, ['explicit', 'blur'], true) ? $commitOn : 'explicit';
    $commitOnForced = false;
    if ($control === 'select' && $commitOn !== 'explicit') {
        $commitOn = 'explicit';
        $commitOnForced = true;
    }

    if (($actionsForced || $commitOnForced) && config('app.debug')) {
        // Say it rather than silently correcting: a developer who wrote the prop
        // meant something by it and deserves to know it did not take effect.
        logger()->warning('[WireKit] inline-edit overrode a prop for control="'.$control.'": '
            .($actionsForced ? 'actions cannot be disabled on a textarea (no keyboard path to save). ' : '')
            .($commitOnForced ? 'commitOn is forced to explicit on a select (arrow-key navigation would commit each option passed over). ' : ''));
    }

    // The id is NOT derived from `name`. Twenty rows bound to `title` would
    // otherwise emit twenty identical ids, and `label[for]` resolves to the
    // first one — so every label in the list would point at row one. The form
    // controls already had to be corrected for exactly this.
    $id = $attributes->get('id') ?: DomId::unique('inline-edit', 'inline-edit-');
    $attributes = $attributes->except(['id']);

    $hintId = $hint ? $id.'-hint' : null;
    $errorId = $hasError ? $id.'-error' : null;
    // Composed, and never emitted empty: an empty aria-describedby is a
    // dangling reference, which some screen readers announce as a blank.
    $describedBy = trim(implode(' ', array_filter([$hintId, $errorId]))) ?: null;

    // ── Read mode shows the READABLE value ───────────────────────────────
    //
    // Almost no real field stores what it should display: a select stores a
    // key, money stores 1299, a date stores an ISO string, a relation stores an
    // id. Rendering the raw value would be wrong more often than right — and
    // for a select it is wrong every single time, which is why that one
    // resolves itself instead of waiting for the caller to notice.
    $resolvedDisplay = $displayValue;
    if ($resolvedDisplay === null && $control === 'select' && $options !== []) {
        $resolvedDisplay = $options[$value] ?? null;
    }
    $resolvedDisplay ??= $value;

    $hasValue = trim((string) $resolvedDisplay) !== '';

    $triggerLabel = $context !== null && $context !== ''
        ? __('Edit :field of :context', ['field' => $label ?? $name, 'context' => $context])
        : __('Edit :field', ['field' => $label ?? $name]);

    $rootClasses = WireKit::resolveClasses('inline-edit', 'root', implode(' ', [
        'group/inline-edit',
        'flex flex-col gap-[var(--gap-wk-xs)]',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    // The trigger's visibility. `hover` is FORCED visible on focus and on a
    // coarse pointer — there is no hover on a touch screen, so without that the
    // control would be invisible and unreachable on a phone.
    $triggerVisibility = match ($trigger) {
        'hover' => 'opacity-0 group-hover/inline-edit:opacity-100 focus-visible:opacity-100 '
            .'[@media(pointer:coarse)]:opacity-100',
        'focus-only' => 'sr-only focus:not-sr-only',
        default => '',
    };

    $triggerClasses = WireKit::resolveClasses('inline-edit', 'trigger', implode(' ', [
        'wk-touch-target',
        'inline-flex shrink-0 items-center justify-center',
        'rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'cursor-pointer',
        $triggerVisibility,
    ]), $scope);

    $actionClasses = 'wk-touch-target inline-flex shrink-0 items-center justify-center rounded-[var(--radius-wk-sm)] '
        .'focus:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] cursor-pointer';
@endphp

<div
    {{ $attributes->class([$rootClasses]) }}
    x-data="wirekitInlineEdit({
        name: {{ \Pushery\WireKit\Support\AlpinePayload::from($name) }},
        value: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $value) }},
        exclusive: {{ \Pushery\WireKit\Support\AlpinePayload::from($exclusive) }},
        control: {{ \Pushery\WireKit\Support\AlpinePayload::from($control) }},
        commitOn: {{ \Pushery\WireKit\Support\AlpinePayload::from($commitOn) }},
        loading: {{ \Pushery\WireKit\Support\AlpinePayload::from($loading) }},
        describedBy: {{ \Pushery\WireKit\Support\AlpinePayload::from($describedBy) }},
        hasSlotEditor: {{ \Pushery\WireKit\Support\AlpinePayload::from(isset($editor)) }},
        {{-- A server-rendered error re-opens the editor after a morph replaced it. --}}
        hasError: {{ \Pushery\WireKit\Support\AlpinePayload::from($hasError) }},
        debug: {{ \Pushery\WireKit\Support\AlpinePayload::from((bool) config('app.debug')) }},
    })"
    @if($loadingTarget) wire:target="{{ $loadingTarget }}" @endif
    @wirekit:inline-edit-saved.window="onSaved($event)"
    @wirekit:inline-edit-failed.window="onFailed($event)"
>
    {{-- The label renders in BOTH modes and lives outside the part that toggles.
         Inside it, the field would be unlabelled while reading and the label
         would jump into place on open — a layout shift caused by a11y markup. --}}
    @if($label)
        <label for="{{ $id }}" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">
            {{ $label }}
        </label>
    @endif

    {{-- Read mode --}}
    <div
        x-show="!editing"
        class="flex items-center gap-[var(--gap-wk-sm)] {{ $width === 'full' ? 'w-full' : '' }}"
    >
        {{-- role="presentation" and NOT a button. Making the value itself the
             button would name it "Jane Doe" for a screen reader, which says
             nothing about the action, and would break voice control by giving
             the control a name that is not its visible text (WCAG 2.5.3). --}}
        <div
            role="presentation"
            {{-- A minimum width because an empty value is 0px wide: without it
                 the one state that most needs a click target has none. --}}
            class="{{ $mirrorBox }} {{ $width === 'full' ? 'w-full' : 'min-w-[6rem]' }} @if($openOnValueClick) cursor-text @endif"
            @if($openOnValueClick)
                x-on:pointerdown="onValuePointerDown($event)"
                x-on:click="onValueClick($event)"
            @endif
        >
            @if($hasValue)
                <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]">{{ $resolvedDisplay }}</span>
            @else
                <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text-muted)] italic">
                    {{ $emptyText ?? __('Not set') }}
                </span>
            @endif
        </div>

        <button
            type="button"
            x-ref="trigger"
            x-on:click="open()"
            class="{{ $triggerClasses }}"
            aria-label="{{ $triggerLabel }}"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
            </svg>
        </button>
    </div>

    {{-- Edit mode. x-show rather than x-if: Livewire stops patching attributes
         while a visibility state differs, so x-show survives a morph and x-if
         would tear the open editor down mid-edit. --}}
    {{-- `items-center` for a single-line control so the confirm/cancel buttons sit
         on the field's midline, matching read mode — they were pinned to the top
         edge, which reads as misalignment because nothing else in the row is.

         `items-start` stays for the MULTI-LINE controls, and there it is not a
         style choice: centering against a textarea that has grown to six rows
         floats the buttons somewhere in the middle of the box, far from both the
         first line and the last. Top-aligned keeps them next to where editing
         starts. A developer-supplied `editor` slot could be either, and takes the
         top-aligned treatment too: its height is unknown from here, and a control
         that turns out to be tall is the case centering gets wrong. `control` is
         one of text · textarea · number · select — there is no `editor` value in
         it, the slot is its own path. --}}
    <div x-show="editing" x-cloak class="flex {{ ($control === 'textarea' || ($editor ?? null) !== null) ? 'items-start' : 'items-center' }} gap-[var(--gap-wk-sm)] {{ $width === 'full' ? 'w-full' : '' }}">
        {{-- ONE partial for the built-in types and the developer's own control.
             Two branches here would let the slot path quietly lose a wiring
             detail the enum path sets, and nothing would look different enough
             to notice.

             The buttons stay IN FLOW rather than overlaying the content below.
             Overlaying avoids the reflow but covers whatever is underneath —
             and it does that in exactly the dense lists this component is for,
             which is WCAG 2.4.11. So the block-axis growth is deliberate, and
             documented rather than defined away. --}}
        <x-wirekit::partials.inline-edit-editor
            :id="$id"
            :control="$control"
            :size="$size"
            :described-by="$describedBy"
            :has-error="$hasError"
            :actions="$actions"
            :options="$options"
            :action-classes="$actionClasses"
            :editor="$editor ?? null"
        />
    </div>

    @if($hint)
        <p id="{{ $hintId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($hasError)
        <p id="{{ $errorId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif

    {{-- The save outcome is ANNOUNCED, never inferred from a round trip ending.
         role="status" is polite: it waits for the user to finish what they are
         doing rather than interrupting mid-word. --}}
    <p x-ref="status" role="status" aria-live="{{ $announceError ? 'polite' : 'off' }}" class="sr-only"></p>
</div>
