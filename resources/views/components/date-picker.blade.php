{{-- optimistic-ui: supported (single-date mode)
     Pass `optimistic="method"` and the date lands the moment it is picked. A
     native <input type="date">, so the layer owns the value exactly as it does
     on time-picker — the previous date is the server's, and an undo destroys
     nothing the user typed.

     `range` mode stays OUT, and not for want of wiring: a range is TWO values,
     so an undo has to answer what it restores when only one end moved, and the
     two inputs constrain each other — rolling one back can leave the other
     holding a bound that no longer applies. That is a contract question, not a
     prop. Passing `optimistic` together with `range` is ignored rather than
     half-applied, and so is passing it with `popover`, whose value the dialog
     writes rather than a native field. --}}
@props([
    // Livewire method to call optimistically. The date appears immediately and
    // is put back if the call fails. Single-date mode only — see the note above.
    // Absent -> the component renders without an optimistic layer.
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
    'label' => null,
    'value' => null,
    // false (default) renders one native date input. true renders a linked
    // start + end pair (two native inputs) — see the range parsing below.
    'range' => false,
    'min' => null,
    'max' => null,
    'size' => config('wirekit.components.date-picker.size', 'md'),
    'disabled' => false,
    'required' => false,
    'placeholder' => null,
    'error' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    // Say so when the browser writes dates in a different order than the page, rather than
    // change the order — which no author can do. A native `<input type="date">` takes its
    // format from the BROWSER's user-interface language and from nothing else; it is not an
    // authorable property in any shipped browser, which is why this is a disclosure and not a
    // `locale` prop.
    //
    // The misreading it closes is silent, and that is what makes it worth a prop: `03.04.2026`
    // and `04/03/2026` are both valid dates. Nothing is rejected, no error appears, and the
    // record carries a different day than the person meant. It happens wherever the system
    // language and the page disagree: an operator on an English laptop entering a date on a
    // German screen is the common case.
    //
    // Off by default. On, it renders NOTHING when the two orders agree — the overwhelmingly
    // common case.
    //
    // If you want the application's own format, the answer is a different component.
    // `x-wirekit::calendar` takes the application locale and draws the dates itself. The
    // trade is the one this component exists to avoid: a non-native field gives up the
    // keyboard model, the calendar the browser opens, and the date wheel on a phone.
    //
    // And the name above carries no angle brackets, which is not a typo. Blade's
    // pre-compiler scans raw text for component tags, before any of this is a PHP comment —
    // so a tag written here is compiled into a real invocation, and every render of this
    // component then dies on `Undefined variable $component`.
    'formatHint' => config('wirekit.components.date-picker.format-hint', false),
    // A trigger that opens a dialog with the calendar component, in place of the native field:
    // the application's own calendar and date format, with the limits and options below. The
    // native field keeps the browser's keyboard, picker and date wheel. A boolean, as on the color
    // picker; the value, the field names and `wire:model` are the same in both forms.
    'popover' => false,
    // With `popover`: a button in the trigger that empties the field.
    'clearable' => false,
    // With `popover`, the calendar in the dialog; see the calendar component for each. `months`
    // is 2 for a range and 1 for a single day unless given, and `presets` lists ranges beside it.
    'presets' => null,
    'unavailable' => null,
    'minRange' => null,
    'maxRange' => null,
    'months' => null,
    'openTo' => null,
    'withToday' => false,
    'selectableHeader' => false,
    'weekNumbers' => false,
    'weekStartsOn' => config('wirekit.components.calendar.week-starts-on', 1),
    'fixedWeeks' => false,
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // A list where the control takes one value, as `old()` gives one after a request that sent
    // `name[]`, is reported and read as no value (Support\FieldValue). A range takes its two ends
    // as a list, and an end that is a list itself is left out the same way.
    $value = BooleanProp::from($range, false, 'date-picker', 'range')
        ? (is_array($value) ? \Pushery\WireKit\Support\FieldValue::items($value, 'date-picker') : $value)
        : \Pushery\WireKit\Support\FieldValue::one($value, 'date-picker');

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('date-picker', $attributes->getAttributes());

    // An unknown `size` is reported (Support\StrictnessGate) and renders as `md`; a bound `null` or
    // empty string is `md` without a word.
    $size = \Pushery\WireKit\WireKit::validateProp('date-picker', 'size', \Pushery\WireKit\Support\NumericProp::text($size ?: 'md'), ['md', 'sm', 'lg']);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $range = BooleanProp::from($range, false, 'date-picker', 'range');
    $disabled = BooleanProp::from($disabled, false, 'date-picker', 'disabled');
    $required = BooleanProp::from($required, false, 'date-picker', 'required');
    $popover = BooleanProp::from($popover, false, 'date-picker', 'popover');
    $clearable = BooleanProp::from($clearable, false, 'date-picker', 'clearable');
    $withToday = BooleanProp::from($withToday, false, 'date-picker', 'withToday');
    $selectableHeader = BooleanProp::from($selectableHeader, false, 'date-picker', 'selectableHeader');
    $weekNumbers = BooleanProp::from($weekNumbers, false, 'date-picker', 'weekNumbers');
    $fixedWeeks = BooleanProp::from($fixedWeeks, false, 'date-picker', 'fixedWeeks');

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

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Date picker wraps native <input type="date"> for maximum accessibility
    // and zero-dependency operation. The browser provides its own calendar
    // popup + keyboard navigation (arrow keys, PageUp/PageDown for months,
    // etc.), and ships localized to the user's OS locale automatically.
    $isRange = filter_var($range, FILTER_VALIDATE_BOOLEAN);
    // How many months the calendar shows, one to four as it draws them, two for a range without
    // a number.
    $months = min(4, max(1, \Pushery\WireKit\Support\NumericProp::int($months, $isRange ? 2 : 1, 'date-picker', 'months')));

    // Livewire sends a date object as a full timestamp, which the native field drops, so a binding
    // to one warns in debug mode (Support\DateModel). Each end of a range binds a path of its own.
    \Pushery\WireKit\Support\DateModel::warn('date-picker', 'date', $attributes, $isRange ? ['.start', '.end'] : ['']);

    // A date object, which is what an Eloquent `date` cast hands over, is written as the day a
    // native date field reads, `YYYY-MM-DD`: cast to a string it would be `Y-m-d H:i:s`, and
    // encoded as JSON an ISO timestamp, and the field reads either as no value at all. A date and
    // time written as text is read as its day too (Support\DateModel). The same holds for `min`,
    // `max` and both ends of a range.
    $toDay = static fn ($date) => \Pushery\WireKit\Support\DateModel::written('date', $date);
    // A range object gives its two ends: a Values\DateRange a Livewire property is typed with, or
    // any other period.
    if ($isRange && $value instanceof \DatePeriod) {
        $value = \Pushery\WireKit\Values\DateRange::from($value)->toFields();
    }
    // The two ends of a range may arrive in a Collection as well.
    if ($value instanceof \Illuminate\Support\Enumerable) {
        $value = $value->all();
    }
    // The preset a range was chosen by stands beside its ends, and is no date to be read as one.
    $namedPreset = is_array($value) && is_string($value['preset'] ?? null) ? $value['preset'] : null;
    $value = is_array($value) ? array_map($toDay, \Illuminate\Support\Arr::except($value, 'preset')) : $toDay($value);
    // The fields take the days the calendar reads (Support\CalendarLimits): a moment as its day,
    // and `today` and `now` as the day of the application's clock, where a browser passes over a
    // bound of a date field that is no `YYYY-MM-DD`. A text that names no day limits nothing and
    // is reported, as an invalid prop is. Two days the wrong way round would leave the field no
    // valid day; read as written they say "between these two", so they are put in order, as the
    // calendar puts them.
    $min = \Pushery\WireKit\Support\CalendarLimits::reportedDay($toDay($min), 'date-picker', 'min');
    $max = \Pushery\WireKit\Support\CalendarLimits::reportedDay($toDay($max), 'date-picker', 'max');

    if ($min !== null && $max !== null && $min > $max) {
        [$min, $max] = [$max, $min];
    }

    // The days the calendar in the dialog closes. An entry that names neither a day nor a time is
    // reported here, under the picker's name, and the calendar gets only the entries it can read,
    // so it does not report one a second time (Support\CalendarLimits::reportedUnavailable()).
    $unavailable = $popover ? \Pushery\WireKit\Support\CalendarLimits::reportedUnavailable($unavailable, 'date-picker') : $unavailable;

    // Range value can be an array ['start' => .., 'end' => ..] or a slash string
    // "Y-m-d/Y-m-d" (e.g. "2025-01-20/2025-02-09"). Single mode keeps `value`.
    $startValue = null;
    $endValue = null;
    if ($isRange) {
        if (is_array($value)) {
            $startValue = $value['start'] ?? ($value[0] ?? null);
            $endValue = $value['end'] ?? ($value[1] ?? null);
        } elseif (is_string($value) && str_contains($value, '/')) {
            [$startValue, $endValue] = array_pad(explode('/', $value, 2), 2, null);
        }
    }

    // The same on every render, or Livewire's morph REPLACES the input on each round trip and
    // a reader typing a date into a `wire:model.live` field loses focus after the first key.
    // Seeded from the bound property when there is no name, counted when there is neither.
    // A second picker with the same name or property on the page gets `-2`, so its label and
    // its hint do not point at the first one's field (DomId::distinct keeps the first verbatim).
    $dateId = \Pushery\WireKit\Support\DomId::valid(is_scalar($id) ? (string) $id : null) ?? \Pushery\WireKit\Support\DomId::distinct($name ? 'wk-date-' . $name : WireKit::stableId('wk-date', $attributes->whereStartsWith('wire:model')->first()));
    $errorId = $dateId . '-error';
    $hintId = $dateId . '-hint';
    $formatHintId = $dateId.'-format-hint';

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    // The bag read is guarded on the name, exactly as field.blade.php does.
    // `MessageBag::has(null)` falls through to `any()`, so an unguarded read
    // makes a date-picker with no `name` report itself invalid the moment ANY
    // unrelated field on the page fails validation — a red border and an
    // `aria-invalid` on a control nobody validated.
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf($name, $attributes);
    // A range is validated end by end, as `{name}.start` and `{name}.end` (FieldError::first()).
    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errorName ? ($errors ?? null) : null, $errorName, $error, $range ? ['start', 'end'] : []);
    $hasError = $errorMessage !== null;

    // The paragraph and the idref pointing at it move together. `$hasError` can
    // be true with nothing to say (`error=""` plus a bag hit), and a described-by
    // resolving to an empty element announces the control as invalid without
    // saying why — a WCAG 3.3.1 failure the markup looks fine in.
    $showsError = $hasError && $errorMessage;

    // Sizing shared with other form controls for visual consistency.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    $inputClasses = WireKit::resolveClasses('date-picker', 'input', implode(' ', [
        'w-full',
        'px-[var(--padding-wk-x-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'rounded-[var(--radius-wk-md)]',
        'focus:outline-hidden',
        'focus:ring-[length:var(--ring-wk-width)]',
        'focus:ring-[var(--color-wk-ring)]',
        'focus:border-[var(--color-wk-accent)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    // Build aria-describedby from hint + error ids conditionally.
    // The hint paragraph below renders only `@if($hint && ! $hasError)`, so an
    // error state must not keep naming it: an idref whose element is not in the
    // document is dropped silently by assistive technology, and the field is then
    // described by less than the markup claims — or, with only a hint set, by
    // nothing at all. Compose from what this render actually emits.
    // The dialog writes dates in the application's language, so a popover has no disagreement to
    // disclose.
    $formatHint = BooleanProp::from($formatHint, false, 'date-picker', 'formatHint') && ! $popover;

    // The format hint joins the description whenever the prop is on, and NOT only when it has
    // something to say — its text is decided in the browser, after this markup is written. An
    // element with no text contributes nothing to a computed description, so the stable idref
    // costs nothing; a describedby rewritten at runtime would be the fragile half.
    $describedBy = trim(
        ($hint && ! $hasError ? $hintId : '')
        .' '.($formatHint ? $formatHintId : '')
        .' '.($showsError ? $errorId : '')
    );
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));
    // The field's help, after its own messages: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered.
    $helpId = filled($help) && filled($label) ? $dateId.'-help' : null;
    // Markup a caller puts after the label and its help, in their row (partials/label-after).
    $hasLabelAfter = isset($labelAfter) && $labelAfter->hasActualContent();
    // A `field` around this control renders its hint below it, and the control is described by it
    // as by a hint of its own (Support\FieldControl).
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $dateId, $hint ? $hintId : null, $helpId ?? null);
    // A required `field` around this control hides its asterisk from assistive technology, so
    // the control says it, with `aria-required` and never the browser's own check
    // (Support\FieldControl::requiredBy()).
    $fieldRequired = \Pushery\WireKit\Support\FieldControl::requiredBy($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $required);
    $describedBy = trim($describedBy.' '.($helpId ?? ''));

    // Accessible-name fallback. WCAG 2.1 (4.1.2) requires every input to
    // have a programmatically-determinable name. When no visible `label`
    // prop is set AND no `aria-label` / `aria-labelledby` is passed via
    // attributes, derive a sr-only fallback from `name` (humanized) so
    // screen readers always announce something. Caller-provided
    // `aria-label` always wins.
    $hasExplicitAriaName = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $needsSrOnlyFallback = ! $label && ! $hasExplicitAriaName;

    // Inside a labeled field the field's label names the date, where a hidden label made from the
    // name would (Support\FieldControl). Its `for` points at the date, or at the first date of a
    // range, as this component's own label does; the two dates of a range carry its text in their
    // names, which no single `for` can give both.
    $fieldLabelId = $needsSrOnlyFallback && $wkField instanceof \Pushery\WireKit\Support\FieldControl
        ? $wkField->takeLabel($dateId)
        : null;

    if ($fieldLabelId !== null) {
        $needsSrOnlyFallback = false;
    }
    // A caller's `id` is there so a label of theirs can reach the field, and the fallback name would
    // stand over that label or add its own words to it, so it steps aside, as it does on
    // tags-input and multi-select.
    if (filled($id)) {
        $needsSrOnlyFallback = false;
    }
    $fallbackLabel = $name ? Str::headline((string) $name) : __('wirekit::Date');

    // A caller's `x-ref` belongs to the caller's component. The range row and the optimistic
    // layer are roots of ours and would keep it, and the optimistic input carries a ref of its
    // own, so the name moves to `x-wk-ref` on the element the bag lands on and the outermost
    // element marks the boundary (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');

    // The range arm renders the attribute bag too. Otherwise `class`, `style`, every
    // `data-*`, every `aria-*` and `wire:model` would be parsed off the tag and dropped:
    // `<x-wirekit::date-picker range wire:model="stay" />` would bind nothing, with no
    // error and a control that looks and behaves normally, because native form
    // submission still works through the `name[start]` / `name[end]` fields.
    //
    // A range has two controls and one wrapper, so the bag is split three ways rather
    // than splatted onto whichever element came first.
    $rangeStartAttributes = null;
    $rangeEndAttributes = null;
    $rangeWrapperAttributes = null;
    $rangeName = $fallbackLabel;

    if ($isRange) {
        // 1. `wire:model` is a BINDING, and a binding cannot be duplicated: both inputs
        //    writing one scalar property would resolve by whichever commit landed last.
        //    It therefore follows the shape this component already emits and documents
        //    for the native post — `name="stay[start]"` / `name="stay[end]"` becomes
        //    `wire:model="stay.start"` / `wire:model="stay.end"`, which is Livewire's own
        //    path notation for the same array. The DIRECTIVE is copied verbatim so the
        //    modifiers survive (`wire:model.live`, `wire:model.blur.debounce.500ms`);
        //    only the property path gains its end.
        $startPairs = [];
        $endPairs = [];

        foreach ($attributes->whereStartsWith('wire:model')->getAttributes() as $directive => $property) {
            if (! is_string($property) || $property === '') {
                continue;
            }

            $startPairs[$directive] = $property.'.start';
            $endPairs[$directive] = $property.'.end';
        }

        // 2. `aria-label` / `aria-labelledby` are NAMES, and a name on the flex row reaches
        //    nothing: it carries no role and is not focusable, so assistive technology
        //    ignores it there. The caller's name goes onto the two inputs instead.
        //
        //    An idref cannot be suffixed the way a string can, so a caller-supplied
        //    `aria-labelledby` lands on both ends unchanged and names them alike; a `label`
        //    prop or an `aria-label` is what tells the two apart.
        $callerAriaLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
        $rangeName = filled($label) ? \Pushery\WireKit\Support\SlotContent::textOf($label) : (filled($callerAriaLabel) ? (string) $callerAriaLabel : ($fieldLabelId !== null ? $wkField->labelText() : $fallbackLabel));

        if ($attributes->has('aria-labelledby')) {
            $startPairs['aria-labelledby'] = $attributes->get('aria-labelledby');
            $endPairs['aria-labelledby'] = $attributes->get('aria-labelledby');
        }

        // What acts only on a field goes onto the two dates, as the single date's field takes it:
        // on the row `form` joins nothing, `readonly` locks nothing, `autocomplete` offers nothing,
        // `step` sets no grid, `list` suggests no date, and the on-screen keyboard reads none of
        // it. `autofocus` goes to the start, the field a reader meets first.
        foreach (['form', 'readonly', 'autocomplete', 'step', 'list', ...\Pushery\WireKit\Support\FieldAttributes::KEYBOARD] as $fieldAttribute) {
            if ($attributes->has($fieldAttribute)) {
                $startPairs[$fieldAttribute] = $attributes->get($fieldAttribute);
                $endPairs[$fieldAttribute] = $attributes->get($fieldAttribute);
            }
        }

        if (\Pushery\WireKit\Support\FieldAttributes::autofocus($attributes)) {
            $startPairs['autofocus'] = true;
        }

        $rangeStartAttributes = new \Illuminate\View\ComponentAttributeBag($startPairs);
        $rangeEndAttributes = new \Illuminate\View\ComponentAttributeBag($endPairs);

        // 3. Everything else lands on the flex row — the element that IS the range control,
        //    which is what the single-date `<input>` is to the other arm. The outer `w-full`
        //    div also wraps the label, the hint and the error, none of which the bag has
        //    ever reached.
        $rangeWrapperAttributes = $attributes
            ->whereDoesntStartWith('wire:model')
            ->except(['aria-label', 'aria-labelledby', 'aria-describedby', 'form', 'readonly', 'autocomplete', 'step', 'list', 'autofocus', ...\Pushery\WireKit\Support\FieldAttributes::KEYBOARD]);

        // 4. An `x-model` on the row would take the value of whichever date sent the last `input`
        //    event, one end of the range and never the range, so it is removed there
        //    (Support\UnboundModel). `wire:model` binds both ends, as in 1.
        \Pushery\WireKit\Support\UnboundModel::drop('date-picker', $rangeWrapperAttributes);
    }
