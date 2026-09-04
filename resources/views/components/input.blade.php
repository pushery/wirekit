{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the value is sent when you leave the field,
     shown as saving while it goes.

     **It uses the `keep` failure exit**: a refusal does NOT put the old value
     back. For a typed value the previous one belongs to the server and the new
     one is your work, so an undo would delete what you just wrote because a
     save failed. The value stays, the state becomes `rejected`, and the
     announcement says both — that it did not save and that the value is still
     there. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. A refusal keeps your value —
    // see the note above. Null leaves the component exactly as it has always
    // rendered.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
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
    // When true (default), the error message renders as an ARIA live region
    // (aria-live="polite") so a validation error that appears dynamically — e.g.
    // after a Livewire round-trip — is announced by screen readers without the
    // focus having to return to the field. Set false when the surrounding page
    // runs its own live region for form errors (avoids a double announcement).
    // The aria-describedby link on the input is unaffected either way.
    'announceError' => null,
    // Success / valid state. Pass a string to show a green confirmation message
    // below the field (e.g. "Username available"), or `true` for just the green
    // border with no message. `error` always wins when both are set.
    'success' => null,
    'size' => config('wirekit.components.input.size', 'md'),
    'type' => 'text',
    // Monospace the field value — for SKUs, measurements, codes, hashes. Swaps the
    // input font to --font-wk-mono; off by default (byte-identical). The <x-slot:leading>
    // / <x-slot:trailing> named slots put an icon or addon INSIDE the field frame (a
    // search glyph, a unit) — distinct from the text-only `prefix`/`suffix` props.
    'mono' => false,
    'prefix' => null,
    'suffix' => null,
    // Optional trailing affordances (opt-in; default off for byte-identical
    // back-compat). `clearable` shows an X button that empties the field,
    // refocuses it, and dispatches input/change so wire:model / x-model sync.
    // `copyable` shows a copy-to-clipboard button with a brief "Copied" state.
    // Both route the field through the flex wrapper and add a tiny inline Alpine
    // island; when neither is set the input renders exactly as before.
    'clearable' => false,
    'copyable' => false,
    'scope' => null,
    // HTML5 form-state props — surface in the schema so AI / IDE tools
    // know about them, while preserving the pre-existing attribute-bag
    // passthrough so the plain HTML-attribute form (required, disabled,
    // readonly as bare attributes) keeps working byte-identically.
    'required' => false,
    'disabled' => false,
    'readonly' => false,
    'autocomplete' => null,
    'placeholder' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $hideLabel = BooleanProp::from($hideLabel, false);
    $reserveMessage = BooleanProp::from($reserveMessage, false);
    $clearable = BooleanProp::from($clearable, false);
    $mono = BooleanProp::from($mono, false);

    // The field-value font: mono for codes/measurements, otherwise the sans stack.
    // Used by BOTH the bare input and the wrapped input so the two render alike.
    $fontFamilyClass = $mono
        ? 'font-[family-name:var(--font-wk-mono)]'
        : 'font-[family-name:var(--font-wk-sans)]';
    $copyable = BooleanProp::from($copyable, false);
    $required = BooleanProp::from($required, false);
    $disabled = BooleanProp::from($disabled, false);
    $readonly = BooleanProp::from($readonly, false);

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


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('input', $attributes->getAttributes());

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'input-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` AND `name` from the bag: both are rendered explicitly
    // below, so leaving either in the bag emits a second, conflicting attribute on the
    // same element. `id` was stripped from the start; `name` was not, and a caller that
    // passed one got two name attributes on one control — invalid HTML the browser
    // accepts silently by keeping the first, which is why nothing ever went red over it.
    $attributes = $attributes->except(['id', 'name']);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Success / valid state — only when there is NO error (error wins). A string
    // value renders a green confirmation message below the field; `true` shows the
    // green border alone. Not an `aria-invalid` state (the field is valid).
    // Tri-state (null | true | string message): `!== false` alone let the unbound
    // string 'false' (truthy) paint the success state — isFalse recognizes the
    // stringly-false spellings without collapsing a real success message.
    $hasSuccess = ! $hasError && $success !== null && ! BooleanProp::isFalse($success);
    $successMessage = is_string($success) ? $success : null;

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    //
    // Note on :user-invalid styling:
    // The [&:user-invalid]:* utilities below give every input automatic visual
    // feedback for native HTML5 constraint violations (type, pattern, min, max,
    // required, minlength, maxlength, step). :user-invalid — unlike :invalid —
    // only activates AFTER the user has interacted with the field (touched it
    // and blurred, or tried to submit the form), which avoids the UX footgun
    // of showing red borders on every empty required field at page load.
    // This runs independently of the $error prop: $error handles server-side
    // Laravel validation errors, :user-invalid handles client-side HTML5
    // constraint violations. Both produce the same red border + red focus ring.
    $inputClasses = WireKit::resolveClasses('input', 'base', implode(' ', [
        'block w-full',
        $fontFamilyClass,
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
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
        '[&:user-invalid]:border-[var(--color-wk-border-error)]',
        '[&:user-invalid:focus-visible]:ring-[var(--color-wk-danger)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
    ]), $scope);

    // Border color switches between error, success, and normal state — all via tokens
    $stateClasses = match (true) {
        (bool) $hasError => 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]',
        $hasSuccess => 'border-[var(--color-wk-border-success)] focus-visible:ring-[var(--color-wk-success)]',
        default => 'border-[var(--color-wk-border-strong)]',
    };

    // Size classes: height, padding, font size, radius — all from sizing tokens
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md-compact' => implode(' ', [
            'h-[var(--size-wk-md-compact)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'md' => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => WireKit::validateProp('input', 'size', $size, ['sm', 'md-compact', 'md', 'lg']),
    };

    // Prefix/suffix-wrapper sizing — computed here in the @php block (not
    // inline in the wrapper's @class directive) so the hyphenated size key
    // 'md-compact' stays in the context where the drift class-detector's
    // shape-marker pass correctly treats it as a dispatch key, not a class.
    $prefixWrapperSizeClass = match ($size) {
        'sm' => 'rounded-[var(--radius-wk-sm)] h-[var(--size-wk-sm)]',
        'md-compact' => 'rounded-[var(--radius-wk-md)] h-[var(--size-wk-md-compact)]',
        'lg' => 'rounded-[var(--radius-wk-md)] h-[var(--size-wk-lg)]',
        default => 'rounded-[var(--radius-wk-md)] h-[var(--size-wk-md)]',
    };
    $prefixInputPadClass = match ($size) {
        'sm' => 'px-[var(--padding-wk-x-sm)] text-[length:var(--text-wk-sm)]',
        'md-compact' => 'px-[var(--padding-wk-x-md)] text-[length:var(--text-wk-sm)]',
        'lg' => 'px-[var(--padding-wk-x-lg)] text-[length:var(--text-wk-lg)]',
        default => 'px-[var(--padding-wk-x-md)] text-[length:var(--text-wk-md)]',
    };

    // Trailing affordances (clearable / copyable) route the field through the
    // flex wrapper so the buttons sit as inline siblings; when set, the wrapper
    // also carries the tiny Alpine island that drives clear() / copy().
    $hasAffordances = $clearable || $copyable;
    // The leading/trailing icon slots live INSIDE the field frame, so — like
    // prefix/suffix and the affordance buttons — they route the field through the
    // flex wrapper.
    $hasLeading = isset($leading) && ! $leading->isEmpty();
    $hasTrailing = isset($trailing) && ! $trailing->isEmpty();
    $useWrapper = $prefix || $suffix || $hasAffordances || $hasLeading || $hasTrailing;
