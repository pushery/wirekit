@props([
    'label' => null,
    'hideLabel' => false, // render the label sr-only (kept for assistive tech) — for compact toolbar / header fields
    'hint' => null,
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
    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config (WIRE-204).
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('input', $attributes->getAttributes());

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = $attributes->get('id', $attributes->get('name', 'input-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Success / valid state — only when there is NO error (error wins). A string
    // value renders a green confirmation message below the field; `true` shows the
    // green border alone. Not an `aria-invalid` state (the field is valid).
    $hasSuccess = ! $hasError && $success !== null && $success !== false;
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
        'font-[family-name:var(--font-wk-sans)]',
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
    $useWrapper = $prefix || $suffix || $hasAffordances;
@endphp

<div class="space-y-1.5">
    @if($label)
        <x-wirekit::label :for="$id" :required="$required" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    @if($useWrapper)
        {{-- Wrapper: flex row places prefix/suffix (and the clearable/copyable
             affordance buttons) as inline siblings so the input padding adjusts
             to the actual content width instead of a hardcoded value. --}}
        <div
            @if($hasAffordances)
                {{-- Tiny inline Alpine island. clear() empties + refocuses the
                     field and dispatches input/change so wire:model / x-model
                     pick up the cleared value; copy() writes the live value to
                     the clipboard with a brief "Copied" state. hasValue gates the
                     clear button so the X only shows when the field has content
                     (kept in sync via the bubbled input event from the field).
                     execCommand is the fallback for non-secure (http) contexts
                     where navigator.clipboard is unavailable. --}}
                x-data="{ copied: false, hasValue: false, _t: null, init() { this.hasValue = (this.$refs.wkField?.value.length ?? 0) > 0; }, clear() { const f = this.$refs.wkField; if (! f) return; f.value = ''; f.dispatchEvent(new Event('input', { bubbles: true })); f.dispatchEvent(new Event('change', { bubbles: true })); this.hasValue = false; f.focus(); }, copy() { const f = this.$refs.wkField; if (! f) return; const done = () => { this.copied = true; clearTimeout(this._t); this._t = setTimeout(() => { this.copied = false; }, 2000); }; if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(f.value).then(done).catch(() => {}); } else { try { f.select(); document.execCommand('copy'); done(); } catch (e) {} } } }"
                @input="hasValue = ($refs.wkField?.value.length ?? 0) > 0"
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
                {{ $attributes->class([
                    'wk-field', // 16px iOS-zoom floor on phones (dist/wirekit.css)
                    'block w-full h-full bg-transparent border-none shadow-none',
                    'font-[family-name:var(--font-wk-sans)]',
                    'text-[color:var(--color-wk-text)]',
                    'placeholder:text-[color:var(--color-wk-text-placeholder)]',
                    'focus:outline-none focus:ring-0',
                    'disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed',
                    $prefixInputPadClass,
                    'pl-1' => (bool) $prefix,
                    'pr-1' => (bool) $suffix || $hasAffordances,
                ]) }}
            />

            @if($suffix)
                <span class="shrink-0 select-none pr-[var(--padding-wk-x-md)] text-[color:var(--color-wk-text-subtle)] text-[length:var(--text-wk-md)] font-[family-name:var(--font-wk-sans)]">{{ $suffix }}</span>
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
                    aria-label="{{ __('Copy to clipboard') }}"
                    :aria-label="copied ? 'Copied to clipboard' : 'Copy to clipboard'"
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
                    aria-label="{{ __('Clear input') }}"
                    class="shrink-0 inline-flex items-center justify-center min-w-[24px] min-h-[24px] mr-[var(--padding-wk-x-sm)] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
                >
                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                    </svg>
                </button>
            @endif

            @if($hasAffordances)
                {{-- Polite live region announces the copy success to screen readers. --}}
                <span aria-live="polite" aria-atomic="true" class="sr-only" x-text="copied ? 'Copied to clipboard' : ''"></span>
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
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
        />
    @endif

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hasSuccess && $successMessage)
        <p id="{{ $id }}-success" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-success-text)]">{{ $successMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
