{{-- optimistic-ui: n/a — passthrough
     The canonical passthrough: the developer's wire:click arrives through the attribute bag and this component never sees it. And a button holds no state of its own — the optimism belongs to whatever the click changes, which is why the prop lives there and not here. --}}
@props([
    'intent' => config('wirekit.components.button.intent', 'primary'),
    'surface' => config('wirekit.components.button.surface', 'filled'),
    'size' => config('wirekit.components.button.size', 'md'),
    'type' => 'button',
    // Let a label that a USER typed take a second line. Default false: a label the application
    // wrote belongs on one line, and a button that wraps mid-word is the defect there.
    'wrapLabel' => false,
    // On a touch screen, lift the label to the text floor the form fields lift to. Default false:
    // a button on its own is right at its designed size; this is for one that shares a row with a
    // field, where it would otherwise read a size below the field beside it.
    'touchTextFloor' => false,
    // Show the icon alone and keep the label as the accessible name.
    //
    // The seam for the icon itself already existed — `<x-slot:iconLeft>` and `<x-slot:iconRight>`
    // have shipped for a long time, so the report that named both halves missing was half right.
    // What was missing is this: a button carrying only an icon still took the HORIZONTAL padding
    // of a text button, so it rendered as a wide box with a small glyph floating in the middle,
    // and there was nothing holding the label in the accessibility tree once it was hidden.
    //
    // Reported from the starter kit's user list, where a row action had to narrow on a phone:
    // "Edit roles" in German held 147px of the 309px the table had left. Their workaround put
    // the icon and a `sr-only` label in the slot by hand and re-did the padding from outside —
    // which is what this replaces, and the shape `clipboard-button` has had all along.
    //
    // ⚠️ THE LABEL STAYS IN THE DEFAULT SLOT. It is rendered visually-hidden rather than
    // dropped, so the button keeps a real accessible name that is translated by the same
    // `__()` the visible one went through and is reachable by voice control — which an
    // `aria-label` on a control with no visible text is not, reliably. A caller who prefers
    // `aria-label` can still use it; this only means they do not HAVE to.
    'iconOnly' => false,
    'href' => null,
    'disabled' => false,
    'loading' => false,
    'forceLoading' => false,
    // Scope the loading spinner + disable to THIS button's own Livewire action,
    // e.g. loading-target="redeliver". The spinner is a child <svg wire:loading>
    // with no target of its own, so Livewire falls back to hasActionForComponent()
    // and flashes it on EVERY commit — including wire:poll refreshes and unrelated
    // sibling actions. Setting loadingTarget emits wire:target so the spinner only
    // reacts to that action. NOTE: this is `loadingTarget`, NOT `target` — a
    // `target` prop would collide with the HTML target attribute (target="_blank"
    // + the rel tabnabbing auto-inject). Null = today's untargeted behavior
    // (byte-identical); scoping is strictly opt-in.
    'loadingTarget' => null,
    // Whether the busy state DISABLES the control, or merely announces itself.
    //
    // `disabled` is what this component has always used, and it costs the focus: the
    // browser blurs a control the moment it becomes disabled, so the element the reader
    // just activated stops being focused for the whole in-flight window and focus falls to
    // `<body>`. That is WCAG 2.4.3, on every request, and it is why an adopting project
    // set `aria-busy` through the attribute bag by hand rather than use `loading` at all.
    //
    // `false` swaps the attribute for `aria-busy`, which announces the same wait while the
    // element stays focusable and in the tab order. Livewire's `.attr` sets the attribute
    // to `true` — measured in its own `toggleBooleanStateDirective`, not assumed — so this
    // yields a valid `aria-busy="true"` rather than the attribute's own name.
    //
    // The default stays `true`, and the trade is the developer's to make: a control that
    // stays enabled while its action is in flight can be pressed twice.
    'disableOnLoading' => true,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $disabled = BooleanProp::from($disabled, false);
    $loading = BooleanProp::from($loading, false);
    $disableOnLoading = BooleanProp::from($disableOnLoading, true);

    // Blade compiles an UNBOUND attribute to a string, and the string 'false' is truthy — so
    // `wrap-label="false"` would have meant the opposite of what the call site reads as, with
    // the page rendering either way and nothing to say so. Normalized against this prop's own
    // default, like every other boolean on this component.
    $wrapLabel = BooleanProp::from($wrapLabel, false);
    $touchTextFloor = BooleanProp::from($touchTextFloor, false);
    $iconOnly = BooleanProp::from($iconOnly, false);

    // ⚠️ `loadingTarget` IMPLIES `loading`, because there is no other reason to set it.
    //
    // Both branches below hang on `$loading`; `loadingTarget` only SCOPES a spinner that
    // `loading` switches on. So `loading-target="submit"` without `loading` rendered a
    // button byte-identical to one without the attribute — no spinner, no disable, nothing
    // — while reading at the call site exactly like a working busy state.
    //
    // Nothing goes red over it: no error, no warning, no log. Reported from an adopting
    // project where it sat on a re-consent screen, the one page a person cannot leave
    // without agreeing, and a slow connection got no feedback at all — neither visual nor
    // assistive — until the answer came back.
    $loading = $loading || $loadingTarget !== null;
    $forceLoading = BooleanProp::from($forceLoading, false);

    // warn when developers pass an
    // unknown prop (e.g. `variant="ghost"` when the prop is `surface`).
    // Dev-only — silent in prod. See WireKit::warnUnknownProps() docs.
    WireKit::warnUnknownProps('button', $attributes->getAttributes());

    // Base classes: layout, typography, transitions, focus ring, disabled state
    // All values reference design tokens — no hardcoded colors, sizes, or durations
    $baseClasses = WireKit::resolveClasses('button', 'base', implode(' ', [
        // `whitespace-nowrap` keeps the button's text on a single line
        // alongside the loading-spinner / icon slots. Without it, a
        // narrow button width (or a long label like "Saving…") flexes
        // the text into a second line BELOW the spinner — visually the
        // spinner stacks above the text. `inline-flex` alone does not
        // prevent the inner TEXT NODE from soft-wrapping at its own
        // whitespace; `whitespace-nowrap` clamps the text to one line.
        // ⚠️ `wrapLabel` FLIPS ONLY THE CLAMP, and the default stays what it was. The reasoning
        // above is right for a label the APPLICATION wrote — "Save", "Send test" — and one line
        // is the correct answer there. It is the wrong answer for a label a USER typed: a
        // template name, a channel label, an org login are as long as somebody made them, and a
        // row of those ran off the edge of a card in an adopting application rather than taking
        // a second line.
        //
        // `flex-nowrap` comes WITH the wrap, and it is what keeps the original reasoning true:
        // the concern in that comment is the spinner ending up ABOVE the text, which is the flex
        // LINE breaking rather than the text soft-wrapping. Holding the line together lets the
        // label take a second row while the spinner stays beside it.
        //
        // `whitespace-normal` is emitted explicitly rather than by omission: a developer's own
        // `class="whitespace-normal"` does not reliably win, because two Tailwind utilities for
        // one property are decided by their order in the generated stylesheet rather than in the
        // attribute — which is exactly why an adopting application had to wrap its labels in a
        // `<span>` instead.
        //
        // `[overflow-wrap:anywhere]` comes with the wrap as well. `whitespace-normal` only allows a
        // break at a space or a hyphen, and a typed name often has neither: a template name with
        // underscores, a channel, a repository. The label then stayed as wide as the whole word,
        // because that word was its min-content width and a flex row cannot shrink the button below
        // it. `break-word` would allow the break without lowering min-content; `anywhere` does both,
        // and a space still wins wherever there is one. Spelled as an arbitrary property, the same
        // spelling text and heading use for their break values.
        $wrapLabel
            ? 'inline-flex items-center justify-center gap-x-2 whitespace-normal flex-nowrap [overflow-wrap:anywhere]'
            : 'inline-flex items-center justify-center gap-x-2 whitespace-nowrap',
        // Marker for the coarse-pointer touch-target floor in dist/wirekit.css —
        // the same hook `wk-field` gives the form controls. It carries no styling
        // of its own; it exists so a stylesheet rule can reach this element with
        // enough specificity to beat the size utility.
        //
        // The touch TEXT floor's marker rides in the same entry when the prop asks for it: a
        // button on its own is right at its designed size, and one that shares a row with a field
        // takes the size the field lifts to instead of reading smaller beside it.
        //
        // ⚠️ ONE STRING WITH A TERNARY, never a spread of a nested array. A bracket inside this
        // list ends the block early for every reader that matches it with a lazy `\[(.*?)\]` —
        // measured: a guard over these class lists then read a base block that stopped before the
        // focus ring below, and reported this button as carrying no ring at all.
        $touchTextFloor ? 'wk-button wk-touch-text-floor' : 'wk-button',
        'cursor-pointer',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-body-weight)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'leading-[var(--font-wk-line-height)]',
        'border-[length:var(--border-wk-width)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-hidden',
        'focus-visible:ring-[length:var(--ring-wk-width)]',
        'focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'focus-visible:ring-[var(--color-wk-ring)]',
        'focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'disabled:opacity-[var(--opacity-wk-disabled)]',
        'disabled:pointer-events-none',
    ]), $scope);

    // accept `outlined` as alias for
    // the canonical `outline` so developers who copy from card (which
    // uses `variant="outlined"`) get the same visual on button.
    $surfaceAliases = ['outlined' => 'outline'];
    $surface = $surfaceAliases[$surface] ?? $surface;

    // Validate intent + surface (debug mode raises on unknown values).
    if (! in_array($intent, \Pushery\WireKit\VariantResolver::INTENTS, true)) {
        WireKit::validateProp('button', 'intent', $intent, \Pushery\WireKit\VariantResolver::INTENTS);
    }
    if (! in_array($surface, \Pushery\WireKit\VariantResolver::SURFACES, true)) {
        WireKit::validateProp('button', 'surface', $surface, \Pushery\WireKit\VariantResolver::SURFACES);
    }

    $variantClasses = \Pushery\WireKit\VariantResolver::resolve($intent, $surface);

    // Size classes: padding, font size, radius — all from sizing tokens. The height
    // is picked separately below, because it is not one value.
    $sizeClasses = match ($size) {
        'xs' => implode(' ', [
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'sm' => implode(' ', [
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
        'md-compact' => implode(' ', [
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'md' => implode(' ', [
            'px-[var(--padding-wk-x-md)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'lg' => implode(' ', [
            'px-[var(--padding-wk-x-lg)]',
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'xl' => implode(' ', [
            'px-[calc(var(--padding-wk-x-lg)*1.25)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-lg)]',
        ]),
        default => WireKit::validateProp('button', 'size', $size, ['xs', 'sm', 'md-compact', 'md', 'lg', 'xl']),
    };

    /*
     * An icon-only button with nothing to be named by is a button a screen reader announces as
     * "button" and nothing else — and it is invisible to the person who built it, because the
     * glyph tells THEM everything.
     *
     * ⚠️ NO FALLBACK NAME IS INVENTED, and that is the difference from `clipboard-button`, which
     * can fall back to "Copy to clipboard" because it does exactly one thing. This button does
     * whatever its call site does; a generic name would be a name that passes an automated check
     * and tells a reader nothing, which is worse than the failure it hides.
     *
     * Loud where it can be fixed — throwing in console, a log line in a request — the split every
     * validator in this catalog makes.
     */
    if ($iconOnly && trim(strip_tags((string) $slot)) === '' && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby')) {
        $message = 'icon-only needs an accessible name: put the label in the default slot (it is '
            .'rendered visually hidden), or pass aria-label / aria-labelledby.';

        if (\Pushery\WireKit\Support\StrictnessGate::shouldThrowOnInvalid()) {
            throw new \InvalidArgumentException('wirekit::button: '.$message);
        }

        if (function_exists('logger')) {
            logger()->warning('[WireKit] button: '.$message);
        }
    }

    /*
     * Icon-only REPLACES that table rather than editing its string, and it runs after it rather
     * than instead of it — so the size is still validated on this path, by the one call that
     * publishes the allow-list.
     *
     * What changes is the horizontal padding, which is the whole defect: a text button's `px`
     * around a single glyph is a wide box with something small in the middle. The font size and
     * the radius stay, because the glyph is sized in `em` by every icon this catalog ships and
     * the corner is a property of the size rather than of the content.
     *
     * ⚠️ SPELLED OUT, LIKE THE HEIGHT TABLE BELOW AND FOR THE SAME REASON. A class assembled at
     * runtime is a class Tailwind never generates: it scans source text, so the utility would be
     * absent from the stylesheet and the button would silently keep the padding this is here to
     * remove.
     */
    if ($iconOnly) {
        $sizeClasses = match ($size) {
            'xs' => 'text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-sm)]',
            'sm' => 'text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-sm)]',
            'md-compact' => 'text-[length:var(--text-wk-sm)] rounded-[var(--radius-wk-md)]',
            'lg' => 'text-[length:var(--text-wk-md)] rounded-[var(--radius-wk-md)]',
            'xl' => 'text-[length:var(--text-wk-lg)] rounded-[var(--radius-wk-lg)]',
            default => 'text-[length:var(--text-wk-md)] rounded-[var(--radius-wk-md)]',
        };
    }

    // The height, and why it is TWO tables rather than one class.
    //
    // A clamped label is exactly one line tall, so a FIXED height is the right
    // answer and it keeps a row of buttons aligned to the same baseline. A label
    // that may wrap is not: measured in the browser inside a 9rem container,
    // `wrap-label` with a user's label took FOUR line boxes while the button
    // stayed 40px tall — the first line sat 20px ABOVE the button's top edge and
    // the last 20px below its bottom. Letting the label wrap and then clamping
    // the box it wraps inside is half the prop, so the wrapping variant turns the
    // same token into a FLOOR and adds the vertical padding a second row needs.
    //
    // ⚠️ NEITHER SPELLING MAY BE ASSEMBLED AT RUNTIME. Tailwind scans source text
    // for class names, so `str_replace('h-[', 'min-h-[', $sizeClasses)` yields a
    // class nothing ever generates — the utility is absent from the stylesheet and
    // the button silently keeps its fixed height. Both tables are written out.
    //
    // `$size` is already validated by the match above, so `default` here only has
    // to be a sane value rather than a second error path.
    /*
     * A THIRD table, and it is a square: the width is the same token as the height, so the
     * button is as wide as it is tall at every size instead of as wide as its padding.
     *
     * ⚠️ It ignores `wrapLabel`, and that is not an oversight. There is no visible label to
     * wrap — the label is in the accessibility tree and nowhere else — so the wrapping variant's
     * `min-h` plus vertical padding would describe a second line that cannot exist, and it would
     * make the box taller than it is wide.
     *
     * Every size clears the 24x24 CSS-pixel floor of WCAG 2.5.8 without a carve-out: the
     * smallest is `xs` at 0.875 x 2rem = 28px, and it is the one worth checking because it is
     * the size a dense table row reaches for.
     */
    $iconOnlyHeightClasses = match ($size) {
        'xs' => 'h-[calc(var(--size-wk-sm)*0.875)] w-[calc(var(--size-wk-sm)*0.875)]',
        'sm' => 'h-[var(--size-wk-sm)] w-[var(--size-wk-sm)]',
        'md-compact' => 'h-[var(--size-wk-md-compact)] w-[var(--size-wk-md-compact)]',
        'lg' => 'h-[var(--size-wk-lg)] w-[var(--size-wk-lg)]',
        'xl' => 'h-[calc(var(--size-wk-lg)*1.1)] w-[calc(var(--size-wk-lg)*1.1)]',
        default => 'h-[var(--size-wk-md)] w-[var(--size-wk-md)]',
    };

    $heightClasses = $iconOnly
        ? $iconOnlyHeightClasses
        : ($wrapLabel
        ? match ($size) {
            'xs' => 'min-h-[calc(var(--size-wk-sm)*0.875)] py-[var(--padding-wk-y-xs)]',
            'sm' => 'min-h-[var(--size-wk-sm)] py-[var(--padding-wk-y-xs)]',
            'md-compact' => 'min-h-[var(--size-wk-md-compact)] py-[var(--padding-wk-y-sm)]',
            'lg' => 'min-h-[var(--size-wk-lg)] py-[var(--padding-wk-y-md)]',
            'xl' => 'min-h-[calc(var(--size-wk-lg)*1.1)] py-[var(--padding-wk-y-md)]',
            default => 'min-h-[var(--size-wk-md)] py-[var(--padding-wk-y-sm)]',
        }
        : match ($size) {
            'xs' => 'h-[calc(var(--size-wk-sm)*0.875)]',
            'sm' => 'h-[var(--size-wk-sm)]',
            'md-compact' => 'h-[var(--size-wk-md-compact)]',
            'lg' => 'h-[var(--size-wk-lg)]',
            'xl' => 'h-[calc(var(--size-wk-lg)*1.1)]',
            default => 'h-[var(--size-wk-md)]',
        });

    // Render as <a> when href is provided, otherwise <button>
    $tag = $href ? 'a' : 'button';

    // Auto-inject rel="noopener noreferrer" + SR hint when target="_blank".
    // See dropdown/item.blade.php for rationale on except('rel') + explicit
    // rel render (avoids $attributes->merge treating rel as a default).
    $targetAttr = $attributes->get('target', '');
    $opensNewTab = $href && str_contains($targetAttr, '_blank');
    $relAttr = $attributes->get('rel', '');
    $finalRel = $opensNewTab && ! str_contains($relAttr, 'noopener')
        ? trim($relAttr.' noopener noreferrer')
        : $relAttr;
    $computedRel = $opensNewTab ? $finalRel : ($relAttr ?: null);

    // When `loading=true` is set without ANY `wire:*` action attribute
    // (wire:click / wire:submit / wire:click.prevent / wire:keydown / etc.),
    // the developer wants the DECLARATIVE loading state — the button stays
    // in its loading look until they manually flip it. The pre-fix behavior
    // attached `wire:loading` to the spinner and `wire:loading.attr` to the
    // button, both of which are no-ops outside a Livewire request — so the
    // spinner never showed AND the button never disabled. We now distinguish:
    //   - $loading + wire:*  → wire:loading.attr (current — implicit while
    //                          a Livewire request is in flight)
    //   - $loading + no wire:*  → unconditional spinner + native disabled
    //                            (declarative — developer toggles `loading`)
    $hasWireAction = false;
    foreach ($attributes->getAttributes() as $key => $_) {
        if (is_string($key) && str_starts_with($key, 'wire:')) {
            $hasWireAction = true;
            break;
        }
    }
    $declarativeLoading = $loading && ! $hasWireAction;

    // `forceLoading=true` renders the
    // spinner unconditionally — useful for static button demos and for
    // non-Livewire contexts where the implicit wire:loading gate would
    // hide the spinner. Bypasses the wire:loading + declarative paths
    // by promoting the button straight to a hard-disabled spinner state.
    if ($forceLoading) {
        $declarativeLoading = true;
    }

    // ⚠️ `disableOnLoading` used to be read in ONE place — the `wire:loading.attr`
    // branch at the bottom of this file — so on the DECLARATIVE path (`loading` set
    // with no `wire:*` attribute on the tag) the prop was inert. A call site that
    // opted out still got the native `disabled`, still lost focus to `<body>` for the
    // whole in-flight window, and got no `aria-busy` announcement in its place: the
    // exact WCAG 2.4.3 failure the prop exists to avoid, plus a silent one. Nothing
    // went red over it either, because both tests covering the prop pass a
    // `wire:click` and therefore never enter this path.
    //
    // Two boundaries below are deliberate rather than oversights:
    //
    //   `forceLoading` keeps disabling. Its own contract is a PREVIEW of the disabled
    //   busy state for static demos and non-Livewire contexts — "disables the button
    //   regardless" — so honoring the opt-out there would break a different promise.
    //
    //   The swap is scoped to `<button>`, exactly like `wire:loading.attr` below.
    //   `disabled` is inert on an anchor, so the link branch withholds the `href`
    //   instead; that is a different mechanism with a different trade, and the
    //   wire:loading path has never applied this prop to it either.
    $declarativeBusyOnly = $tag === 'button'
        && $declarativeLoading
        && ! $forceLoading
        && ! $disableOnLoading;

    // The docblock on the prop records an adopting project setting `aria-busy` through
    // the attribute bag by hand rather than using `loading` at all. Emitting ours beside
    // theirs would put the attribute on the element twice.
    $emitAriaBusy = $declarativeBusyOnly && ! $attributes->has('aria-busy');

    $isDisabled = $disabled || ($declarativeLoading && ! $declarativeBusyOnly);

    // `disabled` is not a valid attribute on <a>, and `:disabled` never matches
    // one — so on the link branch the `disabled:` variants in $baseClasses select
    // nothing and the native attribute is inert. A `href` button rendered with
    // `disabled` therefore stayed fully styled, Tab-reachable and navigable, and
    // announced to a screen reader as an ordinary link.
    //
    // A link cannot be disabled natively, so the href is WITHHELD instead: without
    // it the element is neither focusable nor navigable, and `role="link"` +
    // `aria-disabled="true"` keep it announced as the disabled link it is rather
    // than as anonymous text. The muted look is applied as plain utilities here
    // because the `disabled:` variants cannot reach it — the same shape
    // dropdown.item uses for the <a> branch of its own tag switch.
    $linkDisabled = $tag === 'a' && $isDisabled;
    $linkDisabledClasses = $linkDisabled
        ? 'opacity-[var(--opacity-wk-disabled)] pointer-events-none'
        : '';
@endphp

<{{ $tag }} data-wk-prose-skip
    @if($href && ! $linkDisabled) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $type }}" @endif
    @if($linkDisabled) role="link" aria-disabled="true" @endif
    @disabled($tag === 'button' && $isDisabled)
    @if($emitAriaBusy) aria-busy="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$baseClasses, $variantClasses, $sizeClasses, $heightClasses, $linkDisabledClasses]) }}
    {{-- ⚠️ `disabled` IS INERT ON AN ANCHOR, so the link branch gets the treatment
         this component already uses for a disabled link instead. `wire:loading.attr`
         adds a `disabled` attribute, which a browser honors on a button and ignores
         on an <a> — the link stayed fully navigable for the whole request, which is
         the one moment a second click does the most damage. Same classes as
         $linkDisabledClasses above, so the two disabled states look identical and
         `pointer-events-none` is what actually stops the click. --}}
    @if($loading && ! $declarativeLoading)
        @if($tag === 'button') wire:loading.attr="{{ $disableOnLoading ? 'disabled' : 'aria-busy' }}" @else wire:loading.class="opacity-[var(--opacity-wk-disabled)] pointer-events-none" @endif
        @if($loadingTarget) wire:target="{{ $loadingTarget }}" @endif
    @endif
>
    {{-- Loading spinner: declarative path renders always; wire:loading
         path renders only while a Livewire request is in flight. --}}
    @if($declarativeLoading)
        <svg class="animate-spin -ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    @elseif($loading)
        <svg wire:loading @if($loadingTarget) wire:target="{{ $loadingTarget }}" @endif class="animate-spin -ms-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    @endif

    {{-- Icon slots: use <x-slot:iconLeft> / <x-slot:iconRight> for HTML icons (SVG etc.) --}}
    @isset($iconLeft)
        <span class="shrink-0">{{ $iconLeft }}</span>
    @endisset

    {{-- ⚠️ HIDDEN, NEVER DROPPED. An icon-only button whose label was simply not rendered is a
         control a screen reader announces as "button" and nothing else — and it looks finished,
         because the glyph says everything a sighted reader needs. Kept visually hidden, the same
         translated string is still the accessible name (WCAG 4.1.2) and still the phrase voice
         control matches on. --}}
    @if($iconOnly)
        <span class="sr-only">{{ $slot }}</span>
    @else
        {{ $slot }}
    @endif

    @isset($iconRight)
        <span class="shrink-0">{{ $iconRight }}</span>
    @endisset

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
