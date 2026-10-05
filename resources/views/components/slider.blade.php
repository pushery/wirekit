{{-- optimistic-ui: supported
     Single-handle, over a native `<input type="range">`, so the commit boundary
     is the control's own event rather than a pointerup handler this code owns.
     A native range input fires:

       a five-move drag  → 5 × input, then exactly one change, at release
       one arrow key     → 1 × input, then change, immediately
       two arrow keys    → one input+change pair EACH, no coalescing
       blur afterwards   → nothing further

     So `change` is the boundary for both input modes at once, and it needs no
     special case: it fires once at the end of a drag, and once per keypress
     because one press is already a finished decision.

     The mirror moves DURING the gesture (`@input="current = ..."`), so this
     marks the gesture's start — otherwise the baseline would already be the
     value being committed and a refusal would roll back onto itself. `after`
     pushes the mirror back onto the element, because the element owns its value
     and a rollback that moved only the mirror would leave the thumb where the
     server refused to put it. --}}
@props([
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk. StrictnessGate did not
    // complain either, because `required` is in its HTML passthrough list — so it looked like
    // a legitimate attribute all the way down. The result was a required field that submits
    // empty, in the same form as a plain input that behaves correctly.
    'required' => false,
    // The Livewire method to call when the slider should show the new value
    // before the server has agreed to it. The value is sent when the gesture
    // ends — see the note above. Null leaves the component exactly as it has
    // always rendered.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    'name' => null,
    'id' => null,
    'label' => null,
    // `error` was undeclared while `label` was not, so `:error` on this control
    // landed in the attribute bag and rendered as a stray HTML attribute — a validation
    // message the developer wrote, silently not shown, on a control that is part of forms.
    'error' => null,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out —
    // an app that runs its OWN error summary would otherwise double-announce here.
    'announceError' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    'min' => config('wirekit.components.slider.min', 0),
    'max' => config('wirekit.components.slider.max', 100),
    'step' => config('wirekit.components.slider.step', 1),
    'value' => null,
    'size' => config('wirekit.components.slider.size', 'md'),
    'showValue' => false,
    // Where the shown value sits: `end`, beside the track as it always has, or `below`, on its
    // own centered line under the track. `below` keeps every track of a form the same length:
    // beside the track each slider reserves room for its own widest value, so five sliders
    // with five different value words drew five different track lengths.
    'valuePosition' => 'end',
    // Step marks: a list of values (`[0, 25, 50, 75, 100]`) for plain ticks, or a
    // value => label map (`[0 => 'Low', 100 => 'High']`) for labeled ticks.
    'marks' => [],
    // Decouple the announced aria-valuetext from the visual tick labels: an explicit
    // value => spoken-text map lets you show NUMERIC ticks but announce semantic meaning
    // (e.g. [1 => 'Low', 5 => 'High']). Falls back to the `marks` labels.
    'valueTextMap' => null,
    // Show a value bubble above the thumb that follows it as the user drags.
    'tooltip' => false,
    'disabled' => false,
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkField' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkField', 'wk-field']);

    // A range field gives its value as text, and Livewire takes the server's echo of an `int` or
    // `float` property for a change and writes it over a newer value: a binding to one gets
    // `.number` and sends a number (Support\NumericModel).
    $attributes = \Pushery\WireKit\Support\NumericModel::number($attributes);

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    /*
     * The Laravel validation bag, which this control never consulted. Every other form
     * control resolves `error` from `$error ?? FieldError::first($errors, $name)`, so after a failed
     * `$this->validate()` each of them showed its message and this one showed nothing — in
     * the same form, on the same submit. The explicit prop still wins; the bag is the
     * fallback, exactly as in input.blade.php.
     *
     * Guarded on `$name`, and that guard is not defensive noise. `$errors->first(null)`
     * returns the first error in the bag whatever its key, so a slider with no name would
     * render `aria-invalid="true"` because some other field failed validation, and announce
     * an error message about that field. The sibling controls write it the same way.
     */
    $error ??= $name ? \Pushery\WireKit\Support\FieldError::first($errors ?? null, $name) : null;
    // Whether a message line renders below the control: the error, or the hint when there is none.
    $hasMessage = (bool) $error || (bool) $hint;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showValue = BooleanProp::from($showValue, false);
    $valueBelow = match ($valuePosition) {
        'end' => false,
        'below' => true,
        default => \Pushery\WireKit\WireKit::validateProp('slider', 'valuePosition', (string) $valuePosition, ['end', 'below']) === 'below',
    };
    $valueBelow = $valueBelow && $showValue;
    $tooltip = BooleanProp::from($tooltip, false);
    $disabled = BooleanProp::from($disabled, false);
    $required = BooleanProp::from($required, false);

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('slider', $attributes->getAttributes());

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['@input']);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Slider = styled HTML <input type="range">. Native element gives us
    // arrow-key support, drag handling, and accessibility for free; we only
    // need to style the track + thumb via CSS variables.
    //
    // The id is the same on every render: Livewire's morph recognizes an element by it, and an
    // id drawn fresh per render made every round trip REPLACE the range input, so a drag bound
    // with `wire:model.live` lost its thumb mid-gesture. Seeded from the bound property when
    // there is no name, counted per render order when there is neither. DomId keeps it unique on
    // the page like every other form control: a second slider with the same name gets `-2`, so
    // its label and its hint do not point at the first one.
    $sliderId = \Pushery\WireKit\Support\DomId::unique(
        $id ?? ($name ? 'wk-slider-' . $name : WireKit::stableId('wk-slider', $attributes->whereStartsWith('wire:model')->first())),
        'wk-slider-'
    );
    // One description list for the control: the component's own id first, then a caller's
    // aria-describedby. Written as separate attributes, the parser kept only the first copy,
    // so a caller's description was dropped or pushed the component's own out.
    $describedBy = trim(($error ? $sliderId.'-error' : ($hint ? $sliderId.'-hint' : '')).' '.((string) $attributes->get('aria-describedby', '')));
    // The field's help, after its own message: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered (partials/field-help).
    $helpId = filled($help) && filled($label) ? $sliderId.'-help' : null;
    $describedBy = trim($describedBy.' '.($helpId ?? ''));
    // Numbers, whatever the attribute or the data carried: a text would throw in the
    // arithmetic below. A bound or a step that holds no number takes its configured default,
    // and a value with none starts at the minimum.
    $min = \Pushery\WireKit\Support\NumericProp::from($min, \Pushery\WireKit\Support\NumericProp::from(config('wirekit.components.slider.min', 0), 0));
    $max = \Pushery\WireKit\Support\NumericProp::from($max, \Pushery\WireKit\Support\NumericProp::from(config('wirekit.components.slider.max', 100), 100));
    $step = \Pushery\WireKit\Support\NumericProp::positive($step, \Pushery\WireKit\Support\NumericProp::positive(config('wirekit.components.slider.step', 1), 1));
    $value = \Pushery\WireKit\Support\NumericProp::orNull($value);
    $currentValue = $value ?? $min;

    // Normalize marks to [['value'=>, 'label'=>, 'pct'=>], ...]. A LIST (`[0, 25, 50]`)
    // is positions-only (label = the number); a MAP (`[0 => 'Low', 100 => 'High']`)
    // uses the key as the position and the value as the label. pct positions each
    // tick along the track: (value − min) / (max − min) × 100, clamped to 0–100.
    $range = ($max - $min) ?: 1;
    $normalizedMarks = [];
    // Initialized beside the marks it describes, so the reader below does not depend on
    // `! empty($normalizedMarks) &&` short-circuiting to avoid an undefined variable.
    // Marks may arrive as a Collection; the shape test below takes an array.
    $marks = \Pushery\WireKit\Support\ListProp::records($marks);
    $marksIsList = true;
    $hasLabeledMarks = false;
    if (! empty($marks)) {
        // THREE accepted shapes, and the third is the reason this is not a simple
        // cast any more:
        //
        //   [0, 50, 100]                                  a plain list of positions
        //   [0 => 'Low', 100 => 'High']                   value => label
        //   [0 => ['label' => 'Low', 'description' => …]] value => label + meaning
        //
        // The third exists because a slider whose positions MEAN something is the
        // normal case once the values are not numbers — five steps from -2 to +2,
        // each standing for a policy. Before it, a reader saw only the numbers and
        // had to MOVE the slider to learn what a position means, which is the one
        // thing they were trying to find out before changing it.
        //
        // `valueTextMap` already solved the screen-reader half well. This is the
        // visual half: what gets announced was not readable without altering the
        // value.
        // `array_is_list` alone is not the list/map question, and reading it as if it were
        // CRASHED on a legitimate call. `[0 => ['description' => …], 1 => [...]]` is a map
        // whose positions happen to be 0 and 1 — a slider from 0 to 1 with a meaning at each
        // end — and PHP cannot tell that from a list. The list branch then cast a spec array
        // to string: "Array to string conversion", a fatal on markup that is documented as
        // supported. Found by a test written for a different gap.
        //
        // A list element is a POSITION, never a spec, so an array element settles it: the
        // caller wrote a map and PHP's key numbering is a coincidence.
        //
        // And a position is a number, which the array test alone does not say. A label map
        // written the most natural way for a small ordered scale — `[0 => 'Neutral', 1 =>
        // 'Ja', 2 => 'Hoch']` — has contiguous keys from zero, so `array_is_list()` calls it a
        // list, and the branch below would read `'Neutral'` as a position and subtract the
        // minimum from it: a TypeError, so an HTTP 500 rather than a misplaced mark.
        //
        // The neighboring shapes do not reach it: `[0 => 'Low', 50 => 'Mid']` has gaps,
        // `[1 => 'A', 2 => 'B']` does not start at zero, and an array spec is caught above —
        // only a zero-based contiguous label map reaches it.
        $marksIsList = array_is_list($marks)
            && ! collect($marks)->contains(fn ($m) => is_array($m))
            && ! collect($marks)->contains(fn ($m) => ! is_numeric($m));

        $pairs = $marksIsList
            ? array_map(fn ($v) => [$v, (string) $v, null], $marks)
            : array_map(
                fn ($v, $spec) => is_array($spec)
                    ? [$v, (string) ($spec['label'] ?? $v), ($spec['description'] ?? null) !== null ? (string) $spec['description'] : null]
                    : [$v, (string) $spec, null],
                array_keys($marks),
                array_values($marks)
            );

        foreach ($pairs as [$mValue, $mLabel, $mDescription]) {
            $pct = max(0, min(100, (($mValue - $min) / $range) * 100));
            $normalizedMarks[] = [
                'value' => $mValue,
                'label' => $mLabel,
                'pct' => $pct,
                'description' => $mDescription,
            ];
            $hasLabeledMarks = $hasLabeledMarks || $mLabel !== '';
        }
    }
    // The track overlay (tooltip bubble + tick marks) needs a relative container.
    $hasTrackOverlay = $tooltip || ! empty($normalizedMarks);

    // Value → semantic-text map. When `marks` is a labeled MAP
    // (`[0 => 'Low', 100 => 'High']`, i.e. NOT a plain list), the labels carry
    // meaning a screen reader must hear — otherwise the native range input
    // announces only the bare number ("0"), while sighted users read "Low" off
    // the ticks. Build a string-keyed map (the Alpine `current` value is always a
    // string) so the slider can announce the label via aria-valuetext and echo
    // it in the tooltip / value display. A plain list of positions carries no
    // extra meaning, so it does NOT get aria-valuetext — the number IS the value,
    // and the DOM stays byte-identical to before.
    // A caller-supplied aria-valuetext binding wins over our own (mirrors the v2.8.0
    // aria-label precedence rule). An explicit `valueTextMap` prop decouples the spoken
    // text from the visual ticks entirely.
    $callerBindsValueText = $attributes->has('aria-valuetext')
        || $attributes->has('x-bind:aria-valuetext')
        || $attributes->has(':aria-valuetext');
    $valueTextMap = \Pushery\WireKit\Support\ListProp::from($valueTextMap);
    $explicitValueTextMap = is_array($valueTextMap) && $valueTextMap !== [] ? $valueTextMap : null;

    // A marks MAP opts into aria-valuetext ONLY when a label carries meaning beyond the
    // number (a numeric-label map — [-2 => '-2', …] — stays byte-identical to a plain
    // slider: the number already IS the value).
    // Reads the NORMALIZED marks, not the raw prop. Casting the raw value with
    // `(string)` works for a mark that is a string and throws "Array to string
    // conversion" the moment one is a spec array. Reading
    // the normalized form means the shape is handled in exactly one place — the
    // normalizer above — rather than in every reader of `$marks`.
    // A DESCRIPTION counts as semantic content, not only a label that differs from the
    // value. Without the second clause a map whose marks carry descriptions and no labels
    // binds no `aria-valuetext` at all, so the descriptions reach nothing — while the docs
    // say a mark may carry a description with no label and that the description is what the
    // slider announces. Both cannot be true, and the docs describe the intent.
    $isLabeledMarkMap = ! empty($normalizedMarks) && ! $marksIsList
        && collect($normalizedMarks)->contains(
            fn ($m) => $m['label'] !== (string) $m['value'] || $m['description'] !== null
        );

    $valueTextMap = [];
    if ($explicitValueTextMap !== null) {
        foreach ($explicitValueTextMap as $mValue => $mLabel) {
            $valueTextMap[(string) $mValue] = (string) $mLabel;
        }
    } elseif ($isLabeledMarkMap) {
        foreach ($normalizedMarks as $m) {
            // The DESCRIPTION wins over the label when both exist, and that is
            // the point of the third shape: the label is what a sighted reader
            // sees on the tick ("−2"), the description is what the position
            // MEANS ("Single verdict"). A screen reader should hear the meaning.
            $valueTextMap[(string) $m['value']] = $m['description'] ?? $m['label'];
        }
    }

    // Bind aria-valuetext when we have a semantic map AND the caller didn't bind it.
    $bindValueText = ($explicitValueTextMap !== null || $isLabeledMarkMap) && ! $callerBindsValueText;

    // Track height per size token.
    $trackHeight = match ($size) {
        'sm' => 'h-1',
        'lg' => 'h-3',
        default => 'h-2',
    };

    // Wrapper gives us space for the thumb's vertical overflow — and reserves
    // IN-FLOW space for the out-of-flow overlays, so the component never
    // requires the caller to hand-pad around it: the tooltip bubble floats
    // `bottom-full` (pt-7 ≈ bubble + gap) and the tick marks hang `top-full`
    // (pb-6 with labels, pb-2 ticks-only). Without the reservation the bubble
    // clips inside overflow-hidden ancestors and labeled marks overlap the
    // content below. A `min(16rem, 100%)` minimum width is the same usability floor as
    // range-slider: in any shrink-to-fit context (flex/grid auto item, table
    // cell, fit-content wrapper) a w-full track has no intrinsic width and
    // collapses to a few px — far too narrow to drag.
    $wrapperClasses = WireKit::resolveClasses('slider', 'wrapper', implode(' ', array_filter([
        // With the value below, a grid rather than the row: the label keeps its column and the
        // value gets a line of its own under the TRACK. A wrapping row would center the value
        // under the label and the track together, half a label away from the track's middle.
        // One column when no label is shown, or an empty first column would still cost a gap.
        // The gap is a gap token and runs between the rows too, so the value line keeps the same
        // distance from whatever sits above it: the track, or the marks under the track.
        $valueBelow
            ? 'grid items-center gap-[var(--gap-wk-sm)] w-full '.($label ? 'grid-cols-[auto_minmax(0,1fr)]' : 'grid-cols-[minmax(0,1fr)]')
            : 'flex items-center gap-[var(--padding-wk-x-sm)] w-full',
        // `min(16rem, 100%)`, not a bare `16rem`. The floor keeps a shrink-to-fit
        // context (a flex or grid auto item, a table cell, a fit-content wrapper) from
        // collapsing the track to a few unusable pixels, but `min-width` is a hard floor,
        // so a bare value does not shrink to a narrower parent: in a column roughly 280px
        // wide, 256px of track plus 32px of card padding would push past its own container.
        // `min()` gives 16rem where there is room, the parent's width where there is not.
        //
        // (The same comment also argued for a 20rem floor while shipping 16rem. Whichever
        // of the two was meant, one of them was wrong in the source of truth; 16rem is what
        // shipped and what every preview is drawn against, so that is what stays.)
        'min-w-[min(16rem,100%)]',
        $tooltip ? 'pt-7' : '',
        // With the value below, the value's own line takes the room the marks hang into (see
        // $valueClasses), so the wrapper reserves nothing under the track itself. A message
        // takes that room too: the row wraps, the message is a line of its own (`basis-full`),
        // and it starts under the marks. Without the wrap it was a fourth item of the row,
        // beside the value, and took its width from the track.
        $valueBelow ? '' : ($hasMessage ? 'flex-wrap gap-y-[var(--gap-wk-xs)]' : ($hasLabeledMarks ? 'pb-6' : (! empty($normalizedMarks) ? 'pb-2' : ''))),
    ])), $scope);

    // The native input — we make the thumb and track visible via `wk-slider`
    // utility class (see wirekit.css). Uses accent color for the fill.
    $inputClasses = 'wk-slider '.WireKit::resolveClasses('slider', 'input', implode(' ', [
        // Inside a track overlay (tooltip / marks) the input fills its relative
        // container; otherwise it flexes directly in the wrapper row, or fills its grid
        // column when the value sits below (`flex-1` means nothing to a grid item).
        ($hasTrackOverlay || $valueBelow) ? 'w-full' : 'flex-1',
        'appearance-none',
        'bg-transparent',
        'cursor-pointer',
        'focus-visible:outline-hidden',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        $trackHeight,
    ]), $scope);

    // Live value display next to the slider.
    /*
     * THE WIDEST TEXT THIS BOX WILL EVER SHOW, so it can reserve that width once and stop
     * resizing while the thumb is held.
     *
     * The value span is a SIBLING of the track inside a `w-full` flex row, so the track's
     * width is the row minus the gap minus this box. Anything that changes this box's width
     * moves the track — under the pointer that is dragging it. With `valueTextMap` the text
     * is a word, and the width changes with every step.
     *
     * And it is not only the word case, which is why the reservation is computed rather
     * than switched on a prop: on a plain numeric scale from 0 to 100, a two-and-a-half
     * character minimum covers two digits, and the third would still move the track.
     *
     * That floor is described in words on purpose, and so is the numeric variant a few
     * lines down: Tailwind scans this directory as raw text, so a utility spelled out in a
     * comment would be compiled into a selector no element uses.
     *
     * `valueText` is `marksMap[current] ?? String(current)`, so the candidate set is exactly
     * the map's labels plus the numeric ends. The ghost below renders the longest of them
     * invisibly in the same grid cell, which sizes the cell to the real rendered width — in
     * the real font, rather than in `ch` units that assume every glyph is as wide as a zero.
     */
    $wkStepDecimals = 0;
    if (is_string($step) || is_numeric($step)) {
        $wkStepString = rtrim(rtrim(sprintf('%.6F', (float) $step), '0'), '.');
        $wkStepDecimals = str_contains($wkStepString, '.') ? strlen(explode('.', $wkStepString)[1]) : 0;
    }

    $wkValueTextCandidates = array_map('strval', array_values($valueTextMap));
    foreach ([$min, $max] as $wkEnd) {
        $wkValueTextCandidates[] = number_format((float) $wkEnd, $wkStepDecimals, '.', '');
    }

    $widestValueText = '';
    foreach ($wkValueTextCandidates as $wkCandidate) {
        if (mb_strlen($wkCandidate) > mb_strlen($widestValueText)) {
            $widestValueText = $wkCandidate;
        }
    }

    $valueClasses = WireKit::resolveClasses('slider', 'value', implode(' ', array_filter([
        'tabular-nums',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
        // A one-cell grid: the ghost and the live text share it, so the cell is as wide as
        // the widest of them and never as narrow as whichever is showing.
        $valueBelow ? 'grid justify-items-center text-center' : 'grid justify-items-end text-end',
        // Below the track: the track's column, so the value centers under the track rather
        // than under the label beside it.
        $valueBelow && $label ? 'col-start-2' : '',
        // And the value line starts under the marks. They hang out of flow from the track, so
        // the grid row does not know they are there: a tick is 0.5rem under the track and a
        // labeled one adds a line of `xs` text, 1.75rem in all.
        $valueBelow ? ($hasLabeledMarks ? 'mt-7' : (! empty($normalizedMarks) ? 'mt-2' : '')) : '',
    ])), $scope);

    // Accessible-name fallback. WCAG 2.1 (4.1.2) — every form input must
    // have a programmatically-determinable name. When no visible `label`
    // prop is set AND no `aria-label` / `aria-labelledby` is passed via
    // attributes, derive a sr-only fallback from `name` (humanized).
    $hasExplicitAriaName = $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $needsSrOnlyFallback = ! $label && ! $hasExplicitAriaName;
    $fallbackLabel = $name ? Str::headline((string) $name) : __('wirekit::Slider');

    // Inside a labeled field the field's label names the slider by `for`, where the hidden one
    // made from the name would (Support\FieldControl).
    $fieldLabelId = $needsSrOnlyFallback && $wkField instanceof \Pushery\WireKit\Support\FieldControl
        ? $wkField->takeLabel($sliderId)
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

    // `bind` rather than `value`: `current` already exists on the component this
    // layer nests inside, so binding to it keeps ONE truth for the value.
    //
    // `after` is what keeps the ELEMENT in step. The mirror is deliberately not
    // bound onto the input, so a write that moved only `current` would leave the
    // thumb where the server refused to put it.
    //
    // The default `undo` exit is right here — a slider value is a choice, not
    // typed work, so putting it back costs the user nothing.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'current',
        'after' => 'syncToInput',
        // The field's own error region. Without it the layer's generic "Could not save"
        // is the only thing a listener hears, and it BEATS the specific message the server
        // sent — the whole point of the arbitration is that a specific message wins, and it
        // cannot run against a region nobody pointed at.
        'errorRegion' => \Pushery\WireKit\Support\CssIdentifier::idSelector($sliderId.'-error'),
        'action' => $optimistic,
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second commit while one is in flight would resolve by whichever
        // answer arrives last — network timing, which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);

    // A caller's `x-ref` belongs to the caller's component. The field sits in a root of ours,
    // which would take it, and it already carries our own `x-ref`, which a parser keeps over a
    // second one. The name moves to `x-wk-ref`, which registers the field on the root above
    // `data-wk-ref-scope` (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');
