{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the choice lands the moment an option is
     picked. A discrete value from a fixed list, and the previous one is the
     server's — so an undo destroys nothing the user typed. The optimistic scope
     nests INSIDE this component and binds to `selected`; the text field follows
     via `after`, which derives its label from the value rather than from the
     clicked option, so a rollback restores the PREVIOUS selection's label. --}}
@props([
    // Livewire method to call optimistically. The choice appears immediately
    // and is put back if the call fails. Absent -> this component renders
    // exactly as it did before, down to the byte.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'name' => null,
    'id' => null,
    'options' => [],
    'value' => null,
    'size' => config('wirekit.components.combobox.size', 'md'),
    // Where the options panel opens against the field: any placement `dropdown` takes. The
    // panel still flips to the other side when the chosen one has no room.
    'placement' => config('wirekit.components.combobox.placement', 'bottom-start'),
    // How wide the panel is. `trigger` matches the field. `auto` takes the width of the widest
    // option, and a CSS length sets one. Those two are never narrower than the field and never
    // wider than the room the placement leaves.
    'panelWidth' => config('wirekit.components.combobox.panel-width', 'trigger'),
    // `false` renders a select-only combobox: a focusable trigger instead of a text field, for a
    // short list nobody needs to search, with the keyboard of the WAI-ARIA select-only pattern.
    'searchable' => true,
    // `??` rather than a `config(…, 'Select…')` fallback, and the difference is the
    // whole point: a config default holds ONE string for every locale, so the literal
    // that used to sit in that second argument was unreachable to a translated app —
    // its only escape was publishing the config, which freezes the wording again. The
    // seam survives (an app may still pin its own word), and an untouched default now
    // resolves through the catalog, exactly as the sibling multi-select already does.
    'placeholder' => config('wirekit.components.combobox.placeholder') ?? __('wirekit::Select…'),
    'disabled' => false,
    'error' => null,
    // `hint` — the one Form control with both `label` and `error` that did not have it. A
    // caller writing `hint="Start typing to search"` got the string on the wrapper div as a
    // stray HTML attribute: never displayed, never announced, and never reported, because
    // Blade folds an undeclared prop into the attribute bag without complaint. Every sibling
    // control has carried it for releases.
    'hint' => null,
    // Accessible name for the combobox. Mirrors select / multi-select: a visible
    // `label` renders an associated x-wirekit::label (for={comboId}); `hideLabel`
    // keeps it in the DOM for assistive tech but visually hidden (compact
    // toolbar / header fields); `ariaLabel` sets aria-label directly on the
    // role="combobox" input for the label-less case. All default to today's
    // behavior (no label at all), so existing comboboxes render byte-identically.
    'label' => null,
    'hideLabel' => false,
    'ariaLabel' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('combobox', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);
    $hideLabel = BooleanProp::from($hideLabel, false);
    $searchable = BooleanProp::from($searchable, true);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Combobox = searchable select. Follows WAI-ARIA 1.2 combobox pattern:
    //   https://www.w3.org/WAI/ARIA/apg/patterns/combobox/
    // Key behavior: user types to filter options, uses arrow keys to navigate
    // the filtered list, Enter to select, Escape to close.
    // STABLE across re-renders, which the old `Str::random(6)` was not. The listbox
    // teleports to #wk-overlay-root, so it sits OUTSIDE the subtree Livewire morphs:
    // a fresh id per render updates the input's aria-controls while the panel still in
    // the document carries the id from the render before it. combobox.js compounds it —
    // it freezes _listId/_inputId at init and resolves them with getElementById, so after
    // one round trip _place() resolves null and positions nothing. Same defect, same
    // reason, same fix as `dropdown`; its comment carries the full reasoning.
    $comboId = \Pushery\WireKit\Support\DomId::unique(
        $id ?? ($name ? 'wk-combobox-'.$name : null),
        'wk-combobox-'
    );
    $listId = $comboId . '-list';
    $errorId = $comboId . '-error';

    // Normalize options: accept ['key' => 'label'] assoc or list of
    // ['value' => .., 'label' => .., 'disabled' => bool] or plain strings.
    // The `disabled` flag (default false) renders the option visually
    // dimmed + not-allowed cursor and prevents click + keyboard activation.
    // A GROUP is an array value with neither a 'label' nor a 'value' key — i.e.
    // a nested map of sub-options (mirrors <x-wirekit::select>: `['Europe' =>
    // ['de' => 'Germany', ...]]`). The extra `value`-key guard keeps the legacy
    // single-option shape `['value' => 'x']` (no label) working as an option.
    // Grouped options carry a `group` key; ungrouped options omit it, so a
    // group-free combobox normalizes byte-identically to before.
    //
    // An array option may also carry a medium (`icon`, `image`, `avatar` or `flag`), a `description`,
    // `keywords` and a `selectedLabel`. OptionMedia validates them and adds only the keys an
    // option uses, so an option without them normalizes exactly as it did before they existed.
    $normalizeOption = function ($key, $opt) {
        if (is_array($opt)) {
            $value = (string) ($opt['value'] ?? $key);
            $label = (string) ($opt['label'] ?? $opt['value'] ?? $key);

            return [
                'value' => $value,
                'label' => $label,
                'disabled' => (bool) ($opt['disabled'] ?? false),
            ] + \Pushery\WireKit\Support\OptionMedia::fields('combobox', $opt, $value, $label);
        }

        return is_int($key)
            ? ['value' => (string) $opt, 'label' => (string) $opt, 'disabled' => false]
            : ['value' => (string) $key, 'label' => (string) $opt, 'disabled' => false];
    };

    $normalized = [];
    foreach ($options as $key => $opt) {
        $isGroup = is_array($opt) && ! array_key_exists('label', $opt) && ! array_key_exists('value', $opt);
        if ($isGroup) {
            foreach ($opt as $subKey => $subOpt) {
                $entry = $normalizeOption($subKey, $subOpt);
                $entry['group'] = (string) $key;
                $normalized[] = $entry;
            }
        } else {
            $entry = $normalizeOption($key, $opt);
            if (is_array($opt) && ! empty($opt['group'])) {
                $entry['group'] = (string) $opt['group'];
            }
            $normalized[] = $entry;
        }
    }

    $hasGroups = false;
    foreach ($normalized as $o) {
        if (! empty($o['group'])) {
            $hasGroups = true;
            break;
        }
    }

    // Icons in options are drawn once, as symbols, and each row points at one. The rows are
    // stamped out by Alpine and cannot render a Blade icon themselves; IconSprite has the
    // measurements behind choosing this over the alternatives.
    [$normalized, $iconSprite] = \Pushery\WireKit\Support\IconSprite::attach($normalized, $comboId);
    // A flag is a code until here and a URL from here on, resolved against the optional flags
    // package the way the flag component resolves it.
    $normalized = \Pushery\WireKit\Support\FlagPackage::attach($normalized);
    $optionUses = \Pushery\WireKit\Support\OptionMedia::uses($normalized);
    $richRows = $optionUses['media'] || $optionUses['descriptions'];

    // The bag read is guarded on the name, exactly as field.blade.php does.
    // `MessageBag::has(null)` falls through to `any()`, so an unguarded read
    // makes a combobox with no `name` report itself invalid the moment ANY
    // unrelated field on the page fails validation — a red border and an
    // `aria-invalid` on a control nobody validated.
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($hasError && $name ? $errors->first($name) : null);

    // The paragraph and the idref pointing at it move together. `$hasError` can
    // be true with nothing to say (`error=""` plus a bag hit), and a described-by
    // resolving to an empty element announces the control as invalid without
    // saying why — a WCAG 3.3.1 failure the markup looks fine in.
    $showsError = $hasError && $errorMessage;

    // Accessible name resolution. A visible label associates via <label for>
    // (label wins, no aria-label needed). Otherwise fall back to the ariaLabel
    // prop, then a caller-passed aria-label attribute — applied to the VISIBLE
    // role="combobox" input (the labelable control), never the roleless wrapper.
    $callerAriaLabel = $attributes->get('aria-label');
    $resolvedAriaLabel = $ariaLabel ?? $callerAriaLabel;

    // Merge a caller aria-describedby with our own error target into ONE attribute on
    // the input, so a caller description reaches the labelable control and
    // never collides with the error id as two attributes.
    // The hint describes the control only while no error does — an error supersedes it, the
    // same precedence input.blade.php uses, so the reader is never pointed at two messages.
    $hintId = $comboId.'-hint';
    $showsHint = $hint !== null && $hint !== '' && ! $showsError;

    $ownDescribedBy = $showsError ? $errorId : ($showsHint ? $hintId : null);
    $callerDescribedBy = $attributes->get('aria-describedby');
    $describedBy = trim(((string) ($ownDescribedBy ?? '')).' '.((string) ($callerDescribedBy ?? '')));
    $describedBy = $describedBy !== '' ? $describedBy : null;

    // Sizing.
    $heightClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    // Option-row sizing — scales the DROPDOWN with `size` so the open panel
    // matches its trigger (a `lg` combobox had `sm`-sized options before, which
    // read as a mismatch). Text size mirrors the trigger; padding scales with it.
    $optionRowClasses = match ($size) {
        'sm' => 'p-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-sm)]',
        'lg' => 'p-[var(--padding-wk-y-md)] text-[length:var(--text-wk-lg)]',
        default => 'p-[var(--padding-wk-y-sm)] text-[length:var(--text-wk-md)]',
    };

    // A row with a medium or a description lays its parts out in a line. A list that uses
    // neither keeps the plain row, so its markup does not change.
    if ($richRows) {
        $optionRowClasses .= ' flex items-center gap-[var(--gap-wk-sm)]';
    }

    // Media and description sizes follow `size` the way the row text does. In the field the
    // medium is one step smaller than in the list, because the field is a single line of text.
    [$rowMediaBox, $rowMediaIcon, $fieldMediaBox, $fieldMediaIcon, $mediaInitials, $descriptionText] = match ($size) {
        'sm' => ['size-5', 'size-4', 'size-4', 'size-3.5', 'text-[length:var(--text-wk-2xs)]', 'text-[length:var(--text-wk-2xs)]'],
        'lg' => ['size-7', 'size-5', 'size-6', 'size-5', 'text-[length:var(--text-wk-xs)]', 'text-[length:var(--text-wk-sm)]'],
        default => ['size-6', 'size-5', 'size-5', 'size-4', 'text-[length:var(--text-wk-2xs)]', 'text-[length:var(--text-wk-xs)]'],
    };

    // Room for the chosen option's medium at the start of the field: the field's own inline
    // padding, the medium, and the gap a row puts between its medium and its label.
    $fieldMediaPadding = match ($size) {
        'sm' => 'ps-[calc(var(--padding-wk-x-md)+--spacing(4)+var(--gap-wk-sm))]',
        'lg' => 'ps-[calc(var(--padding-wk-x-md)+--spacing(6)+var(--gap-wk-sm))]',
        default => 'ps-[calc(var(--padding-wk-x-md)+--spacing(5)+var(--gap-wk-sm))]',
    };

    // Text input styling — identical to other form controls for visual cohesion.
    $inputClasses = WireKit::resolveClasses('combobox', 'input', implode(' ', [
        'w-full',
        'px-[var(--padding-wk-x-md)]',
        // Logical, like the chevron and the clear button it makes room for: the chosen option's
        // medium sits at the START, and a physical side here would put the two on one side in a
        // right-to-left document.
        'pe-[var(--size-wk-md)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]',
        'rounded-[var(--radius-wk-md)]',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus:border-[var(--color-wk-accent)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $heightClasses,
    ]), $scope);

    // The select-only trigger is a `div`, so it takes the field's classes plus what a text field
    // gets for free: its contents laid out in a line, the pointer, and a disabled look that reads
    // `aria-disabled`, since a `div` has no `disabled` state for the `disabled:` variant to see.
    $triggerClasses = $inputClasses.' flex items-center text-start cursor-pointer select-none aria-disabled:cursor-not-allowed aria-disabled:opacity-[var(--opacity-wk-disabled)]';

    // What the trigger shows before Alpine starts, from the same option list the factory gets,
    // so a page that has not booted yet does not show the placeholder over a real choice.
    $initialText = '';
    if (! $searchable && $value !== null) {
        foreach ($normalized as $option) {
            if ($option['value'] === (string) $value) {
                $initialText = $option['selectedLabel'] ?? $option['label'];
                break;
            }
        }
    }

    // One placement vocabulary for every overlay that opens against a trigger.
    $placement = WireKit::validateProp('combobox', 'placement', (string) $placement, \Pushery\WireKit\Support\FloatingPlacement::ALL);

    // `trigger`, `auto`, or a CSS length. The length is interpolated into a style attribute,
    // so its shape is stated positively, as `grid` does for its track minimum: a denylist
    // certifies every spelling it has not thought of. A percentage is left out on purpose,
    // since a fixed panel would take it from the viewport rather than from the field.
    $panelWidth = trim((string) $panelWidth);
    $panelWidthStyle = '';
    if (! in_array($panelWidth, ['trigger', 'auto'], true)) {
        if (preg_match('/^\d+(?:\.\d+)?(?:rem|em|px|ch|vw)$/', $panelWidth) === 1) {
            $panelWidthStyle = 'width: '.$panelWidth.';';
        } else {
            WireKit::validateProp('combobox', 'panelWidth', $panelWidth, ['trigger', 'auto', 'a CSS length such as 20rem']);
            $panelWidth = 'trigger';
        }
    }

    // Options list — dropdown panel.
    // list-none removes browser-default bullet points from the <ul>.
    $listClasses = WireKit::resolveClasses('combobox', 'list', implode(' ', [
        'fixed z-[var(--z-wk-dropdown)]',  // fixed escapes a clipping card; width + height come from _place()
        'list-none',
        'wk-scrollbar max-h-60 overflow-auto',
        // `[overflow-wrap:anywhere]` lets an option name with no space or hyphen break inside the
        // word instead of widening its row past the panel. The panel takes its width from the field,
        // so a repository name with underscores scrolled the whole list sideways, and on a phone the
        // name could only be read by scrolling. It sits on the panel rather than the row so group
        // headings, descriptions and the empty state inherit it, and it is `anywhere` rather than
        // `break-word` because only `anywhere` lowers the min-content width. A space still wins
        // wherever there is one.
        '[overflow-wrap:anywhere]',
        'bg-[var(--color-wk-bg-elevated)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-md)]',
        'py-1',
        $panelWidth === 'auto' ? 'w-max' : '',
    ]), $scope);

    // Empty-state row shares the option-row sizing so "No results" scales with
    // the combobox like the options do.
    $emptyRowClasses = $optionRowClasses;
@endphp

{{-- Alpine state: `query` mirrors the text input, `open` controls visibility,
     `selected` holds the chosen option value, `highlight` tracks keyboard focus
     index within the *filtered* list. --}}
{{-- Single always-present root wrapper (mirrors <x-wirekit::select>). The label,
     when set, associates with the combobox input via `for={comboId}`; without a
     label the wrapper is a layout-neutral div (space-y-1.5 applies no margin to a
     single child, so no visual change). A single stable root keeps the anonymous
     component's $attributes / $component scope intact. --}}
@php
    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the options have to be inside it
    // to reach its `run()`.
    //
    // `after: '_syncQuery'` is what makes the rollback readable. The field shows
    // the chosen option's label, and after an undo it must show the PREVIOUS
    // one's — an option nobody clicked, so it can only come from the value.
    $optimisticConfig = ($optimistic === null || $disabled) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_syncQuery',
        // The field's own error region. Without it the layer's generic "Could not save"
        // is the only thing a listener hears, and it BEATS the specific message the server
        // sent — the whole point of the arbitration is that a specific message wins, and it
        // cannot run against a region nobody pointed at.
        'errorRegion' => '#'.$errorId,
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        // A second pick while one is in flight would resolve by whichever answer
        // arrives last — network timing, which is both wrong and untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
    ]);
@endphp

{{-- `wk-combobox` is a marker with no rules of its own. The reduced-motion clamp matches a `wk-` class
     token and its descendants, and this root is the first element above the chevrons to carry one:
     `wk-field` sits on the input, beside them, where the clamp never reaches them. --}}
<div class="wk-combobox space-y-1.5 min-w-0">
    @if($label)
        {{-- The asterisk flag is READ from the bag rather than declared as a prop, deliberately:
             declaring it would pull `required` OUT of the bag, and the bag is what carries the
             attribute to the native control below. A bare `required` lands in the bag as
             `true`, so this reads it without consuming it. --}}
        @if($searchable)
            <x-wirekit::label :for="$comboId" :required="(bool) $attributes->get('required', false)" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
        @else
            {{-- A `div` is not a labelable element, so `for` would point at nothing: the trigger
                 takes its name from this label through `aria-labelledby` instead. --}}
            <x-wirekit::label :id="$comboId.'-label'" :required="(bool) $attributes->get('required', false)" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
        @endif
    @endif
<div
    x-data="wirekitCombobox({ value: {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }}, options: {{ \Pushery\WireKit\Support\AlpinePayload::from($normalized) }}, listId: {{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }}, emptyId: {{ \Pushery\WireKit\Support\AlpinePayload::string($listId.'-empty') }}, inputId: {{ \Pushery\WireKit\Support\AlpinePayload::string($comboId) }}, placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }}, panelWidth: {{ \Pushery\WireKit\Support\AlpinePayload::string($panelWidth) }}{{ $searchable ? '' : ', searchable: false' }} })"
    @click.outside="open = false"
    {{-- The chosen option, exposed by name so a binding on the component tag reaches
         the SELECTION. It used to reach the search field instead -- the bag below is
         routed to the role="combobox" input, which already carries `x-model="query"`,
         so `wire:model` bound the typed text and the server received three letters
         rather than the option behind them. `wire:model` compiles to `x-model`, and
         `x-modelable` is what lets a non-input element answer it. --}}
    x-modelable="selected"
    {{ $attributes->whereStartsWith('wire:model') }}
    {{-- The roleless wrapper otherwise carries ONLY layout — every caller attribute
         (aria-describedby, data-*, autocomplete, required, …) is routed to the
         role="combobox" input below, never left stranded on this <div>. --}}
    {{ $attributes->only(['style'])->class(['relative w-full']) }}
>
    @if($optimisticConfig)
        {{-- `display: contents` — this element's `relative` is the containing
             block the listbox is positioned against, and a real box here would
             move the panel. --}}
        <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
    @endif

    {{-- Hidden input holding the selected *value* for form submission. --}}
    @if($name)
        {{-- Static value as well as the bound one: the field is empty until Alpine
             boots, and a form submitted in that window sends nothing while the
             visible control already shows the value. Both come from the same PHP
             expression that feeds the factory, so they cannot drift. --}}
        <input type="hidden" name="{{ $name }}" value="{{ $value }}" :value="submittedValue" />
    @endif

    @if($searchable)
    {{-- Visible text input — role=combobox + aria-expanded + aria-controls
         satisfies the WAI-ARIA 1.2 combobox pattern. --}}
    <input
        type="text"
        x-ref="cbxInput"
        id="{{ $comboId }}"
        role="combobox"
        aria-expanded="false"
        :aria-expanded="open"
        aria-controls="{{ $listId }}"
        :aria-activedescendant="open && filtered[highlight] ? {{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + highlight : null"
        aria-autocomplete="list"
        placeholder="{{ $placeholder }}"
        autocomplete="off"
        x-model="query"
        @focus="open = true"
        @input="openAndReset()"
        @keydown.arrow-down.prevent="openAndMove(1)"
        @keydown.arrow-up.prevent="moveHighlight(-1)"
        @keydown.home.prevent="openAtFirst()"
        @keydown.end.prevent="openAtLast()"
        {{-- runIf, not run: Enter can fire with nothing highlighted, and
             `run(undefined)` would send the server a value nobody chose and then
             roll back from it. --}}
        @keydown.enter.prevent="{{ $optimisticConfig ? 'runIf(highlightedValue())' : 'activateHighlighted()' }}"
        @keydown.escape="open = false"
        @if($disabled) disabled @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{-- Accessible name: a visible <label for> wins; otherwise aria-label
             from the ariaLabel prop / caller attribute lands on this input (the
             labelable role="combobox" control), never the roleless wrapper. --}}
        @if(! $label && $resolvedAriaLabel) aria-label="{{ $resolvedAriaLabel }}" @endif
        {{-- Every OTHER caller attribute (data-*, autocomplete, required, readonly …)
             reaches the actual control here, not the wrapper. --}}
        {{-- `wire:model*` is excluded here and rendered on the Alpine root above: on
             this element it would be a second model binding beside `x-model="query"`,
             pointed at the search text. --}}
        {{ $attributes->except(['aria-label', 'class', 'style', 'aria-describedby'])->whereDoesntStartWith('wire:model') }}
        class="wk-field {{ $inputClasses }}"
        @if($optionUses['media']) x-bind:class="fieldMedia.length ? {{ \Pushery\WireKit\Support\AlpinePayload::string($fieldMediaPadding) }} : ''" @endif
    />
    @else
    {{-- THE SELECT-ONLY TRIGGER, after the WAI-ARIA APG select-only combobox example: a focusable
         `div` with `role="combobox"`, not an input, because it takes no typed value. Its text is
         the choice, which is what a screen reader reads as the combobox's value, and its name
         comes from the label through `aria-labelledby`.

         Focus never leaves it. The active option is announced through `aria-activedescendant`,
         exactly as on the text field, and the keyboard is `selectOnlyKeydown()`: it hands back
         the value a key chooses, and this attribute decides how that choice is made, because
         with `optimistic` it has to go through the optimistic layer's `runIf()`.

         It does not open on focus, unlike the text field: tabbing through a form must not open
         every select-only field it passes. --}}
    <div
        x-ref="cbxInput"
        id="{{ $comboId }}"
        role="combobox"
        tabindex="{{ $disabled ? '-1' : '0' }}"
        aria-haspopup="listbox"
        aria-expanded="false"
        :aria-expanded="open"
        aria-controls="{{ $listId }}"
        :aria-activedescendant="open && filtered[highlight] ? {{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + highlight : null"
        @if($label) aria-labelledby="{{ $comboId }}-label" @elseif($resolvedAriaLabel) aria-label="{{ $resolvedAriaLabel }}" @endif
        @if($disabled)
            aria-disabled="true"
        @else
            @click="toggleSelectOnly()"
            @keydown="{{ $optimisticConfig ? 'runIf(selectOnlyKeydown($event))' : 'chooseValue(selectOnlyKeydown($event))' }}"
        @endif
        @if($hasError) aria-invalid="true" @endif
        @if($describedBy) aria-describedby="{{ $describedBy }}" @endif
        {{-- `required` is an input attribute, and a `div` has no validity to carry it. --}}
        @if($attributes->get('required')) aria-required="true" @endif
        {{ $attributes->except(['aria-label', 'class', 'style', 'aria-describedby', 'required', 'autocomplete', 'placeholder', 'readonly'])->whereDoesntStartWith('wire:model') }}
        class="wk-field {{ $triggerClasses }}"
        @if($optionUses['media']) x-bind:class="fieldMedia.length ? {{ \Pushery\WireKit\Support\AlpinePayload::string($fieldMediaPadding) }} : ''" @endif
    >
        {{-- Two spans rather than one binding that swaps the text and its color: the placeholder
             color would otherwise live in a runtime class string no scope can restyle. --}}
        <span class="block min-w-0 truncate" x-show="selectedText !== ''" x-text="selectedText" @if($initialText === '') x-cloak @endif>{{ $initialText }}</span>
        <span class="block min-w-0 truncate text-[color:var(--color-wk-text-placeholder)]" x-show="selectedText === ''" @if($initialText !== '') x-cloak @endif>{{ $placeholder }}</span>
    </div>
    @endif

    @if($optionUses['media'])
        {{-- The chosen option's medium, at the start of the field, while the field shows that
             option. A list of at most one, so the medium has an option to bind to: `fieldMedia`
             is empty once the reader starts typing a new search, because the medium of the last
             choice beside a half-typed word names something the field no longer says. --}}
        <template x-for="chosen in fieldMedia" :key="chosen.value">
            <span class="pointer-events-none absolute start-[var(--padding-wk-x-md)] top-1/2 flex -translate-y-1/2 text-[color:var(--color-wk-text-muted)]">
                @include('wirekit::components.partials.listbox-option-media', [
                    'option' => 'chosen',
                    'boxClasses' => $fieldMediaBox,
                    'iconClasses' => $fieldMediaIcon,
                    'initialsClasses' => $mediaInitials,
                ])
            </span>
        </template>
    @endif

    {{-- Clear button — visible only when a value is selected. Positioned left of the chevron. --}}
    @if(!$disabled)
        <button
            type="button"
            x-show="selected"
            x-cloak
            {{-- run(null), not runIf: clearing IS a choice — "none of them" — and it
                 is a mutation the server has to hear about. `undefined` would be
                 the absence of a choice; null is a choice. --}}
            @click.stop="{{ $optimisticConfig ? 'run(null)' : 'clearSelection()' }}"
            class="absolute end-8 top-1/2 -translate-y-1/2 inline-flex items-center justify-center min-w-[24px] min-h-[24px] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
            aria-label="{{ __('wirekit::Clear selection') }}"
        >
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
            </svg>
        </button>
    @endif

    {{-- Chevron — clickable button that toggles the dropdown. Carries
         `cursor-pointer` so the user gets the right hover affordance, and
         delegates focus to the input on click so the input's keyboard
         contract continues to work. tabindex="-1" keeps the chevron out
         of the natural tab order — the input itself is the focusable
         control per the WAI-ARIA combobox pattern. --}}
    @if($searchable)
    <button
        type="button"
        {{-- Always return focus to the input — on close too, not only on open.
             This button is aria-hidden + tabindex=-1 (decorative; the input is
             the focusable combobox control). If a click leaves focus ON this
             button (which happens when it toggles the panel closed), the browser
             flags "aria-hidden on a focused element". Refocusing the input every
             time keeps focus on the real control and clears that warning. --}}
        @click.stop="toggleAndFocus()"
        @if($disabled) disabled @endif
        tabindex="-1"
        aria-hidden="true"
        class="absolute end-3 top-1/2 -translate-y-1/2 p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-transform duration-[var(--transition-wk-duration)] cursor-pointer disabled:cursor-not-allowed disabled:opacity-[var(--opacity-wk-disabled)]"
        :class="open ? 'rotate-180' : ''"
    >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </button>
    @else
    {{-- Drawn, not a control: the whole trigger toggles the list, and a second focusable element
         inside a combobox is nested interaction that assistive technology cannot reach. It lets
         the click through to the trigger underneath. --}}
    <span
        aria-hidden="true"
        class="pointer-events-none absolute end-3 top-1/2 -translate-y-1/2 p-0.5 text-[color:var(--color-wk-text-muted)] transition-transform duration-[var(--transition-wk-duration)]"
        :class="open ? 'rotate-180' : ''"
    >
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.06l3.71-3.83a.75.75 0 111.08 1.04l-4.25 4.39a.75.75 0 01-1.08 0L5.21 8.27a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
        </svg>
    </span>
    @endif

    {{-- Listbox — filtered options rendered via x-for. Each option gets a
         unique id + role=option so AT can announce them as the user navigates. --}}
    {{-- Teleported to <body>, for the reason command-palette's overlay already
         states: `position: fixed` escapes a clipping ancestor but NOT a stacking
         context. Any ancestor with `contain: layout`, a transform or a filter
         scopes this panel's z-index inside itself, and anything painted after
         that ancestor then covers the list however high the z-index goes.
         Reported from the documentation site, whose preview area carries
         `contain: layout`: the open list rendered UNDER the code block below it.
         `$refs` do NOT survive the teleport, and the comment that used to say so
         here was an assumption nobody had measured. Once the panel moves to
         `<body>`, `$refs.cbxList` is null — so `_place()` looped over two nulls,
         positioned nothing, and left a `fixed` panel at its static position:
         measured at 0,1117 while the field sat at 12,451. It never corrected,
         because the positioner had not run at all.
         `_place()` resolves both panels by id now, handed in through the factory
         config. An id survives anything a teleport can do to a node. --}}
    <template x-teleport="#wk-overlay-root">
    <ul data-wk-prose-skip
        {{-- THE MORPH KEY. Without it the id below is what Livewire uses to
             identify this node across an update — it resolves `wire:id`, then
             `wire:key`, then `el.id` — and a key that disagrees between the live
             node and the incoming template does not patch, it SWAPS the live node
             for a native `cloneNode(true)` that carries no Alpine expandos. The
             replacement lands in the overlay root, which hangs off <body> in no
             `x-data`, so Alpine's parent walk finds no scope and every expression
             on the list resolves against the global object instead. `filtered` is
             not a word the global object answers to, so this one raises a
             `ReferenceError` rather than the `Illegal invocation` a panel bound on
             `open` produces — the same defect, reported under a different name.
             The exposure is conditional on the call site: `$listId` is derived from
             an explicit id or name when one is given and randomized when neither
             is, so the bug reaches only the callers who left both off. That is a
             worse shape than an unconditional one, not a milder one — it makes the
             failure look like something about the page rather than the component.
             STATIC on purpose: a teleported node is patched against its own
             counterpart, one to one, never against a keyed sibling, so several
             comboboxes on a page do not compete for this value. --}}
        wire:key="wk-combobox-list"
        id="{{ $listId }}"
        x-ref="cbxList"
        role="listbox"
        aria-label="{{ $resolvedAriaLabel }}"
        class="{{ $listClasses }}"
        style="list-style: none; margin: 0; padding: 0;{{ $panelWidthStyle !== '' ? ' '.$panelWidthStyle : '' }}"
        x-show="open && filtered.length > 0"
        x-cloak
    >
        @if($hasGroups)
        {{-- Grouped options: each group is role="group" with an aria-label; the
             visible heading is decorative (aria-hidden) since the group's
             aria-label supplies its name. The inner list is role="none" so the
             options remain effective children of the group in the a11y tree.
             The flat keyboard model is untouched — selection + highlight key off
             opt._idx (each option's index into the flat `filtered` list). --}}
        <template x-for="grp in filteredGroups" :key="groupKey(grp)">
            <li data-wk-prose-skip role="group" :aria-label="grp.label || {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Options')) }}" style="list-style: none;">
                <template x-if="grp.label">
                    <div aria-hidden="true" class="px-[var(--padding-wk-x-md)] pt-[var(--padding-wk-y-sm)] pb-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] font-[number:var(--font-wk-heading-weight)] uppercase tracking-wider text-[color:var(--color-wk-text-muted)]" x-text="grp.label"></div>
                </template>
                <ul data-wk-prose-skip role="none" style="list-style: none; margin: 0; padding: 0;">
                    <template x-for="opt in grp.options" :key="opt.value">
                        <li data-wk-prose-skip
                            role="option"
                            :id="{{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + opt._idx"
                            :aria-selected="selected === opt.value"
                            :aria-disabled="opt.disabled ? 'true' : null"
                            :class="opt.disabled
                                ? 'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed'
                                : (opt._idx === highlight
                                    ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] cursor-pointer'
                                    : 'text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)] cursor-pointer')"
                            class="{{ $optionRowClasses }}"
                            @click="{{ $optimisticConfig ? 'run(opt.value)' : 'selectOption(opt)' }}"
                            @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                            @mouseenter="hoverOption(opt, opt._idx)"
                            {{-- A pressed option would otherwise take focus from a select-only trigger,
                                 which has no text to type into and must keep it. --}}
                            @if(! $searchable) @mousedown.prevent @endif
                            @if($richRows)
                                @if($optionUses['descriptions'])
                                    :aria-labelledby="{{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + opt._idx + '-label'"
                                    :aria-describedby="opt.description ? {{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + opt._idx + '-desc' : null"
                                @endif
                            @else
                                x-text="opt.label"
                            @endif
                        >@if($richRows)@include('wirekit::components.partials.listbox-option-content', [
                            'idExpression' => \Pushery\WireKit\Support\AlpinePayload::string($listId)." + '-opt-' + opt._idx",
                            'media' => $optionUses['media'],
                            'descriptions' => $optionUses['descriptions'],
                            'mediaBox' => $rowMediaBox,
                            'mediaIcon' => $rowMediaIcon,
                            'mediaInitials' => $mediaInitials,
                            'descriptionClasses' => $descriptionText.' text-[color:var(--color-wk-text-muted)]',
                        ])@endif</li>
                    </template>
                </ul>
            </li>
        </template>
        @else
        <template x-for="(opt, idx) in filtered" :key="opt.value">
            <li data-wk-prose-skip
                role="option"
                :id="{{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + idx"
                :aria-selected="selected === opt.value"
                :aria-disabled="opt.disabled ? 'true' : null"
                :class="opt.disabled
                    ? 'text-[color:var(--color-wk-text-muted)] opacity-[var(--opacity-wk-disabled)] cursor-not-allowed'
                    : (idx === highlight
                        ? 'bg-[var(--color-wk-bg-muted)] text-[color:var(--color-wk-text)] cursor-pointer'
                        : 'text-[color:var(--color-wk-text-muted)] hover:bg-[var(--color-wk-bg-muted)] hover:text-[color:var(--color-wk-text)] cursor-pointer')"
                class="{{ $optionRowClasses }}"
                @click="{{ $optimisticConfig ? 'run(opt.value)' : 'selectOption(opt)' }}"
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                @mouseenter="hoverOption(opt, idx)"
                @if(! $searchable) @mousedown.prevent @endif
                @if($richRows)
                    @if($optionUses['descriptions'])
                        :aria-labelledby="{{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + idx + '-label'"
                        :aria-describedby="opt.description ? {{ \Pushery\WireKit\Support\AlpinePayload::string($listId) }} + '-opt-' + idx + '-desc' : null"
                    @endif
                @else
                    x-text="opt.label"
                @endif
            >@if($richRows)@include('wirekit::components.partials.listbox-option-content', [
                'idExpression' => \Pushery\WireKit\Support\AlpinePayload::string($listId)." + '-opt-' + idx",
                'media' => $optionUses['media'],
                'descriptions' => $optionUses['descriptions'],
                'mediaBox' => $rowMediaBox,
                'mediaIcon' => $rowMediaIcon,
                'mediaInitials' => $mediaInitials,
                'descriptionClasses' => $descriptionText.' text-[color:var(--color-wk-text-muted)]',
            ])@endif</li>
        </template>
        @endif
    </ul>
    </template>

    {{-- Empty state when filter produces no matches. Teleported for the same
         reason as the list above — it is the same panel wearing different content,
         and leaving it behind would fix the case with results and keep the bug for
         the case without. --}}
    <template x-teleport="#wk-overlay-root">
    <div
        {{-- Keyed for the same reason as the list above — it is the same panel
             wearing different content, and leaving it unkeyed would fix the case
             with results and keep the defect for the case without.
             Its own value rather than the list's: a key names a node, and these
             are two nodes. Naming them alike would be a claim that the morph may
             treat either as the other, which is not something this component has
             any reason to promise. --}}
        wire:key="wk-combobox-empty"
        id="{{ $listId }}-empty"
        {{-- "No results" is the outcome of what the reader just typed, and an
             outcome that only appears on screen reaches nobody using a screen
             reader: the input goes on saying aria-expanded="true" with no active
             option, which reads as an open list that happens to have nothing
             highlighted. `role="status"` is an implicit polite, atomic live
             region, and this node is teleported once and then only toggled, so
             the text arrives INTO a region that was already there — which is the
             condition for it being spoken at all. --}}
        role="status"
        class="{{ $listClasses }}"
        @if($panelWidthStyle !== '') style="{{ $panelWidthStyle }}" @endif
        x-ref="cbxEmpty"
        x-show="open && filtered.length === 0 && query !== ''"
        x-cloak
    >
        <p data-wk-prose-skip class="{{ $emptyRowClasses }} text-[color:var(--color-wk-text-muted)]">{{ __('wirekit::No results') }}</p>
    </div>
    </template>

    {{-- The symbols the option icons point at. Inside the component root rather than the
         teleported panel, so a Livewire update renders them with the component; a `<use>`
         reference resolves anywhere in the document, so the panel reaches them from <body>. --}}
    {{ $iconSprite }}

    @if($showsError)
        <p data-wk-prose-skip id="{{ $errorId }}" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($showsHint)
        <p data-wk-prose-skip id="{{ $hintId }}" class="mt-[var(--padding-wk-y-xs)] text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif

    @if($optimisticConfig)
        {{-- Outside the listbox — a live region is not an option — and inside
             the optimistic scope. Rendered unconditionally and starting empty: a
             region that arrives together with its text is a new node, and
             nothing is announced at all.

             It sits AFTER the error paragraph on purpose: where that paragraph
             is present and speaking, the layer's own rollback stays silent and
             leaves it the floor. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
        </div>
    @endif
</div>

{{-- Close the always-present root wrapper. --}}
</div>