@endphp

@php
    // `failure: 'keep'` is what makes this component eligible at all.
    //
    // No `x-ref="control"`: with affordances the field already carries
    // `x-ref="wkField"` and an element gets one ref. The commit reads
    // `$event.target.value` instead, which is what the change event hands over
    // anyway, and `keep` never writes on failure — so the resync the ref would
    // enable has nothing to do.
    //
    // `value` IS handed over, and it is not the same decision. The two were
    // once treated as one, and the layer was dead in every render: this layer is
    // the OUTERMOST x-data here, so an undeclared `value` is not in scope for
    // the default binding either, `init()` sets `_bindMissing`, and `run()`
    // returns before it ever reaches Livewire. Nothing flipped, nothing was
    // announced, nothing was sent — and the component looked supported. What it
    // seeds is only the baseline; `keep` never writes it back.
    $optimisticConfig = ($optimistic === null || $disabled || $readonly) ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (string) ($attributes->get('value') ?? ''),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'kept' => __('wirekit::Could not save. Your entry is still here.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

<div class="space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        <x-wirekit::label :for="$id" :required="$required" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    @if($useWrapper)
        {{-- Wrapper: flex row places prefix/suffix (and the clearable/copyable
             affordance buttons) as inline siblings so the input padding adjusts
             to the actual content width instead of a hardcoded value. --}}
        <div
            @if($hasAffordances)
                {{-- clear() / copy() live in resources/js/components/input.js.
                     They cannot live here: an inline object literal cannot
                     declare methods under Alpine's CSP build, so both buttons
                     rendered and did nothing under a strict policy. --}}
                x-data="wirekitInput"
                @input="syncHasValue()"
            @endif
            @class([
            'flex items-center',
            'bg-[var(--color-wk-bg-input)]',
            'border-[length:var(--border-wk-width)]',
            'shadow-[var(--shadow-wk-sm)]',
            'overflow-hidden',
            'transition-colors',
            'duration-[var(--transition-wk-duration)]',
            'ease-[var(--transition-wk-easing)]',
            'has-[:focus-visible]:ring-[length:var(--ring-wk-width)]',
            'has-[:focus-visible]:ring-offset-[length:var(--ring-wk-offset)]',
            'has-[:focus-visible]:ring-[var(--color-wk-ring)]',
            'has-[:focus-visible]:ring-offset-[var(--color-wk-ring-offset)]',
            // Mirror the inner input's :user-invalid state onto the wrapper
            // so the border and focus ring on the wrapper turn red too. Uses
            // :has() so we don't need any JS sync between input and wrapper.
            'has-[:user-invalid]:border-[var(--color-wk-border-error)]',
            'has-[:user-invalid:focus-visible]:ring-[var(--color-wk-danger)]',
            'hover:border-[var(--color-wk-border-strong-hover)]',
            $hasError
                ? 'border-[var(--color-wk-border-error)]'
                : ($hasSuccess ? 'border-[var(--color-wk-border-success)]' : 'border-[var(--color-wk-border-strong)]'),
            $prefixWrapperSizeClass,
        ])>
            @if($hasLeading)
                {{-- Leading addon (an icon / unit glyph) INSIDE the frame. The slot
                     owns its own a11y — a decorative <x-wirekit::icon> is aria-hidden
                     already; the field's label is its accessible name. --}}
                <span class="shrink-0 inline-flex items-center pl-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text-subtle)]">{{ $leading }}</span>
            @endif

            @if($prefix)
                <span class="shrink-0 select-none pl-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text-subtle)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]">{{ $prefix }}</span>
            @endif

            <input
                id="{{ $id }}"
                name="{{ $name }}"
                type="{{ $type }}"
                @if($required) required @endif
                @if($disabled) disabled @endif
                @if($readonly) readonly @endif
                @if($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
                @if($placeholder !== null) placeholder="{{ $placeholder }}" @endif
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                @if($hasSuccess && $successMessage && !$hasError) aria-describedby="{{ $id }}-success" @endif
                @if($hint && !$hasError && !($hasSuccess && $successMessage)) aria-describedby="{{ $id }}-hint" @endif
                @if($hasAffordances) x-ref="wkField" @endif
                @if($optimisticConfig)
                    x-bind:aria-busy="isPending"
                    {{-- `change`, not `input`: typing fires input per keystroke,
                         and the event that ends the input is leaving the
                         field. --}}
                    x-on:change="run($event.target.value)"
                @endif
                {{ $attributes->class([
                    'wk-field', // 16px iOS-zoom floor on phones (dist/wirekit.css)
                    'block w-full h-full bg-transparent border-none shadow-none',
                    $fontFamilyClass,
                    'text-[color:var(--color-wk-text)]',
                    'placeholder:text-[color:var(--color-wk-text-placeholder)]',
                    'focus:outline-none focus:ring-0',
                    'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed',
                    $prefixInputPadClass,
                    'pl-1' => (bool) $prefix || $hasLeading,
                    'pr-1' => (bool) $suffix || $hasAffordances || $hasTrailing,
                ]) }}
            />

            @if($suffix)
                <span class="shrink-0 select-none pr-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text-subtle)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]">{{ $suffix }}</span>
            @endif

            @if($hasTrailing)
                {{-- Trailing addon (an icon / unit glyph) INSIDE the frame, before any
                     clearable/copyable affordance buttons. The slot owns its a11y. --}}
                <span class="shrink-0 inline-flex items-center pr-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text-subtle)]">{{ $trailing }}</span>
            @endif

            @if($copyable)
                {{-- Copy-to-clipboard button. Swaps to a check icon and announces
                     "Copied" via the polite live region below for ~2s. Static
                     aria-label is the no-JS fallback; Alpine :aria-label swaps it
                     to reflect the copied state. ring-inset so the focus ring is
                     never clipped by the wrapper's overflow-hidden. --}}
                <button
                    type="button"
                    @click="copy()"
                    @if($disabled) disabled @endif
                    aria-label="{{ __('wirekit::Copy to clipboard') }}"
                    :aria-label="copied ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copied to clipboard')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copy to clipboard')) }}"
                    class="shrink-0 inline-flex items-center justify-center min-w-[24px] min-h-[24px] mr-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
                >
                    <svg x-show="! copied" class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M7 3.5A1.5 1.5 0 018.5 2h3.879a1.5 1.5 0 011.06.44l3.122 3.12A1.5 1.5 0 0117 6.622V12.5a1.5 1.5 0 01-1.5 1.5h-1v-3.379a3 3 0 00-.879-2.121L10.5 5.379A3 3 0 008.379 4.5H7v-1z"/>
                        <path d="M4.5 6A1.5 1.5 0 003 7.5v9A1.5 1.5 0 004.5 18h7a1.5 1.5 0 001.5-1.5v-5.879a1.5 1.5 0 00-.44-1.06L9.44 6.439A1.5 1.5 0 008.378 6H4.5z"/>
                    </svg>
                    <svg x-show="copied" x-cloak class="w-4 h-4 text-[color:var(--color-wk-success-text)]" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd"/>
                    </svg>
                </button>
            @endif

            @if($clearable)
                {{-- Clear button. Only visible when the field has content
                     (hasValue); empties + refocuses the field. --}}
                <button
                    type="button"
                    x-show="hasValue"
                    x-cloak
                    @click="clear()"
                    @if($disabled) disabled @endif
                    aria-label="{{ __('wirekit::Clear input') }}"
                    class="shrink-0 inline-flex items-center justify-center min-w-[24px] min-h-[24px] mr-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                    </svg>
                </button>
            @endif

            @if($hasAffordances)
                {{-- Polite live region announces the copy success to screen readers. --}}
                {{-- The only feedback a screen-reader user gets after copying — nothing changes visually. --}}
                <span aria-live="polite" aria-atomic="true" class="sr-only" x-text="copied ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copied to clipboard')) }} : ''"></span>
            @endif
        </div>
    @else
        {{-- No prefix/suffix: render plain input with full styling --}}
        <input
            id="{{ $id }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @if($required) required @endif
            @if($disabled) disabled @endif
            @if($readonly) readonly @endif
            @if($autocomplete !== null) autocomplete="{{ $autocomplete }}" @endif
            @if($placeholder !== null) placeholder="{{ $placeholder }}" @endif
            @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            @if($hasSuccess && $successMessage && !$hasError) aria-describedby="{{ $id }}-success" @endif
            @if($hint && !$hasError && !($hasSuccess && $successMessage)) aria-describedby="{{ $id }}-hint" @endif
            @if($optimisticConfig)
                x-bind:aria-busy="isPending"
                x-on:change="run($event.target.value)"
            @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
        />
    @endif

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
    {{-- `reserve-message` keeps the line's height whether or not there is
         anything to say. In a stacked form that is wasted space; in a horizontal
         toolbar it is the difference between a working layout and one that
         jumps, because an appearing error grows this element and every sibling
         in the row re-anchors to the new bottom edge. Aligning the row does not
         help: `items-end` follows the growth and `items-start` lines things up
         with the label rather than the control.

         `select-none` is the mouse half of the same decision `aria-hidden` makes for a
         screen reader: the line holds space, not text, so there is nothing here to select
         either. Without it a drag-select across a form carries one stray no-break space per
         reserved field into whatever gets pasted. --}}
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

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif
</div>
