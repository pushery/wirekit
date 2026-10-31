{{-- optimistic-ui: supported
     A native select with a server-side value is a mutation the browser has
     already applied. The concurrency question is sharper here than on a toggle:
     two changes in flight would resolve to whichever answered last, which is
     why the mode is reject. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. Null leaves the component
    // exactly as it has always rendered.
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
    'hideLabel' => false, // render the label sr-only (kept for assistive tech) — for compact toolbar / header fields
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    // Keep the message line's height whether or not there is a message.
    //
    // Wasted space in a stacked form, and the difference between a working
    // toolbar and one that jumps in a horizontal row: an appearing error grows
    // this element, and every sibling in the row re-anchors to the new bottom
    // edge. Aligning the row does not fix it — `items-end` follows the growth,
    // and `items-start` lines things up with the label rather than the control.
    'reserveMessage' => false,
    'error' => null,
    // Success / valid state — string shows a green confirmation below, `true`
    // shows just the green border. `error` always wins when both are set.
    'success' => null,
    'size' => config('wirekit.components.select.size', 'md'),
    'placeholder' => null,
    'options' => [],
    // What the field says when there is nothing to choose: `options` is empty and no option
    // arrives through the slot. A selection filled from records shows only its placeholder
    // before the first record exists, and the reader learns neither what is missing nor where
    // to create it. Set, the select is disabled while it is empty, and the sentence below it is
    // its description. Unset, an empty select renders as it always has.
    'emptyText' => null,
    // Where the missing record is created. Renders a link after `empty-text`; leave it null for
    // a reader who may not create one.
    'emptyHref' => null,
    // The link's text. Name what it creates ("Add a supplier"); without it the link reads
    // "Create one".
    'emptyAction' => null,
    // Whether the link moves to that screen with Livewire's `wire:navigate`, as the other links
    // of an application that changes screens without reloading do. A link to a fragment of the
    // same page never takes it, since Livewire would fetch the page instead of scrolling to it.
    'emptyNavigate' => false,
    // The pre-selected option. It has to be a declared prop: `<select>` has no
    // `value` content attribute, so an undeclared one fell into the attribute bag
    // and rendered onto the element, where HTML ignores it. `value="pro"` then
    // selected nothing and the browser fell back to the first option — the field
    // showed one choice while everything reading the component believed another.
    'value' => null,
    'scope' => null,
])

