{{-- optimistic-ui: n/a — passthrough
     The only server action this field can take is the caller's own `wire:model`, handed
     over through the attribute bag and routed onto the hidden input. There is nothing
     here whose result this component could show early, because it never decides that a
     round trip happens. --}}
@props([
    // The country the field starts on, as an ISO 3166-1 alpha-2 code. Case does not matter.
    'country' => config('wirekit.components.phone.country', 'DE'),
    // Which countries the picker offers, as alpha-2 codes. Null offers every country the
    // dialing-code table carries. This narrows the PICKER and not what can be typed — a
    // reader pasting an international number is followed, not corrected.
    'countries' => null,
    // Codes pulled to the top of the list, in the order given. For a form that expects a
    // handful of countries without forbidding the rest.
    'countryOrder' => [],
    'label' => null,
    'hideLabel' => false,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    // Takes the MESSAGE, not a boolean — the house shape. Laravel's validation bag is read
    // as well, under the field's name.
    'error' => null,
    'announceError' => null,
    'size' => config('wirekit.components.phone.size', 'md'),
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'placeholder' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\DialingCodes;
    use Pushery\WireKit\Support\DomId;
    use Pushery\WireKit\WireKit;

    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the control —
    // the opposite of what the call site says, with no error either way.
    $hideLabel = BooleanProp::from($hideLabel, false);
    $required = BooleanProp::from($required, false);
    $disabled = BooleanProp::from($disabled, false);
    $readonly = BooleanProp::from($readonly, false);

    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    WireKit::warnUnknownProps('phone', $attributes->getAttributes());

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['x-on:input']);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    $id = DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'phone-');
    // The bag holds a bound name escaped once; read as the text it stands for, so the field is
    // sent under the name the caller bound (Support\AttributeText).
    $name = \Pushery\WireKit\Support\AttributeText::get($attributes, 'name', $id);
    $attributes = $attributes->except(['id', 'name']);

    $hasError = $error || \Pushery\WireKit\Support\FieldError::has($errors ?? null, $name);
    $errorMessage = $error ?? \Pushery\WireKit\Support\FieldError::first($errors ?? null, $name);

    // The offered set. An unknown code in `countries` is dropped rather than rendered as an
    // empty row: a picker line with no dialing code behind it cannot do anything. A Collection
    // is read as the codes it holds; cast with `(array)`, an object becomes its properties.
    $countries = \Pushery\WireKit\Support\ListProp::from($countries);
    $countryOrder = \Pushery\WireKit\Support\ListProp::from($countryOrder);
    $offered = $countries === null
        ? DialingCodes::countries()
        : array_values(array_filter(
            array_map(fn ($c): string => strtoupper(trim((string) $c)), (array) $countries),
            fn (string $c): bool => DialingCodes::has($c)
        ));

    // Names and sort order come from the reader's locale through intl, the same source and the
    // same reasoning as the country-picker recipe: a byte-wise sort puts every accented name
    // after Z, so Österreich would follow Zypern in German.
    $locale = app()->getLocale();
    $named = [];

    foreach ($offered as $code) {
        $named[$code] = class_exists(\Locale::class)
            ? (\Locale::getDisplayRegion('-'.$code, $locale) ?: $code)
            : $code;
    }

    if (class_exists(\Collator::class)) {
        $collator = new \Collator($locale);
        uasort($named, fn (string $a, string $b): int => (int) $collator->compare($a, $b));
    } else {
        asort($named);
    }

    // Hoisted codes keep the caller's order and leave the sorted remainder behind them.
    $hoisted = array_values(array_filter(
        array_map(fn ($c): string => strtoupper(trim((string) $c)), (array) $countryOrder),
        fn (string $c): bool => isset($named[$c])
    ));

    $ordered = [];

    foreach ($hoisted as $code) {
        $ordered[$code] = $named[$code];
    }

    foreach ($named as $code => $display) {
        if (! isset($ordered[$code])) {
            $ordered[$code] = $display;
        }
    }

    $start = DialingCodes::has($country) && isset($ordered[strtoupper(trim($country))])
        ? strtoupper(trim($country))
        : (array_key_first($ordered) ?? '');

    // The factory's view of the world: dialing code and trunk prefix per offered country. Handed
    // over once rather than read back out of the DOM — a factory that parses its own markup
    // cannot be constructed in a test, and this one is.
    //
    // The display name is deliberately not in here. With it every country's name would appear
    // twice, once in its own <option> and again in this JSON, for a label the factory only builds
    // for a button, and this layer has no button — a native <select> announces its selected
    // option by itself. Layer B, which does have a button, adds the name for the countries it
    // offers.
    //
    // The trunk prefix is omitted where a region has none, which is 101 of 245: `null` and
    // "absent" mean the same thing to the factory, and absent costs no bytes.
    $regions = [];

    foreach ($ordered as $code => $display) {
        $entry = ['dial' => DialingCodes::for($code)];
        $trunk = DialingCodes::trunkPrefix($code);

        if ($trunk !== null) {
            $entry['trunk'] = $trunk;
        }

        $regions[$code] = $entry;
    }

    // The rows the picker draws. The flag is a MEDIUM rather than a character: an emoji flag
    // renders as two letters on Windows, which is the one platform where a country picker most
    // needs to be unambiguous. `OptionMedia` resolves `flag` against the artwork this package
    // ships, and the row's LABEL still carries the name and the dialing code, so the flag is
    // decorative and a reader who cannot see it loses nothing.
    $countryOptions = [];

    foreach ($ordered as $code => $display) {
        $countryOptions[$code] = [
            'label' => $display.' (+'.DialingCodes::for($code).')',
            'flag' => $code,
        ];
    }

    $value = (string) ($attributes->get('value') ?? '');
    $attributes = $attributes->except(['value']);

    // AlpinePayload rather than json_encode: a plain encode escapes non-ASCII as \uXXXX, and
    // Alpine's CSP tokenizer knows only a handful of escapes — it drops the backslash and keeps
    // the letters, so a country name would arrive mangled with nothing thrown and nothing logged.
    // This payload carries no names today; it will when layer B needs them for its button, and a
    // guard that waited for that would be waiting for the bug.
    $config = AlpinePayload::from([
        'country' => $start,
        'regions' => $regions,
        'value' => $value,
    ]);

    // The field SHAPE, spelled out because `wk-field` is a marker rather than a style: it carries
    // an iOS zoom guard and a date-separator color and nothing that draws a box. `input` builds
    // its box from this same set of utilities in its own class list.
    //
    // The field shape, spelled out for both controls. Without it they render with
    // `border-width: 0`, no padding, no radius and a transparent background — a naked HTML input,
    // 24px tall — and the `:user-invalid` rule paints nothing, because a border color on an
    // element with no border width paints nothing.
    $fieldShape = implode(' ', [
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        // --color-wk-border is DECORATIVE: 1.29:1 against a light input fill, below the WCAG
        // 1.4.11 floor for a control boundary. A field drawn with it has a border a reader can
        // barely see. Control boundaries take the -strong variants.
        'border-[var(--color-wk-border-strong)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:border-[var(--color-wk-border-strong-hover)]',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:cursor-not-allowed',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        match ($size) {
            'sm' => 'px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-sm)]',
            'lg' => 'px-[var(--padding-wk-x-lg)] py-[var(--padding-wk-y-lg)] text-[length:var(--text-wk-lg)]',
            default => 'px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] text-[length:var(--text-wk-md)]',
        },
    ]);

    $groupClasses = WireKit::resolveClasses('phone', 'group', 'flex min-w-0 items-stretch gap-2', $scope);
    // Layout only, unlike the number box: the combobox below draws its own field box from the
    // same tokens every other Form control uses. Handing it $fieldShape as well would paint a
    // second border around the first.
    $selectClasses = WireKit::resolveClasses('phone', 'country', 'shrink-0 max-w-[10rem]', $scope);
    $numberClasses = WireKit::resolveClasses('phone', 'number', $fieldShape.' min-w-0 grow', $scope);

    // The group takes the component's own id; the number box takes it and the caller's after it.
    $ownDescribedBy = $hasError ? $id.'-error' : ($hint ? $id.'-hint' : '');
    $describedBy = trim($ownDescribedBy.' '.((string) $attributes->get('aria-describedby', '')));
    // The field's help, after its own message: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered.
    $helpId = filled($help) && filled($label) ? $id.'-help' : null;
    $describedBy = trim($describedBy.' '.($helpId ?? ''));

    // A caller's `x-ref` belongs to the caller's component. The field sits in a root of ours,
    // which would take it, and it already carries our own `x-ref`, which a parser keeps over a
    // second one. The name moves to `x-wk-ref`, which registers the field on the root above
    // `data-wk-ref-scope` (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');
    // A caller's `form` stays on the visible field, where Enter then submits the form it joined,
    // and goes to the hidden field this component submits as well (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);

    // A caller's `x-model` would land on the number box beside the box's own `x-model="national"`,
    // and the parser keeps the first, so the caller's binding would be gone without a word; on the
    // box it could only ever bind the digits typed, not the number this component sends. It is
    // removed with the warning `UnboundModel` gives, and the number binds with `wire:model`.
    \Pushery\WireKit\Support\UnboundModel::drop('phone', $attributes);

    // The country control's own name. It carried the field's label, so a screen reader heard
    // "Phone number" twice and only the role and the value told the two controls apart, and
    // without a label it had no name at all.
    $countryName = filled($label)
        ? __('wirekit:::label, country', ['label' => $label])
        : __('wirekit::Country');
