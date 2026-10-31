{{-- optimistic-ui: supported --}}
@props([
    // The Livewire method this toggle should call, when it should show the new
    // state before the server has agreed to it. Null (the default) leaves the
    // developer's own wire:model / wire:click untouched.
    //
    // It is a METHOD NAME rather than a boolean because the component cannot
    // know the action otherwise — WireKit passes server actions through the
    // attribute bag, so a component never sees the developer's wire:click.
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
    'label' => null,
    // Render the label sr-only (kept as the control's accessible name) — for a
    // toggle in a table column or a toolbar whose surrounding chrome already
    // names it. The <label> WRAPS the control, so the name is associated with the
    // element rather than with the visible text and survives being taken off the
    // screen. Mirrors input / select / textarea / combobox / checkbox `hideLabel`.
    'hideLabel' => false,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    'error' => null,
    'size' => config('wirekit.components.toggle.size', 'md'),
    // The state in words beside the switch, such as "On" and "Off". Drawn from the checkbox
    // itself, so the word follows the click before a server has answered, and hidden from a
    // screen reader, which hears the switch announce its own state.
    'onLabel' => null,
    'offLabel' => null,
    // Where the words sit: `end` after the switch and its label, `start` before the switch.
    'statePosition' => 'end',
    // The track's color for each state: `accent`, `success`, `danger` or `neutral`. On is the
    // accent and off is neutral by default; "green on, red off" is `on-intent="success"
    // off-intent="danger"`.
    'onIntent' => 'accent',
    'offIntent' => 'neutral',
    // Take the surrounding field.set's group error, or decline it. A control that belongs to
    // the group but neither causes the rejection nor can resolve it says `:group-error="false"`
    // and is then neither announced as invalid nor described by the group's message.
    'groupError' => true,
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkFieldSet' => null, 'wkField' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkFieldSet', 'wk-field-set', 'wkField', 'wk-field']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);
    // A list where the field takes one value, as `old()` gives one after a request that sent
    // `name[]`, is reported and left off the field (Support\FieldValue).
    $attributes = \Pushery\WireKit\Support\FieldValue::withoutLists($attributes, 'toggle', ['value', 'checked']);

    // Same trap one level in: an UNBOUND `hideLabel="false"` reaches here as the
    // truthy string 'false' and would hide the label the call site asked to show.
    $hideLabel = BooleanProp::from($hideLabel, false, 'toggle', 'hideLabel');


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('toggle', $attributes->getAttributes());

    // An unknown `size` is reported (Support\StrictnessGate) and renders as `md`; a bound `null` or
    // empty string is `md` without a word.
    $size = \Pushery\WireKit\WireKit::validateProp('toggle', 'size', \Pushery\WireKit\Support\NumericProp::text($size ?: 'md'), ['md', 'sm', 'lg']);

    // A checkbox takes a number from its model for its value rather than its state, so a box
    // bound to one shows unchecked whatever it holds: a binding to one warns in debug mode
    // (Support\CheckboxModel).
    \Pushery\WireKit\Support\CheckboxModel::warn('toggle', $attributes);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // The id from the attribute or the name; with neither, DomId counts one per request.
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'toggle-'); // page-unique DOM id; see Support\DomId
    // The bag holds a bound name escaped once; read as the text it stands for, so the field is
    // sent under the name the caller bound (Support\AttributeText).
    // Without a `name` the field is sent under the caller's `id`, and without either it is not
    // sent at all: an id DomId counted out names nothing a form should carry.
    $name = \Pushery\WireKit\Support\AttributeText::get($attributes, 'name', filled($attributes->get('id')) ? $id : '');
    // Without a `name`, the bag files this field's error under the property its `wire:model`
    // binds (FieldError::nameOf).
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf(\Pushery\WireKit\Support\AttributeText::get($attributes, 'name'), $attributes) ?? $name;

    // Accessible name fallback: if the caller provided neither a visible `label`
    // prop nor an `aria-label` attribute, generate a humanized label from the
    // `name` attribute so axe-core and screen readers still announce the switch
    // correctly. Visible labels still win when present (they'll use `<label
    // for="...">` instead of aria-label).
    // A caller's `id` is there so a label of theirs can reach the field, and the fallback name would
    // stand over that label or add its own words to it, so it steps aside, as it does on
    // tags-input and multi-select.
    $hasAccessibleName = $label !== null || $attributes->has('aria-label') || $attributes->has('aria-labelledby') || filled($attributes->get('id'));
    $fallbackAriaLabel = $hasAccessibleName ? null : ucfirst(str_replace(['-', '_'], ' ', (string) $name));

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    // The field.set around this toggle, when there is one. A bag entry under a key the group
    // answers for is the group's message and renders once, above the group.
    $fieldGroup = $wkFieldSet instanceof \Pushery\WireKit\Support\FieldGroup ? $wkFieldSet : null;
    $groupOwnsBagEntry = ! $error && ($fieldGroup?->covers($errorName) ?? false);

    // Error detection: explicit prop OR Laravel validation bag
    $errorMessage = \Pushery\WireKit\Support\FieldError::message($groupOwnsBagEntry ? null : ($errors ?? null), $errorName, $error);
    $hasError = $errorMessage !== null;
    // Whether this toggle answers for the group's message. A group error can be true of SOME of
    // its controls; announced on one that cannot resolve it, the reader hears "invalid" and a
    // sentence that switching it will not satisfy. `covers()` is not gated on this, so a
    // declining control does not start printing the group's message under itself.
    $groupError = BooleanProp::from($groupError, true, 'toggle', 'groupError');
    $isInvalid = $hasError || ($groupError && ($fieldGroup?->isInvalid($errors ?? null) ?? false));

    // One description list for the control: its own message, the group's, then a caller's
    // aria-describedby. Written as separate attributes, the parser kept only the first copy,
    // so a caller's description was dropped or pushed the component's own out.
    $describedBy = \Pushery\WireKit\Support\FieldGroup::describedBy(
        $fieldGroup,
        $errors ?? null,
        $hasError ? $id.'-error' : null,
        $hint ? $id.'-hint' : null,
        $attributes->get('aria-describedby'),
        $groupError,
    );
    // The field's help, after its own messages: what the field is for (partials/field-help).
    // Only with a label to stand beside, which is also where its hidden copy is rendered.
    $helpLabel = (string) ($label ?? '');
    $helpId = filled($help) && $helpLabel !== '' ? $id.'-help' : null;
    // Markup a caller puts after the label and its help, in their row (partials/label-after).
    $hasLabelAfter = isset($labelAfter) && $labelAfter->hasActualContent();
    // The control sits inside the label, so the row around it stands whenever the slot is passed,
    // filled or not: a mark that comes and goes between two renders then leaves the markup around
    // the control as it was.
    $labelAfterGiven = isset($labelAfter);
    // A `field` around this control renders its hint below it, and the control is described by it
    // as by a hint of its own (Support\FieldControl).
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $id, $hint ? $id.'-hint' : null, $helpId ?? null);
    // A required `field` around this control hides its asterisk from assistive technology, so
    // the control says it, with `aria-required` and never the browser's own check
    // (Support\FieldControl::requiredBy()).
    $fieldRequired = \Pushery\WireKit\Support\FieldControl::requiredBy($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, (bool) $attributes->get('required', false));
    $describedBy = trim(($describedBy ?? '').' '.($helpId ?? '')) ?: null;

    // Size scale: track width/height + knob offset distance
    // Knob diameter = track height minus 4px of padding
    $sizing = match ($size) {
        // Both directions per size, and the RTL half is not decoration. A switch encodes
        // off -> on as travel along the READING direction; that is the whole affordance. With
        // only the positive form, an Arabic or Hebrew form mirrors around the control while the
        // knob still starts at the left edge and runs right, so "on" points back at the start of
        // the line. Nothing fails and nothing is logged — the control simply reads inverted.
        //
        // Written out per size rather than assembled, because Tailwind scans source TEXT for
        // class names and a class built at runtime is never generated.
        'sm' => ['track' => 'w-8 h-4', 'knob' => 'w-3 h-3', 'translate' => 'peer-checked:translate-x-4 rtl:peer-checked:-translate-x-4'],
        'lg' => ['track' => 'w-12 h-6', 'knob' => 'w-5 h-5', 'translate' => 'peer-checked:translate-x-6 rtl:peer-checked:-translate-x-6'],
        default => ['track' => 'w-10 h-5', 'knob' => 'w-4 h-4', 'translate' => 'peer-checked:translate-x-5 rtl:peer-checked:-translate-x-5'],
    };

    // Wrapper styles: fixed-size positioning context for the (absolutely placed) track + knob
    $wrapperClasses = implode(' ', [
        'relative inline-flex shrink-0 items-center',
        'cursor-pointer',
        // The hit-area reserve. The native input is `peer sr-only` at 1x1, so the thing a finger
        // lands on is the track — 40x20 at `md` and 32x16 at `sm`, both under the 24px AA floor
        // on the vertical axis. The expander is out of flow, so the switch keeps the size it was
        // designed at and nothing around it moves. The wrapper is already positioned, so the
        // class only adds the area.
        'wk-touch-target',
        $sizing['track'],
    ]);

    // The track's color for each state. Literal arms, because Tailwind reads class names as text
    // and never one assembled at runtime.
    //
    // Neutral fills the track with --color-wk-border-strong, the contrast-bound border of every
    // form control. The knob shows the state, and WCAG 1.4.11 asks 3:1 for it against the part of
    // the component it sits on: on the decorative --color-wk-border the white knob stood at 1.3:1
    // in light and 1.42:1 in dark, on -strong it stands at 3.11:1 and 3.12:1, and the track
    // itself at 3.11:1 and 3.45:1 against the page.
    //
    // Each list starts with that state's default: an unknown value falls back to the first entry.
    $offTrack = match (WireKit::validateProp('toggle', 'offIntent', (string) $offIntent, ['neutral', 'accent', 'success', 'danger'])) {
        'accent' => ['border-[var(--color-wk-accent)]', 'bg-[var(--color-wk-accent)]'],
        'success' => ['border-[var(--color-wk-success)]', 'bg-[var(--color-wk-success)]'],
        'danger' => ['border-[var(--color-wk-danger)]', 'bg-[var(--color-wk-danger)]'],
        default => ['border-[var(--color-wk-border-strong)]', 'bg-[var(--color-wk-border-strong)]'],
    };
    $onTrack = match (WireKit::validateProp('toggle', 'onIntent', (string) $onIntent, ['accent', 'success', 'danger', 'neutral'])) {
        'success' => ['peer-checked:bg-[var(--color-wk-success)]', 'peer-checked:border-[var(--color-wk-success)]'],
        'danger' => ['peer-checked:bg-[var(--color-wk-danger)]', 'peer-checked:border-[var(--color-wk-danger)]'],
        'neutral' => ['peer-checked:bg-[var(--color-wk-border-strong)]', 'peer-checked:border-[var(--color-wk-border-strong)]'],
        default => ['peer-checked:bg-[var(--color-wk-accent)]', 'peer-checked:border-[var(--color-wk-accent)]'],
    };

    // The words for the state, when either is given. Where they go is the caller's choice.
    $hasStateText = filled($onLabel) || filled($offLabel);

    // On a touch surface a label that shows a text beside the switch is a row: a tap on the text
    // flips the switch, so it takes the 44px floor of every interactive row (`wk-touch-row`). Its
    // content is centered by `items-center`. A switch alone keeps its size and answers a finger
    // through the hit area of `wk-touch-target`.
    $rowMarker = ($label && ! $hideLabel) || $hasStateText ? 'wk-touch-row ' : '';
    $statePosition = WireKit::validateProp('toggle', 'statePosition', (string) $statePosition, ['end', 'start']);

    // Track: the neutral fill is the contrast-bound control border, see the arms above. WCAG 1.4.11
    // asks 3:1 of the track against the page and of the knob against the track.
    // MUST be a direct sibling of .peer for peer-checked:* to resolve.
    $trackClasses = WireKit::resolveClasses('toggle', 'track', implode(' ', [
        'absolute inset-0',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        ...$offTrack,
        ...$onTrack,
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'pointer-events-none',
    ]), $scope);

    // Knob styles: a circle that slides from the inline start to the inline end when checked.
    // MUST be a direct sibling of .peer for peer-checked:* to resolve.
    //
    // `start-0.5`, not `left-0.5`: the anchor is the other half of the direction pair above,
    // and repairing only the travel would leave the knob starting at the wrong edge and then
    // running off it.
    $knobClasses = implode(' ', [
        'absolute start-0.5 top-1/2 -translate-y-1/2',
        'rounded-full',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-transform',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'pointer-events-none',
        $sizing['knob'],
        $sizing['translate'],
    ]);
