{{-- optimistic-ui: supported
     A native time input bound to a server value — the same shape as the other
     form controls. With `popover` the value is written by a dialog or by typed
     segments rather than a native field, and `optimistic` is left out. --}}
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
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    'error' => null,
    // `24h` or `12h`. On the native field this is declared intent only: a native
    // `<input type="time">` takes its 12-hour or 24-hour rendering from the browser
    // and the operating system locale, and there is no HTML attribute that
    // overrides it. It stays declared because it is a documented part of the
    // surface and because an undeclared `format` would land on the control as a
    // stray HTML attribute instead of being absorbed here. With `popover` it sets
    // the clock the times are written on; unset, they follow the locale.
    'format' => null,
    // A trigger that opens a dialog with a list of times, in place of the native
    // field: the application's own clock and language, every `step` from `min` to
    // `max`. A boolean, as on the color picker; the value, the field's name and
    // `wire:model` are the same in both forms.
    'popover' => false,
    // With `popover`: `list`, a dialog with the list of times, or `input`, the time
    // typed in segments in the field, with the list as suggestions.
    'time' => 'list',
    // With `popover` and the list: a button in the trigger that empties the field.
    'clearable' => false,
    // The native `step` attribute, in seconds, exactly as HTML defines it: 900 is
    // a quarter of an hour, 60 is one minute, null leaves the browser's own
    // default of one minute. A value off the step is a step mismatch the form
    // refuses to submit.
    'step' => null,
    // Declared rather than left in the attribute bag, so that a date object arrives as itself:
    // Blade casts a bound attribute that is not a prop to an escaped string before the template
    // runs, and an Eloquent `datetime` cast would reach the control as `Y-m-d H:i:s`.
    'value' => null,
    'min' => null,
    'max' => null,
    'size' => config('wirekit.components.time-picker.size', 'md'),
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // A list where the control takes one value, as `old()` gives one after a request that sent
    // `name[]`, is reported and read as no value (Support\FieldValue).
    $value = \Pushery\WireKit\Support\FieldValue::one($value, 'time-picker');

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('time-picker', $attributes->getAttributes());

    // An unknown `size` is reported (Support\StrictnessGate) and renders as `md`; a bound `null` or
    // empty string is `md` without a word.
    $size = \Pushery\WireKit\WireKit::validateProp('time-picker', 'size', \Pushery\WireKit\Support\NumericProp::text($size ?: 'md'), ['md', 'sm', 'lg']);

    // Livewire sends a date object as a full timestamp, which the native field drops, so a binding
    // to one warns in debug mode (Support\DateModel).
    \Pushery\WireKit\Support\DateModel::warn('time-picker', 'time', $attributes);

    $popover = BooleanProp::from($popover, false, 'time-picker', 'popover');
    $clearable = BooleanProp::from($clearable, false, 'time-picker', 'clearable');

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'time-picker-'); // page-unique DOM id; see Support\DomId
    // Inside a field the label points at the id this control got: a second field with the same
    // name on the page gets `-2`, and pointed at the name its label named the first control.
    if ($wkField instanceof \Pushery\WireKit\Support\FieldControl) {
        $wkField->follow($id);
    }
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

    // A date object, which is what an Eloquent `datetime` cast hands over, is written as the time
    // of day a native time field reads: `HH:MM`, with its seconds only where `step` counts them.
    // Cast to a string it would be `Y-m-d H:i:s`, which the field reads as no value at all, and a
    // time with seconds on a step of minutes is a step mismatch the form refuses to send; `min` is
    // the base the step counts from, so the same holds for it. A date and time written as text is
    // read as its time too (Support\DateModel).
    // A step that is no number, NAN or INF, is no step: the attribute is left out rather than
    // written as text, which PHP 8.5 warns about for NAN.
    $step = is_float($step) && ! is_finite($step) ? null : $step;
    // The attribute below writes the step as given, and `any` is a step HTML takes. Any other
    // text that holds no number is reported.
    if (! (is_string($step) && strtolower(trim($step)) === 'any')) {
        \Pushery\WireKit\Support\NumericProp::check($step, 'time-picker', 'step');
    }

    // With `popover` the list offers a time every `step`, in whole minutes and 30 without one, and
    // the times are written to the minute, as the list and the segments hold them. A step no
    // integer holds counts as none.
    $stepSeconds = is_numeric($step) ? \Pushery\WireKit\Support\NumericProp::cast($step) : null;
    $popoverInterval = $popover && $stepSeconds !== null && $stepSeconds >= 60 ? intdiv($stepSeconds, 60) : 30;
    $timeEntry = WireKit::validateProp('time-picker', 'time', (string) $time, ['list', 'input']);
    // `format` takes `12h` or `24h`. Any other value is reported, and the popover follows the
    // locale, as it does without one.
    if ($format !== null && $format !== '' && $format !== false && ! in_array($format, ['12h', '24h'], true)) {
        \Pushery\WireKit\Support\StrictnessGate::reject('time-picker', 'format', \Pushery\WireKit\Support\NumericProp::text($format), '"12h" or "24h"', 'auto');
    }
    $popoverTimeFormat = is_string($format) ? (['12h' => '12-hour', '24h' => '24-hour'][$format] ?? 'auto') : 'auto';

    $toTime = static fn ($time) => \Pushery\WireKit\Support\DateModel::written('time', $time, $popover ? null : $step);
    $value = $toTime($value);
    // With `popover` the list and the segments hold a time to the minute, and so does the field
    // they write: `07:45:30` is `07:45` (Support\TimeLimits).
    $value = $popover ? \Pushery\WireKit\Support\TimeLimits::time($value) : $value;
    // A bound written as text that names no time limits nothing and is reported, as an invalid
    // prop is (Support\TimeLimits::reportedTime()); one that names a time is written as the field
    // reads it.
    $min = $toTime($min);
    $max = $toTime($max);
    $min = is_string($min) && \Pushery\WireKit\Support\TimeLimits::reportedTime($min, 'time-picker', 'min') === null ? null : $min;
    $max = is_string($max) && \Pushery\WireKit\Support\TimeLimits::reportedTime($max, 'time-picker', 'max') === null ? null : $max;

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errors ?? null, $errorName, $error);
    $hasError = $errorMessage !== null;

    // Base input classes — matches standard input styling
    $inputClasses = WireKit::resolveClasses('time-picker', 'base', implode(' ', [
        'block w-full',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'tabular-nums',
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
    ]), $scope);

    // Each state names its own focus-ring color and the base list names none: two ring colors on
    // one element are decided by the stylesheet's order, which put the resting ring over the error one.
    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        // Hover belongs to the resting state only, so an error border keeps its color under
        // the pointer.
        : 'border-[var(--color-wk-border-strong)] hover:border-[var(--color-wk-border-strong-hover)] focus-visible:ring-[var(--color-wk-ring)]';

    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
    };

    // Build aria-describedby
    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));
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
@endphp

