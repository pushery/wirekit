{{-- optimistic-ui: supported
     The commit-boundary obstacle this used to carry is SOLVED — a value stream
     commits at an EVENT, never a timer, and `_endDrag()` already is one (it now
     ends on pointercancel too, which was a real listener leak, not just an
     optimistic concern).

     What blocks it is a different rule, and it was not visible until the drag
     path was looked at closely: this control is MIXED. The plane, the hue strip
     and the swatches are discrete picks whose previous value belongs to the
     server, so an undo there costs nothing. **The hex field is typed**, and a
     rollback may never delete what the user wrote.

     Enabling only the drag paths is not a way out — it is the same trap as
     number-input: a value typed into the field while a drag's request is in
     flight would be overwritten by that request's rollback.

     THAT BLOCKER IS GONE, and resolving it is what let this ship. It said the
     component needs "the fourth exit for text (keep the value, mark it unsaved,
     say so), which number-input, otp-input and the text fields are waiting on" —
     and the fourth exit shipped. `failure: 'keep'` never rolls back, so nothing
     typed can be destroyed and the mixed-control argument no longer applies. The
     price is that a refused color stays on screen and says it was not saved,
     which the reader can act on: the previous color is one click away in recents.

     TWO THINGS ABOUT THE SHAPE, both found by counting call sites rather than
     following the first plausible one.

     The layer WRAPS this component instead of nesting inside it, and that follows
     from the value being DERIVED. `h`/`s`/`v`/`a` with `formattedValue` computed
     from them means there is no single writable property to bind — so the layer
     holds the value and the component hands it up through `run()`. A child
     reaches its parent, never the reverse, which puts the layer on the outside.
     Every component wired before this one bound a real property and nested the
     other way round.

     The commit boundary is `_commitRecent()`, NOT `_sync(true)` as first assumed.
     `_sync` looked like the seam because its argument separates drag-in-progress
     from settled — but two of the four settled paths (`pickColor`, `eyedropper`)
     call `_commitRecent()` directly and never pass through `_sync(true)` at all.
     Hooking `_sync` would have left a swatch click silently uncommitted, with
     nothing failing. `_commitRecent()` is where a color is settled in all four
     cases, which is exactly why a color lands in "recents" there and nowhere
     else — the boundary was already in the component, under another name. --}}