@aware(['announceErrors' => null, 'alignFields' => false, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $hideLabel = BooleanProp::from($hideLabel, false, 'select', 'hideLabel');
    $reserveMessage = BooleanProp::from($reserveMessage, false, 'select', 'reserveMessage');
    $emptyNavigate = BooleanProp::from($emptyNavigate, false, 'select', 'emptyNavigate');
    // Never for a place on the same page, where Livewire would fetch the page instead of scrolling.
    $emptyNavigates = $emptyNavigate && ! str_starts_with((string) $emptyHref, '#');

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'alignFields', 'align-fields', 'wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);

    // In a row that lines its fields up (`row align-fields`), the field takes the row's three
    // tracks itself, label, control and message, as `field` does: a button beside it then
    // stands level with the control whatever is above or below it. Not inside a `field`,
    // which takes the tracks for the control it wraps.
    $inAlignedRow = \Pushery\WireKit\Support\BooleanProp::from($alignFields, false)
        && ! ($wkField instanceof \Pushery\WireKit\Support\FieldControl);

    // A select holds one value, so a list is none of its values: `old('country')` gives one when a
    // request sends `country[]`. It is reported and selects nothing, rather than reach the string
    // comparison below, where a list does not cast.
    if (is_array($value)) {
        \Pushery\WireKit\Support\StrictnessGate::reject('select', 'value', 'array', 'one value; several values are a multi-select', '');
        $value = null;
    }

    // An enum, as a model attribute cast to one hands it over, is the option of its value.
    $value = \Pushery\WireKit\Support\FieldValue::one($value, 'select');

    // Compared as strings, deliberately. Option keys arrive from PHP arrays, where
    // `['1' => 'One']` is an INT key, while a `value` written on a tag is always a
    // string — a strict comparison would leave numeric-keyed option lists unable to
    // pre-select anything, which is the same defect one type-cast further down.
    $isSelected = fn ($candidate): bool => $value !== null && (string) $value === (string) $candidate;
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    // A select draws one value in the height of a field, with the chevron of a menu. A native
    // `multiple` turns it into a list box clipped to that height, under a chevron that opens
    // nothing, so it is reported with the component that holds several values. The attribute
    // stays on the element, and a page that styled the list box keeps it.
    if ($attributes->has('multiple')) {
        \Pushery\WireKit\Support\StrictnessGate::reject('select', 'multiple', 'true', 'a select without it; several values are a multi-select', 'true');
    }


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('select', $attributes->getAttributes());

    // A select gives its value as text, and Livewire takes the server's echo of an `int` or
    // `float` property for a change and writes it over a newer choice: a binding to one gets
    // `.number` and sends a number (Support\NumericModel).
    $attributes = \Pushery\WireKit\Support\NumericModel::number($attributes);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // The id from the attribute or the name; with neither, DomId counts one per request.
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'select-'); // page-unique DOM id; see Support\DomId
    // Inside a field the label points at the id this control got: a second field with the same
    // name on the page gets `-2`, and pointed at the name its label named the first control.
    if ($wkField instanceof \Pushery\WireKit\Support\FieldControl) {
        $wkField->follow($id);
    }
    // An empty `name` renders none: the field is not meant to be submitted, as inline-edit's own
    // control is not. HTML does not allow an empty name, and a form skips a field without one.
    // The bag holds a bound name escaped once; read as the text it stands for, so the field is
    // sent under the name the caller bound (Support\AttributeText).
    // Without a `name` the field is sent under the caller's `id`, and without either it is not
    // sent at all: an id DomId counted out names nothing a form should carry.
    $name = \Pushery\WireKit\Support\AttributeText::get($attributes, 'name', filled($attributes->get('id')) ? $id : '');
    // Without a `name`, the bag files this field's error under the property its `wire:model`
    // binds (FieldError::nameOf).
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf(\Pushery\WireKit\Support\AttributeText::get($attributes, 'name'), $attributes) ?? $name;
    // Strip the caller's `id` AND `name` from the bag: both are rendered explicitly
    // below, so leaving either in the bag emits a second, conflicting attribute on the
    // same element, which is invalid HTML the browser accepts silently by keeping the first.
    $attributes = $attributes->except(['id', 'name']);

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    // Error detection: explicit prop OR Laravel validation bag
    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errors ?? null, $errorName, $error);
    $hasError = $errorMessage !== null;

    // Success / valid state — only when there is no error (error wins).
    // Tri-state (null | true | string message): `!== false` alone let the unbound
    // string 'false' (truthy) paint the success state — isFalse recognizes the
    // stringly-false spellings without collapsing a real success message.
    $hasSuccess = ! $hasError && $success !== null && ! BooleanProp::isFalse($success);
    // A message as text or as markup, as `hint` takes it; `true` alone is the state without one.
    $successMessage = is_string($success) || $success instanceof \Illuminate\Contracts\Support\Htmlable ? $success : null;

    // The empty state needs the caller's sentence or the `empty` slot, and a select with
    // nothing to choose: no entry in `options` and no option written into the slot. It takes
    // the hint's place, because a hint says how to choose and there is nothing to choose yet.
    // An error still shows beside it.
    $optionList = \Pushery\WireKit\Support\ListProp::from($options, 'select', 'options');
    $showEmpty = $optionList === [] && ! $slot->hasActualContent() && (filled($emptyText) || \Pushery\WireKit\Support\SlotContent::given($empty ?? null));
    $showHint = $hint && ! $showEmpty;

    // One description list for the control: the component's own ids first, then a caller's
    // aria-describedby. Written as separate attributes, the parser kept only the first copy,
    // so a caller's description was dropped or pushed the component's own out.
    $describedBy = implode(' ', array_filter([
        $showEmpty ? $id.'-empty' : '',
        $hasError ? $id.'-error' : ($hasSuccess && $successMessage ? $id.'-success' : ($showHint ? $id.'-hint' : '')),
        trim((string) $attributes->get('aria-describedby', '')),
    ], static fn (string $part): bool => $part !== ''));
    // The field's help, after its own message: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered.
    $helpId = filled($help) && filled($label) ? $id.'-help' : null;
    // A `field` around this control renders its hint below it, and the control is described by it
    // as by a hint of its own (Support\FieldControl).
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $id, $hint ? $id.'-hint' : null, $helpId ?? null);
    // A required `field` around this control hides its asterisk from assistive technology, so
    // the control says it, with `aria-required` and never the browser's own check
    // (Support\FieldControl::requiredBy()).
    $fieldRequired = \Pushery\WireKit\Support\FieldControl::requiredBy($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, (bool) $attributes->get('required', false));
    $describedBy = trim($describedBy.' '.($helpId ?? ''));

    // Disabled while there is nothing to choose. `merge` keeps a caller's own `disabled`
    // rather than writing the attribute twice.
    if ($showEmpty) {
        $attributes = $attributes->merge(['disabled' => true]);
    }

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    $selectClasses = WireKit::resolveClasses('select', 'base', implode(' ', [
        // `overflow-hidden` because with `appearance: none` WebKit paints the chosen value itself, and a
        // value wider than the field counted into the scrollable width of every ancestor: invisible,
        // since the text ends at the chevron, yet the page scrolled sideways on a phone. No
        // ellipsis utility: on this select it draws no ellipsis in either engine.
        // (Named in words on purpose: a class name in a comment is still a class to Tailwind's scanner.)
        'block w-full appearance-none overflow-hidden',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'cursor-pointer',
    ]), $scope);

    // A width utility in the caller's `class` replaces the field's own `w-full`, as on a number input.
    // Beside it the stylesheet's order would decide which width wins, not the attribute. A width
    // under a variant is left alone, because it applies at a width of its own.
    if (\Pushery\WireKit\Support\UtilityClasses::has(\Illuminate\Support\Arr::toCssClasses($attributes->get('class', '')), '/^w-/')) {
        $selectClasses = \Pushery\WireKit\Support\UtilityClasses::without($selectClasses, '/^w-/');
    }

    // Border and focus-ring color switch between error, success, and normal state — all via
    // tokens. Each state names its own ring color: a second one in the base list would be
    // decided by the stylesheet's order, which put the resting ring over the error one.
    $stateClasses = match (true) {
        (bool) $hasError => 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]',
        $hasSuccess => 'border-[var(--color-wk-border-success)] focus-visible:ring-[var(--color-wk-success)]',
        // Hover belongs to the resting state only, so an error or a success border keeps its
        // color under the pointer.
        default => 'border-[var(--color-wk-border-strong)] hover:border-[var(--color-wk-border-strong-hover)] focus-visible:ring-[var(--color-wk-ring)]',
    };

    // Size classes: height, padding, font size, radius — all from sizing tokens
    // pr-8 kept for dropdown arrow space
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'pr-8',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md-compact' => implode(' ', [
            'h-[var(--size-wk-md-compact)]',
            'px-[var(--padding-wk-x-md)]',
            'pr-8',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'md' => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'pr-8',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'pr-8',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => WireKit::validateProp('select', 'size', $size, ['sm', 'md-compact', 'md', 'lg']),
    };
