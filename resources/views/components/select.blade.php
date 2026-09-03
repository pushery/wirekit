{{-- optimistic-ui: supported
     A native select with a server-side value is a mutation the browser has
     already applied. The concurrency question is sharper here than on a toggle:
     two changes in flight would resolve to whichever answered last, which is
     why the mode is reject. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. Null leaves the component
    // exactly as it has always rendered.
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
    'hideLabel' => false, // render the label sr-only (kept for assistive tech) — for compact toolbar / header fields
    'hint' => null,
    // Keep the message line's height whether or not there is a message.
    //
    // Wasted space in a stacked form, and the difference between a working
    // toolbar and one that jumps in a horizontal row: an appearing error grows
    // this element, and every sibling in the row re-anchors to the new bottom
    // edge. Aligning the row does not fix it — `items-end` follows the growth,
    // and `items-start` lines things up with the label rather than the control.
    'reserveMessage' => false,
    'error' => null,
    // Success / valid state — string shows a green confirmation below, `true`
    // shows just the green border. `error` always wins when both are set.
    'success' => null,
    'size' => config('wirekit.components.select.size', 'md'),
    'placeholder' => null,
    'options' => [],
    // The pre-selected option. It has to be a declared prop: `<select>` has no
    // `value` content attribute, so an undeclared one fell into the attribute bag
    // and rendered onto the element, where HTML ignores it. `value="pro"` then
    // selected nothing and the browser fell back to the first option — the field
    // showed one choice while everything reading the component believed another.
    'value' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $hideLabel = BooleanProp::from($hideLabel, false);
    $reserveMessage = BooleanProp::from($reserveMessage, false);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);

    // Compared as strings, deliberately. Option keys arrive from PHP arrays, where
    // `['1' => 'One']` is an INT key, while a `value` written on a tag is always a
    // string — a strict comparison would leave numeric-keyed option lists unable to
    // pre-select anything, which is the same defect one type-cast further down.
    $isSelected = fn ($candidate): bool => $value !== null && (string) $value === (string) $candidate;
@endphp


@php
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('select', $attributes->getAttributes());

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'select-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` from the bag: the deduped $id is rendered explicitly as
    // id="{{ $id }}", so leaving it in the bag would emit a second, conflicting id attribute.
    $attributes = $attributes->except('id');

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Success / valid state — only when there is no error (error wins).
    // Tri-state (null | true | string message): `!== false` alone let the unbound
    // string 'false' (truthy) paint the success state — isFalse recognizes the
    // stringly-false spellings without collapsing a real success message.
    $hasSuccess = ! $hasError && $success !== null && ! BooleanProp::isFalse($success);
    $successMessage = is_string($success) ? $success : null;

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    $selectClasses = WireKit::resolveClasses('select', 'base', implode(' ', [
        'block w-full appearance-none',
        'font-[family-name:var(--font-wk-sans)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'hover:border-[var(--color-wk-border-strong-hover)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        'cursor-pointer',
    ]), $scope);

    // Border color switches between error, success, and normal state — all via tokens
    $stateClasses = match (true) {
        (bool) $hasError => 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]',
        $hasSuccess => 'border-[var(--color-wk-border-success)] focus-visible:ring-[var(--color-wk-success)]',
        default => 'border-[var(--color-wk-border-strong)]',
    };

    // Size classes: height, padding, font size, radius — all from sizing tokens
    // pr-8 kept for dropdown arrow space
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'pr-8',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md-compact' => implode(' ', [
            'h-[var(--size-wk-md-compact)]',
            'px-[var(--padding-wk-x-md)]',
            'pr-8',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'md' => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'pr-8',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'pr-8',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => WireKit::validateProp('select', 'size', $size, ['sm', 'md-compact', 'md', 'lg']),
    };
@endphp

@php
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (string) ($value ?? ''),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

