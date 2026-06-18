/**
 * WireKit Map Alpine component — a peer-dependency adapter.
 *
 * Like chart (Chart.js) and editor (Tiptap), WireKit ships the themed chrome,
 * the declarative API, and the Alpine glue — but NEVER bundles the heavy map
 * library. The app installs MapLibre GL (default) or Leaflet and supplies the
 * tiles; this glue detects whichever is present and drives it.
 *
 * The accessible path is the marker LIST rendered by the Blade view — always in
 * the DOM, never gated on the library. The visual map is a progressive
 * enhancement: if no supported library is loaded, the component degrades to the
 * list + a placeholder (and a one-time console hint, mirroring chart.js).
 *
 * Lifecycle resources held on `this`:
 *   - _map (the library map instance) — destroyed (remove()) in destroy().
 *   No observers / timers / document listeners.
 *
 * @param {Object} config
 * @param {Array}  config.center   - [lat, lng]
 * @param {number} config.zoom
 * @param {Array}  config.markers  - [{id,lat,lng,label,intent?}]
 * @param {string} config.provider - 'maplibre' | 'leaflet'
 * @param {string} config.styleUrl - tile/style URL (provider-specific)
 * @param {string} config.attribution - tile attribution HTML (Leaflet provider; shown
 *   in Leaflet's attribution control). Required by some tile sources (e.g. OSM).
 */
