{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the pill appears (or disappears) the moment
     an option is clicked. A selection is a discrete set of server values, so an
     undo puts back what the server has and destroys nothing the user typed —
     the filter text is separate state and is never rolled back. The optimistic
     scope nests INSIDE this component and binds to `selected`. --}}
@props([
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk. StrictnessGate did not
    // complain either, because `required` is in its HTML passthrough list — so it looked like
    // a legitimate attribute all the way down. The result was a required field that submits
    // empty, in the same form as a plain input that behaves correctly.
    'required' => false,
    // Livewire method to call optimistically. It receives the FULL new
    // selection as an array. The pill appears immediately and is removed again
    // if the call fails. Absent -> this component renders exactly as before.
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
    'label' => null,
    'hint' => null,
    'error' => null,
    'options' => [],
    'value' => [],          // option keys to pre-select on load (array or comma-separated string)
    'placeholder' => config('wirekit.components.multi-select.placeholder') ?? __('wirekit::Select…'),
    // Where the options panel opens against the field: any placement `dropdown` takes. The
    // panel still flips to the other side when the chosen one has no room.
    'placement' => config('wirekit.components.multi-select.placement', 'bottom-start'),
    // How wide the panel is. `trigger` matches the field. `auto` takes the width of the widest
    // option, and a CSS length sets one. Those two are never narrower than the field and never
    // wider than the room the placement leaves.
    'panelWidth' => config('wirekit.components.multi-select.panel-width', 'trigger'),
    // Search on the server instead of in the browser, for a list too long to render into the
    // page. The typed text is sent as a `search-change` event with `{ value }` once it settles,
    // and `options` are the results the application renders for it. A chosen value keeps its
    // label after the results move on.
    'server' => false,
    // Characters before the text is sent. Shorter text sends an empty value, once.
    'searchMinLength' => config('wirekit.components.multi-select.search-min-length', 2),
    // Milliseconds of quiet before the text is sent: one request per settled search rather
    // than one per character. 0 sends at once, for an application that throttles on its side.
    'searchDebounce' => config('wirekit.components.multi-select.search-debounce', 300),
    // The application cut its results at a limit. The list then says there are more.
    'truncated' => false,
    // How the options are offered. `dropdown` opens them in a panel under the field and shows
    // the choice as pills inside it. `list` shows them open under a search field, each with a
    // checkbox, and the choice as a list below it with a button to take each one out.
    'layout' => 'dropdown',
    'scope' => null,
    'ariaLabel' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `required="false"` would read as TRUE and mark the field required anyway.
    $required = BooleanProp::from($required, false);
    $server = BooleanProp::from($server, false);
    $truncated = BooleanProp::from($truncated, false);
    $searchMinLength = max(1, (int) $searchMinLength);
    $searchDebounce = max(0, (int) $searchDebounce);
    $listLayout = WireKit::validateProp('multi-select', 'layout', (string) $layout, ['dropdown', 'list']) === 'list';


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('multi-select', $attributes->getAttributes());

    // A caller's listener for an event this view listens to on the element the bag lands on
    // goes in the other spelling, so both run (Support\CallerListeners).
    $attributes = \Pushery\WireKit\Support\CallerListeners::beside($attributes, ['@click.away', '@keydown.escape']);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'multi-select-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);

    // A caller's `id` goes on the text field, the element a `<label for>` elsewhere on the
    // page and a link to `#id` reach. Without one the field takes the component's id with
    // `-input` after it, as before. The option and listbox ids keep `$id` as their stem.
    $callerId = filled($attributes->get('id'));
    $fieldId = $callerId ? $id : $id.'-input';

    // `id` and `name` are consumed above and re-emitted where they belong -- the
    // internal combobox input and the hidden inputs. Leaving them in the bag would
    // put a `name` on a <div>, which is not a form control and carries nothing.
    $attributes = $attributes->except(['id', 'name']);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);

    // When a parent <x-wirekit::field label="..."> wraps this component, the
    // field-emitted <label for="$id"> doesn't reach the internal combobox
    // <input id="$id-input">, so screen readers + axe's label rule report
    // an unlabeled form element. We synthesize an aria-label fallback —
    // explicit `ariaLabel` prop wins, then the field's `label` prop (passed
    // down via attributes scan), then the `name`/`placeholder` as last resort.
    $resolvedAriaLabel = $ariaLabel ?? $attributes->get('aria-label') ?? $label ?? $placeholder ?? $name;

    // On the text field the last two fallbacks step aside for a caller's `id`: the id is there
    // so a label of the caller's can reach the field, and an aria-label would win over that
    // label. The list and the result group keep the full chain; they are not the field.
    $fieldAriaLabel = $ariaLabel ?? $attributes->get('aria-label') ?? $label ?? ($callerId ? null : ($placeholder ?? $name));

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // One placement vocabulary for every overlay that opens against a trigger.
    $placement = WireKit::validateProp('multi-select', 'placement', (string) $placement, \Pushery\WireKit\Support\FloatingPlacement::ALL);

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
            WireKit::validateProp('multi-select', 'panelWidth', $panelWidth, ['trigger', 'auto', 'a CSS length such as 20rem']);
            $panelWidth = 'trigger';
        }
    }

    // Container classes — styled like an input field, wraps pills + filter input.
    // py-y-sm (0.375rem ≈ 6px) for visually balanced top/bottom padding around
    // the wrapped pills — py-1 (4px) reads as too tight against the
    // px-x-md (12px) horizontal padding on the sides. The sides take the same
    // inline token as select (md) and the combobox input, so a filter row of
    // the three starts its text on one line. This line once read `p-` with the
    // vertical token, which put the placeholder half as far from the border as
    // the text in the fields beside it.
    $containerClasses = WireKit::resolveClasses('multi-select', 'base', implode(' ', [
        'flex flex-wrap items-center gap-1',
        'min-h-[var(--size-wk-md)]',
        'py-[var(--padding-wk-y-sm)] px-[var(--padding-wk-x-md)]',
        'font-[family-name:var(--font-wk-sans)]',
        'bg-[var(--color-wk-bg-input)]',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-within:ring-[length:var(--ring-wk-width)] focus-within:ring-[var(--color-wk-ring)]',
        'cursor-text',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border-strong)]';

    // Pill classes for selected values — py-1 for balanced vertical padding
    $pillClasses = implode(' ', [
        'inline-flex items-center gap-1',
        'pl-[var(--padding-wk-x-sm)] pr-1 py-1',
        'text-[length:var(--text-wk-sm)]',
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'rounded-[var(--radius-wk-sm)]',
    ]);

    // Dropdown option classes
    /*
     * The two appearance branches of an option row, resolved HERE rather than
     * written into the runtime binding below.
     *
     * A class string that only ever exists inside `:class="…"` is out of reach of
     * WireKit::scope(): resolveClasses runs at render time and can be overridden per
     * scope, an Alpine expression cannot. Same shape as segmented-control's selected /
     * unselected segments, for the same reason.
     */
    $optionHighlightedClasses = WireKit::resolveClasses('multi-select', 'option-highlighted', implode(' ', [
        'bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
    ]), $scope);

    // A selected option is marked twice: by the selected weight and by the check at the end
    // of its row. The check is ink, so it holds in grayscale and in forced colors, and it is
    // what keeps the choice visible when a theme sets `--font-wk-selected-weight` to the body
    // weight. Without it the weight would be the only mark on the row, and the highlight
    // tint is the hover tint.
    $optionSelectedClasses = WireKit::resolveClasses('multi-select', 'option-selected', implode(' ', [
        'font-[number:var(--font-wk-selected-weight)]',
    ]), $scope);

    $optionCheckClasses = WireKit::resolveClasses('multi-select', 'option-check', 'h-4 w-4 shrink-0', $scope);

    // Every row lays its parts out in a line, so the check sits at the end whatever the label
    // holds, and a row with a medium or a description keeps the same layout.
    $optionClasses = implode(' ', [
        'flex items-center gap-[var(--gap-wk-sm)]',
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text)]',
        'cursor-pointer',
        'hover:bg-[var(--color-wk-bg-muted)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
    ]);

    // Empty-state row. Shares the option row's sizing so "No results" scales
    // with the control like the options do, and drops the pointer affordances —
    // the hover tint and the pointer cursor both say "choosable", which this row
    // is not. Built as its own string rather than appended to $optionClasses:
    // two conflicting `cursor-*` utilities in one attribute are resolved by the
    // order they sit in the stylesheet, not the order they are written here.
    $emptyRowClasses = implode(' ', [
        'p-[var(--padding-wk-y-sm)]',
        'text-[length:var(--text-wk-md)]',
        'text-[color:var(--color-wk-text-muted)]',
        'cursor-default',
    ]);

    // The list layout: a search field, the options open under it with a checkbox each, and the
    // choice below with a button to take each one out. The checkbox box is the one `checkbox`
    // draws, written out here because a row is stamped out by Alpine and cannot render the
    // component: its `label for` names one fixed id, which every row would share.
    $listSearchClasses = WireKit::resolveClasses('multi-select', 'list-search', implode(' ', [
        'block w-full min-h-[var(--size-wk-md)]',
        'py-[var(--padding-wk-y-sm)] px-[var(--padding-wk-x-md)]',
        'font-[family-name:var(--font-wk-sans)] text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]',
        'bg-[var(--color-wk-bg-input)] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] shadow-[var(--shadow-wk-sm)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
    ]), $scope);

    $listRowClasses = WireKit::resolveClasses('multi-select', 'list-row', implode(' ', [
        'group flex items-start gap-[var(--gap-wk-sm)] cursor-pointer',
        'py-[var(--padding-wk-y-xs)] px-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-sm)]',
        'text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)]',
        'hover:bg-[var(--color-wk-bg-muted)]',
    ]), $scope);

    $listBoxClasses = 'wk-touch-target '.WireKit::resolveClasses('multi-select', 'list-checkbox', implode(' ', [
        'relative inline-flex items-center justify-center shrink-0 w-5 h-5 mt-0.5',
        'rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border-strong)]',
        'peer-hover:border-[var(--color-wk-border-strong-hover)] bg-[var(--color-wk-bg-input)]',
        'peer-checked:bg-[var(--color-wk-accent)] peer-checked:border-[var(--color-wk-accent)]',
        'peer-checked:peer-hover:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)] peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)] peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'transition-colors duration-[var(--transition-wk-duration)] text-[color:var(--color-wk-accent-fg)]',
    ]), $scope);

    $listChosenClasses = WireKit::resolveClasses('multi-select', 'list-chosen', implode(' ', [
        'flex items-center justify-between gap-[var(--gap-wk-sm)]',
        'py-[var(--padding-wk-y-xs)] ps-[var(--padding-wk-x-sm)] pe-1',
        'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]',
        'bg-[var(--color-wk-bg-muted)] rounded-[var(--radius-wk-sm)]',
    ]), $scope);

    // Both plural forms of the heading over the choice travel to the browser, which counts it.
    $selectedOne = trans_choice('wirekit::{1} :count selected|[2,*] :count selected', 1, ['count' => '__COUNT__']);
    $selectedMany = trans_choice('wirekit::{1} :count selected|[2,*] :count selected', 2, ['count' => '__COUNT__']);
    $listConfig = $listLayout
        ? ', selectedOne: '.\Pushery\WireKit\Support\AlpinePayload::string($selectedOne).', selectedMany: '.\Pushery\WireKit\Support\AlpinePayload::string($selectedMany)
        : '';

    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
    // A caller's aria-describedby joins this list, because the control is what it describes
    // and an attribute is written once: the parser keeps the first copy of a duplicate. Own
    // ids first, then the caller's.
    $describedBy = trim($describedBy.' '.((string) $attributes->get('aria-describedby', '')));

    // Encode options for Alpine — convert to array of {value, label} objects.
    //
    // Three call shapes reach this and the docs page promises all three: an
    // associative `key => label` map, a list of plain strings, and a list of
    // `['value' => ..., 'label' => ...]` arrays. Reading the key as the value
    // unconditionally would submit array indexes (0, 1, 2) for a list of strings
    // under labels that look right, and the literal word "Array" for a list of
    // arrays, with nothing thrown and nothing empty on screen.
    // Mirrors the ungrouped half of combobox's own $normalizeOption; multi-select
    // has no grouped-option shape, so the group branch does not apply here.
    //
    // An array option may also carry a medium (`icon`, `image`, `avatar` or `flag`), a `description`,
    // `keywords` and a `selectedLabel`; OptionMedia validates them and adds only the keys an
    // option uses, so an option without them normalizes exactly as it did before.
    //
    // A LIST is decided by the ARRAY, never by one key: keys 0, 1, 2 … in order. PHP turns a
    // numeric string key into an integer, so deciding by the key's type read `[31 => 'Rain
    // jacket']` and every `pluck('name', 'id')` as a list and submitted the NAMES — the pills
    // looked right and `wire:model` received labels instead of ids.
    $optionsAreAList = array_is_list(collect($options)->all());
    $encodedOptions = collect($options)->map(function ($option, $key) use ($optionsAreAList) {
        if (is_array($option)) {
            $value = (string) ($option['value'] ?? $key);
            $label = (string) ($option['label'] ?? $option['value'] ?? $key);

            return [
                'value' => $value,
                'label' => $label,
            ] + \Pushery\WireKit\Support\OptionMedia::fields('multi-select', $option, $value, $label);
        }

        // In a list the string is BOTH value and label; in a map the key is the submitted
        // value and the string beside it its label, whatever type the key has.
        return $optionsAreAList
            ? ['value' => (string) $option, 'label' => (string) $option]
            : ['value' => (string) $key, 'label' => (string) $option];
    })->values()->all();

    // Icons in options are drawn once, as symbols, and each row and pill points at one; the
    // rows are stamped out by Alpine and cannot render a Blade icon. IconSprite has the reasons.
    [$encodedOptions, $iconSprite] = \Pushery\WireKit\Support\IconSprite::attach($encodedOptions, $id);
    // A flag is a code until here and a URL from here on, resolved against the optional flags
    // package the way the flag component resolves it.
    $encodedOptions = \Pushery\WireKit\Support\FlagPackage::attach($encodedOptions);
    $optionUses = \Pushery\WireKit\Support\OptionMedia::uses($encodedOptions);
    // A server search learns what its rows carry only from results that have not arrived yet,
    // so it draws every row the rich way: a medium and a description appear per row, and a row
    // without them looks exactly like a plain one.
    if ($server) {
        $optionUses = ['media' => true, 'descriptions' => true];
    }
    $richRows = $optionUses['media'] || $optionUses['descriptions'];

    // Normalize the `value` prop to an array of string option keys for
    // pre-selection. Accepts an array (['php', 'js']) or a comma-separated
    // string ('php,js') — mirrors the seeding contract of tags-input. The
    // resulting keys seed the Alpine `selected` array so the matching pills
    // render on load. (Framework-agnostic: works in plain Blade forms and as
    // the initial display alongside a two-way binding.)
    $selectedValues = is_array($value)
        ? array_values(array_map(fn ($v) => (string) $v, $value))
        : (is_string($value) && $value !== ''
            ? array_values(array_filter(array_map('trim', explode(',', $value)), fn ($v) => $v !== ''))
            : []);

    // Server search. The results travel on an attribute of their own rather than in `x-data`,
    // which Alpine reads once: Livewire patches the attribute on every render and the factory
    // follows it (utils/server-search.js). The sentences are translated here, since a sentence
    // put together in JavaScript cannot be.
    $serverConfig = '';
    $serverOptions = null;
    if ($server) {
        $serverConfig = ', server: true, searchMinLength: '.$searchMinLength.', searchDebounce: '.$searchDebounce
            .', searchTexts: '.\Pushery\WireKit\Support\AlpinePayload::from([
                'searching' => __('wirekit::Searching…'),
                'prompt' => __('wirekit::Type to search'),
                'tooShort' => trans_choice('wirekit::{1} Type at least :count character|[2,*] Type at least :count characters', $searchMinLength, ['count' => $searchMinLength]),
                'truncated' => __('wirekit::More results. Keep typing to narrow them.'),
                'empty' => __('wirekit::No results'),
            ]);
        $serverOptions = \Pushery\WireKit\Support\AlpinePayload::from(['options' => $encodedOptions, 'truncated' => $truncated]);
    }
