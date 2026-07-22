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

@aware(['announceErrors' => null])

@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config (WIRE-204).
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

<div {{ $attributes->class([$wrapperClasses]) }}>
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
        @endif

        {{-- The actual input/select/textarea/checkbox — passed in as default slot.
             The child component is expected to read its own $errors bag and set aria-* itself,
             but the wrapper still renders its own error/hint messages with stable IDs. --}}
        {{ $slot }}

        {{-- Error takes precedence over hint — show one, not both --}}
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
