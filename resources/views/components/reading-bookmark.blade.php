{{-- optimistic-ui: n/a — client-only
     The bookmark lives in the browser. --}}
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

    // Dev-only — flags unknown props in debug (silent in prod). Declared list
    // auto-derived from this component's @props. Fully qualified: this view's
    // imports may live in a later @php block, which does not reach this one.
    \Pushery\WireKit\WireKit::warnUnknownProps('reading-bookmark', $attributes->getAttributes());

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
        // ⚠️ The bottom of the viewport is NOT the bottom of the usable screen on a phone
        // with a home indicator — the inset is 34px on a notched iPhone, and this control
        // sat inside it. The library states the rule in dist/wirekit.css and already ships
        // this exact expression for `.wk-fab` and `.wk-bottom-nav`; these offsets were
        // simply never given the term. `env()` resolves to 0 wherever there is no inset,
        // so it costs nothing elsewhere.
        //
        // No browser test can catch this: `env(safe-area-inset-*)` is 0 in headless
        // Playwright, which is why it survived every green mobile run.
        : 'fixed bottom-[calc(var(--padding-wk-x-lg)_+_env(safe-area-inset-bottom,0px))] right-[var(--padding-wk-x-lg)]';

    $rootClass = WireKit::resolveClasses('reading-bookmark', 'base', implode(' ', [
        'wk-reading-bookmark',
        $boundaryClass,
        'z-[var(--z-wk-sticky)]',
        'flex items-center gap-3 px-4 py-3',
        'text-[length:var(--text-wk-sm)] text-[color:var(--color-wk-text)]',
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
    {{-- Saving, restoring and the cross-tab sync live in the factory
         (resources/js/components/reading-bookmark.js). The block did not parse
         under Alpine's CSP build, so nothing was saved and the resume prompt
         never appeared — silently, which is this component's whole failure
         mode. --}}
    x-data="wirekitReadingBookmark({
        key: {{ \Pushery\WireKit\Support\AlpinePayload::from($key) }},
        target: {{ \Pushery\WireKit\Support\AlpinePayload::from($resolvedTarget) }},
        threshold: {{ \Pushery\WireKit\Support\AlpinePayload::from($thresholdFloat) }},
        promptEnabled: {{ \Pushery\WireKit\Support\AlpinePayload::from($promptEnabled) }},
        minDwell: {{ \Pushery\WireKit\Support\AlpinePayload::from($minDwell) }},
    })"
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
    {{-- Both controls spell out `cursor-pointer` because nothing else supplies
         it: Tailwind v4's preflight sets `cursor: default` on `button` (v3
         inherited the user agent's pointer), and `.wk-reading-bookmark__resume`
         and `__dismiss` carry no declarations in dist/wirekit.css — the class
         names are hooks for a developer, not styles. Without it the prompt
         reads as static text. --}}
    <button
        type="button"
        @click="resume()"
        class="wk-reading-bookmark__resume inline-flex items-center cursor-pointer px-3 py-1 rounded-[var(--radius-wk-md)] bg-[var(--color-wk-accent)] text-[color:var(--color-wk-accent-fg)] text-[length:var(--text-wk-xs)] font-medium hover:bg-[var(--color-wk-accent-hover)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)] focus-visible:ring-offset-[length:var(--ring-wk-offset)] focus-visible:ring-offset-[var(--color-wk-ring-offset)]"
    >
        Resume
    </button>
    <button
        type="button"
        @click="dismiss()"
        aria-label="{{ __('wirekit::Dismiss') }}"
        class="wk-reading-bookmark__dismiss inline-flex items-center justify-center cursor-pointer w-6 h-6 rounded-full text-[color:var(--color-wk-text-muted)] hover:text-[color:var(--color-wk-text)] focus-visible:outline-none focus-visible:ring-[length:var(--ring-wk-width)] focus-visible:ring-[var(--color-wk-ring)]"
    >
        <svg aria-hidden="true" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 10-1.06-1.06L10 8.94 6.28 5.22z" />
        </svg>
    </button>
</div>