@endphp

{{-- Alpine tracks the current value so the display (and the tooltip bubble /
     fill) update on input. `pct` is the thumb position as a 0–100 percentage,
     which places the tooltip bubble over the thumb.

     `current` is a MIRROR of the input's own value, and it is kept honest in both
     directions. Written once at render and mutated only by @input, it would be
     correct only while the browser is the only thing that moves the thumb. With
     `wire:model`, a server-side change writes `el.value` directly and fires NO
     input event, so the mirror would keep the old number while the thumb showed
     the new one, and everything derived from the mirror (aria-valuetext, the
     tooltip, showValue, the fill) would announce a value the reader can no longer
     see. Assigning a property fires no event and
     mutates no attribute, so it is invisible to x-effect and to a
     MutationObserver alike (verified) — the one reliable signal is Livewire's own
     commit hook, which is exactly the moment the two can diverge. --}}
<div {{ $outerAttributes }}
    {{-- The mirror, the pct math and the announced text live in the factory
         (resources/js/components/slider.js). An inline object literal cannot
         carry methods or getters under Alpine's CSP build — it fails to parse,
         the element gets an empty scope, and every directive here goes quiet. --}}
    x-data="wirekitSlider({ current: {{ \Pushery\WireKit\Support\AlpinePayload::from((string) $currentValue) }}, min: {{ $min }}, max: {{ $max }}, marksMap: {{ \Pushery\WireKit\Support\AlpinePayload::from((object) $valueTextMap) }} })"
    x-modelable="current"
    x-init="initResync()"
    @if($callerRef !== '') data-wk-ref-scope @endif
    {{-- Caller layout attributes (class / style — e.g. a width constraint)
         bind to the WRAPPER, not the <input>. The tooltip bubble + tick marks
         are positioned `left: pct%` relative to the overlay container, which
         fills the wrapper; the <input> track also fills it (`w-full`). Routing
         a width override onto the input alone (the old path — $attributes lands
         on the input) made the input narrower than its overlay container, so
         the bubble's `pct%` resolved against the WIDER container and floated
         far to the side of the thumb. Sizing the wrapper keeps the input, the
         overlay container, and therefore the bubble + marks all at ONE width.
         Input-semantic attributes (wire:model, aria-*, data-*) still flow to
         the <input> below via except(['class','style']). --}}
    {{-- A parent's hand-written `x-model` answers `x-modelable` above, so it belongs HERE
         and not on the range input with the rest. On the input it still tracks a drag -- both
         sides listen to the same event -- but the other direction is silent: Alpine sets
         `el.value` without dispatching, so a programmatic change from the parent moved the thumb
         and left `current` behind, with the bubble and the tick marks reading the old number. --}}
    {{ $attributes->whereStartsWith('x-model')->whereDoesntStartWith('x-modelable') }}
    {{ $attributes->only(['class', 'style'])->class([$wrapperClasses]) }}