@endphp

@php
    // Range mode is excluded HERE rather than at the call site, so a developer
    // who passes both gets the component they had rather than a half-applied
    // layer that rolls back one end of a range and not the other.
    $optimisticConfig = ($optimistic === null || $isRange || $disabled || $popover) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        // `value` IS a declared prop here, unlike on time-picker where it passes
        // through the attribute bag — read it from the prop, not from the bag,
        // or the layer mounts empty and the first rollback restores nothing.
        'value' => (string) ($value ?? ''),
        'action' => $optimistic,
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        'errorRegion' => \Pushery\WireKit\Support\CssIdentifier::idSelector($errorId),
    ]);
    // With the optimistic layer the field stands inside a root of ours, where `.parent` would still
    // find the layer's names: the caller's `x-model` reaches it as `x-wk-model`, read above the
    // layer's element, which marks the boundary (CallerModel::onField()).
    $modelScope = $optimisticConfig && \Pushery\WireKit\Support\CallerModel::binds($attributes);
    $attributes = $modelScope ? \Pushery\WireKit\Support\CallerModel::onField($attributes) : $attributes;
@endphp

@if($popover)
{{-- The trigger, the hidden fields and the dialog with the calendar, apart from the native field
     below, which renders as it always has without `popover`. --}}
@include('wirekit::components.partials.date-picker-popover')
@else
{{-- With the format hint on, the element right before an empty hint keeps no space below it. The
     hint is always in the markup, because the field's description names it before the browser
     decides whether it has anything to say, and the stack spaces every child but the last, so the
     gap above an empty hint made the picker taller than one without it. A rule on the element
     before the hint rather than a negative margin on the hint: that element is often the input,
     which is inline-block, and an inline-block's margin does not collapse. --}}
<div {{ $outerAttributes }} @class(['relative w-full space-y-1.5', '[&>:has(+[data-wk-format-hint]:empty)]:[margin-block-end:0]' => $formatHint]) @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif>
    @if($label)
        {{-- The label every field shows, through the label component: its type and weight, the
             help beside it, the mark after it and the required marker. The wrapper's
             `space-y-1.5` puts the field's own distance below it, so a date picker stands on one
             line with an input beside it. --}}
        <x-wirekit::label for="{{ $dateId }}" :required="$required" :help="$help" :help-id="$helpId" :help-field="(string) ($name ?? $dateId)">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($needsSrOnlyFallback)
        {{-- Screen-reader-only label fallback. Visible-label-less demos still
             pass WCAG 4.1.2 because the input has a programmatically-
             determinable name. --}}
        <label for="{{ $dateId }}" class="sr-only">{{ $fallbackLabel }}</label>
    @endif

    @if($isRange)
        {{-- Range = two native date inputs. A tiny Alpine scope holds the current
             start (s) + end (e) so each input can constrain the other reactively:
             the end can't precede the start, the start can't follow the end. This
             is additive — it doesn't touch the values, so native form submission of
             the {name}[start] / {name}[end] fields is untouched, and `wire:model`
             binds the same two keys as `{property}.start` / `{property}.end` (see
             the split above).

             The scope reads the two dates from the fields
             (resources/js/components/date-range.js) rather than taking them from
             `x-data`, so the expression is the same for every range: a Livewire morph
             that changed it would start the scope again with the dates of the request. --}}
        <div
            x-data="wirekitDateRange()"
            {{-- min-w-0 on the two fields is what makes this row survive a narrow
                 container. A flex item defaults to `min-width: auto`, so it cannot shrink
                 past its intrinsic content width — and a native date input has a large one
                 (the format text plus the picker button). Two of those plus the separator
                 exceed anything under roughly 330px, and a frame that does not scroll
                 clips the excess: the end date would become unreachable on a phone. --}}
            {{ $rangeWrapperAttributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, 'flex items-center gap-[var(--padding-wk-x-sm)]')) }}
            {{-- `focus` and `blur` fire here as the reader enters and leaves the control as a whole, so a
                 listener written on the tag hears one field (utils/focus-unit.js). --}}
            x-wk-focus-unit
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
        >
            <input
                type="date"
                @if($name) name="{{ $name }}[start]" @endif
                id="{{ $dateId }}"
                value="{{ $startValue }}"
                x-on:change="s = $event.target.value"
                :min="{{ \Pushery\WireKit\Support\AlpinePayload::from($min) }}"
                :max="e || {{ \Pushery\WireKit\Support\AlpinePayload::from($max) }}"
                @if($disabled) disabled @endif
                @if($required) required aria-required="true" @elseif($fieldRequired) aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @unless($label) aria-label="{{ __('wirekit:::label start', ['label' => $rangeName]) }}" @endunless
                {{ $rangeStartAttributes->except('type') }}
                class="wk-field min-w-0 flex-1 {{ $inputClasses }}"
            />
            <span aria-hidden="true" class="shrink-0 text-[color:var(--color-wk-text-muted)]">&ndash;</span>
            <input
                type="date"
                @if($name) name="{{ $name }}[end]" @endif
                id="{{ $dateId }}-end"
                value="{{ $endValue }}"
                x-on:change="e = $event.target.value"
                :min="s || {{ \Pushery\WireKit\Support\AlpinePayload::from($min) }}"
                :max="{{ \Pushery\WireKit\Support\AlpinePayload::from($max) }}"
                @if($disabled) disabled @endif
                @if($required) required aria-required="true" @elseif($fieldRequired) aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                aria-label="{{ __('wirekit:::label end', ['label' => $rangeName]) }}"
                {{ $rangeEndAttributes->except('type') }}
                class="wk-field min-w-0 flex-1 {{ $inputClasses }}"
            />
        </div>
    @else
        <input
            type="date"
            @if($name) name="{{ $name }}" @endif
            id="{{ $dateId }}"
            @if($value) value="{{ $value }}" @endif
            @if($min) min="{{ $min }}" @endif
            @if($max) max="{{ $max }}" @endif
            @if($placeholder) placeholder="{{ \Pushery\WireKit\Support\SlotContent::textOf($placeholder) }}" @endif
            @if($disabled) disabled @endif
            @if($required) required aria-required="true" @elseif($fieldRequired) aria-required="true" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($optimisticConfig)
                x-ref="control"
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->except('type')->except('aria-describedby')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['wk-field', $inputClasses])) }}
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
        />
    @endif

    @if($hint && !$hasError)
        <p data-wk-prose-skip id="{{ $hintId }}" class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($formatHint)
        {{-- The sentence is composed in the browser because only the browser knows its own
             date order. The translated TEMPLATE travels from here, so the wording is the
             page's language; the two dates in it are formatted at runtime. --}}
        <p
            data-wk-prose-skip
            data-wk-format-hint
            id="{{ $formatHintId }}"
            x-data="wirekitDateFormatHint({
                locale: {{ \Pushery\WireKit\Support\AlpinePayload::from(\Pushery\WireKit\Support\IcuLocale::tag()) }},
                template: {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Your browser writes dates in a different order than this page: :appDate is entered as :fieldDate.')) }},
            })"
            x-text="message"
            class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]"
        ></p>
    @endif

    @if($showsError)
        {{-- Error message linked via aria-describedby for assistive tech. --}}
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. It comes after the error paragraph, which is the
             one the layer yields to when both would speak. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif
</div>
@endif
