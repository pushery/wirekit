{{-- optimistic-ui: supported
     A wire:model.live round trip on a checkbox is the mutation case, and the
     browser has already flipped the box by the time any handler runs. What the
     layer adds is confirmation, the undo, and the announcement. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. Null renders the plain checkbox.
    // Needs `wirekit-optimistic.js` on the page beside the bundle, as
    // docs.wirekit.app/extending/optimistic-ui shows; without it no `wirekitOptimistic` exists.
    'optimistic' => null,
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer spreads `args` into the call.
    'optimisticArgs' => [],
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    // Render the label sr-only (kept as the control's accessible name) — for a
    // checkbox in a table column whose header already names it, so the visible
    // label is redundant. Mirrors input / select / textarea / combobox `hideLabel`.
    'hideLabel' => false,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
    'error' => null,
    'indeterminate' => false,
    // Take the surrounding field.set's group error, or decline it. A control that belongs to
    // the group but neither causes the rejection nor can resolve it says `:group-error="false"`
    // and is then neither announced as invalid nor described by the group's message.
    'groupError' => true,
    'size' => config('wirekit.components.checkbox.size', 'md'),
    // 'default' (inline control + label) or 'card' (the whole bordered card is the
    // clickable target and highlights when checked — the selectable-option pattern).
    'variant' => config('wirekit.components.checkbox.variant', 'default'),
    'scope' => null,
])