@props([
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk.
    'required' => false,
    // `required` — DECLARED rather than left to the attribute bag. Undeclared, Blade folded it
    // into the bag and it landed on a wrapper div, where it is invalid HTML that nothing
    // reads: no native constraint, no aria-required, no asterisk.
    'required' => false,
    // The Livewire method to call once a color is settled — a released drag, a
    // swatch, an arrow-key nudge. A refusal KEEPS the color and says it was not
    // saved: the hex field is typed, and a rollback may never delete what the
    // reader wrote. See the note at the top of this file.
    // Extra arguments appended to the optimistic action call, after the new value.
    // A list of identical controls — one per row — needs to tell the server WHICH row,
    // and the optimistic layer has always been able to carry that: it spreads `args`
    // into the call. No component exposed it, so the capability existed and was
    // unreachable, and the only way to build the commonest optimistic surface there is
    // was to hand-mount the factory and give up the component.
    'optimisticArgs' => [],
    'optimistic' => null,
    'name' => null,
    'id' => null,
    // `error` and `hint` were undeclared on the one Form-category CONTROL that never
    // got them, so `:error="$errors->first('brand_color')"` landed in the attribute
    // bag and rendered as `error="…"` on the native `<input type="color">` — invalid
    // HTML carrying a validation message the reader is never shown. The same class was
    // closed once for the four controls that take a `label`; this one takes a `name`
    // instead and fell outside that reading of it.
    //
    // No `label` prop comes with them, deliberately. The accessible name here is the
    // DEFAULT SLOT (rendered `sr-only` beside the swatch, documented on the component
    // page and sanctioned in the accessibility rules), and a second naming path would
    // give the component two ways to be named with nothing deciding between them.
    'error' => null,
    'hint' => null,
    // Whether the error paragraph announces itself. Same precedence chain as every
    // sibling control: explicit prop > the enclosing form's @aware value > config.
    'announceError' => null,
    'value' => '#000000',
    'size' => config('wirekit.components.color-picker.size', 'md'),
    'showValue' => true,
    'disabled' => false,
    // Popover mode — a from-scratch HSV picker (SV plane + hue + alpha + format
    // input + swatches + eyedropper + copy + recents). Default false keeps the
    // native <input type="color"> render byte-identical. Boolean flag (not a `mode`
    // prop) per the prop-naming convention — same call the date-picker `range` made.
    'popover' => false,
    'format' => config('wirekit.components.color-picker.format', 'hex'), // hex | rgb | hsl | oklch
    // Popover mode only: on touch-primary devices (pointer: coarse) open the
    // platform's native color sheet instead of the custom panel — the same
    // dialog the default (non-popover) variant gets for free. Opt-in; desktop
    // pointers always get the popover.
    'nativeOnMobile' => config('wirekit.components.color-picker.native-on-mobile', false),
    'withAlpha' => true,
    'withEyedropper' => true,
    // Popover mode only: render a "clear" (no color) button that empties the
    // bound form value. The native <input type="color"> cannot represent an
    // empty value (HTML coerces it to #000000), so this is popover-only. Off by
    // default — "no color" is rarely a valid state on a color control.
    'withClear' => false,
    'withRecents' => true,
    'presets' => [],
    'recentsKey' => null,
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showValue = BooleanProp::from($showValue, true);
    $disabled = BooleanProp::from($disabled, false);
    $popover = BooleanProp::from($popover, false);
    $withAlpha = BooleanProp::from($withAlpha, true);
    $withEyedropper = BooleanProp::from($withEyedropper, true);
    $withClear = BooleanProp::from($withClear, false);
    $withRecents = BooleanProp::from($withRecents, true);
    // Same contract, different spelling of the default: a `config()` fallback is as much a
    // boolean declaration as a literal, and the switch decides whether a touch device gets
    // the native color panel or this one.
    $nativeOnMobile = BooleanProp::from($nativeOnMobile, false);
    $required = BooleanProp::from($required, false);

    use Illuminate\Support\Str;
    use Pushery\WireKit\WireKit;

    // `@aware` reads a value from the parent component, but — unlike `@props` — it does
    // NOT remove that key from the attribute bag, so written on the tag it survives into
    // `{{ $attributes }}` and renders as a stray HTML attribute. Blade accepts both
    // spellings, so both are dropped. BEFORE the unknown-prop warning below, as in every
    // sibling control: the key is understood here, and warning about it would be noise.
    $attributes = $attributes->except(['announceErrors', 'announce-errors']);

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('color-picker', $attributes->getAttributes());

    // HTML reads a boolean attribute by PRESENCE, so `disabled="false"` disables the
    // control — the opposite of what the call site says, with no error either way.
    // Strip such flags when their value reads as false, before the bag reaches the control.
    $attributes = BooleanProp::stripFalseHtmlFlags($attributes);

    // announce-error precedence: explicit prop > form container (@aware announceErrors) > global config.
    $announceError ??= $announceErrors ?? config('wirekit.a11y.announce_error', true);

    // Page-unique. The name still shapes the id — `name="brand"` is still `wk-color-brand`,
    // which is what makes it readable — but it goes through the deduper, so a SECOND picker
    // bound to the same field (which is what a repeater row is) becomes `wk-color-brand-2`
    // instead of colliding, where the second one's `<label for>` pointed at the first one's
    // input and clicking it focused the wrong control.
    //
    // `Str::random(6)` for the nameless case had the opposite problem: it changed on every
    // render, so a Livewire morph broke the association it had just made. `DomId::unique`
    // counts instead of randomizing, which survives one.
    $pickerId = \Pushery\WireKit\Support\DomId::unique($id ?? ($name ? 'wk-color-'.$name : null), 'wk-color-');

    // Explicit prop OR the Laravel validation bag, keyed by `name`. The read is
    // guarded on the name being there: `MessageBag::has(null)` falls through to
    // `any()`, so an unnamed picker would paint itself invalid the moment any
    // unrelated field on the page failed validation.
    $hasError = $error || ($name && ($errors ?? null)?->has($name));
    $errorMessage = $error ?? ($name ? ($errors ?? null)?->first($name) : null);

    // The error REPLACES the hint in the single paragraph below, so the description
    // names whichever id is actually on the page. An idref pointing at nothing is not
    // a partial description — assistive technology drops it in silence.
    //
    // Composed once because this component has FOUR shapes that can be the control:
    // the native input, the nativeOnMobile input, the trigger-slot button and the
    // default swatch button. Spelling the wiring out four times is how three of them
    // end up out of step with the fourth.
    $controlDescribedBy = $hasError ? $pickerId.'-error' : ($hint ? $pickerId.'-hint' : null);

    $swatchSize = match ($size) {
        'sm' => 'w-8 h-8',
        'lg' => 'w-12 h-12',
        default => 'w-10 h-10',
    };

    $popoverValue = (bool) $popover;
    $formatValue = match ($format) {
        'hex', 'rgb', 'hsl', 'oklch' => $format,
        default => WireKit::validateProp('color-picker', 'format', $format, ['hex', 'rgb', 'hsl', 'oklch']),
    };

    $wrapperClasses = WireKit::resolveClasses('color-picker', 'wrapper', 'inline-flex items-center gap-[var(--padding-wk-x-sm)]', $scope);

    // One stacking wrapper around BOTH render branches, so the hint / error paragraph
    // has somewhere to go: the picker's own root is an inline-flex ROW, and a message
    // dropped into it would render beside the swatch instead of under it. Inline-level
    // for the same reason the root is — a picker sits inside a form row, not on a line
    // of its own — and `items-start` keeps the swatch at its intrinsic width.
    $fieldClasses = WireKit::resolveClasses('color-picker', 'field', 'inline-flex flex-col items-start gap-[var(--gap-wk-xs)]', $scope);

    $swatchClasses = WireKit::resolveClasses('color-picker', 'swatch', implode(' ', [
        'relative inline-block',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        // The swatch ring is this control's boundary, so it carries the error state the
        // way every sibling control does. Outside the error state it stays on the
        // decorative token: a ring around an ARBITRARY user-chosen color cannot be held
        // to a contrast floor against what is inside it (FormControlBorderTokenTest).
        $hasError ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border)]',
        'overflow-hidden',
        'cursor-pointer',
        'focus-within:ring-[length:var(--ring-wk-width)]',
        'focus-within:ring-[var(--color-wk-ring)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        $swatchSize,
    ]), $scope);

    // The input extends 8px BELOW the visible swatch (height = label + 4px top
    // overhang + 12px bottom): the browser anchors its native color panel to the
    // INPUT's element box, so the extra (overflow-clipped, invisible) zone pushes
    // the panel the same 8px off the swatch as the house popover offset (without
    // it the native panel sat flush on the circle). The label's overflow-hidden keeps
    // the visual swatch byte-identical; the hit area grows 8px downward.
    $inputClasses = WireKit::resolveClasses('color-picker', 'input', implode(' ', [
        // Replaced elements don't stretch between insets — height must be
        // EXPLICIT: label height + 4px top overhang + 12px bottom extension.
        'absolute -inset-x-1 -top-1',
        'w-[calc(100%+0.5rem)] h-[calc(100%+1rem)]',
        'cursor-pointer',
        'border-0 p-0 bg-transparent',
        'disabled:cursor-not-allowed',
    ]), $scope);

    $valueClasses = WireKit::resolveClasses('color-picker', 'value', implode(' ', [
        'font-[family-name:var(--font-wk-mono)]',
        'text-[length:var(--text-wk-sm)]',
        'text-[color:var(--color-wk-text)]',
        'uppercase tracking-wider',
    ]), $scope);
