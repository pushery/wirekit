{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the date lands the moment a day is clicked.
     A discrete server value, and the previous one is the server's — so an undo
     destroys nothing the user typed. The optimistic scope nests INSIDE this
     component and binds to `selected`; the DISPLAYED month is deliberately
     outside the snapshot, so a refused pick leaves you looking at the month you
     paged to rather than snapping back. --}}
@props([
    // Livewire method to call optimistically. The date appears selected
    // immediately and is put back if the call fails. Absent -> the component renders
    // without an optimistic layer.
    // Needs `wirekit-optimistic.js` on the page beside the bundle, as
    // docs.wirekit.app/extending/optimistic-ui shows; without it no `wirekitOptimistic` exists.
    'optimistic' => null,
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer carries that: it spreads `args` into the call.
    'optimisticArgs' => [],
    'value' => null,
    // Multi-month display — render N consecutive months side by side (1 = the
    // classic single grid). Clamped 1..4 in the Alpine component.
    // Two-click range selection. `value` then reads and writes
    // `YYYY-MM-DD/YYYY-MM-DD`, the spelling the date-picker's own range flag
    // already uses — a value moved between the two keeps its meaning.
    //
    // Written WITHOUT the component tag on purpose: Blade compiles component tags
    // across the whole file before a PHP comment means anything, so naming one
    // here compiles it, and the failure arrives as `Undefined variable $component`
    // from a line that is not code.
    'range' => false,
    'months' => 1,
    // Replace the static month label with native month + year <select> jump
    // controls for fast navigation. Opt-in; off by default.
    'selectableHeader' => false,
    // First day of the week: 0 (Sun) .. 6 (Sat), Monday by default, which matches the
    // event-calendar default. Configurable via config/wirekit.php; `null` there, or `locale` here,
    // follows the application's locale (Support\LocalizedDate::firstDayOfWeek()).
    'weekStartsOn' => config('wirekit.components.calendar.week-starts-on', 1),
    // BCP-47 locale for every date the calendar writes: the month heading, the weekday columns,
    // the names of the days and the first day of a week that follows the locale. Null → the
    // application locale. Its texts stay in the application's language.
    'locale' => null,
    'scope' => null,
    // A required calendar stops an empty submit, as a native required field does; see the
    // stand-in below. Declared, so it does not land on the root, where it would do nothing.
    'required' => false,
    // Locked as a disabled field is: no day, arrow, preset or header control can be pressed, and
    // the form receives none of the calendar's fields.
    'disabled' => false,
    // Shown as a read-only field is: the months and days can be read and moved between, nothing
    // picks a day or a preset, and the form still receives the value. `disabled` wins over it.
    'readonly' => false,
    // The first and the last day a reader can pick: a day, a date and time, a date object,
    // `today` or `now`, each read as its day in the application's time zone (Support\CalendarLimits).
    'min' => null,
    'max' => null,
    // Days a reader cannot pick: a list, or days separated by commas.
    'unavailable' => null,
    // The fewest and the most days a range may cover, both ends counted. Range mode only.
    'minRange' => null,
    'maxRange' => null,
    // With `range`, a list of ranges beside the calendar: the keys Support\DatePresets names,
    // `today`, `last7Days`, `thisMonth` and the others, as a list or separated by spaces, and
    // `custom` for a range picked in the grid; `true` for the default list. A range outside the
    // limits stays in the list and cannot be picked. Not with `optimistic`, whose layer takes one
    // day at a time.
    'presets' => null,
    // A button beside the arrows that goes back to today's month, and picks today there.
    'withToday' => false,
    // A column with the ISO 8601 number of each week.
    'weekNumbers' => false,
    // Six weeks in every month, so that the calendar keeps its height from month to month.
    'fixedWeeks' => false,
    // The month the calendar opens on without a value: a day, `YYYY-MM` or a date object.
    'openTo' => null,
    // The size of the day cells: `sm`, `md` or `lg`.
    'size' => 'md',
    // Without the panel's frame: no border, shadow, background or padding, for a calendar placed
    // on a surface of its own, as the datetime picker places it in its popover.
    'flush' => false,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('calendar', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $selectableHeader = BooleanProp::from($selectableHeader, false, 'calendar', 'selectableHeader');
    $range = BooleanProp::from($range, false, 'calendar', 'range');
    $required = BooleanProp::from($required, false, 'calendar', 'required');
    $disabled = BooleanProp::from($disabled, false, 'calendar', 'disabled');
    $readonly = BooleanProp::from($readonly, false, 'calendar', 'readonly') && ! $disabled;
    $withToday = BooleanProp::from($withToday, false, 'calendar', 'withToday');
    $weekNumbers = BooleanProp::from($weekNumbers, false, 'calendar', 'weekNumbers');
    $fixedWeeks = BooleanProp::from($fixedWeeks, false, 'calendar', 'fixedWeeks');
    $flush = BooleanProp::from($flush, false, 'calendar', 'flush');
    // An unknown size is reported in debug and drawn at `md`, the default.
    $size = WireKit::validateProp('calendar', 'size', (string) $size, ['md', 'sm', 'lg']);

    // The day cells at each size: the token a day grows to, never below the WCAG 2.5.8 target,
    // and the text that fits it. Written out per size so that a Tailwind scan finds every class
    // whole; the sizing row below the grid asks its columns for the same token.
    $dayCellSizeClasses = match ($size) {
        'sm' => 'w-full max-w-[var(--size-wk-sm)] min-w-[var(--size-wk-target-min)] aspect-square mx-auto text-[length:var(--text-wk-xs)]',
        'lg' => 'w-full max-w-[var(--size-wk-lg)] min-w-[var(--size-wk-target-min)] aspect-square mx-auto text-[length:var(--text-wk-md)]',
        default => 'w-full max-w-[var(--size-wk-md-compact)] min-w-[var(--size-wk-target-min)] aspect-square mx-auto text-[length:var(--text-wk-sm)]',
    };

    // `open-to` as the first of its month when it names a month alone, else as the day it names.
    $openToDay = is_string($openTo) && preg_match('/^\d{4}-\d{2}$/', trim($openTo)) === 1
        ? \Pushery\WireKit\Support\CalendarLimits::day(trim($openTo).'-01')
        : \Pushery\WireKit\Support\CalendarLimits::day($openTo);

    // A text that names neither a day nor a month is reported where the developer is, as `min` and
    // `max` are (Support\CalendarLimits::reportedDay()): written wrong, the calendar would open on
    // the current month without a word.
    if ($openToDay === null && is_string($openTo) && ! \Pushery\WireKit\Support\Moment::namesNoDate($openTo)) {
        \Pushery\WireKit\Support\StrictnessGate::reject('calendar', 'openTo', trim($openTo), 'a day such as "2026-10-05", a month such as "2026-10", a date and time or a date object', '');
    }

    // A date object, which is what an Eloquent `date` cast hands over, is written as the day it
    // names: cast to a string it would carry a time of day the factory cannot read. A date and
    // time written as text is read as its day too (Support\DateModel). A range may also arrive as
    // its two ends, `['start' => …, 'end' => …]` or `[$start, $end]` as on the date picker, each
    // end a date object or a string.
    $toDay = static fn ($date) => \Pushery\WireKit\Support\DateModel::written('date', $date);

    // The preset a range was chosen by travels beside its ends: a Values\DateRange carries it, and
    // so does an array with a `preset` key, as a form sends `name[preset]` back. It marks the
    // preset in the list, whatever day the range was computed on.
    $namedPreset = $value instanceof \Pushery\WireKit\Values\DateRange
        ? $value->preset()
        : (is_array($value) && is_string($value['preset'] ?? null) ? $value['preset'] : null);

    // A range object gives its two ends: a Values\DateRange a Livewire property is typed with, or
    // any other period.
    if ($value instanceof \DatePeriod) {
        $value = \Pushery\WireKit\Values\DateRange::from($value)->toFields();
    }

    // The two ends may arrive in a Collection as well.
    if ($value instanceof \Illuminate\Support\Enumerable) {
        $value = $value->all();
    }

    // An end that is a list itself, as `old()` gives one after a request that sent
    // `name[start][]`, is reported and left out (Support\FieldValue).
    $value = is_array($value) ? \Pushery\WireKit\Support\FieldValue::items($value, 'calendar') : $value;

    if (is_array($value)) {
        $value = implode('/', array_filter(
            [$toDay($value['start'] ?? ($value[0] ?? null)), $toDay($value['end'] ?? ($value[1] ?? null))],
            static fn ($end): bool => $end !== null && $end !== ''
        ));
    } else {
        $value = $toDay($value);
    }

    // Split for the pre-Alpine markup only. The component reads the same string
    // again in JS — one parse per side rather than a value threaded through two
    // formats, which is how the two would drift.
    [$rangeStart, $rangeEnd] = array_pad(explode('/', (string) $value, 2), 2, '');

    // Calendar — standalone month grid for date selection.
    // Uses role="grid" with keyboard navigation: arrows, PageUp/Down, Home/End.
    // The bag holds a bound name escaped once; read as the text it stands for, so the field is
    // sent under the name the caller bound (Support\AttributeText).
    $name = \Pushery\WireKit\Support\AttributeText::get($attributes, 'name', 'date');
    // The ids this calendar derives from its name. Two calendars on one page with the same name,
    // or with none (the name falls back to `date`), wrote the same ones, so the second grid was
    // named by the first one's month heading and the second header's labels named the first
    // one's selects. DomId::distinct keeps the first verbatim and gives a repeat `-2`.
    $idBase = \Pushery\WireKit\Support\DomId::distinct($name.'-calendar');
    $monthSelectId = $selectableHeader ? \Pushery\WireKit\Support\DomId::distinct($name.'-month') : null;
    $yearSelectId = $selectableHeader ? \Pushery\WireKit\Support\DomId::distinct($name.'-year') : null;
    // The message of an empty required calendar, named by its grids. The id follows the
    // month labels', which the factory builds from the same base.
    $requiredMessageId = $required ? $idBase.'-required' : null;

    $classes = WireKit::resolveClasses('calendar', 'base', implode(' ', array_filter([
        // wk-calendar: on phones (<640px) the dist/wirekit.css rule flips this to
        // block + width:100% so the grid fills the container instead of sitting at
        // its fixed ~312px content width (which reads as "too narrow" and can clip
        // the trailing weekday column in a padding-squeezed container). Desktop
        // keeps the compact inline-block content-width grid — render unchanged.
        'wk-calendar inline-block',
        $flush ? null : 'bg-[var(--color-wk-bg-elevated)]',
        $flush ? null : 'border-[length:var(--border-wk-width)]',
        $flush ? null : 'border-[var(--color-wk-border)]',
        $flush ? null : 'rounded-[var(--radius-wk-lg)]',
        $flush ? null : 'shadow-[var(--shadow-wk-md)]',
        $flush ? null : 'p-[var(--padding-wk-x-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        // Disabled, the controls fade and the month and weekdays stay readable, as the text around
        // the controls of a disabled fieldset does: they are no control a reader could press.
        $disabled ? 'cursor-not-allowed' : null,
    ])), $scope);

    $headerClasses = WireKit::resolveClasses('calendar', 'header', implode(' ', [
        'flex items-center justify-between',
        'mb-[var(--padding-wk-y-sm)]',
    ]), $scope);

    $navBtnClasses = implode(' ', [
        'p-1',
        'cursor-pointer',
        'rounded-[var(--radius-wk-sm)]',
        'text-[color:var(--color-wk-text-muted)]',
        'hover:bg-[var(--color-wk-bg-hover)]',
        // At `min` or `max` the way on is closed. `aria-disabled` rather than `disabled`, so a
        // button the reader just pressed keeps the focus when it closes under them.
        'aria-disabled:cursor-not-allowed aria-disabled:opacity-[var(--opacity-wk-disabled)] aria-disabled:hover:bg-transparent',
        // A disabled calendar locks every arrow and the today button natively.
        'disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)] disabled:hover:bg-transparent',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
    ]);

    // `relative` holds the forced-colors mark the stylesheet draws on `::before`, through the
    // `wk-calendar-day` marker each day button carries beside these classes.
    //
    // A day takes the width of its column up to the token's size and stays square, and shrinks
    // with its column down to the WCAG 2.5.8 target. At the token's size the seven columns need
    // 280px of grid, which a phone column does not always leave; at the target they need 196px,
    // and the panel 222px. How wide the grid wants to be, where nothing presses it, comes from
    // the sizing row below the body (`partials/calendar-sizing-row`), so a calendar in a container that sizes to
    // its content is as wide as it always was.
    $dayBtnClasses = implode(' ', [
        'relative',
        'flex items-center justify-center',
        'rounded-[var(--radius-wk-sm)]',
        'tabular-nums',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        $dayCellSizeClasses,
    ]);

    // Native month/year selects (selectableHeader), drawn as pills on the muted surface, so the
    // header reads as part of the calendar rather than as a form above it. Native, so the
    // keyboard and a phone's picker wheel come with them.
    $headerSelectClasses = implode(' ', [
        // appearance-none + a custom chevron overlay (below) so the open arrow
        // matches the select component instead of the browser's far-right default.
        'appearance-none',
        'rounded-[var(--radius-wk-full)]',
        'border-[length:var(--border-wk-width)]',
        // The control border, as on the select component: the decorative border stood at
        // 1.30:1 against the page, under the 3:1 WCAG 1.4.11 asks of a control's boundary.
        'border-[var(--color-wk-border-strong)]',
        'hover:border-[var(--color-wk-border-strong-hover)]',
        'bg-[var(--color-wk-bg-muted)]',
        'pl-[var(--padding-wk-x-md)] pr-7 py-1',
        'text-[length:var(--text-wk-sm)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'text-[color:var(--color-wk-text)]',
        'font-[family-name:var(--font-wk-sans)]',
        'cursor-pointer disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]);

    // The column headers before Alpine starts, and without JavaScript for good: in the
    // application's language and in the two forms the factory asks Intl for (`weekday: 'short'`
    // for the cell, `'long'` for `abbr`), so its own headers replace them with the same words.
    // The configured start day leads (Monday by default, per the house convention); 1 January
    // 2023 was a Sunday, the anchor the factory counts from as well.
    // Any whole number is a day of the week: PHP's `%` keeps the sign, so a negative one is
    // brought back into 0..6 before it counts days, here and in the factory, which gets this value.
    // Underscores to hyphens because Laravel spells a regional locale `pt_BR` and Intl reads
    // BCP-47.
    $dateLocale = \Pushery\WireKit\Support\IcuLocale::tag(filled($locale) ? trim((string) $locale) : null);
    $wkStart = $weekStartsOn === null || (is_string($weekStartsOn) && strtolower(trim($weekStartsOn)) === 'locale')
        ? (int) \Pushery\WireKit\Support\LocalizedDate::firstDayOfWeek($dateLocale)
        : (int) (((\Pushery\WireKit\Support\NumericProp::int($weekStartsOn, 0, 'calendar', 'weekStartsOn') % 7) + 7) % 7);
    $weekdays = [];
    for ($i = 0; $i < 7; $i++) {
        $weekday = \Carbon\CarbonImmutable::create(2023, 1, 1 + (($wkStart + $i) % 7));
        $weekdays[] = [
            'short' => \Pushery\WireKit\Support\LocalizedDate::bySkeleton($weekday, 'EEE', 'D', $dateLocale),
            'long' => \Pushery\WireKit\Support\LocalizedDate::bySkeleton($weekday, 'EEEE', 'l', $dateLocale),
        ];
    }

    // Built here rather than assembled inside the attribute, for two reasons —
    // and the second one is not cosmetic.
    //
    // Both literals come from AlpinePayload, which writes a JavaScript string the
    // CSP parser reads as one argument. A prop written straight into a quoted JS
    // literal is not safe in a directive: `{{ }}` escapes an apostrophe to
    // &#039;, the browser decodes it back before Alpine reads the attribute, and
    // a name like "o'clock" would end the string and run the rest as expression.
    // Echoed through `{{ }}`, the literal's own double quotes arrive as &quot;,
    // so the attribute stays whole.
    //
    // Js::from is not interchangeable with it here: it writes JSON.parse('…') for
    // a JsonSerializable value, and JSON is a global the CSP evaluator cannot
    // resolve; and it writes non-ASCII as \u escapes, which some CSP tokenizers
    // read back without the backslash, so a name with an umlaut arrives mangled.
    //
    // The value is not one of them. It travels on `data-wk-server-value`, so the expression is
    // the same for every value: a Livewire morph that changed it would start the calendar again,
    // and the answer to a `wire:model.live` pick would take the grid back to the request's month.
    $nameLiteral = \Pushery\WireKit\Support\AlpinePayload::from($name);
    $idBaseLiteral = \Pushery\WireKit\Support\AlpinePayload::from($idBase);

    // What a reader can pick, written on the root rather than into `x-data`: a render that changes
    // the limits, `min="today"` on the next day or a day the server has just booked, then reaches
    // the grid without starting the calendar again. The script reads and follows the attributes.
    // A bound written as text that names no day limits nothing and is reported, as an invalid
    // prop is (Support\CalendarLimits::reportedDay()), and so is an entry of `unavailable` that
    // names neither a day nor a time (reportedUnavailable()): written wrong, it stays open.
    // The fewest and the most days of a range, as whole numbers: a text that holds none is
    // reported, as any number written wrong is (Support\NumericProp), and sets no bound.
    $minRange = \Pushery\WireKit\Support\NumericProp::intOrNull($minRange, 'calendar', 'minRange');
    $maxRange = \Pushery\WireKit\Support\NumericProp::intOrNull($maxRange, 'calendar', 'maxRange');
    // One to four months, read as the script clamps them, so the layout the view picks and the
    // grids the script draws agree: a value that holds no whole number is one month.
    $months = min(4, max(1, \Pushery\WireKit\Support\NumericProp::int($months, 1, 'calendar', 'months')));
    $calendarLimits = \Pushery\WireKit\Support\CalendarLimits::from(
        \Pushery\WireKit\Support\CalendarLimits::reportedDay($min, 'calendar', 'min'),
        \Pushery\WireKit\Support\CalendarLimits::reportedDay($max, 'calendar', 'max'),
        \Pushery\WireKit\Support\CalendarLimits::reportedUnavailable($unavailable, 'calendar'),
        $minRange,
        $maxRange,
        $range,
    );
    $unavailableLiteral = \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::unavailable'));

    // The presets this calendar offers (Support\DatePresets), with `range` and without
    // `optimistic`. `allTime` runs from `min`, the first day there is, to today, so without `min`
    // it is left out, and a debug build says why. Today is the application's, written on the root
    // for the script, so the list and Values\DateRange agree on what "today" means.
    $presetKeys = $range && $optimistic === null ? \Pushery\WireKit\Support\DatePresets::listed($presets) : [];

    if (in_array('allTime', $presetKeys, true) && blank($calendarLimits['min'])) {
        $presetKeys = array_values(array_diff($presetKeys, ['allTime']));

        if ((bool) config('app.debug') || app()->runningInConsole()) {
            logger()->warning('WireKit [calendar]: the preset allTime runs from `min` to today, and a calendar without `min` leaves it out of its list.');
        }
    }

    $presetLabels = \Pushery\WireKit\Support\DatePresets::labels();
    $presetsLiteral = \Pushery\WireKit\Support\AlpinePayload::from([
        'keys' => $presetKeys,
        'labels' => array_intersect_key($presetLabels, array_flip($presetKeys)),
    ]);
    $namedPreset = is_string($namedPreset) && in_array($namedPreset, $presetKeys, true) ? $namedPreset : '';

    // The APPLICATION's locale reaches the factory, or the one `locale` names, never the
    // browser's. The month heading is mirrored into an aria-live region and every day cell is
    // named with its full date, so the language those are spoken in is the one the page is
    // written in — a German page read on an English laptop announced its months in English
    // while the surrounding text stayed German. Same shape as <x-wirekit::countdown>.
    $calendarLocale = \Pushery\WireKit\Support\AlpinePayload::from($dateLocale);

    // A `locale` in another language than the page's: what the calendar shows in it, the month
    // heading, the weekdays and the names in the month list, carries `lang` (WCAG 3.1.2), and the
    // names a screen reader hears for its days and its today button are written in the page's
    // language, since a name carries one language and the words added to it are the page's. Two
    // spellings of one language (`en`, `en-GB`) are one language, and nothing changes for them.
    $pageLocale = \Pushery\WireKit\Support\IcuLocale::tag();
    $languageOf = static fn (string $tag): string => strtolower(explode('-', $tag, 2)[0]);
    $datesLang = $languageOf($dateLocale) !== $languageOf($pageLocale) ? $dateLocale : null;
    $labelLocale = \Pushery\WireKit\Support\AlpinePayload::from($datesLang !== null ? $pageLocale : $dateLocale);

    // "Today" cannot come out of a date formatter, so it is passed in translated.
    $todayLiteral = \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Today'));
    // The today button's name with `:date` left in it: the factory writes today's month and day
    // there, in the application's language, from the reader's clock.
    $todayButtonLiteral = \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Today, :date'));
    $openToLiteral = \Pushery\WireKit\Support\AlpinePayload::from($openToDay);

    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the day buttons have to be inside
    // it to reach its `run()`.
    //
    // `after: '_notify'` keeps a plain HTML form honest — the hidden input is
    // synced there, and without the call a rollback would leave the form
    // submitting the date that was just taken back.
    //
    // `viewYear` / `viewMonth` are NOT in the snapshot, and that is the design:
    // a reader who paged to March and had their pick refused should still be
    // looking at March. The date rolls back; the place you are looking does not.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_notify',
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
    ]);
    // A caller's `x-ref` belongs to the caller's component, and this bag lands on our root,
    // which would keep it: CallerRef::onRoot() hands it to the root above.
    $attributes = \Pushery\WireKit\Support\CallerRef::onRoot($attributes);
    // A caller's `form` goes to the field this component submits, and only there: on the
    // wrapper it is not a valid attribute and joins nothing to the form (Support\FormOwner).
    $formOwner = \Pushery\WireKit\Support\FormOwner::of($attributes);
    $attributes = $attributes->except('form');
    // A caller's `aria-labelledby` names the widget where a reader meets it, not the wrapper,
    // where it would name nothing a reader lands on.
    $callerLabelledBy = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-labelledby');
    $callerLabelledBy = is_string($callerLabelledBy) && filled($callerLabelledBy) ? $callerLabelledBy : null;
    // A caller's `aria-label` goes to the same element: on the wrapper, which has no role, ARIA
    // prohibits a name, and a reader never hears it.
    $callerLabel = \Pushery\WireKit\Support\AttributeText::get($attributes, 'aria-label');
    $callerLabel = is_string($callerLabel) && filled($callerLabel) ? $callerLabel : null;
@endphp

<div
    data-wk-server-value="{{ (string) $value }}"
    data-wk-min="{{ $calendarLimits['min'] }}"
    data-wk-max="{{ $calendarLimits['max'] }}"
    data-wk-unavailable="{{ implode(',', $calendarLimits['unavailable']) }}"
    data-wk-min-range="{{ $calendarLimits['minRange'] }}"
    data-wk-max-range="{{ $calendarLimits['maxRange'] }}"
    @if($presetKeys !== [])
        data-wk-today="{{ \Carbon\CarbonImmutable::today()->format('Y-m-d') }}"
        data-wk-preset="{{ $namedPreset }}"
    @endif
    x-data="wirekitCalendar({ name: {{ $nameLiteral }}, idBase: {{ $idBaseLiteral }}, months: {{ $months }}, weekStartsOn: {{ $wkStart }}, range: {{ $range ? 'true' : 'false' }}, locale: {{ $calendarLocale }}, labelLocale: {{ $labelLocale }}, todayLabel: {{ $todayLiteral }}, unavailableLabel: {{ $unavailableLiteral }}, todayButtonLabel: {{ $todayButtonLiteral }}, openTo: {{ $openToLiteral }}, fixedWeeks: {{ $fixedWeeks ? 'true' : 'false' }}, presets: {{ $presetsLiteral }}, disabled: {{ $disabled ? 'true' : 'false' }}, readonly: {{ $readonly ? 'true' : 'false' }} })"
    {{-- `name` belongs to the hidden fields below, which render it; on this div it is not a valid
         attribute, and a `[name="…"]` lookup would find the calendar before its field. --}}
    {{ $attributes->except('name')->whereDoesntStartWith('wire:model')->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$classes])) }} data-wk-sr-host
    {{-- Named by the caller, the calendar is a group of its months, each of which keeps its own name. --}}
    @if(($callerLabelledBy || $callerLabel) && ! $attributes->has('role')) role="group" @endif
