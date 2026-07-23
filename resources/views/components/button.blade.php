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
        'cursor-pointer',
        'font-[family-name:var(--font-wk-sans)]',
        'font-[number:var(--font-wk-body-weight)]',
        'tracking-[var(--font-wk-letter-spacing)]',
        'leading-[var(--font-wk-line-height)]',
        'border-[length:var(--border-wk-width)]',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'focus:outline-none',
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
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    @if($tag === 'button') type="{{ $type }}" @endif
    @disabled($disabled || $declarativeLoading)
    @if($computedRel) rel="{{ $computedRel }}" @endif
    {{ $attributes->except('rel')->class([$baseClasses, $variantClasses, $sizeClasses]) }}
    @if($loading && ! $declarativeLoading) wire:loading.attr="disabled" @if($loadingTarget) wire:target="{{ $loadingTarget }}" @endif @endif
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
        <span class="sr-only">{{ __('(opens in new tab)') }}</span>
    @endif
</{{ $tag }}>