@endphp

{{-- The field wrapper wraps BOTH branches so the hint / error paragraph below is a
     sibling of whichever picker rendered, never a child of its inline-flex row. It is
     unconditional on purpose: a wrapper that only appears when a message does gives the
     component two DOM shapes, and the one a developer inspects is whichever they hit
     first. --}}
<div class="{{ $fieldClasses }}">
@if(! $popoverValue)
    {{-- ── Native mode (default). ── Slightly wider swatch↔readout gap than the
         shared wrapper default: the hex pill sits inline next to the swatch, and
         the base --padding-wk-x-sm (10px) read as crowding it. --gap-wk-md (12px)
         gives the readout clear separation. The appended gap-* utility wins over
         the base one in $wrapperClasses (later source order); the popover branch
         keeps the base gap untouched. --}}
    <div x-data="{ current: {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }} }" class="{{ $wrapperClasses }} gap-[var(--gap-wk-md)]">
        <label for="{{ $pickerId }}" class="{{ $swatchClasses }}">
            <input
                type="color"
                {{-- aria-required, not the native attribute: HTML's `required` does not apply
                     to a color input, which always carries a value. --}}
                @if($required) aria-required="true" @endif
                @if($name) name="{{ $name }}" @endif
                id="{{ $pickerId }}"
                :value="current"
                @input="current = $event.target.value"
                @if($disabled) disabled @endif
                @if($hasError) aria-invalid="true" @endif
                @if($controlDescribedBy) aria-describedby="{{ $controlDescribedBy }}" @endif
                {{ $attributes->class([$inputClasses]) }}
            />
            @if(trim((string) $slot) !== '')
                <span class="sr-only">{{ $slot }}</span>
            @else
                {{-- The ONLY accessible name the native `<input type="color">` gets — there is no
                     visible label beside it. It goes through the catalog for the same reason the
                     popover branch below does: both keys already ship in every locale, so a
                     literal here hands a translated application a fully localized picker with one
                     English name on it. The label guard could not see this one — it matches a
                     label as an element's whole raw text (`>Color picker<`), and this sat inside a
                     `{{ … }}` echo. --}}
                <span class="sr-only">{{ $name ? __('wirekit:::name color', ['name' => Str::headline((string) $name)]) : __('wirekit::Color picker') }}</span>
            @endif
        </label>
        @if($showValue)
            <span class="{{ $valueClasses }}" x-text="current" aria-live="polite"></span>
        @endif
    </div>
