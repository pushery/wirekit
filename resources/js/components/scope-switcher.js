/**
 * WireKit Scope Switcher — the searchable list inside the popover.
 *
 * WHAT THIS IS, AND WHAT IT DELIBERATELY IS NOT. A scope switcher is NAVIGATION, not a form
 * field: its "value" is the page you are on, and choosing an item goes there. Nothing is
 * submitted, and there is no hidden input. That is why it is neither a combobox (a form
 * field whose trigger IS its text input) nor a dropdown (the WAI-ARIA menu pattern, where an
 * `<input>` inside `role="menu"` violates the owned-elements rule and there is only
 * type-ahead, never search).
 *
 * THE DOM IS THE MODEL. Items are collected from the rendered markup on init rather than
 * passed in as JSON. The server already rendered them, and duplicating the list into a
 * config object gives two copies to keep in step — the shape of defect this codebase keeps
 * finding elsewhere. Filtering hides and shows what is already there.
 *
 * FOCUS NEVER LEAVES THE INPUT. Items are never focused; the active one is published through
 * `aria-activedescendant`, which is what lets a reader type and steer at once. That single
 * decision is the reason the arrow keys must call preventDefault: without it the caret jumps
 * to the ends of the query while the list moves, and the two fight each other.
 */
export default function wirekitScopeSwitcher(config = {}) {
    return {
        /** Current query, bound to the search input. */
        query: '',

        /** `key` of the item the keyboard is on, or null. NOT the current scope. */
        activeKey: null,

        /** How many items match right now — read by the live region. */
        visibleCount: 0,

        /** The id prefix the Blade side stamped onto every option. */
        idPrefix: config.idPrefix || '',

        /** Announce at most this often, so a fast typist is not read out letter by letter. */
        announceDelay: 150,

        /*
         * Edge shadows for the option list, which scrolls inside a capped panel.
         *
         * The list is the one part of this component a reader can lose their place in: it
         * caps at `list-max-height` and clips silently, so a long scope list looked like a
         * complete list that happened to end. The sidebar solved this already with an
         * IntersectionObserver over two 1px sentinels; the same CSS classes are reused here
         * (`wk-scroll-shadow-top` / `-bottom`), so the affordance is identical rather than
         * merely similar.
         *
         * It is implemented HERE rather than by nesting `x-data="wirekitStickyPanelShadows"`
         * around the list, and that is not a preference: Alpine scopes `$refs` to the
         * nearest `x-data` root, so wrapping the list in a second component would take
         * `$refs.list` away from this one — and every keyboard and search path reads it.
         */
        topShadow: false,
        bottomShadow: false,

        _items: [],
        _announceTimer: null,
        _shadowObserver: null,

        init() {
            this._collect();
            this.reset();
            this._watchScrollEdges();

            // Re-collect when the server replaces the list — a Livewire morph, or the
            // server-search mode of this component. Without it the arrows would walk a list
            // of detached nodes, which fails silently: nothing moves and nothing errors.
            if (this.$refs.list && typeof MutationObserver !== 'undefined') {
                this._observer = new MutationObserver(() => {
                    this._collect();
                    this.filter();
                });

                this._observer.observe(this.$refs.list, { childList: true, subtree: true });
            }
        },

        /**
         * Watch the list's two 1px sentinels and mirror their visibility into the shadows.
         *
         * A sentinel that is IN VIEW means that edge has been reached, so the shadow on that
         * side goes away. Guarded on the API existing, because a bundle can run in a context
         * that has no IntersectionObserver and a missing affordance is better than a throw
         * inside `init()` — an x-data that throws while building leaves the element with an
         * empty scope, which silences every directive on it.
         */
        _watchScrollEdges() {
            const list = this.$refs.list;
            const top = this.$refs.topSentinel;
            const bottom = this.$refs.bottomSentinel;

            if (!list || !top || !bottom || typeof IntersectionObserver === 'undefined') {
                return;
            }

            this._shadowObserver = new IntersectionObserver((entries) => {
                // Null-guard against a post-destroy fire: a browser-queued callback can run
                // after Alpine teardown, and writing to a torn-down scope is the exact
                // null-callback class the house rule for these plugins exists for.
                if (!this._shadowObserver) {
                    return;
                }

                for (const entry of entries) {
                    if (entry.target === top) {
                        this.topShadow = ! entry.isIntersecting;
                    } else if (entry.target === bottom) {
                        this.bottomShadow = ! entry.isIntersecting;
                    }
                }
            }, { root: list, threshold: 0 });

            this._shadowObserver.observe(top);
            this._shadowObserver.observe(bottom);
        },

        destroy() {
            // Both of these outlive the component otherwise: an observer holds the node it
            // watches, and a pending timeout fires into a torn-down scope. The house rule for
            // Alpine plugins here exists because of exactly that null-callback class.
            this._observer?.disconnect();
            this._observer = null;
            this._shadowObserver?.disconnect();
            this._shadowObserver = null;

            if (this._announceTimer) {
                clearTimeout(this._announceTimer);
                this._announceTimer = null;
            }
        },

        /** Read the rendered options once. Order is the server's and is never changed. */
        _collect() {
            const list = this.$refs.list;

            this._items = list
                ? Array.from(list.querySelectorAll('[role="option"]')).map((el) => ({
                    el,
                    key: el.dataset.key || '',
                    // Pre-normalized by the server; normalized again here is wasted work.
                    search: (el.dataset.search || '').toLowerCase(),
                    current: el.getAttribute('aria-selected') === 'true',
                }))
                : [];
        },

        /**
         * Fold accents away so a reader who types `munchen` finds `München`.
         *
         * NFD splits a letter into its base and its combining marks; stripping the marks
         * leaves the base. Doing it the other way — a table of replacements — is a list that
         * is wrong for the next language somebody uses.
         */
        _normalize(value) {
            return String(value ?? '')
                .normalize('NFD')
                .replace(/\p{M}/gu, '')
                .toLowerCase()
                .trim();
        },

        /**
         * Does `token` appear in `haystack` as a SUBSEQUENCE — its letters in order, gaps
         * allowed? This is what forgives a dropped letter: `wroker` still walks through
         * `worker-01.example.com`. Linear in the haystack and allocation-free, so it is cheap enough
         * to run over every row on every keystroke.
         */
        _subsequence(haystack, token) {
            let at = 0;

            for (const ch of token) {
                at = haystack.indexOf(ch, at);

                if (at === -1) {
                    return false;
                }

                at += 1;
            }

            return true;
        },

        /**
         * Edit distance, capped: it stops as soon as the row's best possible score exceeds
         * `max`, so a long word against a short token costs almost nothing.
         *
         * Subsequence alone forgives DELETIONS. This is here for the other two ways a reader
         * mistypes — a substituted letter and a transposition — which a subsequence walk
         * cannot see at all.
         */
        _within(a, b, max) {
            if (Math.abs(a.length - b.length) > max) {
                return false;
            }

            let prevPrev = null;
            let prev = Array.from({ length: b.length + 1 }, (_, i) => i);

            for (let i = 1; i <= a.length; i++) {
                const row = [i];
                let best = i;

                for (let j = 1; j <= b.length; j++) {
                    const cost = a[i - 1] === b[j - 1] ? 0 : 1;
                    let d = Math.min(row[j - 1] + 1, prev[j] + 1, prev[j - 1] + cost);

                    // Damerau, and it is the whole reason this is not plain Levenshtein: a
                    // swapped pair of adjacent letters is the commonest typo there is, and
                    // plain edit distance charges TWO for it. At a budget of one, `wroker`
                    // and `exmaple` therefore matched nothing — measured, before this line.
                    if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) {
                        d = Math.min(d, prevPrev[j - 2] + 1);
                    }

                    row[j] = d;
                    best = Math.min(best, d);
                }

                if (best > max) {
                    return false;
                }

                prevPrev = prev;
                prev = row;
            }

            return prev[b.length] <= max;
        },

        /**
         * One token against one row, in three widening steps — cheapest first, and each one
         * only reached because the previous found nothing.
         *
         *   1. substring     — what the reader almost always means
         *   2. subsequence   — a dropped letter
         *   3. edit distance — a wrong or swapped letter, against each word separately
         *
         * The distance budget grows with the token because one wrong letter in three is a
         * different word, while one in ten is a typo. Tokens shorter than three characters
         * skip step 3 entirely: at that length everything is within one edit of everything.
         */
        _matches(haystack, token) {
            if (haystack.includes(token)) {
                return true;
            }

            if (token.length >= 4 && this._subsequence(haystack, token)) {
                return true;
            }

            if (token.length < 3) {
                return false;
            }

            const budget = token.length >= 8 ? 2 : 1;

            return haystack.split(/[\s.\-_/]+/).some((word) => word && this._within(word, token, budget));
        },

        /**
         * Hide what does not match. EVERY token must appear, so `farm 19` narrows rather
         * than widening — a reader adding a word expects fewer results, not more.
         *
         * Matching is fault-tolerant on purpose. A strict substring test answers "is this
         * exactly in there", and a reader typing a hostname from memory is not exact: one
         * missing letter — `wroker` for `worker` — returned NOTHING over a list that had it,
         * which reads as a broken search rather than as a strict one.
         */
        filter() {
            const tokens = this._normalize(this.query).split(/\s+/).filter(Boolean);
            let visible = 0;
            let first = null;

            for (const item of this._items) {
                const match = tokens.length === 0 || tokens.every((t) => this._matches(item.search, t));

                item.el.hidden = ! match;

                if (match) {
                    visible += 1;
                    first ??= item;
                }
            }

            this.visibleCount = visible;
            this._hideEmptyGroups();

            // The active row must always be one the reader can see. Keeping a hidden row
            // active is how Enter activates something nobody was looking at.
            const active = this._items.find((i) => i.key === this.activeKey);

            if (! active || active.el.hidden) {
                this.setActive(first ? first.key : null, false);
            }

            this._announce();
        },

        /** A group whose every row is hidden is a heading over nothing. */
        _hideEmptyGroups() {
            const list = this.$refs.list;

            if (! list) {
                return;
            }

            for (const group of list.querySelectorAll('[role="group"]')) {
                const anyVisible = Array.from(group.querySelectorAll('[role="option"]'))
                    .some((el) => ! el.hidden);

                group.hidden = ! anyVisible;
            }
        },

        setActive(key, scroll = true) {
            this.activeKey = key;

            for (const item of this._items) {
                if (item.key === key) {
                    item.el.setAttribute('data-active', '');
                } else {
                    item.el.removeAttribute('data-active');
                }
            }

            if (! scroll || key === null) {
                return;
            }

            const item = this._items.find((i) => i.key === key);

            // `nearest` and not `center`: the list should move only when it has to, or the
            // whole thing lurches on every arrow press.
            item?.el.scrollIntoView({ block: 'nearest' });
        },

        /** Step through the VISIBLE rows. No wrap — the ends are a useful boundary here. */
        moveActive(delta) {
            const visible = this._items.filter((i) => ! i.el.hidden);

            if (visible.length === 0) {
                return;
            }

            const at = visible.findIndex((i) => i.key === this.activeKey);
            const next = at < 0
                ? (delta > 0 ? 0 : visible.length - 1)
                : Math.min(visible.length - 1, Math.max(0, at + delta));

            this.setActive(visible[next].key);
        },

        moveEdge(end) {
            const visible = this._items.filter((i) => ! i.el.hidden);

            if (visible.length > 0) {
                this.setActive(visible[end ? visible.length - 1 : 0].key);
            }
        },

        /**
         * Follow the active row.
         *
         * Choosing the scope you are already in navigates nowhere — it just closes, because
         * a reader who opens the switcher and picks the current entry has changed their mind,
         * and a full page load to arrive where they already are is a delay that says nothing.
         */
        activateActive() {
            const item = this._items.find((i) => i.key === this.activeKey);

            if (! item || item.el.hidden) {
                return;
            }

            if (item.current) {
                // `close()` comes from the popover's scope, which this one inherits. There
                // is no close EVENT for a popover — the overlay vocabulary pairs show/close
                // for modal, drawer, alert-dialog and toast only — so dispatching one would
                // be shouting a word nothing listens for.
                this.close?.();

                return;
            }

            this._showChosen(item.el);
            item.el.click();
        },

        /**
         * Show the chosen scope in the trigger straight away.
         *
         * Choosing is navigation: the next page renders with the new scope as its current
         * one, and this text is what that page will show. Writing it now removes the stretch
         * where the reader has chosen and the button still names where they came from — on a
         * slow response that stretch is long enough to read as "the click did nothing", which
         * is what it looked like.
         *
         * Reached across the teleport by id rather than by scope, and it is deliberately not
         * a rollback-capable optimistic write: if navigation fails, the page did not change,
         * so neither did anything this label could be wrong about.
         */
        _showChosen(el) {
            const label = el?.dataset.label;
            const target = label && this.idPrefix
                ? document.getElementById(`${this.idPrefix}-current-label`)
                : null;

            if (target) {
                target.textContent = label;
            }
        },

        /** Clicking the row you are on: same reasoning as above, from the pointer. */
        onItemClick(event, isCurrent) {
            if (isCurrent) {
                event.preventDefault();
                this.close?.();

                return;
            }

            this._showChosen(event.currentTarget);
        },

        /**
         * Empty the query when the panel closes.
         *
         * Without this, reopening shows yesterday's search over a list the reader has
         * already forgotten typing into — it reads as a broken list rather than as a
         * remembered one.
         */
        /**
         * Clear the search when the panel closes, driven by the popover's own `open`.
         *
         * The call shape is not incidental. Alpine's CSP build interprets expressions
         * against a narrower grammar that has no statements in it, so the `if (! open)
         * reset()` this replaced was never evaluated there — no error, just a search box
         * that remembered yesterday's query. A call parses under both builds.
         *
         * @param {boolean} open Whether the popover is currently showing.
         */
        syncOpen(open) {
            if (! open) {
                this.reset();
            }
        },

        reset() {
            this.query = '';

            for (const item of this._items) {
                item.el.hidden = false;
            }

            this._hideEmptyGroups();
            this.visibleCount = this._items.length;

            // Open on the current scope, which is where a reader's eye goes anyway.
            const current = this._items.find((i) => i.current);
            this.setActive(current ? current.key : (this._items[0]?.key ?? null));
        },

        /** The id `aria-activedescendant` points at, or empty when nothing is active. */
        activeId() {
            return this.activeKey === null ? '' : `${this.idPrefix}-option-${this.activeKey}`;
        },

        _announce() {
            if (this._announceTimer) {
                clearTimeout(this._announceTimer);
            }

            this._announceTimer = setTimeout(() => {
                this.announcement = this.visibleCount;
                this._announceTimer = null;
            }, this.announceDelay);
        },

        /** Mirrors visibleCount, but only after the pause — this is what the region reads. */
        announcement: 0,
    };
}
