{{-- optimistic-ui: n/a — presentational
     Renders no interactive element of its own — the control arrives in the slot —
     so there is no action here whose result could be shown early. --}}
@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    // Explained in a tooltip from a question mark beside the label (partials/field-help).
    'help' => null,
    'error' => null,
    // When true (default), the error message renders as an ARIA live region
    // (aria-live="polite") so a validation error that appears dynamically — e.g.
    // after a Livewire round-trip — is announced by screen readers without the
    // focus having to return to the field. Set false when the surrounding page
    // runs its own live region for form errors (avoids a double announcement).
    // The aria-describedby link to the field is unaffected either way.
    'announceError' => null,
    'required' => false,
    'for' => null,
    'orientation' => 'vertical', // vertical (label above) | horizontal (label beside)
    'scope' => null,
])

{{-- Whether the surrounding row lines its fields up on a shared grid. Read from the parent
     rather than passed, because a field cannot see its siblings and the decision belongs to
     whoever composed the row. `alignFields` is a name no other component declares, which
     matters: @aware matches on the prop NAME, so a generic one would also answer from a
     `hero` or a `data-list` that happens to be an ancestor. --}}
@aware(['alignFields' => false, 'announceErrors' => null, 'wkFieldSet' => null])

@php
    $inAlignedRow = \Pushery\WireKit\Support\BooleanProp::from($alignFields, false);

    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('field', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $required = BooleanProp::from($required, false, 'field', 'required');

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'alignFields', 'align-fields', 'wkFieldSet', 'wk-field-set']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError = BooleanProp::from($announceError ?? $announceErrors ?? config('wirekit.a11y.announce_error', true), true);

    use Pushery\WireKit\WireKit;

    $orientationValue = match ($orientation) {
        'vertical', 'horizontal' => $orientation,
        default => WireKit::validateProp('field', 'orientation', $orientation, ['vertical', 'horizontal']),
    };
    $isHorizontal = $orientationValue === 'horizontal';

    // The `for` attribute links the label to its input. If not explicitly given,
    // we fall back to `name` so the wrapped input's auto-generated id (= name) matches. An id
    // holds no whitespace, so the name `first name` is the id `first-name`, as the input writes it
    // (Support\DomId::valid()), and the hint, help and error ids built from it name one element each.
    $targetId = \Pushery\WireKit\Support\DomId::valid(($for ?? $name) !== null ? (string) ($for ?? $name) : null);

    // A composite control in the slot gives its own field an id of its own, so `name` would name
    // nothing. Such a control takes the label through `wkField` (Support\FieldControl, built by the
    // class behind this view before the slot rendered) and records the element a label can name:
    // the label points there, or names by reference only when the control has no such element.
    $fieldControl = isset($wkField) && $wkField instanceof \Pushery\WireKit\Support\FieldControl ? $wkField : null;
    $labelFor = $fieldControl !== null ? $fieldControl->forId() : $targetId;
    $labelId = $fieldControl?->labelId();

    // Error detection: explicit prop OR Laravel validation bag (keyed by `name`), through the set
    // around the field when there is one, whose message its key is (Support\FieldGroup::entryMessage()).
    $errorMessage = \Pushery\WireKit\Support\FieldGroup::entryMessage($wkFieldSet, $name ? ($errors ?? null) : null, $name, $error);
    // A WireKit control in the slot reads the bag under its own name as well and shows the message
    // under itself, as the whole text of its `…-error` paragraph, linked to the control. When the
    // slot already shows this message there, the wrapper does not repeat it: the reader meets it
    // once, attached to the control, and the error id both would write under the same key appears
    // once. A slot that does not show it, such as a control of the caller's own, still gets the
    // message from here. Matching the paragraph rather than the text anywhere keeps a short message
    // from being found in an attribute or a hint.
    $errorPattern = $errorMessage === null ? null : '/\sid="[^"]*-error"[^>]*>\s*'.preg_quote((string) e($errorMessage), '/').'\s*</';
    $slotShowsError = $errorPattern !== null && preg_match($errorPattern, (string) $slot) === 1;
    $hasError = $errorMessage !== null && ! $slotShowsError;
    // The hint gives way to an error the slot shows, as it gives way to the wrapper's own: below a
    // control there is one of the two, never both.
    $showsHint = $hint && ! $slotShowsError;

    // Stable IDs on the hint/error paragraphs, so a caller can point an
    // `aria-describedby` at them. A Blade slot is opaque, so this wrapper cannot add an
    // attribute to the control it was handed; a WireKit control reaches up instead and
    // links the hint through `wkField` (Support\FieldControl). When that control's own
    // hint carries `{target}-hint` already, the wrapper's moves to `{target}-field-hint`,
    // so no id is written twice. Markup the caller wrote references the offered id by hand.
    $hintId = $fieldControl !== null ? $fieldControl->hintParagraphId() : ($targetId ? "{$targetId}-hint" : null);
    // The help's hidden copy takes a stable id on the same terms: a WireKit control links it after
    // the hint, and markup of the caller's own references the offered id by hand.
    $helpId = filled($help) ? ($fieldControl !== null ? $fieldControl->helpParagraphId() : ($targetId ? "{$targetId}-help" : null)) : null;
    $errorId = $targetId ? "{$targetId}-error" : null;

    // Wrapper spacing — vertical stacks (space-y); horizontal lets the inner flex row drive layout.
    // In both, a typed token with no break point in a label, a hint or an error, an email address
    // or a URL, breaks rather than running out of the field. `break-word` leaves every
    // min-content width alone, so the horizontal row keeps the layout it had.
    $wrapperClasses = WireKit::resolveClasses('field', 'base', ($isHorizontal ? '' : 'space-y-1.5 ').'[overflow-wrap:break-word]', $scope);