@else
    @php
        $jsConfig = [
            'value' => $value,
            'format' => $formatValue,
            'withAlpha' => (bool) $withAlpha,
            'withClear' => (bool) $withClear,
            'recentsKey' => $recentsKey,
            'nativeOnMobile' => (bool) $nativeOnMobile,
            // The cleared readout is spoken through an aria-live region and this file is
            // the only place with a translator, so the word travels with the config.
            'noColorLabel' => __('wirekit::No color'),
            'copiedLabel' => __('wirekit::Copied :value'),
            'hexErrorLabel' => __('wirekit::Not a valid color value'),
        ];
        // With withClear on, the readout binds the displayValue getter (which
        // shows "No color" while cleared); otherwise the plain formattedValue,
        // byte-identical to before withClear existed.
        $readoutExpr = $withClear ? 'displayValue' : 'formattedValue';
        // Checkerboard backdrop so alpha is legible — a structural transparency
        // grid (like the SV-plane white/black axes, it is NOT themeable).
        $checker = 'background-image: linear-gradient(45deg, var(--color-wk-border) 25%, transparent 25%), linear-gradient(-45deg, var(--color-wk-border) 25%, transparent 25%), linear-gradient(45deg, transparent 75%, var(--color-wk-border) 75%), linear-gradient(-45deg, transparent 75%, var(--color-wk-border) 75%); background-size: 8px 8px; background-position: 0 0, 0 4px, 4px -4px, -4px 0;';

        // `value` rather than `bind`: the color here is DERIVED (h/s/v/a with
        // formattedValue computed from them), so there is no single writable
        // property for a layer to hold on the component's behalf. The layer keeps
        // it, and the component hands it up through `run()` — which also decides
        // the nesting: the component calls `this.run`, a child reaches its parent,
        // so the layer wraps this one instead of nesting inside it.
        //
        // `keep`, not `undo`, and the hex field being TYPED is why: a rollback
        // may never destroy what the reader wrote. The price is that a refused
        // color stays on screen and says it was not saved — actionable, because
        // the previous color is one click away in the recents strip.
        //
        // The JS DOES call `mark()` at pointerdown, and the reasoning that first
        // said it should not is worth keeping because it is a tempting mistake:
        // `keep` never rolls back, so why hold a baseline? Because `keep` covers
        // only the SERVER's refusal. A CANCELED request still restores — and a
        // second drag started during the first one's round trip is exactly that.
        // Without the mark, the restore writes back the value the drag produced,
        // so the marker stays put and the cancellation is invisible. The rule
        // that a baseline belongs to the START of the gesture holds for every
        // streaming control, regardless of which failure exit it takes.
        $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
            'value' => $value,
            'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
            'failure' => 'keep',
            // The field's own error region, so the layer stays quiet when that
            // paragraph is already speaking — one announcement per deviation, not
            // two saying different things about the same refusal.
            'errorRegion' => '#'.$pickerId.'-error',
            'debug' => (bool) config('app.debug'),
            // Two colors settled in quick succession would otherwise resolve by
            // whichever answer arrives last — network timing, which is both wrong
            // and untestable.
            'mode' => 'reject',
            'messages' => [
                'pending' => __('wirekit::Saving'),
                'kept' => __('wirekit::Could not save. Your color is still here.'),
            ],
        ]);
    @endphp

