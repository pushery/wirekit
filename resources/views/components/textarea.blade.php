{{-- optimistic-ui: supported
     Pass `optimistic="method"` and the text is sent when you leave the field,
     shown as saving while it goes.

     **It uses the FOURTH exit** (§8): a refusal does NOT put the old text back.
     For a free-text field the previous value is the server's and the new one is
     your work, so an undo would delete what you just wrote because a save
     failed. Instead the text stays, the state becomes `rejected`, and the
     announcement says both — that it did not save AND that the text is still
     there, which is the question a user actually has.

     This component is where §8 came from: it was enabled, then taken back once
     the rollback was looked at, and it is enabled again now that the exit it
     needed exists. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. A refusal keeps your text —
    // see the note above. Null leaves the component exactly as it has always
    // rendered.
    'optimistic' => null,
    // A11y: render the error message in a polite live region by default so a
    // server-side validation error that appears after submit (when focus is
    // elsewhere) is announced. Mirrors the input component. Set false to opt out.
    'announceError' => null,
    'label' => null,
    'hideLabel' => false, // render the label sr-only (kept for assistive tech) — for compact toolbar / header fields
    'hint' => null,
    'error' => null,
    // Success / valid state — string shows a green confirmation below, `true`
    // shows just the green border. `error` always wins when both are set.
    'success' => null,
    'size' => config('wirekit.components.textarea.size', 'md'),
    // Number of rows, OR 'auto' to grow with content (CSS field-sizing: content,
    // baseline-safe). In auto mode `rows` acts as the minimum height.
    'rows' => config('wirekit.components.textarea.rows', 3),
    'resize' => true,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $hideLabel = BooleanProp::from($hideLabel, false);
    $resize = BooleanProp::from($resize, true);

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
    WireKit::warnUnknownProps('textarea', $attributes->getAttributes());

    // Auto-generate ID from name attribute, or generate random if neither provided
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'textarea-'); // page-unique DOM id; see Support\DomId
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

    // Auto-size: `rows="auto"` grows the textarea with its content via CSS
    // `field-sizing: content`, which is ABOVE the WireKit browser baseline —
    // Chrome 123 and Safari 17.4, against a floor of 111 and 16.4. This comment
    // claimed the opposite for thirteen releases.
    //
    // The field is complete without it: the numeric `rows` is the minimum height,
    // the control scrolls and stays resizable, and we never emit `rows="auto"`
    // (invalid HTML) — auto falls back to a 2-row minimum. What a reader on a
    // baseline browser does NOT get is the growing, which is the thing the prop is
    // named after. Recorded in BrowserBaselineGuardTest's accepted-use register
    // with that degradation spelled out.
    $autosize = $rows === 'auto' || $rows === true;
    $minRows = $autosize ? 2 : (int) $rows;

    // Base classes: all values reference design tokens — no hardcoded colors or sizes
    //
    // :user-invalid styling mirrors the input component — see input.blade.php
    // for the rationale. Textarea inherits HTML5 constraints via required,
    // minlength, and maxlength, so the same visual feedback applies.
    $textareaClasses = WireKit::resolveClasses('textarea', 'base', implode(' ', [
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

    // Size classes: padding (horizontal + vertical), font size, radius — all from sizing tokens
    // Textarea uses padding-y instead of fixed height (unlike input/select)
    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'px-[var(--padding-wk-x-sm)]',
            'py-[var(--padding-wk-y-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md' => implode(' ', [
            'px-[var(--padding-wk-x-md)]',
            'py-[var(--padding-wk-y-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'px-[var(--padding-wk-x-lg)]',
            'py-[var(--padding-wk-y-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => WireKit::validateProp('textarea', 'size', $size, ['sm', 'md', 'lg']),
    };
@endphp

@php
    // `failure: 'keep'` is the whole reason this component can be here. Every
    // other setting matches the discrete controls; that one is what makes an
    // undo stop being hostile.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (string) ($slot->isEmpty() ? '' : trim($slot)),
        'action' => $optimistic,
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        // A second commit while one is in flight would resolve by whichever
        // answer arrives last — network timing, which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            // Not the `reverted` string, because nothing was reverted. The
            // reassurance is the point: the first thing a person needs to know
            // is whether their text survived.
            'kept' => __('Could not save. Your text is still here.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

<div class="space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        <x-wirekit::label :for="$id" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ $minRows }}"
        @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
        @if($hasSuccess && $successMessage && !$hasError) aria-describedby="{{ $id }}-success" @endif
        @if($hint && !$hasError && !($hasSuccess && $successMessage)) aria-describedby="{{ $id }}-hint" @endif
        @if($optimisticConfig)
            x-ref="control"
            x-bind:aria-busy="isPending"
            {{-- `change`, not `input`: the field fires input on every keystroke,
                 and the commit boundary for typing is leaving the field (§10 —
                 the event that ends the input, never a timer). --}}
            x-on:change="commitFromControl()"
        @endif
        {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
        {{ $attributes->class(['wk-field', $textareaClasses, $stateClasses, $sizeClasses, $resize ? 'resize-y' : 'resize-none', 'wk-autosize' => $autosize]) }}
    >{{ $slot }}</textarea>

    {{-- Error / success / hint text use design tokens for automatic dark mode (error wins, then success, then hint) --}}
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
             announced at all. After the error paragraph, which is the one this
             layer yields to when both would speak. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif
</div>
