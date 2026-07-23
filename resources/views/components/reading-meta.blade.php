@props([
    'target' => null,
    'wpm' => 225,
    'showRemaining' => false,
    'perParagraph' => false,
    'totalLabel' => 'min read',
    'remainingLabel' => 'min remaining',
    'paragraphLabelTemplate' => '{n} min',
    'paragraphMinWords' => 30,
    'cjkCharsPerMinute' => 500,
    'scope' => null,
])

@php
    use Pushery\WireKit\Support\BooleanProp;
    use Pushery\WireKit\WireKit;

    // Blade compiles an UNBOUND attribute to a string, and 'false' is truthy — so
    // `prop="false"` used to mean the opposite of what the call site reads as, silently.
    // Normalized against each prop's own default so a cast never flips a feature that was on.
    $showRemaining = BooleanProp::from($showRemaining, false);
    $perParagraph = BooleanProp::from($perParagraph, false);

    // Reading-meta — small text element showing "~12 min read" (initial) and
    // optionally "~5 min remaining" (after scroll). On mount, measures the
    // target's textContent word count; for articles in CJK languages where
    // whitespace tokenisation underestimates, falls back to a character-based
    // estimate (--cjk-chars-per-minute / default 500).
    //
    // Skips text inside <pre>, <code>, <figure>, <figcaption>, [data-language],
    // and <img>/<picture>/<svg> — code blocks and figure captions don't read
    // at prose pace.
    //
    // perParagraph mode (Medium-style): when enabled, the component injects
    // a small `<span class="wk-reading-meta-paragraph">N min</span>`
    // annotation immediately before each <p> in the target with at least
    // `paragraphMinWords` words. Annotations show estimated remaining-time
    // FROM that paragraph onward (re-computed on scroll). Default off; opt-in.
    // aria-hidden — total/remaining display is the canonical SR reading-time.

    $wpmInt = max(50, (int) $wpm);
    $cjkCpm = max(100, (int) $cjkCharsPerMinute);
    $showRemainingBool = filter_var($showRemaining, FILTER_VALIDATE_BOOL);
    $perParagraphBool = filter_var($perParagraph, FILTER_VALIDATE_BOOL);
    $paragraphMinWordsInt = max(1, (int) $paragraphMinWords);

    // target=null resolves to the family default 'main, article' (first-match
    // wins). Same convention as reading-spine + reading-bookmark.
    $resolvedTarget = $target ?? 'main, article';

    $rootClass = WireKit::resolveClasses('reading-meta', 'base', implode(' ', [
        'wk-reading-meta',
        'inline-flex items-center gap-1',
    ]), $scope);
@endphp