>
    {{-- Hidden input for form submission --}}
    {{-- Static value as well as the bound one: the field is empty until Alpine boots, and a form submitted in that window sends nothing while the visible control already shows the value. Both come from the same PHP expression that feeds the factory, so they cannot drift. --}}
    {{-- In range mode this field keeps the combined value for wire:model and carries no name:
         the form receives the two ends below instead. A name sent both as a string and as an
         array reaches PHP as the array alone, so a combined field of the same name never arrived. --}}
    {{-- `wire:model` binds here, on the field that carries the value, and not on the root above.
         Livewire 4 adds `.self` to it, so a binding on the root heard only events dispatched on the
         root itself and dropped the `input` this field sends: the property never changed. Alpine's
         own `x-model` has no `.self` and stays on the root. --}}
    <input type="hidden" @unless($range) name="{{ $name }}" @endunless x-ref="hiddenInput" value="{{ $value }}" :value="rangeValue" @disabled($disabled) {{ $attributes->whereStartsWith('wire:model') }} @if($formOwner) form="{{ $formOwner }}" @endif />
    @if($range)
        {{-- The two ends as named fields, matching date-picker's `name[start]` / `name[end]`. --}}
        <input type="hidden" name="{{ $name }}[start]" x-ref="hiddenStart" value="{{ $rangeStart }}" :value="rangeStartValue" @disabled($disabled) @if($formOwner) form="{{ $formOwner }}" @endif />
        <input type="hidden" name="{{ $name }}[end]" x-ref="hiddenEnd" value="{{ $rangeEnd }}" :value="rangeEndValue" @disabled($disabled) @if($formOwner) form="{{ $formOwner }}" @endif />
        {{-- The preset the range was chosen by, empty for one picked in the grid, so a form that
             reads `name[preset]` keeps "last 7 days" apart from the seven days it covered today. --}}
        @if($presetKeys !== [])
            <input type="hidden" name="{{ $name }}[preset]" x-ref="hiddenPreset" value="{{ $namedPreset }}" :value="presetValue" @disabled($disabled) @if($formOwner) form="{{ $formOwner }}" @endif />
        @endif
    @endif
    {{-- The browser validates no hidden field, so a required calendar stops an empty submit
         through a stand-in of its own. --}}
    @if($required)
        @include('wirekit::components.partials.required-check', ['requiredFormOwner' => $formOwner, 'requiredDisabled' => $disabled || $readonly])
    @endif

    @if($optimisticConfig)
        {{-- `display: contents` so the grid and header keep their place in this
             element's layout — an extra box between them and it would change the
             calendar's spacing without changing a class. --}}
        <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
    @endif

    @if($presetKeys !== [])
        {{-- The presets stand beside the calendar on a wide screen and above it on a narrow one
             (partials/calendar-presets); a calendar without them keeps its own layout. In the modal
             of a picker on a phone they stand above it on every screen, the modal being narrow
             where a phone held sideways is not (dist/wirekit.css). --}}
        <div data-wk-calendar-presets-layout class="flex flex-col gap-[var(--gap-wk-md)] sm:flex-row sm:items-start">
            @include('wirekit::components.partials.calendar-presets', ['presetListLabelId' => $idBase.'-presets-label'])
            <div class="min-w-0 flex-1">
    @endif

    {{-- Month navigation header. Flush, as a dialog holds it, the month stands at the start and the
         arrows together at the end; the arrow back follows the month in the document too, so the
         order of Tab is the order a reader sees. --}}
    <div class="{{ $headerClasses }}">
        @unless($flush)
            <button type="button" @disabled($disabled) x-on:click="prevMonth()" :aria-disabled="prevDisabled" class="wk-touch-target {{ $navBtnClasses }}" aria-label="{{ __('wirekit::Previous month') }}">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                </svg>
            </button>
        @endunless

        @if($selectableHeader)
            {{-- Native month + year selects: full keyboard + AT support for free,
                 bound straight to the view state so changing them re-renders the
                 grid(s). aria-live mirror keeps the change announced. The year wraps under the
                 month where the header is narrower than the two side by side: on a touch screen
                 both take the 16px text and the 44px target, which a phone column does not hold
                 beside the two arrows. --}}
            <div class="flex flex-wrap items-center justify-center gap-[var(--padding-wk-x-sm)]">
                <label class="sr-only" for="{{ $monthSelectId }}">{{ __('wirekit::Month') }}</label>
                <div class="relative">
                    <select id="{{ $monthSelectId }}" @disabled($disabled) x-model.number="viewMonth" aria-label="{{ __('wirekit::Month') }}" class="wk-field {{ $headerSelectClasses }}">
                        {{-- Same shape as the year `<select>` beside it, and for the same
                             reason the month heading is built in JavaScript: a list written
                             out here can only be written in one language, while the label
                             this control drives is already spelling those months in the
                             reader's. --}}
                        <template x-for="(monthName, monthIndex) in monthNames" :key="monthIndex">
                            <option :value="monthIndex" x-text="monthName"@if($datesLang) lang="{{ $datesLang }}"@endif></option>
                        </template>
                    </select>
                    {{-- Chevron overlay — same glyph + token color as the select component. --}}
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                        <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                <label class="sr-only" for="{{ $yearSelectId }}">{{ __('wirekit::Year') }}</label>
                <div class="relative">
                    <select id="{{ $yearSelectId }}" @disabled($disabled) x-model.number="viewYear" aria-label="{{ __('wirekit::Year') }}" class="wk-field {{ $headerSelectClasses }}">
                        <template x-for="y in yearRange" :key="y">
                            <option :value="y" x-text="y"></option>
                        </template>
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                        <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
                        </svg>
                    </div>
                </div>
                {{-- The id is single-month only. With `months > 1` the per-month heading below already
                     carries `monthLabelId(0)`, and a second element with that id makes the first
                     grid's `aria-labelledby` resolve to whichever comes first in the document —
                     the header, not its own month. Two elements, one id, and the wrong one wins. --}}
                <span @if($months <= 1) :id="monthLabelId(0)" @endif class="sr-only" aria-live="polite" x-text="monthLabel"@if($datesLang) lang="{{ $datesLang }}"@endif></span>
            </div>
        @else
            {{-- With several grids in view each grid names its own month, so this one hides from the
                 eye and stays the region that announces a change. It follows the months the script
                 shows rather than the prop: a dialog on a phone asks for one grid of a calendar
                 rendered with several, and there this is the only name in sight. --}}
            <span @if($months <= 1) :id="monthLabelId(0)" @else :class="{ 'sr-only': months > 1 }" @endif class="font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-md)]" x-text="monthLabel" aria-live="polite"@if($datesLang) lang="{{ $datesLang }}"@endif></span>
        @endif

        @if($flush)
            {{-- Side by side, each arrow takes the 44px as its size on a touch screen (`wk-touch-row`),
                 or each hit area would cover the other. `ms-auto` keeps them at the end when the month
                 beside them hides because each grid names its own. --}}
            <div class="ms-auto flex items-center gap-[var(--gap-wk-xs)]">
                <button type="button" @disabled($disabled) x-on:click="prevMonth()" :aria-disabled="prevDisabled" class="wk-touch-target wk-touch-row {{ $navBtnClasses }}" aria-label="{{ __('wirekit::Previous month') }}">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
        @endif
        @if($withToday)
            {{-- The today button beside the arrow to the next month, as one group, so the month
                 stays between the two arrows of a calendar that is not flush. Its face is a calendar sheet with today's day on it,
                 and its name says the date ("Today, October 22"), which the face shows only in part.
                 It closes as the arrows close, while today's month lies wholly outside the limits,
                 and its pick goes the way a day's does, through the optimistic layer where there is one.
                 On a touch screen the two side by side take the 44px as their size (`wk-touch-row`):
                 the hit area of the next month's arrow would otherwise cover this button. --}}
            @unless($flush)
            <div class="flex items-center gap-[var(--gap-wk-xs)]">
            @endunless
                <button type="button" @disabled($disabled) x-on:click="goToday() && {{ $optimisticConfig ? 'run' : 'selectDate' }}(todayDate)" :aria-disabled="todayDisabled" class="wk-touch-target wk-touch-row {{ $navBtnClasses }}" aria-label="{{ __('wirekit::Today') }}" :aria-label="todayButtonLabel">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 7.5h16.5M5.25 5.25h13.5a1.5 1.5 0 0 1 1.5 1.5v12a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5v-12a1.5 1.5 0 0 1 1.5-1.5Z" />
                        <text x="12" y="17.25" text-anchor="middle" font-size="8" font-weight="600" fill="currentColor" stroke="none" x-text="todayDayOfMonth">{{ now()->day }}</text>
                    </svg>
                </button>
        @endif
        <button type="button" @disabled($disabled) x-on:click="nextMonth()" :aria-disabled="nextDisabled" @class(['wk-touch-target', 'wk-touch-row' => $withToday || $flush, $navBtnClasses]) aria-label="{{ __('wirekit::Next month') }}">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
            </svg>
        </button>
        @if($withToday || $flush)
            </div>
        @endif
    </div>

    @if($months > 1)
        {{-- Multi-month: N grids side by side. Each grid carries data-wk-month so
             the keyboard model (focusOffset) can scope focus to the right grid.
             The shared keydown handler lives on the row wrapper. --}}
        <div class="flex flex-wrap gap-[var(--gap-wk-md)]" @keydown="handleKeydown($event)">
            <template x-for="month in monthsView" :key="month.offset">
                {{-- Each month grows with the calendar, so in a container that stretches it the grids
                     share its width side by side and take a whole line each once they wrap, as one
                     month fills it. A container that fits its content leaves nothing to grow into. --}}
                <div class="grow" :data-wk-month="month.offset">
                    {{-- The month heading names its own grid. Four grids side by side
                         with no accessible name between them are four anonymous
                         tables to a screen reader, which is exactly the case where
                         knowing which month you are in matters most. With one grid in
                         view, as in a picker's dialog on a phone, the header above names
                         that month, so this one stays a name for the grid and hides from
                         the eye. Flush, it starts where the header's month starts. --}}
                    <div :id="monthLabelId(month.offset)"@if($datesLang) lang="{{ $datesLang }}"@endif class="{{ $flush ? 'text-start' : 'text-center' }} mb-[var(--padding-wk-y-sm)] font-[number:var(--font-wk-heading-weight)] text-[length:var(--text-wk-sm)]" :class="{ 'sr-only': months === 1 }" x-text="month.label"></div>
                    <table data-wk-prose-skip role="grid" @if($disabled) aria-disabled="true" @endif @if($readonly) aria-readonly="true" @endif class="w-full border-collapse" :aria-labelledby="monthLabelId(month.offset)"
                        @if($required) aria-required="true" aria-describedby="{{ $requiredMessageId }}" x-bind:aria-invalid="requiredMessage !== '' && requiredValue === '' ? 'true' : null" @endif>
                        <thead>
                            <tr>
                                @if($weekNumbers)
                                    {{-- The week numbers' column: "#" to the eye, its name to a screen reader. --}}
                                    <th data-wk-prose-skip class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col"><span aria-hidden="true">#</span><span class="sr-only">{{ __('wirekit::Week number') }}</span></th>
                                @endif
                                @foreach($weekdays as $day)
                                    {{-- Static names as well as the bound ones, the same way the hidden
                                         input above carries both: the seed keeps the header row readable
                                         before Alpine boots, in the page's language, and `weekdayHeaders`
                                         writes the same words once it runs. `abbr` is the spelling the
                                         WAI-ARIA date-picker example uses to give a column its full name —
                                         the short form is what fits in the cell, not what a screen reader
                                         should have to work from. --}}
                                    <th data-wk-prose-skip class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col" abbr="{{ $day['long'] }}"@if($datesLang) lang="{{ $datesLang }}"@endif :abbr="weekdayHeaders[{{ $loop->index }}].long" x-text="weekdayHeaders[{{ $loop->index }}].short">{{ $day['short'] }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(week, weekIdx) in weeksOf(month.days)" :key="weekIdx">
                                <tr role="row">
                                    @if($weekNumbers)
                                        {{-- A row header, so a screen reader names the week with each day it reads. --}}
                                        <th data-wk-prose-skip scope="row" class="text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)] tabular-nums" x-text="weekNumber(week)"></th>
                                    @endif
                                    <template x-for="day in week" :key="day.date">
                                        <td data-wk-prose-skip role="gridcell" class="p-0.5 text-center" :aria-selected="(day.isSelected || day.isInRange || day.isProvisionalEnd) ? 'true' : 'false'" :data-wk-band="day.band">
                                            <button
                                                type="button"
                                                x-on:click="!locked && day.isCurrentMonth && !day.isClosed && {{ $optimisticConfig ? 'run' : 'selectDate' }}(day.date)"
                                                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                                                {{-- The visible text is the day number alone; the name is the
                                                     whole date. See `_dayLabel` for why. --}}
                                                :aria-label="day.label"
                                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay && month.offset === focusOffset ? '0' : '-1'"
                                                x-on:focus="followFocus(day, month.offset)"
                                                :disabled="!day.isCurrentMonth || disabled"
                                                :aria-disabled="day.ariaDisabled"
                                                class="wk-calendar-day {{ $dayBtnClasses }}"
                                                x-on:mouseenter="hoverDay(day.date)"
                                                :data-wk-in-range="day.rangeMarker"
                                                :class="{
                                                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]': day.isSelected,
                                                    'font-[number:var(--font-wk-heading-weight)]': day.isToday && !day.isSelected,
                                                    'cursor-pointer hover:bg-[var(--color-wk-bg-hover)]': !locked && day.isCurrentMonth && !day.isClosed && !day.isSelected && !day.isInRange && !day.isProvisionalEnd,
                                                    'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed': day.isCurrentMonth && day.isClosed && !day.isSelected,
                                                    'text-[color:var(--color-wk-text-muted)] opacity-40 cursor-default': !day.isCurrentMonth,
                                                    'cursor-pointer': !locked && day.isCurrentMonth && day.isSelected,
                                                    'opacity-[var(--opacity-wk-disabled)] cursor-not-allowed': disabled && day.isCurrentMonth,
                                                }"
                                            >
                                                <span x-text="day.dayOfMonth"></span>
                                                {{-- Today is a dot under the number, inverted on a chosen day, where a ring would compete with the fill. --}}
                                                <span x-show="day.isToday" aria-hidden="true" class="absolute inset-x-0 bottom-[var(--padding-wk-y-xs)] mx-auto h-1.5 w-1.5 rounded-full" :class="day.isSelected ? 'bg-[var(--color-wk-accent-fg)]' : 'bg-[var(--color-wk-accent-text)]'"></span>
                                            </button>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                        @include('wirekit::components.partials.calendar-sizing-row', ['weekNumbers' => $weekNumbers, 'size' => $size])
                    </table>
                </div>
            </template>
        </div>
    @else
    {{-- Calendar grid. Collapsed, so no spacing opens between two cells and the band of a range
         runs on from one to the next, also where the page leaves out Tailwind's preflight. --}}
    <table data-wk-prose-skip role="grid" @if($disabled) aria-disabled="true" @endif @if($readonly) aria-readonly="true" @endif class="w-full border-collapse" :aria-labelledby="monthLabelId(0)" @keydown="handleKeydown($event)"
        @if($required) aria-required="true" aria-describedby="{{ $requiredMessageId }}" x-bind:aria-invalid="requiredMessage !== '' && requiredValue === '' ? 'true' : null" @endif>
        <thead>
            <tr>
                @if($weekNumbers)
                    {{-- The week numbers' column: "#" to the eye, its name to a screen reader. --}}
                    <th data-wk-prose-skip class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col"><span aria-hidden="true">#</span><span class="sr-only">{{ __('wirekit::Week number') }}</span></th>
                @endif
                @foreach($weekdays as $day)
                    {{-- See the multi-month header above for why the abbreviation is
                         written twice and what `abbr` is doing here. --}}
                    <th data-wk-prose-skip class="py-[var(--padding-wk-y-xs)] text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)]" scope="col" abbr="{{ $day['long'] }}"@if($datesLang) lang="{{ $datesLang }}"@endif :abbr="weekdayHeaders[{{ $loop->index }}].long" x-text="weekdayHeaders[{{ $loop->index }}].short">{{ $day['short'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <template x-for="(week, weekIdx) in weeksOf(days)" :key="weekIdx">
                <tr role="row">
                    @if($weekNumbers)
                        {{-- A row header, so a screen reader names the week with each day it reads. --}}
                        <th data-wk-prose-skip scope="row" class="text-center text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)] tabular-nums" x-text="weekNumber(week)"></th>
                    @endif
                    <template x-for="day in week" :key="day.date">
                        {{-- aria-selected lives on the gridcell, NOT the button.
                             Per the WAI-ARIA grid pattern, `aria-selected` is
                             only allowed on gridcell/option/row/rowheader/tab/
                             treeitem roles — placing it on a <button> fails
                             axe-core's aria-allowed-attr (critical). The cell, not the
                             button, also draws the band of a range: drawn across the whole
                             cell, it meets the band of the next cell, which a button inside
                             the cell's padding cannot reach (dist/wirekit.css). --}}
                        <td data-wk-prose-skip role="gridcell" class="p-0.5 text-center" :aria-selected="(day.isSelected || day.isInRange || day.isProvisionalEnd) ? 'true' : 'false'" :data-wk-band="day.band">
                            <button
                                type="button"
                                x-on:click="!locked && day.isCurrentMonth && !day.isClosed && {{ $optimisticConfig ? 'run' : 'selectDate' }}(day.date)"
                                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                                {{-- The visible text is the day number alone; the name is the
                                     whole date. See `_dayLabel` for why. --}}
                                :aria-label="day.label"
                                :data-wk-day="day.isCurrentMonth ? day.dayOfMonth : null"
                                :tabindex="day.isCurrentMonth && day.dayOfMonth === focusedDay ? '0' : '-1'"
                                x-on:focus="followFocus(day, 0)"
                                :disabled="!day.isCurrentMonth || disabled"
                                :aria-disabled="day.ariaDisabled"
                                class="wk-calendar-day {{ $dayBtnClasses }}"
                                {{-- The in-between days get a TINT, not the accent, so the two ends
                                     stay what the eye lands on. --}}
                                x-on:mouseenter="hoverDay(day.date)"
                                :data-wk-in-range="day.rangeMarker"
                                :class="{
                                    'bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)]': day.isSelected,
                                    'font-[number:var(--font-wk-heading-weight)]': day.isToday && !day.isSelected,
                                    'cursor-pointer hover:bg-[var(--color-wk-bg-hover)]': !locked && day.isCurrentMonth && !day.isClosed && !day.isSelected && !day.isInRange && !day.isProvisionalEnd,
                                    'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed': day.isCurrentMonth && day.isClosed && !day.isSelected,
                                    'text-[color:var(--color-wk-text-muted)] opacity-40 cursor-default': !day.isCurrentMonth,
                                    'cursor-pointer': !locked && day.isCurrentMonth && day.isSelected,
                                    'opacity-[var(--opacity-wk-disabled)] cursor-not-allowed': disabled && day.isCurrentMonth,
                                }"
                            >
                                <span x-text="day.dayOfMonth"></span>
                                {{-- Today is a dot under the number, inverted on a chosen day, where a ring would compete with the fill. --}}
                                <span x-show="day.isToday" aria-hidden="true" class="absolute inset-x-0 bottom-[var(--padding-wk-y-xs)] mx-auto h-1.5 w-1.5 rounded-full" :class="day.isSelected ? 'bg-[var(--color-wk-accent-fg)]' : 'bg-[var(--color-wk-accent-text)]'"></span>
                            </button>
                        </td>
                    </template>
                </tr>
            </template>
        </tbody>
        @include('wirekit::components.partials.calendar-sizing-row', ['weekNumbers' => $weekNumbers, 'size' => $size])
    </table>
    @endif

    @if($presetKeys !== [])
            </div>
        </div>
    @endif

    @if($optimisticConfig)
        {{-- Outside the grid — a live region is not a gridcell — and inside the
             optimistic scope. Rendered unconditionally and starting empty: a
             region that arrives together with its text is a new node, and
             nothing is announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
        </div>
    @endif

    @if($requiredMessageId)
        @include('wirekit::components.partials.required-message', ['requiredMessageId' => $requiredMessageId])
    @endif
</div>
