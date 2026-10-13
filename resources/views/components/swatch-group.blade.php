{{-- optimistic-ui: n/a — passthrough
     Each option is a native radio or checkbox, and the round trip is the caller's `wire:model` or
     `x-model` on them: the browser shows the choice the moment it is made, and what the choice
     changes elsewhere on the page is that page's to show. --}}
@props([
    'label' => null,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'error' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    // The options, each an array: `value`, `label` (the name, which a color alone cannot say),
    // and `color` or `image` as on a swatch. `available` false draws a line across the swatch
    // and reads "unavailable" with the name, and the option stays a choice; `disabled` true
    // takes it out of the choice.
    'options' => [],
    // The chosen value, or with `multiple` a list of them, for a form without `wire:model`.
    'value' => null,
    // Several values at once, as checkboxes, as a filter takes them. Without it one value, as
    // radios.
    'multiple' => false,
    // `hidden` keeps each name for assistive technology, `below` shows it under its swatch.
    'labels' => config('wirekit.components.swatch-group.labels') ?? 'hidden',
    // The swatches' size and shape, as on a swatch.
    'size' => config('wirekit.components.swatch-group.size', 'md'),
    'shape' => config('wirekit.components.swatch-group.shape', 'circle'),
    // Disable every option.
    'disabled' => false,
    // One value has to be chosen: the radios carry the native constraint and the group says so.
    // With `multiple` the label carries the mark, and "at least one" is the application's rule.
    'required' => false,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\StrictnessGate;
    use Pushery\WireKit\WireKit;

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('swatch-group', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $multiple = BooleanProp::from($multiple, false);
    $disabled = BooleanProp::from($disabled, false);
    $required = BooleanProp::from($required, false);

    $size = WireKit::validateProp('swatch-group', 'size', (string) $size, ['sm', 'md', 'lg']);
    $shape = WireKit::validateProp('swatch-group', 'shape', (string) $shape, ['circle', 'square']);
    $labels = WireKit::validateProp('swatch-group', 'labels', (string) $labels, ['hidden', 'below']);

    // A binding to an `int` or `float` property sends its value as a number: Livewire takes the
    // server's echo of a number for a change and would write it over a newer choice
    // (Support\NumericModel).
    $attributes = \Pushery\WireKit\Support\NumericModel::number($attributes);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element (Support\OuterAttributes).
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // A caller's `form` goes to the fields this component submits, and only there: on the group
    // it is not a valid attribute and joins nothing to the form (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $attributes = $attributes->except('form');
    // A caller's `autofocus` goes to the chosen option, or the first one, which holds the tab stop
    // (Support\FieldAttributes).
    $fieldAutofocus = \Pushery\WireKit\Support\FieldAttributes::autofocus($attributes);
    $attributes = $attributes->except('autofocus');

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'swatch-group-'); // page-unique DOM id; see Support\DomId
    // The bag holds a bound name escaped once; read as the text it stands for (Support\AttributeText).
    // Radios share it, which is what makes them one choice; checkboxes post it as a list.
    $name = (string) \Pushery\WireKit\Support\AttributeText::get($attributes, 'name', $id);
    $inputName = $multiple ? \Illuminate\Support\Str::finish($name, '[]') : $name;

    // The message a failed validation left in the shared bag, as every field reads it: a list of
    // checkboxes fails as its name or one of its values (Support\FieldError).
    $error ??= \Pushery\WireKit\Support\FieldError::first($errors ?? null, $inputName);

    // The message, then the help, then a caller's own description. One choice carries them on its
    // radiogroup, as a segmented control does: the message is about the choice, and on every radio
    // it would be read once per option. Several values carry them on every checkbox, each a control
    // of its own in a group that is not one.
    $helpId = filled($help) && filled($label) ? $id.'-help' : null;
    $describedBy = trim(implode(' ', array_filter([
        $error ? $id.'-error' : ($hint ? $id.'-hint' : null),
        $helpId,
        trim((string) $attributes->get('aria-describedby', '')),
    ])));

    // What is chosen, as text, the way the browser compares an input's value. A list may arrive as a
    // Collection, which ListProp reads as the list it holds; a single value passes through.
    $chosen = \Pushery\WireKit\Support\ListProp::renumbered($value);
    $chosen = array_map(
        static fn (mixed $item): string => (string) $item,
        array_values(array_filter(is_array($chosen) ? $chosen : [$chosen], static fn (mixed $item): bool => is_scalar($item) && (string) $item !== '')),
    );

    // The options. One without a value has nothing to send, and one without a name leaves the
    // color to say what it is, which a reader who cannot see it never learns: both are reported.
    $swatches = [];

    $records = \Pushery\WireKit\Support\ListProp::renumbered(\Pushery\WireKit\Support\ListProp::records($options));

    foreach (is_array($records) ? $records : [] as $index => $option) {
        $optionValue = is_array($option) ? ($option['value'] ?? null) : null;

        if (! is_scalar($optionValue) || (string) $optionValue === '') {
            StrictnessGate::reject('swatch-group', 'options', is_scalar($optionValue) ? (string) $optionValue : get_debug_type($option), 'an option with a value', '');

            continue;
        }

        $optionLabel = trim((string) ($option['label'] ?? ''));

        if ($optionLabel === '') {
            StrictnessGate::reject('swatch-group', 'options', (string) $optionValue, 'an option with a label, the name a color alone cannot say', '');
            $optionLabel = (string) $optionValue;
        }

        $available = BooleanProp::from($option['available'] ?? true, true);

        $swatches[] = [
            'id' => $id.'-'.$index,
            'value' => (string) $optionValue,
            'label' => $optionLabel,
            'name' => $available ? $optionLabel : __('wirekit:::label, unavailable', ['label' => $optionLabel]),
            'color' => $option['color'] ?? null,
            'image' => $option['image'] ?? null,
            'available' => $available,
            'disabled' => $disabled || BooleanProp::from($option['disabled'] ?? false, false),
            'checked' => in_array((string) $optionValue, $multiple ? $chosen : array_slice($chosen, 0, 1), true),
        ];
    }

    // The tab stop a caller's `autofocus` lands on: the chosen option, or the first.
    $focusIndex = 0;

    foreach ($swatches as $i => $swatch) {
        if ($swatch['checked']) {
            $focusIndex = $i;

            break;
        }
    }

    $groupClasses = WireKit::resolveClasses('swatch-group', 'base', implode(' ', [
        'flex flex-wrap items-start',
        'gap-[var(--gap-wk-md)]',
        $disabled ? 'opacity-[var(--opacity-wk-disabled)]' : '',
    ]), $scope);

    // The option is the target: a click anywhere on it chooses, and on a touch surface its hit area
    // reaches 44px. Relative, so the visually hidden input and name stay inside it.
    $optionClasses = 'wk-touch-target '.WireKit::resolveClasses('swatch-group', 'option', implode(' ', [
        'group/swatch relative inline-flex flex-col items-center gap-[var(--gap-wk-xs)]',
        'cursor-pointer has-[:disabled]:cursor-not-allowed',
    ]), $scope);

    // The state is drawn on the swatch, the input's next sibling: an outline in the accent color
    // for the chosen one, with a gap of the surface between, and the focus ring of every field.
    $swatchClasses = implode(' ', [
        'outline-offset-2',
        'peer-checked:outline-2 peer-checked:outline-solid peer-checked:outline-[color:var(--color-wk-accent)]',
        'group-hover/swatch:border-[var(--color-wk-border-strong-hover)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        // The same ring for a swatch the arrow keys reached: WebKit does not count that focus as
        // `:focus-visible`, and the scripts mark the page while it lasts (utils/arrow-focus.js).
        'in-data-[wk-arrow-focus]:peer-focus:ring-[length:var(--ring-wk-width)]',
        'in-data-[wk-arrow-focus]:peer-focus:ring-offset-[length:var(--ring-wk-offset)]',
        'in-data-[wk-arrow-focus]:peer-focus:ring-[var(--color-wk-ring)]',
        'in-data-[wk-arrow-focus]:peer-focus:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
    ]);

    $nameClasses = WireKit::resolveClasses('swatch-group', 'name', implode(' ', [
        'max-w-[8rem] text-center [overflow-wrap:anywhere]',
        'text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]',
        'peer-checked:text-[color:var(--color-wk-text)]',
    ]), $scope);
    // The group is named by the label above, unless the caller names it.
    $callerNamesGroup = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
@endphp

<div {{ $outerAttributes }} class="relative space-y-1.5 min-w-0" data-wk-swatch-group>
    @if($label)
        <x-wirekit::label id="{{ $id }}-label" :help="$help" :help-id="$helpId" :help-field="$name" :required="$required">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @endif

    {{-- The group is named by the label above. Radios in it are one choice through their shared
         name, and the browser moves that choice with the arrow keys; checkboxes are each their own. --}}
    <div
        {{ $attributes->except(['aria-describedby', 'name'])->whereDoesntStartWith(['wire:model', 'x-model'])->merge(['role' => $multiple ? 'group' : 'radiogroup'])->class([$groupClasses]) }}
        @if($label && ! $callerNamesGroup) aria-labelledby="{{ $id }}-label" @endif
        @if($disabled) aria-disabled="true" @endif
        @if($required && ! $multiple) aria-required="true" @endif
        @if(! $multiple && $error) aria-invalid="true" @endif
        @if(! $multiple && $describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
    >
        @foreach($swatches as $i => $swatch)
            <label data-wk-swatch-option class="{{ $optionClasses }}">
                <input
                    @if($multiple) type="checkbox" @else type="radio" @endif
                    id="{{ $swatch['id'] }}"
                    name="{{ $inputName }}"
                    value="{{ $swatch['value'] }}"
                    {{ $attributes->whereStartsWith(['wire:model', 'x-model'])->class(['peer sr-only']) }}
                    @checked($swatch['checked'])
                    @disabled($swatch['disabled'])
                    @if($required && ! $multiple) required @endif
                    @if($multiple && $error) aria-invalid="true" @endif
                    @if($multiple && $describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                    @if($formOwner) form="{{ $formOwner }}" @endif
                    @if($fieldAutofocus && $i === $focusIndex) autofocus @endif
                >
                <x-wirekit::swatch :color="$swatch['color']" :image="$swatch['image']" :unavailable="! $swatch['available']" :size="$size" :shape="$shape" :scope="$scope" class="{{ $swatchClasses }}" />
                @if($labels === 'below')
                    @if($swatch['available'])
                        <span class="{{ $nameClasses }}">{{ $swatch['label'] }}</span>
                    @else
                        {{-- Read as one name with the word the line says; shown as the name alone. --}}
                        <span class="sr-only">{{ $swatch['name'] }}</span>
                        <span aria-hidden="true" class="{{ $nameClasses }}">{{ $swatch['label'] }}</span>
                    @endif
                @else
                    <span class="sr-only">{{ $swatch['name'] }}</span>
                @endif
            </label>
        @endforeach
    </div>

    {{-- Same shape as `input`: one region, error winning over hint, announced politely so
         it does not interrupt what the reader is doing. --}}
    @if($error)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $error }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
