{{-- optimistic-ui: n/a — passthrough
     Each language is an `input` or a `textarea` of the kit with the caller's `wire:model` on its own
     key, so a field's value reaches the server exactly as a single field's does. The chips and the
     open rows are client-only state. --}}
@props([
    // The value's name: the legend over its languages, and the label of its field when there is one
    // language.
    'label' => null,
    // The languages: a map of code to name (`['de' => 'Deutsch', 'en' => 'English']`), or a list of
    // codes, which are named in the reader's language through `intl` and by their code without it.
    'locales' => [],
    // The code of the primary language. The first of `locales` without it.
    'primary' => null,
    // The text per language, a map of code to text, for a form without `wire:model`.
    'value' => null,
    // The base of the fields' names: `name[de]`, `name[en]`. Without it, a field bound with
    // `wire:model="name"` is named `name.de`, the key its error is filed under.
    'name' => null,
    // A `textarea` per language instead of an `input`.
    'multiline' => false,
    // The lines of each textarea, or `auto` to grow with its text.
    'rows' => 3,
    // How many further languages stand as fields without asking. With more, a chip opens its
    // language, one at a time, and "Show all languages" opens all of them.
    'inline' => 2,
    'hint' => null,
    // A short sentence for one language, under its field: a map of code to text, such as
    // `['en' => 'Translated by machine']`. A code that is not in `locales` is left out.
    'hints' => [],
    'help' => null,
    // A message about the value as a whole. An error of one language stands at its own field.
    'error' => null,
    // The primary language only: a translation stays optional.
    'required' => false,
    // The primary field's placeholder. A translation shows the primary text as its placeholder.
    'placeholder' => null,
    'disabled' => false,
    'readonly' => false,
    'size' => config('wirekit.components.input.size', 'md'),
    'scope' => null,
])

@aware(['wkField' => null, 'wkFieldSet' => null])