@endphp

<div {{ $outerAttributes }} class="space-y-1.5 min-w-0" @if($callerRef !== '') data-wk-ref-scope @endif x-data="wirekitPhone({{ $config }})">
    @if($label)
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name" :for="$id" :required="$required" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    {{-- TWO controls, ONE value, and that is why this is a group rather than a field with an
         addon. The country control and the number box are separate tab stops; the group carries
         the field's name so a screen reader announces it once and then each part. It is
         deliberately NOT a combobox relationship: the number box is free text, and a listbox
         link would promise a keyboard model it does not have. --}}
    {{-- The group carries the hint or the error too, as the range slider's does: the country
         control is part of the value and has no description of its own, so a reader who tabs
         back to it hears the message on the way into the group. --}}
    <div role="group" @if($label) aria-label="{{ $label }}" @endif @if($ownDescribedBy !== '') aria-describedby="{{ $ownDescribedBy }}" @endif class="{{ $groupClasses }}">
        {{-- The country control is a combobox, which is what buys the flag: an <option> carries
             no image, so the native select this replaced could never show one. What it costs is
             named rather than glossed over — the phone's own country wheel is gone, and with it
             the platform keyboard model. The search field is the trade: 245 countries is a list
             nobody should scroll, and typing three letters beats spinning a wheel past Uruguay.

             The binding is `x-model`, answered by the combobox's `x-modelable="selected"`. That
             seam did not work for a hand-written binding until the commit before this one: it
             landed on the combobox's own search input beside that input's `x-model="query"`, and
             the browser kept the first of the two.

             The accessible name of each row carries the country AND its dialing code. A bare
             `+49` beside the box is invisible to a reader who cannot see it, and the number then
             reads as a fragment with no country attached. --}}
        <x-wirekit::combobox
            :id="$id.'-country'"
            :options="$countryOptions"
            :value="$start"
            :size="$size"
            :aria-label="$countryName"
            :disabled="$disabled || $readonly"
            panel-width="auto"
            {{-- No clear button. A phone number cannot be assembled without a country -- the
                 dialing code IS the prefix -- so clearing it would leave the reader looking at a
                 number while the form submits an empty string. Layer A's native select had no
                 such affordance, and gaining one on the way to flags would have been a
                 regression nothing here would have caught. --}}
            :clearable="false"
            {{-- The country is part of this control, not the control a surrounding field
                 labels: that is the number below, which carries the field's `name` as its id.
                 A combobox inside a field takes the field's label (Support\FieldControl), so
                 the field is cut off here, and its label keeps pointing at the number. --}}
            :wk-field="null"
            x-model="country"
            :class="$selectClasses"
        />

        <input
            type="tel"
            id="{{ $id }}"
            x-ref="number"
            x-model="national"
            x-on:input="onInput($refs.bound)"
            @unless($attributes->has('autocomplete')) autocomplete="tel" @endunless
            @unless($attributes->has('inputmode')) inputmode="tel" @endunless
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            @if($required) required aria-required="true" @endif
            @disabled($disabled)
            @readonly($readonly)
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
            {{ $attributes->except('type')->except('aria-describedby')->whereDoesntStartWith('wire:model')->class(['wk-field', 'wk-touch-target', $numberClasses]) }}
        />

        {{-- What the server receives: E.164, assembled from the country and the digits. It is a
             separate element because the box shows a familiar local number and the bound value is
             a different string — writing one into the other is the defect this whole component is
             built around.

             A caller's `wire:model` belongs HERE and is stripped from the number box above. It
             compiles to `x-model`, and on the box it would bind what the reader typed — the local
             string — while the box already carries an `x-model` of its own. Two bindings fighting
             over one element, and the property would receive `0151 2345 6789` where the whole
             component exists to make it `+4915123456789`.

             The STATIC value is not decoration. Without it, a page whose script did not run
             submits an empty field where the reader can see a number on screen, and nothing
             reports it. It comes from the same `$value` the factory is initialized with, so the
             two cannot disagree: before Alpine binds, the field submits what it was loaded with;
             after, it submits what the reader built. --}}
        {{-- A disabled field is left out of the form data, as a native one is. --}}
        <input type="hidden" x-ref="bound" name="{{ $name }}" value="{{ $value }}" x-bind:value="e164" @if($disabled) disabled @endif {{ $attributes->except('type')->whereStartsWith('wire:model') }} @if($formOwner) form="{{ $formOwner }}" @endif />
    </div>

    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
