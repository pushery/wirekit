/**
 * Server search for a listbox control: `multi-select` and `combobox` with `server` set.
 *
 * In the browser these controls filter the options they were rendered with. A catalog of
 * thousands of records cannot be rendered into the page, so in server mode the typed text goes
 * to the application instead and the options ARE the application's results:
 *
 *   - the text is sent as a `search-change` event once typing settles (`searchDebounce`), and
 *     only when it is long enough to be worth a query (`searchMinLength`). Shorter text is sent
 *     once as '' so the application can go back to whatever it shows before a search.
 *   - the options follow the server: Livewire patches the `data-wk-server-options` attribute on
 *     every render, and the component takes the new list from it. Options in `x-data` are read
 *     once, when Alpine starts the element, so they never could.
 *   - every option the component has been given is remembered by value. A chosen value keeps
 *     its label after a new search has replaced the list it was chosen from; without that a
 *     pill showed the raw id.
 *   - a search the component sent is tracked until the Livewire request carrying it comes back,
 *     the same way `data-table` tracks its sort and search, so the panel can say it is waiting.
 *   - a search the server refused or that was lost on the network says so while its text is in
 *     the field, and can be sent again: the same text no longer counts as asked.
 *
 * Plain fields and methods only, so the object can be spread into a component: a spread reads
 * a getter once and copies the value, which would freeze it.
 *
 * @param {Object}  config
 * @param {boolean} [config.server]           whether the options come from the server
 * @param {number}  [config.searchMinLength]  characters before the text is sent
 * @param {number}  [config.searchDebounce]   milliseconds of quiet before the text is sent
 * @param {Object}  [config.searchTexts]      the translated sentences, see searchNote()
 */
import { observeServerValue } from './server-value.js';
import { sameValue } from './same-value.js';
import { afterRenderFrame } from './commit-end.js';

export const WK_SERVER_OPTIONS_ATTRIBUTE = 'data-wk-server-options';

/** The text worth sending for what the reader typed: '' while it is shorter than `min`. */
export function effectiveQuery(text, min = 1) {
    const trimmed = String(text ?? '').trim();

    return trimmed.length >= Math.max(1, min) ? trimmed : '';
}

