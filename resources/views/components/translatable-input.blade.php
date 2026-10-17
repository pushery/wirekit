{{-- optimistic-ui: n/a — passthrough
     Each language is an `input` or a `textarea` of the kit with the caller's `wire:model` on its own
     key, so a field's value reaches the server exactly as a single field's does. The chips and the
     open rows are client-only state. --}}
@props([
    // The value's name, the legend of the group.
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

    $multiline = BooleanProp::from($multiline, false);
    $required = BooleanProp::from($required, false);
    $disabled = BooleanProp::from($disabled, false);
    $readonly = BooleanProp::from($readonly, false);
    $inline = max(0, is_numeric($inline) ? (int) $inline : 2);
    $size = WireKit::validateProp('translatable-input', 'size', $size, ['sm', 'md', 'lg']);
    $errorBag = $errors ?? null;

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
    $given = ListProp::from($locales);
    foreach (is_array($given) ? $given : [] as $key => $entry) {
        $code = is_int($key) ? (string) $entry : (string) $key;

        if (preg_match('/^[A-Za-z0-9_-]+$/', $code) !== 1) {
            if (config('app.debug')) {
                logger()->warning("WireKit [translatable-input]: \"{$code}\" is no language code and was left out.");
            }

            continue;
        }

        $names[$code] = is_int($key) || ! filled($entry) ? $nameOf($code) : (string) $entry;
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

    $texts = ListProp::from($value);
    $texts = is_array($texts) ? $texts : [];

    // A caller's id names the whole value and stands on the root; each field's id is built from it.
    $callerId = filled($attributes->get('id')) ? (string) $attributes->get('id') : null;
    $baseId = \Pushery\WireKit\Support\DomId::unique($callerId ?? $name ?? $modelPath, 'translatable-');
    $attributes = $attributes->except('id');

    // Everything one language's field needs. A bound field takes its text from its model, so only
    // a form without one renders the text.
    $fields = [];
    foreach (array_keys($names) as $code) {
        $fieldName = filled($name) ? "{$name}[{$code}]" : ($modelPath !== null ? "{$modelPath}.{$code}" : null);
        // Only what is safe to print as it is: the code, `lang` and the caller's models.
        $bag = ['data-wk-translation-field' => $code, 'lang' => $code];

        foreach ($models as $key => $path) {
            $bag[$key] = "{$path}.{$code}";
        }

        $fields[$code] = [
            'id' => "{$baseId}-{$code}",
            'row' => "{$baseId}-{$code}-row",
            'name' => $fieldName,
            'bag' => $bag,
            'text' => $models === [] ? (is_scalar($texts[$code] ?? null) ? (string) $texts[$code] : '') : null,
            'invalid' => FieldError::has($errorBag, $fieldName),
        ];
    }

    // The group answers only for the value as a whole, under its own key and no key below it. An
    // error of one language (`name.de`) is its field's, which shows it; the group key `name` of a
    // `field.set` would take it in as well and show it a second time.
    $groupError = filled($error) ? $error : FieldError::first($errorBag, filled($name) ? (string) $name : $modelPath);

    $primaryText = $primaryCode !== '' ? ($fields[$primaryCode]['text'] ?? null) : null;
    $translationPlaceholder = filled($primaryText) ? (string) $primaryText : (string) ($placeholder ?? '');

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
        'placeholder' => (string) ($placeholder ?? ''),
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
    // The set's group below is read by `@aware`, which a caller's attribute of the same name would
    // otherwise also reach the root as.
    $attributes = $attributes->except(['wkFieldSet', 'wk-field-set']);

    // A mark after the legend: the required star, and whatever the caller's `labelAfter` slot holds.
    // A slot that holds only Livewire's morph markers is no mark.
    $callerAfter = isset($labelAfter) && $labelAfter instanceof \Illuminate\View\ComponentSlot && $labelAfter->hasActualContent() ? $labelAfter : null;
    $requiredMark = $required && filled($label);

    // `auto` grows each textarea with its text, as on `textarea`.
    $rows = $rows === 'auto' || $rows === true ? 'auto' : max(1, is_numeric($rows) ? (int) $rows : 3);
@endphp

<div
    @if($callerId !== null) id="{{ $callerId }}" @endif
    x-data="wirekitTranslatableInput({{ AlpinePayload::from($config) }})"
    x-on:input="typed($event)"
    data-wk-translatable
    {{ $attributes->class([$classes]) }}
>
    <x-wirekit::field.set :legend="$label" :hint="$hint" :help="$help" :error="$groupError" :scope="$scope">
        {{-- The set hands its group to the controls in it by `@aware`, as it does to its checkboxes.
             Read here, inside its slot, where the set is the nearest component; the rows take it
             from this scope (partials/translatable-row). --}}
        @aware(['wkFieldSet' => null])
        {{-- The primary field's own label is visually hidden, so its required mark would be too: the
             mark stands after the legend, outside the group's name, as a field's does after its label. --}}
        @if($requiredMark || $callerAfter !== null)
            <x-slot:labelAfter>@if($requiredMark)<span class="text-[color:var(--color-wk-danger-text)]" aria-hidden="true">*</span>@endif{{ $callerAfter ?? '' }}</x-slot:labelAfter>
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
                'placeholderText' => filled($placeholder) ? (string) $placeholder : null,
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
                        'bag' => new ComponentAttributeBag($fields[$code]['bag'] + ['x-bind:placeholder' => "placeholderFor('{$code}')"]),
                    ])
                @endforeach
            </div>
        @endif
    </x-wirekit::field.set>
</div>
