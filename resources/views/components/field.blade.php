@props([
    'label' => null,
    'name' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    'for' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    // The `for` attribute links the label to its input. If not explicitly given,
    // we fall back to `name` so the wrapped input's auto-generated id (= name) matches.
    $targetId = $for ?? $name;

    // Error detection: explicit prop OR Laravel validation bag (keyed by `name`)
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($name ? ($errors ?? null)?->first($name) : null);

    // Stable IDs for hint/error paragraphs so we can wire up aria-describedby
    $hintId = $targetId ? "{$targetId}-hint" : null;
    $errorId = $targetId ? "{$targetId}-error" : null;

    // Wrapper spacing token — uses same spacing as inputs for visual consistency
    $wrapperClasses = WireKit::resolveClasses('field', 'base', 'space-y-1.5', $scope);
@endphp

<div {{ $attributes->class([$wrapperClasses]) }}>
    {{-- Label with optional required indicator (red asterisk) --}}
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
        <p @if($errorId) id="{{ $errorId }}" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">
            {{ $errorMessage }}
        </p>
    @elseif($hint)
        <p @if($hintId) id="{{ $hintId }}" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">
            {{ $hint }}
        </p>
    @endif
</div>
