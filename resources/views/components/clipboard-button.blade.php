{{-- optimistic-ui: n/a — client-only
     Its state is the copied-just-now flag. That is not a value a server owns, so there is
     nothing to anticipate and nothing to roll back. --}}
@props([
    'value' => '',
    // `wirekit::Copied`, the key `color-picker` already uses. This asked for
    // `wirekit::Copied!` — a second catalog key for the same visible state, and the
    // only value of 370 carrying an exclamation mark. Two keys for one string means an
    // application translating the kit has to find both, and the two components answer
    // the same event in two registers.
    'copiedText' => __('wirekit::Copied'),
    'duration' => 2000,
    // Bare icon button — no border / bg / label, just the copy glyph (muted,
    // pops green on copy). For compact action rows (a message's copy control).
    // Requires an aria-label (there is no visible text to name it).
    'iconOnly' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('clipboard-button', $attributes->getAttributes());

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $iconOnly = BooleanProp::from($iconOnly, false);

    // Clipboard Button — copies value to clipboard on click.
    // Temporarily swaps label to "Copied!" via Alpine, announces via aria-live.
    $classes = $iconOnly
        ? WireKit::resolveClasses('clipboard-button', 'icon', implode(' ', [
            // A bare ghost icon button: no chrome. The muted text color makes
            // the rest icon gray; the check icon carries its own success green.
            'inline-flex items-center justify-center',
            'p-[var(--padding-wk-y-sm)]',
            'rounded-[var(--radius-wk-sm)]',
            'text-[color:var(--color-wk-text-muted)]',
            'transition-colors',
            'duration-[var(--transition-wk-duration)]',
            'ease-[var(--transition-wk-easing)]',
            'hover:bg-[var(--color-wk-bg-subtle)] hover:text-[var(--color-wk-text)]',
            'focus:outline-hidden',
            'focus-visible:ring-[length:var(--ring-wk-width)]',
            'focus-visible:ring-[var(--color-wk-ring)]',
            'cursor-pointer',
        ]), $scope)
        : WireKit::resolveClasses('clipboard-button', 'base', implode(' ', [
            'inline-flex items-center gap-[var(--gap-wk-sm)]',
            'px-[var(--padding-wk-x-md)]',
            'py-[var(--padding-wk-y-sm)]',
            'text-[length:var(--text-wk-md)]',
            'font-[family-name:var(--font-wk-sans)]',
            'font-[number:var(--font-wk-body-weight)]',
            'text-[color:var(--color-wk-text)]',
            'bg-[var(--color-wk-bg-elevated)]',
            'border-[length:var(--border-wk-width)]',
            'border-[var(--color-wk-border)]',
            'rounded-[var(--radius-wk-md)]',
            'shadow-[var(--shadow-wk-sm)]',
            'transition-colors',
            'duration-[var(--transition-wk-duration)]',
            'ease-[var(--transition-wk-easing)]',
            'hover:bg-[var(--color-wk-bg-subtle)]',
            'focus:outline-hidden',
            'focus-visible:ring-[length:var(--ring-wk-width)]',
            'focus-visible:ring-[var(--color-wk-ring)]',
            'cursor-pointer',
        ]), $scope);
@endphp

<button
    type="button"
    {{-- The copy lives in resources/js/components/clipboard-button.js. It cannot
         live here: three statements and an arrow function, none of which Alpine's
         CSP build parses — under a strict policy the button took focus and copied
         nothing. The value goes through AlpinePayload::from rather than addslashes, which
         escapes an apostrophe but leaves a newline or a backslash to end the
         string it sits in. --}}
    x-data="wirekitClipboardButton({ value: {{ \Pushery\WireKit\Support\AlpinePayload::from($value) }}, duration: {{ (int) $duration }} })"
    x-on:click="copy()"
    {{-- An icon-only button has no text to be named by, and the prop comment above says so —
         but nothing enforced it, so `icon-only` without an `aria-label` shipped a button a
         screen reader announces as "button" and nothing else. A translated fallback is worse
         than a caller's own name and far better than none, and it is only reached when the
         caller supplied neither form of name. --}}
    @if($iconOnly && ! $attributes->has('aria-label') && ! $attributes->has('aria-labelledby'))
        aria-label="{{ __('wirekit::Copy to clipboard') }}"
    @endif
    {{ $attributes->class([$classes]) }}
>
    {{-- Copy icon (shown when not copied). Muted/gray at rest — a copy affordance
         is subtle until it succeeds, then it pops to the success green below. --}}
    <svg x-show="idle" class="w-4 h-4 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
    </svg>
    {{-- Check icon (shown when copied) --}}
    <svg x-show="copied" class="w-4 h-4 text-[color:var(--color-wk-success-text)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" x-cloak>
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
    </svg>
    {{-- Warning icon (shown when the write was refused). The refusal used to take the success
         back to the look of an untouched button, so a sighted reader saw at most the check flash
         and nothing saying the clipboard was empty. A different shape from the check, not only a
         different color, so the failure reads without telling red from green. --}}
    <svg x-show="failed" class="w-4 h-4 text-[color:var(--color-wk-danger-text)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" data-wk-clipboard-failed x-cloak>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
    </svg>

    {{--
        Stable-width label: stack the default slot and the "Copied!" label
        in the same grid cell. The grid cell auto-sizes to the WIDER of its
        two children — but ONLY if both children stay in the layout. We
        therefore toggle visibility (NOT display) so each label keeps its
        layout slot regardless of which one is currently visible. Using
        x-show / display:none would collapse the cell to whichever child
        is rendered, defeating the stable-width goal — that was the
        regression in the first iteration of this fix.

        Pre-Alpine init the second span carries a static
        `style="visibility: hidden"` so its space is reserved before
        Alpine evaluates :style. Once Alpine init runs, :style takes
        over and toggles visibility on copy state changes.
    --}}
    @unless($iconOnly)
        <span class="grid items-center">
            <span
                class="col-start-1 row-start-1"
                :style="{ visibility: idle ? 'visible' : 'hidden' }"
            >{{ $slot }}</span>
            <span
                class="col-start-1 row-start-1"
                style="visibility: hidden"
                :style="{ visibility: copied ? 'visible' : 'hidden' }"
                aria-hidden="true"
            >{{ $copiedText }}</span>
            {{-- The third label in the same cell, so the width still holds: the refusal, said on
                 screen. Hidden from assistive technology like the copied label, because the alert
                 below already says it. --}}
            <span
                class="col-start-1 row-start-1"
                style="visibility: hidden"
                :style="{ visibility: failed ? 'visible' : 'hidden' }"
                aria-hidden="true"
                data-wk-clipboard-failed-label
            >{{ __('wirekit::Copy failed') }}</span>
        </span>
    @endunless

    {{-- Screen reader announcement --}}
    <span x-show="copied" class="sr-only" role="status" aria-live="polite">{{ __('wirekit::Copied to clipboard') }}</span>
    {{-- The deviation announcement. The success above is OPTIMISTIC — it fires before the
         write is attempted, on purpose, because a promise that never settles would leave the
         reader with no feedback at all. That bargain only holds if a refusal takes it back:
         without this, "Copied to clipboard" was announced for a write the browser declined,
         and the reader pasted nothing with no way to find out why.

         `role="alert"` rather than `status`, because this one interrupts: it is correcting
         something the reader was already told. --}}
    <span x-show="failed" x-cloak class="sr-only" role="alert">{{ __('wirekit::Copy failed') }}</span>
</button>