@endphp

@php
    // The optimistic wiring, built once so the markup below stays readable.
    //
    // Every string is a translation key, per the contract: an announcement is
    // read aloud to somebody, and a literal here would be read aloud in English
    // to everybody.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (bool) ($attributes->get('checked') ?? false),
        'action' => $optimistic,
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        // Developer warning only where warnings belong; the same gate every other
        // dev-warning call site in the catalog uses.
        'debug' => (bool) config('app.debug'),
        // A toggle rejects a second flip while one is in flight rather than
        // queueing it. With a queue the final state depends on the ORDER the
        // responses come back in — network timing — which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        // The field's own error region. Where it carries a message, this layer
        // stays silent on failure: "Email is required" is actionable, "could
        // not save" is not, and WCAG 3.3.1 wants the specific one heard.
        'errorRegion' => \Pushery\WireKit\Support\CssIdentifier::idSelector($id.'-error'),
    ]);
    // With the optimistic layer a caller's `x-ref` would meet the field's own `x-ref="control"` in
    // one tag, where the first is kept, and inside the layer's root besides. It moves to `x-wk-ref`,
    // which registers the field above the boundary the outer element marks
    // (resources/js/utils/caller-ref.js).
    $callerRef = $optimisticConfig ? trim((string) $attributes->get('x-ref', '')) : '';
    if ($callerRef !== '') {
        $attributes = $attributes->except('x-ref');
    }
    // With the optimistic layer the field stands inside a root of ours, where `.parent` would still
    // find the layer's names: the caller's `x-model` reaches it as `x-wk-model`, read above the
    // layer's element, which marks the boundary (CallerModel::onField()).
    $modelScope = $optimisticConfig && \Pushery\WireKit\Support\CallerModel::binds($attributes);
    $attributes = $modelScope ? \Pushery\WireKit\Support\CallerModel::onField($attributes) : $attributes;
