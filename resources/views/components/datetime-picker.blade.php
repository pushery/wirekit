{{-- optimistic-ui: candidate — a moment takes a day and a time, and the optimistic layer binds one
     value: opting in waits for a rollback that returns both halves of the dialog together. --}}
@props([
    'name' => null,
    'id' => null,
    'label' => null,
    // The moment, `YYYY-MM-DDTHH:MM`, or a date object, read as the wall-clock time it names in
    // the application's time zone. With `range`, two moments: `start/end`, an array with `start`
    // and `end`, or a range object (Values\DateTimeRange).
    'value' => null,
    // Two moments, a start and an end, each with its own time, taken together with Select.
    'range' => false,
    // What the trigger shows without a value.
    'placeholder' => null,
    'hint' => null,
    // An explanation shown in a tooltip from a question mark beside the label, and part of the
    // field's description (partials/field-help).
    'help' => null,
    'error' => null,
    'announceError' => null,
    // The height of the trigger: `sm`, `md` or `lg`.
    'size' => 'md',
    // `button`, a trigger that shows the moment, or `input`, a field that takes the moment typed in
    // segments in the locale's order, with a button that opens the dialog. Without `range`.
    'type' => 'button',
    'disabled' => false,
    // Shows the moment and opens nothing.
    'readonly' => false,
    'required' => false,
    // A button in the trigger that empties the field.
    'clearable' => false,
    // A day and a time wait for Select; Cancel, Escape or a press outside leave the value as it was.
    'withConfirmation' => false,
    // The time a day picked without one takes at once, `HH:MM`; with `range`, one for both ends or
    // `HH:MM/HH:MM` for the start and the end.
    'defaultTime' => null,
    // How the time is picked: `list`, from a list of times beside the calendar, or `input`, typed
    // in segments below it with the list as suggestions. A typed time waits for Select.
    'time' => 'list',
    // The minutes between two times in the list.
    'interval' => 30,
    // `auto` follows the locale, or `12-hour` and `24-hour`.
    'timeFormat' => 'auto',
    // The first and the last time of every day, `HH:MM`. A `maxTime` before the `minTime` runs
    // over midnight and opens the night.
    'minTime' => null,
    'maxTime' => null,
    // The first and the last moment a reader can pick: a moment, a day, a date object, `today` or
    // `now`. The calendar reads their days, the time list their times on those days.
    'min' => null,
    'max' => null,
    // Days, times and spans a reader cannot pick: `2026-10-24`, `12:00`, `12:00-13:00`,
    // `2026-10-23T10:00`, `2026-10-23T15:00-16:00` or date objects, as a list or separated by commas.
    'unavailable' => null,
    // Where the dialog opens without a value: the calendar on its month, the time list at its time.
    'openTo' => null,
    // With `range`: the fewest and the most days a range may cover, both ends counted, and the
    // ranges such as "Last 7 days" beside the calendar (see the calendar component).
    'minRange' => null,
    'maxRange' => null,
    'presets' => null,
    // The calendar inside the dialog: see the calendar component for each. `months` is 1, or 2
    // with `range`.
    'months' => null,
    'withToday' => false,
    'selectableHeader' => false,
    'weekNumbers' => false,
    'weekStartsOn' => config('wirekit.components.calendar.week-starts-on', 1),
    'fixedWeeks' => false,
    // BCP-47 locale for the moment on the trigger, the calendar and the times, with the word
    // between the day and the time. Null → the application locale. Its other texts stay in the
    // application's language.
    'locale' => null,
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkField' => null, 'wkFieldSet' => null])

@php
    use Carbon\CarbonImmutable;
    use Illuminate\Support\Str;
    use Pushery\WireKit\Support\AlpinePayload;
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\Support\CalendarLimits;
    use Pushery\WireKit\Support\LocalizedDate;
    use Pushery\WireKit\Support\Plural;
    use Pushery\WireKit\Support\TimeLimits;
    use Pushery\WireKit\WireKit;

    // A range takes its two ends as a list or an array; a single moment reports a list where it
    // takes one value, as `old()` gives one after a request that sent `name[]`, and reads it as no
    // value (Support\FieldValue).
    $range = BooleanProp::from($range, false, 'datetime-picker', 'range');
    $value = $range ? $value : \Pushery\WireKit\Support\FieldValue::one($value, 'datetime-picker');

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('datetime-picker', $attributes->getAttributes());

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element (Support\OuterAttributes).
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy, so each flag is read
    // against its own default.
    $disabled = BooleanProp::from($disabled, false, 'datetime-picker', 'disabled');
    $readonly = BooleanProp::from($readonly, false, 'datetime-picker', 'readonly');
    $required = BooleanProp::from($required, false, 'datetime-picker', 'required');
    $clearable = BooleanProp::from($clearable, false, 'datetime-picker', 'clearable');
    $withConfirmation = BooleanProp::from($withConfirmation, false, 'datetime-picker', 'withConfirmation');
    $withToday = BooleanProp::from($withToday, false, 'datetime-picker', 'withToday');
    $selectableHeader = BooleanProp::from($selectableHeader, false, 'datetime-picker', 'selectableHeader');
    $weekNumbers = BooleanProp::from($weekNumbers, false, 'datetime-picker', 'weekNumbers');
    $fixedWeeks = BooleanProp::from($fixedWeeks, false, 'datetime-picker', 'fixedWeeks');
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    // `@aware` does not take its keys off the bag, so both spellings are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkField', 'wk-field', 'wkFieldSet', 'wk-field-set']);
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    $size = WireKit::validateProp('datetime-picker', 'size', (string) $size, ['md', 'sm', 'lg']);
    $timeFormat = WireKit::validateProp('datetime-picker', 'timeFormat', (string) $timeFormat, ['auto', '12-hour', '24-hour']);
    $time = WireKit::validateProp('datetime-picker', 'time', (string) $time, ['list', 'input']);
    $type = WireKit::validateProp('datetime-picker', 'type', (string) $type, ['button', 'input']);
    // The moment typed in the trigger's field: one moment, on the picker's own trigger; with
    // `range`, each end typed in a field of its own.
    $typesMoment = $type === 'input' && ! $range && ! \Pushery\WireKit\Support\SlotContent::given($trigger ?? null);
    $typesRange = $type === 'input' && $range && ! \Pushery\WireKit\Support\SlotContent::given($trigger ?? null);

    // A typed time can stand half finished or outside the limits while it is being typed, so it
    // is taken only with Select.
    $typesTime = $time === 'input';
    // A range always waits for Select: its two days and two times are picked one after another.
    $withConfirmation = $withConfirmation || $typesTime || $range;
    // How many months the calendar shows, one to four as it draws them, two for a range without
    // a number. A value that holds none takes that default, NAN and INF among them, which a cast
    // to int warns about.
    $months = min(4, max(1, \Pushery\WireKit\Support\NumericProp::int($months, $range ? 2 : 1, 'datetime-picker', 'months')));
    // The months the dialog's calendar shows, as an expression: one in a modal on a phone.
    $calendarMonths = AlpinePayload::from($months);

    // The moment the field holds, `Y-m-d\TH:i`. A day alone is midnight on it.
    $moment = $range ? null : TimeLimits::moment($value, false);
    $momentDay = $moment !== null ? substr($moment, 0, 10) : null;

    // With `range`, the two moments, read through Values\DateTimeRange from text, an array with
    // `start` and `end`, the two as a list, or a range object; an end without a start is none. The
    // presets and the preset the range was chosen by, as on the calendar.
    $rangeEnds = ['start' => null, 'end' => null];
    $presetKeys = [];
    $rangePreset = '';

    if ($range) {
        $rangeFields = \Pushery\WireKit\Values\DateTimeRange::from(is_array($value) || is_string($value) || $value instanceof DateTimeInterface || $value instanceof DatePeriod || $value instanceof \Illuminate\Support\Enumerable ? $value : null)->toFields();
        $rangeEnds = ['start' => $rangeFields['start'], 'end' => $rangeFields['start'] !== null ? $rangeFields['end'] : null];

        $presetKeys = \Pushery\WireKit\Support\DatePresets::listed($presets);

        // `allTime` runs from `min`; the calendar leaves it out without one, and says so.
        if (in_array('allTime', $presetKeys, true) && blank($min)) {
            $presetKeys = array_values(array_diff($presetKeys, ['allTime']));
        }

        $rangePreset = $value instanceof \Pushery\WireKit\Values\DateRange ? (string) $value->preset() : (is_array($value) && is_string($value['preset'] ?? null) ? $value['preset'] : '');
        $rangePreset = in_array($rangePreset, $presetKeys, true) ? $rangePreset : '';
    }

    $rangeDays = $rangeEnds['start'] !== null ? substr($rangeEnds['start'], 0, 10).($rangeEnds['end'] !== null ? '/'.substr($rangeEnds['end'], 0, 10) : '') : null;
    $serverValue = $range
        ? ($rangeEnds['start'] !== null ? $rangeEnds['start'].($rangeEnds['end'] !== null ? '/'.$rangeEnds['end'] : '') : '')
        : ($moment ?? '');

    // The same on every render, so a Livewire morph keeps the trigger it has. A second picker with
    // the same name or property gets `-2`.
    $pickerId = \Pushery\WireKit\Support\DomId::valid(is_scalar($id) ? (string) $id : null) ?? \Pushery\WireKit\Support\DomId::distinct($name ? 'wk-datetime-'.$name : WireKit::stableId('wk-datetime', $attributes->whereStartsWith('wire:model')->first()));
    $panelId = $pickerId.'-dialog';
    $valueId = $pickerId.'-value';
    $labelId = $pickerId.'-label';
    $hintId = $pickerId.'-hint';
    $errorId = $pickerId.'-error';
    $requiredMessageId = $required ? $pickerId.'-required' : null;

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    // The bag read is guarded on the name: `MessageBag::has(null)` falls through to `any()`.
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf($name, $attributes);
    // A range is validated end by end, as `{name}.start` and `{name}.end` (FieldError::first()).
    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $errorName ? ($errors ?? null) : null, $errorName, $error, $range ? ['start', 'end'] : []);
    $hasError = $errorMessage !== null;
    $showsError = $hasError && $errorMessage;

    // The description, from what this render emits: the hint, the error, a caller's ids and the help.
    $helpId = filled($help) && filled($label) ? $pickerId.'-help' : null;
    $hasLabelAfter = isset($labelAfter) && $labelAfter->hasActualContent();
    $describedBy = trim(($hint && ! $hasError ? $hintId : '').' '.($showsError ? $errorId : '').' '.($requiredMessageId ?? ''));
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $pickerId, $hint ? $hintId : null, $helpId);
    $describedBy = trim($describedBy.' '.($helpId ?? ''));

    // The trigger is named by the field's label and the value it shows, so a reader hears both.
    // Without a label, a caller's name stands first, else the field's around it, else one made
    // from the name.
    $callerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $callerLabelledBy = is_string($callerLabelledBy) && filled($callerLabelledBy) ? $callerLabelledBy : null;
    $callerLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    $callerLabel = is_string($callerLabel) && filled($callerLabel) ? $callerLabel : null;
    $fieldLabelId = ! filled($label) && $callerLabelledBy === null && $callerLabel === null && $wkField instanceof \Pushery\WireKit\Support\FieldControl
        ? $wkField->takeLabel($pickerId)
        : null;
    $fallbackLabel = $name ? Str::headline((string) $name) : __('wirekit::Date and time');
    $nameIds = filled($label) ? $labelId : ($callerLabelledBy ?? ($fieldLabelId ?? $labelId));
    $hiddenName = ! filled($label) && $callerLabelledBy === null && $fieldLabelId === null ? ($callerLabel ?? $fallbackLabel) : null;
    $clearName = filled($label) ? \Pushery\WireKit\Support\SlotContent::textOf($label) : ($callerLabel ?? ($fieldLabelId !== null ? $wkField->labelText() : $fallbackLabel));
    // Where the name is the one made from `name`, a label of the page's own pointed at the picker
    // takes its place in the browser, and the clear button's wording says where the name goes
    // (resources/js/utils/page-label.js).
    $fallbackClear = $hiddenName !== null && $callerLabel === null ? __('wirekit::Clear :label') : null;
    $triggerNameIds = $nameIds.' '.$valueId.($required ? ' '.$pickerId.'-required-word' : '');
    $attributes = $attributes->except(['aria-label', 'aria-labelledby', 'aria-describedby']);

    // A caller's `form` goes to the field this component submits (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $attributes = $attributes->except('form');

    // The ends of a range are fields of their own, bound to `property.start` and `property.end`,
    // and its preset to `property.preset`, as on the date picker.
    // Every field that takes the caller's model stands inside this root, where `.parent` would still
    // find our names: an `x-model` reaches each as `x-wk-model`, read above the root, which marks
    // the boundary (CallerModel::onField()).
    $models = \Pushery\WireKit\Support\CallerModel::onField($attributes->whereStartsWith(['wire:model', 'x-model']))->getAttributes();
    $modelScope = \Pushery\WireKit\Support\CallerModel::binds($attributes);
    $endModels = static fn (string $end): \Illuminate\View\ComponentAttributeBag => new \Illuminate\View\ComponentAttributeBag(array_map(
        static fn ($property) => is_string($property) && $property !== '' ? $property.'.'.$end : $property,
        $models,
    ));

    // A caller's `x-ref` belongs to the caller's component; it moves to the trigger as `x-wk-ref`
    // and the root marks the boundary (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');

    // What the trigger shows before the script starts, in the locale its dates are written in:
    // `locale`, or the application's. The word between the day and the time belongs to the
    // moment, so it follows that locale too ("27 oct. 2026 à 14:00"). Underscores to hyphens
    // because Laravel spells a regional locale `pt_BR` and Intl reads BCP-47.
    $dateLocale = \Pushery\WireKit\Support\IcuLocale::tag(filled($locale) ? trim((string) $locale) : null);
    $atWord = trans('wirekit::at', [], $dateLocale);

    // A moment written in another language than the page's carries it in `lang` (WCAG 3.1.2):
    // the moment on the trigger and each time of the list, whose name is then its text alone, a
    // closed one saying it with `aria-disabled`, since a name is read in one language. Two
    // spellings of one language (`en`, `en-GB`) are one, and nothing changes for them.
    $languageOf = static fn (string $tag): string => strtolower(explode('-', $tag, 2)[0]);
    $datesLang = $languageOf($dateLocale) !== $languageOf(\Pushery\WireKit\Support\IcuLocale::tag()) ? $dateLocale : null;
    // The time list reads it from this scope (partials/time-list), also through the typed time's.
    $timeListLang = $datesLang;
    $timeSkeleton = ['12-hour' => ['hmm', 'g:i A'], '24-hour' => ['Hmm', 'H:i']][$timeFormat] ?? ['jmm', 'g:i A'];
    $momentAt = $moment !== null ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $moment) : null;
    $shownDay = $momentAt ? LocalizedDate::bySkeleton($momentAt, 'yMMMd', 'M j, Y', $dateLocale) : '';
    $shownTime = $momentAt ? LocalizedDate::bySkeleton($momentAt, $timeSkeleton[0], $timeSkeleton[1], $dateLocale) : '';
    $placeholder = filled($placeholder) ? \Pushery\WireKit\Support\SlotContent::textOf($placeholder) : ($range ? __('wirekit::Select a date and time range') : __('wirekit::Select a date and time'));

    // A range as its trigger writes it, from the start to the end, each "Oct 27, 2026 at 9:00 AM",
    // and the end's time alone on the start's day. The script joins the same parts, so the text
    // does not change when it starts.
    $rangeShown = '';

    if ($rangeEnds['start'] !== null) {
        $rangeAt = static fn (CarbonImmutable $moment, bool $withDay): string => ($withDay ? LocalizedDate::bySkeleton($moment, 'yMMMd', 'M j, Y', $dateLocale).' '.$atWord.' ' : '').LocalizedDate::bySkeleton($moment, $timeSkeleton[0], $timeSkeleton[1], $dateLocale);
        $rangeStartAt = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $rangeEnds['start']);
        $rangeEndAt = $rangeEnds['end'] !== null ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $rangeEnds['end']) : null;
        $rangeShown = $rangeAt($rangeStartAt, true).($rangeEndAt ? ' – '.$rangeAt($rangeEndAt, $rangeEndAt->toDateString() !== $rangeStartAt->toDateString()) : '');
    }

    $shownAny = $range ? $rangeShown : $shownDay;

    // The time list's options resolve their look under this component's name
    // (partials/time-list), so a personalization of the picker reaches them.
    $timeListComponent = 'datetime-picker';

    // The time list's limits, read on the server (Support\TimeLimits), and where it stands
    // without a value: the time of `open-to`, when it names one. A Collection of days, times and
    // spans reads as the list it holds (Support\ListProp).
    $unavailable = \Pushery\WireKit\Support\ListProp::renumbered($unavailable);
    // An entry that names neither a day nor a time is reported here, under the picker's name, and
    // the time list and the calendar get only the entries they can read, so neither reports one a
    // second time (Support\CalendarLimits::reportedUnavailable()).
    $unavailable = CalendarLimits::reportedUnavailable($unavailable, 'datetime-picker');
    // A bound written as text that names no moment or no time limits nothing and is reported, as
    // an invalid prop is (Support\TimeLimits::reportedMoment() and reportedTime()).
    TimeLimits::reportedMoment($min, false, 'datetime-picker', 'min');
    TimeLimits::reportedMoment($max, true, 'datetime-picker', 'max');
    TimeLimits::reportedTime($minTime, 'datetime-picker', 'minTime');
    TimeLimits::reportedTime($maxTime, 'datetime-picker', 'maxTime');
    $timeLimits = TimeLimits::from($interval, $minTime, $maxTime, $unavailable, $min, $max);
    // The calendar and the typed days take the days of the moments the list reads, so a bound
    // is read, and reported, once.
    $minDay = $timeLimits['min'] === null ? null : substr($timeLimits['min'], 0, 10);
    $maxDay = $timeLimits['max'] === null ? null : substr($timeLimits['max'], 0, 10);
    $openToDay = CalendarLimits::day($openTo);

    // The calendar closes whole days only. A date object here is a moment, a booking at ten
    // say, which the time list closes: on the calendar it would close the whole day.
    $unavailableEntries = is_string($unavailable) ? explode(',', $unavailable) : (is_array($unavailable) ? $unavailable : []);
    $unavailableDays = array_values(array_filter($unavailableEntries, static fn ($entry): bool => is_string($entry) && preg_match('/^\s*\d{4}-\d{2}-\d{2}\s*$/', $entry) === 1));
    $openToTime = is_string($openTo) && preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($openTo)) === 1 ? null : TimeLimits::time($openTo);

    // A text that names neither a day nor a moment is reported where the developer is, as `min`
    // and `max` are: written wrong, the dialog would open on today without a word.
    if ($openToDay === null && $openToTime === null && is_string($openTo) && ! \Pushery\WireKit\Support\Moment::namesNoDate($openTo)) {
        \Pushery\WireKit\Support\StrictnessGate::reject('datetime-picker', 'openTo', trim($openTo), 'a day such as "2026-10-05", a moment such as "2026-10-05 09:30" or a date object', '');
    }

    // `default-time` with `range`: one time for both ends, or `HH:MM/HH:MM` for the start and the end.
    $defaultParts = $range && is_string($defaultTime) ? explode('/', $defaultTime, 2) : [];
    $defaultTimes = $range ? [TimeLimits::reportedTime($defaultParts[0] ?? null, 'datetime-picker', 'defaultTime'), TimeLimits::reportedTime($defaultParts[1] ?? ($defaultParts[0] ?? null), 'datetime-picker', 'defaultTime')] : [];

    // The words of a typed time; `:time`, `:from` and `:to` are filled in by the script with the
    // times as the field writes them. The rows of a range check a time picked from their list
    // with the same words.
    $timeWords = [
        'hourLabel' => __('wirekit::Hour'),
        'minuteLabel' => __('wirekit::Minute'),
        'dayPeriodLabel' => __('wirekit::AM/PM'),
        'unavailableMessage' => __('wirekit:::time is not available.'),
        'outsideMessage' => __('wirekit::Pick a time from :from to :to.'),
    ];

    // With `type="input"`: the names of the six segments, what a field says when a moment cannot
    // be taken, the days the calendar closes, which a field checks the same way, and whether the
    // segments can be changed. With `range` each end takes them, and the days a range covers
    // count both its ends, as on the calendar.
    $momentWords = [
        'dayLabel' => __('wirekit::Day'),
        'monthLabel' => __('wirekit::Month'),
        'yearLabel' => __('wirekit::Year'),
        'hourLabel' => __('wirekit::Hour'),
        'minuteLabel' => __('wirekit::Minute'),
        'dayPeriodLabel' => __('wirekit::AM/PM'),
        'invalidMessage' => __('wirekit::This date does not exist.'),
        'dayUnavailableMessage' => __('wirekit:::date is not available.'),
        'unavailableMessage' => __('wirekit:::time is not available.'),
        'outsideMessage' => __('wirekit::Pick a time from :from to :to.'),
        'days' => array_intersect_key(CalendarLimits::from($minDay, $maxDay, $unavailableDays, null, null, false), array_flip(['min', 'max', 'unavailable'])),
        'readonly' => $readonly,
    ];
    // The shortest and the longest range in days, at least one each, or no limit: a value that
    // holds no number sets none, NAN and INF among them, which a cast to int warns about.
    $minRange = \Pushery\WireKit\Support\NumericProp::intOrNull($minRange, 'datetime-picker', 'minRange');
    $maxRange = \Pushery\WireKit\Support\NumericProp::intOrNull($maxRange, 'datetime-picker', 'maxRange');
    $rangeDaysMin = $minRange !== null ? max(1, $minRange) : null;
    $rangeDaysMax = $maxRange !== null ? max(1, $maxRange) : null;

    $config = AlpinePayload::from([
        'locale' => $dateLocale,
        'panelId' => $panelId,
        'range' => $range,
        'withConfirmation' => $withConfirmation,
        'defaultTime' => $range ? $defaultTimes : TimeLimits::reportedTime($defaultTime, 'datetime-picker', 'defaultTime'),
        'openTo' => $openToTime,
        'timeFormat' => $timeFormat,
        'time' => $timeLimits,
        // A closed time in another language than the page's is named by its text alone (above).
        'unavailableLabel' => $datesLang === null ? __('wirekit::unavailable') : '',
        // The word between the day and the time on the trigger, "Oct 27, 2026 at 10:30 AM".
        'atLabel' => $atWord,
        'timeInput' => $typesTime && ! $range ? $timeWords : null,
        'endAfterStart' => $range ? __('wirekit::End must be after start.') : null,
        // With `type="input"`, the names of the six segments, what the field says when a moment
        // cannot be taken, and the days the calendar closes, which the field checks the same way;
        // with `range`, what the two fields say about the range they hold together.
        'momentInput' => $typesMoment || $typesRange ? $momentWords : null,
        'rangeLimits' => $typesRange ? [
            'min' => $rangeDaysMin,
            'max' => $rangeDaysMax,
            'minMessage' => $rangeDaysMin !== null ? Plural::choice('wirekit::{1} A range covers at least :count day.|[2,*] A range covers at least :count days.', $rangeDaysMin) : null,
            'maxMessage' => $rangeDaysMax !== null ? Plural::choice('wirekit::{1} A range covers at most :count day.|[2,*] A range covers at most :count days.', $rangeDaysMax) : null,
        ] : null,
    ]);

    // With `type="input"` and `range`, the field of each end (resources/js/components/datetime-picker-moment.js),
    // written as a payload where the field is rendered.
    $rangeMomentConfig = static fn (string $end): array => [
        ...$momentWords,
        'end' => $end,
        'locale' => $dateLocale,
        'timeFormat' => $timeFormat,
        'time' => $timeLimits,
    ];

    // The time of each end of a range, a row of its own (resources/js/components/datetime-picker-time.js),
    // written as a payload where the row is rendered.
    $rangeTimeConfig = static fn (string $end): array => [
        'end' => $end,
        'locale' => $dateLocale,
        'timeFormat' => $timeFormat,
        'time' => $timeLimits,
        // A closed time in another language than the page's is named by its text alone (above).
        'unavailableLabel' => $datesLang === null ? __('wirekit::unavailable') : '',
        'timeInput' => $timeWords,
        'openTo' => $defaultTimes[$end === 'end' ? 1 : 0] ?? $openToTime,
        'placeholder' => __('wirekit::Time'),
    ];

    // Sizing shared with the other fields.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    // With `type="input"` the field takes that height as a floor: in a field too narrow for the whole
    // moment the time goes on a line under the date (partials/moment-input), and the field grows.
    $momentHeightClasses = match ($size) {
        'sm' => 'min-h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'min-h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'min-h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    // With `type="input"`: the text size each segment takes as a utility of its own, which the touch
    // floor of `wk-field` lifts to 16px, and the sentence under the field.
    $momentText = match ($size) {
        'sm' => 'text-[length:var(--text-wk-sm)]',
        'lg' => 'text-[length:var(--text-wk-lg)]',
        default => 'text-[length:var(--text-wk-md)]',
    };
    $momentErrorId = $pickerId.'-moment-error';
    $momentDescribedBy = trim($momentErrorId.' '.$describedBy);
    // With `range`: the buttons beside the two fields, squares as tall as a field.
    $momentSquare = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] w-[var(--size-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] w-[var(--size-wk-lg)]',
        default => 'h-[var(--size-wk-md)] w-[var(--size-wk-md)]',
    };

    $triggerClasses = WireKit::resolveClasses('datetime-picker', 'trigger', implode(' ', [
        'flex w-full items-center gap-[var(--gap-wk-sm)]',
        'ps-[var(--padding-wk-x-md)]',
        // Room at the end for the chevron, a square as wide as a medium field is tall, as a select
        // keeps it, and for the clear button before the chevron.
        $clearable
            ? 'pe-[calc(var(--size-wk-md)+var(--size-wk-target-min))]'
            : 'pe-[var(--size-wk-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-start text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'shadow-[var(--shadow-wk-sm)]',
        'rounded-[var(--radius-wk-md)]',
        'cursor-pointer',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'aria-disabled:cursor-default',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    $panelClasses = WireKit::resolveClasses('datetime-picker', 'panel', implode(' ', [
        'fixed z-[var(--z-wk-dropdown,50)]',
        'overflow-y-auto overscroll-contain',
        'rounded-[var(--radius-wk-lg)]',
        'border-[length:var(--border-wk-width)] border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-elevated)]',
        'p-[var(--padding-wk-x-md)]',
        'shadow-[var(--shadow-wk-lg)]',
        'font-[family-name:var(--font-wk-sans)] text-[color:var(--color-wk-text)]',
    ]), $scope);
@endphp

<div
    {{ $outerAttributes }}
    x-data="wirekitDatetimePicker({{ $config }})"
    data-wk-server-value="{{ $serverValue }}"
    x-bind:data-open="isOpen ? '' : null"
    {{ $attributes->whereDoesntStartWith(['wire:model', 'x-model'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [WireKit::resolveClasses('datetime-picker', 'base', 'relative w-full space-y-1.5', $scope)])) }}
    @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif
    data-wk-sr-host
>
    @if($label && ($typesMoment || $typesRange))
        {{-- The label points at the first segment, which the script writes, so it points there
             once the segment is in the page: a press on it starts the typing there. --}}
        <x-wirekit::label :id="$labelId" x-bind:for="{{ AlpinePayload::string($pickerId) }}" :required="$required" :help="$help" :help-id="$helpId" :help-field="(string) ($name ?? $pickerId)">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($label)
        <x-wirekit::label :id="$labelId" for="{{ $pickerId }}" :required="$required" :help="$help" :help-id="$helpId" :help-field="(string) ($name ?? $pickerId)">{{ $label }}<x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after></x-wirekit::label>
    @elseif($hiddenName !== null)
        <span id="{{ $labelId }}" class="sr-only" @if($callerLabel === null) data-wk-fallback-name="{{ $pickerId }}" @endif>{{ $hiddenName }}</span>
    @endif

    <div class="relative">
        @if(\Pushery\WireKit\Support\SlotContent::given($trigger ?? null))
            {{-- A trigger of the caller's, a button of its own: the picker opens on its click and puts
                 the dialog's ARIA on it (resources/js/utils/trigger-aria.js), and its content names
                 it, as a dropdown's trigger does. --}}
            <div x-ref="triggerSlot" x-on:click="togglePanel()" x-on:keydown="triggerKeydown($event)" x-init="initTriggerAria()" class="inline-flex">
                {{ $trigger }}
            </div>
        @elseif($typesMoment)
            {{-- The moment typed in segments, its day, its month, its year, its hour, its minute and
                 its period in the locale's order (resources/js/utils/moment-input.js), and a button
                 at the end that opens the dialog. The group is named by the label; each segment is
                 an `<input>` with the role of a spin button, named after what it holds, and the
                 label points at the first one. The segments and the button carry the hint and the
                 error themselves, so a screen reader entering the group does not read them twice.
                 The script knows the locale's order, so it writes the segments into the frame the
                 server renders. --}}
            <div
                x-ref="momentInputField"
                data-wk-moment-segments
                role="group"
                aria-labelledby="{{ $nameIds }}"
                @class([
                    'wk-field-frame flex w-full items-center gap-[var(--gap-wk-xs)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] bg-[var(--color-wk-bg-input)] shadow-[var(--shadow-wk-sm)] ps-[var(--padding-wk-x-md)] pe-[var(--padding-wk-x-xs)] text-[color:var(--color-wk-text)] focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
                    $momentHeightClasses,
                    'border-[var(--color-wk-border-error)]' => $hasError,
                    'opacity-[var(--opacity-wk-disabled)]' => $disabled,
                ])
                @unless($hasError) :class="momentInputInvalid() ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]'" @endunless
            >
                @include('wirekit::components.partials.moment-input', [
                    'momentInputDescribedBy' => $momentDescribedBy,
                    'momentInputText' => $momentText,
                    'momentInputFirstId' => $pickerId,
                    'momentInputDisabled' => $disabled,
                    'momentInputReadonly' => $readonly,
                    'momentInputRequired' => $required,
                    'momentInputServerError' => $hasError,
                ])
                {{-- The clear button and the button that opens the dialog stand beside the segments and beside
                     each other, so each takes the touch size itself on a touch screen (`wk-field-affordance`)
                     rather than a 44px patch, which would reach into its neighbor and take its taps. --}}
                @if($clearable && ! $disabled && ! $readonly)
                    <button
                        type="button"
                        x-show="value !== ''"
                        @if($moment === null) style="display: none" @endif
                        x-on:click="clear()"
                        aria-label="{{ __('wirekit::Clear :label', ['label' => $clearName]) }}" @if($fallbackClear !== null) data-wk-fallback-clear="{{ $fallbackClear }}" @endif
                        class="wk-field-affordance flex h-[var(--size-wk-target-min)] w-[var(--size-wk-target-min)] shrink-0 cursor-pointer items-center justify-center rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-hover)] hover:text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
                <button
                    type="button"
                    x-ref="trigger"
                    x-on:click="togglePanel()"
                    x-on:keydown="triggerKeydown($event)"
                    aria-haspopup="dialog"
                    aria-expanded="false"
                    x-bind:aria-expanded="isOpen ? 'true' : 'false'"
                    aria-controls="{{ $panelId }}"
                    aria-label="{{ __('wirekit::Select a date and time') }}"
                    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                    @if($hasError) aria-invalid="true" @endif
                    @if($disabled) disabled @endif
                    @if($readonly) aria-disabled="true" @endif
                    @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                    class="wk-field-affordance flex h-[var(--size-wk-target-min)] w-[var(--size-wk-target-min)] shrink-0 cursor-pointer items-center justify-center rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-hover)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:cursor-not-allowed aria-disabled:cursor-default"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </button>
            </div>
            {{-- Why a typed moment cannot be taken, in words, in a live region that stays in the
                 page while it is empty, as a field's error is, unless `announce-error` is off. --}}
            <p data-wk-prose-skip id="{{ $momentErrorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif x-text="momentInputError" class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)] empty:mt-0"></p>
        @elseif($typesRange)
            {{-- A range typed in two fields, its start and its end, each a moment in segments in a
                 scope of its own (resources/js/components/datetime-picker-moment.js), named by the
                 label and its word, with the sentence under it that says why its moment, or for
                 the end the range, is not taken. The two stand side by side and wrap where they do
                 not fit; the buttons beside them clear the range and open the dialog for both. --}}
            <div
                x-ref="momentRangeField"
                role="group"
                aria-labelledby="{{ $nameIds }}"
                class="flex w-full items-start gap-[var(--gap-wk-xs)]"
            >
                <div class="flex min-w-0 flex-1 flex-wrap gap-[var(--gap-wk-xs)]">
                    @foreach(['start', 'end'] as $rangeEnd)
                        @php
                            $rangeMomentPayload = \Pushery\WireKit\Support\AlpinePayload::from($rangeMomentConfig($rangeEnd));
                            $rangeMomentWordId = $pickerId.'-'.$rangeEnd.'-word';
                            $rangeMomentErrorId = $pickerId.'-'.$rangeEnd.'-moment-error';
                        @endphp
                        <div x-data="wirekitDatetimePickerMoment({{ $rangeMomentPayload }})" class="flex min-w-0 flex-auto flex-col">
                            <div
                                x-ref="momentInputField"
                                data-wk-moment-segments
                                role="group"
                                aria-labelledby="{{ $nameIds }} {{ $rangeMomentWordId }}"
                                @class([
                                    'wk-field-frame flex w-full items-center gap-[var(--gap-wk-xs)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] bg-[var(--color-wk-bg-input)] shadow-[var(--shadow-wk-sm)] px-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text)] focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
                                    $momentHeightClasses,
                                    'border-[var(--color-wk-border-error)]' => $hasError,
                                    'opacity-[var(--opacity-wk-disabled)]' => $disabled,
                                ])
                                @unless($hasError) :class="momentInputInvalid() ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]'" @endunless
                            >
                                <span id="{{ $rangeMomentWordId }}" class="shrink-0 text-[color:var(--color-wk-text-muted)]">{{ $rangeEnd === 'end' ? __('wirekit::End') : __('wirekit::Start') }}</span>
                                @include('wirekit::components.partials.moment-input', [
                                    'momentInputDescribedBy' => trim($rangeMomentErrorId.' '.$describedBy),
                                    'momentInputText' => $momentText,
                                    'momentInputFirstId' => $rangeEnd === 'start' ? $pickerId : null,
                                    'momentInputDisabled' => $disabled,
                                    'momentInputReadonly' => $readonly,
                                    'momentInputRequired' => $required,
                                    'momentInputServerError' => $hasError,
                                ])
                            </div>
                            <p data-wk-prose-skip id="{{ $rangeMomentErrorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif x-text="momentShownError" class="mt-[var(--space-wk-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)] empty:mt-0"></p>
                        </div>
                    @endforeach
                </div>
                @if($clearable && ! $disabled && ! $readonly)
                    <button
                        type="button"
                        x-show="value !== ''"
                        @if($serverValue === '') style="display: none" @endif
                        x-on:click="clear()"
                        aria-label="{{ __('wirekit::Clear :label', ['label' => $clearName]) }}" @if($fallbackClear !== null) data-wk-fallback-clear="{{ $fallbackClear }}" @endif
                        class="wk-touch-target flex {{ $momentSquare }} shrink-0 cursor-pointer items-center justify-center rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-input)] shadow-[var(--shadow-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-hover)] hover:text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                @endif
                <button
                    type="button"
                    x-ref="trigger"
                    x-on:click="togglePanel()"
                    x-on:keydown="triggerKeydown($event)"
                    aria-haspopup="dialog"
                    aria-expanded="false"
                    x-bind:aria-expanded="isOpen ? 'true' : 'false'"
                    aria-controls="{{ $panelId }}"
                    aria-label="{{ __('wirekit::Select a date and time range') }}"
                    @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                    @if($hasError) aria-invalid="true" @endif
                    @if($disabled) disabled @endif
                    @if($readonly) aria-disabled="true" @endif
                    @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                    class="wk-touch-target flex {{ $momentSquare }} shrink-0 cursor-pointer items-center justify-center rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-input)] shadow-[var(--shadow-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-hover)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)] aria-disabled:cursor-default"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </button>
            </div>
        @else
        <button
            type="button"
            id="{{ $pickerId }}"
            x-ref="trigger"
            x-on:click="togglePanel()"
            x-on:keydown="triggerKeydown($event)"
            aria-haspopup="dialog"
            aria-expanded="false"
            x-bind:aria-expanded="isOpen ? 'true' : 'false'"
            aria-controls="{{ $panelId }}"
            aria-labelledby="{{ $triggerNameIds }}"
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($disabled) disabled @endif
            @if($readonly) aria-disabled="true" @endif
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
            class="wk-field wk-touch-row {{ $triggerClasses }}"
        >
            <svg class="h-4 w-4 shrink-0 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
            </svg>
            {{-- A button cannot say it is required, so its name does: the label's asterisk is
                 hidden from assistive technology, as a native field says it with `required`. --}}
            @if($required)
                <span id="{{ $pickerId }}-required-word" class="sr-only">{{ __('wirekit::(required)') }}</span>
            @endif
            <span id="{{ $valueId }}" class="min-w-0 flex-1 truncate">
                @if($range)
                <span x-show="! showsPlaceholder" x-text="shownRange"@if($datesLang) lang="{{ $datesLang }}"@endif @if($rangeShown === '') style="display: none" @endif>{{ $rangeShown }}</span>
                @else
                <span x-show="! showsPlaceholder"@if($datesLang) lang="{{ $datesLang }}"@endif @if($shownDay === '') style="display: none" @endif><span x-text="shownDay">{{ $shownDay }}</span><span x-show="shownTime !== ''" @if($shownTime === '') style="display: none" @endif> <span class="text-[color:var(--color-wk-text-muted)]" x-text="atWord">{{ $shownTime !== '' ? $atWord : '' }}</span> <span x-text="shownTime">{{ $shownTime }}</span></span></span>
                @endif
                <span x-show="showsPlaceholder" @if($shownAny !== '') style="display: none" @endif class="text-[color:var(--color-wk-text-placeholder)]">{{ $placeholder }}</span>
            </span>
        </button>

        @endif

        @unless(\Pushery\WireKit\Support\SlotContent::given($trigger ?? null) || $typesMoment || $typesRange)
        {{-- The chevron and the clear button stand over the end of the trigger rather than in it,
             as a button holds no other button. The chevron lets a press through to the trigger. --}}
        <svg class="pointer-events-none absolute top-1/2 end-[var(--padding-wk-x-md)] h-4 w-4 -translate-y-1/2 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
        </svg>

        @if($clearable && ! $disabled && ! $readonly)
            <button
                type="button"
                x-show="value !== ''"
                @if($serverValue === '') style="display: none" @endif
                x-on:click="clear()"
                aria-label="{{ __('wirekit::Clear :label', ['label' => $clearName]) }}" @if($fallbackClear !== null) data-wk-fallback-clear="{{ $fallbackClear }}" @endif
                class="wk-touch-target absolute top-1/2 end-[calc(var(--padding-wk-x-md)+--spacing(4)+var(--gap-wk-xs))] flex h-[var(--size-wk-target-min)] w-[var(--size-wk-target-min)] -translate-y-1/2 cursor-pointer items-center justify-center rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-hover)] hover:text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        @endif
        @endunless
    </div>

    {{-- The field a form submits and `wire:model` binds: the moment, written by the script with
         the events a native field fires for a pick, or with `range` a field for each end and one
         for the preset. A disabled picker leaves them out of the form. --}}
    @if($range)
        <input type="hidden" x-ref="inputStart" @if($name) name="{{ $name }}[start]" @endif value="{{ $rangeEnds['start'] ?? '' }}" @if($disabled) disabled @endif {{ $endModels('start') }} @if($formOwner) form="{{ $formOwner }}" @endif />
        <input type="hidden" x-ref="inputEnd" @if($name) name="{{ $name }}[end]" @endif value="{{ $rangeEnds['end'] ?? '' }}" @if($disabled) disabled @endif {{ $endModels('end') }} @if($formOwner) form="{{ $formOwner }}" @endif />
        @if($presetKeys !== [])
            <input type="hidden" x-ref="inputPreset" @if($name) name="{{ $name }}[preset]" @endif value="{{ $rangePreset }}" @if($disabled) disabled @endif {{ $endModels('preset') }} @if($formOwner) form="{{ $formOwner }}" @endif />
        @endif
    @else
        <input type="hidden" x-ref="input" @if($name) name="{{ $name }}" @endif value="{{ $moment ?? '' }}" x-bind:value="value" @if($disabled) disabled @endif {{ \Pushery\WireKit\Support\CallerModel::onField($attributes->whereStartsWith(['wire:model', 'x-model'])) }} @if($formOwner) form="{{ $formOwner }}" @endif />
    @endif

    {{-- The browser validates no hidden field, so a required picker stops an empty submit through a
         stand-in of its own. --}}
    @if($required)
        @include('wirekit::components.partials.required-check', ['requiredFormOwner' => $formOwner, 'requiredDisabled' => $disabled || $readonly])
    @endif

    {{-- The dialog, teleported out of the document flow and placed below the trigger
         (wirekitDatetimePicker._place). `click.outside` sits here, on the dialog, because the
         teleport takes it out of the root. The calendar is the calendar component, without its
         own frame; the picker hands it the day to show and hears the day it picks. --}}
    {{-- On a phone the dialog is a modal over the page (utils/picker-dialog.js, `modalBelow`): this
         is its backdrop, which a tap closes as Escape does. --}}
    <template x-teleport="#wk-overlay-root">
        <div
            x-show="isOpen && dialogModal"
            x-cloak
            wire:key="wk-datetime-picker-backdrop"
            x-transition:enter="transition-opacity ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-opacity ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:click="closePanel()"
            aria-hidden="true"
            class="wk-overlay-fixed wk-overlay-layer-modal fixed inset-0 z-[var(--z-wk-modal)] bg-[var(--color-wk-overlay)]"
        ></div>
    </template>

    <template x-teleport="#wk-overlay-root">
        <div
            x-show="isOpen"
            x-cloak
            wire:key="wk-datetime-picker-panel"
            {{-- Theme marker — see docs/theming.md "Theme markers". --}}
            data-wk-picker-dialog
            x-bind:data-wk-picker-modal="dialogModal ? '' : null"
            x-ref="panel"
            id="{{ $panelId }}"
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            x-on:click.outside="closePanelOutside($event)"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $nameIds }} {{ $panelId }}-title"
            class="{{ $panelClasses }}"
        >
            {{-- The dialog is named by the field and by what it is for, "Meeting, Select a date and time". --}}
            <span id="{{ $panelId }}-title" class="sr-only">{{ $range ? __('wirekit::Select a date and time range') : __('wirekit::Select a date and time') }}</span>
            @if($range)
                <div class="flex flex-col gap-[var(--gap-wk-md)]">
                    <div x-on:input="onCalendarInput($event)">
                        <x-wirekit::calendar
                            flush
                            range
                            name="{{ $pickerId }}-days"
                            :value="$rangeDays"
                            :presets="$presetKeys === [] ? null : $presetKeys"
                            :min-range="$minRange"
                            :max-range="$maxRange"
                            :open-to="$openToDay"
                            :min="$minDay"
                            :max="$maxDay"
                            :unavailable="$unavailableDays"
                            :months="$months"
                            :with-today="$withToday"
                            :selectable-header="$selectableHeader"
                            :week-numbers="$weekNumbers"
                            :week-starts-on="$weekStartsOn"
                            :fixed-weeks="$fixedWeeks"
                            :locale="$dateLocale"
                            x-bind:data-wk-server-value="draftDays"
                            x-bind:data-wk-preset="draftPreset"
                            x-bind:data-wk-opened="dialogOpenings"
                            x-bind:data-wk-months="dialogModal ? 1 : {{ $calendarMonths }}"
                        />
                    </div>
                    {{-- The time of each end in a row of its own, and the sentence that says why the
                         two cannot be taken while the end does not come after the start. --}}
                    <div class="flex flex-col gap-[var(--gap-wk-sm)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] pt-[var(--padding-wk-y-md)]">
                        @foreach(['start' => __('wirekit::Start'), 'end' => __('wirekit::End')] as $rangeEnd => $rangeEndLabel)
                            @php $rangeRowConfig = AlpinePayload::from($rangeTimeConfig($rangeEnd)); @endphp
                            <div x-data="wirekitDatetimePickerTime({{ $rangeRowConfig }})">
                                @if($typesTime)
                                    @include('wirekit::components.partials.time-input', ['timeInputId' => $panelId.'-'.$rangeEnd, 'timeInputLabel' => $rangeEndLabel])
                                @else
                                    @include('wirekit::components.partials.time-select', ['timeSelectId' => $panelId.'-'.$rangeEnd, 'timeSelectLabel' => $rangeEndLabel])
                                @endif
                            </div>
                        @endforeach
                        <p data-wk-prose-skip id="{{ $panelId }}-range-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif x-text="rangeError" class="text-end text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)] empty:hidden"></p>
                    </div>
                </div>
            @else
            <div data-wk-picker-day-time @class(['flex flex-col gap-[var(--gap-wk-md)]', 'sm:flex-row sm:items-stretch' => ! $typesTime])>
                <div x-on:input="onCalendarInput($event)">
                    <x-wirekit::calendar
                        flush
                        name="{{ $pickerId }}-days"
                        :value="$momentDay"
                        :open-to="$openToDay"
                        :min="$minDay"
                        :max="$maxDay"
                        :unavailable="$unavailableDays"
                        :months="$months"
                        :with-today="$withToday"
                        :selectable-header="$selectableHeader"
                        :week-numbers="$weekNumbers"
                        :week-starts-on="$weekStartsOn"
                        :fixed-weeks="$fixedWeeks"
                        :locale="$dateLocale"
                        x-bind:data-wk-server-value="draftDay || ''"
                        x-bind:data-wk-opened="dialogOpenings"
                    />
                </div>
                @if($typesTime)
                    <div class="border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] pt-[var(--padding-wk-y-md)]">
                        @include('wirekit::components.partials.time-input', ['timeInputId' => $panelId.'-time'])
                    </div>
                @else
                    <div x-show="! dialogModal" class="flex border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] pt-[var(--padding-wk-y-md)] sm:border-t-0 sm:border-s-[length:var(--border-wk-width)] sm:pt-0 sm:ps-[var(--padding-wk-x-md)]">
                        @include('wirekit::components.partials.time-list', ['timeListId' => $panelId.'-times'])
                    </div>
                    {{-- In a modal the list of times gives way to a select with the same times, its
                         closed ones disabled, which a phone opens as its own wheel. --}}
                    <div x-show="dialogModal" style="display: none" class="flex items-center justify-between gap-[var(--gap-wk-md)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] pt-[var(--padding-wk-y-md)]">
                        <label for="{{ $panelId }}-time-select" class="text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-heading-weight)] text-[color:var(--color-wk-text)]">{{ __('wirekit::Time') }}</label>
                        <div class="relative">
                            <select
                                id="{{ $panelId }}-time-select"
                                x-ref="timeSelect"
                                x-on:change="chooseTimeFromSelect($event)"
                                class="wk-field block h-[var(--size-wk-md)] appearance-none rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)] bg-[var(--color-wk-bg-input)] ps-[var(--padding-wk-x-md)] pe-[var(--size-wk-md)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] shadow-[var(--shadow-wk-sm)] cursor-pointer focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                            >
                                <option value="" disabled x-bind:selected="! timeListValue">{{ __('wirekit::Time') }}</option>
                                <template x-for="slot in timeListSlots()" :key="slot.value">
                                    <option :value="slot.value" :disabled="slot.closed" :selected="slot.value === timeListValue" x-text="slot.label"></option>
                                </template>
                            </select>
                            <svg class="pointer-events-none absolute top-1/2 end-[var(--padding-wk-x-md)] h-4 w-4 -translate-y-1/2 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                @endif
            </div>
            @endif

            {{-- Cancel and Select: with confirmation, and on a phone, where a pick always waits for
                 Select, in every mode. In a dialog that scrolls, a modal taller than the screen or a
                 panel capped to the room beside its trigger, the row stays in sight. --}}
            <div
                data-wk-picker-footer
                @unless($withConfirmation) x-show="confirming" style="display: none" @endunless
                class="mt-[var(--space-wk-sm)] flex justify-end gap-[var(--gap-wk-sm)] border-t-[length:var(--border-wk-width)] border-[var(--color-wk-border)] pt-[var(--padding-wk-y-md)]"
            >
                <x-wirekit::button intent="neutral" surface="ghost" size="sm" x-on:click="cancel()">{{ __('wirekit::Cancel') }}</x-wirekit::button>
                <x-wirekit::button size="sm" x-on:click="confirmPick()" x-bind:disabled="! canConfirm" :aria-describedby="$range ? $panelId.'-range-error' : null">{{ $range ? __('wirekit::Select range') : __('wirekit::Select') }}</x-wirekit::button>
            </div>
        </div>
    </template>

    @if($hint && ! $hasError)
        <p data-wk-prose-skip id="{{ $hintId }}" class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($showsError)
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @endif

    @if($requiredMessageId)
        @include('wirekit::components.partials.required-message', ['requiredMessageId' => $requiredMessageId])
    @endif
</div>