@if($optimisticConfig)
    {{-- `display: contents` so the picker keeps its own layout and its `relative`
         positioning context — the panel anchors through Floating UI and teleports
         to <body>, so the wrapper adds no containing block of its own. --}}
    <div x-data="wirekitOptimistic({{ $optimisticConfig }})" style="display: contents">
@endif
    <div
        x-data="wirekitColorPicker({{ \Pushery\WireKit\Support\AlpinePayload::from($jsConfig) }})"
        class="relative {{ $wrapperClasses }}"
        {{-- Resolves because the layer WRAPS this element: `isPending` lives on
             the parent, and a child reads its parent through the scope chain. --}}
        @if($optimisticConfig) x-bind:aria-busy="isPending" @endif
        {{-- escape is window-scoped: the panel teleports out of the document flow, so a keydown
             inside it bubbles to body (not this root) — a non-window escape here
             would never fire while focus is in the panel. The focus trap over the
             panel already handles Escape while focus is inside it; this stays as the
             path for an Escape pressed while focus sits elsewhere, and close() is a
             no-op on an already-closed picker. --}}
        @keydown.escape.window="open && close()"
    >
        @if($nativeOnMobile)
            {{-- nativeOnMobile trigger: on touch-primary devices (useNative, decided
                 once at init via `(pointer: coarse)`) the swatch IS the native-mode
                 label + <input type="color"> — a direct tap opens the OS color sheet,
                 no programmatic click needed, so it works in every mobile browser.
                 x-if (not x-show) keeps exactly ONE interactive trigger in the live
                 DOM. The input's own color well fills the clipped circle, and its
                 input/change events feed the same HSV state + hidden form field the
                 popover path uses (change commits to recents). Native sheets pick
                 opaque sRGB hex — alpha keeps its current value. --}}
            <template x-if="useNative">
                <label for="{{ $pickerId }}-native" class="{{ $swatchClasses }}">
                    <input
                        type="color"
                        @if($required) aria-required="true" @endif
                        id="{{ $pickerId }}-native"
                        :value="hex"
                        @input="onInput($event.target.value)"
                        @change="pickColor($event.target.value)"
                        @if($disabled) disabled @endif
                        @if($hasError) aria-invalid="true" @endif
                        @if($controlDescribedBy) aria-describedby="{{ $controlDescribedBy }}" @endif
                        class="{{ $inputClasses }} disabled:opacity-[var(--opacity-wk-disabled)]"
                    />
                    {{-- Same name, same catalog call as the native branch above and the popover
                         trigger below. This arm is the one a phone reaches, so an untranslated
                         literal here is invisible to every desktop check. --}}
                    <span class="sr-only">{{ $name ? __('wirekit:::name color', ['name' => Str::headline((string) $name)]) : __('wirekit::Color picker') }}</span>
                </label>
            </template>
        @endif

        {{-- Trigger. A custom `trigger` slot (popover mode) replaces the default
             swatch — WireKit still wires the open toggle, the x-ref Floating-UI
             anchor, and the aria-haspopup/aria-expanded dialog semantics; the
             slot supplies the visible content (its text / icon is the accessible
             name, like any icon-button). Without the slot, the default swatch
             shows the live color over a transparency checker. --}}
        @if($nativeOnMobile)<template x-if="!useNative">@endif
        @isset($trigger)
            <button
                type="button"
                id="{{ $pickerId }}"
                x-ref="trigger"
                @click="togglePanel()"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="dialog"
                @if($disabled) disabled @endif
                @if($hasError) aria-invalid="true" @endif
                @if($controlDescribedBy) aria-describedby="{{ $controlDescribedBy }}" @endif
                class="inline-flex items-center cursor-pointer rounded-[var(--radius-wk-sm)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed"
            >
                {{ $trigger }}
            </button>
        @else
            <button
                type="button"
                id="{{ $pickerId }}"
                x-ref="trigger"
                @click="togglePanel()"
                :aria-expanded="open ? 'true' : 'false'"
                aria-haspopup="dialog"
                aria-label="{{ $name ? __('wirekit:::name color', ['name' => Str::headline((string) $name)]) : __('wirekit::Color picker') }}"
                @if($disabled) disabled @endif
                @if($hasError) aria-invalid="true" @endif
                @if($controlDescribedBy) aria-describedby="{{ $controlDescribedBy }}" @endif
                class="{{ $swatchClasses }} disabled:opacity-[var(--opacity-wk-disabled)] disabled:cursor-not-allowed"
                style="{{ $checker }}"
            >
                <span class="absolute inset-0" :style="swatchStyle"@if($withClear) x-show="!cleared"@endif></span>
            </button>
        @endisset
        @if($nativeOnMobile)</template>@endif

        @if($showValue)
            <span class="{{ $valueClasses }}" x-text="{{ $readoutExpr }}" aria-live="polite"></span>
        @endif

        {{-- Hidden form field — mirrors the picked value in the active format. --}}
        <input type="hidden" x-ref="input" @if($name) name="{{ $name }}" @endif value="{{ $value }}" />

        {{-- Picker panel — teleported out of the document flow + Floating-UI positioned (see
             wirekitColorPicker._anchor) so it escapes any clipping/stacking
             ancestor and sits a clear gap below the swatch (no longer flush
             against the trigger circle). click.outside lives HERE (on the panel),
             not the root, because teleporting moves the panel out of the subtree. --}}
        <template x-teleport="#wk-overlay-root">
        <div
            x-show="open"
            x-cloak
            x-ref="panel"
            x-transition.opacity
            @click.outside="close()"
            role="dialog"
            aria-label="{{ __('wirekit::Color picker') }}"
            class="fixed z-[var(--z-wk-dropdown,50)] w-[18rem] space-y-3 rounded-[var(--radius-wk-lg)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] bg-[var(--color-wk-bg-elevated)] p-[var(--padding-wk-x-md)] shadow-[var(--shadow-wk-lg)]"
        >
            {{-- Saturation / value plane. The hue tints the base; white→transparent
                 (left→right) is saturation, transparent→black (top→bottom) is value.
                 The #fff/#000 axes are intrinsic to an HSV plane, not themeable. --}}
            <div
                x-ref="plane"
                @pointerdown="startPlane($event)"
                role="slider"
                tabindex="0"
                aria-label="{{ __('wirekit::Saturation and brightness') }}"
                {{-- `aria-valuenow` is REQUIRED on `slider` — a slider without it is
                     malformed to a conformance checker and announces no numeric
                     position at all. This one control moves on two axes, so the
                     number carries saturation (the inline axis, the one the
                     left/right keys drive) and `aria-valuetext` keeps saying both.
                     Text wins over the number wherever a reader supports it, so
                     nothing is lost by picking an axis for the numeric channel. --}}
                :aria-valuenow="s"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuetext="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::saturation :saturation%, brightness :brightness%')) }}.replace(':saturation', s).replace(':brightness', v)"
                @keydown.arrow-left.prevent="nudgePlane(-1, 0)"
                @keydown.arrow-right.prevent="nudgePlane(1, 0)"
                @keydown.arrow-up.prevent="nudgePlane(0, 1)"
                @keydown.arrow-down.prevent="nudgePlane(0, -1)"
                {{-- The plane announces `role="slider"`, and the hue and opacity sliders have
                     carried Home/End since they were written. This one had neither, so a
                     reader following the pattern the role promises got no response. --}}
                @keydown.home.prevent="planeHome()"
                @keydown.end.prevent="planeEnd()"
                class="relative h-40 w-full cursor-crosshair touch-none overflow-hidden rounded-[var(--radius-wk-md)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                :style="planeStyle"
            >
                <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(to right, #fff, transparent);"></div>
                <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(to top, #000, transparent);"></div>
                {{-- Thumb ring stays theme-independent white (+ shadow) on all three
                     sliders: it must contrast against ARBITRARY colors underneath, so a
                     themed border would vanish against same-toned regions. --}}
                <div class="pointer-events-none absolute h-3.5 w-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow-[var(--shadow-wk-sm)]" :style="planeMarkerStyle"></div>
            </div>

            {{-- Hue slider — the full spectrum (structural, not themeable). --}}
            <div
                x-ref="hue"
                @pointerdown="startHue($event)"
                role="slider"
                tabindex="0"
                aria-label="{{ __('wirekit::Hue') }}"
                :aria-valuenow="h"
                aria-valuemin="0"
                aria-valuemax="360"
                {{-- The full slider key model, not half of it. Up/Down mirror
                     Right/Left and Home/End go to the ends of the range: a hue
                     step of 2 over 0–360 means reaching pure red from the far
                     side of the strip costs 180 keypresses without them, and a
                     reader who follows the announced `slider` pattern gets no
                     response from keys the role promises. --}}
                @keydown.arrow-left.prevent="nudgeHue(-2)"
                @keydown.arrow-right.prevent="nudgeHue(2)"
                @keydown.arrow-down.prevent="nudgeHue(-2)"
                @keydown.arrow-up.prevent="nudgeHue(2)"
                @keydown.home.prevent="setHue(0)"
                @keydown.end.prevent="setHue(360)"
                class="relative h-3 w-full cursor-pointer touch-none rounded-full focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                style="background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%);"
            >
                <div class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-sm)]" :style="hueMarkerStyle"></div>
            </div>

            @if($withAlpha)
                {{-- Alpha slider over a transparency checker. --}}
                <div
                    x-ref="alpha"
                    @pointerdown="startAlpha($event)"
                    role="slider"
                    tabindex="0"
                    aria-label="{{ __('wirekit::Opacity') }}"
                    :aria-valuenow="alphaPercent()"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    {{-- Same full key model as the hue strip above: Up/Down mirror
                         Right/Left, Home is fully transparent and End fully opaque. --}}
                    @keydown.arrow-left.prevent="nudgeAlpha(-0.05)"
                    @keydown.arrow-right.prevent="nudgeAlpha(0.05)"
                    @keydown.arrow-down.prevent="nudgeAlpha(-0.05)"
                    @keydown.arrow-up.prevent="nudgeAlpha(0.05)"
                    @keydown.home.prevent="setAlpha(0)"
                    @keydown.end.prevent="setAlpha(1)"
                    class="relative h-3 w-full cursor-pointer touch-none rounded-full focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    style="{{ $checker }}"
                >
                    <div class="pointer-events-none absolute inset-0 rounded-full" :style="alphaTrackStyle"></div>
                    <div class="pointer-events-none absolute top-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white bg-[var(--color-wk-bg-elevated)] shadow-[var(--shadow-wk-sm)]" :style="alphaMarkerStyle"></div>
                </div>
            @endif

            {{-- Format toggle + editable value field.

                 Every control in the panel below spells out `cursor-pointer`.
                 Tailwind v4's preflight sets `cursor: default` on `button`, and
                 the panel's buttons are styled entirely by these literal utility
                 strings — only the trigger and the swatch get a pointer from
                 elsewhere ($swatchClasses), which is what made the file look
                 covered while six controls were not. --}}
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="cycleFormat()"
                    class="shrink-0 cursor-pointer rounded-[var(--radius-wk-sm)] bg-[var(--color-wk-bg-muted)] px-[var(--padding-wk-x-sm)] py-1 text-[length:var(--text-wk-sm)] font-[number:var(--font-wk-body-weight)] text-[color:var(--color-wk-text-muted)] uppercase hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    {{-- The name STARTS with the word the button shows. A static
                         `aria-label` overrode the visible "hex"/"rgb" entirely, so
                         someone using voice control could not activate the control
                         by the word in front of them, and a reader heard a command
                         with no trace of the one piece of state the button
                         displays. WCAG 2.5.3, Label in Name — a name may add
                         context, as long as it begins from what is written. --}}
                    :aria-label="format + {{ \Pushery\WireKit\Support\AlpinePayload::from(', '.__('wirekit::Cycle color format')) }}"
                    x-text="format"
                ></button>
                <input
                    type="text"
                    :value="{{ $withClear ? 'popoverValue' : 'formattedValue' }}"
                    @change="onInput($event.target.value)"
                    aria-label="{{ __('wirekit::Color value') }}"
                    :aria-invalid="invalidInput ? 'true' : 'false'"
                    {{-- The field said "invalid" and nothing else: no message, and nothing
                         pointing at one. A reader heard that their value was wrong with no
                         way to learn WHY or what a right one looks like, which is what
                         WCAG 3.3.1 asks for in text. The region below is always present and
                         always referenced, so the description resolves — a `describedby`
                         pointing at a `display: none` element is ignored by assistive
                         technology, which is why it is emptied rather than hidden. --}}
                    aria-describedby="{{ $pickerId }}-hex-error"
                    spellcheck="false"
                    class="wk-field w-full rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] bg-[var(--color-wk-bg-input)] px-[var(--padding-wk-x-sm)] py-1 font-[family-name:var(--font-wk-mono)] text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)] focus:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    :class="invalidInput ? 'border-[var(--color-wk-border-error)]' : 'border-[var(--color-wk-border-strong)]'"
                />
                <span id="{{ $pickerId }}-hex-error" class="sr-only" role="alert" x-text="hexError"></span>
                @if($withEyedropper)
                    <button
                        type="button"
                        x-show="hasEyeDropper"
                        @click="eyedropper()"
                        class="shrink-0 cursor-pointer rounded-[var(--radius-wk-sm)] p-1 text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        aria-label="{{ __('wirekit::Pick a color from the screen') }}"
                    >
                        {{-- A recognizable PIPETTE silhouette (angled dropper barrel + tip).
                             The previous glyph was a pencil path — read as "edit", never as
                             "pick a color from the screen". --}}
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2 22 1-1h3l9-9"/><path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-3l9-9"/><path stroke-linecap="round" stroke-linejoin="round" d="m15 6 3.4-3.4a2.1 2.1 0 1 1 3 3L18 9l.4.4a2.1 2.1 0 1 1-3 3l-3.8-3.8a2.1 2.1 0 1 1 3-3l.4.4Z"/></svg>
                    </button>
                @endif
                <button
                    type="button"
                    @click="copy()"
                    class="shrink-0 cursor-pointer rounded-[var(--radius-wk-sm)] p-1 text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                    :aria-label="copied ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copied')) }} : {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copy color value')) }}"
                >
                    {{-- Canonical clipboard glyph (matches <x-wirekit::clipboard-button>);
                         stroke-width 2 on a clean single shape renders crisp at 16px —
                         the old two-rect "duplicate" glyph at 1.8 read as blurry. --}}
                    <svg x-show="!copied" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" /></svg>
                    {{-- Copied confirmation — a success-colored checkmark. --}}
                    <svg x-show="copied" x-cloak class="h-4 w-4 text-[color:var(--color-wk-success)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <span class="sr-only" aria-live="polite" x-text="copied ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Copied to clipboard')) }} : ''"></span>
                </button>
                @if($withClear)
                    {{-- Clear to "no color": empties the bound form value (popover
                         mode only — the native input cannot be empty). --}}
                    <button
                        type="button"
                        @click="clear()"
                        class="shrink-0 cursor-pointer rounded-[var(--radius-wk-sm)] p-1 text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-danger-text)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                        aria-label="{{ __('wirekit::Clear color') }}"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        <span class="sr-only" aria-live="polite" x-text="cleared ? {{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Color cleared')) }} : ''"></span>
                    </button>
                @endif
            </div>

            @if(! empty($presets))
                {{-- Developer preset swatches. --}}
                <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('wirekit::Preset colors') }}">
                    @foreach($presets as $preset)
                        <button
                            type="button"
                            @click="pickColor({{ \Pushery\WireKit\Support\AlpinePayload::from($preset) }})"
                            class="h-6 w-6 cursor-pointer rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                            style="background-color: {{ $preset }};"
                            aria-label="{{ __('wirekit::Use :color', ['color' => $preset]) }}"
                        ></button>
                    @endforeach
                </div>
            @endif

            @if($withRecents)
                {{-- Recent colors (localStorage, capped at 8). --}}
                <div x-show="recents.length" class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('wirekit::Recent colors') }}">
                    <template x-for="recent in recents" :key="recent">
                        <button
                            type="button"
                            @click="pickColor(recent)"
                            class="h-6 w-6 cursor-pointer rounded-[var(--radius-wk-sm)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] focus-visible:outline-hidden focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
                            :style="recentStyle(recent)"
                            :aria-label="{{ \Pushery\WireKit\Support\AlpinePayload::from(__('wirekit::Use :color')) }}.replace(':color', recent)"
                        ></button>
                    </template>
                </div>
            @endif
        </div>
        </template>
    </div>
@if($optimisticConfig)
    {{-- Rendered unconditionally and starting empty: a live region that arrives
         together with its text is a new node, and nothing is announced at all.
         Outside the picker's own root because the layer wraps it — `announcement`
         does not resolve inside the child. --}}
    <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
</div>
@endif
@endif

{{-- One region, the error winning over the hint — the same shape every sibling
     control renders, and the reason `$controlDescribedBy` names only one id. It sits
     OUTSIDE the optimistic wrapper deliberately: the rejected-state outline in the
     stylesheet selects that wrapper's own children, and the field's error paragraph is
     not part of what the optimistic layer withdrew. --}}
@if($hasError && $errorMessage)
    <p id="{{ $pickerId }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
@elseif($hint)
    <p id="{{ $pickerId }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
@endif
</div>
