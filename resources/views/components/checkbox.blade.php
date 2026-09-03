{{-- optimistic-ui: supported
     A wire:model.live round trip on a checkbox is the mutation case, and the
     browser has already flipped the box by the time any handler runs. What the
     layer adds is confirmation, the undo, and the announcement. --}}
@props([
    // The Livewire method this component should call, when it should show the
    // new value before the server has agreed to it. Null leaves the component
    // exactly as it has always rendered.
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
    // checkbox in a table column whose header already names it, so the visible
    // label is redundant. Mirrors input / select / textarea / combobox `hideLabel`.
    'hideLabel' => false,
    'hint' => null,
    'error' => null,
    'indeterminate' => false,
    'size' => config('wirekit.components.checkbox.size', 'md'),
    // 'default' (inline control + label) or 'card' (the whole bordered card is the
    // clickable target and highlights when checked — the selectable-option pattern).
    'variant' => config('wirekit.components.checkbox.variant', 'default'),
    'scope' => null,
])

@aware(['announceErrors' => null])

@php
    use Pushery\WireKit\Support\BooleanProp;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $indeterminate = BooleanProp::from($indeterminate, false);
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
    WireKit::warnUnknownProps('checkbox', $attributes->getAttributes());

    // Size scale (aligned with toggle/radio): sets the box w/h. The check +
    // indeterminate overlays are nested inside the box and fill it (w-full h-full),
    // so they need no size of their own and stay centered in every variant.
    // Lenient match-with-default mirrors the sibling toggle component.
    $sizing = match ($size) {
        'sm' => 'w-4 h-4',
        'lg' => 'w-6 h-6',
        default => 'w-5 h-5',
    };

    $variantValue = match ($variant) {
        'default', 'card' => $variant,
        default => WireKit::validateProp('checkbox', 'variant', $variant, ['default', 'card']),
    };
    // Card variant: the <label> becomes a bordered card that reacts to its inner
    // input via :has() (in the WireKit browser baseline) — accent border + tinted
    // surface when checked, focus ring when the input is focus-visible.
    // `group` so the check/indeterminate overlays (nested in the box, not siblings
    // of .peer) can toggle via group-has-[:checked] / group-has-[:indeterminate].
    //
    // align-top on the default (inline-flex) label kills a sub-pixel layout shift on
    // toggle: an inline-flex label is placed in its line box by its baseline, and the
    // box's flex baseline shifts when the checkmark SVG flips display none↔block — on a
    // 2× display that re-rounds the whole label ~0.5px, pulling the next row closer
    // (measured: CheckboxToggleShiftTest). align-top positions the label by its TOP
    // edge instead, independent of the changing baseline, so the row stays put. The
    // card variant is block-level `flex` (no line-box baseline) and is unaffected.
    $labelClasses = $variantValue === 'card'
        ? 'group flex items-start gap-3 cursor-pointer relative w-full rounded-[var(--radius-wk-lg)] px-[var(--padding-wk-x-md)] py-[var(--padding-wk-y-md)] border-[length:var(--border-wk-width)] border-[var(--color-wk-border)] transition-colors duration-[var(--transition-wk-duration)] has-[:checked]:border-[var(--color-wk-accent)] has-[:checked]:bg-[var(--color-wk-bg-subtle)] has-[:focus-visible]:ring-[length:var(--ring-wk-width)] has-[:focus-visible]:ring-[var(--color-wk-ring)]'
        : 'group inline-flex items-start gap-2 cursor-pointer relative align-top';

    // Resolve a page-unique DOM id. Array-style names ("tags[]") dedup on their
    // base, plain duplicate names get a -2/-3 suffix, and the first occurrence keeps
    // the clean id — so <label for> always targets the right checkbox even when a
    // group or two forms share a name. Shared with every other control via
    // Support\DomId; the form key `name` stays duplicated as required.
    $rawName = $attributes->get('name');
    $id = \Pushery\WireKit\Support\DomId::unique($attributes->get('id') ?? $rawName, 'checkbox-');
    $name = $rawName ?? $id;

    // Error detection: explicit prop OR Laravel validation bag
    $hasError = $error || ($errors ?? null)?->has($name);
    $errorMessage = $error ?? ($errors ?? null)?->first($name);

    // aria-describedby: merge our own hint/error target with any caller-supplied
    // value into ONE attribute. Two separate aria-describedby attributes would make
    // the browser keep the first and silently drop the caller's association — the
    // caller's intended description would be lost (WCAG). Own target first, then
    // the caller's, matching the range-slider convention.
    $ownDescribedBy = $hasError ? $id.'-error' : ($hint ? $id.'-hint' : null);
    $callerDescribedBy = $attributes->get('aria-describedby');
    $describedBy = trim(((string) ($ownDescribedBy ?? '')).' '.((string) ($callerDescribedBy ?? '')));
    $describedBy = $describedBy !== '' ? $describedBy : null;

    // Visual box styling. The <input> uses .peer + .sr-only, and this box listens
    // to peer-checked / peer-focus-visible / peer-disabled via sibling selectors.
    $boxClasses = WireKit::resolveClasses('checkbox', 'base', implode(' ', [
        'relative inline-flex items-center justify-center shrink-0',
        // A 2.75rem hit area centered on the box, which is 20px in the default size. The
        // library already ships this primitive and the theme-controller already consumes it;
        // the checkbox simply never asked for it.
        //
        // ON THE BOX, not on the <label>, and that placement is the whole decision. The
        // default label is the box PLUS its text — 155x20 in one measured case — so a hit area
        // centered there would sit over the words rather than over the control.
        //
        // DEFAULT VARIANT ONLY, which is the counter-check this needs. In `card` the <label>
        // IS the target: a bordered, full-width, already-tall clickable card. Hanging an
        // absolutely-positioned 44px area off the box inside it would push roughly 12px past
        // the card's own edge, so taps in the gap BETWEEN two cards would land on the upper
        // card's checkbox. The card variant needs no reserve and must not grow one.
        //
        // Note this does not make the checkbox newly conformant: axe reports no `target-size`
        // violation today, because 2.5.8's spacing exception counts the free room around the
        // control. That is the point — conformance rested on nothing being placed beside it,
        // and in a dense list (ten call sites in one adopting application, several of them
        // dense) that room is exactly what runs out.
        $variantValue === 'card' ? '' : 'wk-touch-target',
        $sizing,
        'rounded-[var(--radius-wk-sm)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border-strong)]',
        'peer-hover:border-[var(--color-wk-border-strong-hover)]',
        'bg-[var(--color-wk-bg-input)]',
        'peer-checked:bg-[var(--color-wk-accent)]',
        'peer-checked:border-[var(--color-wk-accent)]',
        'peer-indeterminate:bg-[var(--color-wk-accent)]',
        'peer-indeterminate:border-[var(--color-wk-accent)]',
        'peer-focus-visible:ring-[length:var(--ring-wk-width)]',
        'peer-focus-visible:ring-offset-[length:var(--ring-wk-offset)]',
        'peer-focus-visible:ring-[var(--color-wk-ring)]',
        'peer-focus-visible:ring-offset-[var(--color-wk-ring-offset)]',
        'peer-disabled:opacity-[var(--opacity-wk-disabled)]',
        'peer-disabled:cursor-not-allowed',
        'transition-colors',
        'duration-[var(--transition-wk-duration)]',
        'cursor-pointer',
        'text-[color:var(--color-wk-accent-fg)]',
    ]), $scope);

    if ($hasError) {
        $boxClasses .= ' border-[var(--color-wk-border-error)]';
    }
