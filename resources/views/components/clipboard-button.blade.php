@props([
    'value' => '',
    'copiedText' => __('Copied!'),
    'duration' => 2000,
    // Bare icon button — no border / bg / label, just the copy glyph (muted,
    // pops green on copy). For compact action rows (a message's copy control).
    // Requires an aria-label (there is no visible text to name it).
    'iconOnly' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

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
            'focus:outline-none',
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
            'focus:outline-none',
            'focus-visible:ring-[length:var(--ring-wk-width)]',
            'focus-visible:ring-[var(--color-wk-ring)]',
            'cursor-pointer',
        ]), $scope);
@endphp

<button
    type="button"
    x-data="{ copied: false }"
    x-on:click="
        navigator.clipboard.writeText('{{ addslashes($value) }}');
        copied = true;
        setTimeout(() => { copied = false }, {{ (int) $duration }});
    "
    {{ $attributes->class([$classes]) }}
>
    {{-- Copy icon (shown when not copied). Muted/gray at rest — a copy affordance
         is subtle until it succeeds, then it pops to the success green below. --}}
    <svg x-show="!copied" class="w-4 h-4 text-[color:var(--color-wk-text-muted)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
    </svg>
    {{-- Check icon (shown when copied) --}}
    <svg x-show="copied" class="w-4 h-4 text-[color:var(--color-wk-success-text)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true" x-cloak>
        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
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
                :style="{ visibility: copied ? 'hidden' : 'visible' }"
            >{{ $slot }}</span>
            <span
                class="col-start-1 row-start-1"
                style="visibility: hidden"
                :style="{ visibility: copied ? 'visible' : 'hidden' }"
                aria-hidden="true"
            >{{ $copiedText }}</span>
        </span>
    @endunless

    {{-- Screen reader announcement --}}
    <span x-show="copied" class="sr-only" role="status" aria-live="polite">{{ __('Copied to clipboard') }}</span>
</button>
