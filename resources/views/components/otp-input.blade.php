@props([
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hint' => null,
    'error' => null,
    'length' => 6,
    'masked' => false,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
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

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('otp-input', $attributes->getAttributes());

    $id = $attributes->get('id', $attributes->get('name', 'otp-' . \Illuminate\Support\Str::random(6)));
    $name = $attributes->get('name', $id);

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Individual digit input classes
    $digitClasses = WireKit::resolveClasses('otp-input', 'digit', implode(' ', [
        'w-10 h-[var(--size-wk-md)]',
        'text-center tabular-nums',
        'font-[family-name:var(--font-wk-sans)]',
        'text-[length:var(--text-wk-lg)]',
        'font-[number:var(--font-wk-heading-weight)]',
        'bg-[var(--color-wk-bg-input)]',
        'text-[color:var(--color-wk-text)]',
        'border-[length:var(--border-wk-width)]',
        'rounded-[var(--radius-wk-md)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors duration-[var(--transition-wk-duration)]',
        'focus:outline-none',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)]'
        : 'border-[var(--color-wk-border-strong)]';

    $wrapperClasses = WireKit::resolveClasses('otp-input', 'wrapper', implode(' ', [
        'space-y-1.5',
        'font-[family-name:var(--font-wk-sans)]',
    ]), $scope);

    $describedBy = trim(($hint && !$hasError ? $id . '-hint' : '') . ' ' . ($hasError ? $id . '-error' : ''));
@endphp

<div {{ $attributes->only('class')->class([$wrapperClasses]) }}>
    @if($label)
        <x-wirekit::label :for="$id . '-0'">{{ $label }}</x-wirekit::label>
    @endif

    {{-- Hidden input holds the combined OTP value for form submission / wire:model --}}
    <input type="hidden" id="{{ $id }}" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} />

    {{-- Alpine logic inlined (no wirekit.js dependency needed).
         Handles auto-advance on digit input, backspace to previous,
         arrow key navigation, and paste distribution across fields. --}}
    <div
        x-data="{
            _length: {{ $length }},
            _name: '{{ $name }}',
            onInput(event, index) {
                const value = event.target.value;
                if (!/^\d?$/.test(value)) { event.target.value = ''; return; }
                if (value && index < this._length - 1) {
                    this.$refs['digit' + (index + 1)]?.focus();
                }
                this._sync();
            },
            onKeydown(event, index) {
                if (event.key === 'Backspace') {
                    if (!event.target.value && index > 0) {
                        const prev = this.$refs['digit' + (index - 1)];
                        if (prev) { prev.value = ''; prev.focus(); }
                    } else { event.target.value = ''; }
                    this._sync();
                } else if (event.key === 'ArrowLeft' && index > 0) {
                    this.$refs['digit' + (index - 1)]?.focus();
                } else if (event.key === 'ArrowRight' && index < this._length - 1) {
                    this.$refs['digit' + (index + 1)]?.focus();
                }
            },
            onPaste(event) {
                event.preventDefault();
                const pasted = (event.clipboardData?.getData('text') || '').replace(/\D/g, '');
                for (let i = 0; i < this._length; i++) {
                    const ref = this.$refs['digit' + i];
                    if (ref) ref.value = pasted[i] || '';
                }
                const firstEmpty = Array.from({ length: this._length })
                    .findIndex((_, i) => !this.$refs['digit' + i]?.value);
                this.$refs['digit' + (firstEmpty >= 0 ? firstEmpty : this._length - 1)]?.focus();
                this._sync();
            },
            _sync() {
                const combined = Array.from({ length: this._length })
                    .map((_, i) => this.$refs['digit' + i]?.value || '').join('');
                const hidden = this.$el.parentElement?.querySelector('input[name=\'' + this._name + '\']');
                if (hidden) { hidden.value = combined; hidden.dispatchEvent(new Event('input', { bubbles: true })); }
            }
        }"
        class="flex gap-2"
        role="group"
        aria-label="{{ $label ?? $attributes->get('aria-label') ?? 'One-time code' }}"
    >
        @for($i = 0; $i < $length; $i++)
            <input
                type="{{ $masked ? 'password' : 'text' }}"
                inputmode="numeric"
                pattern="[0-9]"
                maxlength="1"
                autocomplete="one-time-code"
                id="{{ $id }}-{{ $i }}"
                aria-label="Digit {{ $i + 1 }} of {{ $length }}"
                @if($hasError) aria-invalid="true" @endif
                @if($i === 0 && $describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                class="wk-field {{ $digitClasses }} {{ $stateClasses }}"
                x-ref="digit{{ $i }}"
                @input="onInput($event, {{ $i }})"
                @keydown="onKeydown($event, {{ $i }})"
                @paste="onPaste($event)"
            />
        @endfor
    </div>

    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