@endphp

@php
    $optimisticConfig = $optimistic === null ? null : \Pushery\WireKit\Support\AlpinePayload::from([
        'value' => (bool) ($attributes->get('checked') ?? false),
        'action' => $optimistic,
        'args' => array_values((array) $optimisticArgs),
        'debug' => (bool) config('app.debug'),
        'mode' => 'reject',
        'messages' => [
            'pending' => __('wirekit::Saving'),
            'reverted' => __('wirekit::Could not save. Change undone.'),
        ],
        'errorRegion' => '#'.$id.'-error',
    ]);
@endphp

<div class="space-y-1.5" @if($optimisticConfig) x-data="wirekitOptimistic({{ $optimisticConfig }})" @endif>
    <label for="{{ $id }}" class="{{ $labelClasses }}">
        {{-- Native checkbox: visually hidden but fully accessible + Livewire-compatible.
             Siblings below consume its :checked / :indeterminate / :focus-visible / :disabled state via peer-*. --}}
        <input
            type="checkbox"
            id="{{ $id }}"
            name="{{ $name }}"
            class="peer sr-only"
            {{-- ALWAYS emitted, in both states. `indeterminate` is a DOM property with no
                 HTML attribute, so the server cannot render it — and the old form
                 (`x-init="$el.indeterminate = true"`, emitted only when true) ran once and
                 could only ever turn the state ON. The third state practically always
                 arrives AFTER the first render, through a Livewire round trip that morphs
                 the element: the attribute text changed, Alpine did not re-initialize, and
                 the property kept its initial value. Measured with one of three rows
                 selected — the server asked for it and `el.indeterminate` was false, so the
                 box read as "none selected" while something was. --}}
            data-wk-indeterminate="{{ $indeterminate ? 'true' : 'false' }}"
            x-wk-indeterminate
            @if($hasError) aria-invalid="true" @endif
            @if($optimisticConfig)
                x-ref="control"
                x-bind:aria-busy="isPending"
                x-on:change="commitFromControl()"
            @endif
            @if($describedBy !== null) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['id', 'name', 'aria-describedby']) }}
        />

        {{-- Visual box — sibling of .peer (consumes peer-checked bg/border). The
             check + indeterminate overlays are nested HERE and fill the box
             (w-full h-full), flex-centered by the box, so they stay over the box
             in BOTH the default and card variants (card label padding no longer
             offsets them). They toggle via the label's group-has-[:checked] /
             group-has-[:indeterminate] (a nested element isn't a sibling of .peer,
             so peer-checked can't reach it; the box bg/border still use peer-*). --}}
        <span class="{{ $boxClasses }}" aria-hidden="true">
            {{-- Checkmark --}}
            <svg
                class="hidden group-has-[:checked]:block pointer-events-none w-full h-full p-0.5 text-[color:var(--color-wk-accent-fg)]"
                fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
            {{-- Indeterminate dash — visible only when input.indeterminate is true --}}
            <svg
                class="hidden group-has-[:indeterminate]:block pointer-events-none w-full h-full p-0.5 text-[color:var(--color-wk-accent-fg)]"
                fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14" />
            </svg>
        </span>

        @if($slot->isNotEmpty())
            {{-- Slot-based label: supports rich HTML (links, formatting) for use cases like GDPR consent --}}
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none leading-tight pt-0.5{{ $hideLabel ? ' sr-only' : '' }}">{{ $slot }}</span>
        @elseif($label)
            <span class="text-[length:var(--text-wk-md)] text-[color:var(--color-wk-text)] select-none leading-tight pt-0.5{{ $hideLabel ? ' sr-only' : '' }}">{{ $label }}</span>
        @endif
    </label>

    @if($optimisticConfig)
        {{-- Rendered unconditionally and starting empty: a live region that
             arrives together with its text is a new node, and nothing is
             announced at all. --}}
        <div class="sr-only" data-wk-optimistic-announcer aria-live="assertive" aria-atomic="true" x-text="announcement"></div>
    @endif

    {{-- Error message or hint text --}}
    @if($hasError && $errorMessage)
        <p id="{{ $id }}-error" @if($announceError) aria-live="polite" aria-atomic="true" @endif class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-danger-text)]">{{ $errorMessage }}</p>
    @elseif($hint)
        <p id="{{ $id }}-hint" class="text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text-muted)]">{{ $hint }}</p>
    @endif
</div>
