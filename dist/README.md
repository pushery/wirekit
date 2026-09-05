# WireKit `dist/` bundles

WireKit ships the compiled artifacts below — every file in this
directory has a row. Pick the one that matches your runtime / loader
story.

| File | Format | Contents | Size (gzip ≈) | When to load |
|---|---|---|---|---|
| `wirekit.min.css` | CSS | The same stylesheet, minified. **This is what `@wirekitStyles` links** — a page that uses the directive ships this file and not the one below | 14 KB gzip (81 KB raw) | Always, and you get it automatically: `@wirekitStyles` emits the `<link>` for you |
| `wirekit.css` | CSS | Design tokens (`--color-wk-*`, `--radius-wk-*`, …), shared utility classes, global keyframes — the readable reference for what a token means | 86 KB gzip (298 KB raw) | When you are reading or overriding tokens by hand, or bundling the stylesheet yourself. Budget from the minified row above, not this one — the difference is a factor of six |
| `wirekit.js` | IIFE | Every WireKit Alpine component (chart, dropdown, tooltip, modal, drawer, toast, …) registered as plugins. **Does NOT bundle Alpine itself.** | 74 KB gzip (261 KB raw) | When your app already runs Alpine and you register WireKit plugins yourself (the usual Laravel + Livewire setup) |
| `wirekit.core.js` | IIFE | Chart and image-compare components only — no overlay deps, no Floating-UI / focus-trap | 5 KB gzip (14 KB raw) | When you only need `<x-wirekit-chart>` / `<x-wirekit::image-compare>` and want the smallest possible bundle |
| `wirekit-apex.js` | IIFE | ApexCharts adapter glue — does **NOT** contain ApexCharts itself (developer's separate npm install) | 7 KB gzip (22 KB raw) | When using `<x-wirekit-chart>` with `'charts.library' => 'apexcharts'` config |
| `wirekit-tiptap.js` | IIFE | Tiptap editor adapter glue (`wirekitEditor` factory) — does **NOT** contain Tiptap itself (developer's separate npm install) | 3 KB gzip (8 KB raw) | When using `<x-wirekit::editor>` alongside `wirekit.core.js` (the full bundle already includes the editor) |
| `wirekit-optimistic.js` | IIFE | Optimistic UI factory (`wirekitOptimistic`) — shows an action's result before the server has confirmed it, then confirms or rolls back | 3 KB gzip (8 KB raw) | When you want optimistic updates. **Deliberately not in any other bundle**: loading this file is how you opt into its announcement behavior, so apps that don't use it pay nothing. Load it alongside whichever bundle you already picked |
| `wirekit-alpine.js` | IIFE | Alpine.js core + every WireKit Alpine plugin + auto-`Alpine.start()`. **Self-contained drop-in.** | 92 KB gzip (314 KB raw) | When you want one bundle that gives you Alpine + every WireKit primitive in a single tag (docs site iframe srcdoc, isolated preview surfaces, sample landing pages) |
| `wirekit-alpine.csp.js` | IIFE | The same self-contained drop-in built against Alpine's CSP variant, so no directive evaluates a string expression at runtime | 96 KB gzip (329 KB raw) | When your Content-Security-Policy forbids `unsafe-eval`. Selected by config — `'scripts' => ['bundle' => 'csp']`, see the table below |
| `wirekit.esm.js` | ESM | Every WireKit Alpine component as an ES module. **Does NOT bundle Alpine itself.** Default export is an Alpine plugin — `Alpine.plugin(WireKit)` — and each factory is also a named export | 74 KB gzip (262 KB raw) | When you bundle WireKit yourself (Vite, Rollup, esbuild) and want tree-shaking or your own registration order. This one is an `import`, not a `<script src>` |

## Pick exactly one of `wirekit.js` OR `wirekit-alpine.js`

Loading both is survivable — `wirekit-alpine.js` sees the Alpine that is
already running, registers its components on THAT instance instead of
starting a second one, and logs a console warning naming the choice — but
developers should pick **one**, so that which Alpine build evaluates your
expressions is a decision rather than a load-order accident:

- **You already run Alpine** (Livewire 4 ships its own Alpine; existing
  Laravel apps register Alpine in `resources/js/app.js`) →
  load `wirekit.js`. WireKit's plugins register against your Alpine
  instance.

- **You want a self-contained drop** (preview iframe srcdocs, demo
  pages, third-party embeds) → load `wirekit-alpine.js`. It bundles
  Alpine + every plugin + auto-starts.

The `wirekit-alpine.js` bundle is built for one-tag drop-in scenarios
where the developer doesn't control the Alpine pipeline. Typical use
cases: preview iframes, embedded demo pages, third-party widgets, and
any context where you need every WireKit Alpine-driven primitive
(reading-progress, stat-animate, reveal, modal, drawer, ...) to
initialize without the developer first bootstrapping Alpine themselves.

## Loading via `@wirekitScripts` Blade directive

```blade
@wirekitStyles
@wirekitScripts {{-- Loads wirekit.js (full bundle) --}}
```

**The directive takes no bundle argument.** Which file it emits comes from
config, so you choose the bundle once per application rather than per
layout — publish the config with
`php artisan vendor:publish --tag=wirekit-config` and set:

```php
// config/wirekit.php
'scripts' => [
    'bundle' => 'core', // 'full' (default) | 'core' | 'csp'
],
```

| Value | File it emits |
|---|---|
| `full` | `wirekit.js` — the default; any unrecognized value falls back here |
| `core` | `wirekit.core.js` |
| `csp` | `wirekit-alpine.csp.js` |

The directive's one optional argument is a Content-Security-Policy nonce
— `@wirekitScripts($nonce)` — which it puts on the `<script>` tag it
emits. Anything else in those parentheses is compiled into the tag's PHP
as an expression and breaks the page: `@wirekitScripts(bundle: 'core')`
is a PHP parse error on every request that renders the layout.

`wirekit-alpine.js` is the one bundle no config value selects, because it
brings its own Alpine and starting a second one alongside Livewire's is
never what a Laravel app wants. Load it with a hand-written tag, as under
"Loading without Blade" below.

The directive has automatic staleness detection: if the developer ran
`vendor:publish` and forgot to re-publish after a `composer update`,
the directive falls back to serving from the package's own dist/
directory, so you never accidentally serve a stale bundle.

## Loading without Blade

For non-Laravel developers or static-HTML preview surfaces, load the
file directly:

```html
<link rel="stylesheet" href="/path/to/dist/wirekit.min.css">
<script defer src="/path/to/dist/wirekit-alpine.js"></script>
```

Link the minified sheet, not `wirekit.css` — they carry the same rules
and the readable one is six times the weight, which is render-blocking
weight on every first page-view.

Every bundle you can put in a `<script src>` is an IIFE with no
module-loader requirement. `wirekit.esm.js` is the one exception — it is
an ES module and reaches the page through your own bundler, never
through a plain tag.

## License notes

- `wirekit.js`, `wirekit.core.js`, `wirekit-alpine.js` — MIT
  (WireKit's code) bundled with **MIT** dependencies (`@floating-ui/dom`,
  `focus-trap`, `tabbable`, plus Alpine.js in the alpine bundle).
- `wirekit-apex.js` — MIT adapter glue only. ApexCharts itself is
  **NOT MIT** (Community License under $2M USD revenue, Commercial
  License above). Adapter does NOT contain ApexCharts code; developer
  installs it separately. See <https://docs.wirekit.app/components/chart>
  for the full license terms.
- `wirekit-tiptap.js` — MIT adapter glue only. Tiptap's core
  (`@tiptap/core`, `@tiptap/starter-kit`) is **MIT**; the optional
  Pro extensions (`@tiptap-pro/*`) are commercial. Adapter does NOT
  contain Tiptap code; developer installs it separately. See
  <https://docs.wirekit.app/components/editor> for the setup walk-through.
