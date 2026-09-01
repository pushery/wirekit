{{-- optimistic-ui: supported
     Half of this control is discrete and half is free text, and the two halves
     want opposite things from an undo. The steppers are the easy case: +1 is a
     discrete mutation and the previous number is the server's. The field is not
     — a number typed into it is the reader's work.

     Both take §8's FOURTH exit, `failure: 'keep'`, and that is a deliberate
     decision rather than a shortcut. Splitting them was the obvious idea and the
     wrong one: a value typed while a stepper's request is in flight would be
     overwritten by that request's rollback, so a per-trigger exit makes safety
     depend on timing. `keep` guarantees structurally that nothing typed is ever
     destroyed. The price is visible and announced — a refused stepper step stays
     on screen and says it was not saved, rather than springing back. --}}
@props([
    // The Livewire method to call when the value should be shown before the
    // server has agreed to it. A refusal KEEPS what is there — see the note
    // above for why that holds for the steppers too.
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
    // Render the label sr-only (kept as the field's accessible name) — for a
    // compact stepper in a toolbar or a table cell, where the stacked visible
    // label costs a second line the layout does not have. The real <label for="…">
    // stays in the DOM, so the name survives. Mirrors input / select / textarea /
    // combobox.
    'hideLabel' => false,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.number-input.size', 'md'),
    'min' => null,
    'max' => null,
    'step' => 1,
    'prefix' => null,
    'suffix' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

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

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    // Same trap one level in: an UNBOUND `hideLabel="false"` reaches here as the
    // truthy string 'false' and would hide the label the call site asked to show.
    $hideLabel = BooleanProp::from($hideLabel, false);


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('number-input', $attributes->getAttributes());

    // Auto-generate ID from name attribute
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'number-input-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` from the bag: the deduped $id is rendered explicitly as
    // id="{{ $id }}", so leaving it in the bag would emit a second, conflicting id attribute.
    $attributes = $attributes->except('id');

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base input classes — same foundation as standard input
    $inputClasses = WireKit::resolveClasses('number-input', 'base', implode(' ', [
        'block w-16',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'text-center tabular-nums',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-y-[length:var(--border-wk-width)]',
        'border-x-0',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        // Hide native spinner arrows
        '[appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none',
    ]), $scope);

    // Border color switches between normal and error state. Only the top/bottom
    // border is colored on the input itself because the stepper buttons sit
    // flush against it and provide the left/right border — see $buttonBorder
    // below for the matching error-aware color on those buttons.
    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        : 'border-[var(--color-wk-border-strong)]';

    // Size classes
    $sizeClasses = match ($size) {
        'sm' => 'h-[var(--size-wk-sm)] text-[length:var(--text-wk-sm)]',
        'lg' => 'h-[var(--size-wk-lg)] text-[length:var(--text-wk-lg)]',
        default => 'h-[var(--size-wk-md)] text-[length:var(--text-wk-md)]',
    };

    // Stepper button padding scales with size variant
    $buttonPadding = match ($size) {
        'sm' => 'px-[var(--padding-wk-x-xs)]',
        'lg' => 'px-[var(--padding-wk-x-md)]',
        default => 'px-[var(--padding-wk-x-sm)]',
    };

    // Stepper button border color — must match the input's state so the entire
    // stepper group looks like a single framed control. Without this, a
    // number-input in error state shows a red line only above and below the
    // middle <input> (because the buttons kept the neutral border), producing
    // a visually broken "gapped" frame.
    $buttonBorderColor = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border-strong)]';

    // Stepper button classes — shared for both decrease/increase
    $buttonClasses = implode(' ', [
        'inline-flex items-center justify-center',
        'bg-[var(--color-wk-bg-subtle)]',
        'text-[color:var(--color-wk-text-muted)]',
        'border-[length:var(--border-wk-width)]',
        $buttonBorderColor,
        'hover:bg-[var(--color-wk-bg-muted)]',
        'hover:text-[color:var(--color-wk-text)]',
        'cursor-pointer',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed',
    ]);

    $radiusLeft = match ($size) {
        'sm' => 'rounded-l-[var(--radius-wk-sm)]',
        'lg' => 'rounded-l-[var(--radius-wk-md)]',
        default => 'rounded-l-[var(--radius-wk-md)]',
    };
    $radiusRight = match ($size) {
        'sm' => 'rounded-r-[var(--radius-wk-sm)]',
        'lg' => 'rounded-r-[var(--radius-wk-md)]',
        default => 'rounded-r-[var(--radius-wk-md)]',
    };

    // Build aria-describedby from hint + error
    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
    // `bind` rather than `value`: the property already exists on the component
    // this layer nests inside — number-input is the case the factory's own
    // comment names, where declaring `value` here would shadow the parent's.
    //
    // `failure: 'keep'` for BOTH halves. See the note at the top: a split exit
    // makes safety depend on whether the reader happened to be typing.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'value',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            'kept' => __('Could not save. Your entry is still here.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);

