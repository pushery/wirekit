@props([
    'key' => null,
    'target' => null,
    'threshold' => 0.1,
    'promptOnReturn' => true,
    'minDwellSeconds' => 30,
    'previewMode' => false,
    // `boundary` — null (default) = the resume-prompt pill pins to the
    // viewport via Tailwind `fixed`. `'container'` = scoped to the
    // nearest positioned ancestor via Tailwind `absolute`. Use
    // `'container'` when the bookmark surface lives inside a contained
    // reading frame (preview iframe, sidebar pane, modal body).
    'boundary' => null,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $promptOnReturn = BooleanProp::from($promptOnReturn, true);
    $previewMode = BooleanProp::from($previewMode, false);

    // Reading-bookmark — saves the reader's scroll position to localStorage
    // and surfaces a "Resume reading?" pill on return-visit if they spent
    // enough time reading the previous session AND scrolled past the threshold.
    //
    // All localStorage operations are wrapped in try/catch — falls back to
    // a no-op on private browsing, quota-exceeded, or storage-disabled.

    $previewModeBool = filter_var($previewMode, FILTER_VALIDATE_BOOL);

    // In previewMode, the component renders an inert placeholder — no
    // localStorage reads/writes, no scroll listener, no resume-prompt
    // pill. Used inside docs.wirekit.app preview iframes where (a) multiple
    // bookmarks on one page would all surface their pills simultaneously
    // (one per localStorage key), and (b) the preview iframe is too
    // narrow to demo the production UX anyway.
    if ($previewModeBool) {
        return;
    }

    if (! $key) {
        // Fail loud in dev — `key` is required and developer-supplied (slug
        // of the article). Without it we can't disambiguate per-article
        // bookmarks. Same shape as scope-required props elsewhere.
        throw new \InvalidArgumentException('<x-wirekit::reading-bookmark> requires a `key` prop (e.g. key="article-{{ $post->slug }}").');
    }

    // Resolve boundary (v2.4.0 Ext 1 extended). null = viewport-pinned;
    // 'container' = scoped to nearest positioned ancestor; any other
    // non-empty string is treated as a CSS selector and surfaces the
    // same scoped (Tailwind `absolute`) shape.
    if ($boundary === null) {
        $resolvedBoundary = null;
        $boundarySelector = null;
    } elseif ($boundary === 'container') {
        $resolvedBoundary = 'container';
        $boundarySelector = null;
    } elseif (is_string($boundary) && $boundary !== '') {
        $resolvedBoundary = 'selector';
        $boundarySelector = $boundary;
    } else {
        $resolvedBoundary = WireKit::validateProp(
            'reading-bookmark',
            'boundary',
            (string) $boundary,
            ['container', '<css-selector-string>']
        );
        $boundarySelector = null;
    }

    $useScoped = $resolvedBoundary === 'container' || $resolvedBoundary === 'selector';
    $boundaryClass = $useScoped
        ? 'absolute bottom-[var(--padding-wk-x-lg)] right-[var(--padding-wk-x-lg)]'
        : 'fixed bottom-[var(--padding-wk-x-lg)] right-[var(--padding-wk-x-lg)]';

    $rootClass = WireKit::resolveClasses('reading-bookmark', 'base', implode(' ', [
        'wk-reading-bookmark',
        $boundaryClass,
        'z-[var(--z-wk-sticky)]',
        'flex items-center gap-3 px-4 py-3',
        'text-sm text-[color:var(--color-wk-text)]',
    ]), $scope);

    $thresholdFloat = max(0.0, min(1.0, (float) $threshold));
    $minDwell = max(0, (int) $minDwellSeconds);
    $promptEnabled = filter_var($promptOnReturn, FILTER_VALIDATE_BOOL);

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Same convention as reading-spine + reading-meta.
    $resolvedTarget = $target ?? 'main, article';
@endphp

<div
    {{-- Bookmark key exposed as a data attribute so sibling primitives
         (reading-minimap E2) can wire to the same localStorage payload
         without re-declaring the key in their own prop list. --}}
    data-reading-bookmark-key="{{ $key }}"
    x-data="{
        showPrompt: false,
        savedTop: 0,
        _key: {{ json_encode($key, JSON_THROW_ON_ERROR) }},
        _target: {{ json_encode($resolvedTarget, JSON_THROW_ON_ERROR) }},
        _threshold: {{ $thresholdFloat }},
        _promptEnabled: {{ $promptEnabled ? 'true' : 'false' }},
        _minDwell: {{ $minDwell }},
        _saveTimer: null,
        _enterAt: 0,
        _onScroll: null,
        _onStorage: null,
        init() {
            this._enterAt = Date.now();
            // Try to read existing bookmark on mount; show prompt if all conditions met.
            try {
                const raw = localStorage.getItem(this._key);
                if (raw) {
                    const data = JSON.parse(raw);
                    if (data && typeof data.top === 'number' && data.top > 0
                        && data.dwell >= this._minDwell
                        && this._promptEnabled) {
                        this.savedTop = data.top;
                        this.showPrompt = true;
                    }
                }
            } catch (_) { /* private mode / disabled / parse error — silently ignore */ }
            // Throttled save on scroll: only persist if scroll moved > threshold * scrollHeight since last save.
            // `top` resolution: when the target element is an internally-
            // scrollable container (overflow:auto/scroll), use ITS
            // scrollTop. Otherwise fall back to window.scrollY (the
            // document-level scroll case). Without this scoping, the
            // minimap's bookmark-marker (reading-minimap E2 extension)
            // computed marker percentages against a different scroll
            // root than the one the bookmark recorded — visible as the
            // marker appearing in the wrong vertical position when the
            // article lives in an internally-scrollable wrapper.
            let lastSavedTop = 0;
            this._onScroll = () => {
                if (this._saveTimer) clearTimeout(this._saveTimer);
                this._saveTimer = setTimeout(() => {
                    const target = document.querySelector(this._target);
                    const targetOverflowsInternally = target && (() => {
                        const cs = getComputedStyle(target);
                        return cs.overflowY === 'auto' || cs.overflowY === 'scroll';
                    })();
                    const scrollHeight = (target?.scrollHeight) || document.documentElement.scrollHeight;
                    const top = targetOverflowsInternally
                        ? target.scrollTop
                        : window.scrollY;
                    const movedFraction = scrollHeight > 0 ? Math.abs(top - lastSavedTop) / scrollHeight : 0;
                    if (movedFraction < this._threshold) return;
                    // User scrolled to the top: forget the bookmark (they're done).
                    if (top <= 0) {
                        try { localStorage.removeItem(this._key); } catch (_) {}
                        lastSavedTop = 0;
                        // Notify sibling primitives (reading-minimap E2)
                        // within the same tab — the browser `storage`
                        // event only fires for OTHER tabs.
                        window.dispatchEvent(new CustomEvent('wirekit:reading-bookmark:cleared', { detail: { key: this._key } }));
                        return;
                    }
                    const dwell = Math.floor((Date.now() - this._enterAt) / 1000);
                    try {
                        localStorage.setItem(this._key, JSON.stringify({ top, dwell, t: Date.now() }));
                        lastSavedTop = top;
                        window.dispatchEvent(new CustomEvent('wirekit:reading-bookmark:saved', { detail: { key: this._key, top, dwell } }));
                    } catch (_) { /* quota / private — ignore */ }
                }, 1000);
            };
            window.addEventListener('scroll', this._onScroll, { passive: true });
            // Cross-tab consistency: react to localStorage events from the same key.
            this._onStorage = (e) => {
                if (e.key !== this._key) return;
                if (!e.newValue) {
                    this.showPrompt = false;
                    this.savedTop = 0;
                }
            };
            window.addEventListener('storage', this._onStorage);
        },
        resume() {
            this.showPrompt = false;
            const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            // Resume to the SAME scroll root the save handler wrote to —
            // internally-scrollable target → target.scrollTop; otherwise
            // window.scrollY. Without this scoping, an internally-
            // scrollable target's saved offset would be applied to the
            // window-level scroll on resume, scrolling to the wrong
            // position (or no-op if the document isn't scrollable).
            const target = document.querySelector(this._target);
            const targetOverflowsInternally = target && (() => {
                const cs = getComputedStyle(target);
                return cs.overflowY === 'auto' || cs.overflowY === 'scroll';
            })();
            if (targetOverflowsInternally) {
                target.scrollTo({ top: this.savedTop, behavior: reduced ? 'auto' : 'smooth' });
            } else {
                window.scrollTo({ top: this.savedTop, behavior: reduced ? 'auto' : 'smooth' });
            }
        },
        dismiss() {
            this.showPrompt = false;
            // Keep the saved bookmark — re-prompt on next visit.
        },
        clear() {
            try { localStorage.removeItem(this._key); } catch (_) {}
            this.showPrompt = false;
            this.savedTop = 0;
            window.dispatchEvent(new CustomEvent('wirekit:reading-bookmark:cleared', { detail: { key: this._key } }));
        },
        destroy() {
            if (this._saveTimer) clearTimeout(this._saveTimer);
            window.removeEventListener('scroll', this._onScroll);
            window.removeEventListener('storage', this._onStorage);
        },
    }"
    x-show="showPrompt"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0 translate-y-2"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-150"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-2"
    role="status"
    aria-live="polite"
    {{ $attributes->class([$rootClass]) }}
>
    <span class="wk-reading-bookmark__label">Resume reading where you left off?</span>
    <button
        type="button"
        @click="resume()"
        class="wk-reading-bookmark__resume inline-flex items-center px-3 py-1 rounded-[var(--radius-wk-md)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] text-xs font-medium hover:bg-[var(--color-wk-accent-hover)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]"
    >
        Resume
    </button>
    <button
        type="button"
        @click="dismiss()"
        aria-label="{{ __('Dismiss') }}"
        class="wk-reading-bookmark__dismiss inline-flex items-center justify-center w-6 h-6 rounded-full text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 10-1.06-1.06L10 8.94 6.28 5.22z" />
        </svg>
    </button>
</div>