@endphp

@php
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (string) ($value ?? ''),
        'action' => $optimistic,
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
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

<div {{ $outerAttributes }} @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif @if($inAlignedRow) data-wk-field @endif class="relative space-y-1.5 min-w-0" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        {{-- The asterisk flag is READ from the bag rather than declared as a prop, deliberately:
             declaring it would pull `required` OUT of the bag, and the bag is what carries the
             attribute to the native control below. A bare `required` lands in the bag as
             `true`, so this reads it without consuming it. --}}
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name !== '' ? $name : $id" :for="$id" :required="(bool) $attributes->get('required', false)" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($inAlignedRow)
        {{-- An empty label track, held so that the control stays on the middle one. --}}
        <span data-wk-field-part="label" aria-hidden="true"></span>
    @endif

    <div class="relative">
        <select
            id="{{ $id }}"
            @if($name !== '') name="{{ $name }}" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($optimisticConfig)
                x-ref="control"
                @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($fieldRequired) aria-required="true" @endif
            {{-- wk-field: lifts font-size to the 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->except('aria-describedby')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['wk-field', $selectClasses, $stateClasses, $sizeClasses])) }}
        >
            @if($placeholder)
                {{-- The placeholder only pre-selects itself when nothing else is
                     chosen. Marking it `selected` unconditionally is what made a
                     `value` prop invisible even once the options below honored it. --}}
                <option value="" disabled{{ ($value === null || $value === '') ? ' selected' : '' }}>{{ $placeholder }}</option>
            @endif
            {{--
                Options accept three shapes (mix freely):
                  - Flat:            ['de' => 'Germany']                       → <option>
                  - Per-option attrs:['de' => ['label' => 'Germany',
                                               'disabled' => true,
                                               'lang' => 'de']]                → disabled <option>
                  - Grouped:         ['Europe' => ['de' => 'Germany', ...]]    → <optgroup>
                A group is an array value WITHOUT a 'label' key; a single option
                with attributes is an array value WITH a 'label' key.

                `lang` exists for one control and that control is common: a language picker
                lists endonyms, so `Deutsch`, `Español` and `Français` are words in a language
                the document is not in. Without it a screen reader says all of them in the
                page's voice — WCAG 2.1 AA 3.1.2, whose proper-name exception does not apply,
                because an endonym is not a borrowed word. It is the one control that exists
                FOR readers of those languages, so it is the one place their language must not
                be mispronounced.

                No automated run catches its absence. axe's `valid-lang` rule carries the
                wcag312 tag but only validates a lang attribute that is PRESENT; with none
                there are no nodes to judge, and the scan reports zero violations.
            --}}
            @foreach($optionList as $optionValue => $optionLabel)
                @if(is_array($optionLabel) && ! array_key_exists('label', $optionLabel))
                    <optgroup label="{{ $optionValue }}">
                        @foreach($optionLabel as $subValue => $subLabel)
                            @php
                                $sLabel = is_array($subLabel) ? ($subLabel['label'] ?? $subValue) : $subLabel;
                                $sDisabled = is_array($subLabel) && ! empty($subLabel['disabled']);
                                $sLang = is_array($subLabel) ? \Pushery\WireKit\Support\LanguageTag::of($subLabel['lang'] ?? null) : null;
                            @endphp
                            <option value="{{ $subValue }}"@if($sLang !== '' && $sLang !== null) lang="{{ $sLang }}"@endif{{ $sDisabled ? ' disabled' : '' }}{{ $isSelected($subValue) ? ' selected' : '' }}>{{ $sLabel }}</option>
                        @endforeach
                    </optgroup>
                @else
                    @php
                        $oLabel = is_array($optionLabel) ? ($optionLabel['label'] ?? $optionValue) : $optionLabel;
                        $oDisabled = is_array($optionLabel) && ! empty($optionLabel['disabled']);
                        $oLang = is_array($optionLabel) ? \Pushery\WireKit\Support\LanguageTag::of($optionLabel['lang'] ?? null) : null;
                    @endphp
                    <option value="{{ $optionValue }}"@if($oLang !== '' && $oLang !== null) lang="{{ $oLang }}"@endif{{ $oDisabled ? ' disabled' : '' }}{{ $isSelected($optionValue) ? ' selected' : '' }}>{{ $oLabel }}</option>
                @endif
            @endforeach
            {{ $slot }}
        </select>

        {{-- Dropdown arrow indicator — color via design token for automatic dark mode --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
            <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>

    {{-- Nothing to choose: what is missing, and where it is created (partials/empty-choice). --}}
    @if($showEmpty)
        @include('wirekit::components.partials.empty-choice', [
            'emptyFor' => 'select',
            'emptyId' => $id.'-empty',
            'emptySlot' => $empty ?? null,
            'emptyClass' => null,
        ])
    @endif

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
    {{-- See the `reserve-message` prop: an appearing message grows this element
         and pushes every sibling in a horizontal row. It is `select-none` for the same
         reason it is `aria-hidden` — it holds space, not text, and a drag-select across the
         form should not carry its no-break space into the clipboard. --}}
    @if($inAlignedRow && ! ($reserveMessage || ($hasError && $errorMessage) || ($hasSuccess && $successMessage) || $showHint || $showEmpty))
        {{-- Nothing below the control, and the track still ends here, or the row's next field
             would be pulled up into it. --}}
        <span data-wk-field-part="message" aria-hidden="true"></span>
    @endif
    @if($reserveMessage && ! (($hasError && $errorMessage) || ($hasSuccess && $successMessage) || $showHint || $showEmpty))
        <p data-wk-prose-skip aria-hidden="true" class="select-none text-[length:var(--text-wk-sm)]">&nbsp;</p>
    @endif
    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hasSuccess && $successMessage)
        <p data-wk-prose-skip id="{{ $id }}-success" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-success-text)]">{{ $successMessage }}</p>
    @elseif($showHint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
