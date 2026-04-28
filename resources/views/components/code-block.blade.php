@props([
    'language' => null,
    'filename' => null,
    'copy' => false,
    'scope' => null,
])

@php
    use Pushery\WireKit\WireKit;

    $wrapperClasses = WireKit::resolveClasses('code-block', 'base', implode(' ', [
        'relative',
        'rounded-[var(--radius-wk-md)]',
        'border-[length:var(--border-wk-width)]',
        'border-[var(--color-wk-border)]',
        'bg-[var(--color-wk-bg-muted)]',
        'overflow-hidden',
    ]), $scope);

    // The wrapper supplies bg / border / radius. The inner <pre> + <code>
    // explicitly null those properties to neutralise generic prose
    // stylesheets that target raw markdown code blocks (e.g. the docs-app's
    // prose.css adds bg-white + radius + padding to every <pre>) — without
    // this defence the consumer sees a white box nested inside our muted
    // container.
    $preClasses = implode(' ', [
        'm-0 p-0',
        'bg-transparent border-0 rounded-none',
    ]);

    $codeClasses = implode(' ', [
        'block overflow-x-auto',
        'p-[var(--space-wk-md,1rem)]',
        'bg-transparent border-0 rounded-none',
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'text-[length:var(--text-wk-sm)]',
        'leading-relaxed',
        'text-[var(--color-wk-text)]',
    ]);
@endphp

<div {{ $attributes->class([$wrapperClasses]) }} data-wk-code-block>
    @if($filename || $copy)
        {{-- x-data lifted to toolbar so the live-region span can read `copied` (WCAG 2.2 SC 4.1.3). --}}
        <div
            class="flex items-center justify-between border-b border-[var(--color-wk-border)] px-[var(--space-wk-md,1rem)] py-[var(--space-wk-xs,0.25rem)]"
            @if($copy) x-data="{ copied: false }" @endif
        >
            @if($filename)
                <span class="text-[length:var(--text-wk-xs,0.75rem)] text-[var(--color-wk-text-muted)] font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]">{{ $filename }}</span>
            @else
                <span></span>
            @endif

            @if($copy)
                <button
                    type="button"
                    x-on:click="
                        navigator.clipboard.writeText($el.closest('[data-wk-code-block]').querySelector('code').textContent);
                        copied = true;
                        setTimeout(() => copied = false, 2000);
                    "
                    class="text-[var(--color-wk-text-muted)] hover:text-[var(--color-wk-text)] transition-colors p-1"
                    aria-label="Copy to clipboard"
                    :aria-label="copied ? 'Copied to clipboard' : 'Copy to clipboard'"
                >
                    <template x-if="!copied">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                        </svg>
                    </template>
                    <template x-if="copied">
                        <svg class="h-4 w-4 text-[var(--color-wk-success)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                </button>
                {{-- Live region must be in DOM at first paint; x-text toggles content so SR announces on copy. --}}
                <span class="sr-only" role="status" aria-live="polite" x-text="copied ? 'Code copied to clipboard' : ''"></span>
            @endif
        </div>
    @endif

    <pre @class([$preClasses])><code @class([$codeClasses]) @if($language) data-language="{{ $language }}" @endif>{{ $slot }}</code></pre>
</div>
