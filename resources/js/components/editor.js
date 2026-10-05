/**
 * WireKit Editor — thin Alpine glue around a ProseMirror-based rich-text editor
 * (a PEER DEPENDENCY; Tiptap is the recommended engine).
 *
 * The editor engine is NOT bundled (same shape as the Chart.js / ApexCharts
 * adapters). The developer installs their editor (e.g. `@tiptap/core` +
 * `@tiptap/starter-kit`) and exposes a `window.wirekitEditor(config)` factory
 * that returns an `Editor` instance (see the docs page). The legacy factory name
 * `window.tiptapEditor` is still accepted as a deprecated alias — the resolution
 * order lives in `_resolveFactory()`. A factory that is not on the page yet is
 * awaited the way the chart adapters await their library; when none has arrived
 * by the end of the grace period we fall back to a plain <textarea> + a console
 * error, so a form is never silently broken.
 *
 * Responsibilities: mount the engine on the content element, mirror the document
 * into a hidden <input> (debounced) so `wire:model` + native form submit keep
 * working, keep a caller's `x-model` in step in both directions (the `content`
 * accessor below), wire the toolbar commands, and tear the instance down on destroy.
 *
 * The factory name is engine-neutral by design: the glue treats the returned
 * Editor as opaque and only calls the shared ProseMirror-editor interface, so it
 * never hard-couples to one vendor.
 */
import { awaitPeer } from '../utils/await-peer.js';
import { watchFieldsetDisabled } from '../utils/fieldset-disabled.js';
import { onFormReset } from '../utils/form-reset.js';
import { requiredCheckState } from '../utils/required-check.js';
import { keepRaw } from '../utils/keep-raw.js';
import { watchModelEvents } from '../utils/model-events.js';
import { moveRovingFocus } from '../utils/roving-focus.js';
import { pluralize } from '../utils/plural.js';

/**
 * What counts as a toolbar command for focus purposes.
 *
 * `:not([disabled])` is the whole reason this is a selector rather than a node list
 * captured once: undo and redo bind `:disabled` to an empty history stack, so which
 * buttons are focusable changes as the reader types. A disabled button cannot take
 * focus, and stepping onto one would strand the arrow keys on it.
 */
const TOOLBAR_COMMAND = 'button:not([disabled])';