@aware(['announceErrors' => null, 'wkFieldSet' => null, 'wkField' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $indeterminate = BooleanProp::from($indeterminate, false, 'checkbox', 'indeterminate');
    $hideLabel = BooleanProp::from($hideLabel, false, 'checkbox', 'hideLabel');

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'wkFieldSet', 'wk-field-set', 'wkField', 'wk-field']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);
    // A list where the field takes one value, as `old()` gives one after a request that sent
    // `name[]`, is reported and left off the field (Support\FieldValue).
    $attributes = \Pushery\WireKit\Support\FieldValue::withoutLists($attributes, 'checkbox', ['value', 'checked']);


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('checkbox', $attributes->getAttributes());

    // An unknown `size` is reported (Support\StrictnessGate) and renders as `md`; a bound `null` or
    // empty string is `md` without a word.
    $size = \Pushery\WireKit\WireKit::validateProp('checkbox', 'size', \Pushery\WireKit\Support\NumericProp::text($size ?: 'md'), ['md', 'sm', 'lg']);

    // A checkbox takes a number from its model for its value rather than its state, so a box
    // bound to one shows unchecked whatever it holds: a binding to one warns in debug mode
    // (Support\CheckboxModel).
    \Pushery\WireKit\Support\CheckboxModel::warn('checkbox', $attributes);

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // `lang` names the language of the label's words, a language picker's "Deutsch" or "Français",
    // so it goes on the <label>, which holds the text and the field alike. On the field alone the
    // text would keep the page's language, and a screen reader would say it in the page's voice
    // (WCAG 3.1.2).
    $labelLang = \Pushery\WireKit\Support\LanguageTag::of($attributes->get('lang'));
    $attributes = $attributes->except('lang');

    // Size scale (aligned with toggle/radio): sets the box w/h. The check +
    // indeterminate overlays are nested inside the box and fill it (w-full h-full),
    // so they need no size of their own and stay centered in every variant.
    // Lenient match-with-default mirrors the sibling toggle component.
    $sizing = match ($size) {
        'sm' => 'w-4 h-4',
        'lg' => 'w-6 h-6',
        default => 'w-5 h-5',
    };

    // Where the box stands beside the first line of its text: their middles meet, whichever of the
    // two is taller. The line is `leading-tight` of `--text-wk-md`, the size of the text block
    // below, so the box steps down by half of what that line exceeds it, and the text by half of
    // what the box exceeds its own line (`1lh`). A longer text keeps its first line beside the box,
    // and content in the slot that keeps to the line's height, a swatch in an inline row before a
    // name for one, stands centered with it.
    $alignment = match ($size) {
        'sm' => ['box' => 'mt-[max(0px,calc((var(--text-wk-md)_*_1.25_-_1rem)_/_2))]', 'text' => 'pt-[max(0px,calc((1rem_-_1lh)_/_2))]'],
        'lg' => ['box' => 'mt-[max(0px,calc((var(--text-wk-md)_*_1.25_-_1.5rem)_/_2))]', 'text' => 'pt-[max(0px,calc((1.5rem_-_1lh)_/_2))]'],
        default => ['box' => 'mt-[max(0px,calc((var(--text-wk-md)_*_1.25_-_1.25rem)_/_2))]', 'text' => 'pt-[max(0px,calc((1.25rem_-_1lh)_/_2))]'],
    };

    $variantValue = match ($variant) {
        'default', 'card' => $variant,
        default => WireKit::validateProp('checkbox', 'variant', $variant, ['default', 'card']),
    };
    // Card variant: the <label> becomes a bordered card that reacts to its inner
    // input via :has() (in the WireKit browser baseline) — accent border + tinted
    // surface when checked, focus ring when the input is focus-visible.
    // `group` so the check/indeterminate overlays (nested in the box, not siblings
    // of .peer) can toggle via group-has-[:checked] / group-has-[:indeterminate].
    //
    // align-top on the default (inline-flex) label kills a sub-pixel layout shift on
    // toggle: an inline-flex label is placed in its line box by its baseline, and the
    // box's flex baseline shifts when the checkmark SVG flips display none↔block — on a
    // 2× display that re-rounds the whole label by a sub-pixel, pulling the next row
    // closer. align-top positions the label by its TOP edge instead, independent of the
    // changing baseline, so the row stays put. The card variant is block-level `flex`
    // (no line-box baseline) and is unaffected.
    //
    // Routed through the class seam, like the box below, and that is the whole point of
    // the two `resolveClasses` calls here. The default label is `inline-flex`, so it is as
    // wide as its own content: a `flex-1` in the slot has nothing to stretch against and a
    // right-aligned second element lands behind the first instead of at the row's edge. The
    // commonest list form an application builds — control, name on the left, code or count
    // on the right edge — needs the label wrapper and its text to be overridable, so both go
    // through `resolveClasses` as the box does.
    //
    // Passing `class="flex w-full"` on the tag is not a way around it: the attribute bag is
    // spread onto the `<input>`, not onto this `<label>`, so it never reaches the element
    // whose display mode is in question.
    //
    // Both variants share one block, matching how `base` below serves both — an override
    // that wants to touch only one reads `$variantValue` from a closure, or scopes itself.
    $labelClasses = WireKit::resolveClasses('checkbox', 'label', $variantValue === 'card'
        ? 'group flex items-start gap-3 cursor-pointer relative w-full rounded-[var(--radius-wk-lg)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] transition-colors duration-[var(--transition-wk-duration)] has-[:checked]:border-[var(--color-wk-accent)] has-[:checked]:bg-[var(--color-wk-bg-hover)] has-[:focus-visible]:ring-[length:var(--ring-wk-width)] has-[:focus-visible]:ring-[var(--color-wk-ring)]'
        : 'group inline-flex items-start gap-2 cursor-pointer relative align-top', $scope);

    // The visible label text. Its size is `--text-wk-md` whatever the `size` prop says, which
    // sizes the box and with it how far the text steps down beside it (`$alignment` above) —
    // routing it through the seam is what makes it adjustable, and it is
    // the surface a row layout needs (`min-w-0 flex-1` on this span is
    // what gives a trailing element the edge to sit against).
    //
    // `sr-only` for `hideLabel` is appended OUTSIDE the seam on purpose: an override replaces
    // the whole block, and folding the visibility switch into it would let a personalization
    // silently unhide a label that a call site asked to hide.
    $textClasses = WireKit::resolveClasses(
        'checkbox',
        'text',
        'text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none leading-tight '.$alignment['text'],
        $scope,
    );

    // A card's description under its name, and what stands at the end of its row (see $nameId
    // below), each under a block of its own.
    $descriptionClasses = WireKit::resolveClasses('checkbox', 'description', 'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]', $scope);
    $trailingClasses = WireKit::resolveClasses('checkbox', 'trailing', 'ms-auto shrink-0 self-center', $scope);

    // The default label is a row, as on the radio: a tap on its text toggles the box. `wk-choice-row`
    // gives it a hit area above and below its text under a coarse pointer, which moves nothing, so a
    // phone keeps the spacing a desk has, and on a `data-wk-touch` surface it makes the label a row
    // of the touch target with one line in the middle. It sits outside the class seam, so a
    // personalization of `checkbox.label` keeps it. Only with a visible text: a hidden label leaves
    // the box alone, and the box answers a finger through the hit area of `wk-touch-target`.
    // The condition is the one the text below renders visibly under.
    $rowMarkers = $variantValue !== 'card' && ! $hideLabel && ($slot->hasActualContent() || $label) ? 'wk-choice-row ' : '';

    // Resolve a page-unique DOM id. Array-style names ("tags[]") dedup on their
    // base, plain duplicate names get a -2/-3 suffix, and the first occurrence keeps
    // the clean id — so <label for> always targets the right checkbox even when a
    // group or two forms share a name. Shared with every other control via
    // Support\DomId; the form key `name` stays duplicated as required.
    // The bag holds a bound name escaped once; read as the text it stands for, so the field is
    // sent under the name the caller bound (Support\AttributeText).
    $rawName = \Pushery\WireKit\Support\AttributeText::get($attributes, 'name');
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $rawName, 'checkbox-');
    // Without a `name` the field is sent under the caller's `id`, and without either it is not
    // sent at all: an id DomId counted out names nothing a form should carry.
    $name = $rawName ?? (filled($attributes->get('id')) ? $id : '');
    // Without a `name`, the bag files this field's error under the property its `wire:model`
    // binds (FieldError::nameOf).
    $errorName = \Pushery\WireKit\Support\FieldError::nameOf($rawName, $attributes) ?? $name;

    // The field.set around this checkbox, when there is one. It answers for its group: a
    // message that belongs to all of its controls renders once, and every control points at it.
    $fieldGroup = $wkFieldSet instanceof \Pushery\WireKit\Support\FieldGroup ? $wkFieldSet : null;

    // A field around this control with its error off turns it off here too (Support\FieldControl::errorOf()).
    $error = \Pushery\WireKit\Support\FieldControl::errorOf($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $error);

    // Error detection: explicit prop OR Laravel validation bag. A bag entry under a key the
    // group answers for is the group's message, not this checkbox's; rendered here as well, it
    // would repeat under every control of the group.
    $groupOwnsBagEntry = ! $error && ($fieldGroup?->covers($errorName) ?? false);
    $errorMessage = \Pushery\WireKit\Support\FieldError::message($groupOwnsBagEntry ? null : ($errors ?? null), $errorName, $error);
    $hasError = $errorMessage !== null;

    // Whether this control answers for the group's message.
    //
    // A group error can be true of SOME of its controls: three checkboxes under one caption,
    // and a rejection that only two of them can resolve. Announced on all three, the reader who
    // reaches the third hears "invalid" and a sentence that ticking it will not satisfy. The
    // group still owns its bag entry either way — `covers()` is not gated on this — so a
    // declining control does not start printing the group's message under itself.
    $groupError = BooleanProp::from($groupError, true, 'checkbox', 'groupError');

    // Invalid on its own error or on its group's.
    $isInvalid = $hasError || ($groupError && ($fieldGroup?->isInvalid($errors ?? null) ?? false));

    // aria-describedby: our own hint/error target, the group's, and any caller-supplied value,
    // merged into ONE attribute. Two separate aria-describedby attributes would make the
    // browser keep the first and silently drop the other association (WCAG). The order lives
    // in FieldGroup::describedBy, shared with radio and toggle.
    $describedBy = \Pushery\WireKit\Support\FieldGroup::describedBy(
        $fieldGroup,
        $errors ?? null,
        $hasError ? $id.'-error' : null,
        $hint ? $id.'-hint' : null,
        $attributes->get('aria-describedby'),
        $groupError,
    );
    // The field's help, after its own messages: what the field is for (partials/field-help).
    // Only with a label to stand beside, which is also where its hidden copy is rendered.
    $helpLabel = $slot->hasActualContent() ? trim(html_entity_decode(strip_tags((string) $slot), ENT_QUOTES | ENT_HTML5)) : (string) ($label ?? '');
    $helpId = filled($help) && $helpLabel !== '' ? $id.'-help' : null;
    // Markup a caller puts after the label and its help, in their row (partials/label-after).
    $hasLabelAfter = isset($labelAfter) && $labelAfter->hasActualContent();
    // The control sits inside the label, so the row around it stands whenever the slot is passed,
    // filled or not: a mark that comes and goes between two renders then leaves the markup around
    // the control as it was.
    $labelAfterGiven = isset($labelAfter);
    // A `field` around this control renders its hint below it, and the control is described by it
    // as by a hint of its own (Support\FieldControl).
    $describedBy = \Pushery\WireKit\Support\FieldControl::describe($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, $describedBy, $id, $hint ? $id.'-hint' : null, $helpId ?? null);
    // A required `field` around this control hides its asterisk from assistive technology, so
    // the control says it, with `aria-required` and never the browser's own check
    // (Support\FieldControl::requiredBy()).
    $fieldRequired = \Pushery\WireKit\Support\FieldControl::requiredBy($wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null, (bool) $attributes->get('required', false));
    $describedBy = trim(($describedBy ?? '').' '.($helpId ?? '')) ?: null;

    // A card holds what describes its option inside its frame: the hint under the name, and
    // `trailing` at the end of the row, for a logo, an image or a price. The frame is the <label>,
    // whose whole text would be the checkbox's name, so with either of them the checkbox takes its
    // name from the label's text alone, through `aria-labelledby`, unless the caller names it.
    $cardHint = $variantValue === 'card' && filled($hint);
    $hasTrailing = isset($trailing) && $trailing->hasActualContent();
    if ($hasTrailing && $variantValue !== 'card') {
        \Pushery\WireKit\Support\StrictnessGate::reject('checkbox', 'trailing', 'a slot outside a card', 'the slot on a checkbox with variant="card"', 'not rendered');
        $hasTrailing = false;
    }
    $nameId = ($cardHint || $hasTrailing) && ($slot->hasActualContent() || filled($label)) && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby')
        ? $id.'-label'
        : null;
    // The description stands in the card in every state, so it describes the option beside an
    // error as well, after it.
    if ($cardHint && $hasError) {
        $describedBy = trim(($describedBy ?? '').' '.$id.'-hint');
    }

    // Visual box styling. The <input> uses .peer + .sr-only, and this box listens
    // to peer-checked / peer-focus-visible / peer-disabled via sibling selectors.
    // A 2.75rem hit area centered on the box, which is 20px in the default size. The
    // library ships this primitive, and the theme-controller consumes it too.
    //
    // On the box, not on the <label>, and that placement is the whole decision. The
    // default label is the box plus its text, often many times wider than tall, so a hit
    // area centered there would sit over the words rather than over the control.
    //
    // DEFAULT VARIANT ONLY, which is the counter-check this needs. In `card` the <label>
    // IS the target: a bordered, full-width, already-tall clickable card. Hanging an
    // absolutely-positioned 44px area off the box inside it would push roughly 12px past
    // the card's own edge, so taps in the gap BETWEEN two cards would land on the upper
    // card's checkbox. The card variant needs no reserve and must not grow one.
    //
    // This is about the dense case, not a lone box: axe reports no `target-size` violation
    // for a lone box, because 2.5.8's spacing exception counts the free room around the
    // control. Conformance then rests on nothing being placed beside it, and in a dense
    // list that room is exactly what runs out.
    // The marker is set in front of the resolved block rather than inside it, so a
    // personalization of the block keeps the hit area.
    $boxClasses = ($variantValue === 'card' ? '' : 'wk-touch-target ').WireKit::resolveClasses('checkbox', 'base', implode(' ', [
        'relative inline-flex items-center justify-center shrink-0',
        $sizing,
        $alignment['box'],
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        // One border color for the state, chosen here rather than appended afterwards: an error
        // class added after the block would lose to the resting one, because both are single
        // classes and the stylesheet sorts `-error` before `-strong`, so an invalid box would
        // stay gray. The
        // hover border belongs to the resting state only, for the same reason the fields' does.
        $isInvalid
            ? 'border-[var(--color-wk-border-error)]'
            : 'border-[var(--color-wk-border-strong)] peer-hover:border-[var(--color-wk-border-strong-hover)]',
        'bg-[var(--color-wk-bg-input)]',
        'peer-checked:bg-[var(--color-wk-accent)]',
        'peer-checked:border-[var(--color-wk-accent)]',
        'peer-indeterminate:bg-[var(--color-wk-accent)]',
        'peer-indeterminate:border-[var(--color-wk-accent)]',
        // A checked or indeterminate box keeps its accent border under the pointer. The hover
        // border above is a LATER rule of the same specificity, so on its own it would recolor
        // the border of a checked box too, and a gray ring would stand around the accent fill.
        // These compound variants outrank it exactly when both states hold.
        'peer-checked:peer-hover:border-[var(--color-wk-accent)]',
        'peer-indeterminate:peer-hover:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'text-[color:var(--color-wk-accent-fg)]',
    ]), $scope);