@endphp

{{-- `wk-toggle` is the marker that puts this subtree inside the library's reduced-motion
     rule, and it is load-bearing rather than decorative. That rule is deliberately scoped
     to WireKit's own surface — it matches a `wk-` CLASS TOKEN and its descendants, because
     matching the class attribute as a substring once clamped a whole application's
     animations to 1ms through `bg-[var(--color-wk-bg)]` on its <body>. The knob below
     slides on `transition-transform` with the themed duration, and nothing here carried
     such a token, so on a page with no WireKit-classed ancestor the switch animated at
     full duration for a reader who had asked their operating system for no motion. The
     marker is also what lets an application's own motion setting win, since the
     `data-reduce-motion` escape hatch is written against the same selector. --}}
<div {{ $outerAttributes }} @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif class="wk-toggle relative space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    {{-- With help, the label shares a row with its button, which may not sit inside the
         label: it would become part of the control's name. --}}
    @if($helpId || $labelAfterGiven)
    <div data-wk-label-row class="{{ $labelAfterGiven ? 'flex flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]' : 'flex items-center gap-[var(--gap-wk-xs)]' }}">@if($labelAfterGiven)<span class="inline-flex min-w-0 items-center gap-[var(--gap-wk-xs)]">@endif
    @endif
    <label for="{{ $id }}" class="{{ $rowMarker }}inline-flex items-center gap-3 cursor-pointer">
        {{-- Switch visual: wrapper contains input (.peer), track, and knob as siblings --}}
        {{-- so peer-checked:* selectors resolve correctly (peer-checked targets siblings only). --}}
        <span class="{{ $wrapperClasses }}">
            {{-- Native checkbox: visually hidden but accessible (screen readers + Livewire wire:model) --}}
            {{-- role="switch" tells AT this is a toggle, not a regular checkbox --}}
            <input
                type="checkbox"
                id="{{ $id }}"
                @if($name !== '') name="{{ $name }}" @endif
                @unless($attributes->has('role')) role="switch" @endunless
                @if($fallbackAriaLabel) aria-label="{{ $fallbackAriaLabel }}" @endif
                @if($optimisticConfig)
                    x-ref="control"
                    @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                    x-bind:checked="value"
                    x-bind:aria-busy="isPending"
                    x-on:change="toggle()"
                @endif
                @if($isInvalid) aria-invalid="true" @endif
                @if($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
                @if($fieldRequired) aria-required="true" @endif
                {{-- `peer sr-only` rides the bag rather than sitting beside it: hardcoded, a
                     caller's own class became a second class attribute and the browser kept
                     only this one. --}}
                {{ $attributes->except('type')->except(['id', 'name', 'aria-describedby'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['peer', 'sr-only'])) }}
            />

            {{-- Track: sibling of .peer, background color flips via peer-checked --}}
            {{-- `wk-choice-frame` marks the element whose edge is the control, outside the resolved block so a
                 personalization keeps it. Under a preset whose border width is 0px the edge is gone, and
                 `.wk-choice-frame { --border-wk-width: 1px; }` in the application's stylesheet brings it back. --}}
            <span class="wk-choice-frame {{ $trackClasses }}" aria-hidden="true"></span>

            {{-- Knob: sibling of .peer, slides via peer-checked:translate-x-*.
                 `wk-toggle-knob` is the marker the stylesheet's forced-colors rule selects,
                 and it sits outside the class list on purpose — the same discipline
                 `wk-spinner` uses. --}}
            <span class="wk-toggle-knob {{ $knobClasses }}" aria-hidden="true"></span>
        </span>

        @if($label)
@php
    // Read, not consumed: a declared `required` prop would take the attribute out of the bag,
    // and the bag is what delivers it to the native control. A bare `required` arrives as
    // `true`.
    $wkRequiredMarker = (bool) $attributes->get('required', false);
@endphp
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none{{ $hideLabel ? ' sr-only' : '' }}">{{ $label }}@if($wkRequiredMarker)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</span>
        @endif

        @if($hasStateText)
            {{-- The state in words. Hidden from a screen reader, which hears the switch say it.
                 Both words are always in the markup and the stylesheet shows the one the
                 checkbox matches (`[data-wk-toggle-state]` in dist/wirekit.css), so it changes
                 with the click; `start` moves it before the switch there, by `order`. --}}
            <span data-wk-toggle-state="{{ $statePosition }}" aria-hidden="true" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)] select-none"><span data-wk-toggle-state-off>{{ $offLabel }}</span><span data-wk-toggle-state-on>{{ $onLabel }}</span></span>
        @endif
    </label>
    @if($helpId || $labelAfterGiven)
        @if($helpId)
            @include('wirekit::components.partials.field-help', ['helpText' => $help, 'helpName' => $helpLabel, 'helpId' => $helpId, 'helpButton' => ! $hideLabel, 'helpField' => (string) ($name !== '' ? $name : $id)])
        @endif
        @if($labelAfterGiven)</span>@if($hasLabelAfter)@include('wirekit::components.partials.label-after', ['labelAfter' => $labelAfter])@endif @endif
    </div>
    @endif

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting EMPTY. A live region that
             arrives together with its text is a new node rather than a changed
             region, and nothing is announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