export function serverSearchState(config = {}) {
    const texts = config.searchTexts && typeof config.searchTexts === 'object' ? config.searchTexts : {};
    const min = Number(config.searchMinLength);
    const debounce = Number(config.searchDebounce);

    return {
        _server: config.server === true,
        _searchMin: Number.isInteger(min) && min > 0 ? min : 1,
        _searchDebounce: Number.isInteger(debounce) && debounce >= 0 ? debounce : 300,
        _searchTexts: {
            searching: String(texts.searching ?? ''),
            prompt: String(texts.prompt ?? ''),
            tooShort: String(texts.tooShort ?? ''),
            truncated: String(texts.truncated ?? ''),
            empty: String(texts.empty ?? ''),
            failed: String(texts.failed ?? ''),
        },
        _searchTimer: null,
        // The query the server's options answer: '' on the first render, then whatever was
        // sent last. Only a change of it is sent, so typing a space or a letter below the
        // minimum costs no request.
        _sentQuery: '',
        // The server cut its results at a limit of its own.
        _truncated: false,
        // value -> option, for every option the component has been given.
        _known: {},
        // The query the options on screen answer, and the one whose search failed, until a new
        // search goes out. The text typed last, as it would be sent.
        _answeredQuery: '',
        _failedQuery: null,
        _typedQuery: '',
        // payload of a Livewire message -> the query it carries, for the searches this sent.
        _searchPayloads: null,
        _unhookSearchFailure: null,
        // Round trips carrying a search this component sent.
        _searchesOut: 0,
        _expectingSearch: false,
        _expectingSearchTimer: null,
        _unhookSearchCommit: null,
        _stopFollowingOptions: null,
        _serverOptionsRaw: null,

        /**
         * Start following the server: read the options the page was rendered with and subscribe
         * to the carrier and to Livewire's commits.
         *
         * @param {(options: Array) => void} apply  installs a new list in the component
         */
        _startServerSearch(apply) {
            if (! this._server) {
                return;
            }

            const root = this.$root;
            const read = (raw) => {
                if (raw === null || raw === this._serverOptionsRaw) {
                    return;
                }

                let payload;

                try {
                    payload = JSON.parse(raw);
                } catch {
                    return;
                }

                this._serverOptionsRaw = raw;
                const options = Array.isArray(payload?.options) ? payload.options : [];
                this._truncated = payload?.truncated === true;
                this._remember(options);
                apply(options);
            };

            read(root?.getAttribute?.(WK_SERVER_OPTIONS_ATTRIBUTE) ?? null);
            this._stopFollowingOptions = observeServerValue(root, read, WK_SERVER_OPTIONS_ATTRIBUTE);

            if (typeof window !== 'undefined' && window.Livewire?.hook) {
                this._searchPayloads = new WeakMap();
                this._unhookSearchCommit = window.Livewire.hook('commit', ({ component, commit, succeed, fail, respond }) => {
                    // The commit that carries a search this component just sent: the first one
                    // of the Livewire component it sits in. Any other commit is not ours to wait on.
                    if (! this._expectingSearch || component?.el?.contains?.(root) !== true) {
                        return;
                    }

                    this._expectingSearch = false;
                    clearTimeout(this._expectingSearchTimer);
                    this._expectingSearchTimer = null;
                    this._searchesOut++;

                    const carried = this._sentQuery;

                    if (commit && typeof commit === 'object') {
                        this._searchPayloads.set(commit, carried);
                    }

                    succeed(() => {
                        this._answeredQuery = carried;

                        if (this._failedQuery === carried) {
                            this._failedQuery = null;
                        }
                    });

                    // Once per commit, whichever signal comes first.
                    let ended = false;
                    const back = () => {
                        if (ended) {
                            return;
                        }

                        ended = true;
                        queueMicrotask(() => {
                            this._searchesOut = Math.max(0, this._searchesOut - 1);
                        });
                    };

                    // An error or a canceled request ends the wait just as a response does.
                    succeed(back);
                    fail(back);

                    // So does a request that never came back: Livewire hands a network failure
                    // to neither of the two above, only to `respond`, which ends every commit. A
                    // response still ends the wait at its render, through `succeed`, as before.
                    respond?.(() => afterRenderFrame(back));
                });
            }

            // Which search failed: the legacy hook runs `fail` for a cancel as well, and nothing
            // for a request lost on the network, while a message interceptor (Livewire 4.0 on)
            // tells an error and a network failure apart from a cancel. A message superseded by
            // a newer one only finishes, and is no failure either.
            if (typeof window !== 'undefined' && typeof window.Livewire?.interceptMessage === 'function') {
                this._unhookSearchFailure = window.Livewire.interceptMessage(({ message, onError, onFailure }) => {
                    const failed = () => {
                        const payload = message?.payload;

                        if (payload && this._searchPayloads?.has(payload)) {
                            this._searchFailed(this._searchPayloads.get(payload));
                        }
                    };

                    onError?.(failed);
                    onFailure?.(failed);
                });
            }
        },

        /**
         * A search the server refused, or one lost on the network: say so while its text is in
         * the field, and let the same text be sent again, since nothing answered it.
         *
         * @param {string} query  the query the failed search carried
         */
        _searchFailed(query) {
            this._failedQuery = query;

            if (this._sentQuery === query) {
                this._sentQuery = this._answeredQuery;
            }
        },

        /** Whether the search for this query failed and nothing has been sent since. */
        _searchFailedFor(query) {
            return this._failedQuery !== null && this._failedQuery === query;
        },

        _stopServerSearch() {
            clearTimeout(this._searchTimer);
            this._searchTimer = null;
            clearTimeout(this._expectingSearchTimer);
            this._expectingSearchTimer = null;
            this._stopFollowingOptions?.();
            this._stopFollowingOptions = null;
            this._unhookSearchCommit?.();
            this._unhookSearchCommit = null;
            this._unhookSearchFailure?.();
            this._unhookSearchFailure = null;
        },

        /** Remember the options by value, so a chosen one keeps its label after the list moves on. */
        _remember(options) {
            for (const option of Array.isArray(options) ? options : []) {
                if (option && option.value !== undefined && option.value !== null) {
                    this._known[String(option.value)] = option;
                }
            }
        },

        /**
         * The option a value was given with, from the current list or an earlier one. A number
         * finds the option whose text it is, as a value bound to an `int` property arrives.
         */
        _knownOption(value, current = []) {
            return current.find((o) => sameValue(o.value, value)) ?? this._known[String(value)] ?? null;
        },

        /**
         * The reader typed: send the text once it settles, if it asks the server something new.
         *
         * @param {string} text  what the field holds, or '' for "no search"
         */
        _queueSearch(text) {
            if (! this._server) {
                return;
            }

            this._typedQuery = effectiveQuery(text, this._searchMin);
            clearTimeout(this._searchTimer);
            const send = () => {
                this._searchTimer = null;
                this._sendSearch(text);
            };

            if (this._searchDebounce === 0) {
                send();
            } else {
                this._searchTimer = setTimeout(send, this._searchDebounce);
            }
        },

        _sendSearch(text) {
            const query = effectiveQuery(text, this._searchMin);

            if (query === this._sentQuery) {
                return;
            }

            this._sentQuery = query;
            this._failedQuery = null;

            // From the text field, not through `$dispatch`: that starts the event at the element
            // whose handler called in, and a pick is a click on a row of the panel, which is
            // teleported to the end of the body. The event then bubbled past every element of
            // the control, and a listener on the component tag never heard the search a choice
            // ends. The field is inside the control, so its event reaches every ancestor.
            const source = (typeof this._searchSource === 'function' ? this._searchSource() : null) ?? this.$root;
            source?.dispatchEvent?.(new CustomEvent('search-change', { detail: { value: query }, bubbles: true, composed: true }));

            // The listener starts the round trip at once, as `$wire.set(…)` does. One that holds
            // the request back must not leave the panel saying it is searching, so the
            // expectation lapses if no commit starts shortly.
            this._expectingSearch = true;
            clearTimeout(this._expectingSearchTimer);
            this._expectingSearchTimer = setTimeout(() => {
                this._expectingSearch = false;
                this._expectingSearchTimer = null;
            }, 1000);
        },

        /** Whether a search this component sent has not come back yet. */
        searchBusy() {
            return this._server && (this._expectingSearch || this._searchesOut > 0);
        },

        /** `aria-busy` while a search is out, and no attribute otherwise. */
        searchAriaBusy() {
            return this.searchBusy() ? 'true' : null;
        },

        /** The line under a list of results: a search still out, or results cut at a limit. */
        searchNote() {
            if (! this._server) {
                return '';
            }

            if (this.searchBusy()) {
                return this._searchTexts.searching;
            }

            if (this._searchFailedFor(this._typedQuery)) {
                return this._searchTexts.failed;
            }

            return this._truncated ? this._searchTexts.truncated : '';
        },

        /**
         * What the search status region says: a search still out, what an empty list means, or
         * that the results were cut. Nothing while results are simply on screen: the list
         * itself is what the reader arrows through.
         */
        searchAnnouncement(count, text) {
            if (! this._server) {
                return '';
            }

            if (this.searchBusy()) {
                return this._searchTexts.searching;
            }

            if (this._searchFailedFor(effectiveQuery(text, this._searchMin))) {
                return this._searchTexts.failed;
            }

            if (count === 0) {
                return this.searchEmptyText(text);
            }

            return this._truncated ? this._searchTexts.truncated : '';
        },

        /**
         * What the panel says when it lists nothing, for the text in the field.
         *
         * A search out wins; then text too short to send; then no search at all, where "No
         * results" would answer a question nobody asked.
         */
        searchEmptyText(text) {
            if (! this._server) {
                return this._searchTexts.empty;
            }

            if (this.searchBusy()) {
                return this._searchTexts.searching;
            }

            if (this._searchFailedFor(effectiveQuery(text, this._searchMin))) {
                return this._searchTexts.failed;
            }

            const trimmed = String(text ?? '').trim();

            if (trimmed !== '' && trimmed.length < this._searchMin) {
                return this._searchTexts.tooShort;
            }

            return effectiveQuery(trimmed, this._searchMin) === '' && this._sentQuery === ''
                ? this._searchTexts.prompt
                : this._searchTexts.empty;
        },
    };
}