@php
    use Illuminate\View\ComponentAttributeBag;
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\FieldError;
    use Pushery\WireKit\Support\ListProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('translatable-input', $attributes->getAttributes());

    $multiline = BooleanProp::from($multiline, false, 'translatable-input', 'multiline');
    $required = BooleanProp::from($required, false, 'translatable-input', 'required');
    $disabled = BooleanProp::from($disabled, false, 'translatable-input', 'disabled');
    $readonly = BooleanProp::from($readonly, false, 'translatable-input', 'readonly');
    $inline = max(0, \Pushery\WireKit\Support\NumericProp::int($inline, 2, 'translatable-input', 'inline'));
    $size = WireKit::validateProp('translatable-input', 'size', $size, ['sm', 'md', 'lg']);
    // `error` false leaves the bag alone for the value as a whole and for the field of every
    // language, which reads it under its own key otherwise (`name.de`). A field around the
    // component with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);
    $errorOff = FieldError::isOff($error);
    $errorBag = $errorOff ? null : ($errors ?? null);

    // A language named in the reader's language, with a capital, since some languages write their
    // names in lower case ("anglais") and the name here starts a line of its own.
    $nameOf = static function (string $code): string {
        $name = class_exists(\Locale::class) ? (string) \Locale::getDisplayLanguage($code, app()->getLocale()) : '';

        if ($name === '' || strcasecmp($name, $code) === 0) {
            return strtoupper($code);
        }

        return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
    };

    // A code ends up in an id, an attribute selector and an Alpine expression, so it is a code and
    // nothing else: letters, digits, `-` and `_` (`pt-BR`, `zh_Hant`).
    $names = [];
    $refused = [];
    $given = ListProp::from($locales, 'translatable-input', 'locales');
    foreach (is_array($given) ? $given : [] as $key => $entry) {
        $code = is_int($key) ? (string) $entry : (string) $key;

        if (preg_match('/^[A-Za-z0-9_-]+$/', $code) !== 1) {
            if (config('app.debug')) {
                logger()->warning("WireKit [translatable-input]: \"{$code}\" is no language code and was left out.");
            }

            $refused[] = $code;

            continue;
        }

        $names[$code] = is_int($key) || ! filled($entry) ? $nameOf($code) : (string) $entry;
    }

    // Without a language there is no field to type in, and the form sends nothing for the value.
    // The application's language stands in, as one ordinary field. A list whose every code was
    // refused is reported as well: it named languages, and the page would show another one.
    if ($names === []) {
        $appLocale = (string) app()->getLocale();

        if ($refused !== []) {
            \Pushery\WireKit\Support\StrictnessGate::reject('translatable-input', 'locales', implode(', ', $refused), 'language codes of letters, digits, `-` and `_`', $appLocale);
        }

        if (preg_match('/^[A-Za-z0-9_-]+$/', $appLocale) === 1) {
            $names[$appLocale] = $nameOf($appLocale);
        }
    }

    $primaryCode = filled($primary) && array_key_exists((string) $primary, $names) ? (string) $primary : (string) (array_key_first($names) ?? '');
    $further = array_values(array_filter(array_keys($names), static fn (string $code): bool => $code !== $primaryCode));
    $collapsible = count($further) > $inline;

    // `wire:model` and `x-model` on the component, with their modifiers, bind each language by its
    // key: Livewire and Alpine both reach a key of an array by its dotted path.
    $models = [];
    foreach ($attributes->getAttributes() as $key => $path) {
        if (is_string($key) && is_string($path) && (str_starts_with($key, 'wire:model') || str_starts_with($key, 'x-model'))) {
            $models[$key] = $path;
        }
    }
    $attributes = $attributes->except(array_keys($models));
    $modelPath = $models === [] ? null : (string) reset($models);
    // Each language's field stands inside this root, where `.parent` would still find our names: an
    // `x-model` reaches each as `x-wk-model`, read above the root, which marks the boundary
    // (CallerModel::onField()).
    $fieldModels = \Pushery\WireKit\Support\CallerModel::onField(new \Illuminate\View\ComponentAttributeBag($models))->getAttributes();
    $modelScope = \Pushery\WireKit\Support\CallerModel::binds(new \Illuminate\View\ComponentAttributeBag($models));

    // What belongs to a field reaches each language's field, as on `input`, where the attributes
    // land on the field. A key listener on the root heard every key of the component, Enter on a
    // chip included, and ran the field's action where the reader meant to open a language; an
    // attribute of a text field did nothing there at all. A listener on the window or the document
    // stays on the root: on every field it would run once per language. `form` joins every
    // language to a form elsewhere on the page; on the root it is not a valid attribute and joins
    // nothing (Support\FormOwner). `pattern` checks every language's one-line field; a textarea
    // takes none, so a multiline input drops it. An `aria-describedby` describes the value, so
    // each language's field reads it after its own message; on the root it described nothing a
    // reader focuses. A `tabindex` is about the fields, the tab stops of the value.
    $fieldOwned = ['autocomplete', 'autocapitalize', 'autocorrect', 'spellcheck', 'inputmode', 'enterkeyhint', 'maxlength', 'minlength', 'form', 'pattern', 'aria-describedby', 'tabindex'];
    $fieldAttributes = [];
    foreach ($attributes->getAttributes() as $key => $value) {
        if (! is_string($key)) {
            continue;
        }

        $listener = preg_match('/^(?:wire:|x-on:|@)key(?:down|up|press)(?:\.|$)/', $key) === 1
            && preg_match('/\.(?:window|document)(?:\.|$)/', $key) !== 1;

        if ($listener || in_array($key, $fieldOwned, true)) {
            $fieldAttributes[$key] = $value;
        }
    }
    $attributes = $attributes->except(array_keys($fieldAttributes));

    if ($multiline) {
        unset($fieldAttributes['pattern']);
    }

    // A caller's name for the value. On the root, a `div` with no role, ARIA allows none and a
    // screen reader reads none: with one language it names the field, as on `input`, and with more
    // it names the set, whose fields keep their language's name (Support\CallerName). Only a name
    // that is there is handed on, since the set reads a key it holds as a name of the caller's.
    [$callerLabelledBy, $callerLabel, $attributes] = \Pushery\WireKit\Support\CallerName::split($attributes);
    $callerNames = array_filter(['aria-labelledby' => $callerLabelledBy, 'aria-label' => $callerLabel], static fn (?string $value): bool => $value !== null);

    $texts = ListProp::from($value);
    $texts = is_array($texts) ? $texts : [];

    $rowHints = ListProp::from($hints, 'translatable-input', 'hints');
    $rowHints = is_array($rowHints) ? $rowHints : [];
    // The sentence as it was given, so a string and an `HtmlString` print as they do on a field.
    $hintOf = static fn (string $code) => isset($rowHints[$code]) && filled($rowHints[$code]) ? $rowHints[$code] : null;

    // A caller's id names the whole value and stands on the root; each field's id is built from it.
    $callerId = filled($attributes->get('id')) ? (string) $attributes->get('id') : null;
    $baseId = \Pushery\WireKit\Support\DomId::unique($callerId ?? $name ?? $modelPath, 'translatable-');
    $attributes = $attributes->except('id');

    // Everything one language's field needs. A bound field takes its text from its model, so only
    // a form without one renders the text.
    $fields = [];
    foreach (array_keys($names) as $code) {
        $fieldName = filled($name) ? "{$name}[{$code}]" : ($modelPath !== null ? "{$modelPath}.{$code}" : null);
        // Only what is safe to print as it is: the code, `lang` and the caller's models. The code
        // names the field as it was given; `lang` takes it as HTML reads a language, so `pt_BR` is
        // the tag `pt-BR`.
        $bag = ['data-wk-translation-field' => $code, 'lang' => \Pushery\WireKit\Support\LanguageTag::of((string) $code)];

        foreach ($fieldModels as $key => $path) {
            $bag[$key] = "{$path}.{$code}";
        }

        $bag += $fieldAttributes;

        $fields[$code] = [
            'id' => "{$baseId}-{$code}",
            'row' => "{$baseId}-{$code}-row",
            // Empty rather than null without a name, a model or a caller's `id`: the field takes the id
            // it is given here as a caller's, and would be sent under it.
            'name' => $fieldName ?? (filled($callerId) ? null : ''),
            'bag' => $bag,
            'text' => $models === [] ? (is_scalar($texts[$code] ?? null) ? (string) $texts[$code] : '') : null,
            'invalid' => FieldError::has($errorBag, $fieldName),
        ];
    }

    // The group answers only for the value as a whole, under its own key and no key below it. An
    // error of one language (`name.de`) is its field's, which shows it; the group key `name` of a
    // `field.set` would take it in as well and show it a second time.
    $groupError = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errorBag, filled($name) ? (string) $name : $modelPath, $error);

    $primaryText = $primaryCode !== '' ? ($fields[$primaryCode]['text'] ?? null) : null;
    $translationPlaceholder = filled($primaryText) ? (string) $primaryText : \Pushery\WireKit\Support\SlotContent::textOf($placeholder);

    // A state the server already knows: the text of a form without a model, and an error.
    $initialState = static fn (array $field): string => $field['invalid'] ? 'error' : (filled($field['text']) ? 'translated' : 'missing');

    $states = [
        'translated' => __('wirekit::translated'),
        'missing' => __('wirekit::missing'),
        'error' => __('wirekit::has an error'),
    ];

    $config = [
        'primary' => $primaryCode,
        'locales' => array_keys($names),
        'names' => $names,
        'collapsed' => $collapsible,
        'placeholder' => \Pushery\WireKit\Support\SlotContent::textOf($placeholder),
        'states' => $states,
    ];

    $classes = WireKit::resolveClasses('translatable-input', 'base', 'min-w-0', $scope);

    // The code beside each field, as tall as the field so the two line up in every size.
    $codeHeight = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)]',
        default => 'h-[var(--size-wk-md)]',
    };

    $rowClasses = WireKit::resolveClasses('translatable-input', 'row', 'grid grid-cols-[auto_minmax(0,1fr)] items-start gap-x-[var(--gap-wk-sm)]', $scope);

    $codeBox = implode(' ', [
        'inline-flex items-center justify-center',
        $codeHeight,
        'min-w-[var(--size-wk-sm)]',
        'px-[var(--padding-wk-x-xs)]',
        'rounded-[var(--radius-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[length:var(--text-wk-xs)]',
        'select-none',
    ]);

    $codeClasses = WireKit::resolveClasses('translatable-input', 'code', $codeBox.' font-medium text-[color:var(--color-wk-text-muted)]', $scope);

    // The primary language's code reads as the main one: the same box, a heavier and darker text.
    $primaryCodeClasses = WireKit::resolveClasses('translatable-input', 'primary-code', $codeBox.' font-semibold text-[color:var(--color-wk-text)]', $scope);

    $chipsClasses = WireKit::resolveClasses('translatable-input', 'chips', 'flex flex-wrap items-center gap-[var(--gap-wk-xs)]', $scope);

    // The state is in the chip's name and in its glyph; the colors follow it and never carry it alone.
    // A chip is a control, so its edge is a control's (WCAG 1.4.11). The open language's chip is
    // marked `data-active` and painted in the chosen shade. Chips stand side by side, so on a touch
    // screen each takes the 44px as its own size through `wk-touch-row`, rather than a hit area that
    // would reach into its neighbor; the marker stands in front of the block, so a string
    // personalization of it keeps the size. `relative` makes the chip the containing block of the
    // state it keeps for screen readers, so that text is placed inside the chip, not against the page.
    $chipClasses = WireKit::resolveClasses('translatable-input', 'chip', implode(' ', [
        'relative inline-flex items-center gap-[var(--gap-wk-xs)]',
        'min-h-[var(--size-wk-xs)]',
        'px-[var(--padding-wk-x-sm)]',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-strong)]',
        'bg-[var(--color-wk-bg)]',
        'text-[length:var(--text-wk-xs)]',
        'font-medium',
        'text-[color:var(--color-wk-text-muted)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-hover)]',
        'focus-visible:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'data-[state=translated]:border-[var(--color-wk-border-success)]',
        'data-[state=translated]:text-[color:var(--color-wk-success-text)]',
        'data-[state=error]:border-[var(--color-wk-border-error)]',
        'data-[state=error]:text-[color:var(--color-wk-danger-text)]',
        'data-[active]:bg-[var(--color-wk-bg-active)]',
        'data-[active]:text-[color:var(--color-wk-text)]',
        'disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)]',
    ]), $scope);

    // The space between two rows sits inside each row (partials/translatable-row): a closed row
    // keeps a box of its own where the browser's find in page can open it, and a gap between the
    // boxes would stand for every closed language. The container's pull cancels the first row's
    // push. Both are margins of the space family, the value of the gap the rows had.
    $furtherClasses = WireKit::resolveClasses('translatable-input', 'further', 'grid gap-x-[var(--gap-wk-sm)] -mt-[var(--space-wk-sm)]', $scope);
    $furtherRowClasses = $rowClasses.' mt-[var(--space-wk-sm)]';

    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's listener for an event this view listens to on its root goes in the other
    // spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:input']);
    // The field around the component and the set's group below are read by `@aware`, which a
    // caller's attribute of the same name would otherwise also reach the root as.
    $attributes = $attributes->except(['wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);

    // A mark after the legend, whatever the caller's `labelAfter` slot holds. A slot that holds only
    // Livewire's morph markers is no mark. The required star is the set's, below.
    $callerAfter = isset($labelAfter) && $labelAfter instanceof \Illuminate\View\ComponentSlot && $labelAfter->hasActualContent() ? $labelAfter : null;
    $requiredMark = $required && filled($label);

    // `auto` grows each textarea with its text, as on `textarea`.
    $rows = $rows === 'auto' || $rows === true ? 'auto' : max(1, \Pushery\WireKit\Support\NumericProp::int($rows, 3, 'translatable-input', 'rows'));
@endphp

<div
    @if($callerId !== null) id="{{ $callerId }}" @endif
    x-data="wirekitTranslatableInput({{ AlpinePayload::from($config) }})"
    @if($modelScope) data-wk-ref-scope @endif
    x-on:input="typed($event)"
    data-wk-translatable
    {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }}
    {{-- `focus` and `blur` fire here as the reader enters and leaves the control as a whole, so a
         listener written on the tag hears one field (utils/focus-unit.js). --}}
    x-wk-focus-unit
>
    @if($primaryCode !== '' && $further === [])
    {{-- One language and no other: one field, drawn as `input` or `textarea` draws one, with its
         label in sight and no code beside it. The binding, the messages and the help are the ones
         the language would take in a row, so a form reads as it did before the shop had a second
         language, and shows the codes from the second one on. The caller's mark goes to the field
         as a slot that is always there and empty without one: the field reads an empty one as
         none, and a slot inside an `@if` would end in a directive glued to the closing tag, which
         Blade does not compile. Nothing stands between the tags, since a textarea's text is its
         default slot. --}}
    @php($single = $fields[$primaryCode])
    @if($multiline)
        <x-wirekit::textarea
            :id="$single['id']"
            :name="$single['name']"
            :label="$label"
            :hint="filled($hint) ? $hint : $hintOf($primaryCode)"
            :help="$help"
            :error="$errorOff ? false : $groupError"
            :rows="$rows"
            :size="$size"
            :placeholder="filled($placeholder) ? \Pushery\WireKit\Support\SlotContent::textOf($placeholder) : null"
            :required="$required"
            :disabled="$disabled"
            :readonly="$readonly"
            :scope="$scope"
            :attributes="new ComponentAttributeBag($single['bag'] + $callerNames)"
        ><x-slot:labelAfter>{{ $callerAfter ?? '' }}</x-slot:labelAfter>{{ (string) ($single['text'] ?? '') }}</x-wirekit::textarea>
    @else
        <x-wirekit::input
            :id="$single['id']"
            :name="$single['name']"
            :label="$label"
            :hint="filled($hint) ? $hint : $hintOf($primaryCode)"
            :help="$help"
            :error="$errorOff ? false : $groupError"
            :size="$size"
            :value="$single['text']"
            :placeholder="$placeholder"
            :required="$required"
            :disabled="$disabled"
            :readonly="$readonly"
            :scope="$scope"
            :attributes="new ComponentAttributeBag($single['bag'] + $callerNames)"
        ><x-slot:labelAfter>{{ $callerAfter ?? '' }}</x-slot:labelAfter></x-wirekit::input>
    @endif
    @else
    {{-- The value stands for one field, so the set draws its legend as a field's label and its
         message under the rows (`as-field`): the primary language's field then stands on the
         height of a field beside the component. --}}
    {{-- The primary field's own label is visually hidden, so its required mark would be too: the set
         draws the star right after the legend's text, where a field's label draws it, and says it in
         the caption. --}}
    {{-- The set's help names the value, by its name, its model or, without either, its id: the set
         reads no error-bag key, so it has no name of its own to give. --}}
    <x-wirekit::field.set as-field :legend="$label" :hint="$hint" :help="$help" :help-field="filled($name) ? (string) $name : ($modelPath ?? $baseId)" :error="$groupError" :required="$requiredMark" :scope="$scope" :attributes="new ComponentAttributeBag($callerNames)">
        {{-- The set hands its group to the controls in it by `@aware`, as it does to its checkboxes.
             Read here, inside its slot, where the set is the nearest component; the rows take it
             from this scope (partials/translatable-row). --}}
        @aware(['wkFieldSet' => null])
        {{-- A caller's mark stands after the legend, outside the group's name, as a field's does
             after its label. --}}
        @if($callerAfter !== null)
            <x-slot:labelAfter>{{ $callerAfter }}</x-slot:labelAfter>
        @endif

        @if($primaryCode !== '')
            @include('wirekit::components.partials.translatable-row', [
                'code' => $primaryCode,
                'field' => $fields[$primaryCode],
                'languageName' => $names[$primaryCode],
                'collapsible' => false,
                'closed' => false,
                'required' => $required,
                'rowClasses' => $rowClasses,
                'codeClasses' => $primaryCodeClasses,
                'placeholderText' => filled($placeholder) ? \Pushery\WireKit\Support\SlotContent::textOf($placeholder) : null,
                'rowHint' => $hintOf($primaryCode),
                'bag' => new ComponentAttributeBag($fields[$primaryCode]['bag']),
            ])
        @endif

        @if($further !== [])
            {{-- One chip per further language with its state. With every row on screen a chip
                 takes the focus to its field; with rows that open on demand it opens its own. --}}
            <div class="{{ $chipsClasses }}" role="group" aria-label="{{ __('wirekit::Translations') }}">
                @foreach($further as $code)
                    @php($codePayload = AlpinePayload::string($code))
                    <button
                        type="button"
                        data-wk-translation-chip="{{ $code }}"
                        data-state="{{ $initialState($fields[$code]) }}"
                        x-bind:data-state="state({{ $codePayload }})"
                        x-on:click="choose({{ $codePayload }})"
                        @if($collapsible)
                            aria-controls="{{ $fields[$code]['row'] }}"
                            aria-expanded="{{ $fields[$code]['invalid'] ? 'true' : 'false' }}"
                            x-bind:aria-expanded="isOpen({{ $codePayload }}) ? 'true' : 'false'"
                            @if($fields[$code]['invalid']) data-active @endif
                            x-bind:data-active="isOpen({{ $codePayload }})"
                        @endif
                        @if($disabled) disabled @endif
                        class="wk-touch-row {{ $chipClasses }}"
                    >
                        <span aria-hidden="true">{{ strtoupper($code) }}</span>
                        <span aria-hidden="true" class="inline-flex" x-cloak x-show="state({{ $codePayload }}) === 'translated'"><x-wirekit::icon name="check" size="xs" /></span>
                        <span aria-hidden="true" class="inline-flex" x-cloak x-show="state({{ $codePayload }}) === 'missing'"><x-wirekit::icon name="minus" size="xs" /></span>
                        <span aria-hidden="true" class="inline-flex" x-cloak x-show="state({{ $codePayload }}) === 'error'"><x-wirekit::icon name="warning" size="xs" /></span>
                        <span class="sr-only" x-text="chipLabel({{ $codePayload }})">{{ $names[$code] }}: {{ $states[$initialState($fields[$code])] }}</span>
                    </button>
                @endforeach

                @if($collapsible)
                    <x-wirekit::button size="xs" intent="neutral" surface="ghost" aria-expanded="false" x-bind:aria-expanded="all ? 'true' : 'false'" x-on:click="toggleAll()" :disabled="$disabled" :scope="$scope">
                        <span x-show="! all">{{ __('wirekit::Show all languages') }}</span>
                        <span x-cloak x-show="all">{{ __('wirekit::Show fewer languages') }}</span>
                    </x-wirekit::button>
                @endif
            </div>

            {{-- In two columns from `sm` on while every language is open. --}}
            <div class="{{ $furtherClasses }}" @if($collapsible) x-bind:class="all ? 'sm:grid-cols-2' : ''" @endif>
                @foreach($further as $code)
                    @include('wirekit::components.partials.translatable-row', [
                        'code' => $code,
                        'field' => $fields[$code],
                        'languageName' => $names[$code],
                        'collapsible' => $collapsible,
                        'closed' => $collapsible && ! $fields[$code]['invalid'],
                        'required' => false,
                        'rowClasses' => $furtherRowClasses,
                        'codeClasses' => $codeClasses,
                        'placeholderText' => $translationPlaceholder !== '' ? $translationPlaceholder : null,
                        'rowHint' => $hintOf($code),
                        'bag' => new ComponentAttributeBag($fields[$code]['bag'] + ['x-bind:placeholder' => "placeholderFor('{$code}')"]),
                    ])
                @endforeach
            </div>
        @endif
    </x-wirekit::field.set>
    @endif
</div>
