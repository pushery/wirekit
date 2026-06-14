/**
 * WireKit Editor — thin Alpine glue around Tiptap (a PEER DEPENDENCY).
 *
 * Tiptap itself is NOT bundled (same shape as the Chart.js / ApexCharts
 * adapters). The developer installs `@tiptap/core` + `@tiptap/starter-kit`
 * and exposes a `window.tiptapEditor(config)` factory that returns a Tiptap
 * `Editor` instance (see the docs page). When that factory is missing we
 * gracefully fall back to a plain <textarea> + a console error, so a form is
 * never silently broken.
 *
 * Responsibilities: mount Tiptap on the content element, mirror the document
 * into a hidden <input> (debounced) so `wire:model` + native form submit keep
 * working, wire the toolbar commands, and tear the instance down on destroy.
 */
export default function wirekitEditor(config = {}) {
    return {
        editor: null,
        // Bumped on every Tiptap transaction so the toolbar's isActive() bindings
        // re-evaluate reactively (Tiptap's own state is not Alpine-reactive).
        _version: 0,
        _syncTimer: null,
        _format: config.format === 'json' ? 'json' : 'html',
        _editable: config.editable !== false,
        // Character count (soft limit). charCount drives the visible counter; the
        // sr-only announce string is debounced so a screen reader speaks when the
        // user pauses, not on every keystroke. maxLength accepts a number OR a
        // numeric string (from a plain `max-length="500"` Blade attribute).
        charCount: 0,
        charAnnounce: '',
        _announceTimer: null,
        _maxLength: config.maxLength != null && Number.isFinite(Number(config.maxLength))
            ? Number(config.maxLength)
            : null,

        init() {
            // Idempotency guard: Livewire DOM morphing can re-run Alpine's init() on an
            // already-mounted editor. Without this, a second window.tiptapEditor() mounts a
            // duplicate ProseMirror view on the same node — the content renders twice and
            // every toolbar command throws "Applying a mismatched transaction". Bail if a
            // live editor already exists (destroy() nulls it, so a real remount still works).
            if (this.editor) { return; }

            if (typeof window.tiptapEditor !== 'function') {
                this._activateFallback();

                return;
            }

            // Second defensive layer for the Livewire-morph path: Livewire can
            // re-render the markup and KEEP the content node (with its already-
            // mounted ProseMirror view) while Alpine constructs a FRESH component
            // object — so `this.editor` is undefined here and the guard above
            // misses it. Mounting again would put TWO ProseMirror views on one
            // node: the content renders twice and every toolbar command throws
            // "Applying a mismatched transaction" (it dispatches against a stale
            // view). Remove any orphaned view so exactly one fresh view mounts.
            // The canonical complement is `wire:ignore` on the host (so Livewire
            // never morphs the editor subtree) — documented for integrators.
            const host = this.$refs.content;
            if (host && typeof host.querySelectorAll === 'function') {
                // Tiptap APPENDS its view to `element` and never empties it — so the
                // server-rendered seed ([data-wk-editor-seed], the pre-hydration
                // first paint) MUST go before mounting, or the content renders twice
                // (seed on top, editable view below). Stale .ProseMirror views from
                // a Livewire morph (which keeps the DOM but rebuilds the Alpine
                // component) are swept in the same pass.
                host.querySelectorAll('[data-wk-editor-seed], .ProseMirror').forEach((n) => n.remove());
            }

            const created = window.tiptapEditor({
                element: host,
                content: this._initialContent(),
                editable: this._editable,
                extensions: config.extensions || [],
                editorProps: {
                    attributes: {
                        // The editable surface is a multiline textbox (WAI-ARIA textbox pattern).
                        // wk-editor-content is REAL shipped CSS (typography + flex-fill +
                        // outline:none + wrap rules in dist/wirekit.css) — never put Tailwind
                        // utility classes here: Tailwind doesn't scan JS config strings, so
                        // they'd silently not exist in the developer's build (the old
                        // outline-suppressing utility formerly here was exactly that trap — the browser's
                        // default contenteditable outline showed INSIDE the field).
                        role: 'textbox',
                        'aria-multiline': 'true',
                        class: 'wk-editor-content',
                        ...(config.ariaLabel ? { 'aria-label': config.ariaLabel } : {}),
                        ...(config.ariaDescribedby ? { 'aria-describedby': config.ariaDescribedby } : {}),
                        ...(config.ariaInvalid ? { 'aria-invalid': 'true' } : {}),
                    },
                },
                onCreate: () => { this._version++; this._writeOut(); this._updateCount(); },
                onUpdate: () => { this._version++; this._scheduleSync(); this._updateCount(); },
                onSelectionUpdate: () => { this._version++; },
                onTransaction: () => { this._version++; },
            });

            // Mark the Editor raw BEFORE storing it on Alpine state. Alpine
            // deep-proxies component data via its vendored Vue reactivity, and a
            // PROXIED ProseMirror editor builds every command's transaction against
            // the proxied state.doc — a different object identity from the view's
            // real doc — so EVERY command throws "Applying a mismatched transaction"
            // (proven: proxied selectAll() throws, Alpine.raw(editor) works).
            // `__v_skip` is the flag Vue's markRaw() sets and reactive() honors;
            // setting it here protects every integrator no matter what their
            // window.tiptapEditor factory returns. The toolbar's reactivity never
            // depended on the editor being reactive — it's driven by the _version
            // counter (see the on* hooks above). try/catch: a frozen/sealed factory
            // return can't take the flag — fall through rather than break mount.
            if (created && typeof created === 'object') {
                try {
                    Object.defineProperty(created, '__v_skip', { value: true });
                } catch {
                    // frozen/sealed object — proceed un-flagged (factory's concern)
                }
            }
            this.editor = created;

            // Autofocus the Tiptap surface when requested (the `autofocus` prop
            // previously reached ONLY the textarea fallback). Done AFTER the assignment
            // — not in onCreate, which can fire mid-construction while this.editor is
            // still undefined (the same reason _writeOut guards on it) — and
            // factory-independent: it doesn't rely on the window.tiptapEditor factory
            // forwarding a Tiptap `autofocus` option. Guarded for factories that return
            // a minimal stub without the command API.
            if (config.autofocus && this.editor && this.editor.commands && typeof this.editor.commands.focus === 'function') {
                this.editor.commands.focus('end');
            }
        },

        destroy() {
            clearTimeout(this._syncTimer);
            clearTimeout(this._announceTimer);
            if (this.editor) {
                this.editor.destroy();
                this.editor = null;
            }
        },

        // ── Character count (soft limit) ─────────────────────────────

        _updateCount() {
            if (!this.editor) { return; }
            // Prefer the CharacterCount extension's count (grapheme-aware); fall back
            // to plain-text length when the extension isn't installed.
            const cc = this.editor.storage?.characterCount;
            this.charCount = cc && typeof cc.characters === 'function'
                ? cc.characters()
                : this.editor.getText().length;
            this._scheduleAnnounce();
        },

        _scheduleAnnounce() {
            if (this._maxLength === null) { return; }
            // Debounce so the sr-only live region speaks on pause, not per keystroke.
            clearTimeout(this._announceTimer);
            this._announceTimer = setTimeout(() => {
                const remaining = this._maxLength - this.charCount;
                this.charAnnounce = remaining >= 0
                    ? `${remaining} characters remaining`
                    : `${-remaining} characters over the limit`;
            }, 500);
        },

        // Visible counter label + over-limit flag for the bottom-bar bindings.
        get charCountLabel() {
            return this._maxLength !== null ? `${this.charCount} / ${this._maxLength}` : String(this.charCount);
        },
        get isOverLimit() {
            return this._maxLength !== null && this.charCount > this._maxLength;
        },

        // ── Output sync ──────────────────────────────────────────────

        _initialContent() {
            const value = config.value;
            if (this._format === 'json' && typeof value === 'string' && value !== '') {
                try {
                    return JSON.parse(value);
                } catch {
                    return value;
                }
            }

            return value ?? '';
        },

        _scheduleSync() {
            // Debounce so wire:model.live doesn't round-trip on every keystroke.
            clearTimeout(this._syncTimer);
            this._syncTimer = setTimeout(() => this._writeOut(), 200);
        },

        _writeOut() {
            if (!this.editor || !this.$refs.input) {
                return;
            }
            this.$refs.input.value = this._format === 'json'
                ? JSON.stringify(this.editor.getJSON())
                : this.editor.getHTML();
            // input → Livewire wire:model; change → legacy listeners.
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
        },

        // ── Toolbar command dispatch ─────────────────────────────────

        cmd(name) {
            if (!this.editor) {
                return;
            }
            if (name === 'link') {
                this._promptLink();

                return;
            }
            const chain = this.editor.chain().focus();
            const map = {
                bold: () => chain.toggleBold(),
                italic: () => chain.toggleItalic(),
                underline: () => chain.toggleUnderline(),
                strike: () => chain.toggleStrike(),
                code: () => chain.toggleCode(),
                'heading-1': () => chain.toggleHeading({ level: 1 }),
                'heading-2': () => chain.toggleHeading({ level: 2 }),
                'heading-3': () => chain.toggleHeading({ level: 3 }),
                paragraph: () => chain.setParagraph(),
                quote: () => chain.toggleBlockquote(),
                'bullet-list': () => chain.toggleBulletList(),
                'ordered-list': () => chain.toggleOrderedList(),
                'task-list': () => chain.toggleTaskList(),
                'code-block': () => chain.toggleCodeBlock(),
                'horizontal-rule': () => chain.setHorizontalRule(),
                'align-left': () => chain.setTextAlign('left'),
                'align-center': () => chain.setTextAlign('center'),
                'align-right': () => chain.setTextAlign('right'),
                'align-justify': () => chain.setTextAlign('justify'),
                'clear-formatting': () => chain.unsetAllMarks().clearNodes(),
                undo: () => chain.undo(),
                redo: () => chain.redo(),
            };
            const fn = map[name];
            if (fn) {
                try {
                    fn().run();
                } catch (e) {
                    // A command whose Tiptap extension isn't registered throws here —
                    // Underline / TaskList / TextAlign are NOT in StarterKit, so a
                    // `toolbar="full"` editor wired with only StarterKit hits this on an
                    // Underline click. Surface a DX hint instead of letting an uncaught
                    // error break the editor (same "never silently broken" contract as
                    // the missing-factory fallback above).
                    // eslint-disable-next-line no-console
                    console.error(
                        `[wirekit] editor: command "${name}" failed — is its Tiptap `
                        + 'extension installed and registered in window.tiptapEditor? '
                        + 'See https://docs.wirekit.app/components/editor#toolbar-presets',
                        e
                    );
                }
            }
        },

        _promptLink() {
            const previous = this.editor.getAttributes('link').href || '';
            const url = window.prompt('Link URL', previous);
            if (url === null) {
                return; // canceled
            }
            if (url === '') {
                this.editor.chain().focus().extendMarkRange('link').unsetLink().run();

                return;
            }
            // Defense-in-depth: never write a dangerous-scheme href into the document.
            // Sanitize-on-store is the editor's primary contract, but WireKit must not
            // be the code path that introduces a javascript:/data:/vbscript: link in
            // the first place. Denylist (not allowlist) so legitimate schemes —
            // mailto, tel, sms, app deep-links, relative, and anchors — keep working.
            if (this._isDangerousUrl(url)) {
                // eslint-disable-next-line no-console
                console.error(
                    `[wirekit] editor: refused a link with an unsafe URL scheme ("${url}"). `
                    + 'javascript:, data:, and vbscript: URLs are blocked to prevent XSS.'
                );

                return;
            }
            this.editor.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
        },

        // Scheme denylist for _promptLink. Strips the TAB / LF / CR characters a
        // browser ignores when resolving a URL scheme (so `java\tscript:` can't slip
        // through) and leading whitespace, then tests the leading scheme.
        _isDangerousUrl(url) {
            const normalized = String(url).replace(/[\t\n\r]/g, '').trim().toLowerCase();

            return /^(?:javascript|data|vbscript):/.test(normalized);
        },

        // Reactive active-state for the toolbar. Reading `_version` registers it as an
        // Alpine dependency so `:aria-pressed` / `:class` re-evaluate on every transaction.
        isActive(name, attrs = {}) {
            return this._version >= 0 && this.editor ? this.editor.isActive(name, attrs) : false;
        },

        // Reactive history availability — drives the undo/redo buttons' :disabled so a
        // button with nothing to undo/redo reads as disabled instead of a dead no-op
        // Reads
        // `_version` (bumped on every transaction) so the bindings re-evaluate as the
        // history stack changes. Tiptap exposes `editor.can().undo()/redo()` (booleans);
        // `?.` guards a minimal factory return or an editor wired WITHOUT the History
        // extension — in that case there's nothing to undo/redo, so `false` (disabled)
        // is the honest state. `_canRun` centralizes the can()-probe + its guards.
        canUndo() {
            return this._canRun('undo');
        },
        canRedo() {
            return this._canRun('redo');
        },
        _canRun(name) {
            if (this._version < 0 || !this.editor || typeof this.editor.can !== 'function') {
                return false;
            }
            try {
                const can = this.editor.can();

                return typeof can?.[name] === 'function' ? !!can[name]() : false;
            } catch {
                // A can()-probe can throw if the command's extension is absent — treat
                // as "nothing to run" (disabled) rather than letting it break the toolbar.
                return false;
            }
        },

        // ── Tiptap-absent fallback ───────────────────────────────────

        _activateFallback() {
            // One-time DX hint — gate ONLY the console.error behind a module-scoped
            // flag so a page with N Tiptap-less editors logs ONCE, not N times.
            // Mirrors the missing-peer-dependency hint in chart.js / chart-apex.js /
            // map.js (all gate on window.__wirekit_<lib>_missing_warned__). The
            // textarea-fallback DOM work below still runs for EVERY editor — each
            // instance needs its own field un-hidden — so the dedup wraps the log
            // only, never the fallback itself.
            if (typeof window !== 'undefined') {
                window.__wirekit_tiptap_missing_warned__ ??= false;
                if (!window.__wirekit_tiptap_missing_warned__) {
                    window.__wirekit_tiptap_missing_warned__ = true;
                    // eslint-disable-next-line no-console
                    console.error(
                        '[wirekit] editor: window.tiptapEditor is not defined — falling back to a plain '
                        + 'textarea. Install @tiptap/core + @tiptap/starter-kit and expose '
                        + 'window.tiptapEditor(config) (see https://docs.wirekit.app/components/editor).'
                    );
                }
            }
            // The hidden form-field textarea ($refs.input) doubles as the fallback:
            // un-hide it so the user can still edit + submit, and hide the (now dead)
            // Tiptap mount point + toolbar.
            if (this.$refs.input) {
                this.$refs.input.removeAttribute('hidden');
                this.$refs.input.removeAttribute('aria-hidden');
            }
            if (this.$refs.content) {
                this.$refs.content.setAttribute('hidden', '');
            }
            if (this.$refs.toolbar) {
                this.$refs.toolbar.setAttribute('hidden', '');
            }
        },
    };
}
