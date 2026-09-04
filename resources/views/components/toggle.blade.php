{{-- optimistic-ui: supported --}}
@props([
    // The Livewire method this toggle should call, when it should show the new
    // state before the server has agreed to it. Null (the default) is today's
    // behavior exactly: the developer's own wire:model / wire:click, untouched.
    //
    // It is a METHOD NAME rather than a boolean because the component cannot
    // know the action otherwise — WireKit passes server actions through the
    // attribute bag, so a component never sees the developer's wire:click.
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
    // Render the label sr-only (kept as the control's accessible name) — for a
    // toggle in a table column or a toolbar whose surrounding chrome already
    // names it. The <label> WRAPS the control, so the name is associated with the
    // element rather than with the visible text and survives being taken off the
    // screen. Mirrors input / select / textarea / combobox / checkbox `hideLabel`.
    'hideLabel' => false,
    'hint' => null,
    'error' => null,
    'size' => config('wirekit.components.toggle.size', 'md'),
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

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

    // Same trap one level in: an UNBOUND `hideLabel="false"` reaches here as the
    // truthy string 'false' and would hide the label the call site asked to show.
    $hideLabel = BooleanProp::from($hideLabel, false);


    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props.
    WireKit::warnUnknownProps('toggle', $attributes->getAttributes());

    // Auto-generate ID from name or fall back to a random identifier
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $attributes->get('name'), 'toggle-'); // page-unique DOM id; see Support\DomId
    $name = $attributes->get('name', $id);

    // Accessible name fallback: if the caller provided neither a visible `label`
    // prop nor an `aria-label` attribute, generate a humanized label from the
    // `name` attribute so axe-core and screen readers still announce the switch
    // correctly. Visible labels still win when present (they'll use `<label
    // for="...">` instead of aria-label).
    $hasAccessibleName = $label !== null || $attributes->has('aria-label') || $attributes->has('aria-labelledby');
    $fallbackAriaLabel = $hasAccessibleName ? null : ucfirst(str_replace(['-', '_'], ' ', (string) $name));

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // Size scale: track width/height + knob offset distance
    // Knob diameter = track height minus 4px of padding
    $sizing = match ($size) {
        'sm' => ['track' => 'w-8 h-4', 'knob' => 'w-3 h-3', 'translate' => 'peer-checked:translate-x-4'],
        'lg' => ['track' => 'w-12 h-6', 'knob' => 'w-5 h-5', 'translate' => 'peer-checked:translate-x-6'],
        default => ['track' => 'w-10 h-5', 'knob' => 'w-4 h-4', 'translate' => 'peer-checked:translate-x-5'],
    };

    // Wrapper styles: fixed-size positioning context for the (absolutely placed) track + knob
    $wrapperClasses = implode(' ', [
        'relative inline-flex shrink-0 items-center',
        'cursor-pointer',
        // The hit-area reserve. The native input is `peer sr-only` at 1x1, so the thing a finger
        // lands on is the track — 40x20 at `md` and 32x16 at `sm`, both under the 24px AA floor
        // on the vertical axis. The expander is out of flow, so the switch keeps the size it was
        // designed at and nothing around it moves. The wrapper is already positioned, so the
        // class only adds the area.
        'wk-touch-target',
        $sizing['track'],
    ]);

    // Track: OFF state uses border color (neutral-300) for visible contrast against white backgrounds.
    // Old bg-muted (neutral-100) was ~1.04:1 contrast — nearly invisible. WCAG 1.4.11 requires ≥3:1.
    // MUST be a direct sibling of .peer for peer-checked:* to resolve.
    $trackClasses = WireKit::resolveClasses('toggle', 'track', implode(' ', [
        'absolute inset-0',
        'rounded-full',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-strong)]',
        'bg-[var(--color-wk-border)]',
        'peer-checked:bg-[var(--color-wk-accent)]',
        'peer-checked:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'pointer-events-none',
    ]), $scope);

    // Knob styles: a circle that slides from left to right when checked.
    // MUST be a direct sibling of .peer for peer-checked:* to resolve.
    $knobClasses = implode(' ', [
        'absolute left-0.5 top-1/2 -translate-y-1/2',
        'rounded-full',
        'bg-[var(--color-wk-bg-elevated)]',
        'shadow-[var(--shadow-wk-sm)]',
        'transition-transform',
        'duration-[var(--transition-wk-duration)]',
        'ease-[var(--transition-wk-easing)]',
        'pointer-events-none',
        $sizing['knob'],
        $sizing['translate'],
    ]);