@endphp

{{-- `data-wk-field` is how the shared row grid tells a field from a submit button: the
     first takes all three rows and hands its parts to them, the second sits on the control
     row alone. Emitted always, not only inside such a row — an identity hook that appears
     conditionally is one nothing else can ever rely on. --}}
<div data-wk-field {{ $attributes->class(\Pushery\WireKit\Support\UtilityClasses::yieldToCaller($attributes, [$wrapperClasses])) }}>
    @if($isHorizontal)
        {{-- Horizontal: label in a left column beside the control; control + messages
             take the remaining inline space. --}}
        <div class="flex items-start gap-[var(--padding-wk-x-lg)]">
            @if($label)
                <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name ?? $targetId" :for="$labelFor" :id="$labelId" :required="$required" :scope="$scope" class="w-1/3 shrink-0 pt-[var(--padding-wk-y-sm)]">
                    {{ $label }}
                    <x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after>
                </x-wirekit::label>
            @endif
            <div class="flex-1 min-w-0 space-y-1.5">
                {{ $slot }}
                @if($hasError && $errorMessage)
                    <p data-wk-prose-skip @if($errorId) id="{{ $errorId }}" @endif @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
                @elseif($showsHint)
                    <p data-wk-prose-skip @if($hintId) id="{{ $hintId }}" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
                @endif
                @if(\Pushery\WireKit\Support\SlotContent::given($after ?? null))
                    <div data-wk-field-after>{{ $after }}</div>
                @endif
            </div>
        </div>
    @else
        {{-- Vertical (default): label above the control. --}}
        @if($label)
            <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name ?? $targetId" :for="$labelFor" :id="$labelId" :required="$required" :scope="$scope">
                {{ $label }}
                <x-slot:after>{{ $labelAfter ?? '' }}</x-slot:after>
            </x-wirekit::label>
        @elseif($inAlignedRow)
            {{-- A field with no label still needs to occupy the label row, or it slides up
                 into it and its control stops lining up with its neighbors'. Empty and
                 aria-hidden — it is spacing, and there is no name here to announce. --}}
            <span data-wk-field-part="label" aria-hidden="true"></span>
        @endif

        {{-- The actual input/select/textarea/checkbox — passed in as default slot.
             The child component reads its own $errors bag and sets its own aria-*; the
             wrapper renders its own error/hint messages with stable IDs for a caller to
             reference. The two are alternatives, not halves of one wiring — see the note
             on $hintId above. --}}
        {{ $slot }}

        {{-- Error takes precedence over hint — show one, not both --}}
        @if($inAlignedRow && ! ($hasError && $errorMessage) && ! $showsHint)
            {{-- The other half of the same problem: a field with nothing below its control
                 would end one row short, and the shared grid would pull the next field's
                 control up to fill the gap. --}}
            <span data-wk-field-part="message" aria-hidden="true"></span>
        @endif

        @if($hasError && $errorMessage)
            <p data-wk-prose-skip @if($errorId) id="{{ $errorId }}" @endif @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">
                {{ $errorMessage }}
            </p>
        @elseif($showsHint)
            <p data-wk-prose-skip @if($hintId) id="{{ $hintId }}" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
                {{ $hint }}
            </p>
        @endif

        {{-- What follows the field in its cell, a button under a select for example. It is no part
             of the control's name or description. In a row with `align-fields` it takes a fourth
             track of its own (dist/wirekit.css), so the controls and messages of the row stay on
             their lines. --}}
        @if(\Pushery\WireKit\Support\SlotContent::given($after ?? null))
            <div data-wk-field-after>{{ $after }}</div>
        @endif
    @endif
</div>