@endphp

@php
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (bool) ($attributes->get('checked') ?? false),
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

{{-- `data-wk-choice` marks a checkbox whose label is a row, which the stylesheet reads in a field
     set: under a coarse pointer the hit areas of two such rows meet halfway between them, and on a
     touch surface the two rows need no gap. --}}
<div {{ $outerAttributes }} @if($callerRef !== '' || $modelScope) data-wk-ref-scope @endif @if($rowMarkers !== '') data-wk-choice @endif class="relative space-y-1.5 min-w-0" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    {{-- With help, the label shares a row with its button, which may not sit inside the
         label: it would become part of the control's name. --}}
    @if($helpId || $labelAfterGiven)
    <div data-wk-label-row class="{{ $labelAfterGiven ? 'flex flex-wrap items-center gap-x-[var(--gap-wk-sm)] gap-y-[var(--gap-wk-xs)]' : 'flex items-center gap-[var(--gap-wk-xs)]' }}">@if($labelAfterGiven)<span class="{{ $variantValue === 'card' ? 'inline-flex min-w-0 flex-1 items-center gap-[var(--gap-wk-xs)]' : 'inline-flex min-w-0 items-center gap-[var(--gap-wk-xs)]' }}">@endif
    @endif
    <label for="{{ $id }}" @if($labelLang !== null && $labelLang !== '') lang="{{ $labelLang }}" @endif class="{{ $rowMarkers }}{{ $labelClasses }}">
        {{-- Native checkbox: visually hidden but fully accessible + Livewire-compatible.
             Siblings below consume its :checked / :indeterminate / :focus-visible / :disabled state via peer-*. --}}
        <input
            type="checkbox"
            id="{{ $id }}"
            @if($name !== '') name="{{ $name }}" @endif
            {{-- Always emitted, in both states. `indeterminate` is a DOM property with no
                 HTML attribute, so the server cannot render it, and an
                 `x-init="$el.indeterminate = true"` emitted only when true runs once and can
                 only ever turn the state on. The third state practically always arrives
                 after the first render, through a Livewire round trip that morphs the
                 element without re-initializing it, so the box would read as "none
                 selected" while something is. --}}
            data-wk-indeterminate="{{ $indeterminate ? 'true' : 'false' }}"
            x-wk-indeterminate
            @if($isInvalid) aria-invalid="true" @endif
            @if($optimisticConfig)
                x-ref="control"
                @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
            @if($nameId !== null) aria-labelledby="{{ $nameId }}" @endif
            @if($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
            @if($fieldRequired) aria-required="true" @endif
            {{-- `peer sr-only` rides the bag rather than sitting beside it: hardcoded, a
                 caller's own class would become a second class attribute, and the browser
                 keeps only the first. --}}
            {{ $attributes->except('type')->except(['id', 'name', 'aria-describedby'])->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, ['peer', 'sr-only'])) }}
        />

        {{-- Visual box — sibling of .peer (consumes peer-checked bg/border). The
             check + indeterminate overlays are nested HERE and fill the box
             (w-full h-full), flex-centered by the box, so they stay over the box
             in BOTH the default and card variants (card label padding does not
             offset them). They toggle via the label's group-has-[:checked] /
             group-has-[:indeterminate] (a nested element isn't a sibling of .peer,
             so peer-checked can't reach it; the box bg/border still use peer-*). --}}
        {{-- `wk-choice-frame` marks the element whose edge is the control, outside the resolved block so a
             personalization keeps it. Under a preset whose border width is 0px the edge is gone, and
             `.wk-choice-frame { --border-wk-width: 1px; }` in the application's stylesheet brings it back. --}}
        <span class="wk-choice-frame {{ $boxClasses }}" aria-hidden="true">
            {{-- Checkmark --}}
            <svg
                class="hidden group-has-[:checked]:block pointer-events-none w-full h-full p-0.5 text-[color:var(--color-wk-accent-fg)]"
                fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {{-- Indeterminate dash — visible only when input.indeterminate is true --}}
            <svg
                class="hidden group-has-[:indeterminate]:block pointer-events-none w-full h-full p-0.5 text-[color:var(--color-wk-accent-fg)]"
                fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            </svg>
        </span>

@php
    // Read, not consumed: a declared `required` prop would take the attribute out of the bag,
    // and the bag is what delivers it to the native control. A bare `required` arrives as
    // `true`. Read once for both branches: the slot branch is the consent case, and consent
    // checkboxes are exactly the required ones.
    $wkRequiredMarker = (bool) $attributes->get('required', false);
@endphp
        {{-- A card with a description holds the name above it in one column. Each branch holds its
             whole element: one that opened the column and left it to a later branch to close would
             leave Livewire's markers of the first block inside the column. --}}
        @if($cardHint)
        <span class="flex min-w-0 flex-1 flex-col gap-[var(--gap-wk-xs)]">
            @if($slot->hasActualContent())
                <span class="wk-choice-text {{ $textClasses }}{{ $hideLabel ? ' sr-only' : '' }}"@if($nameId !== null) id="{{ $nameId }}"@endif>{{ $slot }}@if($wkRequiredMarker)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</span>
            @elseif($label)
                <span class="wk-choice-text {{ $textClasses }}{{ $hideLabel ? ' sr-only' : '' }}"@if($nameId !== null) id="{{ $nameId }}"@endif>{{ $label }}@if($wkRequiredMarker)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</span>
            @endif
            <span data-wk-choice-description id="{{ $id }}-hint" class="{{ $descriptionClasses }}">{{ $hint }}</span>
        </span>
        @elseif($slot->hasActualContent())
            {{-- Slot-based label: supports rich HTML (links, formatting) for use cases like GDPR consent --}}
            <span class="wk-choice-text {{ $textClasses }}{{ $hideLabel ? ' sr-only' : '' }}"@if($nameId !== null) id="{{ $nameId }}"@endif>{{ $slot }}@if($wkRequiredMarker)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</span>
        @elseif($label)
            <span class="wk-choice-text {{ $textClasses }}{{ $hideLabel ? ' sr-only' : '' }}"@if($nameId !== null) id="{{ $nameId }}"@endif>{{ $label }}@if($wkRequiredMarker)<span class="text-[color:var(--color-wk-danger-text)] ms-0.5" aria-hidden="true">*</span>@endif</span>
        @endif
        @if($hasTrailing)
            <span data-wk-choice-trailing class="{{ $trailingClasses }}">{{ $trailing }}</span>
        @endif
    </label>
    @if($helpId || $labelAfterGiven)
        @if($helpId)
            @include('wirekit::components.partials.field-help', ['helpText' => $help, 'helpName' => $helpLabel, 'helpId' => $helpId, 'helpButton' => ! $hideLabel, 'helpField' => (string) ($name !== '' ? $name : $id)])
        @endif
        @if($labelAfterGiven)</span>@if($hasLabelAfter)@include('wirekit::components.partials.label-after', ['labelAfter' => $labelAfter])@endif @endif
    </div>
    @endif

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    {{-- Error message or hint text. A card's hint stands inside the card, as its description. --}}
    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint && ! $cardHint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
