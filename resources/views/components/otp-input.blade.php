{{-- optimistic-ui: supported
     Uses the `keep` failure exit — a refusal KEEPS the code and says only that
     it was not accepted. That is not a preference here, it is the whole reason this
     component was blocked: clearing the boxes on a refusal would make the reader
     re-enter a one-time code that may well have expired in the meantime.

     The commit boundary is the code being COMPLETE, not any single keystroke.
     The field syncs on every character, so anything hung on that would
     fire once per box; `wirekit:otp-complete` fires once, when the last box is
     filled, and again after a correction — which is exactly the moment a
     one-time code is normally submitted. --}}
@props([
    // The Livewire method to call when the code is COMPLETE — not per keystroke.
    // A refusal keeps what was entered; see the note above. Null leaves the
    // component exactly as it has always rendered.
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
    'hint' => null,
    'error' => null,
    'length' => 6,
    'masked' => false,
    'scope' => null,
    // Which characters a box accepts. Defaults to digits, so a call site that
    // passes nothing keeps exactly the previous behavior.
    //
    // "OTP" does not mean "numeric": an ambiguity-free alphabet like
    // ABCDEFGHJKMNPQRSTUVWXYZ23456789 (no I/1, no O/0) is chosen deliberately —
    // it carries far more entropy per character and survives being read aloud.
    // Before this prop existed, such a code could not be entered here at all:
    // every keystroke was discarded and the boxes stayed empty with no message.
    'alphabet' => '0123456789',
    // Focus the first box on load.
    //
    // A one-time-code screen is single-purpose: the reader arrived from a
    // redirect whose status region has already said the code was sent, and the
    // boxes are the only thing on the page they came to operate. Every sibling
    // screen in that flow focuses its field; this one could not, because an
    // `autofocus` written at the call site landed in the attribute bag and was
    // dropped with it — measured as `document.activeElement === body`, one
    // extra Tab for a keyboard reader and a pre-filled email field read out
    // first for a screen-reader reader.
    //
    // Default false, because an OTP field is not always alone on its page and
    // moving focus on a page somebody is already reading takes them somewhere
    // they did not ask to go. WCAG forbids neither; the call site knows which
    // of the two screens it is building.
    'autofocus' => false,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $masked = BooleanProp::from($masked, false);
    $autofocus = BooleanProp::from($autofocus, false);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);

    // ── Alphabet: one prop, every constraint derived from it ──────────────
    //
    // Deriving all four constraints here is the point. A prop that only relaxed
    // the `pattern` while the keystroke filter kept rejecting letters would be
    // worse than no prop at all: the field would look configurable and still
    // discard the code.
    $alphabet = (string) $alphabet;
    // Guard the empty case rather than emitting a control that rejects every
    // keystroke — an empty character class is also invalid HTML `pattern`.
    if ($alphabet === '') {
        $alphabet = '0123456789';
    }
    $alphabetChars = array_values(array_unique(mb_str_split($alphabet)));
    $alphabetIsNumeric = ctype_digit($alphabet);

    // Case folding, but only when it cannot lose information: an alphabet with
    // letters of a single case (the ambiguity-free kind) accepts either case and
    // normalizes. Without this, a reader who types the code in lowercase gets the
    // silent-empty-box failure this prop exists to remove — just via a different
    // route. An alphabet mixing cases is meaningful, so it stays case-sensitive.
    $hasUpper = preg_match('/\p{Lu}/u', $alphabet) === 1;
    $hasLower = preg_match('/\p{Ll}/u', $alphabet) === 1;
    // `!==` rather than `xor`: the latter binds looser than `=`, so
    // `$x = $a xor $b` assigns $a and discards the comparison — it read correctly
    // and was wrong for a mixed-case alphabet only, which is the case a test had
    // to catch because the two common ones came out right by coincidence.
    $alphabetCaseFold = $hasUpper !== $hasLower;

    // The HTML `pattern` mirrors the alphabet as a character class. Characters
    // that carry meaning inside one are escaped; everything else is literal.
    //
    // The full digit set keeps its range spelling so a call site that passes no
    // alphabet gets byte-identical markup to before this prop existed. Enumerating
    // it as [0123456789] would behave the same and still be a change to every
    // rendered page — additive means the default output does not move.
    // When the alphabet folds case, the pattern has to say so — this is the half
    // that was missing, and it was invisible for the reason such things usually
    // are: the promise was kept by the layer that is easiest to test.
    //
    // `$alphabetCaseFold` reached Alpine and nothing else, so the browser's own
    // constraint validation still held a single-case class. With the script
    // running, folding happens on the way in and the value always matches. With
    // it not running — a Content-Security-Policy without `unsafe-eval`, an error
    // earlier on the page, scripting off — the reader types the code in the case
    // the label shows, the pattern rejects it, and the browser refuses to submit
    // with a message about a format nobody was told about. Lacking a `name` does
    // not exempt these boxes: that keeps them out of the submitted data, not out
    // of constraint validation.
    //
    // So the class carries both cases exactly when the component promises to
    // accept both. A mixed-case alphabet is meaningful and folds nothing, and its
    // pattern is unchanged.
    $alphabetPatternChars = $alphabetCaseFold
        ? array_values(array_unique(array_merge(
            $alphabetChars,
            array_map(static fn (string $c): string => mb_strtolower($c), $alphabetChars),
            array_map(static fn (string $c): string => mb_strtoupper($c), $alphabetChars),
        )))
        : $alphabetChars;

    $alphabetPattern = count($alphabetChars) === 10 && $alphabetIsNumeric
        ? '[0-9]'
        : '['.str_replace(
            ['\\', ']', '^', '-'],
            ['\\\\', '\\]', '\\^', '\\-'],
            implode('', $alphabetPatternChars)
        ).']';
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
    WireKit::warnUnknownProps('otp-input', $attributes->getAttributes());

    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'otp-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);
    // Strip the caller's `id` from the bag: the deduped $id is rendered explicitly as
    // id="{{ $id }}", so leaving it in the bag would emit a second, conflicting id attribute.
    $attributes = $attributes->except('id');

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

    // `failure: 'keep'` — the keep-on-refusal exit, and where it matters most. A
    // rollback would clear the boxes, and a one-time code cannot simply be
    // retyped: it may have expired while the request was in flight.
    //
    // `value` is handed over as an empty string rather than omitted, because this
    // layer is the OUTERMOST x-data on the component and an undeclared property
    // is in no scope at all — the binding would name nothing, init() would
    // disarm, and the component would look supported while doing nothing. It
    // seeds the baseline only, which `keep` never writes back.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => '',
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        // A second commit while one is in flight would resolve by whichever
        // answer arrives last — network timing, which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            // Names no value. It never should for any field, and here the value
            // is a credential.
            'kept' => __('wirekit::Could not save. Your entry is still here.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

<div {{ $attributes->only('class')->class([$wrapperClasses]) }}
    @if($optimisticConfig)
        x-data="wirekitOptimistic({{ $optimisticConfig }})"
        {{-- The boundary: the code is whole. The field syncs on every character,
             so anything hung on that would fire once per box. --}}
        x-on:wirekit:otp-complete="run($event.detail.value)"
    @endif
>
    @if($label)
        {{-- `-digit-0`, not `-0`. See the digit id below for why the segment is
             there; this must move with it or every otp-input loses its label. --}}
        <x-wirekit::label :for="$id . '-digit-0'">{{ $label }}</x-wirekit::label>
    @endif

    {{-- Hidden input holds the combined OTP value for form submission / wire:model --}}
    <input type="hidden" id="{{ $id }}" name="{{ $name }}" {{ $attributes->whereStartsWith('wire:model') }} />

    {{-- Alpine logic inlined (no wirekit.js dependency needed).
         Handles auto-advance on digit input, backspace to previous,
         arrow key navigation, and paste distribution across fields. --}}
    <div
        x-data="wirekitOtpInput({ length: {{ $length }}, name: {{ \Pushery\WireKit\Support\AlpinePayload::from($name) }}, alphabet: {{ \Pushery\WireKit\Support\AlpinePayload::from($alphabetChars) }}, caseFold: {{ \Pushery\WireKit\Support\AlpinePayload::from($alphabetCaseFold) }} })"
        {{-- flex-wrap because every digit box carries a hard `w-10` (40px) and is an
             <input>, whose automatic minimum size resolves to that definite width —
             the row cannot shrink, so without wrapping it overflows its parent. An
             eight-digit code needs 8x40 + 7x8 = 376px, and the sign-in card an OTP
             field normally sits in offers about 320px of content width, so it runs
             past the edge. Wrapping is the only adjustment that keeps the boxes at
             their designed size; shrinking them would make the digits unreadable. --}}
        class="flex flex-wrap gap-2"
        role="group"
        aria-label="{{ $label ?? $attributes->get('aria-label') ?? __('wirekit::One-time code') }}"
    >
        @for($i = 0; $i < $length; $i++)
            <input
                type="{{ $masked ? 'password' : 'text' }}"
                {{-- A numeric keypad is right only for a numeric alphabet; offering
                     one for a code containing letters hides the keys the reader
                     needs. The autocapitalize/spellcheck pair is for the letter
                     case: mobile keyboards otherwise start lowercase and browsers
                     underline the "misspelled" code. --}}
                inputmode="{{ $alphabetIsNumeric ? 'numeric' : 'text' }}"
                pattern="{{ $alphabetPattern }}"
                @unless($alphabetIsNumeric)
                    autocapitalize="characters"
                    spellcheck="false"
                @endunless
                maxlength="1"
                autocomplete="one-time-code"
                {{-- `-digit-N`, and the segment is load-bearing. `DomId::unique`
                     dedupes a repeated name by appending `-2`, `-3`, … — so with a
                     bare `-N` the digit index and the dedupe counter shared one
                     namespace, and `code-2` meant both "digit 2 of the first
                     control" and "the second control called code". One otp-input
                     plus ANY second same-name control emitted that id twice, at
                     the default length, which is precisely the state `dedupe_ids`
                     exists to remove. A dedupe suffix is always numeric, so a word
                     segment cannot collide with one. --}}
                id="{{ $id }}-digit-{{ $i }}"
                {{-- Placeholders rather than concatenation: a language that orders
                     the words differently cannot be served by a fixed word order. --}}
                aria-label="{{ __('wirekit::Digit :position of :total', ['position' => $i + 1, 'total' => $length]) }}"
                @if($hasError) aria-invalid="true" @endif
                {{-- Digit 0 only. `autofocus` is honored on the FIRST element in
                     the document carrying it and silently ignored on the rest, so
                     emitting it per box would look like six requests and behave
                     like one — and the one it behaved like would be right only by
                     accident of source order. The plain HTML attribute rather
                     than a scripted focus: it works before Alpine boots and under
                     a Content-Security-Policy that stops it booting at all. --}}
                @if($i === 0 && $autofocus) autofocus @endif
                @if($i === 0 && $describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                {{-- Every box reports the pending state: the code is one value,
                     and which box happens to be focused when the answer arrives
                     is not something the reader chose. --}}
                @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
                class="wk-field {{ $digitClasses }} {{ $stateClasses }}"
                x-ref="digit{{ $i }}"
                {{-- Selects the cell on focus, so a filled cell overwrites like an empty
                     one. Without it, `maxlength="1"` plus a caret after the existing
                     character means the browser refuses the keystroke, `onInput` never
                     fires, and correcting a code costs a deletion per cell. --}}
                @focus="onFocus($event)"
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

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif
</div>
