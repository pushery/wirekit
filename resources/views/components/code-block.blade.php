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
    // explicitly null those properties to neutralize generic prose
    // stylesheets that target raw markdown code blocks (e.g. a host
    // prose.css that adds bg-white + radius + padding to every <pre>) —
    // without this defense the developer sees a white box nested inside
    // our muted container.
    $preClasses = implode(' ', [
        'm-0 p-0',
        'bg-transparent border-0 rounded-none',
    ]);

    $codeClasses = implode(' ', [
        'wk-scrollbar block overflow-x-auto',
        'p-[var(--space-wk-md,1rem)]',
        'bg-transparent border-0 rounded-none',
        'font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]',
        'text-[length:var(--text-wk-sm)]',
        'leading-relaxed',
        'text-[color:var(--color-wk-text)]',
    ]);
@endphp

<div {{ $attributes->class([$wrapperClasses]) }} data-wk-code-block>
    @if($filename || $copy)
        {{-- x-data lifted to toolbar so the live-region span can read state (WCAG 2.2 SC 4.1.3).
             srMessage is the SR-only announcement; copied drives the icon swap.
             Symmetric success/failure announcements: success path sets both; error path sets only srMessage. --}}
        <div
            class="flex items-center justify-between gap-2 border-b border-[var(--color-wk-border)] px-[var(--space-wk-md,1rem)] py-[var(--space-wk-xs,0.25rem)]"
            @if($copy) x-data="{ copied: false, srMessage: '' }" @endif
        >
            @if($filename)
                {{-- Filename header truncates from the START (leading ellipsis) on narrow
                     viewports so the actual file NAME stays visible while the parent path
                     collapses — `app/Providers/AppServiceProvider.php` becomes
                     `…rs/AppServiceProvider.php` instead of `app/Providers/Ap…`. The CSS
                     trick is `direction: rtl` on the outer span (clips at the left) with
                     `unicode-bidi: plaintext` on the inner span so the path itself still
                     renders LTR. `min-w-0` is the standard flex-overflow unlock so the
                     span actually shrinks instead of pushing the copy button off-screen.
                     `title` surfaces the full path on hover (desktop) and on long-press
                     (iOS Safari); the unclipped text remains in the DOM so screen readers
                     always announce the full name. --}}
                <span
                    class="min-w-0 flex-1 overflow-hidden whitespace-nowrap text-[length:var(--text-wk-xs,0.75rem)] text-[color:var(--color-wk-text-muted)] font-[family-name:var(--font-wk-mono,ui-monospace,monospace)]"
                    style="direction: rtl; text-align: left; text-overflow: ellipsis;"
                    title="{{ $filename }}"
                ><span style="unicode-bidi: plaintext;">{{ $filename }}</span></span>
            @else
                <span class="flex-1"></span>
            @endif

            @if($copy)
                <button
                    type="button"
                    x-on:click="
                        navigator.clipboard.writeText($el.closest('[data-wk-code-block]').querySelector('code').textContent)
                            .then(() => {
                                copied = true;
                                srMessage = 'Code copied to clipboard';
                                setTimeout(() => { copied = false; srMessage = ''; }, 2000);
                            })
                            .catch(() => {
                                srMessage = 'Copy failed';
                                setTimeout(() => { srMessage = ''; }, 2000);
                            });
                    "
                    class="shrink-0 cursor-pointer text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] transition-colors p-1"
                    aria-label="{{ __('Copy to clipboard') }}"
                    :aria-label="copied ? 'Copied to clipboard' : 'Copy to clipboard'"
                >
                    <template x-if="!copied">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                        </svg>
                    </template>
                    <template x-if="copied">
                        <svg class="h-4 w-4 text-[color:var(--color-wk-success)]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </template>
                </button>
                {{-- Live region must be in DOM at first paint; x-text toggles content so SR announces on copy success / failure.
                     Polite (not assertive) so the announcement does not interrupt other speech. --}}
                <span class="sr-only" role="status" aria-live="polite" x-text="srMessage"></span>
            @endif
        </div>
    @endif

    <pre @class([$preClasses])><code @class([$codeClasses]) @if($language) data-language="{{ $language }}" @endif>{{ $slot }}</code></pre>
</div>