>
@if($optimisticConfig)
    {{-- The layer nests INSIDE the component that owns the mirror: a nested
         Alpine component reads and writes its parent's properties through
         `this` and never the reverse, so `bind: 'current'` only resolves this
         way round.

         `display: contents` because the wrapper is a flex row — a real box here
         would make the label, the track and the value display one flex item
         instead of three. --}}
    <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
    @if($label)
        {{-- With help, the label and its button are one item of the row, the button outside the
             label so it stays out of the field's name. --}}
        @if($helpId)
        <div data-wk-label-row class="flex items-center gap-[var(--gap-wk-xs)]">
        @endif
        <label for="{{ $sliderId }}" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]">{{ $label }}@if($required)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</label>
        @if($helpId)
            @include('wirekit::components.partials.field-help', ['helpText' => (string) $help, 'helpName' => (string) $label, 'helpId' => $helpId, 'helpField' => (string) ($name ?? $sliderId)])
        </div>
        @endif
    @elseif($needsSrOnlyFallback)
        {{-- sr-only label fallback so the input always has an accessible
             name (axe rule "label" / WCAG 4.1.2). --}}
        <label for="{{ $sliderId }}" class="sr-only">{{ $fallbackLabel }}</label>
    @endif

    {{-- Track overlay container (tooltip bubble + tick marks). Only rendered when
         needed so the plain slider DOM stays unchanged. --}}
    @if($hasTrackOverlay)<div class="relative flex-1">@endif
        @if($tooltip)
            {{-- Value bubble that follows the thumb. Decorative — the native input
                 already exposes the value to AT. The bubble shifts by translateX(-pct%)
                 so it stays WITHIN the track at the extremes instead of overhanging:
                 at 0% it left-aligns with the thumb (extends inward/right), at 100% it
                 right-aligns (extends inward/left), and centers (-50%) in the middle.
                 (The native thumb has no JS-readable width, so pct doubles as the shift.) --}}
            <div class="pointer-events-none absolute bottom-full z-10 mb-1.5" :style="bubbleStyle()" aria-hidden="true">
                <span class="block whitespace-nowrap rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-tooltip-bg)] px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] tabular-nums text-[color:var(--color-wk-tooltip-text)] shadow-[var(--shadow-wk-sm)]" :style="bubbleShiftStyle()" x-text="valueText"></span>
            </div>
        @endif

        <input
            type="range"
            @if($name) name="{{ $name }}" @endif
            {{-- aria-required, NOT the native attribute.

                 HTML's `required` does not apply to `type="range"`: a range always has a
                 value, so the constraint can never fail and the browser ignores the
                 attribute outright. Before `required` was a declared prop it reached this
                 input through the bag, which made the slider LOOK compliant to any check
                 asking "is there a required on an input?" while nothing was constrained —
                 the exact trap the audit warned a naive guard would fall into.

                 aria-required is the honest form: it tells assistive technology the field
                 must be answered, which is true and trivially satisfied here. --}}
            @if($required) aria-required="true" @endif
            {{-- On the input itself: unlike the grouped controls, this IS the single
                 element the message is about. --}}
            @if($error) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            id="{{ $sliderId }}"
            min="{{ $min }}"
            max="{{ $max }}"
            step="{{ $step }}"
            x-ref="control"
            {{-- NOT `:value="current"`. Binding the mirror back onto the element
                 makes Alpine re-assert the stale value over whatever Livewire just
                 wrote, which is the other half of the same bug. The element owns
                 its value; the mirror follows it.

                 initResync() above re-reads it after every Livewire commit — a
                 server-side change writes el.value and fires NO input event, so
                 that is the one moment the two can silently diverge. --}}
            value="{{ $currentValue }}"
            @input="current = $event.target.value"
            @if($optimisticConfig)
                x-bind:aria-busy="isPending"
                {{-- `change` fires once at the end of a drag and once per keypress
                     (see the note at the top), which is exactly the commit boundary
                     for both input modes. --}}
                x-on:change="run($event.target.value)"
                {{-- The gesture begins here, before the mirror moves. A drag
                     writes `current` on every frame via @input above, so a
                     baseline read at commit time would already BE the committed
                     value. keydown lands before the browser applies the step,
                     for the same reason. --}}
                x-on:pointerdown="mark()"
                x-on:keydown="mark()"
            @endif
            {{-- Labeled discrete slider: announce the mark's label, not the bare
                 number. Only bound for a labeled MAP so plain sliders stay
                 byte-identical (the number is already the value). --}}
            @if($bindValueText) :aria-valuetext="valueText" @endif
            @if($disabled) disabled @endif
            {{-- class / style are consumed by the wrapper above; everything
                 else (wire:model, aria-*, data-*) stays on the input. --}}
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
            {{ $attributes->except('type')->except(['class', 'style', 'aria-describedby'])->whereDoesntStartWith('x-model')->class([$inputClasses]) }}
        />

        @if(! empty($normalizedMarks))
            {{-- Tick marks under the track. Decorative; the native input announces value/min/max. --}}
            <div class="pointer-events-none absolute inset-x-0 top-full mt-1" aria-hidden="true">
                @foreach($normalizedMarks as $mark)
                    {{-- `title` for a pointer. `title` is a hover affordance and there is
                         no hover on touch, so assistive technology gets the same text
                         through `aria-valuetext` on the input instead (see below). --}}
                    {{-- `pointer-events-auto` ONLY on a tick that carries a title, and only
                         because the container above is `pointer-events-none` so ticks cannot
                         swallow a drag. Without it the title can never appear: no pointer
                         event reaches the element, so the browser has nothing to show a
                         tooltip for. Ticks without a description stay transparent to the
                         pointer, which keeps dragging over them unaffected. --}}
                    {{-- Positioned the way the value bubble above is: a mark moves back by its own
                         position as a share of its own width, so a label at 0% starts where the row
                         starts, one at 100% ends where it ends, and one at 50% stays centered.
                         Centering every label put the first and the last half a label past the
                         track, and a clipping container cut them off. The tick does not follow its
                         label: it sits at the same share of the mark's width, which lands it back on
                         the value. --}}
                    <div
                        class="absolute flex flex-col items-start{{ $mark['description'] !== null ? ' pointer-events-auto' : '' }}"
                        style="left: {{ $mark['pct'] }}%; transform: translateX(-{{ $mark['pct'] }}%)"
                        @if($mark['description'] !== null)
                            title="{{ $mark['description'] }}"
                        @endif
                    >
                        <span class="relative h-1 w-px -translate-x-1/2 bg-[var(--color-wk-border)]" style="left: {{ $mark['pct'] }}%"></span>
                        @if($mark['label'] !== '')
                            <span class="mt-0.5 whitespace-nowrap text-[length:var(--text-wk-xs)] tabular-nums text-[color:var(--color-wk-text-muted)]">{{ $mark['label'] }}</span>
                        @endif
                        {{-- No `sr-only` description span here, and no `aria-describedby`:
                             this whole container is `aria-hidden="true"` — correctly, because
                             a tick label duplicates the value — so a span here would be dropped
                             from the accessibility tree, and an `aria-describedby` on a
                             non-focusable div is inert regardless.
                             The description reaches assistive technology through
                             `aria-valuetext` on the input, which announces the meaning of the
                             CURRENT position rather than reading every mark at once. --}}
                    </div>
                @endforeach
            </div>
        @endif
    @if($hasTrackOverlay)</div>@endif
    @if($showValue)
        {{-- aria-live="polite" so screen readers get the updated value when
             the user releases the slider, not on every tick. --}}
        {{-- `data-wk-slider-value` is the styling hook for the box. The `aria-live` element
             inside it is the announced TEXT, and since the value gained its width reservation
             it is a grid child, so a rule aimed at it no longer reaches the box. --}}
        <span data-wk-slider-value class="{{ $valueClasses }}">
            {{-- The reservation. `aria-hidden` because it is the same value said twice, and
                 `invisible` rather than `hidden` because a hidden element occupies nothing
                 and would reserve nothing. --}}
            <span aria-hidden="true" class="col-start-1 row-start-1 invisible whitespace-nowrap">{{ $widestValueText }}</span>
            {{-- aria-live="polite" so screen readers get the updated value when the user
                 releases the slider, not on every tick. It stays on the element whose text
                 changes, which is right for the announcement. It is NOT a styling hook for the
                 box: that is `data-wk-slider-value` on the parent. This comment offered the
                 attribute as one until the box became a grid, and a rule written against it then
                 moved the text inside the box instead of the box. --}}
            <span aria-live="polite" class="col-start-1 row-start-1 whitespace-nowrap" x-text="valueText"></span>
        </span>
    @endif
@if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    </div>
@endif
    {{-- Same shape as `input`: one region, error winning over hint, announced politely so
         it does not interrupt what the reader is doing. A line of its own under the control in
         both layouts: across both columns of the grid when the value sits below, since in the
         label's column alone it widened that column to its own length; a full-width line of the
         wrapped row otherwise, starting under the marks, which hang out of flow. --}}
    {{-- The layout keys are written out in each `@class` rather than kept in one array and
         spread in: a class that exists only in a PHP variable is one the drift audit cannot
         trace to this file, and Tailwind would still compile it. --}}
    @if($error)
        <p data-wk-prose-skip id="{{ $sliderId }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif @class([
            'col-span-full' => $valueBelow,
            'basis-full' => ! $valueBelow,
            'mt-7' => ! $valueBelow && $hasLabeledMarks,
            'mt-2' => ! $valueBelow && ! $hasLabeledMarks && ! empty($normalizedMarks),
            'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]',
        ])>{{ $error }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $sliderId }}-hint" @class([
            'col-span-full' => $valueBelow,
            'basis-full' => ! $valueBelow,
            'mt-7' => ! $valueBelow && $hasLabeledMarks,
            'mt-2' => ! $valueBelow && ! $hasLabeledMarks && ! empty($normalizedMarks),
            'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]',
        ])>{{ $hint }}</p>
    @endif
</div>
