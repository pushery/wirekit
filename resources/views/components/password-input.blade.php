{{-- optimistic-ui: supported
     Uses §8's fourth exit — a refusal KEEPS what was typed and says so, because
     for a typed value the previous one belongs to the server and the new one is
     the user's work.

     The component's own question was whether the announcement can read anything
     back, since a password field is the one place where that would be a
     disclosure. Confirmed at the source rather than assumed: the layer's
     `_announce()` takes only the `messages` strings handed to it in this config,
     and no path builds a message from the value. The wording names no value
     either.

     Nor does the VALUE reach the markup: this binds to the `password` property
     the component already declares, so the config carries a property NAME rather
     than a value. `value` in the config would have serialized a typed password
     into an x-data attribute — correct for every other field, disqualifying
     here. Binding also keeps one truth for the value instead of two, whether or
     not the strength meter is on. --}}
@props([
    // The Livewire method to call when the field should show the new value
    // before the server has agreed to it. A refusal KEEPS what was typed — see
    // the note above. Null leaves the component exactly as it has always
    // rendered.
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
    // compact field in a toolbar or header, where the stacked visible label costs
    // a second line the layout does not have. The real <label for="…"> stays in
    // the DOM, so the name survives. Mirrors input / select / textarea / combobox.
    'hideLabel' => false,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.password-input.size', 'md'),
    'toggle' => true,
    'strengthMeter' => false,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $toggle = BooleanProp::from($toggle, true);
    $strengthMeter = BooleanProp::from($strengthMeter, false);
    $hideLabel = BooleanProp::from($hideLabel, false);

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
    WireKit::warnUnknownProps('password-input', $attributes->getAttributes());

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'password-input-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` from the bag: the deduped $id is rendered explicitly as
    // id="{{ $id }}", so leaving it in the bag would emit a second, conflicting id attribute.
    $attributes = $attributes->except('id');

    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Base input classes — same as standard input
    $inputClasses = WireKit::resolveClasses('password-input', 'base', implode(' ', [
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
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
        $toggle ? 'pr-[var(--size-wk-md)]' : '',
    ]), $scope);

    $stateClasses = $hasError
        ? 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]'
        : 'border-[var(--color-wk-border-strong)]';

    $sizeClasses = match ($size) {
        'sm' => implode(' ', [
            'h-[var(--size-wk-sm)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'lg' => implode(' ', [
            'h-[var(--size-wk-lg)]',
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        default => implode(' ', [
            'h-[var(--size-wk-md)]',
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
    };

    $describedBy = trim(
        ($hint && !$hasError ? $id . '-hint' : '') . ' '
        . ($hasError ? $id . '-error' : '') . ' '
        . ($strengthMeter ? $id . '-strength' : '')
    );

    // `failure: 'keep'` is what makes this component eligible at all — §8. A
    // rollback here would delete a typed password, and re-typing one is the most
    // expensive re-entry any field can ask for.
    //
    // `bind` rather than `value`: the property already exists on the component
    // this layer nests inside, so the config names it instead of carrying it.
    // That is the ordinary reason — and here it is also the safe one, because a
    // `value` would have written the typed password into an x-data attribute.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'bind' => 'password',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        // A second commit while one is in flight would resolve by whichever
        // answer arrives last — network timing, which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('Saving'),
            // Names no value, and that is load-bearing here rather than a
            // stylistic choice: this is the one field where quoting what was
            // typed would be a disclosure.
            'kept' => __('Could not save. Your entry is still here.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

{{-- The toggle and the meter live in resources/js/components/password-input.js.
     They cannot live here: this used to be an inline object literal carrying a
     getter and a method, and Alpine's CSP parser does not accept that as an
     expression — under a strict policy the element got an EMPTY scope, so the
     show/hide button and the whole meter were dead with no error to say why. --}}
<div class="space-y-1.5" x-data="wirekitPasswordInput({ strengthMeter: {{ $strengthMeter ? 'true' : 'false' }} })">
@if($optimisticConfig)
    {{-- The layer nests INSIDE the component that owns the value, because a
         nested Alpine component reads and writes its parent's properties
         through `this` and never the reverse — so `bind: 'password'` only
         resolves in this direction.

         `display: contents` because the wrapper above is a `space-y-1.5` stack:
         a real box here would make its children one flow item and collapse the
         spacing between label, field and meter. --}}
    <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
    @if($label)
        <x-wirekit::label :for="$id" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @endif

    <div class="relative">
        <input
            :type="showPassword ? 'text' : 'password'"
            id="{{ $id }}"
            name="{{ $name }}"
            @if($strengthMeter) x-model="password" @endif
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($optimisticConfig)
                x-bind:aria-busy="isPending"
                {{-- `change`, not `input`: typing fires input per keystroke, and
                     the event that ends the input is leaving the field (§10). --}}
                x-on:change="run($event.target.value)"
            @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
        />

        {{-- Toggle visibility button --}}
        @if($toggle)
            <button
                type="button"
                class="absolute inset-y-0 right-0 flex items-center px-[var(--padding-wk-x-sm)] cursor-pointer text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] transition-colors duration-[var(--transition-wk-duration)]"
                @click="showPassword = !showPassword"
                {{-- Static aria-label guards pre-Alpine render (axe scans DOM
                     before hydration may complete). :aria-label overrides live. --}}
                aria-label="{{ __('Show password') }}"
                :aria-label="showPassword ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('Hide password')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('Show password')) }}"
            >
                {{-- Eye icon (show) --}}
                <svg x-show="!showPassword" aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/>
                    <circle cx="8" cy="8" r="2"/>
                </svg>
                {{-- Eye-off icon (hide) --}}
                <svg x-show="showPassword" x-cloak aria-hidden="true" class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M1 8s2.5-5 7-5 7 5 7 5-2.5 5-7 5-7-5-7-5z"/>
                    <circle cx="8" cy="8" r="2"/>
                    <path d="M2 14L14 2" stroke-width="2"/>
                </svg>
            </button>
        @endif
    </div>

    {{-- Strength meter — 4 bars that fill based on password complexity score.
         Score: +1 for length≥8, +1 mixed case, +1 digit, +1 symbol. --}}
    @if($strengthMeter)
        <div id="{{ $id }}-strength" role="status" aria-live="polite" class="flex gap-1">
            <template x-for="i in 4" :key="i">
                <div
                    class="h-1 flex-1 rounded-full transition-colors duration-[var(--transition-wk-duration)]"
                    :style="'background-color:' + barColor(i - 1)"
                ></div>
            </template>
        </div>
    @endif

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