@endphp

{{-- The stepper's arithmetic — precision snapping plus step-grid alignment —
     lives in resources/js/components/number-input.js. It cannot live here: an
     inline object literal cannot declare getters or methods under Alpine's CSP
     build, so under a strict policy both buttons rendered, looked enabled, and
     did nothing. --}}
<div
    class="space-y-1.5"
    x-data="wirekitNumberInput({ value: {{ $attributes->get('value', $min ?? 0) }}, min: {{ $min !== null ? $min : 'null' }}, max: {{ $max !== null ? $max : 'null' }}, step: {{ $step }} })"
>
@if($optimisticConfig)
    {{-- The layer nests INSIDE the component that owns the value: a nested Alpine
         component reads and writes its parent's properties through `this`, never
         the reverse, so `bind: 'value'` only resolves this way round.

         `display: contents` because the wrapper above is a `space-y-1.5` stack —
         a real box here would make label, field and message one flow item. --}}
    <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
    @if($label)
        <x-wirekit::label :for="$id" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    <div class="flex items-center">
        {{-- Prefix — inline before the stepper group --}}
        @if($prefix)
            <span class="shrink-0 pr-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">{{ $prefix }}</span>
        @endif

        {{-- Decrease button — disabled at min boundary --}}
        <button
            type="button"
            class="{{ $buttonClasses }} {{ $buttonPadding }} {{ $radiusLeft }} {{ $sizeClasses }}"
            aria-label="{{ __('Decrease') }}"
            :disabled="atMin"
            :aria-disabled="atMin"
            @click="decrease()"
        >
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M3 8h10" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        </button>

        {{-- Number input — x-model keeps Alpine state and native input in sync --}}
        <input
            type="number"
            id="{{ $id }}"
            name="{{ $name }}"
            x-model.number="value"
            @blur="value = clamp(value)"
            @if($optimisticConfig)
                x-bind:aria-busy="isPending"
                {{-- `change`, not `input`: typing fires input per keystroke, and
                     the event that ends the input is leaving the field (§10). The
                     steppers commit on their own, from the factory — one click is
                     already a finished decision. --}}
                x-on:change="run($event.target.value)"
            @endif
            @if($min !== null) min="{{ $min }}" @endif
            @if($max !== null) max="{{ $max }}" @endif
            step="{{ $step }}"
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
        />

        {{-- Increase button — disabled at max boundary --}}
        <button
            type="button"
            class="{{ $buttonClasses }} {{ $buttonPadding }} {{ $radiusRight }} {{ $sizeClasses }}"
            aria-label="{{ __('Increase') }}"
            :disabled="atMax"
            :aria-disabled="atMax"
            @click="increase()"
        >
            <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" fill="none"/></svg>
        </button>

        {{-- Suffix — inline after the stepper group --}}
        @if($suffix)
            <span class="shrink-0 pl-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]" aria-hidden="true">{{ $suffix }}</span>
        @endif
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
@if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    </div>
@endif
</div>