@endphp

@php
    // The optimistic wiring, built once so the markup below stays readable.
    //
    // Every string is a translation key, per the contract: an announcement is
    // read aloud to somebody, and a literal here would be read aloud in English
    // to everybody.
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (bool) ($attributes->get('checked') ?? false),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        // Developer warning only where warnings belong; the same gate every other
        // dev-warning call site in the catalog uses.
        'debug' => (bool) config('app.debug'),
        // A toggle rejects a second flip while one is in flight rather than
        // queueing it. With a queue the final state depends on the ORDER the
        // responses come back in — network timing — which is both wrong and
        // untestable.
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        // The field's own error region. Where it carries a message, this layer
        // stays silent on failure: "Email is required" is actionable, "could
        // not save" is not, and WCAG 3.3.1 wants the specific one heard.
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

{{-- `wk-toggle` is the marker that puts this subtree inside the library's reduced-motion
     rule, and it is load-bearing rather than decorative. That rule is deliberately scoped
     to WireKit's own surface — it matches a `wk-` CLASS TOKEN and its descendants, because
     matching the class attribute as a substring once clamped a whole application's
     animations to 1ms through `bg-[var(--color-wk-bg)]` on its <body>. The knob below
     slides on `transition-transform` with the themed duration, and nothing here carried
     such a token, so on a page with no WireKit-classed ancestor the switch animated at
     full duration for a reader who had asked their operating system for no motion. The
     marker is also what lets an application's own motion setting win, since the
     `data-reduce-motion` escape hatch is written against the same selector. --}}
<div class="wk-toggle space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    <label for="{{ $id }}" class="inline-flex items-center gap-3 cursor-pointer">
        {{-- Switch visual: wrapper contains input (.peer), track, and knob as siblings --}}
        {{-- so peer-checked:* selectors resolve correctly (peer-checked targets siblings only). --}}
        <span class="{{ $wrapperClasses }}">
            {{-- Native checkbox: visually hidden but accessible (screen readers + Livewire wire:model) --}}
            {{-- role="switch" tells AT this is a toggle, not a regular checkbox --}}
            <input
                type="checkbox"
                id="{{ $id }}"
                name="{{ $name }}"
                role="switch"
                class="peer sr-only"
                @if($fallbackAriaLabel) aria-label="{{ $fallbackAriaLabel }}" @endif
                @if($optimisticConfig)
                    x-ref="control"
                    x-bind:checked="value"
                    x-bind:aria-busy="isPending"
                    x-on:change="toggle()"
                @endif
                @if($hasError) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
                @if($hint && !$hasError) aria-describedby="{{ $id }}-hint" @endif
                {{ $attributes->except(['id', 'name']) }}
            />

            {{-- Track: sibling of .peer, background color flips via peer-checked --}}
            <span class="{{ $trackClasses }}" aria-hidden="true"></span>

            {{-- Knob: sibling of .peer, slides via peer-checked:translate-x-* --}}
            <span class="{{ $knobClasses }}" aria-hidden="true"></span>
        </span>

        @if($label)
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none{{ $hideLabel ? ' sr-only' : '' }}">{{ $label }}</span>
        @endif
    </label>

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting EMPTY. A live region that
             arrives together with its text is a new node rather than a changed
             region, and nothing is announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