<div
    x-data="{
        totalMinutes: 0,
        remainingMinutes: 0,
        wordCount: 0,
        _target: {{ json_encode($resolvedTarget, JSON_THROW_ON_ERROR) }},
        _wpm: {{ $wpmInt }},
        _cjkCpm: {{ $cjkCpm }},
        _showRemaining: {{ $showRemainingBool ? 'true' : 'false' }},
        _perParagraph: {{ $perParagraphBool ? 'true' : 'false' }},
        _paragraphMinWords: {{ $paragraphMinWordsInt }},
        _paragraphTemplate: {{ json_encode($paragraphLabelTemplate, JSON_THROW_ON_ERROR) }},
        _scrollHandler: null,
        _ticking: false,
        _paragraphData: [],  // [{ el, wordCount }] — populated in perParagraph mode
        init() {
            const el = document.querySelector(this._target);
            if (!el) return;
            this.wordCount = this.countWords(el);
            this.totalMinutes = Math.max(1, Math.ceil(this.wordCount / this._wpm));
            this.remainingMinutes = this.totalMinutes;
            if (this._perParagraph) {
                this._injectParagraphAnnotations(el);
            }
            if (this._showRemaining || this._perParagraph) {
                this._scrollHandler = () => this._onScroll();
                window.addEventListener('scroll', this._scrollHandler, { passive: true });
                window.addEventListener('resize', this._scrollHandler, { passive: true });
                this._onScroll();
            }
        },
        _injectParagraphAnnotations(root) {
            // Walk every <p> in the target; for each one with >= minWords,
            // inject a sibling <span> annotation with the per-paragraph
            // remaining-time hint. Stored in _paragraphData for live updates.
            const paragraphs = root.querySelectorAll('p');
            paragraphs.forEach((p) => {
                const text = (p.textContent || '').trim();
                const words = text.split(/\s+/).filter((w) => w.length > 0).length;
                if (words < this._paragraphMinWords) return;
                const annotation = document.createElement('span');
                annotation.className = 'wk-reading-meta-paragraph';
                annotation.setAttribute('aria-hidden', 'true');
                annotation.dataset.minutes = '';
                p.parentNode?.insertBefore(annotation, p);
                this._paragraphData.push({ el: annotation, paragraph: p, wordCount: words });
            });
            this._updateParagraphLabels();
        },
        _updateParagraphLabels() {
            // Find the first paragraph still below the viewport top — that's
            // the current reading position. Annotations BEFORE it get
            // hidden (annotation.minutes = 0 — already read), annotations AT
            // or AFTER it show remaining time from that paragraph onward.
            const viewportTop = window.scrollY + 80; // 80px header offset
            let cumulativeWordsAfter = 0;
            // Iterate in reverse to accumulate words-AFTER from each paragraph.
            const reversed = [...this._paragraphData].reverse();
            const labels = new Map();
            for (const item of reversed) {
                cumulativeWordsAfter += item.wordCount;
                labels.set(item, cumulativeWordsAfter);
            }
            this._paragraphData.forEach((item) => {
                const rect = item.paragraph.getBoundingClientRect();
                const paragraphTop = rect.top + window.scrollY;
                if (paragraphTop < viewportTop) {
                    item.el.style.display = 'none';
                    return;
                }
                item.el.style.display = '';
                const minutes = Math.max(1, Math.ceil(labels.get(item) / this._wpm));
                item.el.dataset.minutes = String(minutes);
                item.el.textContent = this._paragraphTemplate.replace('{n}', String(minutes));
            });
        },
        countWords(el) {
            // Clone so DOM removal doesn't damage the live page.
            const clone = el.cloneNode(true);
            clone.querySelectorAll('pre, code, figure, figcaption, img, picture, svg, [data-language]').forEach((n) => n.remove());
            const text = (clone.textContent || '').trim();
            // CJK heuristic: if >40% of chars are CJK ideographs, switch to
            // character-based estimation. Whitespace tokenisation under-counts
            // CJK because Chinese/Japanese/Korean text has no spaces between
            // logographic characters.
            const cjkChars = (text.match(/[一-鿿぀-ヿ가-힯]/g) || []).length;
            if (text.length > 0 && cjkChars / text.length > 0.4) {
                // Convert chars to word-equivalent at the wpm baseline so the
                // downstream Math.ceil(wordCount / wpm) still works.
                return Math.ceil(cjkChars * (this._wpm / this._cjkCpm));
            }
            return text.split(/\s+/).filter((w) => w.length > 0).length;
        },
        _onScroll() {
            if (this._ticking) return;
            requestAnimationFrame(() => {
                const el = document.querySelector(this._target);
                if (!el) { this._ticking = false; return; }
                if (this._showRemaining) {
                    // Slice still below the bottom edge of the viewport.
                    const rect = el.getBoundingClientRect();
                    const elTop = rect.top + window.scrollY;
                    const elBottom = elTop + el.scrollHeight;
                    const viewportBottom = window.scrollY + window.innerHeight;
                    const remainingFraction = Math.max(0, Math.min(1, (elBottom - viewportBottom) / el.scrollHeight));
                    this.remainingMinutes = Math.max(0, Math.ceil(this.wordCount * remainingFraction / this._wpm));
                }
                if (this._perParagraph) {
                    this._updateParagraphLabels();
                }
                this._ticking = false;
            });
            this._ticking = true;
        },
        destroy() {
            if (this._scrollHandler) {
                window.removeEventListener('scroll', this._scrollHandler);
                window.removeEventListener('resize', this._scrollHandler);
            }
        },
    }"
    role="status"
    aria-live="polite"
    {{ $attributes->class([$rootClass]) }}
    style="font-size: var(--reading-meta-text-size); color: var(--reading-meta-color);"
>
    <span class="wk-reading-meta__total">
        ~<span x-text="totalMinutes"></span> {{ $totalLabel }}
    </span>
    @if ($showRemainingBool)
        <span class="wk-reading-meta__separator" aria-hidden="true">·</span>
        <span class="wk-reading-meta__remaining">
            ~<span x-text="remainingMinutes"></span> {{ $remainingLabel }}
        </span>
    @endif
</div>