export default function wirekitEditor(config = {}) {
    return {
        // The browser prompt a reader answers when inserting a link. Server-translated;
        // English fallback for a directly-constructed factory.
        _linkPromptLabel: config.linkPromptLabel || 'Link URL',

        editor: null,
        // Bumped on every Tiptap transaction so the toolbar's isActive() bindings
        // re-evaluate reactively (Tiptap's own state is not Alpine-reactive).
        _version: 0,
        // Bumped only when the DOCUMENT changes, not the selection, so the `content`
        // accessor is re-read when there is something new to read and not on every
        // caret move.
        _docVersion: 0,
        // The fallback textarea's input listener, when there is no engine. Released in
        // destroy().
        _fallbackInput: null,
        // Set by the first keystroke in the fallback textarea. A factory that arrives after it
        // finds the reader typing into the plain field, and leaves the field to them.
        _fallbackTyped: false,
        // Stops the wait for a factory that was not on the page at start. Released in destroy().
        _stopAwaitingFactory: null,
        _syncTimer: null,
        // `change` and `blur` on the hidden field for `wire:model.change` and `wire:model.blur`,
        // which listen on that field alone (utils/model-events.js). Armed only once the engine
        // has mounted: the fallback textarea is the field itself, visible, and fires its own.
        // Released in destroy().
        _modelEvents: null,
        // Brings the surface back to the form field when the form is reset (utils/form-reset.js).
        // Released in destroy().
        _stopFormReset: null,
        // Follows the fieldsets around the editor, whose `disabled` locks the surface as it
        // locks the field the document is submitted in (utils/fieldset-disabled.js). Released
        // in destroy().
        _stopFieldsetWatch: null,
        // `requiredMessage` and `onRequiredInvalid()`: a required editor stops an empty submit
        // (utils/required-check.js).
        ...requiredCheckState(),
        // The visible label and its two listeners. Released in destroy().
        _label: null,
        _onLabelPress: null,
        _onLabelClick: null,
        _format: config.format === 'json' ? 'json' : 'html',
        _editable: config.editable !== false,
        // Character count (soft limit). charCount drives the visible counter; the
        // sr-only announce string is debounced so a screen reader speaks when the
        // user pauses, not on every keystroke. maxLength accepts a number OR a
        // numeric string (from a plain `max-length="500"` Blade attribute).
        charCount: 0,
        charAnnounce: '',
        // The announced sentence's plural forms and the locale that picks between them, handed
        // in translated from Blade. Empty when mounted by hand, which keeps the English sentence.
        _remainingPhrases: config.remainingPhrases || {},
        _overPhrases: config.overPhrases || {},
        _locale: config.locale || 'en',
        _announceTimer: null,
        // The id of the message an empty required editor shows under its frame; see
        // _syncRequiredInvalid().
        _requiredMessageId: typeof config.requiredMessageId === 'string' ? config.requiredMessageId : null,
        _maxLength: config.maxLength != null && Number.isFinite(Number(config.maxLength))
            ? Number(config.maxLength)
            : null,

        init() {
            // Idempotency guard: Livewire DOM morphing can re-run Alpine's init() on an
            // already-mounted editor. Without this, a second factory call mounts a
            // duplicate ProseMirror view on the same node — the content renders twice and
            // every toolbar command throws "Applying a mismatched transaction". Bail if a
            // live editor already exists (destroy() nulls it, so a real remount still works),
            // and while a wait for the factory is running, which would mount a second time.
            if (this.editor || this._stopAwaitingFactory) { return; }

            this._wireLabel();
            this._stopFormReset = onFormReset(this.$root, () => this.$refs?.input, () => this._restore());

            // Resolve the engine factory: canonical window.wirekitEditor first, then the
            // deprecated window.tiptapEditor alias (with a one-time hint).
            if (this._resolveFactory()) {
                this._takeOver(false);

                return;
            }

            // No factory yet. An application may load its editor only on the pages that have
            // one, and after a `wire:navigate` visit that script runs after Alpine has started
            // this component. So the editor keeps asking, the way the charts and the map wait
            // for their library: the server-rendered seed stays on screen until the grace
            // period ends, then the plain textarea takes over, and a factory that arrives even
            // later still replaces it unless the reader has started using it.
            this._stopAwaitingFactory = awaitPeer({
                isReady: () => this._resolveFactory() !== null,
                onReady: () => {
                    this._stopAwaitingFactory = null;
                    this._takeOver(true);
                },
                onMissing: () => this._activateFallback(),
            });
        },

        /**
         * Wire the visible label to the surface it names.
         *
         * The label sits outside this component and names the surface by reference: the surface
         * is built here, in the browser, and is not an element a `for` can reach. So a click on
         * the label is wired here to focus it, as a label does for a field. A press on it keeps
         * the focus where it is when the reader is already writing: the blur that would follow
         * commits and leaves, and the click would only put the focus back.
         */
        _wireLabel() {
            if (!config.ariaLabelledby || typeof document === 'undefined') {
                return;
            }

            const label = document.getElementById(config.ariaLabelledby);

            if (!label || typeof label.addEventListener !== 'function') {
                return;
            }

            this._label = label;
            this._onLabelPress = (event) => {
                const surface = this._surface();

                if (surface && surface.contains(document.activeElement)) {
                    event.preventDefault();
                }
            };
            this._onLabelClick = () => this._focusSurface();
            label.addEventListener('mousedown', this._onLabelPress);
            label.addEventListener('click', this._onLabelClick);
        },

        /** What a reader types into: the engine's surface, or the plain field without one. */
        _surface() {
            if (this.editor) {
                return this.$refs.content?.querySelector?.('[contenteditable="true"]') ?? null;
            }

            const field = this.$refs.input;

            return field && !field.hidden ? field : null;
        },

        /** Focus the surface, if there is one a reader can type into: a read-only editor has none. */
        _focusSurface() {
            const surface = this._surface();

            if (!surface) {
                return;
            }

            if (this.editor && this.editor.commands && typeof this.editor.commands.focus === 'function') {
                this.editor.commands.focus();

                return;
            }

            surface.focus?.();
        },

        /**
         * Mount the engine once its factory is on the page.
         *
         * The plain textarea stays when the reader has typed into it or is in it: their text and
         * their caret are in that field, and the editor would take both away. Otherwise the
         * fallback is undone and the engine mounts with what the form field holds, which is the
         * server's value unless a bound `x-model` set another while the factory was on its way.
         *
         * @param {boolean} late  the factory arrived after this component started
         */
        _takeOver(late) {
            const factory = this._resolveFactory();

            if (!factory || this.editor) {
                return;
            }

            if (this._fallbackInput) {
                const field = this.$refs.input;
                const focused = typeof document !== 'undefined' && field != null && document.activeElement === field;

                if (this._fallbackTyped || focused) {
                    return;
                }

                this._deactivateFallback();
            }

            const content = late && this.$refs.input && typeof this.$refs.input.value === 'string'
                ? this._parseContent(this.$refs.input.value)
                : this._initialContent();

            this._mount(factory, content, late);
        },

        /**
         * Mount the engine on the content element with the given document.
         *
         * @param {Function} factory  window.wirekitEditor, or its deprecated alias
         * @param {string|Object} content  HTML, or the parsed JSON of a JSON editor
         * @param {boolean} late  the factory arrived after this component started
         */
        _mount(factory, content, late) {
            // Second defensive layer for the Livewire-morph path: Livewire can
            // re-render the markup and KEEP the content node (with its already-
            // mounted ProseMirror view) while Alpine constructs a FRESH component
            // object — so `this.editor` is undefined here and the guard in init()
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

            const created = factory({
                element: host,
                content,
                editable: this._editable,
                extensions: config.extensions || [],
                // `placeholder` is passed on to the factory: the Blade puts it in the Alpine
                // config, and the integration snippet the docs tell developers to paste reads
                // `config.placeholder` on the object handed to their factory, which is this one.
                placeholder: config.placeholder ?? null,
                editorProps: {
                    attributes: {
                        // What the developer wrote for the field the reader types into
                        // (`inputmode`, `enterkeyhint`, `autocapitalize`, `spellcheck`): the
                        // on-screen keyboard reads it on this surface, never on the wrapper.
                        // First, so none of it can replace a key below.
                        ...(config.fieldAttributes || {}),
                        // The editable surface is a multiline textbox (WAI-ARIA textbox pattern).
                        // wk-editor-content is REAL shipped CSS (typography + flex-fill +
                        // outline:none + wrap rules in dist/wirekit.css) — never put Tailwind
                        // utility classes here: Tailwind doesn't scan JS config strings, so
                        // they'd silently not exist in the developer's build, and an
                        // outline-suppressing utility here would leave the browser's default
                        // contenteditable outline showing inside the field.
                        role: 'textbox',
                        'aria-multiline': 'true',
                        class: 'wk-editor-content',
                        ...(config.ariaLabelledby ? { 'aria-labelledby': config.ariaLabelledby } : {}),
                        ...(config.ariaLabel ? { 'aria-label': config.ariaLabel } : {}),
                        ...(config.ariaDescribedby ? { 'aria-describedby': config.ariaDescribedby } : {}),
                        ...(config.ariaInvalid ? { 'aria-invalid': 'true' } : {}),
                        ...(config.ariaRequired ? { 'aria-required': 'true' } : {}),
                        ...(config.ariaDisabled ? { 'aria-disabled': 'true' } : {}),
                    },
                },
                // Nothing is written to the field on load. The engine's serialization of the
                // stored value is not always the stored value (`<b>` for `<strong>`, a table an
                // older engine did not know, whitespace), and writing it here would fire `input`,
                // which `wire:model` takes as the reader's edit: the next save would store the
                // engine's version of a document nobody had touched, and a plain form would post
                // it the same way. The field keeps the server's bytes until the first real edit
                // writes it.
                onCreate: () => { this._version++; this._updateCount(); },
                // The commit boundary for rich text is LEAVING the editor.
                //
                // Not `onUpdate`, and not the `change` this component emits from
                // it: that fires on a 200ms debounce, so committing there would
                // be a request every fifth of a second while someone types — a
                // timer wearing an event's name, and the boundary has to be a
                // real event. Blur is the moment the writing stopped.
                onBlur: () => { this._flushSync(); this._commitOptimistic(); },
                onUpdate: () => { this._version++; this._docVersion++; this._scheduleSync(); this._updateCount(); },
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
            // editor factory returns. The toolbar's reactivity never
            // depended on the editor being reactive — it's driven by the _version
            // counter (see the on* hooks above). try/catch: a frozen/sealed factory
            // return can't take the flag and is stored as it is.
            this.editor = keepRaw(created);

            // Leaving writes a pending sync out first: the field follows the document 200 ms
            // late, and a press outside comes before the editor's own blur.
            this._modelEvents = watchModelEvents(this.$root, () => this.$refs?.input, {
                beforeLeave: () => this._flushSync(),
            });

            // Once the engine exists, since the lock is its own: the fallback textarea is a
            // native control, which a disabled fieldset locks by itself.
            this._stopFieldsetWatch ??= watchFieldsetDisabled(this.$root, (locked) => this._lockByFieldset(locked));

            // Autofocus the Tiptap surface when requested, so the `autofocus` prop
            // reaches it and not only the textarea fallback. Done AFTER the assignment
            // — not in onCreate, which can fire mid-construction while this.editor is
            // still undefined (the same reason _writeOut guards on it) — and
            // factory-independent: it doesn't rely on the editor factory
            // forwarding a Tiptap `autofocus` option. Guarded for factories that return
            // a minimal stub without the command API.
            // A late mount takes the focus only from nobody: by then the reader may be in another
            // field, and a focus that moves on its own takes their place in it.
            const focusIsFree = !late || typeof document === 'undefined'
                || document.activeElement == null || document.activeElement === document.body;

            if (config.autofocus && focusIsFree && this.editor && this.editor.commands && typeof this.editor.commands.focus === 'function') {
                this.editor.commands.focus('end');
            }
        },

        destroy() {
            this._stopAwaitingFactory?.();
            this._stopAwaitingFactory = null;
            if (this._label) {
                this._label.removeEventListener('mousedown', this._onLabelPress);
                this._label.removeEventListener('click', this._onLabelClick);
                this._label = null;
            }
            this._modelEvents?.dispose();
            this._modelEvents = null;
            this._stopFormReset?.();
            this._stopFormReset = null;
            this._stopFieldsetWatch?.();
            this._stopFieldsetWatch = null;
            clearTimeout(this._syncTimer);
            this._syncTimer = null;
            clearTimeout(this._announceTimer);
            if (this._fallbackInput) {
                this.$refs.input?.removeEventListener?.('input', this._fallbackInput);
                this._fallbackInput = null;
            }
            if (this.editor) {
                this.editor.destroy();
                this.editor = null;
            }
        },

        /**
         * Lock or free the surface for a disabled fieldset around the editor.
         *
         * An editor the `editable` or `disabled` prop already locks is left as it is. The update
         * the engine emits by default is suppressed: a lock is not an edit, and that update
         * writes the field, which `wire:model` would take as the reader's.
         *
         * @param {boolean} locked
         */
        _lockByFieldset(locked) {
            const editor = this.editor;

            // Also when the engine already stands where the fieldset puts it, as on the first report
            // for an editor no fieldset locks: setting it again would update the view for nothing.
            if (! this._editable || ! editor || typeof editor.setEditable !== 'function' || editor.isEditable === ! locked) {
                return;
            }

            editor.setEditable(! locked, false);

            const surface = editor.view?.dom;

            if (surface && typeof surface.setAttribute === 'function') {
                if (locked) {
                    surface.setAttribute('aria-disabled', 'true');
                } else {
                    surface.removeAttribute('aria-disabled');
                }
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
                const phrases = remaining >= 0 ? this._remainingPhrases : this._overPhrases;
                const count = Math.abs(remaining);

                this.charAnnounce = Object.keys(phrases).length > 0
                    ? pluralize(phrases, count, this._locale)
                    : (remaining >= 0 ? `${count} characters remaining` : `${count} characters over the limit`);
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
            return this._parseContent(config.value ?? '');
        },

        /**
         * A serialized value turned into what the engine accepts: the object for a JSON
         * editor, the string for an HTML one. A JSON string that does not parse is handed
         * over as it is, which is what the first mount always did.
         */
        _parseContent(value) {
            if (this._format === 'json' && typeof value === 'string' && value !== '') {
                try {
                    return JSON.parse(value);
                } catch {
                    return value;
                }
            }

            return value;
        },

        /** The document in the configured format: HTML, or JSON as a string. */
        _serialize() {
            return this._format === 'json'
                ? JSON.stringify(this.editor.getJSON())
                : this.editor.getHTML();
        },

        _scheduleSync() {
            // Debounce so wire:model.live doesn't round-trip on every keystroke.
            clearTimeout(this._syncTimer);
            this._syncTimer = setTimeout(() => {
                this._syncTimer = null;
                this._writeOut();
            }, 200);
        },

        /**
         * Write a pending sync out at once.
         *
         * The form field follows the document 200 ms late, and leaving the editor is how a
         * reader gets to a submit button, by pointer or by Tab. Without this, a submit right
         * after the last change sent the text from before it. Only a pending sync is written,
         * so leaving an editor that did not change dispatches no `input`.
         */
        _flushSync() {
            if (this._syncTimer === null) {
                return;
            }

            clearTimeout(this._syncTimer);
            this._syncTimer = null;
            this._writeOut();
        },

        _writeOut() {
            if (!this.editor || !this.$refs.input) {
                return;
            }
            this.$refs.input.value = this._serialize();
            // input → Livewire wire:model; change → `wire:model.change` and legacy listeners,
            // through the model events so leaving the editor does not fire it a second time.
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));

            if (this._modelEvents) {
                this._modelEvents.commit();
            } else {
                this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },

        // ── Two-way x-model ──────────────────────────────────────────

        /**
         * The document as the form field carries it (HTML, or a JSON string). A caller's
         * `x-model` binds to this through `x-modelable`.
         *
         * Without it an `x-model` here was a plain Alpine model on a `div`, listening to
         * every `input` event that bubbled up. The editing surface's own input events are
         * among them, and Alpine read `event.target.value`, which is `undefined` on a
         * contenteditable: the bound value became `undefined` on every keystroke until
         * the debounced sync wrote HTML again. Nothing flowed the other way either, so a
         * value set from outside (a cancel restoring a draft) never reached the surface.
         * `x-modelable` removes that listener and routes both directions through here.
         *
         * Read from the engine rather than from the debounced form field, so a value read
         * right after a keystroke (a commit on a shortcut, a click on a save button)
         * contains that keystroke.
         */
        get content() {
            // Read for the dependency alone: the binding re-reads this accessor when the
            // document changes, and not on every caret move.
            this._docVersion;

            return this._currentContent();
        },

        set content(value) {
            const next = value == null ? '' : String(value);

            if (next === this._currentContent()) {
                return;
            }

            // The document was built from exactly this value and has not changed since.
            // Alpine hands the bound value in once when the binding starts, and applying
            // it again would re-parse the document on every page load for nothing.
            if (this._docVersion === 0 && next === String(config.value ?? '')) {
                return;
            }

            this._applyContent(next);
        },

        _currentContent() {
            if (this.editor) {
                return this._serialize();
            }

            // Without an engine the form field is the editor.
            if (this.$refs.input && typeof this.$refs.input.value === 'string') {
                return this.$refs.input.value;
            }

            return String(config.value ?? '');
        },

        /**
         * Replace the document with a value set from outside.
         *
         * Not an undo step: an undo straight after a cancel would bring back exactly
         * what the cancel discarded. The form field follows at once, so a native submit
         * and a `wire:model` on the same editor agree with what the surface shows.
         */
        _applyContent(next) {
            if (!this.editor) {
                if (this.$refs.input) {
                    this.$refs.input.value = next;
                }

                return;
            }

            const content = this._parseContent(next);

            // Guarded like every other engine call here, for a factory that returns a
            // minimal stub rather than a full editor.
            if (typeof this.editor.chain === 'function') {
                this.editor.chain().setMeta('addToHistory', false).setContent(content).run();
            } else if (typeof this.editor.commands?.setContent === 'function') {
                this.editor.commands.setContent(content);
            } else {
                return;
            }

            this._docVersion++;
            this._writeOut();
            this._updateCount();
        },

        /**
         * After a form reset, the surface shows the document the form field holds again.
         *
         * The field is a native textarea, and the reset has put it back to the document the page
         * started with; the surface kept the reader's text. Not an undo step, as in
         * _applyContent(), and silent: the engine reports the new document as an edit, which
         * schedules a write-out with its events, and a reset changes a native field without one.
         * Without an engine the textarea is the editor, and the count follows it.
         */
        _restore() {
            const field = this.$refs?.input;

            if (this.editor && field && typeof field.value === 'string') {
                const content = this._parseContent(field.value);

                if (typeof this.editor.chain === 'function') {
                    this.editor.chain().setMeta('addToHistory', false).setContent(content).run();
                } else if (typeof this.editor.commands?.setContent === 'function') {
                    this.editor.commands.setContent(content);
                } else {
                    return;
                }

                clearTimeout(this._syncTimer);
                this._syncTimer = null;
                this._docVersion++;
            }

            this._updateCount();
            this.requiredMessage = '';
        },

        /**
         * What the required check reads: empty exactly while the document holds no text. Read
         * through `_docVersion`, which every edit and every keystroke in the plain field moves.
         */
        get requiredValue() {
            this._docVersion;

            if (this.editor) {
                return this.editor.isEmpty === true ? '' : '1';
            }

            const field = this.$refs?.input;

            return field && typeof field.value === 'string' && field.value.trim() !== '' ? '1' : '';
        },

        /** The surface a reader types into, which is where an empty required editor sends the focus. */
        _focusRequiredControl() {
            this._focusSurface();
        },

        /**
         * The state of an empty required editor, written where no binding reaches: the message under
         * the frame, outside this component's scope, and the invalid state on the textbox the engine
         * built, which takes its attributes only at mount. The plain field, when it is the editor,
         * carries a binding of its own. Run by an effect, so it follows the message and the document.
         */
        _syncRequiredInvalid() {
            const invalid = this.requiredMessage !== '' && this.requiredValue === '';
            const message = this._requiredMessageId && typeof document !== 'undefined'
                ? document.getElementById(this._requiredMessageId)
                : null;

            if (message) {
                message.textContent = invalid ? this.requiredMessage : '';
                message.hidden = ! invalid;
            }

            const surface = this.editor ? this._surface() : null;

            if (! surface) {
                return;
            }

            if (invalid) {
                surface.setAttribute('aria-invalid', 'true');
            } else {
                surface.removeAttribute('aria-invalid');
            }
        },

        /**
         * Hand the content to the optimistic layer, if one is nested here.
         *
         * Reads the value through `_serialize()`, the same as _writeOut and the
         * `content` accessor: a JSON editor must not commit HTML, and three copies
         * of the format switch would drift apart.
         *
         * No `mark()`: this component takes `failure: 'keep'`, so a refusal never
         * writes back and the baseline is never read.
         *
         * `run` is looked up rather than assumed — without the layer this
         * component behaves exactly as before, down to the byte.
         */
        _commitOptimistic() {
            if (typeof this.run !== 'function' || !this.editor) {
                return;
            }

            this.run(this._serialize());
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
                    // TaskList / TextAlign are NOT in StarterKit (and on Tiptap 2 neither
                    // is Underline), so an `align-*` or `task-list` button wired with only
                    // StarterKit hits this on a click. Surface a DX hint instead of letting
                    // an uncaught error break the editor (same "never silently broken"
                    // contract as the missing-factory fallback above).

                    console.error(
                        `[wirekit] editor: command "${name}" failed — is its editor `
                        + 'extension installed and registered in window.wirekitEditor? '
                        + 'See https://docs.wirekit.app/components/editor#toolbar-presets',
                        e
                    );
                }
            }
        },

        _promptLink() {
            const previous = this.editor.getAttributes('link').href || '';
            const url = window.prompt(this._linkPromptLabel, previous);
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
        // button with nothing to undo/redo reads as disabled instead of a dead no-op. Reads
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

        // ── Toolbar roving focus ─────────────────────────────────────

        /**
         * Move focus along the toolbar's commands, and take the tab stop with it.
         *
         * `role="toolbar"` promises a reader that Tab reaches the group ONCE and the
         * arrow keys move inside it. The template renders the first enabled command
         * with `tabindex="0"` and every other one with `-1`, which is one half of that
         * promise; without this method the other commands are reachable by pointer
         * alone, which is worse than the many tab stops it replaced.
         *
         * The buttons are read out of the DOM on every press rather than counted once
         * at init. The vocabulary is server-rendered and Livewire REPLACES the markup
         * on every round trip, so an index captured earlier would survive the morph and
         * point into a list that no longer exists — and it would do it quietly, landing
         * focus on the wrong command or on nothing.
         *
         * The tab stop moves only when focus actually did. A press that moved nothing
         * (focus sitting outside the toolbar after a morph) must not hand the group's
         * single tab stop to whichever command happens to be first.
         *
         * @param {KeyboardEvent} event  keydown, bound on the toolbar container
         * @param {string} direction     'next' | 'prev' | 'first' | 'last'
         */
        focusToolbarCommand(event, direction) {
            const root = event?.currentTarget;

            if (! moveRovingFocus(root, direction, TOOLBAR_COMMAND)) {
                return;
            }

            const focused = document.activeElement;

            root.querySelectorAll(TOOLBAR_COMMAND).forEach((button) => {
                button.setAttribute('tabindex', button === focused ? '0' : '-1');
            });
        },

        // ── Engine factory resolution ────────────────────────────────

        // Resolve the developer-supplied editor factory. The contract name is
        // engine-neutral: `window.wirekitEditor` is canonical; `window.tiptapEditor`
        // is a deprecated alias that keeps working, and no removal is scheduled.
        // A one-time console.info nudges old-name integrators to rename,
        // gated on a window flag so a page with N editors hints ONCE, not N times.
        // (No collision with the `wirekitEditor` Alpine.data component: that name lives
        // in Alpine's registry, never on `window`. The bundles do put a few names on
        // `window` — `wirekitPosition`, `Alpine` in the Alpine build, and the one-shot
        // `__wirekit*` diagnostic flags — but none of them is `wirekitEditor`, so this
        // lookup only ever finds a factory the developer defined.)
        // Returns the factory function, or null when neither global is callable.
        _resolveFactory() {
            if (typeof window === 'undefined') {
                return null;
            }
            if (typeof window.wirekitEditor === 'function') {
                return window.wirekitEditor;
            }
            if (typeof window.tiptapEditor === 'function') {
                window.__wirekit_editor_alias_warned__ ??= false;
                if (!window.__wirekit_editor_alias_warned__) {
                    window.__wirekit_editor_alias_warned__ = true;

                    console.info(
                        '[wirekit] editor: window.tiptapEditor is a deprecated alias — rename your '
                        + 'factory to window.wirekitEditor. The old name keeps working, and no '
                        + 'removal is scheduled.'
                    );
                }

                return window.tiptapEditor;
            }

            return null;
        },

        // ── Engine-absent fallback ───────────────────────────────────

        _activateFallback() {
            // One-time DX hint — gate ONLY the console.error behind a module-scoped
            // flag so a page with N engine-less editors logs ONCE, not N times.
            // Mirrors the missing-peer-dependency hint in chart.js / chart-apex.js /
            // map.js (all gate on window.__wirekit_<lib>_missing_warned__). The
            // textarea-fallback DOM work below still runs for EVERY editor — each
            // instance needs its own field un-hidden — so the dedup wraps the log
            // only, never the fallback itself.
            if (typeof window !== 'undefined') {
                window.__wirekit_editor_missing_warned__ ??= false;
                if (!window.__wirekit_editor_missing_warned__) {
                    window.__wirekit_editor_missing_warned__ = true;

                    console.error(
                        '[wirekit] editor: no editor factory defined — falling back to a plain '
                        + 'textarea. Install a ProseMirror editor (e.g. @tiptap/core + @tiptap/starter-kit) '
                        + 'and expose window.wirekitEditor(config) (see https://docs.wirekit.app/components/editor).'
                    );
                }
            }
            // The hidden form-field textarea ($refs.input) doubles as the fallback:
            // un-hide it so the user can still edit + submit, and hide the (now dead)
            // Tiptap mount point + toolbar.
            if (this.$refs.input) {
                this.$refs.input.removeAttribute('hidden');
                this.$refs.input.removeAttribute('aria-hidden');

                // The form field is the editor now, so what is typed into it is the
                // document. `x-modelable` removed the model's own input listener, so
                // without this a bound value would never move off its first value.
                if (typeof this.$refs.input.addEventListener === 'function' && !this._fallbackInput) {
                    this._fallbackInput = () => { this._fallbackTyped = true; this._docVersion++; };
                    this.$refs.input.addEventListener('input', this._fallbackInput);
                }
            }
            // The surface steps aside only for a field that takes its place. A read-only editor
            // renders no form field, and its host holds the document the server rendered, which
            // is then all the reader has.
            if (this.$refs.content && this.$refs.input) {
                this.$refs.content.setAttribute('hidden', '');
            }
            if (this.$refs.toolbar) {
                this.$refs.toolbar.setAttribute('hidden', '');
            }
        },

        /**
         * Undo the fallback for a factory that arrived late: the form field is hidden again and
         * goes back to following the engine, and the editing surface and the toolbar return.
         */
        _deactivateFallback() {
            const field = this.$refs.input;

            if (field) {
                if (this._fallbackInput) {
                    field.removeEventListener?.('input', this._fallbackInput);
                }

                field.setAttribute?.('hidden', '');
                field.setAttribute?.('aria-hidden', 'true');
            }

            this._fallbackInput = null;
            this.$refs.content?.removeAttribute?.('hidden');
            this.$refs.toolbar?.removeAttribute?.('hidden');
        },
    };
}