export default function wirekitMap(config = {}) {
    return {
        center: Array.isArray(config.center) ? config.center : [0, 0],
        zoom: Number(config.zoom) || 2,
        markers: Array.isArray(config.markers) ? config.markers.map((m) => ({ ...m })) : [],
        provider: config.provider || 'maplibre',
        styleUrl: config.styleUrl || null,
        attribution: config.attribution || null,
        available: false,
        // Currently-selected marker id (set by a list click OR a map-pin click).
        // The Blade list highlights the matching row, so clicking a pin and clicking
        // a list row stay in sync — closing the gap where a pin click previously
        // left the sidebar unchanged.
        selectedId: null,
        _map: null,
        // ResizeObserver that keeps the GL canvas matched to its container (held so
        // destroy() can disconnect it — defensive observer cleanup).
        _resizeObserver: null,
        // The provider actually resolved at init (may differ from `provider` when it
        // falls back to whichever library is present). Drives _addMarker + panTo.
        _resolved: null,
        // id → library marker instance, so realtime upsert/remove and selection can
        // reach the rendered pins (not just the reactive `markers` array / the list).
        _markers: {},

        init() {
            this.available = this._detectProvider() !== null;
            if (!this.available) {
                this._warnMissing();
                return;
            }
            // Defer the actual library init to a hook the integration can call;
            // wrapped in try/catch so a misconfigured tile/style never breaks the
            // page — the list-alternative still works.
            try {
                this._initLibrary();
            } catch (e) {
                this.available = false;
                this._warnMissing(e);
            }
        },
        destroy() {
            // Disconnect the ResizeObserver BEFORE tearing the map down so a final
            // resize callback can't fire against a removed map — defensive observer
            // cleanup; a callback after teardown would break browser tests.
            if (this._resizeObserver && typeof this._resizeObserver.disconnect === 'function') {
                this._resizeObserver.disconnect();
            }
            this._resizeObserver = null;
            if (this._map && typeof this._map.remove === 'function') {
                this._map.remove();
            }
            this._map = null;
            this._markers = {};
        },

        // ── Provider detection ───────────────────────────────────────────
        _detectProvider() {
            if (typeof window === 'undefined') return null;
            if (this.provider === 'leaflet' && window.L) return 'leaflet';
            if (this.provider === 'maplibre' && window.maplibregl) return 'maplibre';
            // Fall back to whichever is present.
            if (window.maplibregl) return 'maplibre';
            if (window.L) return 'leaflet';
            return null;
        },

        _initLibrary() {
            const el = this.$refs.canvas;
            if (!el) return;
            const which = this._detectProvider();
            this._resolved = which;
            let map = null;
            if (which === 'maplibre') {
                map = new window.maplibregl.Map({
                    container: el,
                    style: this.styleUrl || 'https://demotiles.maplibre.org/style.json',
                    center: [this.center[1], this.center[0]], // maplibre is [lng, lat]
                    zoom: this.zoom,
                });
            } else if (which === 'leaflet') {
                map = window.L.map(el).setView(this.center, this.zoom);
                // Pass attribution through to Leaflet's tileLayer so the tile
                // source's required credit (e.g. OSM's '© OpenStreetMap
                // contributors') shows in the attribution control.
                if (this.styleUrl) {
                    const layer = window.L.tileLayer(
                        this.styleUrl,
                        this.attribution ? { attribution: this.attribution } : undefined,
                    );
                    if (typeof layer.on === 'function') {
                        layer.on('tileerror', () => this._warnLoadFailure());
                    }
                    layer.addTo(map);
                }
            }
            // Mark the library instance raw BEFORE storing it on Alpine state.
            // Alpine deep-proxies component data via its vendored Vue reactivity,
            // and a PROXIED MapLibre map corrupts its internal WebGL render chain:
            // the style + tiles fetch fine (the network shows them loading) but the
            // GL canvas paints BLANK, while DOM markers still project — the exact
            // half-broken "tiles load, map stays empty" symptom. Leaflet renders
            // via DOM/img tiles and tolerates the proxy, but we mark both for
            // parity. `__v_skip` is the flag Vue's markRaw() sets and reactive()
            // honors — the same fix the editor needed for its ProseMirror instance.
            if (map && typeof map === 'object') {
                try {
                    Object.defineProperty(map, '__v_skip', { value: true });
                } catch {
                    // frozen/sealed instance — proceed un-flagged
                }
            }
            this._map = map;
            // Zoom controls. Leaflet's L.map() ships a zoom control by default, but
            // MapLibre ships NO UI — add the standard NavigationControl (zoom in/out
            // + compass) so both engines have on-canvas zoom buttons (MapLibre
            // otherwise has no zoom buttons). Guarded for a stubbed/older
            // build without NavigationControl.
            if (which === 'maplibre' && this._map && typeof this._map.addControl === 'function'
                && typeof window.maplibregl.NavigationControl === 'function') {
                this._map.addControl(new window.maplibregl.NavigationControl());
            }
            // A blank canvas must self-diagnose: when the style/tiles fail to load
            // (CSP connect-src/img-src block, cert interception, network), the
            // libraries fail SILENTLY — wire their error events to a one-time DX
            // hint naming the URL (mirrors the missing-library hint above).
            if (which === 'maplibre' && this._map && typeof this._map.on === 'function') {
                this._map.on('error', () => this._warnLoadFailure());
            }
            // Keep the GL canvas matched to its container.
            //
            // A ResizeObserver re-measures on EVERY container size change
            // (responsive reflow, font load, sidebar wrap, container queries) and
            // calls the library's own resize — the canonical pattern for a
            // dynamically-sized map mount. The rAF is a belt-and-braces first
            // attempt right after mount; the load handler covers a style that
            // finishes after the last resize. All three are idempotent.
            //
            // NOTE — this is NOT the fix for the "canvas stuck at 300px, paints
            // blank" bug class. That was a CSS cascade collision: MapLibre adds
            // `maplibregl-map` (position: relative in maplibre-gl.css) to the
            // mount, which can override the template's `absolute` (equal
            // specificity, source order wins), collapsing the mount to height 0 —
            // so MapLibre falls back to clientHeight || 300 and resize() re-reads
            // 0 forever (observer fires, but there's no height to grow into). The
            // real fix is h-full/w-full on the Blade mount (see map.blade.php),
            // which resolves under either computed position. The observer here
            // handles GENUINE size changes only.
            const resize = () => {
                if (!this._map) return;
                if (typeof this._map.resize === 'function') this._map.resize();
                else if (typeof this._map.invalidateSize === 'function') this._map.invalidateSize();
            };
            if (typeof window !== 'undefined' && typeof window.ResizeObserver === 'function') {
                this._resizeObserver = new window.ResizeObserver(resize);
                this._resizeObserver.observe(el);
            }
            if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(resize);
            }
            // Resize once more after the style/first render is ready, so the GL
            // viewport matches the (by-then-settled) container even if every earlier
            // resize measured a transient height.
            if (which === 'maplibre' && this._map && typeof this._map.on === 'function') {
                this._map.on('load', resize);
            } else if (which === 'leaflet' && this._map && typeof this._map.whenReady === 'function') {
                this._map.whenReady(resize);
            }
            // One marker-creation path for both providers (and for realtime upsert):
            // intent color + click-to-select + label tooltip all live in _addMarker.
            this.markers.forEach((m) => this._addMarker(m));
        },

        // Resolve an intent to its computed theme color. Map pins are drawn by the
        // peer library OUTSIDE WireKit's CSS, so we can't hand them a `var(--…)`
        // reference — we read the token's COMPUTED value off the component root, which
        // honors the active theme + any per-instance scope. `info` has no base token
        // (only --color-wk-info-text), so it aliases to accent, like the rest of the
        // intent system. Returns '' when there's no DOM (node) — callers treat an
        // empty color as "library default pin".
        _intentColor(intent) {
            const tokens = {
                accent: '--color-wk-accent',
                success: '--color-wk-success',
                warning: '--color-wk-warning',
                danger: '--color-wk-danger',
                info: '--color-wk-accent',
            };
            const name = tokens[intent] || tokens.accent;
            if (typeof window === 'undefined' || typeof window.getComputedStyle !== 'function' || !this.$el) {
                return '';
            }
            return window.getComputedStyle(this.$el).getPropertyValue(name).trim();
        },

        // Escape a developer-supplied string for the popup/tooltip HTML below.
        // BOTH engines parse their content as HTML (Leaflet's bindTooltip and
        // MapLibre's Popup.setHTML), so an unescaped label/body would be a
        // stored-XSS sink for marker data (same precedent as the ApexCharts
        // tooltip escaping).
        _esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
            }[ch]));
        },

        // Popup/tooltip content — four variants, driven purely by the marker's
        // data shape (four tooltip variants):
        //   {label}                       → text-only line
        //   {label, body}                 → styled card (bold label + muted body)
        //   {label, image, body?}         → photo card above the text
        //   {label, image, tooltip:'image'} → IMAGE-ONLY bubble (label stays in
        //                                     the list + the pin's aria-label)
        // Inline styles, not classes — the bubble renders inside the map
        // library's own pane. _esc escapes every developer string, including
        // the image URL in the src attribute.
        _tipHtml(m) {
            const imageOnly = m.tooltip === 'image' && m.image;
            // Photo-ONLY bubbles need a DEFINITE width: Leaflet's tooltip (and
            // MapLibre's popup) shrink-wrap their content, so a bare `width:100%`
            // image has no width to resolve against and collapses to a sliver
            // (a photo-only bubble collapses to a sliver on Leaflet). When there's text
            // alongside (text-with-photo), the text gives the bubble its width, so
            // `width:100%` is correct there. 12rem ≈ the 320×180 source at a
            // readable size; max-width keeps it inside a small viewport.
            const imageWidth = imageOnly ? 'width:12rem;max-width:60vw' : 'width:100%';
            const image = m.image
                ? `<img src="${this._esc(m.image)}" alt="" style="display:block;${imageWidth};height:auto;max-height:7rem;object-fit:cover;border-radius:.375rem;${imageOnly ? '' : 'margin-bottom:.375rem'}" />`
                : '';
            if (imageOnly) return image;
            const label = `<div style="font-weight:600">${this._esc(m.label)}</div>`;
            const body = m.body ? `<div style="opacity:.75;font-size:.85em">${this._esc(m.body)}</div>` : '';
            return image + label + body;
        },

        // Every marker with content gets a hover/click bubble — the data shape
        // picks the variant (see _tipHtml). Only a marker with nothing to show
        // (no label, body, or image) binds none.
        _hasTip(m) {
            return !!(m && (m.label || m.body || m.image));
        },

        // A Leaflet teardrop pin as an inline-SVG divIcon, filled with the intent
        // color (Leaflet's default marker is a fixed PNG that can't be themed). The
        // `wk-map-pin` class resets Leaflet's default white div-icon box — see
        // dist/wirekit.css.
        _leafletIcon(color) {
            const svg = '<svg viewBox="0 0 24 36" width="24" height="36" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
                + '<path d="M12 0C5.4 0 0 5.4 0 12c0 9 12 24 12 24s12-15 12-24C24 5.4 18.6 0 12 0z" fill="' + color + '"/>'
                + '</svg>';
            return window.L.divIcon({
                html: svg,
                className: 'wk-map-pin',
                iconSize: [24, 36],
                iconAnchor: [12, 36],
                popupAnchor: [0, -34],
                tooltipAnchor: [0, -30],
            });
        },

        // Create one rendered pin and register it by id. Intent color, click-to-select
        // (previously missing), and a label tooltip are wired here for BOTH providers.
        _addMarker(m) {
            if (!this._map || !m || m.id === undefined) return;
            const color = this._intentColor(m.intent);
            if (this._resolved === 'maplibre') {
                // Don't rely on chained return values (robust + mockable): construct,
                // then position + attach. `color` empty → the library default pin.
                const marker = new window.maplibregl.Marker(color ? { color } : undefined);
                marker.setLngLat([m.lng, m.lat]);
                marker.addTo(this._map);
                const node = typeof marker.getElement === 'function' ? marker.getElement() : null;
                if (node) {
                    node.style.cursor = 'pointer';
                    node.setAttribute('role', 'button');
                    node.setAttribute('aria-label', m.label || 'Map marker');
                    node.addEventListener('click', (e) => { e.stopPropagation(); this.selectMarker(m.id); });
                }
                if (this._hasTip(m) && window.maplibregl.Popup) {
                    // setHTML, not setText: the bubble carries the image / label /
                    // muted `body` line (_tipHtml escapes all three). Bare-label
                    // pins get no popup — the label is already the aria-label + list row.
                    const popup = new window.maplibregl.Popup({ offset: 24, closeButton: false }).setHTML(this._tipHtml(m));
                    marker.setPopup(popup);
                    // Open on HOVER. MapLibre's setPopup toggles the popup on marker
                    // CLICK, but our click is reserved for selectMarker (pan + emit) —
                    // and the docs promise "hovering (or tapping) a pin opens a
                    // bubble", which is how Leaflet's bindTooltip already behaves. So
                    // wire mouseenter/leave to togglePopup for parity (MapLibre popups
                    // were otherwise click-only, unlike Leaflet's hover tooltips).
                    // isOpen() guards keep enter/leave idempotent.
                    if (node) {
                        node.addEventListener('mouseenter', () => {
                            if (typeof popup.isOpen === 'function' && !popup.isOpen()) marker.togglePopup();
                        });
                        node.addEventListener('mouseleave', () => {
                            if (typeof popup.isOpen === 'function' && popup.isOpen()) marker.togglePopup();
                        });
                    }
                }
                this._markers[m.id] = marker;
            } else if (this._resolved === 'leaflet') {
                const marker = window.L.marker([m.lat, m.lng], color ? { icon: this._leafletIcon(color) } : undefined);
                if (typeof marker.addTo === 'function') {
                    marker.addTo(this._map);
                }
                if (this._hasTip(m) && typeof marker.bindTooltip === 'function') {
                    // Leaflet tooltips parse HTML — _tipHtml escapes the developer
                    // strings and adds the optional image + muted `body` line. Bare
                    // labels get no tooltip (already the aria-label + list row).
                    marker.bindTooltip(this._tipHtml(m));
                }
                if (typeof marker.on === 'function') {
                    marker.on('click', () => this.selectMarker(m.id));
                }
                this._markers[m.id] = marker;
            }
        },

        _removeMapMarker(id) {
            const mk = this._markers[id];
            if (mk && typeof mk.remove === 'function') {
                mk.remove();
            }
            delete this._markers[id];
        },

        // ── Marker interaction (list + map share this) ──────────────────
        selectMarker(id) {
            const marker = this.markers.find((m) => m.id === id);
            if (!marker) return;
            // Persist the selection so the list highlights it (and a map-pin click
            // mirrors into the sidebar — the reported gap). Both entry points (list
            // button @click + the pin click handler in _addMarker) route here.
            this.selectedId = id;
            this.panTo(marker);
            this.$dispatch('marker-click', { id });
        },
        panTo(marker) {
            if (!this._map) return;
            // Use the RESOLVED provider (the library actually in use), not the
            // requested one — _detectProvider can fall back to whichever is present.
            if (this._resolved === 'leaflet' && typeof this._map.panTo === 'function') {
                this._map.panTo([marker.lat, marker.lng]);
            } else if (typeof this._map.flyTo === 'function') {
                // Respect reduced-motion: jump instead of fly.
                const reduce = typeof window !== 'undefined' && window.matchMedia
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                this._map.flyTo({ center: [marker.lng, marker.lat], zoom: Math.max(this.zoom, 12), animate: !reduce });
            }
        },

        // ── Realtime marker diffing ──────────────────────────────────────
        upsertMarker(marker) {
            if (!marker || marker.id === undefined) return;
            const i = this.markers.findIndex((m) => m.id === marker.id);
            this.markers = i === -1 ? [...this.markers, { ...marker }] : this.markers.map((m) => (m.id === marker.id ? { ...m, ...marker } : m));
            // Keep the RENDERED pin in sync, not just the list: drop the old library
            // marker (if any) and re-add from the merged record (picks up moved
            // coordinates / a changed intent color).
            if (this._map) {
                this._removeMapMarker(marker.id);
                this._addMarker(this.markers.find((m) => m.id === marker.id));
            }
        },
        removeMarker(id) {
            this.markers = this.markers.filter((m) => m.id !== id);
            this._removeMapMarker(id);
            // A removed marker can't stay selected.
            if (this.selectedId === id) {
                this.selectedId = null;
            }
        },
        get markerCount() {
            return this.markers.length;
        },

        // One-time hint when the map library loaded but its style/tiles DON'T —
        // otherwise the canvas is just silently blank. A strict CSP is the classic
        // cause: MapLibre fetches style.json + vector tiles via connect-src while
        // Leaflet raster tiles load via img-src, so one engine can render fine
        // while the other stays blank on the same page.
        _warnLoadFailure() {
            if (this._loadFailureWarned) return;
            this._loadFailureWarned = true;
            // eslint-disable-next-line no-console
            console.error(
                '[wirekit::map] The map library loaded but its style/tiles failed to load'
                + (this.styleUrl ? ` (${this.styleUrl})` : ' (default demo style)')
                + '. Check the network panel: a Content-Security-Policy block '
                + '(connect-src for MapLibre styles/tiles, img-src for Leaflet raster '
                + 'tiles), a certificate interception, or an offline tile source. '
                + 'The accessible marker list keeps working.',
            );
        },

        _warnMissing(error) {
            // Intentional one-time console hint — DX signal when the map peer
            // dependency is missing (mirrors chart.js's Chart.js-missing hint).
            if (typeof window === 'undefined') return;
            window.__wirekit_map_missing_warned__ ??= false;
            if (window.__wirekit_map_missing_warned__) return;
            window.__wirekit_map_missing_warned__ = true;
            // eslint-disable-next-line no-console
            console.error(
                '[wirekit::map] No supported map library found on window. Install a '
                + 'peer dependency (MapLibre GL or Leaflet) and load it before WireKit. '
                + 'The accessible marker list still renders.',
                error || '',
            );
        },
    };
}
