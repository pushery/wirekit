# WireKit `dist/` bundles

WireKit ships five compiled artefacts. Pick the one that matches your
runtime / loader story.

| File | Format | Contents | Size (gzip ≈) | When to load |
|---|---|---|---|---|
| `wirekit.css` | CSS | Design tokens (`--color-wk-*`, `--radius-wk-*`, …), shared utility classes, global keyframes | 17 KB | Always — every consumer needs this |
| `wirekit.js` | IIFE | Every WireKit Alpine component (chart, dropdown, tooltip, modal, drawer, toast, …) registered as plugins. **Does NOT bundle Alpine itself.** | 17 KB | When your app already runs Alpine and you register WireKit plugins yourself (Laravel-Livewire setups, sample-app) |
| `wirekit.core.js` | IIFE | Chart component only — no overlay deps, no Floating-UI / focus-trap | ~6 KB | When you only need `<x-wirekit::chart>` and want the smallest possible bundle |
| `wirekit-apex.js` | IIFE | ApexCharts adapter glue — does **NOT** contain ApexCharts itself (consumer's separate npm install) | ~2 KB | When using `<x-wirekit::chart>` with `'charts.library' => 'apexcharts'` config |
| `wirekit-alpine.js` | IIFE | Alpine.js core + every WireKit Alpine plugin + auto-`Alpine.start()`. **Self-contained drop-in.** | ~30 KB | When you want one bundle that gives you Alpine + every WireKit primitive in a single tag (docs site iframe srcdoc, isolated preview surfaces, sample landing pages) |

## Pick exactly one of `wirekit.js` OR `wirekit-alpine.js`

The two bundles are mutually compatible (loading both is a no-op once
Alpine is detected) but consumers should pick **one** to avoid double-
registering plugins:

- **You already run Alpine** (Livewire 4 ships its own Alpine; existing
  Laravel apps register Alpine in `resources/js/app.js`) →
  load `wirekit.js`. WireKit's plugins register against your Alpine
  instance.

- **You want a self-contained drop** (preview iframe srcdocs, demo
  pages, third-party embeds) → load `wirekit-alpine.js`. It bundles
  Alpine + every plugin + auto-starts.

The `wirekit-alpine.js` bundle is built for one-tag drop-in scenarios
where the consumer doesn't control the Alpine pipeline. Typical use
cases: preview iframes, embedded demo pages, third-party widgets, and
any context where you need every WireKit Alpine-driven primitive
(reading-progress, stat-animate, reveal, modal, drawer, ...) to
initialise without the consumer first bootstrapping Alpine themselves.

## Loading via `@wirekitScripts` Blade directive

```blade
@wirekitStyles
@wirekitScripts {{-- Loads wirekit.js (full bundle) --}}
```

To force a specific bundle:

```blade
@wirekitScripts(bundle: 'core')   {{-- wirekit.core.js  --}}
@wirekitScripts(bundle: 'full')   {{-- wirekit.js       --}}
@wirekitScripts(bundle: 'alpine') {{-- wirekit-alpine.js --}}
```

The directive has automatic staleness detection: if the consumer ran
`vendor:publish` and forgot to re-publish after a `composer update`,
the directive falls back to serving from the package's own dist/
directory, so you never accidentally serve a stale bundle.

## Loading without Blade

For non-Laravel consumers or static-HTML preview surfaces, load the
file directly:

```html
<link rel="stylesheet" href="/path/to/dist/wirekit.css">
<script defer src="/path/to/dist/wirekit-alpine.js"></script>
```

The bundles are IIFEs with no module-loader requirement.

## License notes

- `wirekit.js`, `wirekit.core.js`, `wirekit-alpine.js` — MIT
  (WireKit's code) bundled with **MIT** dependencies (`@floating-ui/dom`,
  `focus-trap`, `tabbable`, plus Alpine.js in the alpine bundle).
- `wirekit-apex.js` — MIT adapter glue only. ApexCharts itself is
  **NOT MIT** (Community License under $2M USD revenue, Commercial
  License above). Adapter does NOT contain ApexCharts code; consumer
  installs it separately. See `docs/components/chart.md` for the full
  license terms.