<div class="space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        <x-wirekit::label :for="$id" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    <div class="relative">
        <select
            id="{{ $id }}"
            name="{{ $name }}"
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if($optimisticConfig)
                x-ref="control"
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
            @if($hasSuccess && $successMessage && !$hasError) aria-describedby="{{ $id }}-success" @endif
            @if($hint && !$hasError && !($hasSuccess && $successMessage)) aria-describedby="{{ $id }}-hint" @endif
            {{-- wk-field: lifts font-size to the 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $selectClasses, $stateClasses, $sizeClasses]) }}
        >
            @if($placeholder)
                {{-- The placeholder only pre-selects itself when nothing else is
                     chosen. Marking it `selected` unconditionally is what made a
                     `value` prop invisible even once the options below honored it. --}}
                <option value="" disabled{{ ($value === null || $value === '') ? ' selected' : '' }}>{{ $placeholder }}</option>
            @endif
            {{--
                Options accept three shapes (mix freely):
                  - Flat:            ['de' => 'Germany']                       → <option>
                  - Per-option attrs:['de' => ['label' => 'Germany',
                                               'disabled' => true,
                                               'lang' => 'de']]                → disabled <option>
                  - Grouped:         ['Europe' => ['de' => 'Germany', ...]]    → <optgroup>
                A group is an array value WITHOUT a 'label' key; a single option
                with attributes is an array value WITH a 'label' key.

                `lang` exists for one control and that control is common: a language picker
                lists endonyms, so `Deutsch`, `Español` and `Français` are words in a language
                the document is not in. Without it a screen reader says all of them in the
                page's voice — WCAG 2.1 AA 3.1.2, whose proper-name exception does not apply,
                because an endonym is not a borrowed word. It is the one control that exists
                FOR readers of those languages, so it is the one place their language must not
                be mispronounced.

                No automated run catches its absence. axe's `valid-lang` rule carries the
                wcag312 tag but only validates a lang attribute that is PRESENT; with none
                there are no nodes to judge, and the scan reports zero violations.
            --}}
            @foreach($options as $optionValue => $optionLabel)
                @if(is_array($optionLabel) && ! array_key_exists('label', $optionLabel))
                    <optgroup label="{{ $optionValue }}">
                        @foreach($optionLabel as $subValue => $subLabel)
                            @php
                                $sLabel = is_array($subLabel) ? ($subLabel['label'] ?? $subValue) : $subLabel;
                                $sDisabled = is_array($subLabel) && ! empty($subLabel['disabled']);
                                $sLang = is_array($subLabel) && isset($subLabel['lang']) ? (string) $subLabel['lang'] : null;
                            @endphp
                            <option value="{{ $subValue }}"@if($sLang !== '' && $sLang !== null) lang="{{ $sLang }}"@endif{{ $sDisabled ? ' disabled' : '' }}{{ $isSelected($subValue) ? ' selected' : '' }}>{{ $sLabel }}</option>
                        @endforeach
                    </optgroup>
                @else
                    @php
                        $oLabel = is_array($optionLabel) ? ($optionLabel['label'] ?? $optionValue) : $optionLabel;
                        $oDisabled = is_array($optionLabel) && ! empty($optionLabel['disabled']);
                        $oLang = is_array($optionLabel) && isset($optionLabel['lang']) ? (string) $optionLabel['lang'] : null;
                    @endphp
                    <option value="{{ $optionValue }}"@if($oLang !== '' && $oLang !== null) lang="{{ $oLang }}"@endif{{ $oDisabled ? ' disabled' : '' }}{{ $isSelected($optionValue) ? ' selected' : '' }}>{{ $oLabel }}</option>
                @endif
            @endforeach
            {{ $slot }}
        </select>

        {{-- Dropdown arrow indicator — color via design token for automatic dark mode --}}
        <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5">
            <svg class="h-4 w-4 text-[color:var(--color-wk-text-subtle)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
    {{-- See the `reserve-message` prop: an appearing message grows this element
         and pushes every sibling in a horizontal row. It is `select-none` for the same
         reason it is `aria-hidden` — it holds space, not text, and a drag-select across the
         form should not carry its no-break space into the clipboard. --}}
    @if($reserveMessage && ! (($hasError && $errorMessage) || ($hasSuccess && $successMessage) || $hint))
        <p aria-hidden="true" class="select-none text-[length:var(--text-wk-sm)]">&nbsp;</p>
    @endif
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hasSuccess && $successMessage)
        <p id="{{ $id }}-success" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-success-text)]">{{ $successMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