@php
    $optimisticConfig = $optimistic === null || $popover ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        // The value the control renders with, after the date-object normalization
        // above, so the layer starts from the time the reader sees.
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

@if($popover)
{{-- The trigger and its dialog, or the typed time, apart from the native field below, which
     renders as it always has without `popover`. --}}
@include('wirekit::components.partials.time-picker-popover')
@else
<div {{ $outerAttributes }} @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif class="relative space-y-1.5 min-w-0" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        {{-- The asterisk flag is READ from the bag rather than declared as a prop, deliberately:
             declaring it would pull `required` OUT of the bag, and the bag is what carries the
             attribute to the native control below. A bare `required` lands in the bag as
             `true`, so this reads it without consuming it. --}}
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name !== '' ? $name : $id" :for="$id" :required="(bool) $attributes->get('required', false)">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @endif

    <input
        type="time"
        id="{{ $id }}"
        @if($name !== '') name="{{ $name }}" @endif
        {{-- Seconds, per the HTML attribute: a value off the step is a step
             mismatch, and stepUp() and stepDown() move by it. --}}
        @if($step !== null) step="{{ $step }}" @endif
        @if($value) value="{{ $value }}" @endif
        @if($min) min="{{ $min }}" @endif
        @if($max) max="{{ $max }}" @endif
        @if($hasError) aria-invalid="true" @endif
            @if($optimisticConfig)
                x-ref="control"
                @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
        @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
        @if($fieldRequired) aria-required="true" @endif
        {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
        {{ $attributes->except('type')->except('aria-describedby')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['wk-field', $inputClasses, $stateClasses, $sizeClasses])) }}
    />

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
@endif
