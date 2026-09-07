{{-- optimistic-ui: n/a — passthrough
     The canonical passthrough: the developer's wire:click arrives through the attribute bag and this component never sees it. And a button holds no state of its own — the optimism belongs to whatever the click changes, which is why the prop lives there and not here. --}}
@props([
    'intent' => config('wirekit.components.button.intent', 'primary'),
    'surface' => config('wirekit.components.button.surface', 'filled'),
    'size' => config('wirekit.components.button.size', 'md'),
    'type' => 'button',
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
        'inline-flex items-center justify-center gap-x-2 whitespace-nowrap',
        // Marker for the coarse-pointer touch-target floor in dist/wirekit.css —
        // the same hook `wk-field` gives the form controls. It carries no styling
        // of its own; it exists so a stylesheet rule can reach this element with
        // enough specificity to beat the size utility.
        'wk-button',
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

    // Size classes: height, padding, font size, radius — all from sizing tokens
    $sizeClasses = match ($size) {
        'xs' => implode(' ', [
            'h-[calc(var(--size-wk-sm)*0.875)]',
            'px-[var(--padding-wk-x-sm)]',
            'text-[length:var(--text-wk-sm)]',
            'rounded-[var(--radius-wk-sm)]',
        ]),
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
            'text-[length:var(--text-wk-md)]',
            'rounded-[var(--radius-wk-md)]',
        ]),
        'xl' => implode(' ', [
            'h-[calc(var(--size-wk-lg)*1.1)]',
            'px-[calc(var(--padding-wk-x-lg)*1.25)]',
            'text-[length:var(--text-wk-lg)]',
            'rounded-[var(--radius-wk-lg)]',
        ]),
        default => WireKit::validateProp('button', 'size', $size, ['xs', 'sm', 'md-compact', 'md', 'lg', 'xl']),
    };

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

<{{ $tag }}
    @if($href && ! $linkDisabled) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $type }}" @endif
    @if($linkDisabled) role="link" aria-disabled="true" @endif
    @disabled($tag === 'button' && $isDisabled)
    @if($emitAriaBusy) aria-busy="true" @endif
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$baseClasses, $variantClasses, $sizeClasses, $linkDisabledClasses]) }}
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
        <svg class="animate-spin -ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    @elseif($loading)
        <svg wire:loading @if($loadingTarget) wire:target="{{ $loadingTarget }}" @endif class="animate-spin -ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
    @endif

    {{-- Icon slots: use <x-slot:iconLeft> / <x-slot:iconRight> for HTML icons (SVG etc.) --}}
    @isset($iconLeft)
        <span class="shrink-0">{{ $iconLeft }}</span>
    @endisset

    {{ $slot }}

    @isset($iconRight)
        <span class="shrink-0">{{ $iconRight }}</span>
    @endisset

    @if($opensNewTab)
        <span class="sr-only">{{ __('wirekit::(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
