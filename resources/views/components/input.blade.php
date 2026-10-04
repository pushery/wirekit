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
    // Explained in a tooltip from a question mark beside the label, and read as the
    // field's description (partials/field-help).
    'help' => null,
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
    // What the X says it clears, as its accessible name and its tooltip. A field whose X
    // lifts more than the text (a found receipt, a started return) says so here.
    'clearLabel' => null,
    'copyable' => false,
    // A count of the characters under the field, counted in the browser as they are typed, with no
    // request to the server: `true` shows the number, "12 / 60" when the field has a maxlength; a
    // string is the sentence that shows it, with :count, :max and :remaining.
    'counter' => false,
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

@aware(['announceErrors' => null, 'alignFields' => false, 'wkField' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` would otherwise mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $hideLabel = BooleanProp::from($hideLabel, false);
    $reserveMessage = BooleanProp::from($reserveMessage, false);
    $clearable = BooleanProp::from($clearable, false);
    $clearText = filled($clearLabel) ? (string) $clearLabel : __('wirekit::Clear input');
    $mono = BooleanProp::from($mono, false);

    // The field-value font: mono for codes/measurements, otherwise the sans stack.
    // Used by BOTH the bare input and the wrapped input so the two render alike.
    $fontFamilyClass = $mono
        ? 'font-[family-name:var(--font-wk-mono)]'
        : 'font-[family-name:var(--font-wk-sans)]';
    $copyable = BooleanProp::from($copyable, false);
    // `counter`: a string that is a sentence is the format; one that only says yes or no is that.
    $counterFormat = is_string($counter) && ! in_array(strtolower(trim($counter)), ['', '0', '1', 'false', 'true'], true) ? $counter : null;
    $counter = $counterFormat !== null || BooleanProp::from($counter, false);
    $required = BooleanProp::from($required, false);
    $disabled = BooleanProp::from($disabled, false);
    $readonly = BooleanProp::from($readonly, false);

    // `@aware` reads a value from the parent component, but — unlike `@props` —
    // it does NOT remove that key from the attribute bag. So when the key is also
    // written as an attribute on the tag, it survives into `{{ $attributes }}` and
    // renders as a stray HTML attribute on the element. Blade accepts both
    // spellings on a tag, so both are dropped here.
    $attributes = $attributes->except(['announceErrors', 'announce-errors', 'alignFields', 'align-fields', 'wkField', 'wk-field']);

    // In a row that lines its fields up (`row align-fields`), the field takes the row's three
    // tracks itself, label, control and message, as `field` does: a button beside it then
    // stands level with the control whatever is above or below it. Not inside a `field`,
    // which takes the tracks for the control it wraps.
    $inAlignedRow = \Pushery\WireKit\Support\BooleanProp::from($alignFields, false)
        && ! ($wkField instanceof \Pushery\WireKit\Support\FieldControl);
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

    // A caller's `wire:key`, `x-show`, `wire:show` and their transitions are about the whole
    // component, so they go on the outermost element while the bag lands further in: see
    // Support\OuterAttributes.
    [$outerAttributes, $attributes] = \Pushery\WireKit\Support\OuterAttributes::split($attributes);

    // The id from the attribute or the name; with neither, DomId counts one per request.
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
    // One description list for the control: the component's own id first, then a caller's
    // aria-describedby. Written as separate attributes, the parser kept only the first copy,
    // so a caller's description was dropped or pushed the component's own out.
    $describedBy = trim(
        ($hasError ? $id.'-error' : ($hasSuccess && $successMessage ? $id.'-success' : ($hint ? $id.'-hint' : '')))
        .' '.((string) $attributes->get('aria-describedby', ''))
    );
    // The field's help, after its own message: what the field is for. Only beside a label,
    // which is where its hidden copy is rendered.
    $helpId = filled($help) && filled($label) ? $id.'-help' : null;
    $describedBy = trim($describedBy.' '.($helpId ?? ''));

    // A native date, time or date-and-time field takes a grid with its content centered on the
    // block axis. WebKit centers the text only while the field lays its parts out in a row or a
    // grid: given a block box, the text sat against the top edge, 8px above the middle of a 40px
    // field. A grid rather than a flex row, because Blink's date field holds its text and its
    // calendar icon in an inner box that a flex row shrinks to their width, so the icon stood right
    // behind the date with empty field after it; a grid item stretches across the field and the
    // icon keeps the end edge. The other engines center the text either way.
    $isNativeTemporal = in_array($type, ['date', 'time', 'datetime-local'], true);

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
        $isNativeTemporal ? 'grid items-center w-full' : 'block w-full',
        $fontFamilyClass,
        'tracking-[var(--font-wk-letter-spacing)]',
        'bg-[var(--color-wk-bg-input)]',
        // Read-only takes the muted surface, so a field that accepts no typing does not look
        // like one that does. Not the disabled treatment: the value stays fully legible,
        // selectable and focusable, and it is still sent with the form.
        '[&[readonly]]:bg-[var(--color-wk-bg-muted)]',
        'text-[color:var(--color-wk-text)]',
        'placeholder:text-[color:var(--color-wk-text-placeholder)]',
        'border-[length:var(--border-wk-width)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        '[&:user-invalid:not([data-wk-cleared])]:border-[var(--color-wk-border-error)]',
        '[&:user-invalid:not([data-wk-cleared]):focus-visible]:ring-[var(--color-wk-danger)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:cursor-not-allowed',
    ]), $scope);

    // Border and focus-ring color switch between error, success, and normal state — all via
    // tokens. Each state names its own ring color and the base list names none: two ring colors
    // on one element are decided by the stylesheet's order, which put the resting ring over the
    // error one.
    $stateClasses = match (true) {
        (bool) $hasError => 'border-[var(--color-wk-border-error)] focus-visible:ring-[var(--color-wk-danger)]',
        $hasSuccess => 'border-[var(--color-wk-border-success)] focus-visible:ring-[var(--color-wk-success)]',
        // The hover border belongs to the resting state only: on an error or a success border
        // it would win under the pointer, and the state would turn gray exactly while the
        // reader reaches for the field.
        default => 'border-[var(--color-wk-border-strong)] hover:border-[var(--color-wk-border-strong-hover)] focus-visible:ring-[var(--color-wk-ring)]',
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
    // flex wrapper so the buttons sit as inline siblings. The Alpine island that
    // drives clear() / copy() holds the buttons only, beside the field and never
    // around it, so an `x-ref` the caller puts on the field registers on the
    // caller's component.
    $hasAffordances = $clearable || $copyable;

    // How large the clear and copy buttons are. 24px meets WCAG 2.2 AA and is fine under a
    // mouse, and too small for a finger. `lg` is the size a field worked by finger reaches for,
    // so there they take the touch target on any pointer; on a coarse pointer every size does,
    // through the `wk-field-affordance` rule in dist/wirekit.css.
    $affordanceSizeClasses = $size === 'lg'
        ? 'min-w-[var(--size-wk-touch-target)] min-h-[var(--size-wk-touch-target)]'
        : 'min-w-[24px] min-h-[24px]';
    // Each button keeps --padding-wk-x-md to its right, the inset of the leading and trailing
    // slots, so a field with an icon in front and a button behind is as deep on both sides.
    // The leading/trailing icon slots live INSIDE the field frame, so — like
    // prefix/suffix and the affordance buttons — they route the field through the
    // flex wrapper.
    $hasLeading = isset($leading) && $leading->hasActualContent();
    $hasTrailing = isset($trailing) && $trailing->hasActualContent();
    $useWrapper = $prefix || $suffix || $hasAffordances || $hasLeading || $hasTrailing;
@endphp

@php
    // `failure: 'keep'` is what makes this component eligible at all.
    //
    // No `x-ref="control"`: the commit reads `$event.target.value`, which is
    // what the change event hands over anyway, and `keep` never writes on
    // failure — so the resync the ref would enable has nothing to do.
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
        'args' => (array) \Pushery\WireKit\Support\ListProp::renumbered($optimisticArgs),
        'failure' => 'keep',
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'kept' => __('wirekit::Could not save. Your entry is still here.'),
        ],
        'errorRegion' => \Pushery\WireKit\Support\CssIdentifier::idSelector($id.'-error'),
    ]);

    // With the optimistic layer this component renders a root around the field, which would
    // take a caller's `x-ref`. The name then moves to `x-wk-ref`, which registers the field on
    // the root above `data-wk-ref-scope` (resources/js/utils/caller-ref.js). Without the layer
    // there is no root of ours, and the caller's `x-ref` stays as written.
    $callerRef = $optimisticConfig ? trim((string) $attributes->get('x-ref', '')) : '';
    if ($callerRef !== '') {
        $attributes = $attributes->except('x-ref');
    }
@endphp

<div {{ $outerAttributes }} @if($inAlignedRow) data-wk-field @endif class="space-y-1.5 min-w-0" @if($callerRef !== '') data-wk-ref-scope @endif @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    @if($label)
        <x-wirekit::label :help="$help" :help-id="$helpId" :help-field="$name" :for="$id" :required="$required" :class="$hideLabel ? 'sr-only' : ''">{{ $label }}</x-wirekit::label>
    @elseif($inAlignedRow)
        {{-- An empty label track, held so that the control stays on the middle one. --}}
        <span data-wk-field-part="label" aria-hidden="true"></span>
    @endif

    @if($useWrapper)
        {{-- Wrapper: flex row places prefix/suffix (and the clearable/copyable
             affordance buttons) as inline siblings so the input padding adjusts
             to the actual content width instead of a hardcoded value. --}}
        <div
            @class([
            'flex items-center',
            // The frame is the field a finger aims at, so on a coarse pointer it takes the 44px
            // floor and the input inside gives its own up (dist/wirekit.css). Without the marker
            // a framed field stayed 40px tall on a phone while a plain one grew to 44.
            'wk-field-frame',
            'bg-[var(--color-wk-bg-input)]',
            // The frame paints the surface here, so read-only is read through it.
            'has-[input[readonly]]:bg-[var(--color-wk-bg-muted)]',
            'border-[length:var(--border-wk-width)]',
            'shadow-[var(--shadow-wk-sm)]',
            'overflow-hidden',
            'transition-colors',
            'duration-[var(--transition-wk-duration)]',
            'ease-[var(--transition-wk-easing)]',
            'has-[:focus-visible]:ring-[length:var(--ring-wk-width)]',
            'has-[:focus-visible]:ring-offset-[length:var(--ring-wk-offset)]',
            'has-[:focus-visible]:ring-offset-[var(--color-wk-ring-offset)]',
            // Mirror the inner input's :user-invalid state onto the wrapper
            // so the border and focus ring on the wrapper turn red too. Uses
            // :has() so we don't need any JS sync between input and wrapper.
            'has-[:user-invalid:not([data-wk-cleared])]:border-[var(--color-wk-border-error)]',
            'has-[:user-invalid:not([data-wk-cleared]):focus-visible]:ring-[var(--color-wk-danger)]',
            // One border and one ring color per state, as on the field without a frame.
            $hasError
                ? 'border-[var(--color-wk-border-error)] has-[:focus-visible]:ring-[var(--color-wk-danger)]'
                : ($hasSuccess
                    ? 'border-[var(--color-wk-border-success)] has-[:focus-visible]:ring-[var(--color-wk-success)]'
                    : 'border-[var(--color-wk-border-strong)] hover:border-[var(--color-wk-border-strong-hover)] has-[:focus-visible]:ring-[var(--color-wk-ring)]'),
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
                @if($hasError) aria-invalid="true" @endif
                @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
                @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
                @if($optimisticConfig)
                    x-bind:aria-busy="isPending"
                    {{-- `change`, not `input`: typing fires input per keystroke,
                         and the event that ends the input is leaving the
                         field. --}}
                    x-on:change="run($event.target.value)"
                @endif
                {{ $attributes->except('aria-describedby')->class([
                    'wk-field', // 16px iOS-zoom floor on phones (dist/wirekit.css)
                    'w-full h-full bg-transparent border-none shadow-none',
                    'block' => ! $isNativeTemporal,
                    'grid items-center' => $isNativeTemporal,
                    $fontFamilyClass,
                    'text-[color:var(--color-wk-text)]',
                    'placeholder:text-[color:var(--color-wk-text-placeholder)]',
                    'focus:outline-hidden focus:ring-0',
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

            @if($hasAffordances)
                {{-- The island that drives clear() / copy(). Its methods live in
                     resources/js/components/input.js, because an inline object literal
                     cannot declare methods under Alpine's CSP build. It holds the
                     buttons and the live region only: the field stays outside it, in
                     the caller's scope, so an `x-ref` on the field is the caller's.
                     The island reaches the field through the frame it sits in. --}}
                <span x-data="wirekitInput" class="shrink-0 inline-flex items-center">
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
                        class="wk-field-affordance shrink-0 inline-flex items-center justify-center {{ $affordanceSizeClasses }} mr-[var(--padding-wk-x-md)] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
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
                         (hasValue); empties + refocuses the field. An icon-only button, so its
                         name is also its tooltip; the tooltip does not describe the button a
                         second time, because the name already says the same words. --}}
                    <x-wirekit::tooltip :text="$clearText" :focusable-trigger="false" :describes="false" x-show="hasValue" x-cloak class="shrink-0">
                    <button
                        type="button"
                        @click="clear()"
                        {{-- A read-only field keeps its value, so the button that empties it is
                             out of use there too. The copy button above stays: copying changes
                             nothing, and `copyable readonly` is the documented token field. --}}
                        @if($disabled || $readonly) disabled @endif
                        aria-label="{{ $clearText }}"
                        class="wk-field-affordance shrink-0 inline-flex items-center justify-center {{ $affordanceSizeClasses }} mr-[var(--padding-wk-x-md)] rounded-[var(--radius-wk-sm)] text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] hover:bg-[var(--color-wk-bg-subtle)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-inset focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed transition-colors duration-[var(--transition-wk-duration)] cursor-pointer"
                    >
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/>
                        </svg>
                    </button>
                    </x-wirekit::tooltip>
                @endif

                {{-- Polite live region announces the copy success to screen readers. --}}
                {{-- The only feedback a screen-reader user gets after copying — nothing changes visually. --}}
                <span aria-live="polite" aria-atomic="true" class="sr-only" x-text="copied ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copied to clipboard')) }} : ''"></span>
                </span>
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
            @if($hasError) aria-invalid="true" @endif
            @if($describedBy !== '') aria-describedby="{{ $describedBy }}" @endif
            @if($callerRef !== '') x-wk-ref="{{ $callerRef }}" @endif
            @if($optimisticConfig)
                x-bind:aria-busy="isPending"
                x-on:change="run($event.target.value)"
            @endif
            {{-- wk-field: 16px iOS-zoom floor on phones (dist/wirekit.css) --}}
            {{ $attributes->except('aria-describedby')->class(['wk-field', $inputClasses, $stateClasses, $sizeClasses]) }}
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
    @if($counter)
        {{-- With a count, the message and the count share one line, and in an aligned row one track:
             a fourth part would have no track of its own in the row's subgrid. --}}
        <div class="flex items-start gap-[var(--gap-wk-sm)]">
            <div class="min-w-0 flex-1">
    @endif
    @if($inAlignedRow && ! $counter && ! ($reserveMessage || ($hasError && $errorMessage) || ($hasSuccess && $successMessage) || $hint))
        {{-- Nothing below the control, and the track still ends here, or the row's next field
             would be pulled up into it. --}}
        <span data-wk-field-part="message" aria-hidden="true"></span>
    @endif
    @if($reserveMessage && ! (($hasError && $errorMessage) || ($hasSuccess && $successMessage) || $hint))
        <p data-wk-prose-skip aria-hidden="true" class="select-none text-[length:var(--text-wk-sm)]">&nbsp;</p>
    @endif
    @if($hasError && $errorMessage)
        <p data-wk-prose-skip id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hasSuccess && $successMessage)
        <p data-wk-prose-skip id="{{ $id }}-success" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-success-text)]">{{ $successMessage }}</p>
    @elseif($hint)
        <p data-wk-prose-skip id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
    @if($counter)
            </div>
            @include('wirekit::components.partials.character-counter', [
                'counterFor' => $id,
                'counterFormat' => $counterFormat,
                'counterValue' => $attributes->get('value'),
                'counterMax' => $attributes->get('maxlength'),
            ])
        </div>
    @endif

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif
</div>
