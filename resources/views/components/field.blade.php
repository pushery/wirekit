{{-- optimistic-ui: n/a — presentational
     Renders no interactive element, so there is no action whose result could be
     shown early. Measured rather than asserted: the guard refutes this reason for
     any file that renders one. --}}
@props([
    'label' => null,
    'name' => null,
    'hint' => null,
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
@aware(['alignFields' => false, 'announceErrors' => null])

@php
    $inAlignedRow = \Pushery\WireKit\Support\BooleanProp::from($alignFields, false);

    use Pushery\WireKit\Support\BooleanProp;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('field', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $required = BooleanProp::from($required, false);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'alignFields', 'align-fields']);
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    $orientationValue = match ($orientation) {
        'vertical', 'horizontal' => $orientation,
        default => WireKit::validateProp('field', 'orientation', $orientation, ['vertical', 'horizontal']),
    };
    $isHorizontal = $orientationValue === 'horizontal';

    // The `for` attribute links the label to its input. If not explicitly given,
    // we fall back to `name` so the wrapped input's auto-generated id (= name) matches.
    $targetId = $for ?? $name;

    // Error detection: explicit prop OR Laravel validation bag (keyed by `name`)
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($name ? ($errors ?? null)?->first($name) : null);

    // Stable IDs for hint/error paragraphs so we can wire up aria-describedby
    $hintId = $targetId ? "{$targetId}-hint" : null;
    $errorId = $targetId ? "{$targetId}-error" : null;

    // Wrapper spacing — vertical stacks (space-y); horizontal lets the inner flex row drive layout.
    $wrapperClasses = WireKit::resolveClasses('field', 'base', $isHorizontal ? '' : 'space-y-1.5', $scope);
@endphp

{{-- `data-wk-field` is how the shared row grid tells a field from a submit button: the
     first takes all three rows and hands its parts to them, the second sits on the control
     row alone. Emitted always, not only inside such a row — an identity hook that appears
     conditionally is one nothing else can ever rely on. --}}
<div data-wk-field {{ $attributes->class([$wrapperClasses]) }}>
    @if($isHorizontal)
        {{-- Horizontal: label in a left column beside the control; control + messages
             take the remaining inline space. --}}
        <div class="flex items-start gap-[var(--padding-wk-x-lg)]">
            @if($label)
                <x-wirekit::label :for="$targetId" :required="$required" :scope="$scope" class="w-1/3 shrink-0 pt-[var(--padding-wk-y-sm)]">
                    {{ $label }}
                </x-wirekit::label>
            @endif
            <div class="flex-1 min-w-0 space-y-1.5">
                {{ $slot }}
                @if($hasError && $errorMessage)
                    <p @if($errorId) id="{{ $errorId }}" @endif @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
                @elseif($hint)
                    <p @if($hintId) id="{{ $hintId }}" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
                @endif
            </div>
        </div>
    @else
        {{-- Vertical (default): label above the control. --}}
        @if($label)
            <x-wirekit::label :for="$targetId" :required="$required" :scope="$scope">
                {{ $label }}
            </x-wirekit::label>
        @elseif($inAlignedRow)
            {{-- A field with no label still needs to occupy the label row, or it slides up
                 into it and its control stops lining up with its neighbors'. Measured on a
                 row of three: one field without a label sat 13.5px above the other. Empty
                 and aria-hidden — it is spacing, and there is no name here to announce. --}}
            <span data-wk-field-part="label" aria-hidden="true"></span>
        @endif

        {{-- The actual input/select/textarea/checkbox — passed in as default slot.
             The child component is expected to read its own $errors bag and set aria-* itself,
             but the wrapper still renders its own error/hint messages with stable IDs. --}}
        {{ $slot }}

        {{-- Error takes precedence over hint — show one, not both --}}
        @if($inAlignedRow && ! ($hasError && $errorMessage) && ! $hint)
            {{-- The other half of the same problem: a field with nothing below its control
                 would end one row short, and the shared grid would pull the next field's
                 control up to fill the gap. --}}
            <span data-wk-field-part="message" aria-hidden="true"></span>
        @endif

        @if($hasError && $errorMessage)
            <p @if($errorId) id="{{ $errorId }}" @endif @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">
                {{ $errorMessage }}
            </p>
        @elseif($hint)
            <p @if($hintId) id="{{ $hintId }}" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
                {{ $hint }}
            </p>
        @endif
    @endif
</div>