@endphp

@php
    // The optimistic layer NESTS INSIDE this component, and the direction is not
    // interchangeable: a nested Alpine component's method reads and writes its
    // parent's properties through `this`, never the other way around. So it has
    // to be the child to reach `selected`, and the options have to be inside it
    // to reach its `run()`.
    //
    // `after: '_afterToggle'` is the rest of what a pick does — clearing the
    // filter and restoring focus — and it runs on the rollback too, because
    // those are the same courtesy either way.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'selected',
        'after' => '_afterToggle',
        // The field's own error region. Without it the layer's generic "Could not save"
        // is the only thing a listener hears, and it BEATS the specific message the server
        // sent — the whole point of the arbitration is that a specific message wins, and it
        // cannot run against a region nobody pointed at.
        'errorRegion' => '#'.$id.'-error',
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

    // A caller's `x-ref` belongs to the caller's component. The caller's attributes land on our
    // root, and a root registers a ref on itself, where the caller's `$refs` never reads it.
    // The name moves to `x-wk-ref`, which registers the root on the root above
    // `data-wk-ref-scope` (resources/js/utils/caller-ref.js).
    $callerRef = trim((string) $attributes->get('x-ref', ''));
    $attributes = $attributes->except('x-ref');
@endphp

<div {{ $outerAttributes }} class="space-y-1.5 min-w-0" @if($callerRef !== '') data-wk-ref-scope @endif>
    @if($label)
        <x-wirekit::label :for="$fieldId" :required="$required">{{ $label }}</x-wirekit::label>
    @endif

    {{-- `x-modelable` is what makes `wire:model` work here, and without it the control
         failed in the direction that looks like success: the pills confirmed the choice to
         the reader while the server never heard about it. A filter looked set and filtered
         nothing.

         The selection lives only in Alpine and reaches a classic form POST through hidden
         inputs, which is a different contract: `wire:model` on a non-input root listens for
         an `input` event from the subtree, and there was none to hear. `x-modelable` is the
         bridge Alpine provides for exactly this, so the array becomes bindable both to
         Livewire and to a plain `x-model` in an Alpine page.

         Both payloads go through AlpinePayload rather than json_encode, and that is not
         interchangeable here: an option label is developer text, and a plain encode escapes
         non-ASCII as `\u00fc`, which some of the CSP tokenizers Alpine and Livewire ship do
         not decode: `Grüße` would arrive in the listbox as `Gru00fce`, with nothing thrown.
         `Support/AlpinePayload.php` names the versions. --}}
    <div
        {{ $attributes->except('aria-describedby')->class(['relative']) }}
        @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
        x-modelable="selected"
        {{-- In server mode the options are read from the attribute below, so they are not sent twice. --}}
        x-data="wirekitMultiSelect({ options: {{ $server ? '[]' : \Pushery\WireKit\Support\AlpinePayload::from($encodedOptions) }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::string($name) }}, value: {{ \Pushery\WireKit\Support\AlpinePayload::from($selectedValues) }}, id: {{ \Pushery\WireKit\Support\AlpinePayload::string($id) }}, fieldId: {{ \Pushery\WireKit\Support\AlpinePayload::string($fieldId) }}, placement: {{ \Pushery\WireKit\Support\AlpinePayload::string($placement) }}, panelWidth: {{ \Pushery\WireKit\Support\AlpinePayload::string($panelWidth) }}{{ $serverConfig }}{{ $listConfig }} })"
        @if($listLayout) data-wk-multi-select-layout="list" @endif
        @if($serverOptions !== null) data-wk-server-options="{{ $serverOptions }}" @endif
        @click.away="dropdownOpen = false"
        @keydown.escape="dropdownOpen = false"
    >
        @if($optimisticConfig)
            {{-- `display: contents` so the panel keeps `relative` above it as its
                 containing block — an extra box here would move the dropdown. --}}
            <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
        @endif

        {{-- Hidden inputs for form submission --}}
        <template x-for="(val, i) in selected" :key="i">
            <input type="hidden" :name="{{ \Pushery\WireKit\Support\AlpinePayload::string($name.'[]') }}" :value="val" />
        </template>

        @if($listLayout)
            {{-- The search field. Its text is sent the way the dropdown's is; there is no panel to
                 open, so it is a search field rather than a combobox. --}}
            <input
                type="search"
                id="{{ $fieldId }}"
                x-ref="filterInput"
                x-model="filter"
                @input="onListInput()"
                aria-controls="{{ $id }}-results"
                @if($required) aria-required="true" @endif
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @if($fieldAriaLabel !== null) aria-label="{{ $fieldAriaLabel }}" @endif
                placeholder="{{ $placeholder }}"
                {{-- `wk-field` is outside `resolveClasses()` so a personalization cannot take it off:
                     it holds the font-size floor that keeps iOS from zooming the page on focus. --}}
                class="wk-field {{ $listSearchClasses }} {{ $stateClasses }}"
            />

            {{-- The options, open, each with a checkbox. A checkbox is a control of its own, reached
                 with Tab and flipped with Space, and it says whether its option is chosen, so a
                 chosen option stays in the list rather than leaving it. --}}
            <div
                id="{{ $id }}-results"
                role="group"
                aria-label="{{ $resolvedAriaLabel }}"
                @if($server) x-bind:aria-busy="searchAriaBusy()" @endif
                class="mt-[var(--space-wk-xs)] [overflow-wrap:anywhere]"
            >
                <ul data-wk-prose-skip role="list" class="m-0 p-0 list-none" style="list-style: none; margin: 0; padding: 0;">
                    <template x-for="(opt, idx) in listOptions" :key="opt.value">
                        <li data-wk-prose-skip>
                            <label class="{{ $listRowClasses }}">
                                <input
                                    type="checkbox"
                                    class="peer sr-only"
                                    data-wk-multi-select-option
                                    :value="opt.value"
                                    :checked="selected.includes(opt.value)"
                                    @change="{{ $optimisticConfig ? 'run(nextWith(opt.value))' : 'toggleFromList(opt.value)' }}"
                                />
                                <span class="{{ $listBoxClasses }}" aria-hidden="true">
                                    <svg class="hidden group-has-[:checked]:block pointer-events-none w-full h-full p-0.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                                </span>
                                <span class="flex min-w-0 flex-col">
                                    <span x-text="opt.label"></span>
                                    <span x-show="opt.description" x-text="opt.description" class="text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]"></span>
                                </span>
                            </label>
                        </li>
                    </template>
                </ul>
                <p data-wk-prose-skip
                    x-show="listOptions.length === 0"
                    @if($server) x-text="searchEmptyText(filter)" @endif
                    class="{{ $emptyRowClasses }}"
                >{{ __('wirekit::No results') }}</p>
                @if($server)
                    <p data-wk-prose-skip
                        x-show="listOptions.length > 0 && searchNote() !== ''"
                        x-text="searchNote()"
                        class="{{ $emptyRowClasses }}"
                    ></p>
                @endif
            </div>

            {{-- The choice, under the options, with a button to take each one out. A chosen option
                 keeps its name here after the results move on to another search. --}}
            <div x-show="selected.length > 0" class="mt-[var(--space-wk-sm)] space-y-1">
                <p data-wk-prose-skip class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]" x-text="selectionHeading"></p>
                <ul data-wk-prose-skip role="list" class="m-0 p-0 list-none flex flex-col gap-[var(--gap-wk-xs)]" style="list-style: none; margin: 0; padding: 0;">
                    <template x-for="(val, i) in selected" :key="'chosen-'+val">
                        <li data-wk-prose-skip class="{{ $listChosenClasses }}">
                            <span class="min-w-0" x-text="pillLabel(val)"></span>
                            <button
                                type="button"
                                data-wk-multi-select-remove
                                @click="{{ $optimisticConfig ? 'run(nextWith(val))' : 'removeFromList(val)' }}"
                                :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Remove :name')) }}.replace(':name', getLabel(val))"
                                class="wk-touch-target relative p-1 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
                            >
                                <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                            </button>
                        </li>
                    </template>
                </ul>
            </div>
        @else
        {{-- Input container with pills. `wk-field-frame` is outside `resolveClasses()` on purpose:
             on a coarse pointer the frame takes the 44px touch floor and the text input inside gives
             its own up, and a developer who restyles the base classes must not lose that. --}}
        <div
            x-ref="field"
            class="{{ $containerClasses }} {{ $stateClasses }} wk-field-frame"
            @click="focusAndOpen()"
        >
            {{-- Selected value pills --}}
            <template x-for="(val, i) in selected" :key="'pill-'+val">
                <span class="{{ $pillClasses }}">
                    {{-- The medium in a pill is smaller than in a row, since a pill is one short line. --}}
                    @if($optionUses['media'])
                        <template x-for="chosen in pillMedia(val)" :key="chosen.value">
                            @include('wirekit::components.partials.listbox-option-media', [
                                'option' => 'chosen',
                                'boxClasses' => 'size-5',
                                'iconClasses' => 'size-3.5',
                                'initialsClasses' => 'text-[length:var(--text-wk-2xs)]',
                            ])
                        </template>
                    @endif
                    <span x-text="pillLabel(val)"></span>
                    <button
                        type="button"
                        {{-- run(nextWith(val)), not deselect(val): removing a pill is
                             the same server mutation as picking one, so it takes
                             the same path and is undone the same way. --}}
                        @click.stop="{{ $optimisticConfig ? 'run(nextWith(val))' : 'deselect(val)' }}"
                        :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Remove :name')) }}.replace(':name', getLabel(val))"
                        class="p-0.5 rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] transition-colors cursor-pointer"
                    >
                        <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 12 12" fill="currentColor"><path d="M3.05 3.05a.5.5 0 01.7 0L6 5.29l2.25-2.24a.5.5 0 01.7.7L6.71 6l2.24 2.25a.5.5 0 01-.7.7L6 6.71 3.75 8.95a.5.5 0 01-.7-.7L5.29 6 3.05 3.75a.5.5 0 010-.7z"/></svg>
                    </button>
                </span>
            </template>

            {{-- Filter text input --}}
            <input
                type="text"
                id="{{ $fieldId }}"
                x-ref="filterInput"
                x-model="filter"
                @focus="dropdownOpen = true"
                {{-- A fresh filter is a fresh list, so the old index means
                     nothing and the highlight restarts at the top. --}}
                @input="openAndReset()"
                @keydown.backspace="onBackspace($event)"
                {{-- The combobox keyboard model. Focus never leaves this input —
                     the options are `role="option"` with no tab stop — so these
                     keys plus `aria-activedescendant` below are the ONLY way a
                     keyboard reaches the list. Home/End move the highlight
                     rather than the caret, matching the sibling combobox; the
                     filter field holds a word or two, and jumping to the first
                     or last option is what the reader is here for.
                     Space is deliberately NOT bound: this is an editable
                     combobox, and a text field that swallows the space bar
                     cannot be typed into. --}}
                @keydown.arrow-down.prevent="openAndMove(1)"
                @keydown.arrow-up.prevent="openAndMove(-1)"
                @keydown.home.prevent="openAtFirst()"
                @keydown.end.prevent="openAtLast()"
                {{-- runIf, not run: Enter also fires with nothing highlighted
                     and on a list the user has closed, and `run(undefined)`
                     would ask the server for a selection nobody made. --}}
                @keydown.enter.prevent="{{ $optimisticConfig ? 'runIf(enterNext())' : 'onEnter()' }}"
                :aria-activedescendant="activeDescendantId"
                role="combobox"
                @if($required) aria-required="true" @endif
                aria-haspopup="listbox"
                aria-expanded="false"
                :aria-expanded="dropdownOpen ? 'true' : 'false'"
                aria-controls="{{ $id }}-listbox"
                aria-autocomplete="list"
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                {{-- Wire an aria-label so WCAG 2.1 AA + axe label-rule are     --}}
                {{-- satisfied even when the parent <x-wirekit::field label="..."> --}}
                {{-- doesn't reach this internal combobox input.                 --}}
                @if($fieldAriaLabel !== null) aria-label="{{ $fieldAriaLabel }}" @endif
                :placeholder="selected.length === 0 ? {{ \Pushery\WireKit\Support\AlpinePayload::string($placeholder) }} : ''"
                class="wk-field flex-1 min-w-[80px] bg-transparent text-[color:var(--color-wk-text)] text-[length:var(--text-wk-md)] placeholder:text-[color:var(--color-wk-text-placeholder)] focus-visible:outline-hidden"
            />
        </div>

        {{-- Dropdown listbox --}}
        {{-- Teleported to the overlay root at the end of <body>. `position: fixed` escapes a clipping ancestor but NOT
             a stacking context — the same trap the combobox and dropdown panels were in. --}}
        <template x-teleport="#wk-overlay-root">
        <div
            {{-- THE MORPH KEY. Livewire identifies a node across an update as
                 `wire:id`, then `wire:key`, then `el.id` — so without this line the
                 id below is the identity, and an id that disagrees between the live
                 node and the incoming template makes the morph SWAP rather than
                 patch: the live node is replaced by a native `cloneNode(true)` with
                 no Alpine expandos, landing in the overlay root, which hangs off
                 <body> in no `x-data`. Alpine's parent walk then finds no scope and
                 `dropdownOpen` resolves against the global object, which does not
                 have it — a `ReferenceError` on every update, from a panel nobody
                 opened, with a stack that names nothing on the page.
                 `$id` falls back to a counted value when the call site supplies
                 neither an id nor a name, and a count can restart when only part
                 of the page re-renders (see `DomId`), so a key derived from it
                 would not agree across renders. STATIC on purpose: the morph patches a teleported node
                 against its own counterpart, one to one, never against a keyed
                 sibling, so several multi-selects on a page do not compete. --}}
            wire:key="wk-multi-select-listbox"
            {{-- Open is open, whether or not anything matched. A panel that hid
                 itself on an empty list would make a filter that matched nothing
                 and a filter not typed yet look identical — no panel either way —
                 and leave the documented "No results" state nowhere to appear. --}}
            x-show="dropdownOpen"
            x-transition:enter="transition ease-out duration-[var(--transition-wk-duration)]"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-[var(--transition-wk-duration)]"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-ref="panel"
            id="{{ $id }}-listbox"
            role="listbox"
            aria-label="{{ $resolvedAriaLabel }}"
            aria-multiselectable="true"
            {{-- The results on screen answer an older search while a newer one is out. --}}
            @if($server) x-bind:aria-busy="searchAriaBusy()" @endif
            {{-- `[overflow-wrap:anywhere]` lets an option name with no space or hyphen break inside the
                 word instead of widening its row past the panel, which scrolled the list sideways. The
                 combobox panel carries the same class, and its comment has the reasoning. --}}
            class="fixed z-[var(--z-wk-dropdown)] overflow-y-auto [overflow-wrap:anywhere] rounded-[var(--radius-wk-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-lg)] wk-scrollbar{{ $panelWidth === 'auto' ? ' w-max' : '' }}"
            @if($panelWidthStyle !== '') style="{{ $panelWidthStyle }}" @endif
            x-cloak
        >
            <template x-for="(opt, idx) in filteredOptions" :key="opt.value">
                <div
                    role="option"
                    {{-- The id half of the pairing whose other half is
                         `aria-activedescendant` on the input above. Both read
                         `optionId()`, so they cannot drift apart. --}}
                    :id="optionId(idx)"
                    :aria-selected="selected.includes(opt.value) ? 'true' : 'false'"
                    {{-- `data-active` on the row the arrow keys are on, for the forced-colors
                         mark the stylesheet draws through `wk-listbox-option`. --}}
                    :data-active="idx === highlight ? '' : null"
                    class="wk-listbox-option {{ $optionClasses }}"
                    {{-- One binding, because an attribute can only be bound
                         once, and the two conditions are independent: a row can
                         be highlighted, selected, both or neither. The strings
                         come from the resolver above, so a scope can restyle
                         them the way it restyles every other class here. --}}
                    :class="(idx === highlight ? {{ \Pushery\WireKit\Support\AlpinePayload::string($optionHighlightedClasses) }} : '')
                        + ' '
                        + (selected.includes(opt.value) ? {{ \Pushery\WireKit\Support\AlpinePayload::string($optionSelectedClasses) }} : '')"
                    {{-- Pointing at a row makes it the active one, so a pointer
                         and the arrow keys leave the highlight in the same
                         place instead of each keeping their own idea of it. --}}
                    @mouseenter="hoverOption(idx)"
                    {{-- nextWith() returns a NEW array. toggleValue() splices in place,
                         and an in-place mutation gives the layer nothing to
                         snapshot — the rollback would restore the array it had
                         just changed. --}}
                    @click="{{ $optimisticConfig ? 'run(nextWith(opt.value))' : 'toggleValue(opt.value)' }}"
                    @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                    @if($optionUses['descriptions'])
                        :aria-labelledby="optionId(idx) + '-label'"
                        :aria-describedby="opt.description ? optionId(idx) + '-desc' : null"
                    @endif
                >
                    @if($richRows)
                        @include('wirekit::components.partials.listbox-option-content', [
                            'idExpression' => 'optionId(idx)',
                            'media' => $optionUses['media'],
                            'descriptions' => $optionUses['descriptions'],
                            'mediaPerRow' => $server,
                            'mediaBox' => 'size-6',
                            'mediaIcon' => 'size-5',
                            'mediaInitials' => 'text-[length:var(--text-wk-2xs)]',
                            'descriptionClasses' => 'text-[length:var(--text-wk-xs)] text-[color:var(--color-wk-text-muted)]',
                        ])
                    @else
                        <span class="min-w-0 flex-1" x-text="opt.label"></span>
                    @endif
                    {{-- The check of a selected option. `invisible` rather than removed, so the
                         column is there on every row and a label keeps its width when it is
                         picked. `aria-hidden`: the option already says `aria-selected`. --}}
                    <svg aria-hidden="true" class="{{ $optionCheckClasses }}" :class="selected.includes(opt.value) ? '' : 'invisible'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
                </div>
            </template>

            {{-- Empty state. Inside the panel and wearing `role="option"`,
                 which is not decoration: `role="listbox"` may contain only
                 options and groups, so a bare paragraph here would be a list
                 with a stray child. `aria-disabled` says it is not a choice,
                 and it never becomes the active descendant because the
                 highlight indexes `filteredOptions`, which is empty exactly
                 when this row is showing.
                 The sibling combobox solves the same problem with a SECOND
                 teleported panel; here that would be a second `fixed` box for
                 _place() to anchor, and an unanchored one sits at the viewport
                 origin. One panel, two contents, one anchor. --}}
            <p data-wk-prose-skip
                role="option"
                aria-disabled="true"
                x-show="filteredOptions.length === 0"
                {{-- A server search has more to say about an empty list than "No results": a
                     search still out, text too short to send, or no search at all yet. --}}
                @if($server) x-text="searchEmptyText(filter)" @endif
                class="{{ $emptyRowClasses }}"
            >{{ __('wirekit::No results') }}</p>

            @if($server)
                {{-- Under results: a newer search still out, or results the application cut at
                     its limit. The same kind of row as the empty state, for the same reason. --}}
                <p data-wk-prose-skip
                    role="option"
                    aria-disabled="true"
                    x-show="filteredOptions.length > 0 && searchNote() !== ''"
                    x-text="searchNote()"
                    class="{{ $emptyRowClasses }}"
                ></p>
            @endif
        </div>
        </template>
        @endif

        {{-- Selection announcer. Outside the listbox — a live region is not an
             option — and present from the first render, carrying whatever the
             `value` prop seeded: a region that arrives together with its text
             is a new node, and nothing is announced at all.
             It exists because the announcement a combobox normally gets for
             free cannot happen here. `filteredOptions` DROPS an option the
             moment it is chosen, so the `aria-selected` flip a reader would
             hear happens on a row that has stopped existing.
             Not rendered on the optimistic path, and that is the arbitration
             rather than an omission: there the pick is already announced,
             hedged, by the optimistic layer, and a second voice on the success
             path is what makes a rollback indistinguishable from a
             confirmation. One speaker per pick, whichever one is there. --}}
        {{-- The list layout needs none: its checkbox stays where it is and says its own state. --}}
        @unless($optimisticConfig || $listLayout)
            <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="selectionAnnouncement"></div>
        @endunless

        @if($server)
            {{-- The search status, spoken: the panel's rows are options a reader arrows through,
                 and a row that says "Searching" is only read when the keyboard lands on it.
                 Present from the first render for the reason the announcer above gives. --}}
            <div class="sr-only" aria-live="polite" aria-atomic="true" x-text="searchAnnouncement({{ $listLayout ? 'listOptions' : 'filteredOptions' }}.length, filter)"></div>
        @endif

        {{-- The symbols the option icons point at, rendered with the component so a Livewire
             update keeps them; a `<use>` reference reaches them from the teleported panel. --}}
        {{ $iconSprite }}

        @if($optimisticConfig)
            {{-- Outside the listbox — a live region is not an option — and inside
                 the optimistic scope. Rendered unconditionally and starting
                 empty: a region that arrives together with its text is a new
                 node, and nothing is announced at all. --}}
            <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
            </div>
        @endif
    </div>

    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
