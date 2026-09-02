# Changelog

All notable changes to WireKit are documented in this file.

Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
This project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Browse it online — one page per version — at
[docs.wirekit.app/changelog](https://docs.wirekit.app/changelog).

---

## [2.43.0] — 2026-09-01

A minor release that spans the catalog rather than one corner of it. Five components gain an opt-in capability — a sticky header whose bound is yours to set, a stepper you can walk back through, a theme control that names the mode it is in, a code region you can name, and slot attributes that reach the cluster they belong to — and `wirekit:verify` learns to report which ApexCharts version you have. The fixes reach further than the shells: a module drawn as a button sat ten pixels off the axis of the ones beside it, a navigation column that remembers its width painted the seed before the value it remembered, `surface="soft"` had no hover state at all — and it is the surface most of an application's buttons use — and `wirekit:verify` reported a filled `icons.aliases` as missing, so using the feature is what turned the check red. Everything under Added is additive — an unset prop resolves to what it resolved to before, so a call that renders today renders the same after the upgrade.

### Added

- **`php artisan wirekit:verify` names the ApexCharts version you have, and whether the adapter is tested against it.** WireKit ships adapter glue rather than the chart library, so the version is yours to pick and nothing in the package pins it — which left the question "may we move to the new major?" unanswerable from the outside. The check now reads the installed version (falling back to the declared range), reports it against the tested majors, and warns rather than fails on one outside that set, because an unmeasured major is not a broken one. The range is 5, 6 and 7, verified in a real browser against 5.16.0, 6.10.0 and 7.1.0. See [Chart](https://docs.wirekit.app/components/chart).
- **`shell-bar` forwards the attributes of its `start` and `end` slots to the cluster that holds them.** Writing `<x-slot:start class="lg:hidden">` now hides the whole cluster rather than only its contents. Hiding the control alone left the wrapper behind as a zero-width flex item, which still took the bar's gap on both sides — so everything after it carried an indent nothing in the markup accounted for. See [Shell Bar](https://docs.wirekit.app/components/shell-bar).
- **`table` takes `sticky-header-max`, so how tall a sticky-header table may grow is the call site's decision.** `sticky-header` needs a bounded body to mean anything at all: the wrapper already scrolls on both axes, so a heading measured against a box that never scrolls vertically never sticks. That bound was a fixed `24rem`, which a five-row table reserves and then never uses — a box taller than its own content, and visibly worse than the plain table it replaced. It now takes any CSS length, and it reaches the element as an inline `max-height` rather than as a utility class, so `calc(100vh - 4rem)` works exactly as written, with nothing to escape and nothing your build has to have seen first. The default is still `24rem`, so no table that renders today moves. See [Table](https://docs.wirekit.app/components/table).
- **A completed `stepper` step can carry `href` or `wire:click`, and becomes a real link or button.** A stepper looks like the way back, so a finished step that does not answer a click costs the reader a click and a guess about the state of the page. Only steps before `current` become operable: one at or after it stays presentational even when you give it a destination, because a jump forward is a permission only your flow can grant — a clickable skip in a checkout walks around the validation the steps exist for. A stepper built from plain strings renders as it always has: no tag, no class, no attribute added. See [Stepper](https://docs.wirekit.app/components/stepper).
- **`theme-controller` has a fourth variant, `menu` — a trigger that names the mode currently on, with a menu of all three.** The other three each answer only half of "which mode is on right now": the icon button shows a glyph that may mean the state you are in or the one a click would produce, which are opposites, and it cannot say `system` at all; the switch says which of two and has the same missing third state; the `select` says all three in words, but a native `<option>` takes no markup and so can never carry an icon. The menu trigger shows the active mode's icon and its name, the panel lists all three with theirs, and `hide-label` drops back to the glyph alone where the name is a line too wide. See [Theme Controller](https://docs.wirekit.app/components/theme-controller).
- **`code-block` takes `label`, which names its scrollable code region.** That region is a landmark, and landmarks have to be distinguishable. The name is derived from the language when you set none, which reads well on a page carrying one or two blocks and stops helping the moment it carries twenty of the same language — reported from an audit log rendering one diff per table row, where the name meant to tell the regions apart is the thing that makes them identical. Name a block after what it holds rather than after what it is: "Change 4471" locates it, where the language alone only repeats what the whole page already is. See [Code Block](https://docs.wirekit.app/components/code-block).

### Fixed

- **`php artisan wirekit:verify` names translation keys that mean something different in your catalog than in WireKit.** The catalog is one flat namespace and your translations are layered on top on purpose — but an ordinary English word can carry two meanings, and then your wording wins inside a WireKit component that was written for the other one. Nothing throws; only the text is wrong, in a component you may not have reached yet. The check reports only keys a shipped component actually renders, because re-wording anything else is exactly what the app-wins rule is for. See [Chart](https://docs.wirekit.app/components/chart) for how to run the check as part of a build gate.
- **Nine component options the components read are finally in the published config stub, and two of them now actually work.** `vendor:publish` hands you `config/wirekit.php` to learn what is configurable, so an option missing from it is one nobody can find. Two were worse than missing: `table.head.variant` and `dropdown.panel.width` were declared under a flat dotted key, which `config()` never reaches — they read as configurable and resolved to their default whatever you set. Both keep their previous value, so nothing renders differently; they simply respond now. `table`'s new bound is spelled `sticky-header-max` to match its siblings.
- **`surface="soft"` answers the pointer.** It was the only one of the five surfaces with no hover state, and it is the surface secondary actions use — so it is the majority of the buttons in an application, and an element that does not answer the pointer reads as not clickable. See [Button](https://docs.wirekit.app/components/button).
- **The console shell's two navigation columns are no longer a pair of nameless landmarks.** Both are layout; the landmark is the `<nav>` inside each, which already carries its own name. Each slot's attributes now reach its column, so an application that wants a real landmark there can say so. See [App Shell](https://docs.wirekit.app/components/app-shell).
- **A line or area chart's tooltip names the point again instead of counting it.** Given `:labels`, the underlying library rewrites the axis into a numeric one and moves your strings aside, which left the header showing the data-point index — `15` where a date belonged. It is the normal case for `type="line"` with labels, and a quiet one: the chart draws, the console stays silent, and only hovering shows it. If you worked around this by passing `{x, y}` objects, that keeps working and you can drop it. See [Chart](https://docs.wirekit.app/components/chart).
- **`wirekit:verify` no longer reports a filled `icons.aliases` as a missing option.** The config-drift check filtered one direction and not the other, so using the feature is what turned a green check red — and the remedy it printed would have overwritten the aliases it was complaining about.
- **An `app-rail.item` rendered with `as="button"` sits on the same axis as its neighbors.** Its inline padding was being reset away, so a module drawn as a button stood ten pixels to the left of the ones around it. See [App Rail](https://docs.wirekit.app/components/app-rail).
- **A `sidebar` or `app-rail` with `persist` paints the width it remembers.** The stored state lives in the reader's browser, so the first paint used to show the seed and the column corrected itself a frame later — visible as the navigation being briefly collapsed and then snapping open. Both columns now emit a small nonced script that applies the remembered width before anything is painted; where a Content-Security-Policy refuses it, or the browser has site storage switched off, the previous behavior is unchanged. See [Sidebar](https://docs.wirekit.app/components/sidebar).

### Documentation

- **Each of the five application-shell blueprints carries a preview with every zone filled** — a brand row, labeled groups, a nested collapsible section, an unread count, the collapse control, an account menu, both header clusters and a page footer. The other previews on those pages each hold one decision still so it can be read; this one is the starting point to copy. See [Rail Shell](https://docs.wirekit.app/blueprints/application-shells/rail-shell).
- **The multi-column shell shows which column gives way.** Three columns and one window mean something has to yield, and the shell does not decide which: one preview hands the control to the list column, the other leaves the list alone and collapses a detail column on the far side of the reading area. See [Multi-Column Shell](https://docs.wirekit.app/blueprints/application-shells/multi-column).

## [2.42.0] — 2026-08-31

A minor release: seven components gained an opt-in prop or attribute, and a sweep of
accessibility and pointer fixes went through the catalog. Everything here is additive — an
unset prop resolves to what it resolved to before, so an upgrade from 2.41.x changes no
rendering you did not ask it to change.

### Added

- **`sidebar` takes `persist-driver="cookie"`, so the server can render the collapsed state it
  will keep.** `persist` was a localStorage key, and no server reads localStorage: a column that
  remembered being collapsed rendered at its seed width and the client corrected it a frame
  later. See [sidebar](https://docs.wirekit.app/components/sidebar).
- **`sidebar.collapsible` and `sidebar.group` announce themselves.** The collapsible root now
  carries `role="group"` and a name taken from its label, its trigger carries `aria-controls`,
  and the disclosed region carries the matching `id`. Both read the role and the label through
  the attribute bag, so a caller can still override either.
- **`profile` takes `as="div"` or `as="button"`.** A control that synthesized its own keyboard
  model out of a `div` is the shape the native element exists for. The default is unchanged.
  See [profile](https://docs.wirekit.app/components/profile).
- **`dropdown.item` takes `active`**, which sets `aria-current` — `page` on a link, `true` on an
  action — and marks the entry by weight and color. A menu whose most common use is navigation
  had no way to say which page you are on. See
  [dropdown](https://docs.wirekit.app/components/dropdown).
- **A `select` option can declare the language of its own label.** The per-option array form now
  accepts `lang`. A language picker lists endonyms, so most of its labels are words in a
  language the document is not in, and a screen reader read all of them in the page's voice
  (WCAG 2.1 AA 3.1.2). Both the flat and the grouped form. See
  [select](https://docs.wirekit.app/components/select).
- **`hideLabel` reaches `theme-controller`, `toggle`, `number-input` and `password-input`.** The
  label stays for assistive technology and leaves the layout. It matters most on
  `theme-controller`: the `select` variant is the only shape that can offer the third "System"
  state, and it was the only one that could not hide its label. See
  [theme-controller](https://docs.wirekit.app/components/theme-controller).

### Changed

- **`dropdown.item` marks the keyboard caret with `focus-visible` and a ring, not a filled
  background.** The menu focuses its first item on open, and a filled row reads as a selection
  rather than as a caret position — on a twelve-entry menu it looked like you were already on
  that page. Keyboard users keep the mark; a click no longer produces one.
- **`sidebar`'s `collapsed` prop is nullable.** With `false` as the default, "not set" and
  "explicitly expanded" were one value, and a server seeding from a cookie would override the
  call site rather than fill in for it. An unset prop still resolves to `false`.

### Fixed

- **The table's trailing scroll hint switches off at the end of the scroll.** Its sentinels had
  no width, so at full scroll the trailing one sat exactly on the scrollport edge, where an
  IntersectionObserver has no dependable verdict — the hint could stay lit over the end of the
  table, which is the one moment it promises to go away. Each sentinel now carries a pixel,
  taken straight back with a negative logical margin, so a non-overflowing table is no wider
  than before and the behavior flips correctly in a right-to-left table.
- **`link` rendered as a button shows a pointer.** Tailwind v4's preflight sets
  `cursor: default` on `button`, reversing the v3 default, so an underlined link whose action
  had to be a POST or a Livewire call left the underline as its only affordance.
- **Ten more buttons across four components** — `reading-bookmark`, `reading-spine`,
  `color-picker`, and `app-rail.item` under `as="button"` — carry a pointer for the same reason.
- **`checkbox` carries its own touch target.** The visible box is 20px and the input is 1x1, so
  conformance rested on nothing being placed beside it — a property of the page rather than of
  the component, and dense lists are where that room runs out. Default variant only: in `card`
  the label already is the target.
- **`sidebar`'s collapse toggle has a name before Alpine boots**, as do the `carousel`
  play control, the `app-rail` expand toggle and the `tour.step` close button. All four were
  named only by a binding, so the server-rendered markup carried no accessible name at all.
- **`wirekit:doctor` no longer reports a seam this package maintains.**
  `wirekit.components.*.classes` is a supported override point, and every correct use of it was
  reported as an option this version no longer offers — a red gate over working code for any
  project running `--fail-on=warning`.
- **The late-registration guard no longer reports an error about a page that is working.** A
  Livewire redirect-navigate re-evaluates the bundle's head script, and the guard read its own
  fresh module scope as a missing registration. Fixed per bundle rather than globally, so a
  genuine diagnostic still fires for every other bundle on the page.
- **The strictness gate sees all five scope directives.** `x-effect` and `x-model` were missing,
  and a discarded `x-model` is a two-way binding that silently never binds — visible only as a
  field that does not change.
- **Two component descriptions named prop values the validator rejects.** `app-rail` said
  `caption` where the value is `below`, and `swap` said `crossfade` where it is `fade`. With
  `APP_DEBUG` off the gate returns the first allowed value instead of throwing, so the invalid
  value rendered the wrong mode rather than failing.

### Documentation

- The [console-shell blueprint](https://docs.wirekit.app/blueprints/application-shells/console-shell)
  states its scroll model: `viewport` pins the root, and only `main` and the two navigation
  columns scroll. Reaching for `position: sticky` there produces no error, no warning and no
  visible change, which reads in a diff like somebody's decision.
- `dist/README.md` describes `wirekit.min.css`, which is what `@wirekitStyles` has emitted since
  2.35.0 — its only CSS figure previously described the readable copy, 81 KB gzip against 13 KB.
- The public CSS API page states when a scroll sentinel needs its pixel taken back and when it
  does not.

## [2.41.1] — 2026-08-31

Patch. Three defects with one root between two of them: a slot was asked about Blade syntax
it can no longer contain by the time the question is asked.

### Fixed

- **`<x-wirekit::alert-dialog.cancel>` and `.confirm` wrapped your button in a second
  button.** Both let the caller supply their own control instead of the default one, and both
  decided which case they were in by looking for `<x-wirekit` in the slot. A slot holds
  RENDERED markup, so by then Blade has already compiled that tag into `<button>` — the test
  never matched, and every caller who passed a button got it wrapped. Nested buttons are
  invalid HTML, and a browser repairing them splits the nesting: the control rendered as an
  empty box with its label stranded beside it, and on a page where one appeared, every
  heading after it stopped being a heading. Detection now reads the rendered markup. See
  [Alert Dialog](https://docs.wirekit.app/components/alert-dialog).

- **`<x-wirekit::wizard>` had no width of its own, so it shrank onto the step being shown.**
  It rendered as a flex column with no width, which takes the intrinsic width of its content
  — in any container that sizes to its content, the wizard became as wide as the current
  step. The step indicator inside it asks for the full width and faithfully filled a box that
  had already collapsed, so the frame around the flow changed size as the reader moved
  through it, and on a short step a two-word label wrapped mid-word. See
  [Wizard](https://docs.wirekit.app/components/wizard).

## [2.41.0] — 2026-08-30

Minor. Four new capabilities — a multi-step container, a selectable sidebar, type-to-confirm on
the alert dialog, and an overflow opt-out on the card — alongside a run of repairs to surfaces
that were documented and did nothing.

Classified MINOR because the additions are additive: every new prop defaults to the behavior
that shipped before it, so an application that upgrades and changes nothing renders exactly as
it did.

The theme of the fixes is worth stating, because it explains why so many of them are
documentation: a prop that reaches nothing, a token that does not exist, and an example that
names a slot the component discards all fail the same way — the page looks authored, the code
runs, and the developer gets a hole with no error to report.

### Added

- **`<x-wirekit::wizard>` — a container for multi-step flows.** The step indicator was purely
  presentational, so every application rebuilt the state machine and the navigation around it.
  The wizard uses the existing stepper rather than replacing it, and owns the part that was
  being rewritten each time: which step is current, what may be entered, and how focus moves
  when it changes. See [Wizard](https://docs.wirekit.app/components/wizard).

- **`<x-wirekit::sidebar mode="selection">` — a column that CHOOSES rather than navigates.**
  The two are different ARIA contracts, not a styling difference: a navigating column emits
  `aria-current="page"`, while a selection column is a `role="listbox"` with a single tab stop,
  `role="option"` rows, `aria-selected`, and arrow keys that move `aria-activedescendant`
  without moving focus. Passing `mode="selection"` gets the second contract whole. The default
  is unchanged. See [Sidebar](https://docs.wirekit.app/components/sidebar).

- **`<x-wirekit::alert-dialog>` can require the operator to type a confirmation.** The usual
  brake before a consequential action was missing, so applications either shipped without one
  or built it beside the dialog. A mismatched entry is REFUSED rather than styled as refused —
  the confirming action stays disabled and says why, instead of looking available and failing.
  See [Alert Dialog](https://docs.wirekit.app/components/alert-dialog).

- **`<x-wirekit::card overflow="visible|auto|clip">`.** A child meant to lift out of the card —
  a dropdown panel, a popover — was clipped by the card's own `overflow-hidden`, and the only
  way out was an `!important` override in application CSS. The prop names the four values
  directly; the default is the previous behavior. See
  [Card](https://docs.wirekit.app/components/card).

- **`<x-wirekit::command-palette>` answers `wirekit-command-palette-close`.** The documentation
  shipped a two-button block whose second button dispatched that event, and only the `-show`
  half had a listener. A developer who copied the pair got a Close button that did nothing,
  beside a sibling that worked — which reads as a scoping problem in their own application.
  Both halves are registered, and both are dropped on `destroy()`. See
  [Overlay events](https://docs.wirekit.app/overlays/events).

- **`wirekit:doctor` reports two things it could not before.** It names a configured icon preset
  whose composer package is not installed — previously a missing glyph at render time with no
  hint about the cause — and it says when the WireKit in `vendor/` is not the commit
  `composer.lock` names. The second matters when reading vendor source to decide whether a
  capability has landed: that tree can be older than the lockfile beside it while agreeing
  about the version. A path install is reported as NOT measured rather than as clean.
  See [CLI reference](https://docs.wirekit.app/cli-reference).

- **`StrictnessGate::discardedScopeDirectives()` is a public seam.** The gate already detected a
  scope-directive collision and warned about it; the detection was private, so a consuming
  application could not ask the same question in its own test. The predicate is now callable
  and returns the discarded directives rather than only writing a warning.

- **Two icon aliases promoted to the base preset**, `paint-brush` and `click`, so a page naming
  them resolves on the configuration WireKit ships rather than only under an extension preset.
  See [Icon](https://docs.wirekit.app/components/icon).

### Fixed

- **`--watch` was parsed and never watched — and the one thing it did do was corrupt `dist/`.**
  The flag existed only as `minify: !watch`, so `just watch` performed a single UNMINIFIED build
  and exited. Because `dist/` is committed and shipped as-is, that build left the unminified
  bundles in the tree for anyone who ran it: 76,170 inserted lines across eight files. The flag
  now opens a real watch context, and a build without it minifies as before.

- **Four design tokens were documented across 31 files and do not exist.**
  `--color-wk-bg-active`, `--color-wk-primary`, `--color-wk-reading-spine-active` and
  `--font-wk-display` appeared in theming and component pages as though they could be
  overridden. Setting one changed nothing, silently. The pages now name the tokens that exist.
  See [Theming](https://docs.wirekit.app/theming).

- **Two documented props reached nothing.** `<x-wirekit::context-menu.item href="…">` promised a
  real link and rendered a `<button>`; `<x-wirekit::reveal scope="…">` was declared, documented
  and never passed to class resolution. Both now do what their documentation says, and a
  `target="_blank"` link carries `rel="noopener noreferrer"`.

- **The catalog described slots that are not slots, and slots that are not required.** Three
  separate causes with one effect on `components.json`, `api-map.json` and the MCP catalog an
  AI assistant reads: a variable bound by a Blade loop, a variable bound by list destructuring,
  and a slot given a default with `??` — the first two surfaced as REQUIRED slots that do not
  exist, the third made an optional slot look mandatory. `<x-wirekit::pagination>` no longer
  advertises a slot named `link`, and `<x-wirekit::swap>`'s `on` / `off` are optional, which is
  what the component was always built for.

- **Five documentation examples named a slot the component discards.** Blade drops an unmatched
  `<x-slot:name>` without a word, so the affected blocks rendered without the part they were
  demonstrating — including an accessibility example whose whole point was the text alternative
  it was silently dropping.

- **`<x-wirekit::reading-spine>` reads a heading's accessible text and can skip a subtree.** A
  heading containing an icon or a badge contributed that markup to the table of contents, and a
  demo region inside an article had no way to stay out of it.

- **`dist/README.md` documented the self-contained Alpine bundle three kilobytes under its
  actual size.** The per-bundle decision table is what a developer reads to choose one; the
  figures are re-measured.

### Documentation

- **`<x-wirekit::calendar>` documents the width it needs** — 306px, measured, not the 322 a
  report was built on. See [Calendar](https://docs.wirekit.app/components/calendar).

- **The data-bearing pages answer the text-alternative question.** Chart, sparkline and the
  other pages that draw data documented keyboard access and left the question a screen-reader
  user actually has — what does this chart say — to a cross-reference.

## [2.40.0] — 2026-08-29

Minor. Two silent-failure repairs — a rail that renders the wrong state on a server that
keeps its workers alive, and a table that accepted a valid intent and drew it gray — plus a
documentation page that described a mechanism replaced months ago.

Classified MINOR rather than PATCH for one reason: the table now accepts three intent values
it previously rejected, and widening what an input accepts is additive.

### Added

- **`<x-wirekit::data-table>`'s badge columns accept every intent `<x-wirekit::badge>` does.**
  A column can map a cell value to an intent — `'intents' => ['processing' => 'accent']` — and
  the set was four values wide while the badge component validates against seven. Naming
  `primary`, `accent` or `info` produced a gray pill with no warning, which reads as "no
  status" rather than as a value the table did not know. All seven now render, and `primary`
  and `info` use the same accent-derived strengths the badge itself uses, so the word means
  the same thing in a cell as on a badge. See
  [Data Table](https://docs.wirekit.app/components/data-table).

### Fixed

- **`<x-wirekit::app-rail>` reads its persisted width from the request, not from the process.**
  With `persist-driver="cookie"`, the server half read `$_COOKIE`, which PHP fills once per
  PROCESS rather than once per request. Under `fpm-fcgi` those are the same thing; on any
  server that keeps a worker alive across requests — Octane, FrankenPHP, RoadRunner — it is
  filled at boot and never again. The rail then rendered collapsed and the browser widened it
  a frame later, moving the content column by 187px. Nothing threw; it rendered the other
  state. The request is asked first now, and the process globals remain as a fallback so an
  application that has not excepted the cookie from `EncryptCookies` keeps working exactly as
  before. See [App Rail](https://docs.wirekit.app/components/app-rail).

### Changed

- **The self-contained bundles carry Alpine 3.16.3**, up from 3.15 and inside the range already
  declared. `wirekit-alpine.js` grows from 282 to 290 KB raw, `wirekit-alpine.csp.js` from 297
  to 305 KB. Nothing changes for a developer who supplies their own Alpine. See
  [Integration](https://docs.wirekit.app/getting-started/integration).

### Documentation

- **[Checkbox](https://docs.wirekit.app/components/checkbox) describes how the third state
  actually survives a re-render.** The page credited an Alpine `x-init` snippet, which is the
  mechanism that was replaced — `x-init` runs once, while the indeterminate state usually
  arrives after the first render and is lost when a round trip morphs the element. The page
  now names the attribute the component watches, explains why an effect over a server-rendered
  value would not help, and says what to look for when verifying it from outside the package.
- **[App Rail](https://docs.wirekit.app/components/app-rail) carries the `EncryptCookies` note
  the theme controller has always had.** The cookie is written by JavaScript and therefore
  arrives unencrypted, so Laravel's default middleware drops it on the server read unless the
  key is excepted.

## [2.39.0] — 2026-08-29

Minor. One new primitive, one new check in the CSP audit, and a font-loading fix that moved
long-form text 35px on every first paint.

### Added

- **`<x-wirekit::band>` — the strip between two areas of a window.** Padding, and one rule on
  one edge: the search field under a toolbar, the composer under a message list, the action row
  along the bottom. It was hand-rolled in two dozen places across the blueprint catalog because
  nothing covered it. `edge` takes `top`, `bottom`, `both` or `none`; `padding` and `surface`
  sit on the token scales. It renders a plain element with no ARIA role, deliberately — a band
  is chrome, and `role="toolbar"` would promise arrow-key navigation between controls that
  nothing implements. Use `as="header"` or `as="footer"` where the strip genuinely is a
  landmark. See [Band](https://docs.wirekit.app/components/band).
- **`wirekit:csp-audit` now reports an Alpine payload placed inside a `<script>` block.** HTML
  escaping does not apply in a script context, so a payload containing the closing-tag sequence
  ends the block and everything after it is parsed as markup. The encoder's documentation has
  always said it belongs in a directive attribute; the audit now says so at build time, over
  your templates rather than only ours. See
  [CLI reference](https://docs.wirekit.app/cli-reference).

### Fixed

- **Text measured in `ch` no longer reflows when the web font arrives.** A `ch` is the advance
  width of the digit zero in whichever face is rendering, so with `font-display: swap` every
  box sized in it resized mid-load. The metric-matched fallback did not prevent this: it
  corrects a frequency-weighted average across many characters, which is what keeps a paragraph
  the same overall length, while `ch` reads one glyph. For the default sans face the two
  differed by about five per cent — roughly 35px on a 65-character measure, visible as
  long-form text jumping on the first visit to every page. The shipped font stylesheets now
  carry the measured value and the affected tokens use it. Nothing changes for a developer
  using their own faces. See [Theming](https://docs.wirekit.app/theming).

### Documentation

- **[Events](https://docs.wirekit.app/events) — the complete inventory of what WireKit
  dispatches**, split by direction: the events you listen for, and the ones you dispatch to
  drive a component. It also settles the naming convention for new events
  (`wirekit:<component>-<happening>`) and marks which existing names sit on older forms.
  Nothing is renamed — every current name keeps working for the whole of v2.

## [2.38.1] — 2026-08-28

Patch. Three fixes, no new API. The largest is an accessibility one that had been shipping
quietly: seven components minted a fresh DOM id on every render, so after any partial
re-render the attribute pointing at an element and the element's own id named different
things — both well-formed, and a screen reader simply stopped announcing the control.

### Fixed

- **A restarted or re-rendered component no longer loses the link between a control and the
  thing it points at.** `combobox`, `editor`, `message`, `progress`, `tooltip`, `accordion`
  and `usage-meter` derived their internal ids from a random value, which is stable only while
  the whole page renders in one pass. As soon as one part of it is refreshed on its own,
  `aria-controls`, `aria-labelledby`, `aria-describedby` and `label[for]` were left naming an
  id from the render before, while the element itself had moved on to a new one. Nothing
  looks wrong in the markup — that is why it survived — and the only witness is somebody using
  a screen reader. The ids are now counted rather than randomized, so they survive a re-render.
  Passing your own `id` or `name` behaved correctly before and is unchanged. See
  [Combobox](https://docs.wirekit.app/components/combobox),
  [Editor](https://docs.wirekit.app/components/editor) and
  [Tooltip](https://docs.wirekit.app/components/tooltip).
- **A date range fits a narrow container.** `<x-wirekit::date-picker range>` renders two native
  date inputs side by side, and neither could shrink below the width its own text and picker
  button need. In anything under roughly 330px the pair spilled out of its container, and where
  that container does not scroll the second field was clipped away entirely — unreachable on a
  phone. Both fields may now shrink and share the row. See
  [Date picker](https://docs.wirekit.app/components/date-picker).
- **A torn-down animated stat no longer reports itself as still running.** `<x-wirekit::stat
  animate>` left its started flag set after teardown while every timer was already released, so
  code inspecting the component could not tell a destroyed counter from one that had stalled
  mid-count. It now says which it is. No behavior changes; the counter animated correctly in
  both cases. See [Stat](https://docs.wirekit.app/components/stat).

## [2.38.0] — 2026-08-28

Minor. The overlay work of the last two releases reached the drawer: its geometry ships in the
stylesheet now rather than depending on your Tailwind build, and the panel became a real dialog
instead of one that only looked like it. Alongside that, three layout defects that each cost a
visible jump on a page load, and a row that lines its form controls up whatever mix of labels
they carry.

### Added

- **A `<x-wirekit::data-table>` column takes a `subKey`, so a cell can be two lines.** The
  ordinary shape of an admin table is a value over a quieter second one — order number over date,
  customer over email, product over SKU — and a column could previously render only a single
  string, a number or a status pill. A row whose second field is empty draws one line rather than
  one line and a gap. On a `number` column both lines stay tabular. See
  [Data Table](https://docs.wirekit.app/components/data-table).
- **`<x-wirekit::product-card>` takes a `media` slot and a `badge` slot.** `image` is a URL, and
  there was no branch for its absence — so a product whose artwork is drawn rather than
  photographed, or is a video or a color field, had no way into the component at all. The slot
  wins over `image` when both are given, and with neither the card draws no media area, which is
  deliberate: a placeholder invented here would appear on every card that legitimately has no
  picture. The `badge` slot sits beside the ones the card derives — those answer on-sale and
  in-stock, and "New" or "Limited" had no equivalent — and the two stack rather than overlapping.
  See [Product Card](https://docs.wirekit.app/components/product-card).
- **`<x-wirekit::row align-fields>` puts every control in a row on one line, and stacks them
  below 48rem.** A labeled field
  is taller than an unlabeled one and a field with a hint is taller still, so no single `align`
  value lines the controls up for every mix — and a submit button beside them has no label row
  at all. The row becomes three shared tracks (labels, controls, messages) and each field hands
  its parts to them. Opt-in: it changes the row's display model, and `align` / `justify` stop
  applying while it is on. See [Row](https://docs.wirekit.app/components/row).
- **`<x-wirekit::app-rail.item as="button">`.** A rail entry that opens a menu is not a
  destination, and as a link it was subtly broken: a link activates on Enter and not on Space,
  so the key did nothing. Every click on the placeholder `href` also pushed a history entry and
  wrote a bare `#` into the address bar. See
  [App Rail](https://docs.wirekit.app/components/app-rail).
- **`<x-wirekit::app-rail persist-driver="cookie">`.** `persist` keeps the reader's choice in
  `localStorage`, which no server can read — so a rail that remembers being expanded renders
  collapsed and widens itself after the first paint. A cookie is the only store Blade and Alpine
  both read, so the first render is already right. Opt-in, because writing a cookie is something
  an application has to be able to account for. See
  [App Rail](https://docs.wirekit.app/components/app-rail).
- **`wirekit:doctor` says what a stale published asset costs.** "Outdated" undersold it: the
  Blade directives compare size and hash, decide a stale copy cannot be trusted, and serve the
  package route instead — so every asset travels through PHP, without the cache headers or the
  precompressed sibling configured for `public/vendor/`. The report names that now, once per
  run. See [CLI Reference](https://docs.wirekit.app/cli-reference).
- **Two design tokens, `--size-wk-drawer-inset` and `--size-wk-rail-icon`.** Both were literals
  repeated across rules that had to agree; each is one value now. See
  [Design Tokens](https://docs.wirekit.app/theming/design-tokens).

### Fixed

- **An icon whose set is not installed renders a placeholder instead of a 500.** The
  component degraded for two of the three ways an icon can go missing: `blade-icons`
  absent, and an alias nobody has. The third is an alias that resolves perfectly onto a
  set nobody registered — `inbox` becomes `heroicon-m-inbox` out of a static table that
  never checks whether the glyph is there — and that name reached the renderer, found
  neither the set nor a fallback, and threw. Icons draw transitively through buttons,
  dropdowns and modals, so the page it took down was most pages. It is the state an
  application lands in by accident: one that already had `blade-icons` for its own set
  reads the set package as optional. The log line names the package to install, once per
  prefix rather than once per icon, and a fallback you configured still wins. A console
  command or a test still fails loudly, because there the missing package is fixable now.
  See [Icon](https://docs.wirekit.app/components/icon).
- **A restarted `<x-wirekit::stat>` counter no longer stays on zero when its frames stop after
  the first one.** The counter carries a watchdog for exactly that case — a frame that is hidden
  or unpainted pauses `requestAnimationFrame` indefinitely while timers keep running — and the
  watchdog was being called off by the first frame that landed. One frame does land: it writes
  zero and is then never followed, so the one state the net was there for was the one state that
  took it down. The watchdog now decides by whether the run actually finished. See
  [Stat](https://docs.wirekit.app/components/stat).
- **The scope-collision warning no longer fires at developers whose scope is fine.** It told a
  caller their `x-data` was discarded whenever the component's own template mentioned one — but
  it read the template with a pattern that cannot see conditions, and a large minority of
  components set theirs only inside one. `<x-wirekit::card>` is the case that surfaced it: its
  `x-data` belongs to a debug warning that renders only when a card is composed wrongly, so a
  correctly composed card collides with nothing and the message asked for working code to be
  rewritten. The check now asks whether the component sets one on every render, which is the
  claim the message makes. A component that sets one both ways still warns.
- **The workspace mark in an expandable `<x-wirekit::app-rail>` now shows its name when the rail
  expands.** The mark decided that in PHP, from `labels`, while rendering — and widening the rail
  is a browser-side change that happens long after, so a rail that started narrow kept its name
  hidden at every width. Move the workspace name out of your header because the rail was meant to
  carry it, and it was nowhere. The failure was silent: the name was in the document and
  announced, merely never drawn. The mark now reads the same live state its modules read. See
  [App Rail](https://docs.wirekit.app/components/app-rail).

- **The drawer's panel gets its geometry from the stylesheet, not from your Tailwind build.**
  Its position, size and edge came from bare utility classes, which exist only where a build
  scanned this package — so an application that did not would get a panel with no position at
  all. Alert dialog and modal already shipped theirs; the drawer was the one that did not. See
  [Drawer](https://docs.wirekit.app/components/drawer).
- **The navigation drawer is a dialog to the keyboard, not only to the mouse.** Below the
  breakpoint it slid over the page like a modal sheet and had none of a dialog's semantics: no
  `role`, no `aria-modal`, no focus trap, and Escape did nothing. Tab walked straight out of it
  into the page behind. See [App Shell](https://docs.wirekit.app/components/app-shell).
- **An expanded rail no longer makes the mobile drawer wider than the phone.** The drawer took
  the rail's expanded width with it, so a reader who had expanded the rail on a desktop got a
  sheet wider than the viewport on their phone. See
  [App Shell](https://docs.wirekit.app/components/app-shell).
- **The shell's columns stay in the layout above the breakpoint while the page is still
  booting.** A shell column is a drawer below `lg` and an ordinary column above it, and it was
  hidden either way until Alpine ran — so the content column painted across the full width and
  jumped aside when the columns arrived. Measured on a console shell at 0.1788 cumulative layout
  shift against a budget of 0.1, and it was the page's entire CLS. See
  [App Shell](https://docs.wirekit.app/components/app-shell).
- **The rail no longer flashes a horizontal scrollbar or slides its rows while it expands.** Its
  scroller asked only for a vertical axis, and CSS promotes the other one to `auto` as soon as
  one axis scrolls — so the region was horizontally scrollable by omission. Separately, a
  module name sat in a line box taller than the glyph beside it, and every entry grew by the
  difference the moment its name appeared. See
  [App Rail](https://docs.wirekit.app/components/app-rail).
- **The script tag carries `data-navigate-once`, so a client-side navigation does not execute
  the bundle again.** The bundle registers Alpine components and document listeners, which is a
  once-per-document job; without the attribute Livewire re-ran it on every hop and the listener
  set grew for as long as somebody navigated without a full reload. See
  [Integration](https://docs.wirekit.app/getting-started/integration).

### Documentation

- **The one diagnostic WireKit emits at `error` level is now documented as such.** A missing
  package stylesheet makes every dialog and drawer render inside the page instead of over it —
  finished-looking and unusable — so the message is an error rather than a warning. The
  consequence worth knowing beforehand is that a console check gating on `error` goes red in that
  situation over an install step rather than a defect. See
  [Console Output Baseline](https://docs.wirekit.app/extending/console-output-baseline).
- **The marketing icon preset no longer puts a number on its own size.** Its docblock claimed a
  count several times what the file declares: v2.37.0 moved the common semantic names into every
  base preset and the sentence stayed behind. A reader deciding whether to stack the extension
  at all was reading a figure that had been wrong for four minors — and the words it implied
  were missing had in fact become reachable from every preset. See
  [Icon](https://docs.wirekit.app/components/icon).
- **The shipped asset sizes match the files again.** Four rows in the bundle catalog and three
  on the dependencies page had drifted; the readable stylesheet's row now names its property
  instead of a figure that moves whenever a rule gains a comment. See
  [Dependencies](https://docs.wirekit.app/dependencies).

---

## [2.37.2] — 2026-08-27

Patch. v2.37.1 gave the icon alias tables a column for every base preset and left the
prose above and below them saying there were two.

### Fixed

- **The paragraph introducing the alias tables no longer says they show two of the four
  base presets.** It is the sentence that described the defect v2.37.1 fixed, and it went
  out unchanged — so a reader arriving on `phosphor` or `tabler` was told, immediately
  above the tables, that the column they needed was not there. See
  [Icon](https://docs.wirekit.app/components/icon).
- **The note under the last alias table names every preset column, not two of them.** It
  is the only sentence on the page that says what those columns contain — the exact string
  `@svg()` receives — and it excluded half of them.
- **Correction to v2.37.0: the drawer's panel is not yet covered by the stylesheet-only
  geometry that release announced.** That entry named alert dialog, modal, **drawer** and
  command palette, and said the overlay is correct with or without the utilities. It is, for
  every surface it named except this one. The drawer's backdrop carries the shipped positioning
  class; its panel takes `position: fixed` from the utility alone, so in a build that does not
  scan this package the backdrop covers the page and the panel sits inside it in the document
  flow.

  The fix is not the obvious one and is deliberately not being rushed: the positioning class
  also sets `inset: 0`, which is right for a dialog that fills the viewport and wrong for a
  panel that sits on one edge — and the panel's edges and size come from utilities too, so
  position alone would leave it fixed with no geometry. Tracked, with the drawer's own
  geometry as the intended fix. Reported by a developer who checked the release note against
  the shipped stylesheet instead of adopting it. See
  [Drawer](https://docs.wirekit.app/components/drawer).

- **The Props table on the Icon page renders again.** Widening the ten alias tables also
  widened that table's delimiter row to six cells while its header row stayed at four,
  which is not a table any Markdown renderer will lay out as one.

## [2.37.1] — 2026-08-26

Patch. The alias reference on the Icon page promised a mapping for every base preset and
showed two of the four.

### Fixed

- **The icon alias tables now carry a column for every base preset, not two of four.** The
  page states that every base preset maps the complete set, and then listed only Heroicons
  and Lucide across all ten tables — so a reader whose `wirekit.icons.preset` is `phosphor`
  or `tabler` could not look up a single one of the 141 words. Both new columns are
  generated from the presets themselves rather than typed, so they cannot drift from what
  the package resolves. See
  [Icon](https://docs.wirekit.app/components/icon).

## [2.37.0] — 2026-08-26

Minor. Twenty-four icon names that only carried a real meaning if you happened to be on
Heroicons now carry it on every interchangeable preset, a streaming component can hand its
announcements to a page that already has a status region, and an overlay is an overlay even
when your Tailwind build never scanned this package.

### Added

- **Twenty-four alias words now carry a defined meaning on all four interchangeable icon
  presets, not just Heroicons.** The vocabulary's whole promise is that a name keeps meaning
  the same thing when a project changes preset, and these words broke that promise quietly.
  On a non-Heroicons set they were never declared, so they fell through to whatever glyph
  happened to share the name — which is a coincidence rather than a mapping, and produced
  nothing at all where no such glyph existed. Each word was checked against the actual glyph
  files of every set before it was taken, and nine candidates stayed in the extension. Two
  reasons, both measured rather than assumed: for some, at least one set has no true cognate —
  `pulse` is the clearest, an EKG trace on three sets and a refresh arrow on the fourth — and
  for others the only candidate glyph is one another alias already uses there, so promoting
  the word would make two names indistinguishable on that set. Either way an alias that
  silently substitutes something merely similar is worse than one that is simply absent.
  Every base preset now carries an identical vocabulary.
  See [Icon](https://docs.wirekit.app/components/icon).
- **`cube` and `sparkles` join the base vocabulary too, so every alias a documented example
  uses now resolves on a stock install.** They had lived only in the marketing extension,
  which meant eight blueprint call sites rendered on the documentation site and threw
  `Unknown icon alias` for anyone who pasted them into a fresh project. Both were measured
  against the real glyph files of all four sets first — a box for `cube`, a sparkle for
  `sparkles`, everywhere. The singular `sparkle` deliberately stays in the extension:
  Heroicons and Phosphor each ship exactly one sparkle glyph, so promoting both words would
  make them indistinguishable there.
- **`attach` — a paperclip, on every preset.** Three blueprint pages were drawing the
  external-link box on an "Attach file" button, because the vocabulary had no word for an
  attachment and that was the nearest thing anyone could reach for. All four interchangeable
  presets ship a real paperclip, so the word is taken and those pages say what they mean.
  See [Icon](https://docs.wirekit.app/components/icon).
- **`announce="none"` on [Stream](https://docs.wirekit.app/components/stream).** A page that
  already owns a status region can now take the streaming transport without shipping a second
  one alongside it — previously the component always rendered its own, so adopting it meant a
  screen reader heard every completion twice. The failure announcement is deliberately not
  part of the bargain: the alert region is rendered in every mode, because a setting about
  routine progress should not quietly remove the one message a reader cannot afford to miss.
  The default is unchanged.

### Fixed

- **`arrow-up-right` draws a diagonal arrow now, not an external-link mark.** If you stack
  the marketing extension, this is a visible change: the word used to resolve to the
  box-with-escaping-arrow — the same glyph `external-link` gives you — so the two names were
  indistinguishable. It now draws what its name says, which is also what an unstacked
  Heroicons install has always rendered. If you meant the outbound-link mark, use
  `external-link`. Nothing changes on an unstacked install.
- **The `api-map.json` page-layout group is now called `page-layouts`, and the documented
  shape says so.** It was `layouts` until the pages moved under `blueprints/`; the rename
  shipped and the documented output shape did not follow, so a tool reading the catalog by
  name got nothing back instead of an error. The `partials` group had never been listed at
  all. If you read that artifact by group id, this is the one line in this release that
  affects you. See the [CLI reference](https://docs.wirekit.app/cli-reference).
- **An overlay is now positioned by the shipped stylesheet, not only by your Tailwind build.**
  Alert dialog, modal, drawer, command palette and the other overlays carried their geometry
  as utility classes, which exist only if your build scans this package's views. Where it did
  not, the overlay rendered in the document flow instead of above it: on a long page the
  dialog opened somewhere down the document, its confirm button sat below the fold, and a
  click on it did nothing at all — no error, no feedback, the button simply out of reach
  behind a scroll-locked body. The geometry now also lives in the stylesheet you already
  load, so the overlay is correct with or without the utilities. When both are missing, the
  overlay root says so once in the console and names the three things worth checking — one of
  them being `wirekit:verify`, which reports the same condition without waiting for a reader
  to open a dialog. See the [CLI reference](https://docs.wirekit.app/cli-reference).

### Documentation

- **[Localization](https://docs.wirekit.app/localization) now covers the case that surprises
  people on an upgrade: a word you deliberately left untranslated changes when WireKit learns
  a language.** The page explained that your own catalog wins per key, and warned about new
  keys appearing — but not the reverse. If your app defines no entry for a key, WireKit's
  catalog answers, and that answer depends on which languages WireKit speaks at the moment.
  Leaving a key undefined to keep the English word therefore holds only until a release adds
  your visitor's language. A real report: a plan tier *named* `Unlimited` — a proper noun,
  not a quantity — read `Unbegrenzt` for German visitors after the release that added German.
  Absence is not an override; the override props exist for this, and the page now says which
  of the two mechanisms fits which case.

- The published configuration file now states what stacking the extension presets requires,
  so the commented-out line under `preset` no longer reads as free to enable on any install.
- The SQLite step in [Getting started](https://docs.wirekit.app/getting-started) explains why
  the database file has to be created and why the migration follows it, instead of leaving
  both commands unannotated.

## [2.36.0] — 2026-08-25

Minor. A navigation column now paints at its real width on the very first frame instead of
flicking into place, a brand keeps its mark and its name on one row, and a counter that is
asked to run again always ends on its target rather than on a zero.

### Added

- **An `empty` slot on [`<x-wirekit::data-table>`](https://docs.wirekit.app/components/data-table)** — put a real starting point on the screen a
  new user reaches first. `emptyText` can say that nothing is here; it cannot say what to do
  about it, and there was no way to supply anything that could. The slot replaces the muted
  line rather than joining it, so a table never shows a call to action and a sentence saying
  nothing is here at the same time. Leave it off and nothing changes.
- **`rule="top"` on [`<x-wirekit::shell-bar>`](https://docs.wirekit.app/components/shell-bar)** — draw the bar's rule above itself instead of
  below. A bar at the FOOT of a navigation column needs its line between itself and the last
  item, not along the column's bottom edge; without this the only symmetric column available
  was one with a band at the head and a bare row at the foot.

### Fixed

- **A navigation column no longer flickers into place on load.** An interactive
  [`<x-wirekit::sidebar collapsible>`](https://docs.wirekit.app/components/sidebar) or [`<x-wirekit::app-rail expandable>`](https://docs.wirekit.app/components/app-rail) carried its width only in a
  binding that runs after the page has already painted, so the column laid out at content
  width and snapped to its real width a frame later. The width is now on the element from
  the first frame. A column whose collapsed state is remembered across reloads can still
  move once — that choice lives in the reader's browser and only the client can read it.
- **The brand slot of [`<x-wirekit::navbar>`](https://docs.wirekit.app/components/navbar) keeps a mark and a wordmark on one row.** The
  wordmark is a block element, so in the previous wrapper the name dropped to a second line
  under the logo. Nothing inside the slot could correct that — the wrapper is what decides
  the flow.
- **[`<x-wirekit::popover>`](https://docs.wirekit.app/components/popover) and [`<x-wirekit::hover-card>`](https://docs.wirekit.app/components/hover-card) now sit below page chrome.** Both are
  anchored panels that trap focus, so they belong on the dropdown layer rather than the
  tooltip layer, and a sticky header painted over them the way `--z-wk-chrome` was
  introduced to prevent. The tooltip layer keeps the surfaces that cannot be interacted
  with at all.
- **`wirekit:doctor` now notices a package update when it checks the compiled-view cache.**
  It compared your own `resources/views/` against `storage/framework/views/` and nothing
  else, so a `composer update pushery/wirekit` — which moves the package's views and touches
  none of yours — left it reporting a fresh cache while every compiled template still held
  the previous version's markup. That is the state right after an upgrade, and the one where
  `php artisan view:clear` is the answer it exists to give.
- **`hreflang` and `media` no longer read as unknown props.** Both are standard anchor
  attributes, and passing either to [`<x-wirekit::link>`](https://docs.wirekit.app/components/link) logged a warning that named a real,
  spec-defined attribute and offered a spelling suggestion for it — a gap in the reserved
  list that read like a typo in your markup. The rendered output was correct throughout.
- **A restarted counter no longer runs two animations at once.** Asking for a replay while
  the first count-up was still in flight left both running against the same value, so the
  number stuttered between them.
- **Restarting a [`<x-wirekit::stat animate>`](https://docs.wirekit.app/components/stat) counter always ends on the target value.** The
  restart resets to zero and then counts up on animation frames, which a browser stops
  delivering while a document is not being rendered — a hidden or zero-sized frame kept the
  counter at zero for as long as that lasted. It now settles on the target if no frame
  arrives.

### Security

- **Every Alpine expression a component writes into an attribute is escaped through one
  helper.** This landed in 2.35.0 across 117 call sites in 34 component views and was not
  named in that release's notes, so nobody reading them could tell it was worth prioritizing.
  It is stated here instead. No exploitable path was found in the shipped components — every
  value reaching those sites is either an allow-list member or already escaped — but the
  attribute was previously assembled by hand in each view, which is the shape where the next
  value nobody checks becomes a hole.

### Changed

- **`--size-wk-rail` is derived from its own content** rather than written as a round
  number, and resolves about a quarter of a rem narrower than before. It has to be exact:
  a column that is wider than the icon plus its paddings and border leaves slack, and the
  slack showed up as a sideways jump on every expand. Override the token to keep the
  previous width.
- **An icon-only rail row aligns its glyph to the leading padding instead of centering it.**
  With the width above derived from exactly that content the two look identical — centering
  had nothing left to center. Where they differed is a shell that insets the column beside a
  panel: centering averaged that inset in and moved the icon sideways on every expand, while
  the expanded row, which has a label beside the glyph, did not move. A caption placed UNDER
  the icon still centers, because there the axis in question is the vertical one.

### Documentation

- **The [Sidebar Shell](https://docs.wirekit.app/blueprints/application-shells/sidebar-shell), [Stacked Shell](https://docs.wirekit.app/blueprints/application-shells/stacked-shell) and [Multi-Column Shell](https://docs.wirekit.app/blueprints/application-shells/multi-column) pages are published.**
- **The observer helper leads the [custom Alpine plugin guide](https://docs.wirekit.app/extending/authoring-custom-alpine-plugins)** instead of appearing as an
  alternative to writing the guard by hand.

---

## [2.35.0] — 2026-08-25

Minor. The stylesheet you serve is a fifth of the size it was, the ES module can finally be
tree-shaken, and a handful of controls now do what they always said they did — a rich-text
editor that stops zooming iOS, a checkbox whose third state survives a server round trip, a
sidebar rail that no longer shifts the page when it collapses.

### Added

- **`--z-wk-chrome`** — a layer for page chrome that must stay above overlays opened in page
  content. [`<x-wirekit::header sticky>`](https://docs.wirekit.app/components/header) and [`<x-wirekit::navbar sticky>`](https://docs.wirekit.app/components/navbar) sit on it, so a
  popover in an article no longer paints over the site header. It stays below
  `--z-wk-modal`, and a panel anchored inside the header is unaffected: top chrome opens
  downward and never overlaps the bar it hangs from.
- **`--reading-progress-segment`** — retint the chapter dividers `<x-wirekit::reading-progress
  :segments="…">` draws, independently of the bar itself.
- **`previous-label` and `next-label` on [`<x-wirekit::pagination>`](https://docs.wirekit.app/components/pagination)** — name the two direction
  controls. "Previous" and "Next" are misleading on a reverse-chronological list, where
  moving "next" goes backward in time; on a changelog or an activity feed,
  `previous-label="Newer"` says what the button does. Unset keeps the translated defaults.
- **`label` on [`<x-wirekit::drawer>`](https://docs.wirekit.app/components/drawer) and [`<x-wirekit::alert-dialog>`](https://docs.wirekit.app/components/alert-dialog)** — name the dialog
  without a header slot. A drawer built from body content alone previously had no accessible
  name and no way to give it one.
- **The `undo` icon alias** — a real undo glyph in all four presets, so the name keeps meaning
  the same thing when a project changes preset.
- **Per-component ES module exports.** Every Alpine factory is exported by name and
  `register()` takes a chosen subset, so a bundler can drop what you never render. Measured
  with esbuild: one component 26 KB raw / 10 KB gzip against 235 KB / 66 KB for the whole
  library. The default export is unchanged.

### Changed

- **`@wirekitStyles` links a minified stylesheet.** The readable, fully commented
  `dist/wirekit.css` is still published beside it — it is the source of truth for what every
  token means — but two thirds of it is comments, and it is render-blocking. The linked file
  is now **12 KB gzip instead of 74 KB**, verified to contain every token, component class
  and at-rule the readable one does.
- **`wirekit:install --font=<key>` publishes that family only.** It copied the whole 5.8 MB
  font tree for a single flag, once per `--font*` flag, and again on every `composer install`
  where the publish is wired into `post-autoload-dump`.
- **[`<x-wirekit::prose>`](https://docs.wirekit.app/components/prose) wraps long lines in a `<pre>` instead of scrolling them.** A
  horizontal scroll region has to be reachable by keyboard, and Prose styles markup it did not
  author, so it cannot put a tab stop on your `<pre>`. Author one yourself —
  `<pre tabindex="0" role="region" aria-label="…">` — and Prose gives that block its
  horizontal scrolling back.
- **[`wirekit:doctor`](https://docs.wirekit.app/cli-reference) checks the stylesheet the page actually links.** It reported the readable
  copy as published while the linked one was missing, which is a green tick over an unstyled
  page.
- **Sidebar rows keep one height whether the rail is expanded or collapsed.** They were sized
  by the label when it showed and by the icon when it did not — 2.75px per row on the shipped
  type ramp, so a three-item rail moved everything below it by 8.25px on every toggle.

### Fixed

- **[`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) no longer makes iOS zoom on focus.** Its typing surface computed to
  14px: the 16px floor was written for form controls, and a rich-text editor types into a
  `contenteditable`, which no `input`/`textarea` selector reaches.
- **A caller's `aria-label` reaches the control on `inline-edit`, `editor`,
  `command-palette`, `drawer` and `alert-dialog`.** It was landing on a wrapper with no role,
  so the element the user operates had no accessible name at all.
- **Every labeled [`<x-wirekit::inline-edit>`](https://docs.wirekit.app/components/inline-edit) had a control with no accessible name** — the
  label's `for` and the control's `id` were two different strings.
- **[`<x-wirekit::checkbox indeterminate>`](https://docs.wirekit.app/components/checkbox) follows a change made after the first render.** The
  third state is a DOM property with no HTML attribute, and it was applied once; a Livewire
  round trip left the box reading "none selected" while something was selected.
- **[`<x-wirekit::collapsible>`](https://docs.wirekit.app/components/collapsible) and [`<x-wirekit::tabs>`](https://docs.wirekit.app/components/tabs) keep their content across a Livewire
  round trip.** Both built ids that changed on every render, so the morph replaced the
  region: focus left the slot, anything typed into an unbound input was discarded, and an
  open panel flashed hidden.
- **[`<x-wirekit::menubar.menu>`](https://docs.wirekit.app/components/menubar) and [`<x-wirekit::navigation-menu.item>`](https://docs.wirekit.app/components/navigation-menu) keep the classes you
  pass them.** They emitted `class` twice, and the browser discards the second.
- **[`<x-wirekit::code-block>`](https://docs.wirekit.app/components/code-block) and [`<x-wirekit::shell-bar>`](https://docs.wirekit.app/components/shell-bar) scroll regions are reachable by
  keyboard.** The shell bar's wiring depended on its optional `label`, so the common usage
  shipped an unreachable scroller.
- **[`<x-wirekit::message>`](https://docs.wirekit.app/components/message) reveals its actions on touch.** The default reveal used hover and
  focus, and a phone has neither in a discoverable form — reply, react and delete were
  invisible with no way to summon them.
- **Bottom-anchored floating controls clear the iOS home indicator.** `action-bar`,
  `scroll-to-top`, `reading-bookmark` and the [`<x-wirekit::reading-progress indicator="dot">`](https://docs.wirekit.app/components/reading)
  badge sat inside the gesture strip, which swallows taps.
- **`<x-wirekit::drawer>` measures against the dynamic viewport**, so the strip it reserves is
  not consumed by the mobile browser's own chrome.
- **Reveals no longer flash before they animate, and a click-triggered one is visible enough
  to click.** The rule that pre-hides an element until its animation runs was spelled for one
  authoring form in its anchor and for the other in its exclusions, so each was half-covered
  in opposite directions: a [`<x-wirekit::reveal>`](https://docs.wirekit.app/components/reveal)
  painted at full opacity before sliding in, while a `wirekitAnimate('…', { trigger: 'click' })`
  written by hand was hidden into a target nobody could aim at. Measured across all seven
  combinations.
- **A [`<x-wirekit::stat animate>`](https://docs.wirekit.app/components/stat) counter can be
  started again.** It fires once when the stat scrolls into view — correctly, since a stat that
  re-counts on every pass would be distracting — but nothing could ask for a restart: the
  observer disconnects itself after the first intersection, and the page said to scroll the
  stat out of view and back, which for that reason could never have worked. Dispatch
  `wirekit:stat-replay` on the stat's root. Under `prefers-reduced-motion: reduce` the value is
  already settled and the event does nothing.
- **[`<x-wirekit::mark>`](https://docs.wirekit.app/components/mark) renders its themed background again.**
- **`reading-progress` chapter dividers are visible in dark mode** — they were a fixed
  near-black on a transparent strip.
- **The sliders shrink with a narrow parent instead of overflowing it.** Their minimum width
  was a hard floor, against a comment promising the opposite.
- **[`<x-wirekit::glass />`](https://docs.wirekit.app/components/liquid-glass) is documented for the start of the body, not the head.** It emits an
  `<svg>`, and an SVG in the head ends head parsing — every metadata tag after it is reparented
  into the body, where a crawler does not look.
- **Config defaults that nothing read are wired or removed** — `stat.animate`, `editor.format`,
  `editor.toolbar` and `editor.size` are read from config now; `date-picker.format` is gone,
  since a native date input renders in the viewer's locale and cannot be told otherwise.
- **`as` is validated before it becomes a tag name.** A value containing a space or an `=`
  was rendered into the opening tag as an attribute.
- **`wirekit:install --rollback` no longer runs out of memory on a large install log**, and a
  second run reports that the session is already undone instead of silently replaying it.
- **[`<x-wirekit::fonts>`](https://docs.wirekit.app/components/fonts) documents its one prop.**
  `nonce` was explained in prose but never listed as a prop, so it was absent from the one place
  a developer looks for the component's surface.
- **The `wirekit:doctor` page shows the glyphs the command prints.** Five of its six output
  examples used `⚠` for a warning, which the command has never emitted — it prints `!` — and the
  page asserted the wrong glyph in its own legend. Four of the quoted lines also carried labels
  and a shape the command does not produce. A developer comparing their terminal against the page
  was matching it to output that does not exist.
- **[`wirekit:glass install`](https://docs.wirekit.app/components/liquid-glass) tells you to put
  the component in the body, where it belongs.** The command and both pages that show the
  placement still put it in the `<head>` — the placement this release announced as corrected. An
  SVG there ends head parsing, so every metadata tag after it is reparented into the body.
- **[`wirekit:icons --audit`](https://docs.wirekit.app/cli-reference) says what it looked at when
  it finds nothing.** It reads two surfaces — the `<x-wirekit::icon>` tag and the `icon` prop on
  every component that takes one — but both of its refusals named only the tag, so a project
  whose icons are props was told no icon tags were found and sent looking for a scanning path
  that was not the problem. The bound-value line said `:name="…"` where a prop binds as `:icon`.
- **[`wirekit:class-by-area`](https://docs.wirekit.app/cli-reference) looks for your application's own compiled CSS**, and says so when
  it finds none.

### Documentation

- The ES module page explains how to import a subset, with measured sizes.
- `wirekit:doctor`'s example output is transcribed once, on the command's own page.
- Fifty-seven blueprint code blocks that name a destination `.php` file now open with `<?php`.
- Thirteen page-to-page links that pointed at a fragment no heading generates are corrected.
- Every publicly served page has an authored meta description within the serving cap, and no
  two share a title.

---

## [2.34.3] — 2026-08-23

Patch. Two things a navigation column did while it was moving, and one that it did on a page
that had only just loaded.

### Fixed

- **The icon in an expanding [app rail](https://docs.wirekit.app/components/app-rail) no longer
  drifts and snaps back.** The attribute that decides where an item's icon sits — centered in an
  icon strip, at the start edge once names are beside it — is the same one that switches the
  labeling mode, and it was being held back until the width animation finished so that names
  would stop being laid out at widths they do not keep. The icon went with it: measured across
  the expand, it drifted from 17.5px out to 108px as the column grew underneath, then returned
  to 16px in a single frame. The two concerns are now two attributes, so the alignment follows
  the mode immediately and only the names wait.
- **A hard reload no longer animates a navigation column that has never been anywhere else.**
  Both the app rail and the [sidebar](https://docs.wirekit.app/components/sidebar) carried their
  width transition unconditionally, so the very first paint after a cache-clearing reload showed
  the column growing from its collapsed width to its resting one. It was most visible on a column
  that remembers its state — `persist` restores the collapsed width from `localStorage` before
  anything is painted, and the transition then animated out of the state it had just restored. The
  transition is now armed one frame after the column initializes, and only where the column can
  actually collapse; before that it simply has the width it has.

### Documentation

- The two collapsible sidebars on the sidebar page set their width with the `--wk-sidebar-w`
  token instead of a plain `width`, which is what the component reads. Pasted as it was, the
  example produced a column that ignored the value on collapse.

## [2.34.2] — 2026-08-22

Patch. The collapse control on a [sidebar](https://docs.wirekit.app/components/sidebar) moves
to the bottom of the column, where this library's own documentation already said it was.

### Fixed

- **The collapse control sits below the navigation.** It was the first child of the column, so
  it rendered at the top — while the page describing it told the reader to click the chevron
  at the bottom, and while the [app rail](https://docs.wirekit.app/components/app-rail)'s
  expander really is down there. One gesture, two components, two places to look for it.
- **It no longer shares an edge with the row beside it.** The column carried no row gap, so
  the control's hover surface ended at exactly the pixel where the adjacent
  [`sidebar.item`](https://docs.wirekit.app/components/sidebar)'s began. Two surfaces of the same gray touching read as one smudged block rather than as two
  controls — and on a collapsed rail, where both are icon-sized squares, nothing else tells
  them apart. The column now spaces its rows by the token they were already spaced by.

## [2.34.1] — 2026-08-22

Patch. Three things that only show while a navigation column is moving or a theme is rounding
hard, and a set of guards that were reading less than they claimed.

### Fixed

- **A navigation column no longer rearranges itself while it opens.** Both the
  [app rail](https://docs.wirekit.app/components/app-rail) and the
  [sidebar](https://docs.wirekit.app/components/sidebar) put their names back into the layout
  the instant the toggle was pressed — at whatever width the animation happened to be passing
  through, which is not the width it will keep. The text wrapped there and unwrapped as the
  column caught up. Measured in the sidebar, on a module whose name is long enough to wrap: sixty
  milliseconds in, at
  a column 174px of its final 256px, the first row stood 70.5px tall and the second sat 19.5px
  below where it ends up. With shorter names it is the pixel or two that reads as the menu
  settling. The names leave with the toggle as they always did, and now arrive only once the
  column has stopped moving — so they are only ever set at a width they keep. Nothing about
  wrapping changed: an entry still wraps rather than truncating.
- A [sidebar](https://docs.wirekit.app/components/sidebar) hides its `sidebar.item` names
  behind a marker of their own while the column is widening, rather than by holding back the
  collapsed state — that one is read by twenty-five other rules, including the width itself,
  and delaying it would delay all of them.
- **A heavily rounded [app rail](https://docs.wirekit.app/components/app-rail) `variant="panel"`
  gives its modules room to clear the corner.** The panel's radius follows a theme token and
  its padding was a constant, so a scale that rounds hard — 1.75rem against the stock 1rem —
  turned a 56px strip into a pill while the first icon stayed exactly where it was. Measured
  there, the icon's corner sat 2.48px INSIDE the arc; it now clears it by 5.07px, which is the
  room the stock scale always had. A floor rather than a value, so a gently rounded theme is
  unchanged.
- The `search` icon is back on the plain magnifying glass in the Heroicons preset. The circled
  variant matched its neighbors in ink coverage and not in kind — a filled disc among thin
  outlines reads as a different family rather than a heavier weight.

### Documentation

- The remaining application-shell previews drop their outer frame, matching the rest: the box a
  preview already sits in stands in for the browser window, and a bordered shell inside it is a
  window drawn inside a window.

## [2.34.0] — 2026-08-22

Minor release. It adds the pieces an application shell was missing — a module rail, a column
head, and the switcher that says what the page is about — and fixes a set of defects across
charts, overlays, pagination and the CLI.

### Added

- **[`app-rail`](https://docs.wirekit.app/components/app-rail) — the full-height module rail,
  with `app-rail.item`, `app-rail.group` and `app-rail.brand`.** The narrow column of icons an
  application puts its top-level areas in. It has three label modes — a tooltip beside the icon,
  a caption under it, or the name inline once the rail is wide — and it never truncates: a rail
  wide enough for captions wraps them instead, because a navigation whose labels are cut off is
  a navigation you have to guess at. The brand row centers while the rail is an icon strip and
  aligns to the column's spine once it is wide, and the workspace name is uncovered as the rail
  widens rather than appearing in the first frame of the animation. A current-module marker
  (`edge`) sits inside the row it marks, and a counter badge reads rail-specific colors so it
  stays visible on a toned column. The collapse toggle sits at the bottom, in the footer block,
  and takes no navigation row of its own when the rail is expanded.
- **[`shell-bar`](https://docs.wirekit.app/components/shell-bar) — the aligned column head that
  draws an app shell's top rule.** Every column in a shell draws that rule at the same height,
  from the same component, off the same token, which is what makes one line across all the
  columns. It carries three clusters: a pinned `start`, a scrolling middle, and an end. The
  `start` slot exists because the middle one scrolls — a hamburger or a back arrow placed there
  scrolls away with the tabs the moment the bar overflows, which on a phone is immediately. The
  scrolling cluster draws no scrollbar of its own, since the bar's bottom edge is the shell's
  rule and a track drawn there is a second, shorter line a few pixels under the real one; it
  still scrolls by touch, wheel and Tab.
- **[`app-shell`](https://docs.wirekit.app/components/app-shell) takes `panel` and `tone`.**
  `panel` insets the content into a rounded sheet that stands on the chrome — inset by the same
  amount on all four sides, with the shell's rule reaching it rather than stopping short at the
  seam. `tone` colors the chrome around it. A chrome column standing beside an inset panel draws
  no edge of its own, so the two do not sit a hairline apart.
- **[`scope-switcher`](https://docs.wirekit.app/components/scope-switcher) — the control that
  changes what the page is about.** Workspace, project, tenant, environment: the thing every
  application has and everyone builds again, usually as a dropdown, which makes it a menu of
  commands rather than a list with a current member. It is a listbox with search, groups, status
  and an optional create action, it sits where the answer already is — the first crumb of a
  breadcrumb — and it tells a screen reader which entry is the current one, which a menu
  structurally cannot. The search tolerates a typo: each word is tried as a substring, then
  as a subsequence, then within one edit of a part of the entry — with a swapped pair of
  letters counted as one edit rather than two, because that is how a name typed from memory
  usually comes out wrong.
- **[`popover`](https://docs.wirekit.app/components/popover) takes `label` and `padded`.** The
  panel is a `role="dialog"`, so `label` is what a screen reader announces on entry — the default
  was the word "Popover", identical for every popover on a page. `padded="false"` hands the whole
  surface to the caller, which is what a panel with its own header, scroll region and footer
  needs: those have to reach the panel's edges.
- **Type-ahead in [`dropdown`](https://docs.wirekit.app/components/dropdown) menus.** Typing a
  letter jumps to the next item starting with it; keep typing to narrow, or press the same key
  again to cycle through everything sharing that letter. The buffer clears after half a second.
  This was documented for a long time and never implemented — the line was eventually removed
  from the docs rather than the behavior added.
- **A [`data-table`](https://docs.wirekit.app/components/data-table) column may name its own
  status words.** `intents` maps a cell value to `success` / `warning` / `danger` / `neutral`, so
  a status the built-in English list never had — a domain term, another language — is no longer
  stuck on neutral. The built-in list is now documented in full rather than as an excerpt.
- **[`wirekit:verify`](https://docs.wirekit.app/cli-reference) `--fail-on=error|warning|none`.**
  The command exited 0 however many warnings it printed, so it could not gate a pipeline on the
  question it answers best. `warning` makes them a failure; the default is unchanged.
- **Seven icon aliases** — `more`, `more-vertical`, `arrows-left-right`, `hash`,
  `shield-warning`, `prohibit`, `scan` — including the first word for the overflow menu.
  Each has a genuine matching glyph in every preset WireKit ships, which is the standing
  admission test for the vocabulary.
- `--space-wk-nav-gap` — the gap between rows in a navigation column, read by both the rail and
  the sidebar.
- `--color-wk-rail-badge` and `--color-wk-rail-badge-fg` — the counter dot and the digits on it,
  as rail roles, so they invert with a toned column.
- `--wk-rail-item-aspect` — hold a rail's icon-only rows to a square.
- `data-wk-rail-brand-label` — mark anything in the rail's `brand` slot that is a label, and it
  appears only while the rail is wide enough to hold it.

### Fixed

- **The ApexCharts adapter stopped warning about itself.** It has two entry paths — register
  eagerly when Alpine is already present, register again on `alpine:init` — and in a Livewire
  application both always run, because Livewire assigns `window.Alpine` long before it starts
  it. The guard counted registrations, so every page of every Livewire app printed "the adapter
  is on the page more than once" and sent the developer looking for a second copy that did not
  exist, including on pages with no chart. It counts script evaluations now, which is what that
  sentence actually means.
- **The self-contained Alpine bundle no longer leaves the page inert when another Alpine is
  present.** It detected the other one and skipped **every** component registration, so the
  Alpine that was actually walking the page had never heard of any of them: 31 console errors,
  `wirekitDropdown is not defined` among them, and a page that renders perfectly and does
  nothing. It registers on the Alpine that is running now and starts one only when there is
  none — and says so, because the CSP guarantee belongs to whichever Alpine evaluates the
  expressions. The same branch registered the collapse plugin on the wrong instance, so four
  components lost their animation silently.
- **[`app-shell`](https://docs.wirekit.app/components/app-shell) tells you when it builds a
  drawer nothing can open.** Below the `lg` breakpoint the shell moves a `sidebar` or `rail`
  into an off-canvas panel — a decision the shell makes, not one you ask for — and the only
  thing that brings it back is a `<x-wirekit::sidebar.toggle>`. A composition without one has
  no navigation on a phone, and nothing said so. It now writes that to your log when
  `APP_DEBUG` is on.
- **`.wirekit-install.log` stops growing without bound.** It records the prior content of
  every file an install touched so `wirekit:install --rollback` can put it back — and a
  reinstall touches the published asset bundles, so the second install and every one after it
  wrote roughly ten megabytes into your project root, forever. Nothing warned, because an
  append is not an error. The five newest sessions are kept; `--rollback` only ever replays
  the newest, and it read the whole file to find it — so on a log that had been allowed to
  grow, the command that undoes a bad install was the one that ran out of memory.
- **[`popover`](https://docs.wirekit.app/components/popover) returns focus to its trigger when it
  closes.** Escape left focus on `<body>`, which drops a keyboard user out of the page they were
  in — WCAG 2.4.3.
- **The [sidebar](https://docs.wirekit.app/components/sidebar) reads one vertical rhythm.** Its
  first row sat 17px below the rule and its rows 8px apart, and the 17px was not a spacing
  anybody chose — it was the column's inter-zone gap, plus a 1px scroll-shadow sentinel, plus
  one more gap that the sentinel took part in. It reads `--space-wk-nav-gap` now, the same token
  the rail does, so two navigation columns side by side agree.
- **[`callout`](https://docs.wirekit.app/components/callout) renders a `<div>` rather than an `<aside>`.** An `<aside>` is a `complementary`
  landmark, and a shell already has one in its sidebar, so any screen showing a callout carried
  two same-role landmarks with no distinguishing name — an accessibility failure that gets
  reported against the sidebar. A callout is a note in the flow of the text, not a
  self-contained section. If you styled callouts by element selector, target the class instead.
- **[`pagination`](https://docs.wirekit.app/components/pagination) renders a `CursorPaginator` instead of throwing.** Its only guard asked "is
  there more than one page", which both paginator kinds answer — so a cursor paginator passed
  it and then reached methods it does not have. Since `full` is the default, the ordinary case
  failed. It now renders `mini` (previous/next), which is the whole of what cursor pagination
  can drive, and says so in the log when `APP_DEBUG` is on.
- **[`wirekit:icons`](https://docs.wirekit.app/cli-reference) `--audit` reads icon names passed as props**, not only
  `<x-wirekit::icon>` tags. Both resolve the same way, so both carry the same risk on a preset
  switch; a project that names its icons in props was getting a clean result over a fraction of
  its surface. The set of components that take an icon name is derived from the components
  themselves.
- **An optimistic control inside a menu never sent its request.** The layer looked for its
  Livewire component among the direct children of `<body>`, which stopped being where
  teleported panels land. With no component it silently fell back to a stub, installed no
  interceptor, and sat showing an unconfirmed value forever. Only one component could reach
  that path, so it looked like a single broken control rather than a broken mechanism.

### Changed

- The [sidebar](https://docs.wirekit.app/components/sidebar) draws no edge of its own while it
  stands on an app shell's chrome beside an inset content panel — the panel's own margin already
  separates the two, and both drawing an edge put a doubled hairline there.

## [2.33.0] — 2026-08-19

**Minor — this release is about being told what you actually have.** `wirekit:doctor` names every
personalized class block your application owns, `wirekit:icons --audit` separates the icon names
under contract from the ones that merely work today, the MCP server hands an assistant real worked
examples and the full component signature instead of a name and a category, and a scroll area fades
only the edges its content really continues past.

### Added

- **[`wirekit:icons --audit`](https://docs.wirekit.app/cli-reference) tells you which of YOUR icon
  names are under contract.** `<x-wirekit::icon>` renders any name your icon set knows, so a glyph
  name works — and looks exactly like a declared alias right up until somebody switches preset, at
  which point every one of them breaks at once. The audit reads your views and separates the two,
  with the file and line of each fall-through. It never calls a glyph name an error (some glyphs
  have no alias and never will) and never suggests a replacement (checked across ten such pairs,
  ten pointed at a different character). Names bound at runtime are counted separately rather than
  quietly dropped, and a run that finds no icon usage at all exits non-zero — "nothing was measured"
  and "nothing is wrong" are different answers.
- **The MCP server gained `get_component_examples` — worked examples instead of assembled markup.**
  [`php artisan wirekit:mcp-serve`](https://docs.wirekit.app/cli-reference) could tell an editor what
  props a component accepts; it could not show one being used. A prop list says what is allowed, and
  an assistant filling one in guesses at the composition — which sub-component wraps which, which
  props are set together, what the canonical shape actually is. The new tool answers that from real,
  reviewed usage: 439 examples covering every documented component, and a sub-component (`card.body`)
  resolves to the page where it is shown inside its parent. Ask for it before writing markup.
  Documented in the [AI tooling guide](https://docs.wirekit.app/ai-tooling).
- **`get_component` now answers with the whole component, not a summary of it.** The MCP server
  described a component as a name, a category, a description and three fields per prop. An assistant
  reading that could not tell an enum from free text, saw `config('wirekit.components.button.intent',
  'primary')` as the default and had to guess what may be passed, and — worse — was never told that
  `card.body` exists, which is the one composition rule the shipped guidance spends a paragraph on.
  It now returns the same picture the JSON manifest carries: the documentation URL, whether the
  component is anonymous or class-based, every declared slot with whether it is required, every
  sub-component with its own props, and the full prop signature including type hints, the resolved
  default behind a `config(...)` call, and the example values the docblock names. A test compares
  the two surfaces field by field, in both directions, so one can no longer learn something the
  other does not. Documented in the [AI tooling guide](https://docs.wirekit.app/ai-tooling).
- **Three registry helpers became public API**, because the manifest and the MCP server were each
  deriving the same answers privately and had begun to disagree: `ComponentRegistry::slotsOf(…)`
  returns a component's declared slots with their required flag, `ComponentRegistry::describeSubComponentsOf(…)`
  returns its sub-components with their props, and `ComponentRegistry::existingBladeFilePath(…)`
  resolves a component's template — or null when it has none, which is the answer the
  path-returning companion cannot give.

  The examples are extracted when the package is built rather than read at runtime, because the
  documentation is not part of what gets installed — a server that read it would answer correctly in
  WireKit's own repository and "no examples" in yours.

- **[`scroll-area`](https://docs.wirekit.app/components/scroll-area) gained `fade="auto"` — an edge
  fade that measures before it masks.** The named edges (`both`, `start`, `end`) are unconditional
  CSS, which is what makes them free and also what limits them: they fade the top edge while the
  reader is already at the top, the bottom edge at the bottom, and both edges on an area whose
  content fits and cannot scroll at all — taking ink off text that is entirely visible, to signal
  something that is not true. `auto` masks only an edge the content continues past, and follows
  content that arrives later, which is the case worth having it for: a transcript appending a
  message, a list a search filters down, a panel that opens. Nothing to call, nothing to refresh.
  It is the one value that needs JavaScript, and it fails toward **no mask at all** rather than the
  wrong one — a missing hint instead of dissolved text. The named edges are untouched and stay pure
  CSS, so nothing that exists today changes, and the depth is still the `--fade-wk-size` token.

- **[`wirekit:doctor`](https://docs.wirekit.app/cli-reference) names every personalized block that
  replaces the shipped one.**
  [`WireKit::personalize()`](https://docs.wirekit.app/customization) takes two value shapes per
  block, and they differ in a consequence nothing reported: a finished class string REPLACES the
  block, while a closure receiving the vendor default extends it. A replacement is a valid choice —
  it also ends the flow of later WireKit changes to that block, permanently and without a word, so
  the personalization keeps looking like a decision somebody made long after it has stopped
  inheriting improvements. The check reports replacements as a warning with the block names and
  offers the closure form for the case where only a delta was wanted. It stays silent when every
  block extends. A new `WireKit::personalizedComponents()` returns the names of the personalized
  components; the map could be read per component but never enumerated, and a diagnostic cannot
  guess names it has no way to list.

- **[`wirekit:doctor:props`](https://docs.wirekit.app/cli-reference) gained `--require-in-scope`.**
  A run that scans real templates and finds none of them using a WireKit component has two honest
  readings, and which one is right depends on the application rather than on the linter. If you do
  not use WireKit in that tree, nothing in scope is correct and the default still succeeds. If you
  use it everywhere, the same result means the walk found the wrong tree — a second view path, a
  renamed directory, an argument pointing somewhere empty — and a green run is the last thing you
  want. The flag is how you say which application you are. Reported by a developer whose only handle
  on that state was matching the success sentence in a shell script, which a reword would have
  deleted silently.

### Fixed

- **The tab bar's active indicator was missing from the compiled CSS.** A tab bar's appearance
  moved into PHP in 2.31.0 — a good refactor, and one Tailwind's `@source` glob never looks at, so
  six classes stopped compiling. They were not decoration: they *are* the active-tab indicator and
  the margins that pull it onto the container edge. The bar rendered, the tabs worked, the ARIA was
  correct, and the selected tab was simply not marked. Reported from a project that attributes its
  built stylesheet byte for byte — 123702 → 123169 bytes, six selectors gone and none added. Fixed
  through the safelist mechanism that already exists for this class of bug, listing all 48 emitted
  classes rather than only the six that went missing: the other 42 survive today because some
  unrelated view happens to use `flex` or `gap-1`, which is a coincidence and not a guarantee.
- **[`<x-wirekit::fonts>`](https://docs.wirekit.app/components/fonts) overwrote the shipped font
  tokens with weaker ones.** The component wrote all three `--font-wk-*` variables unconditionally,
  standing in a hardcoded stack for a category nobody had configured — and those stand-ins are
  shorter than what the package ships. Both declarations sit unlayered at equal specificity, so
  document order decided it, and placed after `@wirekitStyles` the monospace stack silently lost two
  families. Nothing threw and the markup was identical either way; it showed only to a reader who
  had those fonts installed. An unconfigured `sans` or `mono` is now simply not declared, so the
  stylesheet's value stands from any position. Serif is deliberately still written, because the
  stylesheet does not declare it and omitting it would drop every serif surface to the browser
  default.
- **The `fonts.fallbacks` example gave a real family another font's numbers.** The configuration
  stub and the [fonts page](https://docs.wirekit.app/components/fonts) both showed a named family
  with measurements that belong to a different one — directly below a line reading "measure the four
  values, do not estimate them". A developer whose font really was that family read the block as
  already measured and pasted it. Both examples now use a placeholder family with blank
  placeholders: a blank cannot be copied, a plausible number can.
- **`wirekit:doctor` told developers to delete configuration that was working.** The config-drift
  check compared key names against the shipped stub and reported anything the stub does not carry as
  an option "this version no longer offers". Reported from a project where ten keys were named and
  all ten were wrong, in three shapes — keys whose names belong to the developer rather than to the
  stub, a feature whose stub value is an empty array so no correct use could ever match, and leaves
  sitting under a branch the stub does carry. Developer-keyed nodes are now exempt, a path that is a
  prefix of a stub key is not an orphan, and the wording no longer asserts that nothing reads them:
  a diff is evidence, not a verdict. The list also prints in full, because the truncation hid half
  of a finding whose whole point was which keys were named.
- **An underscore-spelled regional locale resolved to the wrong variety.** `pt_BR` and `pt-BR` are
  one locale wearing two separators, but only the base-language half of that was handled. The
  underscore spelling never reached the regional catalog this package ships: it missed, the base
  fallback answered, and a Brazilian-Portuguese application quietly rendered European Portuguese.
  Nothing threw — the strings were all present, just from the wrong catalog.
- **The component manifest said nothing about `glass`.** Of the whole catalog, exactly one component
  carries no props, no slots and no sub-components, and its emptiness is real. In a manifest that is
  indistinguishable from a component whose props could not be parsed — and the wrong reading is the
  expensive one, because a tool that assumes a parse failure will invent an API. Its description now
  says so, and a guard requires any component with nothing to declare to declare that.

- **`list` reported that it accepts no content.** Every machine-readable surface — the JSON
  manifest, the project-root schema file, and every tool fed by them — listed the component with an
  empty slot array while its template renders `{{ $slot }}` on its last line. A developer asking
  the manifest how to use it was told to write an empty tag. The cause was a second Blade-path
  resolver that knew the flat and dotted filenames but not the directory-index form `list` is
  written in, so the file was never found and "no template" read as "no slots" — indistinguishable
  from the components that genuinely have none. Both surfaces resolve through one path now, and a
  test fails whenever a component that renders a default slot fails to report one.

- **Two siblings of the same overlay no longer stand open at once.** Opening a second
  [`popover`](https://docs.wirekit.app/components/popover),
  [`dropdown`](https://docs.wirekit.app/components/dropdown),
  [`hover-card`](https://docs.wirekit.app/components/hover-card),
  [`menubar`](https://docs.wirekit.app/components/menubar) or
  [`navigation-menu`](https://docs.wirekit.app/components/navigation-menu) menu on the same page left
  the first one open behind it, and the two panels overlapped. Nothing reported it — no console
  error, no changed markup — because the only symptom is what a reader sees. `context-menu` and
  `combobox` had each solved it separately; all seven now share one mechanism, so the next overlay
  inherits it instead of copying it. Opening a dropdown still does not close a popover: the
  coordination is per component family, which is the behavior that existed before and is not a
  question a patch release should answer differently.

- **The CSP advice about method names was far wider than the rule it described, and the extra
  width cost real renames.** Two pages and
  [`wirekit:csp-audit`](https://docs.wirekit.app/cli-reference) itself said that a Livewire method
  "whose name is a JavaScript keyword" needs index access — `$wire['delete'](...)` instead of
  `$wire.delete(...)`. Measured against the parser that decides it, that is true of ten names and
  false of the forty-two other reserved and future-reserved words, because a reserved word after a
  dot has been an ordinary property name since ES5. A developer auditing their own component
  against the old sentence renames public action names — `for`, `class`, `return` — that were never
  affected, and every one of those renames is reachable from templates and tests. All three places
  now print the set itself: `delete`, `false`, `in`, `instanceof`, `new`, `null`, `true`, `typeof`,
  `undefined` and `void`, with a named counter-example so the list reads as complete rather than as
  a sample. The set is no longer written by hand anywhere — it is checked against the tokenizer's
  own table on every test run, so a future change to that table fails the build instead of leaving
  three pages quietly wrong.

### Documentation

- **Two PHP entry points that were only findable in the source are now on a page.**
  `WireKit::avatarPaletteFor(...)` returns the same background/foreground pair
  `<x-wirekit::avatar from-initials>` derives, so a custom chip can match an avatar without rendering
  one. `WireKit::defaultsFor(...)` reads back what `WireKit::defaults([...])` registered — with the
  distinction stated on the page, because it reports the runtime record rather than the value in
  effect.

- **Four tables across three pages rendered as raw text and now render as tables.** A blank line, a
  callout and a paragraph had each been placed inside a table, and one font-size table carried no
  header row at all. Markdown ends a table at the first interruption rather than resuming it
  afterwards, so every row below the break was published as pipe-separated body text. Affected the
  bundles table on the dependencies page, the App Shell prop table, and the motion and font-size
  token tables.
- **`wirekit:verify --tier` examples corrected.** The commented check ranges beside the two `--tier`
  examples still described the numbering from before a check was added, and the environment-tier
  comment pointed at a package-tier check.

- **The gap scale is now reachable from the components that use it.** `--gap-wk-*` and
  `--space-wk-*` do not run on the same ladder, and the
  [design-token page](https://docs.wirekit.app/theming/design-tokens) has said so since 2.28 with
  three guards keeping the table honest. It was still reported twice from two applications eight
  days apart, and the second report was measured against a version that already carried the table.
  That is a placement problem rather than a documentation one: measured, the words `gap-wk`,
  `design-tokens`, `ladder` and `rung` appeared zero times on the row, stack and grid pages, so a
  developer typing `gap="lg"` had no path to the paragraph that prevents the mistake — which also
  had no heading, so it was neither linkable nor in the page's contents. It has one now, and the
  five components that let a developer name a rung link to it:
  [row](https://docs.wirekit.app/components/row),
  [stack](https://docs.wirekit.app/components/stack),
  [grid](https://docs.wirekit.app/components/grid),
  [bento-grid](https://docs.wirekit.app/components/bento-grid) and
  [feature-grid](https://docs.wirekit.app/components/feature-grid).
- **The [customization page](https://docs.wirekit.app/customization) no longer says nothing warns
  you.** Its section on adjusting a block rather than replacing it explained that taking ownership
  silently stops later improvements from reaching it, and closed with "nothing warns you, because
  nothing is broken". The doctor check in this release *is* that warning, so the sentence became
  false in the same release that made it obsolete. It now names the check.
- **The PHP discovery surface is documented where it claims to be.** The
  [ComponentRegistry page](https://docs.wirekit.app/extending/component-registry) opens by calling
  itself the canonical surface for discovering every component, and documented five of its thirteen
  entry points — `subComponentsOf`, `tag`, `tagAlias`, `resolve`, `componentClass`,
  `isSubComponent`, `subComponents` and `extractAwareProps` were all shipped and described nowhere.
  All of them are there now, with the three this release adds. Two claims on the page were also
  wrong: `type_hint` was said to be always null where fifteen props carry one, and the prop-record
  table listed five fields where the record has six.
- **The theme-preset registry had only its write side on a page.**
  [`ThemePresetRegistry::register()`](https://docs.wirekit.app/cli-reference) was documented; the
  four reads that make it useful were not — which is exactly the set a theme picker needs.
  `all()`, `keys()`, `get()` and `isValid()` are documented together now, with `isDefault()` called
  out separately because it is the one a picker gets wrong: `default` is not a preset with variables,
  it is the instruction to remove the block, and treating it as a normal preset writes an empty one.
- **`WireKit::cspNonce()` and `WireKit::prefix()` are documented** — the first in the
  [integration guide's nonce section](https://docs.wirekit.app/getting-started/integration), with
  its resolution order and the fact that `null` means "no policy" rather than a failed lookup; the
  second in [Getting Started](https://docs.wirekit.app/getting-started), where code that builds a
  tag name should ask rather than assume, because the prefix is a setting and `wirekit` is only its
  default.
- **[Localization](https://docs.wirekit.app/localization) no longer reads as base-language only.**
  The page described the shipped catalogs without saying that a regional variety is a catalog of
  its own, which is the half a developer needs before choosing a locale string.

## [2.32.1] — 2026-08-17

**Patch — a component and your own `style` no longer overwrite each other, a seeded value survives a
Livewire morph, today is announced in the calendar, and two catalog strings say what they mean.**

Fixes only. Nothing here adds or removes API, and every change is backward compatible — though two
of them change what a page renders, which is the point: in both cases it was rendering the wrong
thing quietly.

### Fixed

- **A component and a passed-in `style` attribute no longer cancel each other out.** Several
  components wrote their own inline `style` beside the attribute bag. As soon as a `style` was
  passed in, the element carried two of them — and a browser keeps the first and drops the rest,
  with no error and no invalid-markup complaint. Which half disappeared depended only on the order
  they were written in. [`aspect-ratio`](https://docs.wirekit.app/components/aspect-ratio) lost its ratio to anyone who also set a
  background; [`data-list`](https://docs.wirekit.app/components/data-list) and its items discarded the passed-in style instead.
  Both halves now survive as one declaration list, with your declaration last so a deliberate
  override still wins. The same shape is fixed in [`scroll-area`](https://docs.wirekit.app/components/scroll-area),
  [`stepper`](https://docs.wirekit.app/components/stepper), [`header`](https://docs.wirekit.app/components/header), [`badge`](https://docs.wirekit.app/components/badge) and every other component
  that had it.

- **A value seeded into `x-data` now survives a Livewire morph.**
  [`segmented-control`](https://docs.wirekit.app/components/segmented-control), [`rating`](https://docs.wirekit.app/components/rating),
  [`pricing-table`](https://docs.wirekit.app/components/pricing-table) and [`tabs`](https://docs.wirekit.app/components/tabs) interpolated a server-driven value into
  their `x-data` seed while also observing it through a data attribute. A morph rewrites `x-data`,
  so Alpine re-initializes on a DOM node that survived, and an effect queued against the pre-morph
  scope writes the old value last — the control reverted to its initial state after a server
  round-trip. The seed is now read from the attribute at init instead.

- **[`event-calendar`](https://docs.wirekit.app/components/event-calendar) announces today.** The current day was distinguished by an
  accent pill and a heavier weight and nothing else, so "this is today" reached a screen reader only
  as a color. It now carries `aria-current="date"` in the month, week and agenda views.

- **Spanish: a status tile read as an instruction.** On the success tile of
  [`status-tiles`](https://docs.wirekit.app/components/status-tiles), `OK` was translated as `Aceptar` — the verb on a confirm
  button — where the key is a status *word*. Beside a green check it asked the operator to confirm
  something. It is now `Correcto`, matching `Advertencia` and `Crítico` in the same catalog.

- **`Popover` shipped untranslated in every catalog.** The key was present with the English term as
  its value, so a completeness comparison found nothing missing while a reader saw English in an
  otherwise translated interface. It is now translated in Spanish, French, Italian and Portuguese;
  German and Dutch keep the established loanword deliberately.

- **`wirekit:csp-audit` pointed at the shape it does not hit.** Its hint about `Js::from()` said the
  encoder wraps its payload for "any non-empty string, array or object". It wraps a non-empty
  **array or object**; a string of any length — apostrophes and non-ASCII included — comes back a
  quoted literal, as do numbers, booleans, `null`, `[]` and `{}`. Acting on the old advice for a
  string meant replacing a correct encoder with hand-written interpolation that the next apostrophe
  breaks. The report now names the real trigger and exempts the safe shapes explicitly.

### Documentation

- [CLI reference](https://docs.wirekit.app/cli-reference): the `wirekit:csp-audit` section states
  the real trigger, says outright that a string is not the case to fix, and recommends
  `AlpinePayload::from()` for handing a composed payload to an Alpine directive — a helper the
  documentation had never mentioned.
- [`event-calendar`](https://docs.wirekit.app/components/event-calendar): the accessibility section documents the
  `aria-current="date"` contract across all three views.

---

## [2.32.0] — 2026-08-16

**Minor — a shared icon vocabulary that resolves the same way on every preset, an icon package you can actually install, a prop-naming reference that was wrong about six things, and a prop linter that no longer fails a build over a test selector.**

Nothing here changes what an unchanged call site renders. One command reports less than it used to, and it is the reporting that was wrong rather than the rule.

### Added

- **Eleven words the shared icon vocabulary was missing.** `chart-bar`, `trend-up`,
  `percent`, `coins`, `gift`, `map-pin`, `sliders`, `list-checks`, `list-bullets`,
  `lock-key` and `broadcast` — reporting, money, settings and location, the vocabulary a
  back-office reaches for. Each resolves on all four base presets, and each target was
  checked against the installed icon set rather than assumed. Two more were asked for and
  are deliberately absent: Heroicons has no gauge and no handshake, and an alias that
  changes the drawing when you switch presets is worth less than no alias at all — for
  those two, name the glyph directly.
  See [Icon](https://docs.wirekit.app/components/icon).

### Changed

- **`chart-bar` moved from the marketing extension into the shared vocabulary.** While the
  extension owned the name it meant `chart-bar-square` on Heroicons and nothing at all on
  Lucide, Phosphor or Tabler — so the one property a shared alias has to keep, that it
  means the same thing whichever preset you install, was the property it did not have. On
  `heroicons-marketing` the glyph is now the plain bar chart the other three presets draw.
  If you want the framed variant, name `heroicon-m-chart-bar-square` directly.

- **`wirekit:doctor:props` no longer reports a browser-test selector as an unknown prop.**
  `dusk` is written on a component precisely so that it reaches the rendered HTML, where a
  browser suite selects on it. The linter counted it as a mistake, and in a project whose
  quality gate runs the linter under `set -euo pipefail` that exit code ended the stage
  before the tests ran — seventy of one project's findings were this one attribute. The
  line is what the attribute does: an unknown prop is an instruction that disappears, a
  test selector is one that arrives where it was aimed. The same change covers the runtime
  warning, since both read one verdict. Valid HTML attributes and the `aria-` / `data-` /
  `wire:` / `x-` / `on` prefixes were already passthrough and are unchanged.
  [CLI Reference](https://docs.wirekit.app/cli-reference) lists what is never reported.

### Fixed

- **The `tabler` icon preset named a package you cannot install.**
  `ryangjchandler/blade-tabler-icons` is marked abandoned and its newest release requires
  Laravel 10 or 11, while WireKit requires 12 or 13 — so `composer require` refused, for
  every supported installation. The preset was documented, listed in the preset table, and
  unreachable. It now names `secondnetwork/blade-tabler-icons`, the maintained successor:
  same `tabler-` prefix, same alias vocabulary, ~7,200 icons, and it tracks Tabler's own
  version line. Nothing changes in your templates —
  `<x-wirekit::icon name="user">` on the `tabler` preset resolves exactly as before.
  [Icon](https://docs.wirekit.app/components/icon) names the new package.

- **`megaphone` was broken on the `tabler` preset.** It mapped to `tabler-megaphone`, which
  does not exist — Tabler calls that glyph `speakerphone`. Blade Icons throws on an unknown
  name, so the page broke rather than degraded. It survived because the preset's package
  could not be installed, which meant nothing could check its 94 targets; all four presets
  are verified now, and every other alias across them was already correct.

- **`wirekit:publish-icons lucide` reported success while publishing the wrong thing.** The
  command looked for SVGs in a directory the package emptied when it reorganized — the
  folder still exists, so the check answered yes and the copy produced
  `icons/lucide/icons/…` where every other preset gives you `icons/lucide/…`. The source
  directory is now located by looking for the files rather than by a written-down path, so
  an upstream reorganization cannot silently change what you get.

### Documentation

- **The CLI reference documented an exit code no command returns.** It described a
  three-value convention and gave exit `2` its own row and its own meaning, plus two more
  mentions under individual commands. Every `wirekit:*` command exits `0` or `1` — including
  on rejected input — and has done since that was standardized. A CI step written as
  `if [ $? -eq 2 ]` could never fire, and `1` read as narrower than it is. The reference now
  states the two-value contract and says outright that there is no exit `2`.
  [CLI Reference](https://docs.wirekit.app/cli-reference).

- **A recipe's dark band did not follow the theme.** The marketing landing-page recipe
  painted its hero with raw hex — a near-black gradient with white text, plus three sparkline
  strokes in literal red, green and gray. Copied into an application, that band stayed
  exactly as light in dark mode as it had been in light mode while everything around it
  moved. It is tokens now, so the band belongs to whichever theme is active.

- **The prop-naming reference was wrong about six things, on the page that exists to
  settle them.** It named a component this package does not have, filed two components
  under a prop they do not declare, classified `alert` and `callout` as surface-treatment
  while both resolve `variant` into the color axis, promised a stable seven-value intent
  enum that `button` does not accept, and described a v3 plan whose canonical surface axis
  was the wrong name. It also named one component as keeping a back-compat alias while
  seven do — which is how a reader plans a one-component migration for a seven-component
  change. The page now carries the full alias matrix, the accepted values per component,
  and the v3 mapping split into the rows that are a plain prop rename and the rows that
  also rename a value. The release date stays open.
  [Prop Naming Conventions](https://docs.wirekit.app/extending/prop-naming-conventions).

- **The integration guide says that `x-cloak` is covered.** Alpine hides nothing for that
  attribute — it removes it once initialized, and the hiding has to come from CSS. WireKit
  ships the rule and `@wirekitStyles` delivers it, which was true and written down nowhere.
  The guide now also says why looking for it comes up empty: the stylesheet arrives as its
  own `<link>`, so the rule is never in your Vite bundle and grepping `app-*.css` finds
  nothing whether your setup is right or not.
  [Integration](https://docs.wirekit.app/getting-started/integration).

## [2.31.0] — 2026-08-16

**Minor — a Brazilian Portuguese catalog, a font of your own that no longer moves the page, a CSP audit you can act on without checking it first, and six pages that were teaching the version before this one.**

Nothing here changes what an unchanged call site renders. Two commands report more than
they used to: `wirekit:csp-audit` gained a warning class that leaves your exit code alone,
and `wirekit:verify` compares every published bundle instead of four.

### Added

- **A tab bar the server drives, with no panels — `<x-wirekit::tabs.list>` and
  `<x-wirekit::tabs.tab>`.** `tabs` assumes the browser already holds each panel's
  content, which is the wrong shape for the arrangement a Livewire application reaches
  for first: a row of tabs above content the *server* renders, where choosing one is a
  round trip. Two of the things the full component does are actively wrong there — it
  holds the selection your server has already decided, and it emits `aria-controls`
  pointing at panels that do not exist, sending a screen-reader user somewhere there is
  nothing. The bar on its own holds nothing at all: `selected` is a plain server-side
  boolean that drives both `aria-selected` and the roving `tabindex`, and the keyboard
  handlers resolve the tabs from the DOM on every keypress, so Livewire replacing the
  markup cannot leave focus pointing at an element that no longer exists. Activation is
  manual — arrows move focus, `Enter` or `Space` commits — because selection following
  focus would fire one request per arrow key and render four pages nobody asked to see.
  Both bars are styled from one source, so `variant` and `orientation` mean the same
  thing in either. See [Tabs](https://docs.wirekit.app/components/tabs).

- **Brazilian Portuguese.** `pt` is European Portuguese, and regional locales resolve
  through their base language — so `pt-BR` was rendering fluent Portuguese of the wrong
  variety. A Brazilian reader saw `(abre num novo separador)` where they expect
  `(abre em uma nova aba)`, and `A carregar` where they expect `Carregando`. Nothing failed:
  the page rendered, the screen reader read it out, and only a native speaker looking at the
  right string would ever have caught it. `pt-BR` now ships as a delta over `pt` — it holds
  only the strings the two varieties spell differently, so the shared wording stays in one
  place and cannot drift apart. [Localization](https://docs.wirekit.app/localization) names
  the variety of every catalog, so you can decide deliberately rather than inherit one.

- **A font you host yourself can get a metric-matched fallback.** Every bundled family ships
  one — a local system font registered under the family's own name with the web font's
  measured metrics, so text painted before the swap occupies the same box as text painted
  after it. Your own family got none of that, which is the setup `null` is for. Declare the
  four measured values in `wirekit.fonts.fallbacks` and WireKit emits the face. Empty by
  default and nothing is invented: a guessed `size-adjust` moves the layout in the *other*
  direction and looks deliberate while doing it. The method, including the two parts that are
  easy to get wrong, is on the [Fonts](https://docs.wirekit.app/components/fonts) page.

- **The font component's inline `<style>` can carry a CSP nonce.** It is the only inline style
  WireKit emits, and it has to stay inline — the three custom properties come from your font
  configuration, not from our build. Without a nonce you cannot drop `'unsafe-inline'` from
  `style-src`, and CSP Level 2 makes that a cliff rather than a slope: a nonce anywhere in a
  directive makes the browser ignore `'unsafe-inline'` in that same directive, so this block
  loses its permission the moment you add a nonce for anything else. Silently — the page
  renders and your typography falls back to the system font. The value resolves itself from a
  `csp-nonce` container binding or `Vite::cspNonce()`, so an application that already has one
  needs no configuration; an explicit `:nonce` still wins.

- **`status-tiles` items can state the word they report.** The visible status word was derived
  from `intent`, which has five values — so a domain with more states folds two onto one word,
  and the pair it folds is usually the one carrying the information. A health check that ran
  and found a problem and one that crashed are both `danger`, and both read "Critical": the
  first points at your application, the second at your monitoring. Pass `status` per item and
  the tile says your word, in the caption and in the screen-reader text alike.

- **`stream` gained `replace(text)`.** `push()` cannot express a rendering derived from the
  whole stream rather than accumulated from its parts — masked text where a placeholder breaks
  across a chunk boundary, incremental Markdown where a closing fence changes what came before
  it, anything that diffs. Setting `text` directly looks like the workaround and quietly skips
  the reduced-motion buffer; `replace()` takes the same path a push takes.

- **The sidebar item's icon size can be personalized.** It was a literal in the render call,
  so the only way to resize it was to take over the surrounding block — and a taken-over block
  stops inheriting improvements silently, which is the one outcome the closure form exists to
  avoid.

### Changed

- **`wirekit:csp-audit` no longer reports a bare PASS for an expression it did not fully
  measure.** Blade renders before Alpine reads, so an attribute carrying `{{ … }}` is checked
  with an identifier standing in for the part the audit cannot see. That substitution was
  invisible, and it hid the case that matters: `Js::from()` — Laravel's attribute-safe encoder,
  and the documented way to hand data to Alpine — renders to `JSON.parse('…')`, and `JSON` is
  precisely the name the CSP evaluator cannot resolve. The audit rejected that call written out
  by hand and passed it written the way everybody writes it, in the same file on the same line.
  A run now counts the expressions resting on a substitution, names the ones that call
  `Js::from()` or `@js()` so you can look at those first, and qualifies its verdict instead of
  claiming everything resolves in scope. Your exit code does not change — the same encoder emits
  a plain literal for a number, a boolean, `[]` or `{}`, and a violation that turns out to be
  nothing costs the next hundred their credibility.
  See [CLI reference](https://docs.wirekit.app/cli-reference).

- **`wirekit:csp-audit` warns when an expression resolves but may not evaluate.** Alpine's CSP
  evaluator refuses a *value*, not a name: it throws on any property access whose result is one
  of `globalThis`'s own. So a chain can parse, resolve every identifier, run, and be rejected
  the moment it touches something global — `$el.ownerDocument.location` reaches
  `window.location` by another route. Reported as a warning that leaves your exit code alone,
  because the rule approximates a question about runtime values. The output also names what it
  measured, so a pass is not read as more than it is, and the
  [CLI reference](https://docs.wirekit.app/cli-reference) documents the supported shape.

- **`wirekit:verify` compares every published bundle, and reports a config option by name.**
  The freshness check compared four; every bundle added since inherited nothing, so an upgrade
  could leave six stale files behind a clean report. It is derived from the package now. The
  config check also names the missing options and prints each default instead of counting
  them, and reports an option your file still carries that this version no longer offers.

### Fixed

- **`wirekit:csp-audit` read a bare `:` on a `<livewire:…>` tag as an Alpine expression.** On a
  component tag that is Blade's prop binding and its value is PHP, finished on the server. The
  scanner knew that for `<x-…>` only. Measured in a real application: 13 of 18 reported
  violations were false, and 12 of those were this one construct.

- **`wirekit:csp-audit` judged a Livewire action as written, not as Livewire presents it.**
  `wire:click="confirm"` is rewritten to a call on the component proxy before Alpine sees it,
  so a method whose name collides with a browser global was reported as a dead handler while
  working perfectly.

- **`countdown`'s `locale` prop did nothing.** The `x-data` object declared the key twice, and
  a JavaScript object literal keeps the last one — so the line that read the prop was dead and
  `locale="fr-FR"` on a German page counted in German.

- **The unknown-prop check now reaches sub-components.** It covered the top level and none of
  the 76 sub-component views — `accordion.item`, `table.th`, `list.item`, `field.*`,
  `dropdown.panel` and the rest, which are exactly the tags you write in a loop.

- **An overlay container you write yourself is finished, not just adopted.** WireKit creates
  `#wk-overlay-root` and names it in the page's language; one already in your markup was
  handed straight back without a role or a label. Writing it yourself is the sturdier route —
  it sits in the markup Livewire morphs — so the localization was reaching everyone except the
  readers who had built the better setup. A label you wrote is left alone.

- **`wirekit:csp-audit` said "bridge" when it meant "node".** A missing executable does not
  throw, so the branch written for it never ran and the message never contained the word
  `node`. It now says so before the process starts, and the dependency is named in the
  command's own description.

### Documentation

- **Six pages were teaching the version before the one that linked them.** All six had the same
  shape: the reference table was maintained and the prose around it was not.

- **[Fonts](https://docs.wirekit.app/components/fonts)** recommended writing your own
  `font-display` rules — the exact hand workaround `wirekit.fonts.display` had replaced, and
  rules that land on the same faces as the metric-matched fallbacks that are the actual fix.

- **The [CLI reference](https://docs.wirekit.app/cli-reference)** framed the CSP audit as a
  grammar check alone, which is precisely the half that does not explain why a passing build
  had started failing. It now says what the command looks at, and what it deliberately does
  not.

- **The script-order rule named the wrong invariant.** `@wirekitScripts` must RUN before Alpine
  starts — which its own `defer` guarantees in a Livewire layout, so the order of the two
  directives is not the lever. The troubleshooting table sent you to check a tag order that was
  already correct, ruling out the wrong hypothesis and keeping the right one.

- **[Scroll area](https://docs.wirekit.app/components/scroll-area)** documents that `start` and
  `end` are the reading edges, so a horizontal fade follows the document direction.

- **[Table](https://docs.wirekit.app/components/table)** documents the edge hint that tells a
  reader the row continues before they touch it.

- **[Integration](https://docs.wirekit.app/getting-started/integration)** documents the overlay
  container, the `data-wk-overlay-label` attribute, and `wirekit.assets.middleware` — the last
  of which the previous release's own entry had linked to a page that never named it.

---

## [2.30.0] — 2026-08-14

**Minor — a personalization that keeps inheriting, an error message that finally names its own cause, and asset replies that are safe to cache where they claim to be.**

Nothing here changes what an unchanged call site renders. The asset routes change which
middleware they run; see the entry below if your application relies on `web` applying to
them.

### Added

- **A mistyped prop now tells you, on nearly every component instead of a handful.** Blade
  folds a name a component does not declare into the attribute bag, where it renders as a
  literal HTML attribute nothing reads — the page looks finished and nothing fails. WireKit
  has warned about that in development since v2.6, with a did-you-mean hint, but only a
  handful of components asked for the check. It now covers essentially the whole catalog. The
  warning is development-only and silent in production, and the list of accepted names is
  read from the component itself, so it cannot fall behind the component it describes.

  Two things it deliberately does not flag: any valid HTML attribute, and framework wiring
  (`aria-*`, `data-*`, `wire:`, `x-`/`@`/`:`, `v-`, and the `on*` event family). A name a
  component reads through `@aware` — `announce-errors` on any form control, for instance —
  counts as known too. That last part is also a fix: those calls were reported as typos
  before, on the components that already had the check.

  `ComponentRegistry::extractAwareProps()` exposes the `@aware` half for tooling that needs
  it. It stays separate from `extractProps()`, which continues to mean "the props this
  component declares".
  [Customization](https://docs.wirekit.app/customization)

- **A class block can state its delta instead of taking the block over.** Personalizing a
  block has always meant restating it in full, and from that moment your application owns
  it — every later improvement WireKit makes to that block stops arriving, silently,
  because nothing is broken. A block value may now be a closure, and it receives the class
  string WireKit ships:

  ```php
  WireKit::personalize('sidebar.item', [
      'base' => fn (string $vendor): string => $vendor.' rounded-none',
  ]);
  ```

  You are not limited to appending — the point is that you can see what you are overriding,
  so you can swap or drop one utility and keep the rest. Works on `personalize()` and
  `scope()`. A plain string still replaces the block outright, unchanged, and is still the
  right choice when you do mean to own the slot. Not available for class overrides written
  in `config/wirekit.php`: a closure there cannot survive `config:cache`, so it would work
  in development and vanish on the first cached deploy.
  [Customization](https://docs.wirekit.app/customization)

### Fixed

- **The scroll-area edge fade pointed the wrong way in right-to-left layouts.** `fade="start"`
  and `fade="end"` name the reading start and end, but the horizontal mask was emitted with
  physical directions, so in an Arabic or Hebrew document the two were inverted: the fade sat
  on the edge the reader had already passed while the edge that actually scrolls stayed hard.
  Nothing failed and nothing logged — the hint simply pointed backwards. `fade="both"` is
  symmetric and was never affected, which is why the most-viewed example could not show it.
  [Scroll Area](https://docs.wirekit.app/components/scroll-area)

- **The asset routes no longer set a cookie on a response they ask a cache to keep for a
  year.** The routes serving WireKit's own CSS, JS and fonts were registered inside the
  `web` middleware group. Their handlers read a file from the package directory — no
  session, no CSRF token, no authentication, no route-model binding — so the group gave
  them nothing, while `StartSession` added the session cookie to every reply. Those replies
  declare `public, max-age=31536000, immutable`: a shared cache is invited to keep them for
  a year and may hand one to the next visitor with the cookie still attached. That most
  shared caches refuse a response carrying `Set-Cookie` is a per-vendor default you do not
  control, not a property of what is sent. The practical cost was the reverse of what the
  header was written for — a CDN could not cache the files the year-long directive exists
  to let it cache, and every stylesheet hit paid for a session read and write.

  The group is now taken from `wirekit.assets.middleware`, which defaults to none. If your
  application applies its own middleware everywhere — a security-header layer, HTTPS
  enforcement — name it there; anything that starts a session brings the cookie back with
  it. Published assets served from `public/vendor/wirekit/` never used these routes and are
  unaffected.
  [Integration](https://docs.wirekit.app/getting-started/integration)

- **A bundle that loads too late now says so, instead of leaving you with someone else's
  error.** If the WireKit bundle is loaded after Alpine has already started, every
  component already on the page never receives its Alpine data. Those elements cannot be
  revived by registering afterwards, and what reached you instead was the browser
  complaining about the *bindings inside them* — `startShadow is not defined`, a property
  of a component you never wrote, from a file you did not author. Because tables are
  responsive by default, one ordinary `<x-wirekit::table>` was enough to produce it on a
  page whose author had never heard of a sticky panel. Every bundle now reports the real
  cause once, with the remedy, and only when there is actually affected markup on the page.
  [Integration](https://docs.wirekit.app/getting-started/integration)

### Documentation

- **[Design Tokens](https://docs.wirekit.app/theming/design-tokens) is now actually complete.**
  The page opens by calling itself the complete CSS-variable surface, and 69 of the 237
  declared tokens were not on it — among them `--space-wk-md`, the whole
  `--size-wk-container-*` ladder, both halves of the inverse color pair, every
  `--shimmer-wk-*`, and 26 tokens of the reading family. Looking one up returned nothing,
  with no way to tell "not a token" from "not written down". All of them now have a row,
  and a check keeps the claim honest as tokens are added.

- [Customization](https://docs.wirekit.app/customization) explains when to adjust a block
  rather than replace it, and why the choice affects whether you keep receiving upstream
  changes to that block.

---

## [2.29.0] — 2026-08-13

**Minor — the navigation that took the page with it, and four checks that could not fail.**

Nothing here changes what an unchanged call site renders. The additions are things the
browser and the screen reader were told nothing about until now.

### Added

- **Native widgets follow your dark theme.** The `<select>` popup, scrollbars, number
  spinners, date and color pickers, autofill and the spellcheck menu are painted by the
  browser rather than by any stylesheet, and the browser had no way to know the page was
  dark — so it painted them light, on a page that was not. WireKit now declares
  `color-scheme`, so they follow. The native `<select>` is the one this matters most for,
  because using it is a deliberate choice here: it costs no JavaScript and gives a real
  picker on a real phone, and its popup was the one surface a screenshot of our own
  components would never show. [Theming](https://docs.wirekit.app/theming)

- **A regional locale reaches its language.** `pt-PT`, `pt-BR`, `de-AT`, `es-MX`, `fr-CA` —
  every regional variant previously saw none of the shipped translations, because the JSON
  loader matches a locale's filename exactly and there is no step from `pt-PT` to `pt`. An
  application running regional locales got English chrome inside a fully translated page,
  with nothing to indicate why. They now resolve through the base language, and your own
  catalog still wins per key, so you can reword one regional phrase without re-translating
  everything around it. [Localization](https://docs.wirekit.app/localization)

- **The overlay landmark is announced in the reader's language.** Every teleported panel
  lives inside one labeled region, and that label was the English word `Overlays` in every
  locale — invisible on screen, and therefore visible only to the readers the landmark was
  built for. It now follows the page's locale. A language WireKit does not yet ship keeps
  the English name rather than losing it: a region announced as "region" and nothing else
  helps nobody.

- **Two words the commerce vocabulary was missing.** `stack`, and `cash-register` — the
  till the receipts come from, next to `receipt`, `barcode` and `truck` which were all
  already there. A name outside the vocabulary resolves through whichever icon set happens
  to be installed, so it works until the day you change one; `stack` did not even do that,
  because two of the four sets ship no glyph of that name and the icon package throws on a
  name it does not know. [Icon](https://docs.wirekit.app/components/icon)

- **Self-hosted fonts no longer move the page when they arrive.** Every bundled family now
  ships a metric-matched fallback face: a local system font registered under its own name
  with the web font's own measurements, named in the stack ahead of the generic families.
  Measured on a 400px column of prose, the unadjusted fallback rendered the block a full
  line shorter than the web font — that shift is now zero. The four override percentages
  are read out of the shipped font files rather than estimated, on both sides of every
  ratio. [Fonts](https://docs.wirekit.app/components/fonts)

- **`font-display` is yours to choose.** `wirekit.fonts.display` sets what every bundled
  `@font-face` is served with; the default stays `swap`, which now costs no layout shift.
  Pick `optional` if you would rather some readers never see the web font than see it
  arrive late — that is a real trade, and it is your application's to make. It reaches the
  package route and `wirekit:publish-fonts`; a plain `vendor:publish` copies files
  verbatim, so when that leaves a published copy disagreeing with your config, the fonts
  component says so in the page source and `wirekit:verify` reports it.

- **`<x-wirekit::tabs>` can be driven from the server.** The new `active` prop is the
  active tab as the server sees it, and unlike `default` it keeps arriving: a tab restored
  from a URL, a validation error whose field lives in another panel, or a permission that
  just changed can all move the tablist. Previously the only route was to hand-build the
  widget, which meant re-implementing its keyboard and ARIA behavior.
  [Tabs](https://docs.wirekit.app/components/tabs)

- **`<x-wirekit::pricing-table>` tells your application which interval was chosen.** A
  hidden input carries the choice into a surrounding form, `wire:model` binds it, and the
  new `interval` prop lets the server set it — so a checkout decided server-side can both
  read the choice and correct it. [Pricing table](https://docs.wirekit.app/components/pricing-table)

- **`<x-wirekit::stream>` can render your markup instead of its own.** The new `output`
  slot replaces the component's output block while keeping the state machine and the
  announcement contract, so a chat bubble or a syntax-highlighted pane can use it as a
  pure transport. The default slot is unchanged and still additive.
  [Stream](https://docs.wirekit.app/components/stream)

- **`<x-wirekit::status-tiles>` can let a caption wrap.** `wrapMeta` turns the one-line
  clamp on the `meta` line into wrapping text, for the case where the caption is the
  message rather than a two-word count. Off by default, because a wrapping caption grows
  the whole grid row. [Status tiles](https://docs.wirekit.app/components/status-tiles)

- **Seven words the icon vocabulary was missing.** `system` names the third theme state
  beside `sun` and `moon`; `code`, `key`, `rocket-launch`, `chart-line`, `folders` and
  `phone` fill gaps that showed up as a name with no answer. `key` and `rocket-launch`
  were previously reachable only by stacking an extension preset, so they are now
  available on every icon set. [Icon](https://docs.wirekit.app/components/icon)

- **Disclosure animations work outside Livewire.** Four components ask for Alpine's
  collapse directive and nothing registered it, so in an Alpine-only application the
  region snapped open instead of animating and each element logged a warning. It is now
  registered by every full-catalog bundle, at a cost of roughly 600 gzipped bytes.

### Changed

- **`wirekit:csp-audit` now checks more, and will fail builds it used to pass.** Read this
  one before upgrading: nothing about your templates has changed, but the command was
  reporting a pass over two things it never looked at.

  It checked whether an expression parses under Alpine's grammar, and stopped there. Under
  a strict policy an identifier is resolved against the Alpine scope alone — there is no
  `window` fallback — so an expression can be flawless grammar and still throw on the
  first name it reads. `JSON.parse('…')`, which is what Laravel's `Js::from()` emits for
  anything but a scalar, is exactly that shape: it passed the audit and threw in the
  browser, leaving the element with an empty scope and every directive on it silently
  dead. It also scanned no `wire:` attributes at all, though Livewire hands most of their
  values to the same evaluator.

  Both are now covered. A pipeline that is green today can go red on upgrade — it was
  green about something that does not run.

- **`wirekit:doctor:props` and `wirekit:doctor:a11y` refuse to pass over nothing.** A
  mistyped path produced "no issues found across 0 templates" and exit `0`. Reading zero
  files is now a failure, because a linter that read nothing and reported clean is worse
  than no linter — you stop looking. Templates that exist but use no WireKit component are
  a different, honest result and still succeed, saying the count out loud.

### Fixed

- **A `wire:navigate` no longer takes the whole page with it.** Since 2.27.0 every overlay
  panel teleports into a landmark container built by the bundle. A navigation replaces the
  document body, the container left with it, and the framework treats a teleport whose
  target is missing as fatal — so initialization stopped at the first overlay on the
  arriving page and everything after it was never wired up. The result is the expensive
  kind of broken: the page renders completely, every control is visible, and not one of
  them does anything. There is no error a reader would see and nothing that looks wrong in
  a screenshot.

  The obvious repair does not work, and finding that out was most of the fix. The event
  that announces a completed navigation fires *after* the framework has already walked the
  new document, so rebuilding the container there restores it for the next overlay and
  leaves the failure exactly where it was. The container is now rebuilt in the window
  between the body swap and that walk. Covered by a case that navigates twice and asserts
  the arriving page is still interactive, not merely quiet.

- **A page-wide script failure could no longer start with a color.** Several places
  assumed a document body was present before touching it — the overlay container, the
  chart adapters' theme probe, its color-variable resolver, the optimistic announcer, and
  two class-attribute observers. An uncaught error there does not cost the thing that
  failed; it ends the evaluation of the bundle, and everything registered after it stops
  existing. Each of these now yields a sensible default and stays quiet, and a check holds
  the whole class rather than the sites that were reported.

- **A menu that stayed dead after an update, in three more components.** 2.28.0 fixed this
  for the dropdown. The same shape survived on the tooltip, the combobox and the
  multi-select: a teleported panel identified by an id that changes on every render is
  replaced rather than patched by a Livewire update, the replacement has lost its
  component scope, and the expression deciding whether it is open resolves against the
  browser's global object — where `open` is a real function. Calling it raises
  `Illegal invocation` from a stack that names nothing. All four panels now carry a stable
  key, and a check fails the build on a teleported panel that has an id and no key.

- **`wirekit:doctor` no longer reports a token asymmetry that is not there — and can now
  report one that is.** The symmetry check located `:root` and `.dark` by searching for
  the text, so `.dark` also matched inside `html.dark` and `.dark-mode`. An application
  that writes its theme class on `<html>` — the usual arrangement for setting it before
  the first paint — was told all of its color tokens were missing from dark mode, and
  advised to duplicate declarations it already had. Worse in the other direction: the
  `@custom-variant dark` line the integration guide asks every application to write also
  matched, and made the two sides look identical, so a real gap could never be reported at
  all. The selector is now matched as a selector, a shared `:root, .dark { … }` head counts
  for both sides, and a rule nested in a layer or a media query is still found. The check
  also says when it skipped, instead of falling silent in a way that reads like a pass.

- **`wirekit:csp-audit` no longer crashes on an ordinary comment — and no longer misses
  expressions because of one.** An apostrophe inside a Blade comment placed among a tag's
  attributes was read as the start of a quoted value. With an odd number the scan ran past
  the end of the file and the command failed with an internal error, before it could even
  tell you the CSP build was not installed. With an even number there was no error at all:
  the span between them was swallowed and every Alpine expression inside it went
  unreported — silence from a command whose entire purpose is finding failures that are
  silent. Both halves came from one hand-written scanner that a sister scanner had already
  been fixed for; there is now one, and all three places that read Blade tags use it.
  `wirekit:show --validate-against` was the third, and it was quietly wrong in its own way:
  it read words out of expression text as if they were attributes.

- **`vendor:publish --tag=wirekit-lang` hands over every catalog.** It named English and
  German because those were the two that existed when it was written; five more shipped and
  it kept handing over two, while the documentation promised seven. Nothing failed — a
  publish list cannot notice a file it was never told about. It is now derived from what
  ships, so the next language needs no edit.

- **A blank reserved for a validation message is no longer selectable.** The spacer that
  keeps a field from shoving its neighbors around, and the reserved message row on input,
  select and textarea, each render a non-breaking space that exists only to hold height.
  Dragging a selection across a form collected one of them per reserved field and carried
  them into the clipboard. They are marked unselectable now, which is the pointer half of
  the decision that already hid them from screen readers.

- **`wirekit:csp-audit` reads what the browser reads, not what Blade left behind.** Server-side
  constructs are removed before the expression is parsed — a comment outright, an escaped or raw
  echo and `@js(…)` as a placeholder, with the parentheses of `@js(…)` balanced across strings so
  a payload containing `)` does not truncate the rest. Before this, `@js(…)` — the pattern this
  very command recommends in its own advice — was reported as blocked with a syntax error at the
  `@`, and a raw echo carrying JSON was reported as a broken object literal. Neither is anything a
  reader could act on: by the time Alpine sees the attribute, the server text is gone.

  Two more corrections in the same area. `x-teleport` is no longer scanned at all: its value goes
  straight to `document.querySelector()`, so it is a CSS selector, and a selector beginning with
  `#` was being reported as an expression beginning with an operator. And a rejection now counts
  as a finding only when the parser saw what the browser sees — an expression the audit could not
  read is listed separately and does not fail the run, because "the tool could not check this" and
  "Alpine will refuse this" are different facts and only one of them is yours to fix.
  [CLI reference](https://docs.wirekit.app/cli-reference)

- **The setup guide listed five CSS features as safe to rely on that are not.** `@starting-style`,
  `field-sizing: content`, `text-wrap: balance`, native CSS nesting and `round()` all require
  browsers newer than the supported floor, so an application taking the sentence at face value
  would ship something a reader on a supported browser does not get. They are now named for what
  they are — worth using behind a feature test — and where WireKit uses one itself, it already
  sits behind one. Every version in the correction was read from browser-compatibility data rather
  than recalled. [Integration](https://docs.wirekit.app/getting-started/integration)

- **Two field-composition recipes in the documentation did not do what they said.** The
  pattern for putting a button beside a labeled field described a wrapper that produces a
  different vertical rhythm than the one the field itself uses, so following it left the
  control a hair off. Corrected, and pinned by a check so the page and the component cannot
  drift apart again. [Field](https://docs.wirekit.app/components/field)

- **A table that scrolls sideways shows its edge hint before you scroll.** The hint is the
  only sign that more columns exist, and it was appearing only after the first scroll —
  after the moment it is for. Its two markers sat on the wrong axis.
  [Table](https://docs.wirekit.app/components/table)

- **`wirekit:doctor:props` no longer invents props that are not there.** A Blade echo
  inside a quoted attribute value — the shape any translated attribute built from a
  variable takes — was read as more attributes, so a translation key surfaced as a prop
  the component never declared.

- **`wirekit:verify` recognizes every real form of the ApexCharts global assignment.**
  `window.ApexCharts ??= …`, `globalThis.ApexCharts = …` and the bracket form were
  reported as missing, TypeScript entry points were not read at all, and a commented-out
  line counted. In the other direction, `window.ApexCharts == null` was read as an
  assignment — a feature detect passing as a completed setup.

- **`wirekit:verify` no longer recommends putting a chart library on every page.** Its
  ApexCharts advice named only the global entry point, which is roughly 850 KB on pages
  without a chart. It now names the route-scoped alternative too, with the weight attached.

- **Six documentation pages named icons that do not exist on a default installation.** They
  rendered a hole for anyone following them without an extension preset. Two of them wanted
  a telephone, which is why `phone` is now a word.

- **The design-token reference credited the wrong spacing ladder.** The `gap` prop of the
  layout primitives resolves to the `--space-wk-*` scale, not `--gap-wk-*` — so overriding
  the latter to widen a grid changed nothing, and the page said to look there.
  [Design tokens](https://docs.wirekit.app/theming/design-tokens)

- **A 2.27.0 note credited `wirekit:verify` with a check that lives elsewhere.** Duplicate
  adapter registration is reported — by the adapter, as a browser console warning — so
  running the command and seeing nothing meant nothing. The entry now says where the report
  comes from.

## [2.28.0] — 2026-08-08

**Minor — the dropdown defect 2.27.0 shipped with, and the tooling that could not see it.**

Nothing about how you write a dropdown changes; the fix is entirely inside the component.

### Fixed

- **One more deep link in the public API map pointed at nothing.** The Alpine animation helper's entry aimed at `#wirekit-animate` while the page renders a heading that slugs to `#the-wirekitanimate-alpine-helper`. Same shape as the eleven corrected in 2.27.0, one group over — which is why the check now reads every fragment the manifest publishes rather than one group of them.

- **A dropdown trigger announces a panel that is actually there — and writing that relationship no longer paints a menu nobody opened.** Both halves were broken at once, and neither could be fixed alone.

  Since 2.27.0 the panel's `aria-controls` pointed at an element that is not in the document until the panel first opens, so a screen reader following the reference landed nowhere. The obvious repair — write the id — made a Livewire update replace the panel with a copy that had lost its Alpine scope, at which point the expression deciding whether the panel is visible resolved against the browser's global object instead. `open` is a real function there, a function is truthy, and a menu nobody had opened appeared on the page.

  The panel now carries a stable key of its own, so a Livewire update patches it in place rather than replacing it, and the id can be written safely. Both halves are covered by tests that were watched failing before the fix and passing after. [Dropdown](https://docs.wirekit.app/components/dropdown)

## [2.27.0] — 2026-08-06

**Minor — the whole triage, and what the tickets did not know.**

Every addition here is opt-in: an unchanged call site renders exactly what it did in 2.26.1.

### Added

- **[`<x-wirekit::step-marker>`](https://docs.wirekit.app/components/step-marker)** — the numbered chip a sequence of steps was being hand-rolled for. No existing primitive fits, and each near-miss misses differently: a badge is a pill, and a pill reads as a label *about* something — a status, a count, a tag attached to its neighbor. A step marker is not about the step, it **is** the step, which is what the square corners carry and why the shape is not a prop. It is filled always, and each intent's fill is paired with that intent's own foreground token in one place, because that pairing is precisely the part a call site has no way to get right: a number on a colored square is legible or it is not.

- **The three accessibility hooks have documented, guaranteed names.** `data-reduce-motion`, `data-contrast` and `--font-scale-wk` are what the stylesheet and the bundle carry literally, and `motion_attribute`, `contrast_attribute` and `font_scale_property` in `config/wirekit.php` record them so you can read them without grepping a bundle. A build check breaks if a recorded name stops matching what ships, so an application writing its own preference rules has a fixed contract to match. [Integration](https://docs.wirekit.app/getting-started/integration)

- **`safeObserver`, for the observer that keeps firing after the component is gone.** An observer's callback runs once more after Livewire morphs the node away and Alpine tears the component down — into a `this` whose fields are already null. The page fills with `Cannot read properties of null` pointing at your plugin rather than at the teardown that happened, and your browser tests go red for a reason that is not yours. `safeObserver` gives you an observer whose callback simply does not run once you stop it, including for a batch the browser had already queued. It ships with the Composer package rather than from npm, so you import it by path — there is nothing to install. It does not replace `destroy()` — nothing can — it removes the guard you would otherwise write in every callback. [Authoring custom Alpine plugins](https://docs.wirekit.app/extending/authoring-custom-alpine-plugins)

- **A type-size preference, which completes the accessibility trio.** Motion and contrast each got an override in this release; type size had none, so an application offering its own "larger text" setting had nothing to set. The browser zoom is not the same thing — it scales the whole page and reflows a dense interface into something harder to use. One custom property now moves the whole ramp: `<html style="--font-scale-wk: 1.25">`. The default is `1` and multiplying by one changes nothing, so an application that sets nothing renders exactly as before. [Integration](https://docs.wirekit.app/getting-started/integration)

- **`<x-wirekit::field.spacer>`** — the other half of `reserve-message`. That one holds the space below a control so a field does not shove its neighbors down when validation fires; this one holds the space above, so a button beside a labeled field starts where the input starts instead of a label-height too high. Neither `items-end` nor `items-start` solves it — the first follows the field as it grows, the second lines up with the label. It renders the real label rather than a copy of its classes, so it stays exactly as tall when the typography changes. [Field](https://docs.wirekit.app/components/field)

- **`php artisan wirekit:csp-audit`** — checks every Alpine expression in your own Blade views against Alpine's real CSP grammar. Under a policy without `'unsafe-eval'` an expression outside that grammar is never evaluated: nothing throws, nothing logs, and the page looks correct while the control is dead — so there is no symptom to notice and the check has to be mechanical. The verdict comes from Alpine's own parser rather than a pattern list, because the grammar is wider than it looks and judging by eye over-reports badly. It exits non-zero when it found nothing to scan, too: an audit that measured nothing must not report a pass. [CLI reference](https://docs.wirekit.app/cli-reference)

- **`optimisticArgs` on every component that supports optimistic UI.** The optimistic layer always spread `args` into the action call and no component exposed it — so the commonest optimistic surface there is, a list of identical controls with one per row, had no way to say which row it meant, and the only path was to hand-mount the factory and give up the component. [Optimistic UI](https://docs.wirekit.app/extending/optimistic-ui)
- **`start(payload)` and `setBody()` on `<x-wirekit::stream>`.** The `fetch` request body was read once at mount and could not be changed, so a stream could only ever send the payload it was born with — while the case this transport exists for rebuilds its body every run. `startMessage` / `readyMessage` / `stoppedMessage` / `failedMessage` are now props as well.

- **Seven locales ship, not two.** Spanish, French, Italian, Dutch and Portuguese join English
  and German. Set your app's locale to any of them and every string WireKit renders on your
  behalf follows — no publishing step, no configuration. Authored rather than machine-translated,
  and checked against the reference catalog key by key: nothing missing, no dropped `:placeholder`,
  no broken plural form. See [Localization](https://docs.wirekit.app/localization).
- **An application can ask for increased contrast, or decline it.** `data-contrast="more"` on
  `<html>` lifts the muted end of the palette — helper text, placeholders, borders, the values a
  reader with low vision loses first — and `"no-preference"` holds the default even when the OS
  asks for more. Without the attribute the OS decides, as before. This completes the set: motion,
  color scheme and contrast can now each be stated by the application rather than only by the
  system.
- **New icon aliases**, so a page can name what it means instead of a glyph:
  `envelope`, `user-add`, `user-remove`, `building`, `webhook`, `arrow-left`, `clock`, `lock`,
  and a commerce set — `store`, `cart`, `receipt`, `truck`, `package`, `barcode`. Every alias
  resolves in all four icon sets. See [Icon](https://docs.wirekit.app/components/icon).
- **`WireKit::isIconAlias()` and `WireKit::iconVocabulary()`** answer a question `WireKit::icon()`
  cannot: whether a name is one WireKit *declares*, rather than one that merely renders. `icon()`
  always returns something — an unknown name falls through to the icon set's own naming — so it
  tells you what will appear, never whether the name was recognized. Useful for editor completion
  and for checking a design system's names against the ones that exist.
- **`error` (and `hint`) on `slider`, `range-slider`, `rating` and `segmented-control`.** All four
  took a `label` and none took an `error`, so a validation message passed to them was silently
  dropped into the attribute bag. They now render it, announce it politely, and wire
  `aria-invalid` + `aria-describedby` to the element the message is about.
- **`wirekit:doctor:props`** finds a misspelled prop before a page renders. An unknown prop is not
  an error at render time — Blade passes it through and the component uses its default — so
  `<x-wirekit::button intnet="danger">` renders a perfectly good button in the wrong intent. The
  linter is static, so it covers templates nobody has opened yet.
- **`variant="flush"` on `table.head`** drops the header fill and keeps the divider and the
  sticky behavior. Previously the fill could only be removed by overriding the whole class
  string, which discarded both.
- **`zoneInset` on `sidebar`** stops the head and foot zones from insetting content that already
  insets itself — a `sidebar.item` in a zone was indented twice.
- **`name` on `dropdown`** pins the panel id, so it survives a Livewire round trip verbatim.

### Fixed

- **A [`segmented-control`](https://docs.wirekit.app/components/segmented-control) inside a form submitted nothing while the page showed a choice.** Its hidden input was filled from JavaScript, so between the server render and Alpine booting the field was empty with the selected segment highlighted right beside it — and a submit in that window sent no value at all. Under a Content-Security-Policy that blocks Alpine's evaluator, that window is the entire session: nothing 404s, nothing throws, and the form is silently empty every time. The field now carries its value from the server render, read from the same expression that seeds the client state so the two cannot disagree.

- **The [`command-palette`](https://docs.wirekit.app/components/command-palette) reopened showing the previous search.** Closing and reopening cleared the visible input but told the host nothing, so a server-driven palette kept the last query alive: type "strategy", close, reopen, and the field is empty while the results still answer a word the reader can no longer see. The empty query is now announced at the moment the empty field appears. Its results list also takes a height override like every other part of the panel — the 288px it was fixed at is a reasonable default and a poor ceiling for ranked results on a large screen.

- **Opening a WireKit asset URL directly no longer garbles its text.** The nine `dist` routes did not declare their character set, so a browser hitting `/wirekit/wirekit.css` on its own fell back to a legacy single-byte encoding and every em-dash rendered as mojibake. Loaded from a page the file inherits the document's encoding, which is why this was invisible in every application and visible only on the direct hit.

- **`wirekit:verify` now checks the ApexCharts adapter wherever it is switched on.** The check only ran when `charts.library` was `apexcharts`, while `scripts.apex` emits the adapter independently — so the configuration that ships the adapter with the library set elsewhere went entirely unexamined, and every chart stayed blank while the installation was reported healthy. It also asks the harder question now: finding the package in `package.json` proves it can be resolved, not that it reaches the page. Registering the adapter twice is reported as well — by the adapter itself, as a console warning in the browser, not by this command.

- **Eleven CLI deep links in the public API map pointed at nothing, and one command in it was never part of WireKit.** The anchors were built from the command id rather than from the heading the page renders, so `wirekit:show` pointed at `#wirekitshow` while the page offers `#wirekitshow-name` — and a fragment that matches no heading does not fail: the page loads and simply does not scroll. Separately, the export kept every command whose name began with `wirekit:`, which is a naming choice rather than a proof of ownership, so a command belonging to the host application could appear in a manifest describing this package. Ownership is now decided by the class.

- **The public API map listed every recipe twice, and its `blueprints` group held no blueprints.** The group scanned its directory recursively and swept up the pages belonging to two other groups, so a tool reading the manifest saw duplicate entries under two different names and a group whose contents did not match what it is called. Every page now appears under exactly one group.

- **Picking a date range with the keyboard now shows the same preview a pointer gets.** Moving through the grid after choosing one end left the shading — and the `aria-selected` that says "commit here and this is your range" — untouched, because the preview was wired to hover alone. There is no second way to learn what a range will be before making it, so for anyone not using a pointer the feature was not harder to reach, it was absent. [Calendar](https://docs.wirekit.app/components/calendar)

- **A dropdown inside a Livewire component no longer throws on every round trip.** The panel read its id out of the Alpine scope, which is right across the teleport and wrong across a morph — so every re-render raised `panelId is not defined`. That is worth more than console noise: a JavaScript error during a morph ends evaluation at that point, so whatever the same pass would have done next silently does not happen, and nothing turns red. The id is written from the component now instead of bound, leaving no scope-dependent expression on the teleported node. Reported against 2.25 and 2.26 alike, so it was not new — only unnamed. [Dropdown](https://docs.wirekit.app/components/dropdown)

- **Streaming over `fetch` now works for an endpoint that sends more than one kind of frame.** The reader kept only `data:` lines and dropped the `event:` name, so a stream of named events — which is how Laravel's own SSE helper frames them — arrived as one run-on string with a refusal indistinguishable from a token, and not one error anywhere. Name the frame that carries text with `event-name`, and every other named frame is dispatched as a `wirekit-stream-event` on the element (with its JSON payload decoded) instead of being pasted into the output. A stream that names nothing is unaffected. See [Named events](https://docs.wirekit.app/components/stream).
- **A failed stream no longer discards what had already arrived.** `stop()` and completion both revealed the buffered text under `prefers-reduced-motion`; the failure path did not — so a response that died at 90% showed the reader nothing at all. The one terminal state where a partial answer is worth most, and the reader affected was the one who had asked for less motion.
- **Stream failures can be announced in the application's language.** The failure announcement and all five reasons behind it were English literals in the JavaScript with no way through `__()` — so an interface translated everywhere else announced its failures in English, in the moment the reader most needs to understand what happened. All six are now in the shipped catalogs (seven languages) and overridable via `failed-message`.

- **A date range now previews backwards.** Dragging toward an earlier day showed nothing at all
  until the click, while dragging forward shaded as expected. The gesture was always accepted —
  only the preview refused to show it.
- **[`calendar`](https://docs.wirekit.app/components/calendar)'s range caps follow the ordered
  ends**, so the rounding no longer lands on the wrong day while the pointer sits before the start.
- **Ten numeric props stopped discarding a deliberate `0`.** A toast asked not to auto-dismiss
  vanished after five seconds; a dropdown or popover asked to sit flush against its trigger sat
  eight pixels away. Also `carousel`'s interval, `hover-card`'s delays and offset, and
  `reading-meta`'s two reading speeds.
- **Nine labels the catalog already knew were rendered in English** regardless of locale — the
  data table's screen-reader column header, the event calendar's view switcher, the status
  matrix legend, two calendar labels, the combobox empty state, and the alert dialog's cancel.
- **An upgrade's new assets are served instead of the old published copy.** Staleness was decided
  by which file was written later, and a `vendor:publish` from the previous version can easily
  carry the later timestamp — so the old bytes shipped, with a fresh-looking cache-buster.
- **A `dropdown` inside a Livewire component survives a round trip.** Its panel teleports out of
  the morphed subtree while the id was regenerated per render, so the trigger and the panel
  stopped naming each other.
- **`wirekit:verify` no longer calls a correct setting invalid.** `scripts.bundle = 'csp'` — the
  one correct choice under a strict Content-Security-Policy — was reported as an invalid value
  with advice to undo it.
- **`wirekit:doctor`'s stale-views advice is no longer a loop.** Running the suggested
  `view:clear` produced exactly the state the warning reported.
- **`wirekit:doctor`'s typo scan can go green again.** It read the whole log history, so a typo
  fixed weeks ago kept the warning yellow for the life of the file. It now looks at a
  configurable window, 24h by default — `WIREKIT_DOCTOR_SCAN_LOGS_WINDOW_HOURS` sets it.
- **Every teleported overlay panel now lives inside a landmark.** Dropdowns, tooltips,
  popovers, comboboxes and menus lift their panel out of the document flow so an
  `overflow: hidden` ancestor cannot clip it — which also placed them outside every region on
  the page. An accessibility audit reported it on every one, and a developer could not fix it,
  because the markup is WireKit's. They now teleport into a named region WireKit creates.
- **A responsive [`table`](https://docs.wirekit.app/components/table) shows its horizontal
  scroll.** On a phone there is no scrollbar until you are already dragging, so a cut-off column
  looked like the end of the table.

### Documentation

- **A recipe for numbering a feature list.** Replacing the auto-generated icon chip with a numbered marker via `iconSlot` is the composition the new step marker exists for, and it is now written down rather than left as something to work out from two component pages. [Feature with numbered marker](https://docs.wirekit.app/blueprints/recipes/feature-numbered-marker)

- **The one-time-code page no longer reads as a rule about one-time codes.** Every preview on it used a trimmed character set, and the section above them explained why a recovery code drops `I` and `O` — so the page looked like a statement that the component removes ambiguous characters. It does not: `alphabet` is the developer's declaration, and it accepts lowercase, symbols and non-ASCII alike. A second preview now shows a permissive set beside the conventional one. Two previews, two audiences: one shows the convention, the other shows the range. [OTP input](https://docs.wirekit.app/components/otp-input)

- **A named Content-Security-Policy route for a Livewire app.** The page sent an app with a strict policy toward the self-contained CSP bundle and warned in the same breath that a Livewire app must not load it — leaving the one stack WireKit is built for with no route at all. The route is simpler than either warning suggests: the default bundle carries no evaluator of its own, so switching Livewire to its own CSP distribution is enough and WireKit needs no change. Also documents that a Livewire method named like a JavaScript keyword needs index access. [Integration](https://docs.wirekit.app/getting-started/integration)

- **[`grid`](https://docs.wirekit.app/components/grid) explains that `template` is not responsive**
  and that a strict `style-src` can drop it entirely — where other components lose a shade, the
  grid loses its whole column definition. Pass `cols` alongside to declare the fallback.
- **[`app-shell`](https://docs.wirekit.app/components/app-shell) names the coupling that silently
  disables a sticky header**: `headerPlacement="content"` puts the header inside a column that
  never scrolls, and nothing sticks to a container that does not move.
- **[`inline-edit`](https://docs.wirekit.app/components/inline-edit)** already carried its
  click-away note from 2.26.1; the reduced-motion attribute is now documented as fixed rather
  than configurable, because setting it never had any effect.

## [2.26.1] — 2026-08-04

**Patch — the day under the pointer.**

### Fixed

- **[`calendar`](https://docs.wirekit.app/components/calendar) shades the day the pointer is
  actually on.** While only the start of a `range` is set, the day under the pointer stands
  in for the end — and it was the one day in the preview with no surface: the days on either
  side of it shaded, and the one being chosen blank until it was clicked.
  It is shaded rather than filled, because a filled day would claim a choice nobody has made
  yet. A screen reader is told about it too; the two channels had failed apart.

### Documentation

- **[`inline-edit`](https://docs.wirekit.app/components/inline-edit) says what happens to the
  draft when the reader clicks away.** The page described where focus lands and stopped there,
  so the more consequential half was left to be discovered: leaving the control without
  confirming also discards what was typed. That is the same outcome as `Escape`, reached a
  different way, and it is the price of the `explicit` default — nothing is written until the
  reader confirms, so a click into the next field cannot save half a thought either.
  A round trip is not a departure, and the difference is now stated where it is relevant
  rather than only in the morph section.

- **[`grid`](https://docs.wirekit.app/components/grid) warns that `template` is not
  responsive.** `min` promises a column never overflows its container, and the page said so;
  `template` makes the opposite promise — an explicit track list is handed to CSS as written,
  so a fixed track is that wide at every viewport — and the page said nothing. The example
  beside it reserves `10rem + 8rem` before the content pane gets anything, which on a phone
  leaves the middle column a few characters wide and pushes the grid past the screen edge.
  It renders; it is simply unreadable, and nothing warns you. Now the page does, with the
  pattern to use below the width such a layout needs.

## [2.26.0] — 2026-08-04

**Minor — two column layouts the grid could not express, a switch the tooltip never had, a reorder that was announced and never built, and German.** Every addition is opt-in, with two exceptions worth stating plainly rather than leaving to be discovered:

- **A `de` locale now renders WireKit's own strings in German.** If your application's locale is already `de`, text WireKit produces on your behalf — button labels, screen-reader announcements, empty states — changes language without you asking for it. That is the point of the feature, and it is still a change to an unchanged call site.
- **[`otp-input`](https://docs.wirekit.app/components/otp-input) widens its HTML `pattern` when the alphabet is single-case.** An alphabet of `ABCDEF…` now accepts a typed lowercase `a` and stores `A`, so the rendered `pattern` attribute carries both cases where it previously carried one. The default numeric alphabet is unaffected. The fixes share a shape worth naming: each was a component that was right in the render a test looks at and wrong a moment later — a control the server had changed, a code the browser rejected only with scripting off, a field that moved its neighbors the instant validation fired.

### Added

- **[`grid`](https://docs.wirekit.app/components/grid) can count columns by the container, and set them by hand.**
  `min="14rem"` is the ordinary responsive card grid: as many columns as fit, at
  least that wide, one on a narrow screen. It is not `cols` with breakpoints, and
  the difference is the point — `cols` measures the viewport, so the two look
  identical until the container is narrower than the window (a sidebar opens, a
  split view, an embedded preview) and the breakpoint version keeps counting
  columns that no longer fit.
  `template="14rem 1fr 18rem"` takes an explicit track list, for the layouts
  `cols` cannot express at all: it only knows equal columns, and the two
  commonest application shells there are — the three-pane workspace and the week
  grid — are neither equal nor a count. Both accept any CSS length or track
  syntax; `template` wins over `min`, which wins over `cols`.

- **German ships with the package.**
  Set your application's locale to `de` and every string WireKit renders on your
  behalf follows — no publishing step, no configuration. Until now the package
  shipped the English key reference and nothing else, so each project translated
  the same strings again without being able to see the others' work. Publish the
  catalog if you want to adjust a phrase to your own voice; your copy wins per
  key. See [Localization](https://docs.wirekit.app/localization).

- **[`tooltip`](https://docs.wirekit.app/components/tooltip) can be switched off.**
  `disabled` silences it without removing it, for a control that has become
  inert. `pointer-events: none` on the wrapper is not a way to do this, however
  much it looks like one: `mouseenter` reaches every ancestor of the element the
  pointer actually hit, so the panel still opens over something that is supposed
  to be dead. Bind `data-wk-tooltip-disabled` instead of passing the prop when
  the answer changes while the page is open.

- **[`kanban`](https://docs.wirekit.app/components/kanban) reorders, by pointer and by keyboard.**
  `sortable` previously produced attributes for you to wire a library to. It now
  moves cards on its own and dispatches one `wirekit:sortable:reordered` event
  carrying the whole new sequence. The keyboard path is part of it rather than an
  addition — Space lifts, the arrows move, Space drops, Escape puts the card back
  — because a list that can only be reordered by dragging cannot be reordered at
  all without a pointer.

- **[`sidebar`](https://docs.wirekit.app/components/sidebar) lets you own the scrolling middle.**
  `scroll-shadows="false"` removes the fading edge affordance and keeps
  everything else. The two used to arrive together, so being rid of the shadows
  meant dropping `header` and `footer` — and with them the pinned head and foot,
  which is an unrelated capability.

- **[`input`](https://docs.wirekit.app/components/input), [`select`](https://docs.wirekit.app/components/select) and [`textarea`](https://docs.wirekit.app/components/textarea) can hold their height.**
  `reserve-message` keeps the message line's height whether or not there is a
  message. In a row of fields, an appearing error otherwise grows the field and
  every neighbor re-anchors — and no value of `align-items` fixes it, because
  none of them anchors siblings to an element two levels down. Off by default: in
  a stacked form the reserved line is an empty row under every field.

### Fixed

- **A value the server changed now reaches [`segmented-control`](https://docs.wirekit.app/components/segmented-control) and [`rating`](https://docs.wirekit.app/components/rating).**
  Livewire patches the DOM in place, and the component read its starting value
  once — so a selection changed on the server left the control showing the old
  one while the form submitted the new one. Only visible on a round trip your
  user did not initiate, which is why it survived: clicking the control yourself
  hides it completely.

- **[`otp-input`](https://docs.wirekit.app/components/otp-input) accepts the case it says it accepts.**
  A single-case alphabet folds case, and the promise lived only in the script —
  the browser's own validation still held an uppercase-only pattern. With
  scripting unavailable, a code typed in the case the label shows was rejected on
  submit, citing a format nobody had mentioned.

- **A refused optimistic action inside a menu is now visible.**
  Clicking a menu item closes the menu, and the refusal was rendered into the
  closed panel: announced to a screen reader, and to nobody looking at the page.
  It is now surfaced when — and only when — its own control has no visible box.

### Changed

- **An expected refusal no longer has to reach your error tracker.**
  The optimistic guide asks for a thrown exception, correctly — a validation
  rejection is a successful response and confirms the value instead of restoring
  it. It now also says what throwing costs, and how to keep a refusal your
  application makes on purpose out of your incident feed. Measured on our own
  documentation site: one reader on one page produced 46 reports from a demo that
  refuses by design.

## [2.25.0] — 2026-08-03

**Minor — a date range, a vocabulary the icon system was missing, and a long run of fixes for behavior that looked right and was not.** Nothing here changes what an unchanged call site renders. The additions are opt-in; the fixes are the larger half, and most of them share a shape worth naming — a component doing its job at the moment anyone would look, and stopping afterwards. Several were invisible by construction rather than by oversight: a refusal that rendered exactly like a success, a control that changed on screen and never reached the server, an editable surface with no name for a screen reader.

### Added

- **[`calendar`](https://docs.wirekit.app/components/calendar) takes a date range.**
  Add `range` and it selects in two clicks — the first sets the start, the second
  the end, and a second click before the start becomes the start instead, so there
  is no rule about which end to pick first. While only the start is set, the day
  under the pointer stands in for the end, so the shading shows what you are about
  to choose rather than nothing until you have chosen it. `value` reads and writes
  `YYYY-MM-DD/YYYY-MM-DD`, the same spelling
  [`date-picker`](https://docs.wirekit.app/components/date-picker) uses, and the
  form receives `name[start]` and `name[end]` beside the combined field — so a
  handler written for a single date keeps working. Both ends and every day between
  them are announced, in the single-month grid and side-by-side months alike.

- **`.wk-only-below-md`** — shown only below the `md` breakpoint, and the counterpart to
  `.wk-hide-below-md`. A dense desktop affordance often wants a different shape on a phone rather
  than a smaller one, and swapping the two takes both halves.

- **Nine semantic icon aliases, in every base preset:** `bell`, `bell-slash`, `tag`, `send`, `archive`, `reply`, `forward`, `image` and `message`. Notification, labeling, mail and media — concepts every administrative interface has, and none of them had a word.

  The gap did not present as a gap, which is why it lasted. A page that needed one of these reached for the icon package's own glyph name — `envelope`, `paper-airplane`, `magnifying-glass` — and it worked. It worked because the optional icon package happened to be installed, which is a dependency on the icon set rather than a contract with this library. On an install without it, the same markup throws.

  `bell` and `bell-slash` move out of the `heroicons-app` extension into the base set. Same glyph, so nothing rendered changes; what changes is that they now resolve without stacking an extension.

- **The [sidebar](https://docs.wirekit.app/components/sidebar)'s scrolling list shows where it continues.** A long navigation column scrolls, and a scrollbar alone is easy to miss on a track that fades when idle. The list now carries the same edge shadows `sticky-panel` uses: at the top there is a shadow below the fold and none above it, and at the bottom the reverse. Sentinel-driven rather than masked, so the shadow appears exactly when there is somewhere left to scroll — a mask dims the edge whether or not anything follows, which leaves the last item grayed out once you have reached it.

- **`charts.apex_license` is in the published config.** The doctor told you to record your ApexCharts tier "in config/wirekit.php" and the file shipped without that key, so the instruction pointed at nothing. The `charts` block also never listed `apexcharts` among its adapters, though the component and the script bundle both offer it.

### Fixed

- **`<x-wirekit::textarea rows="auto">` silently did nothing on a browser at the supported floor.**
  Auto-growing uses CSS `field-sizing: content`, which is newer than WireKit's baseline
  (Chrome 123 / Safari 17.4 against 111 / 16.4) — so on a baseline browser the prop named the
  behavior and the behavior was absent, with nothing saying so. The component's own comment
  described the property as being *inside* the baseline. It now applies `wk-autosize`, a real rule
  inside `@supports (field-sizing: content)`, and the class is documented in
  [the public CSS API](https://docs.wirekit.app/extending/public-css-api) as progressive
  enhancement: where the property is unsupported the textarea keeps its `rows` minimum and stays
  scrollable and resizable. No API change — `rows="auto"` is unchanged.

- **An open [inline editor](https://docs.wirekit.app/components/inline-edit) could close itself
  across a Livewire re-render, discarding the draft.** The component announces on `window` when it
  opens so that other editors stand down, and it recognized the sender by comparing against its own
  root element — captured once when the component initialized. A re-render that replaces that
  element while keeping the component alive left the comparison pointing at a detached node, so the
  editor treated its own announcement as another editor's and closed. Nothing was logged; the field
  simply reverted to its previous value. The root is now resolved when the event arrives.

- **The [editor](https://docs.wirekit.app/components/editor)'s editable surface had no accessible
  name when it carried a visible label.** A `<label for>` names labelable elements; the surface a
  ProseMirror engine builds is a `div` with `contenteditable` and `role="textbox"`, which is not one,
  so the reference never reached it — and because the component suppresses its own `aria-label`
  whenever a visible label is present, the field ended up with neither. A screen reader announced an
  unnamed text box. The label is now wired by reference (`aria-labeledby`), and `aria-label` remains
  the fallback for an editor without a visible label. No API change; nothing to update in your code.

- **Muted text on a [callout](https://docs.wirekit.app/components/callout) or
  [alert](https://docs.wirekit.app/components/alert) was below AA contrast in dark mode.** These
  components tint their background with 15% of a state color, and in dark mode the default accent is
  near-white — so the tint moves the surface *toward* light text rather than away from it. A
  secondary line written as `<x-wirekit::text variant="muted">` inside one measured 4.45:1, just
  under the 4.5:1 WCAG AA requirement for normal text, while the same token measured 7.63:1 on the
  page background it was set against. `--color-wk-text-muted` in dark mode moves from
  `oklch(70.8% 0 0)` to `oklch(74% 0 0)`: 5.00:1 on the worst tinted surface, and every other dark
  pairing improves with it. Muted text on a tinted callout surface is now part of the guaranteed set
  documented in [Theming](https://docs.wirekit.app/theming). `text-subtle` and `text-placeholder`
  are unchanged — they are guaranteed on the page and input backgrounds, and the guide now says so
  explicitly.

  If you override `--color-wk-text-muted` for dark mode, check it against a callout as well as
  against the page background; the two surfaces are further apart than they look.

- **An optimistic control inside a panel that opens over the page never reached the server.** The
  value changed on screen, no request was sent, no error was raised, and the control stayed in its
  provisional state indefinitely — announcing that it was saving, forever. It affected any optimistic
  control whose panel is rendered outside the component that owns it: a popover
  [color picker](https://docs.wirekit.app/components/color-picker), a menu, a combobox list. Only the
  keyboard path reached it, because a drag ends on a handler bound to the document while the layer's
  own element is still in place — so a pointer never reproduced it and a keyboard hit it every time.
- **[`select`](https://docs.wirekit.app/components/select) ignored its `value`.** A `<select>` has no
  `value` content attribute, and the prop was not declared — so `value="pro"` landed on the element,
  HTML ignored it, and the browser fell back to the first option. Every standalone select showed one
  choice while everything reading the component believed another. The prop is declared now, the
  matching option carries `selected`, and a placeholder pre-selects itself only when nothing else is
  chosen.
- **[`carousel`](https://docs.wirekit.app/components/carousel) slide dots were an 8px tap target
  6px apart**, failing both WCAG 2.5.8 AA's size floor and its spacing exception. The dots are still
  8px — an indicator the size of a button is not an indicator — and they now sit 24px center to
  center, which is exactly the distance at which the criterion's notional circles stop intersecting.
  The spacing exception therefore applies on its own merits. A 44px hit region was tried first and
  was worse than the problem: at the old pitch the expanders overlapped, so a tap aimed at one dot
  landed on its neighbor.

- **`<x-wirekit::tooltip>` no longer runs off the edge of a phone.** It was the only overlay in the library without cross-axis shifting, so a `placement="right"` tooltip on a narrow screen went past the right edge and stayed there — measured at 184px off-screen in a 375px viewport. Floating UI's default shift only moves along the placement's main axis, which for left/right is vertical, and flipping gives up when both sides overflow. Six sibling overlays already passed this flag.

- **An anchored panel no longer opens one panel-width away from what opened it.** Every overlay that positions itself against a trigger — menus, popovers, comboboxes, the data table's column manager — could be placed as though it had no size, because it was measured before the browser had laid it out. A placement that aligns a panel's far edge subtracts the panel's own width, and subtracting zero puts the panel's near edge exactly where its far edge belongs. On a 375px screen the column manager opened at 290px and ran to 482px, a hundred pixels past the edge, with a trigger that ended at 290.

  Whether it happened at all depended on the browser: the same markup placed correctly in Chrome and Edge and incorrectly in Safari, on iOS and on the desktop, because the two differ in how much layout is guaranteed by the moment a panel becomes visible. The positioner now waits for the panel to have a box before it measures one, so the placement is the same everywhere.

- **A refused optimistic action no longer shows the reader the server's error page.** The layer
  already did the right thing — it took the value back and announced the refusal — and then Livewire
  painted its own error page over the top, because nothing told it the failure had been handled. On a
  phone that is a full-screen stack trace covering the very rollback the component just performed.

  A component that opts into `optimistic` owns its failure path by definition, so it now says so:
  Livewire's default display is suppressed for the request it handled, and the refusal goes to the
  browser console instead, where a developer looks and a reader does not. Handling a failure and
  hiding it are different acts; only the second would be a defect. Nothing changes for a component
  that has not opted in.

- **`<x-wirekit::hover-card>` can be dismissed with Escape.** The handler was bound to the panel, and the panel is teleported while the card is opened by pointing at or focusing the trigger — so the key went one way and the listener sat the other. WCAG 1.4.13 requires content shown on hover or focus to be dismissible without moving the pointer or the focus, and Escape is the mechanism it names.

- **The mobile drawer in `<x-wirekit::app-shell>` fills the shell instead of floating on it.** Below the breakpoint the sidebar slides in over the page, but it kept the `card` treatment it wears in a padded column — as tall as its content, with rounded corners. Measured with the drawer open at 393px: the shell was 256px tall and the panel a reader actually sees was 85px of it, reading as a card dropped on the content rather than as a drawer. It now fills its column and meets the edge flush. Above the breakpoint the card is untouched.

- **`<x-wirekit::app-shell>`'s mobile drawer is anchored to the shell rather than to the page.** The drawer and its backdrop were `position: fixed`, which measures against the viewport only while no ancestor is a containing block — `contain`, `transform`, `filter` and `perspective` all make one, and any of them can appear above an embedded shell. When one did, the opened drawer measured itself against that ancestor instead: it started 64px above the shell and took that ancestor's height. The shell now owns the positioning context, so the two coincide in a full-page app and in an embedded one alike.

- **A closed off-canvas drawer is now gone rather than merely moved.** `<x-wirekit::app-shell>` parked its mobile sidebar at `-translate-x-full`, which puts it off-screen only while something clips it — and a `position: fixed` element is clipped by the viewport only while no ancestor is its containing block. `contain: layout` makes an ancestor exactly that, as do `transform`, `filter` and `perspective`, and then -100% means 256px to the left of *that* box: the whole menu paints beside the content. The drawer now also carries `visibility: hidden` when closed, so nothing depends on a host clipping it.

  The same change fixes a defect that was never about painting: a translated drawer keeps its place in the tab order and in the accessibility tree, so keyboard and screen-reader users could reach a menu that nobody can see. `visibility` transitions alongside `transform`, so the closing animation still plays.

- **`<x-wirekit::tooltip>` colors its panel before showing it, not after.** Setting `open` displays the panel immediately; the themeable variables were copied onto it only after the tick that followed. In between, the panel is displayed and still carries the default color. Whether a browser paints inside that window was never established — but an observer can read a shown panel without its color, which is enough to make the contract untestable without a race.

- **Optimistic controls now settle instead of staying provisional.** A control marked `optimistic` painted its dashed pending outline when you left it and then waited forever: no confirmation, no rollback, and a refusal announced nothing. It reached the server through Alpine's `$wire`, which in some render contexts hands back a stand-in attached to no component — it answers every method name, every call returns nothing, and no request is made. The component that owns the control is resolved from the page now, so the answer comes back and the state resolves.

  The check beside it went too: `typeof $wire[method] === 'function'` is true for a method that does not exist, because that object answers for any name. It could never fail, so it protected nothing. What is checked instead is whether a component was found at all.

- **`<x-wirekit::sticky-panel>`'s `width` is a ceiling, and only where the panel is stuck.** It was applied as a fixed width capped at `max-width: 100%`, which caps the case that does not arise — the container being narrower. Below the breakpoint the panel un-sticks into ordinary flow, and there the container is wider: a `15rem` summary sat in a `335px` column with the space beside it doing nothing. The panel fills its column now and stops growing at `width`, which is what the prop always described. Above the breakpoint nothing changes.

- **[Inline edit](https://docs.wirekit.app/components/inline-edit) no longer discards what you typed when something else on the page updates.** The editor closed and dropped its draft on every blur, without asking where the focus had gone. A Livewire update anywhere on the page patches the DOM around a focused input, and the browser fires a blur while it does — focus returns immediately, nobody went anywhere, and the text was gone. No message, no undo, on a round trip that had nothing to do with the field. It now waits a tick and checks: if focus came back to the same editor, the draft stays.

- **[Tooltip](https://docs.wirekit.app/components/tooltip) color overrides work again.** Moving the panel to the end of the document so a masking ancestor could not clip it also ended its descent from the trigger — and the documented way to restyle one tooltip is an inline `--color-wk-tooltip-bg` / `--color-wk-tooltip-text` on the component, which the panel read by inheritance. The panel kept rendering, in theme defaults, and every override silently stopped applying. The values are now copied onto the panel each time it opens, read from the trigger, so an inline style, a class or a scoped theme all resolve the same way they used to.

- **A [chart](https://docs.wirekit.app/components/chart) that updates keeps its keyboard fix.** ApexCharts re-stamps `tabindex` on every SVG it rebuilds. The correction ran at moments — after a render, after a theme swap — which is a list somebody has to remember to extend. A page streaming data rebuilds its SVG every tick, so the fix ran once, correctly, and the next update put the tab stops straight back: a focus stop inside a subtree assistive technology has been told to ignore. It is now driven by what the chart does rather than by a list of moments, and it covers the legend entries too, which have the same defect one level down.

- **Two scroll animations ignored `prefers-reduced-motion`.** An explicit `behavior` argument overrides the CSS rule that covers everything else, so scroll-to-top and the tour stepper animated for a reader who had asked them not to. Both go through the shared helper now. The CSS half of reduced-motion is blanket and covers a component added tomorrow; the JavaScript half is a list, and these two were not on it.

- **A [sidebar](https://docs.wirekit.app/components/sidebar) header or footer lines up with its own items.** The zones carried no inline padding while `sidebar.item` and `sidebar.group` apply their own, so a brand row rendered flush against the column's edge and everything below it looked inset. Applied on the zone rather than asked of the caller: the alignment is a property of the column, and supplying a brand row should not require knowing which token the items happen to use.

- **`wirekit:doctor` no longer promises a silence it cannot deliver.** The ApexCharts license reminder offered three values to record — community, commercial, oem — and matched two of them. Anyone on the community tier followed the instruction, saw the identical message with the identical advice, and had every reason to conclude the command is imprecise. The reminder itself stays, deliberately: the revenue threshold is a continuing condition rather than an install step, so a project crosses it without any file changing. What changed is the advice. A declared community tier is now confirmed back to you with the reason it still speaks, an unrecognized value is named rather than treated as unset, and the unset case keeps its full explanation without the promise.

- **`wirekit:export-blocks` emitted a `source_url` that could not resolve.** It pointed at a branch and a directory that the public repository does not have — neither the branch nor the directory exists there. Each block now links to its page's raw Markdown on the documentation site, which is the sibling of the `preview_url` beside it and follows the same visibility rules.

### Documentation

- **[`sidebar`](https://docs.wirekit.app/components/sidebar) shows its event addressing in a
  live example.** The `wirekit:sidebar:toggle` / `:toggled` pair — how a trigger outside the
  sidebar reaches it, and how `{ id }` picks one of several — was documented in a code block and
  never rendered anywhere. The page now carries a running two-sidebar demo with one `id` each, so
  you can see a toggle address one and leave the other alone.

- **Three refusal demos now say what a refusal does.** The optimistic examples on
  [`calendar`](https://docs.wirekit.app/components/calendar),
  [`reaction`](https://docs.wirekit.app/components/reaction) and
  [`toggle-button`](https://docs.wirekit.app/components/toggle-button) showed the behavior without
  stating it, so a reader had to infer the contract from watching.
- **Two of them stated the opposite of what the component does.**
  [`number-input`](https://docs.wirekit.app/components/number-input) and
  [`color-picker`](https://docs.wirekit.app/components/color-picker) promised that a refused value
  comes back. Both are deliberately built to KEEP it — deleting what somebody typed because a save
  failed is worse than the failed save — and the pages say so now. A reader trusting the old wording
  would have filed a bug against correct behavior.
- **The [`fab`](https://docs.wirekit.app/components/fab) example stacked two floating buttons in one
  corner.** The component pins itself to a screen corner, so the wrapper each demo sat in did nothing
  and both landed on the right, one under the other. The first now declares `position="start"`, which
  also shows the prop.
- **[`footer`](https://docs.wirekit.app/components/footer) link columns sit further apart.** Stacked
  links at the tightest gap put their centers 23.4px apart, half a pixel under the threshold where a
  column of small targets stops being comfortable to tap.

- **Documentation previews used icon names that no preset registers.** Eight pages taught glyph names from the icon package rather than this library's own aliases, which meant the snippet worked on a machine with that package installed and threw on one without. Every one now uses the alias a developer is meant to learn — `search` rather than the magnifier's glyph name, `inbox` rather than `envelope`, `edit` rather than `pencil`.

- **`CONTRIBUTING.md` named tooling that is not in the package.** Its setup and pre-commit blocks listed commands whose test suite, build scripts, linter configuration and `package.json` are all stripped from the distributed tree — so for every reader who has that file, the commands resolved to nothing. It points at the documentation site instead.

- **The [icon](https://docs.wirekit.app/components/icon) page lists every semantic alias, and a test keeps it that way.** It documented 39 of them while the preview block directly above rendered all 71, and said so — the tables were described as a selection rather than the full list, on the reasoning that a hand-maintained table goes stale. The reasoning is sound and the page still contradicted itself on one screen: anyone counting it concluded the vocabulary was half its size.

  All of them are listed now, in four added groups — content and chrome, people and records, notifications and mail, media controls. Stale counts elsewhere on the page are gone rather than corrected, and the shipped config no longer understates the vocabulary. `php artisan wirekit:icons` remains the answer to a different question: what *your* install resolves, extensions included, which no table can know.

- **Three [optimistic UI](https://docs.wirekit.app/extending/optimistic-ui) pages were missing a demo the overview promised.** The overview says each page lets you accept, refuse and watch a slow answer; the color picker had no slow answer and the calendar and editor showed only the refusal. A sentence true of most pages reads as true of all of them, so somebody following it and finding no rollback concludes there is none.

---

## [2.24.0] — 2026-07-31

**Minor — the full-bleed sidebar column, two smaller additions, and five fixes.** Every addition defaults to what the library already did, so an unchanged call site renders exactly as before; opting in is what changes shape, and it changes it on purpose. The fixes are the quieter half — each one makes something that already looked fine actually be fine.

### Added

- **The [sidebar](https://docs.wirekit.app/components/sidebar) can be a full-bleed column, and the [application shell](https://docs.wirekit.app/components/app-shell) can seat it against its own edge.** The most common administrative layout — sidebar running the full height, topbar beginning beside it rather than above — could not be expressed, and the reason was the same in five separate places: the thing that needed changing existed only as a literal inside a template, where no call site could reach it.

  Five props, all defaulting to today's behavior:

  - `app-shell` `sidebar-inset` — `false` meets the shell's top and inline-start edge with nothing between them.
  - `app-shell` `header-placement="content"` — the topbar renders inside the content column, so the sidebar runs the full height.
  - `sidebar` `variant="flush"` — no radius, no surrounding border, surface from the host, one logical inline-end edge. Two variants rather than a set of booleans, because the in-between states are the broken-looking ones: a rounded panel without a border reads as a rendering fault, and a full-bleed column with a radius shows a sliver of page at each corner.
  - `sidebar` `header` / `footer` slots — the three-zone column, and only when one is supplied, so no existing call site changes.
  - `sidebar` `toggle="start|end|none"` — where the collapse control sits, or that there is none.

  `toggle="none"` needed more than leaving the button out. Alpine merges scope downwards only, so a trigger in your topbar is not inside the sidebar's tree and can never call its `toggle()`. The state stays with the sidebar and the outside reaches it through a `wirekit:sidebar:toggle` window event — with no id it addresses every sidebar, with an id only that one. The sidebar answers with `wirekit:sidebar:toggled` **on init as well as on change**, because a button outside it has to render `aria-expanded` from its first paint and cannot read the sidebar's state to do so.

- **The machine-readable manifests advertise the newest RELEASED version.** `components.json`, `api-map.json` and `blocks.json` each carry a `released_version` field — a bare version string, so a tool that serves or mirrors this package can compare what it is showing against what the package claims.

  It is a separate field from `version`, deliberately. `version` answers "which build is installed here", which on a branch pin is a branch pseudo-version rather than a release; a check built on it would compare a branch name to a release number and stay green forever. Two fields that both read as "the version" would be the hazard, so the new one says which question it answers.

- **[Slider](https://docs.wirekit.app/components/slider#marks-that-carry-a-meaning) tick marks can carry a meaning, not just a label.** A third `marks` shape takes `['label' => …, 'description' => …]` per position, for sliders whose steps stand for something the label cannot say — five positions from −2 to +2, each a policy.

  Without it the alternative is worse than it sounds: a reader who wants to know what a position means has to **move the slider to find out**, changing the very thing they were still deciding about.

  The description reaches a reader two ways, because either alone leaves a gap. It becomes the tick's `title`, which is a pointer affordance — and there is no hover on touch, so that alone would hide the meaning from the readers most likely to be guessing at it. So it is also **what the slider announces**: moving through the positions reads out the meaning of the one you are on instead of the bare number, one at a time rather than every mark at once. The label is what you see; the description is what the position means.

  Both older shapes render exactly as before — no `title`, no description — so this is opt-in per mark.

- **Five icon aliases for concepts every administrative interface has:** `users`, `history`, `legal`, `badge` and `layers`. Available on all four presets, so an alias resolves whichever one is configured.

  These were missing, and the gap did not present as a gap: an application reaching for "user management" or "audit log" found no word for it and used the icon package's own glyph name instead. That works — and only because the full package happens to be installed, which is a dependency on the icon set rather than a contract with this library. One application discovered that when it tried to ship only the icons it renders: the restriction removed the coincidence and four of its test files went red.

  `users` is a separate word from `user` on purpose. Managing accounts is a different menu item from your own profile, and the two sharing one glyph was forced rather than chosen. It also moves out of the marketing extension into the base set — same glyph, so nothing rendered changes.

- **[reading-toc](https://docs.wirekit.app/components/reading) works inside your own scroll region.** Its jump moved the page, which does nothing in a shell that scrolls an inner container instead — a fixed header with the content scrolling beneath it. Clicking a heading simply did not move, and nothing said why: the page had nowhere to go, and not going there raises no error. It now finds the container that actually scrolls, with no prop to set.

- **A collapsed [sidebar](https://docs.wirekit.app/components/sidebar) group can still say something is waiting.** `sidebar.collapsible` now takes a `trailing` slot on its trigger. A group is collapsed to keep the list short, and the counters on the items inside went with it — with `persist`, permanently: collapse once and the numbers are never seen again without going to look. Alpine's `open` is in scope, so `x-show="! open"` shows it only while the group is closed, which is usually what you want. A slot rather than a `badge` prop on purpose: a count is one answer and a silent dot is another, and a total across several queues asserts an urgency the number cannot know.

- **Scroll shadows on the inline axis, for a bar that scrolls sideways.** `wk-scroll-shadow-start` and `wk-scroll-shadow-end` are the horizontal counterparts to the existing top/bottom pair — a tab strip, a chip row or a toolbar can now show the same "there is more this way" cue. They share the block-axis pair's two variables, so a theme tunes both axes in one place, and they are named for the writing direction rather than for left and right, so a right-to-left interface gets the cue on the side its content continues toward. The markup is yours and the driving is not: no component template renders them — `<x-wirekit::sticky-panel>` emits only the block-axis pair — but the Alpine factory behind it reads `startSentinel` / `endSentinel` refs alongside the block-axis ones and drives whichever are present, so adding the two sentinels to your own scroller is enough, and a container that scrolls both ways gets all four. This is not what `fade` does: that mask is static and dims the edge even where nothing follows, which leaves the last item of a strip looking disabled.

### Fixed

- **`wirekit:verify` no longer reports a completeness it never checked.** Its config-drift check compared section and component names — two of the 186 options the config actually has — and then said your published file "covers every option this version offers". A file published one minor earlier could be missing options inside sections it already had, and the command confirmed it was current. The comparison now covers every option, grouped by the section that owns them so the output stays readable. **Expect a longer warning the first time you run it** if you have not re-published in a while: the missing options were always there, only unmentioned. They still resolve at runtime — nothing behaves differently — but your file now shows you what you can set.

- **`WireKit::defaults()` now changes what renders.** The documented way to set component defaults from PHP stored its values and nothing read them: the call succeeded, and every component rendered as though it had never been made. The repair is not a new resolution path — components already read their defaults from `wirekit.components.*` config, so there were two mechanisms for one job and only one was connected. The call feeds that config, which wires it for every component at once and keeps a single order of precedence. An explicit attribute at the call site still wins, as a default should.

- **A [tooltip](https://docs.wirekit.app/components/tooltip) is no longer cut off by whatever it happens to sit inside.** Its panel now renders on the `<body>` rather than beside its trigger. The case that surfaced it was a tooltip inside a [scroll area](https://docs.wirekit.app/components/scroll-area) with `fade`, where roughly 18 px of the panel was missing: the panel is fixed-positioned and so escapes overflow clipping, but `fade` works with a mask, and a mask applies to everything an element renders — fixed descendants included. Leaving the subtree settles that case and every relative of it at once: a clipping card, a transformed ancestor, an isolated stacking context.

- **`wk-touch-target` no longer moves an element that positions itself, and [scroll-to-top](https://docs.wirekit.app/components/scroll-to-top) finally gets the 44 px target.** The class guarantees the enlarged tap area a positioned ancestor, and did so by setting `position: relative` unconditionally. WireKit's stylesheet is unlayered while Tailwind's position utilities are not, and an unlayered declaration wins over a layered one whatever the specificity or import order — so a `fixed` element became `relative`, its `right`/`bottom` turned into flow offsets, and it moved. Scroll-to-top is fixed by design, so applying the class pushed it off the left edge of the screen; and because the rule is inside `@media (pointer: coarse)`, this happened only on the devices the class exists for. A host that already declares its position now keeps it, and scroll-to-top carries the class itself — it had been left at a 40 px target while sibling controls got 44.

- **`@wirekitScripts` can load the ApexCharts adapter, so a chart no longer goes missing without saying why.** The adapter is a separate bundle — an app that draws no charts should ship no chart code — but leaving it out was punishing: the page rendered, the console said `wirekitApexChart is not defined`, and only the chart was absent. Nothing failed at build time and nothing appeared server-side, so the developer had to work out that a second file existed and hand-write a tag against a route path documented nowhere they were looking. Set `scripts.apex` to `true` in `config/wirekit.php` and both tags are emitted, in the order the adapter needs, with the same cache-busting and CSP nonce as the main bundle. It stays off by default, because that is the point of the split.

- **`wire:model` on [file-upload](https://docs.wirekit.app/components/file-upload) now reaches the file input, so Livewire uploads work.** The binding was applied to the component's wrapping element, and Livewire decides what a model binding means by reading the element's type: on a file input it takes the upload path, on anything else it binds a plain value. A wrapper has no type, so the upload path was never entered — and the failure was quiet in the worst way, because the component's own list showed the chosen file's name and size. The field looked filled while the server had nothing, and submitting failed with "the field is required". Modifiers (`wire:model.live`, `.blur`) travel with the binding; `wire:key` and `wire:ignore` stay on the wrapper, where they describe what they are meant to describe.
- **An [ApexCharts](https://docs.wirekit.app/components/chart) chart no longer puts keyboard focus somewhere screen readers are told to ignore.** The rendered chart carries the library's own tab stops — on the SVG itself and on each legend entry — while WireKit mounts it inside an `aria-hidden` container, because the chart's accessible name and `role="img"` belong to the element around it. Tabbing therefore landed inside a region assistive technology skips: the focus ring moved, nothing was announced, and there was nothing to operate once you arrived (the toolbar and zoom are off by default). Those tab stops are now removed — but only while the container is hidden, so a chart you deliberately expose keeps its focusable SVG. The tab stops are removed at the source rather than deflected by a handler that pushed focus back out as it arrived — one listener less per chart, and the behavior no longer depends on that handler running in time.

- **A sortable [table](https://docs.wirekit.app/components/table) header's click target was its label, not its cell.** With `sortAction`, the label and sort indicator now sit in a full-width button that fills the header cell, so the whole cell is the target rather than the few characters of text — it was under the 24 px minimum that WCAG 2.5.8 asks for, on a control people click repeatedly while scanning a table.

  Two details you may notice. The cell's padding moved onto the button rather than being added to it: an inline-flex child grows the line box, so keeping both made the row 52 px where 36 was expected. And the focus ring is inset, because a ring drawn outside a button that fills its cell is clipped by the cell.

- **A filled [one-time code](https://docs.wirekit.app/components/otp-input) can be corrected by
  typing from the left.** Every cell had to be clicked and cleared individually first, which is
  what a row of single-character inputs does by default: `maxlength="1"` is already satisfied by
  the character in the cell, so with the caret after it the browser refuses the keystroke —
  nothing happens, and no advance to the next cell happens either. Focusing a cell now selects
  its character, so typing replaces it and moves on exactly as if the row were empty. Clicking
  and tabbing both, and pasting over a filled row is unchanged.

- **An icon alias pointed at an icon that does not exist.** `server` resolved to a Phosphor glyph the package has never shipped, and unknown icon names throw rather than degrade — so a page using it broke. It now points at Phosphor's rack metaphor.

  Worth naming the reason it lasted, because it was structural rather than careless: three of the four icon packages were optional dependencies, so no test could resolve a single one of their aliases, and the defect landed in one of those three. Those packages are now development dependencies and every alias of every resolvable preset is checked against the files actually shipped. The fourth cannot be installed alongside the Laravel versions this package supports, so it is reported as unverifiable rather than skipped quietly.

- **[Alert dialog](https://docs.wirekit.app/components/alert-dialog#the-order-focus-is-resolved-in) documents the order focus is resolved in, and the two ways `focusReturnTo` can make things worse.** The prop existed and worked; what was missing is the part a reader needs before setting it.

  It **beats a surviving trigger** rather than acting as a fallback for the case where the trigger disappears — so setting it on a dialog whose trigger usually survives pulls focus away from where the reader was, which is worse than the default. And the target must outlive the action: the selector resolves at close time, so pointing it inside the row you just deleted falls through silently, with no error, to the state the prop exists to avoid.

- **[Using a raw icon name](https://docs.wirekit.app/components/icon#using-a-raw-icon-name-instead-of-an-alias) is documented as the trade it is.** An icon name the vocabulary does not define still resolves when the package provides one — that has always worked, was never written down, and is the reason applications reached for it without knowing what it costs.

  What it costs: your application is coupled to the icon package rather than to this library. Restrict that package to the icons you actually render and the raw names stop resolving; one application had to revert exactly that change because four of its test files depended on them. The page now says when reaching for a raw name is legitimate, when it is not, and that a fallthrough is logged in development.

- **[Progress](https://docs.wirekit.app/components/progress)'s label id changed on every render.** The id linking the visible label to the bar was regenerated each time, which only matters where this component is most used: inside a polling region, every poll produced a new id, so the accessible name was re-resolved on a control whose whole purpose is being watched while it changes.

  It is invisible in any single render — the label and the bar always agreed with each other, just on a different value every second. The id now derives from the one you pass, and the page says to pass one when the bar sits in a polling region.

### Documentation

- **Every component that supports [optimistic UI](https://docs.wirekit.app/extending/optimistic-ui) now has a demo you can run.** The capability was documented on each component's page and shown running on none of them, which is a poor trade: the interesting part is what the screen does in the second before the server answers, and prose cannot show that. Each page now has a **Try it** section with one control per outcome — accepted, refused, and a slow answer that holds the provisional state on screen long enough to read.

- The optimistic-UI page no longer states how many components support it. The list directly beneath the sentence already answers that, and a number in prose goes stale on the next release.

## [2.23.0] — 2026-07-30

**Minor release — accessibility promises the library documented and did not keep.** Every
item here corrects something that was already wrong; the three props and two helpers exist to
make two of those corrections configurable, and it is those additions that make this a minor
rather than a patch.

**Three things do change what an unchanged call site renders**, so budget a look at them
rather than treating the upgrade as visually inert: an
[inline-edit](https://docs.wirekit.app/components/inline-edit) textarea now opens at the
height of its content instead of a fixed three rows; an inline-edit with `width="full"`
reserves one action slot in read mode, so the value's box is narrower by that much; and every
control already using `optimistic` gains the pending outline described below. Nothing else
moves, and no prop changes meaning.

### Fixed

- **A pending [optimistic](https://docs.wirekit.app/extending/optimistic-ui) value now looks
  pending.** While an optimistic control waits for the server it drew nothing: the state was
  announced to a screen reader through `aria-busy` and nothing in the stylesheet painted it,
  so a sighted user saw a *finished* change where the contract says the change has to read as
  withdrawable. In-flight controls now carry a dashed outline that breathes slowly.

  Dashed rather than solid, because a solid outline in the accent color is what focus looks
  like — a pending control drawn that way would say the wrong thing. An outline rather than a
  ring, so nothing reflows when a value goes in flight. And never a dim: dimming degrades the
  text exactly when the reader most wants to read the value. Under
  `prefers-reduced-motion: reduce` the outline stays and only the breathing stops.

  This reaches every component that supports `optimistic` and every hand-mounted
  `wirekitOptimistic(...)`, with no change needed at your call site.

- **[inline-edit](https://docs.wirekit.app/components/inline-edit) no longer abandons an
  unanswered save in silence.** When your handler never reports the outcome — a missing
  paired event, a dropped request — the editor stopped waiting and said nothing at all. It
  now reports the save as **not confirmed**, and deliberately not as *failed*: a missing
  acknowledgment is not evidence the write did not happen, and calling it a failure invites
  a second edit over a value that is already stored. Your text stays in the field either way.

- **[inline-edit](https://docs.wirekit.app/components/inline-edit) gives the editor the box
  the value had.** With `width="full"` the field came out narrower than the text it replaced
  by exactly one button's width, because edit mode has two trailing controls where read mode
  has one. Subtle on a single-line input, obvious on a textarea. Both boxes are now one width.

- **[inline-edit](https://docs.wirekit.app/components/inline-edit)'s textarea opens at the
  height of the text it replaces.** It opened at three rows regardless, so a value that read
  as four wrapped lines became a box you had to scroll to see your own text in.

- **The Quick Start in `README.md` taught a prop `button` does not accept.** The `Delete`
  example used `variant="danger"`, and `button` takes `intent` + `surface` — so the attribute
  fell through to the markup unread and the button rendered in the accent color: a
  destructive action styled as the primary call to action. `WireKit::defaults()`'s docblock
  had the same mistake twice, including a `variant` for
  [input](https://docs.wirekit.app/components/input), which has neither a `variant` nor a
  `surface`.

  `variant` is **not** retired in general — it is a real prop on
  [alert](https://docs.wirekit.app/components/alert),
  [card](https://docs.wirekit.app/components/card),
  [text](https://docs.wirekit.app/components/text) and ten others. It is retired on `button`
  and `badge` only, so do not sweep `variant=` across a codebase; and `variant="outline"` on
  a button was a **surface**, not a renamed intent.

### Added

- **`saveTimeout` and `unknownMessage` on
  [inline-edit](https://docs.wirekit.app/components/inline-edit)** — how long to wait for your
  `saved` / `failed` answer before giving up, and what the live region says when that bound is
  reached. Both were read by the component and never passed, which is why the report above was
  silent.

- **`rows` on [inline-edit](https://docs.wirekit.app/components/inline-edit)**, defaulting to
  `auto`: the textarea sizes to its content and grows while you type. A row count pins a fixed
  height, which is the previous behavior.

- **`Pushery\WireKit\Support\StrictnessGate::unknownPropNames()`** — the attribute names on a
  component that are neither declared props nor legitimate passthrough, as a return value
  rather than a log line. Useful if you want to assert in your own test suite that your
  templates only pass props the component declares; the existing warning path now calls it, so
  your check and the runtime's cannot disagree.

- **`Pushery\WireKit\Support\BladeParser::extractWireKitComponentUsagesFromSource()`** —
  every `<x-wirekit::…>` usage in a string with the attribute names it carries. It walks each
  tag rather than matching it, so a `>` inside `x-show="count > 3"` does not truncate the tag
  and a class list does not read as attribute names.

### Documentation

- **The [inline-edit](https://docs.wirekit.app/components/inline-edit) previews look broken
  and are not, and the page now says so.** Edit a title, confirm, and the value snaps back —
  because nothing behind a documentation preview answers the confirmation, and the component
  refuses to show a value as saved on its own authority. That is the whole contract in one
  interaction, and it now reads that way instead of like a fault.

- A section on what happens when nothing answers a confirmation, with both new props.

- **The textarea demo contradicted its own copy.** Its value claimed to wrap onto more than
  one line and fitted on one, in the single demo meant to tell a textarea from a text input.

- **[Optimistic UI](https://docs.wirekit.app/extending/optimistic-ui) now lists the components
  that have it.** Twenty-three components ship the optimistic path and the page named none of
  them, so the one page you read to decide whether to use the feature could not tell you
  whether your component supported it. The page also has a preview of what a pending control
  looks like, and a shorter title.

- Every `variant=` example in the theming and component pages was already correct; only the
  README and the docblock had fallen behind.

## [2.22.0] — 2026-07-30

**Minor release — three topics in one version: an optimistic-UI layer, a Content-Security-Policy build, and a new component with five capabilities an application could not reach before.** Nothing existing changes shape: every prop added here has a default that renders exactly what the previous version rendered, and both new script bundles are ones nobody loads by accident.

Read in one sentence each: a component can show the result of an action before the server confirms it and put it back if the answer is no; the whole catalog now works under a policy that forbids `unsafe-eval`, which took moving every inline expression into a registered factory; and a displayed value can become an editor in place.

### Added

- **[Optimistic UI](https://docs.wirekit.app/extending/optimistic-ui) — a component can show the result of an action before the server has confirmed it, and put it back if the answer is no.** Add `optimistic="yourMethod"` to a supported component and load `wirekit-optimistic.js` alongside whichever bundle you already use. Supported today on [toggle](https://docs.wirekit.app/components/toggle), [checkbox](https://docs.wirekit.app/components/checkbox), [rating](https://docs.wirekit.app/components/rating), [reaction](https://docs.wirekit.app/components/reaction), [select](https://docs.wirekit.app/components/select), [segmented control](https://docs.wirekit.app/components/segmented-control), [combobox](https://docs.wirekit.app/components/combobox), [multi-select](https://docs.wirekit.app/components/multi-select), [calendar](https://docs.wirekit.app/components/calendar), [date picker](https://docs.wirekit.app/components/date-picker), [time picker](https://docs.wirekit.app/components/time-picker), [input](https://docs.wirekit.app/components/input), [textarea](https://docs.wirekit.app/components/textarea), [password input](https://docs.wirekit.app/components/password-input), [slider](https://docs.wirekit.app/components/slider), [range slider](https://docs.wirekit.app/components/range-slider), [number input](https://docs.wirekit.app/components/number-input), [tags input](https://docs.wirekit.app/components/tags-input), [one-time code](https://docs.wirekit.app/components/otp-input), [toggle button](https://docs.wirekit.app/components/toggle-button), [editor](https://docs.wirekit.app/components/editor), [color picker](https://docs.wirekit.app/components/color-picker) and the checkbox item inside a [dropdown](https://docs.wirekit.app/components/dropdown).

  Every one of them had to earn it. A component is not declared safe — it becomes reachable once its own rendered accessibility test is green, and some still do not make it: a date picker in `range` mode is excluded because an undo of two values has no single right answer. Components that are not covered say so, on the component, with the specific obstacle.

  **A field you type into does not undo.** Putting the old value back costs a toggle or a select nothing — it is simply the other choice. In a text field the old value belongs to the server and the new one is your work, so restoring it would delete what you wrote because a save failed. Those fields keep the value instead, mark it unsaved, and say both: that it did not save, and that it is still there. Nothing is read back to you, which matters most in a password field.

  **A control with two ways in takes the safer exit for both.** A number field has steppers and a box you can type in; a tag field has a list and a box you can type in. Stepping or removing is a discrete choice that could safely spring back — but a value typed while such a request is still out would be overwritten when it does, so the safety would depend on whether you happened to be typing. Neither half undoes: the change stays and says it was not saved.

  **A slider commits when the gesture ends**, not per frame — at the end of a drag, or immediately on a keypress, since one press is already a finished decision. There is no settle delay anywhere in this: a timer would make the same gesture behave differently on a fast machine than on a slow one. A refusal returns the thumb to where the gesture began rather than to where it was released, and for a range the pair moves as one value, so both handles return together.

  **What it announces is the part worth reading.** The flip announces once and hedged — "Saving" — so the new value is audible as provisional. Confirmation is **silent**, because what was announced is what happened; only a deviation speaks a second time, which is what makes an undo recognizable as an undo. Where the component sits in a form that already shows a validation message, the undo stays silent and leaves that message to speak. An aborted request announces nothing at all. Focus never moves.

  The factory ships in its own bundle and in no other, so an application that does not use it pays nothing — neither the bytes nor the announcement behavior. A component without the prop renders exactly as it did before, down to the byte.

---

- **A Content-Security-Policy build: `'scripts' => ['bundle' => 'csp']`.** Alpine's standard build evaluates its expressions with `new Function(...)`, so a policy without `script-src 'unsafe-eval'` left every interactive component inert — a dropdown that never opened, a modal that never closed, and no error to say why. The CSP bundle is built against Alpine's own Content-Security-Policy distribution and brings its own Alpine, so it is loaded instead of yours, not alongside it.

  Reaching that meant the whole catalog had to stop asking for what a strict policy forbids. Every inline `x-data` object moved into a registered factory, every directive payload is encoded rather than interpolated, and the expressions that remain are single expressions with no arrow functions, template literals or optional chaining. That work is invisible in the standard build and is what makes the strict one possible.

  See [Content Security Policy](https://docs.wirekit.app/getting-started/integration#content-security-policy).

- **Plural forms are chosen in the browser by the language's own rule.** A count that changes on the client cannot have its wording decided on the server: the page would show six and announce five. Translations now travel as forms and the browser picks between them with `Intl.PluralRules`. This replaces a `count === 1 ? singular : plural` choice, which is correct only for languages that have exactly two forms — Polish has three and Arabic six, and the wrong one was picked without ever looking wrong to a reader of English. Reaches the [countdown](https://docs.wirekit.app/components/countdown)'s screen-reader text and every accessible name that embeds a count.

- **[Inline edit](https://docs.wirekit.app/components/inline-edit) — a displayed value becomes an editor in place, and nothing is written until the reader confirms.** The component owns the interaction and not the saving: it emits an event carrying the field name, the new value and the previous one, and your Livewire component decides what that means.

  It waits for you to say the save worked. The end of a request is not evidence of success — a validation failure completes one too — so a component that closed there would discard the error message you just rendered and leave the reader looking at their old value with no explanation.

  Four control types, an `editor` slot for anything else, and three affordance styles: the pencil always visible, on hover, or only once tabbed to. There is deliberately no way to remove it, because plain text is not focusable and a value with no button cannot be reached by keyboard at all.

- **[One-time code](https://docs.wirekit.app/components/otp-input) fields accept a non-numeric alphabet.** A recovery code is often deliberately not digits — dropping the ambiguous pairs buys entropy per character and survives being read aloud. Such a code could not be typed into the field at all: every keystroke was discarded and the boxes stayed empty, with no message, while the reader held the correct code. One `alphabet` prop drives the keystroke filter, the paste filter, the validation pattern and the mobile keyboard together.

- **An application can state its own motion preference.** Motion followed the operating system and nothing else, and the blanket reduced-motion rule made overriding it an arms race — it carries `!important` and matches the whole surface. `data-reduce-motion` on `<html>` takes `reduce` or `no-preference`; absent, the operating system decides exactly as before. The middle value is the point: a media query expresses two states, and someone who set the system flag for an unrelated reason needed a third.

- **The stylesheet and script directives accept a CSP nonce.** `@wirekitStyles($nonce)` and `@wirekitScripts($nonce)`, matching `@wirekitThemeScript`. Under a `strict-dynamic` policy the nonce is the only thing that grants a resource, so without a parameter there was no way to allow the two tags that load the library.

- **Component-internal icon buttons reach the 44px touch target.** The floor was bound to two marker classes, and a button rendered from inside another component carries neither — so a page built only from WireKit showed a row where two controls grew on touch and the third did not. The new `wk-touch-target` class centers a transparent 44×44 hit area inside a control without changing its size, and you can use it on your own icon buttons.

- **[Usage meter](https://docs.wirekit.app/components/usage-meter) accepts its own word for the unlimited tier.** `unlimitedLabel` overrides the translated default, for the application whose fair-use tier is *named* "Unlimited" — a proper noun that has to read identically in every language. Until now that word could be held on surfaces the application rendered itself but not on the meter, so the rule survived only by nobody adding the key to a catalog.

- **A theme can reach four surfaces it previously could not.** Dressing specific surfaces — putting a glass class on every panel, say — needs a stable way to find them, and only the card carried one. A dropdown panel, a popover panel, a modal body and a drawer body now each emit a `data-wk-*` attribute for exactly that purpose, and [theming](https://docs.wirekit.app/theming#theme-markers) documents the complete set.

  Worth stating because of how the gap presented: a selector that matches nothing throws nothing, warns nothing, and produces no visual difference to compare against. A theme mapping six surfaces reached one, and read as though it reached six. The page now also names the one marker that is **not** a themeable surface — `data-wk-tip` marks an element that *has* a tooltip, not a tooltip, so dressing it frosts the wrong thing while looking like it worked.

### Fixed

- **Four announcements that never announced anything.** A live region that arrives on the page together with its text is a new node, and a screen reader says nothing at all — the region has to exist first and be filled afterwards. Affected the alert region behind failed streams and three sibling surfaces.

- **A toast is no longer announced twice.** Each toast carried its own live region inside a container that was already one, so the same text was queued by two announcers.

- **A rating set with the keyboard never reached the form.** Arrow keys moved the visible score without writing the hidden input, so submitting sent the score the page had before it was touched — and the star buttons had no translatable label at all.

- **Clearing a combobox did not reach the form**, for the same reason: the visible field emptied and the submitted value did not.

- **A range slider and a one-time-code field each shipped a fix that could never run** — both components registered a factory that nothing on the page used, so the corrected behavior was present in the bundle and unreachable from the markup.

- **A slider's value bubble stopped overwriting the element's style attribute**, which discarded anything else set on it.

- **`wirekit:doctor` warned about three things that were not wrong.** Each came from a check reading for a *shape* instead of the property it names, and a warning nobody can act on is worse than none — it teaches people the doctor is noisy, and then the real findings go unread too.

  A null-guard written as `if (this._observer) { … }` was rejected because only the inverted and optional-chaining spellings were recognized, though the accepted forms do strictly less. An observer created once for the lifetime of the page was reported as a leak, when giving it teardown would have been the defect. And an application self-hosting Chart.js was told its charts "render but draw nothing" while they demonstrably drew — that registration step applies only to the module import, not to the self-hosted build.

  The null-guard check now judges each `disconnect()` by its own surroundings rather than asking whether the file contains a guard anywhere. That distinction matters in both directions: the file-wide question rejected a correct guard, and simply teaching it the third spelling would have let a genuinely unguarded call hide behind a correct one elsewhere in the same file.

- **[Inline edit](https://docs.wirekit.app/components/inline-edit) could not be opened at all.** Clicking the pencil did nothing — not on a busy page, not on an empty one with a single field. The editor opened and closed itself within the same tick, so nothing was logged, nothing threw, and the control simply looked dead.

  The cause was an identity check comparing two values that are not the same element. Opening announces itself so that other open editors close; that announcement identified its sender by the element in scope, and "in scope" means the *button* when the announcement comes from a click, but the *component* when the announcement is received. No editor ever recognized itself, so each one closed itself the instant it opened.

  It also resisted being debugged: instrumenting the open path changes which element is in scope, and the fault disappears while you look at it.

- **[Inline edit](https://docs.wirekit.app/components/inline-edit) shrank its own field the moment you clicked it**, and put the confirm and cancel buttons above the field's center line rather than on it. Both applied to every control type.

  The width is the interesting half, because the markup looked right. The control carried `w-full`, which reads as "fills the row" and does not: as a flex item it keeps its automatic basis and settles at its intrinsic size — around twenty characters — no matter how wide the row is. So a value that read across the full width opened into a field a fifth of that.

  Moving the growth onto the control did not help either, and that is only visible in a measured box rather than in the markup: these are complete form controls, each rendering its own wrapper around label, field and hint, and that wrapper is the flex item while the field is a grandchild. Growth on a grandchild grows nothing. The editor now fills the row it is in, and the buttons sit on the midline of a single-line control — while a multi-line editor keeps them at the top edge, next to where typing starts, because centering against a box that has grown to six rows would float them far from both the first line and the last.

- **[Liquid glass](https://docs.wirekit.app/components/liquid-glass) — the refraction never actually rendered.** `.wk-glass-refract` asked for its distortion inside `backdrop-filter`, as `blur(…) url(#wk-glass-refract)`, behind a support check for exactly that. The browser accepts the value, reports it as supported, keeps it — and draws nothing from it. Measured twice, independently: the card is pixel-for-pixel identical with the filter and without it. So the two tiers the page invites you to compare were the same image, and every check short of comparing rendered pixels agreed that all was well.

  The distortion now sits on a layer behind the content, applied through `filter` — which does draw. The surface's own text is unaffected, because only the backdrop is displaced.

  Moving it there was necessary and, on its own, still not enough to see anything, which is worth stating plainly because the reason generalizes. `.wk-glass-refract` had inherited Tier 1's `blur(20px)` over a 72% wash, and frosting exists precisely to destroy the structure behind it. A refraction can only bend a backdrop that still arrives, so displacing an already-flat wash produced an exactly flat wash — no error anywhere, and nothing on screen at any strength.

  **Tier 2 is therefore now clear glass rather than frosted glass with an effect on top**, with a much thinner blur and a much thinner wash. That is a visible change to anything already using `.wk-glass-refract`: the backdrop shows through where it used to be hidden. `wk-glass` is unchanged and remains the surface to pick when content on top needs a calm background.

  Two further consequences. The displacement moves the backdrop layer's own edge as well, which gave the surface a rippling outline — that layer now overshoots and the surface clips it, so the edge is straight again; **the cost is that a Tier 2 surface clips overflowing descendants** and cannot host a dropdown or tooltip. And it is no longer restricted to one browser: the mechanism is inside the supported baseline everywhere. The page said "Chrome only" and no longer does.

  **And the strength was still too low to see.** With the backdrop restored, the displacement moved measurably and the pattern behind the surface stayed a *regular grid* — so the page's own sentence, "watch the dotted pattern bend behind the box", was still not true. Judged at three strengths as images rather than by a number: the grid only visibly curves from roughly triple the previous value, and beyond that it dissolves into swirls and stops reading as a pattern at all. The shipped value now sits where the dots are drawn into arcs and still read as dots.

  Both failure modes are held by tests — a floor under the displacement strength, and a ceiling over the blur and the wash, so neither can quietly return to values that render nothing. The floor was raised with this change: it had been set at "moves at least one pixel", which is not what the page promises and let the effect stay invisible while passing.

  A second, smaller defect on the same page: the SVG filter reaches a page only through `<x-wirekit::glass />`, and on the documentation page that component appeared solely inside a code example — something to copy, not something the page ran. So the demos referenced a filter that was not there. Both halves had to be fixed; either alone leaves the effect invisible.

- **[Sparklines drew nothing under ApexCharts 6.](https://docs.wirekit.app/components/sparkline)** The chart configuration reached the library carrying keys from the other adapter's vocabulary. ApexCharts used to ignore what it did not recognize; version 6 iterates one of those keys and threw before drawing. The failure was quiet in the worst way — a sparkline is usually decoration beside the number it illustrates, so the page still looked complete and only an empty box gave it away. ApexCharts 5 and 6 are both verified in a real browser, and the [chart page](https://docs.wirekit.app/components/chart) now states the supported range.

- **[Inline edit](https://docs.wirekit.app/components/inline-edit) closed itself when a save was refused.** The editor is meant to stay open until a save is confirmed, and it did — right up until the case that matters most. A validation failure re-renders the field *with* an error message, which changes it enough that Livewire replaces the element instead of patching it, and the component came back in read mode. The reader was returned to their old value, holding an error about an edit they could no longer see. A field carrying a validation error now opens as editable, which is both the fix and simply what is true of such a field.

- **[Countdown](https://docs.wirekit.app/components/countdown) left a bound value false when the deadline had already passed.** Binding with `x-model` and a target in the past produced a countdown that showed its expired state while the bound value stayed false for the life of the page — a resend button, for instance, that never became clickable again without a reload. The completion event is one-shot by design; the bound value should never have been.

- **Countdown's screen-reader text agrees in number.** It joined the value to a plural label, so a single second was announced as "1 seconds", and it lowercased the label — wrong in every language that capitalizes nouns. The units are now pluralized through the translator, which also means a translation decides its own casing and word order.

- **[Table](https://docs.wirekit.app/components/table)'s scrollable region had an English name in every language.** A responsive table wraps itself in a focusable region whose accessible name fell back to a hard-coded English string, so a reader tabbing into it on a translated page heard English. Callers that pass `tableLabel` were never affected — which is why it lasted: the failure only appeared when the component was used exactly as documented.

- **Twenty-eight strings inside component behavior are translatable.** Copy confirmations, sidebar collapse labels, password show/hide, filter-chip actions, map coordinates, calendar overflow, tour navigation and more were written as literals inside Alpine expressions rather than passed through the translator. That shape is invisible to every tool that hunts missing translations — they all look for the translator call — so unlike an ordinary oversight this one could not surface on its own.

- **`wirekit:doctor` names the token that is actually missing.** When a token pair could not be compared it reported the Tailwind side as unset regardless of which side was absent, sending you to the file where nothing was wrong. It now names the specific token, so reading the line no longer requires knowing which side is which.

### Changed

- **[Product card](https://docs.wirekit.app/components/product-card) is documented as a navigation surface.** It renders a link and no server action, which is what its documentation now says.

---

### Documentation

- **The Content Security Policy requirement is written down.** The interactive components need `script-src 'unsafe-eval'`, because Alpine evaluates expressions with `new Function`. Without it nothing throws — a one-time-code field still accepts typing and simply stops advancing — so this was previously learned by watching components quietly not work.
- **The [liquid glass](https://docs.wirekit.app/components/liquid-glass) page states its theme requirement above the demos, and names the right theme.** It sat in a trailing section below every demo, and named a theme that does not show the effect.
- **[Toast](https://docs.wirekit.app/components/toast) examples show the real integration.** The code panel carried demo scaffolding and a hardcoded payload, which read as though every click replays a fixed event. It now shows a Livewire action with the toast dispatched after the write returns, and the failure branch beside it.

## [2.21.1] — 2026-07-27

**Patch release — accessibility fixes, most of them repairs to things 2.21.0 and 2.20.0 introduced.** No new props, no new configuration. One change renames a DOM id; it is called out below.

### Fixed

- **A [floating action button](https://docs.wirekit.app/components/fab) with words in it had no accessible name at all.** The wrapper around its content was hidden from assistive technology unconditionally, so a button reading *Send feedback* was announced as nothing and could not be reached by voice control. The wrapper is now hidden only when there is nothing in it to read. An icon-only button is unaffected.
- **`prefers-reduced-motion` no longer reaches past WireKit's own markup.** The rule matched any element whose `class` attribute merely *contained* the characters `wk-`, which every design-token utility does — `bg-[var(--color-wk-bg)]` on a `<body>`, the documented way to tint a page, put the entire document in scope and clamped the application's own animations. It now matches a class token, so a developer's animations are their own again. Components animated through Alpine's shorthand transitions keep their coverage.
- **The [modal](https://docs.wirekit.app/components/modal) and [drawer](https://docs.wirekit.app/components/drawer) close buttons reach the 44×44 target.** They rendered 32×32 with nothing widening them. A transparent centered expander supplies the hit area; the visible button is unchanged.
- **The touch-target floor applies to both axes on text fields.** It set a minimum height only, so the [one-time-code](https://docs.wirekit.app/components/otp-input) digit boxes grew to 44px tall and stayed 40px wide — every box in the library's densest row of targets was still under the minimum. Buttons keep the height floor alone: widening them would grow a row of small icon buttons past its container, and giving those a real touch target needs its own treatment.
- **The active [sidebar item](https://docs.wirekit.app/components/sidebar) renders its emphasized foreground.** The resting muted color and the active color are utilities of equal specificity, so which one wins is decided by the order Tailwind emits them — and the muted one came last. An application retinting the active state lost the same way, leaving `!important` as the only way out.
- **[Radio](https://docs.wirekit.app/components/radio) ids are page-unique like every other name-derived control.** Two identical radio sets on one page — a filter bar and the same filter in a dialog — emitted the same ids, and because the hint and error ids derive from them, the second set's description pointed at the first set's help text.
- **The [one-time-code](https://docs.wirekit.app/components/otp-input) digit boxes no longer collide with a same-named control.** `id="code-2"` meant both *digit 2 of the first control* and *the second control called code*, so one of these beside any same-named field emitted a duplicate id at the default length. **The digit ids change shape: `code-0` becomes `code-digit-0`.** They are not documented and nothing inside WireKit addresses them, but code reaching for them by id needs the new spelling.
- **The [one-time-code](https://docs.wirekit.app/components/otp-input) row wraps instead of overflowing.** Each box has a fixed width and cannot shrink, so an eight-digit code needed more room than a sign-in card offers and ran past its edge.
- **Eleven accessible names now go through the translator.** The [one-time-code](https://docs.wirekit.app/components/otp-input) group and its per-digit labels, plus names in [color picker](https://docs.wirekit.app/components/color-picker), [date picker](https://docs.wirekit.app/components/date-picker), [carousel](https://docs.wirekit.app/components/carousel), [stage card](https://docs.wirekit.app/components/stage-card), [notification center](https://docs.wirekit.app/components/notification-center), [tour](https://docs.wirekit.app/components/tour) and [map](https://docs.wirekit.app/components/map), were English text with a value interpolated into it — translatable in appearance, fixed in practice. They use placeholders, so a language that orders the words differently is served correctly.
- **The component sandbox no longer offers two variants that [alert](https://docs.wirekit.app/components/alert) and [callout](https://docs.wirekit.app/components/callout) refuse.** Its schema advertised `secondary` and `accent`, which are not in either component's vocabulary, so picking one in the prop editor threw instead of rendering. Both components take the six canonical intents and the schema now says so.
- **An inline event handler is no longer reported as an unknown prop.** Writing `<x-wirekit::button onclick="history.back()">` — ordinary HTML — produced a development-time warning saying the prop does not exist. Every `on*` handler was affected.
- **The component sandbox shows a bound attribute for a numeric prop.** Its Show Code panel offered `level="4"` where the schema declares an integer; both render the same today, but the snippet is what a developer copies, and it should teach the shape that stays correct once anything compares the value strictly.
- **The published package manifest no longer carries a development-only Composer `scripts` block.** It referenced a directory the distributed package does not contain. Composer never ran it for an installed dependency, so no existing installation is affected.

### Documentation

- **The `2.21.0` entry now names the 16px floor on text fields that shipped with the touch-target change.** Both live in the same coarse-pointer rule, and the 16px half is the one that stops iOS Safari from zooming the page in when a field takes focus — and not zooming back out. The behavior has been there since 2.21.0; only its description was missing.

## [2.21.0] — 2026-07-26

**Minor release — accessibility fixes across dialogs, touch targets and motion, plus a visible name for floating-action items.** Everything here is additive or a fix; existing markup renders the same.

### Added

- **[Modal](https://docs.wirekit.app/components/modal) gains an `ariaLabel` prop.** A dialog built without a [modal header](https://docs.wirekit.app/components/modal) — a confirmation, a media lightbox, anything whose heading is its own markup — can now be named directly. Without a name and without a header the component says so during development instead of rendering a dialog that screen readers announce as just "dialog".
- **[Floating action](https://docs.wirekit.app/components/fab) items show their name on hover and on keyboard focus.** An icon-only action carried its name only for assistive tech; anyone looking at the screen saw a row of circles. The label appears on hover and on focus, is positioned so it never shifts the buttons, and cannot intercept a click.
- **[Speed-dial actions](https://docs.wirekit.app/components/fab) can hide their visible name.** `hideLabel` takes the name off the screen while `aria-label` keeps it — the bare icon-only dial the pattern started as. The label still shows by default.
- **The single-action [floating button](https://docs.wirekit.app/components/fab) can sit along the top edge.** `placement="block-start"` moves it there, combining with `position` so all four corners are reachable; `block-end` stays the default and existing markup is unchanged. The top offset clears the status bar and notch the same way the bottom one clears the home indicator.
- **`intent` now works on every component whose colors carry a severity.** [Alert](https://docs.wirekit.app/components/alert), [callout](https://docs.wirekit.app/components/callout), [text](https://docs.wirekit.app/components/text), [reading progress](https://docs.wirekit.app/components/reading), timeline items and the circular progress accept `intent` alongside the `variant` they already had — the same spelling [button](https://docs.wirekit.app/components/button) and [badge](https://docs.wirekit.app/components/badge) use. Writing `intent` on one of them used to do nothing at all: it was emitted as a stray HTML attribute and the component rendered its default color. `variant` keeps working unchanged; when both are given, `intent` decides.

### Changed

- **Form controls and buttons reach a 44px touch target on touch devices, and text fields render at 16px there.** Inputs, selects, textareas and buttons rendered 40px tall, which clears the WCAG 2.5.8 minimum but misses the 2.5.5 target of 44×44. The 16px floor on [inputs](https://docs.wirekit.app/components/input), [selects](https://docs.wirekit.app/components/select) and [textareas](https://docs.wirekit.app/components/textarea) is what stops iOS Safari from zooming the page in when a field takes focus — and it does not zoom back out. Both apply only where the pointer is coarse, so the desktop rendering is unchanged.
- **`prefers-reduced-motion` is honored across the component library.** Twenty components animate their entrance and exit; only one treatment was previously covered, so a modal still faded and scaled for a user who had asked their system to reduce motion. The rule is scoped to WireKit's own elements — a developer's own animations are left alone.

### Fixed

- **A [modal](https://docs.wirekit.app/components/modal) without a header no longer renders a dialog with no accessible name.** The panel referenced a title element that only the header creates, so a headerless dialog pointed at nothing — which resolves to no name at all.
- **`aria-*` attributes passed to a [modal](https://docs.wirekit.app/components/modal) now reach the dialog.** They were applied to an outer wrapper with no role, where ARIA prohibits them and assistive tech never sees them.
- **The [floating button](https://docs.wirekit.app/components/fab) documentation now opens the dialog it describes.** The example titled *a single-action FAB that opens a dialog* opened nothing, while the button carried `aria-haspopup="dialog"` — an attribute that describes behavior to assistive technology rather than creating it, so the page was making a promise the code did not keep.
- **A [floating button](https://docs.wirekit.app/components/fab) with words in it is announced by those words.** It carried `aria-label="Action"` unconditionally, so a button reading *Send feedback* was announced as *Action* — and someone using voice control could not activate it by the words on screen. Visible text now becomes the accessible name; an explicit label is kept when it contains that text, and an icon-only button is unaffected.
- **The interactive component previews show WireKit markup in their code panel** instead of compiled HTML. The preview knew which markup it had rendered and did not pass it on, so the documentation site had nothing else to display.

## [2.20.0] — 2026-07-24

**Minor release — sidebar and app-shell layout controls, a floating action button, two form-field affordances, and a run of accessibility and correctness fixes.** Everything here is additive or a fix; existing code renders identically.

### Added

- **A single-action floating action button — [`<x-wirekit::fab.button>`](https://docs.wirekit.app/components/fab).** A circular, screen-corner button for the one primary action a screen offers (compose, add, feedback). It follows the writing direction, keeps clear of the iOS home indicator and a landscape notch via safe-area insets, and announces to assistive tech what it opens.
- **[Sidebar](https://docs.wirekit.app/components/sidebar) sections fold and remember their state.** `<x-wirekit::sidebar.group collapsible>` turns a section heading into a disclosure that folds its items; `<x-wirekit::sidebar.collapsible>` gains the same, a `persist="key"` that remembers the open state across reloads, and a `variant="heading"` that styles its trigger as a small uppercase section label instead of a nav row.
- **[App shell](https://docs.wirekit.app/components/app-shell) gains a `viewport` mode.** `<x-wirekit::app-shell viewport>` pins the shell to the viewport height so the sidebar and main region scroll internally — brand at the top, account menu at the bottom — instead of the page growing past the fold. The default keeps the document-scroll behavior.
- **[Input](https://docs.wirekit.app/components/input) gains a `mono` variant and leading/trailing icon slots.** `mono` renders the field value in the monospace font (codes, SKUs, hashes); `<x-slot:leading>` / `<x-slot:trailing>` place an icon or addon inside the field frame.
- **[Checkbox](https://docs.wirekit.app/components/checkbox) gains a `hideLabel` prop** — a checkbox with no visible label that keeps its accessible name, closing an API gap with the other form controls.
- **[File upload](https://docs.wirekit.app/components/file-upload)'s remove button gains a translatable, overridable `removeLabel`** (default `Remove :name`), so its accessible name is no longer hardcoded English.

### Changed

- **[Dropdown](https://docs.wirekit.app/components/dropdown), popover, menubar, multi-select and color-picker panels follow their trigger on scroll and resize.** An open panel used to stay at its opening coordinates while the page scrolled; it now re-anchors to its trigger and tears its listeners down on close.

### Fixed

- **Form controls that share a `name` on one page now each get a unique DOM `id`.** [Input](https://docs.wirekit.app/components/input), textarea, select and the other name-derived controls emitted the same `id` twice when two fields shared a `name` (a create-and-edit form, a filter bar plus a modal), leaving every field after the first without an accessible name. Each instance now derives a page-unique id, so `label[for]` and `aria-describedby` resolve to the right field. Opt out with `wirekit.a11y.dedupe_ids`.
- **[File upload](https://docs.wirekit.app/components/file-upload)'s remove button now meets the 44px touch-target size.** Its clickable area was ~18px; it now fills a 44×44 target while the visible chip stays small.
- **[Sidebar](https://docs.wirekit.app/components/sidebar) sections no longer disappear in the collapsed icon rail.** A `<x-wirekit::sidebar.collapsible>` hid all its items when the sidebar folded to the icon rail; its child icons now stay reachable as a flat list, like a static group.
- **A retinted active [sidebar](https://docs.wirekit.app/components/sidebar) item keeps its color on hover.** The base hover style is now scoped to non-active items, so a themed active state is not overridden while the pointer is over it.
- **[Tooltip](https://docs.wirekit.app/components/tooltip) honors a `delay` or `offset` of `0`** instead of swallowing the zero and applying the default.
- **Unbound `prop="false"` reads as off** across the remaining tri-state props that still treated the unbound string `"false"` as truthy.
- **Config class overrides now reach dotted sub-components.** `wirekit.components.sidebar.item.classes.*` (and every other `parent.child` sub-component) applied only to top-level components before; the override now resolves for sub-components too.
- **`php artisan wirekit:publish-fonts` re-publishes fonts when the bundled files change**, and `wirekit:doctor` reports stale published fonts, instead of silently leaving an old copy in place.

### Documentation

- **[Alert dialog](https://docs.wirekit.app/components/alert-dialog) and [field](https://docs.wirekit.app/components/field) docs clarify** the action-button count and where a validation error's accessible ownership lives.

## [2.19.0] — 2026-07-23

**Minor release — overlay panels that escape their container, a boolean attribute that finally means what it says, and a slider that shows its labels.** Everything here is additive or a fix; existing code renders identically, except the `prop="false"` case, which was broken before and is now correct.

### Added

- **Dropdown, combobox, multi-select and data-table panels escape a clipping card and cap to the viewport.** Putting one of these inside a [card](https://docs.wirekit.app/components/card) — the most ordinary filter-bar layout there is — used to clip the open panel at the card's edge, and a long menu on a short window clipped its top items. The panels are now positioned against their field and capped to the available height, so they open in full and scroll when tall. No developer wrapper needed.
- **`window.wirekitPosition`** exposes the same tested positioning helper WireKit uses internally, for developers who position their own overlays.

### Fixed

- **`prop="false"` now turns a feature off instead of on.** Passing an unbound boolean attribute — `disabled="false"`, `required="false"`, [faq](https://docs.wirekit.app/components/faq)'s `schema="false"` — used to do the opposite of what it read as, silently, because an unbound Blade attribute is the truthy string `"false"`. Every boolean prop across the component library now reads `"false"`, `"0"`, `"off"` and `"no"` the way they are written. Bound props (`:disabled="false"`) are unchanged. The direction that was broken is the one you reach for deliberately — turning something off.
- **[range-slider](https://docs.wirekit.app/components/range-slider) shows its named values.** `value-text-map` gave each stop a word — "Free", "Enterprise" — but sent it only to screen readers, so the handles and legend still showed the raw number. They now show the word too; unnamed stops fall back to the number.

### Documentation

- **Supported Laravel versions are stated as `12+/13+`.** The package has been built and tested against Laravel 13 for a while; the docs now say so.

## [2.18.3] — 2026-07-22

**Patch release — rendering and accessibility corrections.** Four defects that were invisible in the markup you write and visible in what your users get: a stray space in running text, a boolean attribute that did the opposite of what it said, an accessible name that never arrived, and invalid attributes on rendered elements.

### Fixed

- **Inline components put a visible space in front of whatever followed them.** `Run <x-wirekit::code>wirekit:install</x-wirekit::code>.` rendered as `Run wirekit:install .` — the component's view ended with a newline, and HTML collapses that to a space. It also reached structured data: [faq](https://docs.wirekit.app/components/faq)'s plain-text mode derives its schema answer from the rendered HTML, so the space shipped inside the FAQPage JSON-LD. Sixteen components were affected, among them [code](https://docs.wirekit.app/components/code), [link component](https://docs.wirekit.app/components/link), [kbd](https://docs.wirekit.app/components/kbd), [mark](https://docs.wirekit.app/components/mark) and [badge](https://docs.wirekit.app/components/badge). There was no way to work around this from the call site, because the space originated inside the component's own output.
- **`<x-wirekit::faq schema="false">` emitted the FAQPage JSON-LD anyway.** An unbound Blade attribute arrives as a string, and the string `"false"` is true — so switching the schema off switched it on, silently, with the page rendering normally either way. That is precisely the spelling you reach for when a page carries a second FAQ block and must not emit two competing FAQPage nodes. `schema`, `multiple` and `plain-text` now read `"false"`, `"0"`, `"off"` and `"no"` the way they are written; the bound form `:schema="false"` behaves exactly as before.
- **[faq](https://docs.wirekit.app/components/faq)'s accessible name was not exposed to screen readers.** The `label` prop was applied to an element that ARIA does not allow to carry a name, so assistive technology did not announce it and accessibility scans reported a violation on a page that had done nothing wrong. The question list now carries a role that can hold the name, and only when a name is actually set.
- **`variant`, `size` and `announce-errors` rendered as invalid HTML attributes.** Passing one of these to a component left it in the markup — `<div variant="flush" size="lg">`, `<input announce-errors="false">`. Harmless to the display, but invalid HTML from a library that promises clean semantic output, and visible in any validator. Fixed across all eighteen affected components, including [faq](https://docs.wirekit.app/components/faq), [input](https://docs.wirekit.app/components/input), [select](https://docs.wirekit.app/components/select), [textarea](https://docs.wirekit.app/components/textarea) and [toggle](https://docs.wirekit.app/components/toggle).
- **[range-slider](https://docs.wirekit.app/components/range-slider)'s merged value badge faded in on page load.** When two thumbs sit close enough for their value badges to combine, the combined badge animated in from nothing on first paint instead of simply being there — a visible blink for a state that was never in question. It now paints its initial state outright; moving a thumb into or out of the merge still animates.

## [2.18.2] — 2026-07-22

**Patch release — setup-documentation corrections.** No code changes: the package renders exactly as it did in 2.18.1. What changed is the getting-started walkthrough, which in two places described a setup step inaccurately enough to leave a first-time reader with a failing build.

### Documentation

- **[Getting started](https://docs.wirekit.app/getting-started) told readers to create a `resources/js/bootstrap.js` that their build cannot resolve.** The page treated a missing `bootstrap.js` as a file to be recreated from the block shown. That holds for `laravel/laravel`, which ships the file and lists `axios` in `package.json` — but the Livewire Starter Kit ships neither, so following the instruction there ended in `failed to resolve import "axios"` on the next `npm run build`. The section now distinguishes the two scaffolds, states plainly that creating nothing is the correct action when axios is absent (WireKit never uses it), and gives the install command for readers who want Laravel's baseline regardless.
- **The same block described the axios global as a Livewire requirement.** It is not: Livewire issues its own `fetch()` calls and never reads `window.axios`. The comments said otherwise, which made an explicitly optional step read as mandatory.
- **The first-Livewire-page walkthrough named a command without the flag that produces its files.** The paragraph promised `app/Livewire/Showcase.php` and `resources/views/livewire/showcase.blade.php` while naming `make:livewire` alone. Livewire 4 defaults to a single-file component, so readers who ran it as written got `resources/views/components/⚡showcase.blade.php` and could not find any file the walkthrough went on to edit. The flag is now stated before the claim, together with what happens without it.
- **[Code block](https://docs.wirekit.app/components/code-block) and the [Chart.js advanced guide](https://docs.wirekit.app/components/charts-chartjs/advanced) now install what their examples import.** Both showed an import for a package (`highlight.js` and `chart.js` respectively) whose install step lived on another page.

## [2.18.1] — 2026-07-22

**Patch release — localization and accuracy fixes.** No new capability and no behavior change for an English application; every fix below either corrects output in a non-English locale or removes something that should never have shipped.

### Fixed

- **[alert](https://docs.wirekit.app/components/alert) announced its variant in English whatever the page language was.** The screen-reader prefix a reader speaks before every alert — "Notice", "Warning", "Error" — was a literal, so a German page announced "Notice: Alle Locales werden gemeinsam freigegeben". 2.18.0 made the catalog translatable and this component was missed. The five labels now route through the translator, with the matching keys in the published `en.json`.
- **[alert](https://docs.wirekit.app/components/alert) silently ignored a `role` you passed it.** The component emitted its own `role` before your attributes, and HTML keeps the first occurrence, so your value sat inert in the markup beside the one that won. A caller-supplied `role` now takes precedence.
- **Numbers rendered with English separators in every locale.** [usage-meter](https://docs.wirekit.app/components/usage-meter) grouped thousands as `1,234` where German, Italian, Spanish and Portuguese want `1.234` and French wants a space; [attachment](https://docs.wirekit.app/components/attachment) rendered file sizes as `2.4 MB` instead of `2,4 MB`; [rating](https://docs.wirekit.app/components/rating) announced `4.2`. All three now format for the active application locale. Applications without `ext-intl` keep exactly the output they had — the extension is not a new requirement.
- **[rating](https://docs.wirekit.app/components/rating) announced a hardcoded English sentence.** A readonly rating told screen readers "4.2 out of 5 stars" and named its interactive group "Rating", in every language. Both are translatable now.

### Documentation

- **Structured data's site-identity builders were missing from the 2.18.0 notes.** `Schema::webSite(…)`, `Schema::organization(…)` and `Schema::softwareApplication(…)` build the three nodes almost every marketing page needs, `Schema::graph([…])` combines them into a single `@graph`, and `Schema::node(…)` is the escape hatch for any schema.org type the builders do not model. They shipped in 2.18.0; only the announcement was missing. The [structured-data](https://docs.wirekit.app/components/structured-data) page documents the full set.

## [2.18.0] — 2026-07-22

**Minor release — a broad wave of developer-facing capability, accessibility and accuracy work.** Everything here is additive and backward-compatible: new props default to today's behavior, new commands and components are opt-in, and no existing tag renders differently unless you reach for one of the new options.

### Added

- **`wirekit:publish-fonts` publishes the font families your config names, not the whole tree.** Point `fonts.sans` / `fonts.serif` / `fonts.mono` at bundled families and this command copies exactly those into `public/`, roughly 430 KB for a typical two-family setup against 5.8 MB for everything. `--all` publishes the full tree (for an app with a runtime font picker), `--prune` removes families the config no longer names, `--force` overwrites. See [fonts](https://docs.wirekit.app/components/fonts).
- **Fonts now load even when they were never published.** A configured family is served straight from the installed package over a `/wirekit/fonts/…` route, so a page can no longer silently fall back to system fonts because a publish step was missed. Publishing remains the fast path (a static file beats a PHP round trip); it is now a performance choice, not a correctness one.
- **The whole component catalog is translatable.** Every user- and screen-reader-visible string routes through Laravel's translator, WireKit registers its own language directory, and a `wirekit-lang` publish tag drops a complete `en.json` reference into your app to rename per locale and fill in. A new [localization guide](https://docs.wirekit.app/localization) walks the publish → translate flow. A developer building a multilingual product can now localize WireKit itself, which was previously impossible without republishing every view.
- **[range-slider](https://docs.wirekit.app/components/range-slider) announces a spoken value per handle.** A new `valueTextMap` prop gives each thumb its own `aria-valuetext` — a tier range can announce "Free" and "Enterprise" instead of "0" and "100". Unnamed stops fall back to their number.
- **New `<x-wirekit::form>` wrapper sets one error-announcement policy for every control inside it**, so you configure `aria-live` error behavior once per form rather than per field.
- **[theme-controller](https://docs.wirekit.app/components/theme-controller) gained a `cookie` storage driver and configurable button chrome.** The cookie driver lets the server render the correct theme on first paint (no flash); the chrome (size, surface, icon slots) is now overridable.
- **[stream](https://docs.wirekit.app/components/stream) gained a `fetch` transport and a `manual` mode**, the two shapes it needed to drive a token stream from a plain fetch response or from your own code rather than only Server-Sent Events.
- **[countdown](https://docs.wirekit.app/components/countdown) is now a headless clock** — a slot plus a full `remaining` breakdown (days/hours/minutes/seconds), so you can render the time however you like.
- **[pricing-table](https://docs.wirekit.app/components/pricing-table) renders its own monthly/annual toggle and fits its grid to the plan count**; `pricing-tier` now forwards minor-unit and locale price formatting instead of dropping it.
- **[status-tiles](https://docs.wirekit.app/components/status-tiles) gained an opt-in `showStatus`**, [image-gallery](https://docs.wirekit.app/components/image-gallery) a `fit` prop, and [faq](https://docs.wirekit.app/components/faq) a `plain-text` schema answer with a per-item escape hatch — each additive and byte-compatible by default.
- **Media-playback icons ship in every base preset** (play / pause / stop / etc.), so a player UI has consistent glyphs without a custom icon set.
- **Sub-components are discoverable through the AI-tooling surface.** `wirekit:show card.body`, the JSON export, the MCP catalog and the Boost manifest now report every sub-component with its own props — `table.th`'s column-scope option, `card.body`, and the rest — so an editor or agent authoring WireKit markup can find the composition pieces, not just the top-level tags.

### Changed

- **A published `config/wirekit.php` is no longer a ceiling.** The config merge is now recursive, so an app that published the config once keeps receiving every new component default a later release adds, while still winning on any value it actually set. Previously a published `components` array replaced the package's entire section, quietly freezing the config to the keys it had the day it was published. `wirekit:doctor` gained a check that names any section your published file is missing.
- **The AI-tooling manifest and Boost skill derive each component's tag from the registry**, so the class-based chart's real tag is reported correctly, and **`ComponentRegistry` reads props for directory-form components**, not just top-level ones.

### Fixed

- **[slider](https://docs.wirekit.app/components/slider) announced a value the reader could no longer see.** Under `wire:model`, a server-driven change moved the thumb but not the spoken value, so a screen reader read the old number — and with a labeled mark map, the wrong *meaning*. The component now re-reads the element after each Livewire update. Apps without Livewire are unaffected.
- **Accent-toned text no longer drops below contrast when a brand accent is re-tinted**, and the theme-controller `<select>` and data-table search `<input>` moved onto the contrast-compliant resting border token.
- **A batch of documentation-accuracy and accessibility corrections** across component pages: row-header styling, a named radial-progress, correct status-tile link semantics, prop-list and default-value fixes on several pages, and the shipped Cursor rules no longer teach a handful of wrong patterns.

## [2.17.1] — 2026-07-20

**Patch release — dependency maintenance.** No API change and nothing to migrate: the bundled positioning engine moves forward a minor version, and the chart adapter is confirmed against the next major of its optional peer.

### Changed

- **Bundled Floating UI updated to 1.8.** This is the positioning engine behind [dropdown](https://docs.wirekit.app/components/dropdown), [tooltip](https://docs.wirekit.app/components/tooltip), [modal](https://docs.wirekit.app/components/modal), [combobox](https://docs.wirekit.app/components/combobox) and [menubar](https://docs.wirekit.app/components/menubar). It ships inside the WireKit bundle, so there is nothing to install — placement, flipping and collision behavior are unchanged.
- **The [Chart](https://docs.wirekit.app/components/chart) ApexCharts adapter now supports ApexCharts 6 alongside 5.** The peer dependency you install can be either major; the adapter's option shape and theming are the same on both.

## [2.17.0] — 2026-07-20

**Minor release.** Additive and backward-compatible throughout: a new form-control hover token, per-preset font publish tags, and a form-control accessibility pass finished across every control, every hover state, and every theme preset. A form-control accessibility pass that finishes what 2.16.0 started, an internationalization fix for pagination, and a stdio server that no longer gives up after a minute of quiet. Everything here is additive and backward-compatible.

### Added

- **`--color-wk-border-strong-hover` design token.** The hover border for form controls, and the counterpart to the `--color-wk-border-strong` resting border that shipped in 2.16.0. One value is correct in both themes: it sits below the resting border on light and above it on dark, so the edge always moves *away* from the field fill rather than toward it. See [design tokens](https://docs.wirekit.app/theming/design-tokens).
- **Per-preset font publish tags.** `vendor:publish` now offers a tag per font preset instead of only the all-or-nothing bundle, so an app can publish just the family it uses. A newly added preset gets its tag automatically. See [fonts](https://docs.wirekit.app/components/fonts).
- **[Segmented Control](https://docs.wirekit.app/components/segmented-control) selected / unselected appearance.** The two segment states are now addressable through theming and personalization, which previously could not reach them because the appearance was decided at runtime rather than at render time.

### Fixed

- **Every form control now keeps a 3:1 border, at rest and on hover.** 2.16.0 fixed the resting border on [input](https://docs.wirekit.app/components/input), [select](https://docs.wirekit.app/components/select), [textarea](https://docs.wirekit.app/components/textarea) and [checkbox](https://docs.wirekit.app/components/checkbox); fourteen sibling controls still drew theirs from the decorative border token at 1.29:1 in the light theme and 1.56:1 in the dark. All of them now use `--color-wk-border-strong`. Separately, the hover state pointed at a decorative token that is *lighter* than the resting border, so a control that met the contrast floor dropped to 1.87:1 precisely while the pointer was on it — the worst possible moment to lose a visible edge. Hover now uses the new `--color-wk-border-strong-hover`.
- **Every theme preset keeps its form-control borders contrast-compliant.** Presets that tint their own control border — [Aurora](https://docs.wirekit.app/theming/aurora), Brutalist, Retro Terminal, Cupertino — now define `--color-wk-border-strong` and `--color-wk-border-strong-hover` alongside it, so a form control keeps the theme's edge instead of falling back to the stock neutral, and every one clears 3:1 against the field fill in both light and dark. Aurora's values were also corrected (they had reached 1.75:1 light / 2.13:1 dark). If you pasted a preset block into your own stylesheet, re-copy it.
- **`wirekit:mcp-serve` no longer exits after 60 seconds of quiet.** Editors that launch the server over a socket rather than a pipe — which is what Node-based clients do — hit PHP's socket read timeout, and the server read that timeout as the client hanging up. It exited with status 0 and an empty error stream, so the editor reported a disconnect with nothing to go on, a reconnect worked instantly, and the whole thing looked like flakiness. Idle timeouts and real hangups are now told apart.
- **[Countdown](https://docs.wirekit.app/components/countdown) segments no longer overflow on a narrow screen.** The boxed-segment row did not wrap, so a five-unit countdown ran past the edge of a phone-width viewport, and the box-pulse animation clipped its own outermost segment. The row now wraps and reserves room for the pulse.
- **[Pagination](https://docs.wirekit.app/components/pagination) is translatable as whole sentences.** The summary and the page indicator were assembled from separate fragments, which cannot be translated correctly into languages that order those parts differently. Each is now a single translatable string with placeholders. The translation keys are documented for the first time.
- **[Sidebar](https://docs.wirekit.app/components/sidebar) item counters no longer push the icon off-center in the collapsed rail.** A `sidebar.item` carrying a `badge` shared its flex row with the icon at rail width. The counter now becomes a corner dot when the rail is collapsed, with the digits kept available to screen readers.

### Documentation

- **Bundle sizes are quoted in one place.** [Dependencies](https://docs.wirekit.app/dependencies) is the only page that states them, and it is the only one measured against the shipped files; other pages link to it. Two pages had drifted apart on the same figure.

## [2.16.1] — 2026-07-20

**Patch release — re-publishes 2.16.0.** Nothing here is new. The 2.16.0 package that reached Packagist was built from an incomplete tree: it is missing the [Status Tiles](https://docs.wirekit.app/components/status-tiles) and [Stream](https://docs.wirekit.app/components/stream) components entirely, along with most of the other changes listed under 2.16.0 below. The tag and the source were correct throughout; only the published package was not.

**If you installed 2.16.0, upgrade.** Everything the 2.16.0 notes describe is in this release and nothing else changes, so the upgrade carries no migration and no behavior difference beyond gaining what 2.16.0 promised.

## [2.16.0] — 2026-07-20

**Minor release.** Two new components plus a broad set of accessibility, internationalization, and composition improvements, and a new form-control border token that meets WCAG non-text contrast. Everything here is additive and backward-compatible.

### Added

- **[Status Tiles](https://docs.wirekit.app/components/status-tiles).** N entities as colored status tiles read at a glance — a fleet light, with an optional count-per-intent legend. Status never rides on color alone: every tile carries a distinct icon shape and a screen-reader status word, and an `href` makes a tile a keyboard-operable link.
- **[Stream](https://docs.wirekit.app/components/stream).** A primitive for streaming text output over Server-Sent Events, with the hard parts handled once — a single live region announces that a response is generating and then the result (never re-read on every token), `prefers-reduced-motion` reveals the buffered text at once, and a dropped connection or an explicit stop resolves to a defined terminal state. A `simulate` mode types a fixed string out from a local timer for a demo or typewriter effect with no endpoint.
- **[Badge](https://docs.wirekit.app/components/badge) `wrap`.** A long label can now wrap across lines: the pill grows with the text instead of the second line spilling below its surface.
- **[Image Gallery](https://docs.wirekit.app/components/image-gallery) per-item overlays.** An `itemOverlay` render-callback layers a control — a badge, a report button, a required content label — over each thumbnail as a sibling of the zoom trigger, so the thumbnail still opens the lightbox while the overlay stays interactive.
- **[Command Palette](https://docs.wirekit.app/components/command-palette) server-side search.** The palette now emits a debounced query event on every keystroke, so you can drive a server-backed search — feed the query to a Livewire property and re-render the results — instead of only filtering a fixed list.
- **[Slider](https://docs.wirekit.app/components/slider) `valueTextMap`.** The spoken `aria-valuetext` can now be decoupled from the visual tick labels — show numeric ticks yet announce semantic meaning (`1 => 'Low'`).
- **[Countdown](https://docs.wirekit.app/components/countdown) completion event.** The countdown now dispatches an event when it expires and exposes its `done` state, so it can drive a sibling control without running a second clock.
- **[Sidebar](https://docs.wirekit.app/components/sidebar) item badge.** `sidebar.item` gained a `badge` prop for a trailing unread counter — the common notifications / inbox nav pattern.
- **[Table](https://docs.wirekit.app/components/table) row headers.** `table.th` gained a `headerScope` prop so a per-row header cell can be `scope="row"`, previously reachable only as a column header.
- **[Tooltip](https://docs.wirekit.app/components/tooltip) `focusableTrigger`.** A tooltip on a non-interactive trigger — an icon, a text span — can now be reached by keyboard instead of being hover-only.
- **Design tokens.** The `--gap-wk-*` scale is now the full `xs`…`2xl` ladder — the same rung labels as `--space-wk-*`, with its own tighter values, and a new `--color-wk-border-strong` token gives form controls a contrast-compliant resting border. See [design tokens](https://docs.wirekit.app/theming/design-tokens).
- **Error-announcement config default.** A new `wirekit.a11y.announce_error` config key lets an app that runs its own live region opt every form control out of the built-in error announcement in one place, instead of per control.

### Fixed

- **Form-control borders now meet WCAG non-text contrast (1.4.11).** [Input](https://docs.wirekit.app/components/input), [select](https://docs.wirekit.app/components/select), [textarea](https://docs.wirekit.app/components/textarea) and [checkbox](https://docs.wirekit.app/components/checkbox) drew their resting edge at 1.29:1 in the light theme — effectively invisible against the field fill. They now use the dedicated `--color-wk-border-strong` token (≥3:1 in both themes); the decorative border on cards and dividers is unchanged.
- **Fonts no longer fall back to system fonts silently in production.** A configured but unpublished bundled font now leaves a visible signal in every environment plus a throttled server-side warning, instead of vanishing to system fonts with no trace outside local development.
- **[Editor](https://docs.wirekit.app/components/editor) binding and custom toolbar.** `wire:model` on the editor now binds the textarea — it previously landed on the wrapper and lost input — and a custom `toolbar` slot now renders instead of being overridden by the default set.
- **[Notification Center](https://docs.wirekit.app/components/notification-center) announcements.** New notifications are now announced to screen readers through a polite live region.
- **[Pagination](https://docs.wirekit.app/components/pagination) nested-route 404.** The component now emits absolute hrefs, so a paginator under a nested Livewire route no longer 404s; and every user-visible string — body text and aria-labels — is now translatable.
- **[Combobox](https://docs.wirekit.app/components/combobox) attribute forwarding.** Every caller attribute now reaches the `role="combobox"` input rather than a roleless wrapper.
- **Translatable strings.** The "opens in new tab" screen-reader hint on the [link component](https://docs.wirekit.app/components/link) and its siblings, the threshold band labels and reset-cadence notes on the [usage meter](https://docs.wirekit.app/components/usage-meter), and the copy-confirmation text on the [clipboard button](https://docs.wirekit.app/components/clipboard-button), are now translatable instead of hardcoded English.
- **[Checkbox](https://docs.wirekit.app/components/checkbox) description association.** A caller `aria-describedby` is now merged with the checkbox's own hint / error target instead of being silently dropped.
- **[Table](https://docs.wirekit.app/components/table) sort headers.** A sortable column in Livewire sort mode no longer shows a pointer cursor across the whole cell when only the button is the click target.
- **Contrast helper parses `rgb()`.** `WcagContrast::parseToLinearRgb()` now understands `rgb()` / `rgba()` color strings, not only `#hex` and `oklch()`.

## [2.15.0] — 2026-07-19

**Feature release.** A broad set of marketing, ecommerce, control and mobile components — the blocks a landing page, a store, and a mobile app-shell were missing — plus SEO structured-data builders and a drop-in dark-mode toggle. All additive; one existing component (Carousel) was rebuilt on a more robust foundation with no API break.

### Added

- **[Pricing Table](https://docs.wirekit.app/components/pricing-table).** The plan grid — tiers, features, a highlighted plan, monthly/annual framing — the shape a SaaS pricing page needs, composed from tokens rather than a bespoke layout.
- **[Testimonial](https://docs.wirekit.app/components/testimonial).** A cited quote with author, role, avatar and an optional read-only star rating (announced as a single record, never an operable control), plus a grid to lay several out.
- **[FAQ](https://docs.wirekit.app/components/faq).** An accordion of questions that emits `FAQPage` JSON-LD **derived from what it actually rendered** — the structured data can't drift from the visible copy because it is generated from it.
- **[Logo Cloud](https://docs.wirekit.app/components/logo-cloud)** and **[Team Section](https://docs.wirekit.app/components/team-section).** The two marketing blocks WireKit had no exemplar for — a "trusted by" logo strip and a people grid.
- **[Announcement Banner](https://docs.wirekit.app/components/announcement-banner).** A dismissible page-edge bar (top or bottom) with an optional inline CTA. The dismissal persists by default; set `persist` off for a session-only bar that reappears next visit instead of being remembered.
- **[Bento Grid](https://docs.wirekit.app/components/bento-grid).** An asymmetric feature showcase — cells span a real column/row ladder and collapse to a single stacked column when the grid itself is narrow (a container query — a sidebar, a card, a split view — reflowing on its own width, independent of the viewport), reclaiming their spans once it is wide again; an unknown span degrades to a normal cell rather than a broken track claim.
- **[Mockup](https://docs.wirekit.app/components/mockup).** Frame chrome — browser, window, code, phone and tablet — for screenshots and demos. Every surface, border and shadow is a design token, so the frame follows the theme; the chrome is decorative and hidden from assistive technology.
- **[Product Card](https://docs.wirekit.app/components/product-card).** The ecommerce keystone — image, price with an optional compare-at, rating, stock state, and a call-to-action.
- **[Button Group](https://docs.wirekit.app/components/button-group).** Welds adjacent controls (buttons, an input + button) into one unit — inner radii collapsed to a single seam, RTL-safe via logical properties.
- **[Toggle Button](https://docs.wirekit.app/components/toggle-button).** An `aria-pressed` toggle — a button that stays down, for a single on/off state. Opt into `self-toggle` and it flips its own pressed state on click (a formatting toolbar with no wiring); the controlled form — the pressed state lives in your app — stays the default.
- **[Indicator](https://docs.wirekit.app/components/indicator).** A corner-badge positioner — a count or dot that rides the corner of any element (an avatar, an icon button) via logical insets.
- **[Radial Progress](https://docs.wirekit.app/components/radial-progress).** A circular progress ring — a real `progressbar` with the value announced, drawn from tokens. Opt into `animate` to sweep the fill from empty on first paint and animate later value changes instead of snapping, gated by `prefers-reduced-motion`.
- **[Bottom Nav](https://docs.wirekit.app/components/bottom-nav)** and **[FAB](https://docs.wirekit.app/components/fab).** The two mobile app-shell pieces WireKit was missing — a fixed bottom tab bar (opt into `interactive` to track the current tab client-side: clicking a tab marks it active with no page load) and a floating action button that fans out into secondary actions (keyboard-operable).
- **[Theme Controller](https://docs.wirekit.app/components/theme-controller)** and **[Swap](https://docs.wirekit.app/components/swap).** Drop-in dark mode — a control that toggles the theme with no wiring and no flash-of-wrong-theme, and a `swap` primitive that cross-fades between two states (the sun/moon icon being the canonical case).
- **Schema builders.** Typed PHP builders for schema.org JSON-LD (the same engine the FAQ block uses to derive its `FAQPage` data), for emitting structured data from your own components.

### Changed

- **[Carousel](https://docs.wirekit.app/components/carousel) was rebuilt on native scroll-snap.** The slider now rides the browser's own scroll-snap instead of a JavaScript transform, so it is smoother, keyboard- and touch-native, and lighter; autoplay gained a stop button, and a `perView` shows 2–4 slides at once. No API break — existing usage keeps working.

### Fixed

- **[Progress](https://docs.wirekit.app/components/progress) — label and value no longer jam at narrow widths.** The label / value row gained a column gap so the two can never touch.

## [2.14.0] — 2026-07-18

**Feature release.** The Chat / AI Conversation suite: six new components for building chat transcripts and AI-native surfaces, plus fade, delivery-status, readability, animated-progress and copy-affordance additions to existing components. All additive — nothing changes for existing components — with one accessibility fix to the rating component.

### Added

- **[Shimmer](https://docs.wirekit.app/components/shimmer).** An animated highlight that sweeps across the letterforms of live text — the "Generating response…" / "Thinking…" affordance for AI streaming and long-running status. Unlike a skeleton's block shimmer, this shimmers the real copy rather than a gray placeholder, so the text stays readable to a screen reader throughout. Bind `active` to a streaming flag and it turns itself on and off with no conditional markup and no JavaScript. The sweep is disabled under `prefers-reduced-motion` and its duration is a token you can override per instance.
- **[Conversation](https://docs.wirekit.app/components/conversation).** A stick-to-bottom transcript scroller for chat and streaming. It keeps the newest message in view while a response streams in, but the moment the reader scrolls up to read history it stops yanking them back down — and offers an announced "jump to latest" with an unread count instead. New content appended above the fold never shifts what the reader is looking at. The viewport is a labeled live region, reachable and scrollable by keyboard.
- **[Attachment](https://docs.wirekit.app/components/attachment).** The display card for a file on a message — icon, name, human-readable size and type — and an `attachment-group` that lays several out, stacked by default or as a scroll-snapped row. Sizes and types are formatted server-side, so nothing about the file depends on JavaScript. Fills the existing `attachments` slot on the message component. An opt-in `animate` gives the uploading bar a shimmer sweep so an in-flight upload reads as active; it is disabled under `prefers-reduced-motion`.
- **[Chat Marker](https://docs.wirekit.app/components/chat-marker).** The in-thread meta row for the things in a conversation that are not messages — a streaming or tool-call status, a system note, a "New messages" divider, a timestamp separator. Presentational by design: compose a link or button inside it when a marker needs to be actionable.
- **[Message Typing](https://docs.wirekit.app/components/message-typing).** The three-dot "someone is composing" indicator. Given an `author`, it shows that person's avatar and name above the dots — indented like their message — so the reader sees who is typing. It carries a visually-hidden "… is typing" text so a screen-reader user loses nothing when the dots freeze under `prefers-reduced-motion`, and it is a static announcement rather than a per-keystroke flicker that would flood assistive tech.
- **[Assistant Message](https://docs.wirekit.app/components/assistant-message).** The AI turn — the assistant-side counterpart to the human chat bubble, and WireKit's first AI-native surface. Assistant, user and system roles (a system turn is a centered notice), a streaming state, a prose body, an optional model chip on assistant turns, a collapsible reasoning disclosure, and footer chips and actions slots. The streaming body is announced a complete sentence at a time rather than character by character, so a screen reader hears finished thoughts, never a half-clause re-read on every token. An `intent` tints the bubble when a turn IS a state — an error answer (danger), a caution (warning), a confirmation (success) — with the body text kept regular so the tint reads as a marker, never as low-contrast colored text.

### Changed

- **[Scroll Area](https://docs.wirekit.app/components/scroll-area) gained an edge fade.** A `fade` option masks the overflow edges along the scroll axis so the content itself dissolves toward the edge — adapting to any background automatically, since there is no colored overlay to keep in sync with the surface. The fade lifts entirely when a child near the edge takes keyboard focus, so a focused control is never faded out from under the reader. Edge depth is a token.
- **[Message](https://docs.wirekit.app/components/message) gained a delivery-status ladder.** A `status` prop shows sending → sent → delivered → read (or failed), the affordance a messaging UI needs for a bubble's own state. It follows the convention every messaging app shares — one check for sent, two checks for delivered, two accent checks for read — and is never carried by color or glyph alone: each rung states its own wording for assistive technology. Pass `statusTime` and the glyph carries a tooltip with the exact moment ("Read at 9:15 AM"), woven into its accessible text too so a screen reader gets the time without the hover.
- **[Progress](https://docs.wirekit.app/components/progress) gained an optional animated fill.** An `animation` overlays motion on a determinate bar — `stripes` (a barber-pole) or `shimmer` (a light sweep) — the "work in flight" affordance for uploads and streaming. The bar's value is unchanged and the motion is disabled under `prefers-reduced-motion`.
- **[Clipboard Button](https://docs.wirekit.app/components/clipboard-button) gained a bare `icon-only` mode.** A compact copy glyph — muted gray at rest, popping to the success green on copy — for action rows where a full labeled button is too heavy. Requires an `aria-label` since there is no visible text to name it.
- **[Prose](https://docs.wirekit.app/components/prose) gained readability presets.** A `preset` picks a reading rhythm — tighter line spacing for chat, roomier line spacing for long-form reading, larger text for emphasis — on top of the existing prose styling. (This is the vertical rhythm only; the separate `measure` prop that clamps line length is untouched.)

### Fixed

- **[Rating](https://docs.wirekit.app/components/rating): a read-only rating is now a record, not a switched-off control.** A read-only rating used to render a hidden form field and announce itself as a radio group with several stars simultaneously "selected" — so a page showing a dozen product scores carried a dozen stray form fields, and a screen reader heard a broken multi-select. A read-only rating is now announced as a single image with one readable value ("4.2 out of 5 stars"), carries no form field, and exposes nothing to operate. The interactive rating — where the reader gives a score — now implements its full documented keyboard model: both arrow axes move the selection, and Home / End jump to the first and last star.

## [2.13.0] — 2026-07-16

**Feature release.** Four new components for user-content media and running deadlines — three Display (countdown, image, image gallery) and a reusable media-viewer Overlay (lightbox). All additive — nothing changes for existing components.

### Added

- **[Countdown](https://docs.wirekit.app/components/countdown).** A live countdown to an absolute deadline, ticking down client-side with no polling. The display scales from years down to seconds (a far-off deadline reads in years and days, not tens of thousands of days), or you can pick an exact set of units. It colors itself when the deadline is near (urgent) and again once it has passed (overdue). Large values get locale-aware thousands separators, an optional `segments` variant renders each unit as a labeled box that animates when its value changes — either the whole box pulses (`animate="box"`, the default) or only the changing number flashes color (`animate="text"`), both honoring `prefers-reduced-motion` — and it stays accessible — the ticking value is exposed to screen readers as a stable deadline label, not announced every second.
- **[Image](https://docs.wirekit.app/components/image).** A content image rendered as a semantic `<figure>` with required alt text, native lazy loading, an optional CLS-safe ratio box (space reserved before the image loads), `cover`/`contain` fit, and an optional caption. Renders only — a signed, ACL-protected download URL works unchanged.
- **[Image Gallery](https://docs.wirekit.app/components/image-gallery).** A responsive grid of content images with an accessible lightbox. Clicking a thumbnail opens a focus-trapped dialog that navigates with the arrow keys, closes on Escape, and returns focus to the thumbnail it opened from. Set `:lightbox="false"` for a plain responsive grid.
- **[Lightbox](https://docs.wirekit.app/components/lightbox).** The gallery's zoom overlay as a standalone component you can drive from any trigger and any layout — images, video, and embeds. It is a focus-trapped dialog that steps through its items with the arrow keys, closes on Escape, and returns focus to whatever opened it. Open it from a control inside the component or from anywhere on the page via a `wirekit-lightbox-open` event; configure looping, captions, and a per-instance backdrop color. Media fills most of the viewport (very wide and very tall images, and video, scale to ~90% of the screen with their aspect ratio intact); off-screen slides load lazily and show a loading spinner until they resolve; captions sit on a semi-transparent dark scrim for readability and wrap centered under the media. The Image Gallery now builds on it internally.

## [2.12.0] — 2026-07-16

**Feature release.** A wave of accessibility and Livewire-integration improvements across the form controls, plus new infrastructure icons. Every change defaults to the current behavior, so upgrading is safe and requires no code changes.

### Added

- **[Combobox](https://docs.wirekit.app/components/combobox) — accessible name.** New `label`, `hideLabel`, and `ariaLabel` props, matching Select and Multi-Select. A combobox in a facet toolbar can now carry a proper accessible name without hand-rolling an external `<label for>`.
- **Form controls — announced validation errors.** Every error-rendering control (Select, Checkbox, Radio, Textarea, Toggle, [Number Input](https://docs.wirekit.app/components/number-input), OTP Input, Password Input, Multi-Select, Combobox, Tags Input, Time Picker, Date Picker, Editor) now renders its error message in a polite live region by default, so a server-side validation error that appears after submit is announced even when focus has moved to the submit button. Opt out per field with `:announce-error="false"`.
- **[Slider](https://docs.wirekit.app/components/slider) — labeled-mark announcements.** When `marks` is a labeled map (`[0 => 'Low', 100 => 'High']`), the slider now exposes the label to assistive tech via a live `aria-valuetext` and shows it in the value tooltip — a screen reader announces "Low" instead of "0". Plain sliders are unchanged.
- **[Button](https://docs.wirekit.app/components/button) — scoped loading spinner.** New `loading-target` prop scopes the loading spinner and disable to the button's own Livewire action, so on a polling page the spinner no longer flashes on every `wire:poll` refresh or unrelated action.
- **[Table](https://docs.wirekit.app/components/table) — keyboard-operable Livewire sort.** New `sort-action` prop on `<x-wirekit::table.th>` wraps the header label in a real `<button>` with a focus ring, so server-side sorting is operable by keyboard, not mouse only.
- **[Message](https://docs.wirekit.app/components/message) — accessible actions and localized time.** The actions slot now reveals on hover **or** keyboard focus (and `actions-reveal="always"` keeps it visible for touch). The timestamp is now locale-aware — `9:15 PM` for English, `21:15` for German and other 24-hour locales — with a `time-format` prop for an explicit format.
- **[Icon](https://docs.wirekit.app/components/icon) — infrastructure glyphs.** New aliases across every base preset: `server`, `database`, `cloud`, `shield` / `shield-check`, `inbox`, `bolt`, and `refresh`.
- **Theming — auditable tinted surfaces.** `Pushery\WireKit\Theming\WcagContrast` now parses `color-mix(in srgb, …)` values, so the soft (tinted) surfaces used by Badge, Alert, and Stat can be contrast-checked directly.

### Fixed

- **Brand — responsive logo no longer vanishes.** `<x-wirekit::brand>` built its responsive show/hide classes by splicing the breakpoint into the class name at render time, which a Tailwind v4 CSS-first build cannot discover — so the desktop logo could silently disappear after a WireKit upgrade. The classes are now emitted as full literals.
- **Segmented Control, [Rating](https://docs.wirekit.app/components/rating), OTP Input — `wire:model` modifiers.** These controls dropped `wire:model` modifiers (`.live` / `.blur` / `.debounce`) when forwarding the binding to their hidden input, so a live binding never updated. All modifier forms are now forwarded.
- **[Input](https://docs.wirekit.app/components/input) — `inputmode` no longer warns.** Standard HTML attributes like `inputmode`, `enterkeyhint`, `autocapitalize`, and `spellcheck` no longer log a spurious "unknown prop" warning.
- **Icon — no more crash on an unknown alias.** An unknown icon name in a browser request now renders an inert placeholder and logs the problem, instead of throwing and taking down the whole page. Console and test runs still fail fast so typos surface early.
- **[Combobox](https://docs.wirekit.app/components/combobox) — the dropdown now matches its size.** A large or small combobox opened a dropdown whose option rows stayed medium-sized; the option padding and text now scale with the `size` prop so the open panel matches its trigger.
- **[Combobox](https://docs.wirekit.app/components/combobox) — no stray focus warning.** Clicking the chevron to close the dropdown could leave keyboard focus on the decorative toggle, which browsers flag as focus trapped on an `aria-hidden` element. Focus now always returns to the input.
- **[Button](https://docs.wirekit.app/components/button) — a joined input abuts its button cleanly.** When an `<x-wirekit::input>` is joined to a trailing button inside `<x-wirekit::button.group>`, the field's seam-side corner is now squared and its duplicated seam border removed, so the input meets the button flush instead of showing a rounded edge and a doubled border.

## [2.11.1] — 2026-07-04

**Patch release.** Documentation-only clarifications to the Getting Started guide. No component code changed — upgrading is optional.

### Documentation

- **[Getting Started](https://docs.wirekit.app/getting-started) — JavaScript pipeline.** Clarified that `resources/js/app.js` and `resources/js/bootstrap.js` already ship with a standard Laravel app, so there is nothing to create there. The one thing that matters is not importing Alpine yourself — Livewire provides it.
- **Getting Started — layout example.** The example layout now shows the `@wirekitStyles` directive in `<head>` alongside `@wirekitScripts`, so pasting it verbatim loads WireKit's design tokens (colors, spacing, radii, shadows).
- **Getting Started — your first Livewire page.** Clarified that `make:livewire` scaffolds empty files whose contents you then replace with the example shown, and that the example route replaces Laravel's default welcome route instead of adding a second route for `/`.

## [2.11.0] — 2026-07-02

**Minor release.** Overridable navigation-landmark and heading-level semantics, screen-reader-announced form errors, and an engine-neutral rich-text-editor factory — all backward-compatible.

### Added

- **[`<x-wirekit::sidebar>`](https://docs.wirekit.app/components/sidebar) gains a `label` prop for the navigation landmark's accessible name.** It defaults to `"Sidebar"`; override it — or pass `aria-label` / `aria-labeledby` directly — when a page has more than one navigation landmark, so assistive technology can tell them apart. No duplicate or conflicting name is emitted.
- **[`<x-wirekit::empty-state>`](https://docs.wirekit.app/components/empty-state) gains a `level` prop for the title's heading level.** Choose `1`–`6` (default `3`) to fit the surrounding document outline so screen-reader heading navigation stays correct. The default still renders an `<h3>`.
- **[`<x-wirekit::field>`](https://docs.wirekit.app/components/field) and [`<x-wirekit::input>`](https://docs.wirekit.app/components/input) announce validation errors to screen readers.** The error message now renders as a polite ARIA live region by default, so an error that appears dynamically — for example after a Livewire round-trip — is announced without the focus having to return to the field. Opt out with `announceError="false"` when your page runs its own live region.

### Changed

- **The [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) rich-text component now uses an engine-neutral `window.wirekitEditor(config)` factory.** The previous `window.tiptapEditor` name keeps working as a deprecated alias, so upgrading is a one-line rename in your editor bootstrap.

## [2.10.0] — 2026-07-01

**Minor release.** An opt-in accent stripe for callouts, a readable line-length measure for prose, a refined intent treatment on KPI tiles and stage cards, and refreshed marketing-blueprint copy — all backward-compatible.

### Added

- **[`<x-wirekit::callout>`](https://docs.wirekit.app/components/callout) gains an opt-in `stripe` prop.** It adds a one-sided accent bar and is off by default (the plain callout is the alert-style tinted border). Pass `stripe` to bring the bar back.
- **[`<x-wirekit::prose>`](https://docs.wirekit.app/components/prose) gains a `measure` prop — a readable line-length clamp.** Long-form text caps at a comfortable ~65 characters per line by default; use `measure="wide"` (~78ch) for a roomier column or `measure="none"` for the full container width. Themeable via the new `--measure-wk` token.

### Changed

- **[`<x-wirekit::callout>`](https://docs.wirekit.app/components/callout) no longer shows the accent stripe by default.** The plain callout is now the balanced tinted border; add the new `stripe` prop to restore the one-sided bar.
- **[`<x-wirekit::stat>`](https://docs.wirekit.app/components/stat) and [`<x-wirekit::stage-card>`](https://docs.wirekit.app/components/stage-card) intent treatment is now a balanced 4-sided tinted border** (previously a one-sided left bar). The intent color cue is unchanged — only the shape is refined.
- **[`<x-wirekit::prose>`](https://docs.wirekit.app/components/prose) now clamps long-form text to a readable measure by default.** Existing prose wraps at ~65ch; set `measure="none"` to restore the previous full-container-width behavior.

### Documentation

- Refreshed the marketing-blueprint demo copy (hero, testimonials, feature, and landing previews) with neutral, concrete placeholder content.

## [2.9.0] — 2026-06-28

**Minor release.** A local MCP server, discoverable AI tooling, new SaaS icon aliases, a dropdown form-submit affordance, dev-mode composition + prop warnings, and documentation — all backward-compatible.

### Added

- **A local MCP server — `php artisan wirekit:mcp-serve`.** AI coding assistants spawn it over stdio and query the component catalog live while authoring, so the editor reads real prop signatures and design tokens instead of guessing them. It is local and read-only — no port, no daemon, no network, always version-matched to your installed WireKit. See the [AI-tooling guide](https://docs.wirekit.app/ai-tooling) for copy-paste editor config and the [CLI reference](https://docs.wirekit.app/cli-reference).
- **A tool-neutral `AGENTS.md` at the package root.** It points AI coding assistants at the `wirekit:list` / `wirekit:show` / `wirekit:icons` discovery commands, so assistants that do not read the Cursor rules still find the tooling. The README gains a matching "Using WireKit with AI assistants" section.
- **A Laravel Boost skill manifest — `php artisan wirekit:boost-skills`.** Publishes `.boost/wirekit.json` — every component with its real props + defaults, the theme presets, the customization decision tree, and the CLI — so a Laravel-Boost-aware editor autocompletes WireKit. Auto-generated from the installed package, so it cannot drift from your version; re-run to refresh after an upgrade. See the [CLI reference](https://docs.wirekit.app/cli-reference).
- **Five high-frequency icon aliases now resolve out of the box on every base preset** — `settings`, `gear`, `dashboard`, `billing`, and `credit-card`. Previously `settings` / `gear` / `dashboard` / `billing` had no glyph on a base-only setup. See [`<x-wirekit::icon>`](https://docs.wirekit.app/components/icon).
- **[`<x-wirekit::dropdown.item>`](https://docs.wirekit.app/components/dropdown) now honors a caller `type`.** A no-href item still defaults to `type="button"`, but `type="submit"` lets it drive a wrapping `<form>` — the canonical CSRF logout for non-Livewire apps. A "Sign out & form actions" recipe documents both the Livewire and the form-submit paths.
- **A `hideLabel` prop on [`<x-wirekit::input>`](https://docs.wirekit.app/components/input), `<x-wirekit::select>`, and `<x-wirekit::textarea>`.** It keeps the real `<label>` (so the control keeps its accessible name) but renders it visually hidden — for a compact field in a toolbar or header, where the default stacked label reads wrong.
- **Dev-mode warnings for two silent mistakes (debug only, silent in production).** [`<x-wirekit::card>`](https://docs.wirekit.app/components/card) warns when content is placed directly in it with no `card.body` (it would otherwise render flush against the border), and a misspelled prop on a prop-rich component — the form controls plus button, badge, alert, stat, callout, progress, and tooltip — now logs a "did you mean" hint instead of silently doing nothing.

### Changed

- **The shipped `.cursor/rules/wirekit.mdc` AI-authoring rules were corrected to match the shipped API.** Several prop names (the layout primitives take `gap`, not `space`; the button takes `intent`), token names, and a composition example had drifted from the components they describe. The file now matches the real surface and gains the `card.body` and app-shell composition patterns, with a guard that keeps it from drifting again.

### Fixed

- **A caller `aria-label` now reaches the actual control on more form components.** On [`<x-wirekit::otp-input>`](https://docs.wirekit.app/components/otp-input), [`<x-wirekit::rating>`](https://docs.wirekit.app/components/rating), [`<x-wirekit::tags-input>`](https://docs.wirekit.app/components/tags-input), and [`<x-wirekit::file-upload>`](https://docs.wirekit.app/components/file-upload), a caller `aria-label` previously landed on the outer wrapper, where assistive technology ignored it. It now names the role-bearing element — the `role="group"` / `role="radiogroup"`, or the text / file `<input>` — so screen readers announce it. A class-level guard now locks this across every single-interactive-element component.

### Documentation

- **Setup and reference guidance for the most common first-build mistakes.** A consolidated "Styles not applying?" troubleshooting checklist and a "using WireKit tokens in your own Tailwind classes" note on the [integration](https://docs.wirekit.app/getting-started/integration) and [customization](https://docs.wirekit.app/customization) pages; a Spacing-and-Layout token section on the [Design Tokens](https://docs.wirekit.app/theming/design-tokens) page; a typography signpost steering body copy to `<x-wirekit::text>`; a note that `navbar` and the app-shell are alternative layout shells; and corrected prop / token references across several component pages. The [AI-tooling guide](https://docs.wirekit.app/ai-tooling) now features the local MCP server with copy-paste editor config, and component reference pages lead with their live examples — usage notes and prop conventions moved below.

## [2.8.1] — 2026-06-25

**Patch release.** A Livewire 4 layout-setup documentation correction plus a matching installer-hint fix — fully backward-compatible.

### Fixed

- **`wirekit:install` now names the correct layout path in its no-layout hint.** When no app layout exists and Livewire's `livewire:layout` command is unavailable, the installer's fallback instruction pointed at `resources/views/components/layouts/app.blade.php`. On a fresh Livewire 4 app the layout actually belongs at `resources/views/layouts/app.blade.php` (Livewire 4's default `layouts::app` namespace), so the hint now names that path.

### Documentation

- **The [Getting Started](https://docs.wirekit.app/getting-started) layout walkthrough now matches what the installer actually creates.** It recommended `resources/views/components/layouts/app.blade.php` and pinned the example page-component to `#[Layout('components.layouts.app')]` — but on a fresh Livewire 4 app (no Starter Kit), `wirekit:install`, via Livewire's own `livewire:layout`, creates `resources/views/layouts/app.blade.php` (Livewire 4's default `layouts::app`). Following the old steps left `#[Layout]` pointing at a file that was never created. The page now frames the two real cases (fresh app vs. Starter Kit), drops the `#[Layout]` attribute from the example (Livewire's default already wraps the component in the created layout), and clarifies that `make:livewire` scaffolds empty stubs you fill in and that the route is yours to add.

---

## [2.8.0] — 2026-06-22

**Minor release.** One additive component prop plus accessibility, tooling, and documentation fixes — all backward-compatible.

### Added

- **[`<x-wirekit::brand>`](https://docs.wirekit.app/components/brand) gains a `darkLogo` prop for a mode-aware logo swap.** Set `darkLogo` alongside `logo` and the component renders a light/dark `<img>` pair that swaps automatically under the `.dark` class — no more rendering two `<x-wirekit::brand>` elements toggled by hand. It composes with `mobileLogo` (the compact mobile mark stays mode-neutral below the breakpoint; the light/dark swap applies to the desktop logo above it). Omitting `darkLogo` keeps the previous single-logo behavior byte-for-byte.

### Fixed

- **[`<x-wirekit::range-slider>`](https://docs.wirekit.app/components/range-slider) now forwards a caller `aria-label` / `aria-describedby` to the sliders instead of a non-focusable wrapper.** The dual-thumb slider spread its attribute bag onto the outer `<div>`, so a caller-supplied accessible name never reached the two `role="slider"` thumbs (WCAG 4.1.2 / 1.3.1). The wrapper is now a labeled `role="group"`: the visible `label` names the group, each thumb's name embeds that context ("…minimum" / "…maximum"), and the `hint` plus any caller `aria-describedby` are announced on the focusable thumbs.
- **`wirekit:verify` (alias `wirekit:doctor`) no longer mis-reads CSS comments in your `app.css`.** A comment that merely mentioned an `@import` of `wirekit.css` could trip a false "imported" pass, and a `--font-wk-*` example pasted in a comment above the real declaration could mask the token-alignment check. The command now strips CSS comments before scanning, so only live CSS is read.
- **[`<x-wirekit::reaction>`](https://docs.wirekit.app/components/reaction) and [`<x-wirekit::scroll-to-top>`](https://docs.wirekit.app/components/scroll-to-top) now let a caller's `aria-label` override the component's default.** Both hardcoded their default `aria-label` *before* spreading the attribute bag, so a developer-supplied `aria-label` rendered as a duplicate the browser ignores (first wins). They now merge the default into the bag, so your `aria-label` takes precedence — and the sensible default still applies when you pass none.

### Documentation

- **New guidance for dark-mode `dark:` utilities in your own markup** on the [integration page](https://docs.wirekit.app/getting-started/integration): if you write Tailwind `dark:` utilities yourself while loading WireKit via the `@wirekitStyles` `<link>`, add `@custom-variant dark (&:where(.dark, .dark *))` to your `app.css` so they follow the `.dark` class rather than the OS `prefers-color-scheme`.
- **[`wirekit:doctor:a11y`](https://docs.wirekit.app/cli-reference) now documents its scope** — the contrast audit covers your Blade usage and `--color-wk-*` token overrides, not colors inside your own CSS custom classes.
- **The [theming guide](https://docs.wirekit.app/theming) now warns that token overrides wrapped in `@layer` silently lose** to WireKit's unlayered defaults — keep `--color-wk-*` overrides in a plain `:root {}` block.

---

## [2.7.2] — 2026-06-19

**Patch release.** A documentation addition, fully backward-compatible.

### Documentation

- **WireKit's release history is now browsable online at [docs.wirekit.app/changelog](https://docs.wirekit.app/changelog), with a dedicated page per version.** The README "Documentation" table and this file's header now link it, so you can read the release notes as navigable per-version pages instead of scrolling one long file. The page list stays in step with each published release.

---

## [2.7.1] — 2026-06-18

**Patch release.** A single layout fix, fully backward-compatible.

### Fixed

- **[`<x-wirekit::app-shell>`](https://docs.wirekit.app/components/app-shell) now reflows its main content when a [`collapsible`](https://docs.wirekit.app/components/sidebar) sidebar collapses to its icon rail.** The shell's sidebar column was a fixed 16rem, so when the nested `<x-wirekit::sidebar collapsible>` shrank to its 3.5rem rail the main content kept its left offset and a ~12.5rem gap appeared. On desktop the column now tracks the sidebar's width — following the `--wk-sidebar-w` variable when expanded and shrinking to the rail width when the sidebar is collapsed — so the content reclaims the freed space. Mobile (the off-canvas overlay) is unchanged.

---

## [2.7.0] — 2026-06-18

**Minor release.** An additive enrichment wave across forms, navigation, and the editor toolchain. Every new feature is opt-in and backward-compatible. One rendered-output change is called out under **Changed** — the standalone calendar now defaults to a Monday-first week to match the rest of WireKit's date components. Adds nested submenu flyouts to the three menu components, collapse-to-icon sidebars, multi-month calendars, grouped comboboxes, clearable/copyable inputs, a clearable color picker with a custom-trigger slot, a standalone editor adapter bundle, and an editor-preset scaffolder.

### Added

- **[`<x-wirekit::dropdown>`](https://docs.wirekit.app/components/dropdown), [`<x-wirekit::context-menu>`](https://docs.wirekit.app/components/context-menu), and [`<x-wirekit::menubar>`](https://docs.wirekit.app/components/menubar) gain nested submenu flyouts.** A new opt-in sub-component on each parent — `<x-wirekit::dropdown.submenu>`, `<x-wirekit::context-menu.submenu>`, `<x-wirekit::menubar.submenu>` — opens a child flyout beside its parent item on hover, click, or `ArrowRight`. Each accepts `label` (or a `<x-slot:label>`), `icon`, `placement` (default `right-start`), `offset`, and `disabled`, supports arbitrary nesting depth, and implements the full WAI-ARIA submenu keyboard model (ArrowRight/Enter/Space to open and focus the first child; ArrowLeft/Escape to close and refocus the parent) with hover-open collision flipping. Flat menus are unaffected and render identically.

- **[`<x-wirekit::sidebar>`](https://docs.wirekit.app/components/sidebar) gains a collapse-to-icon rail.** Set `collapsible` to add an auto-rendered toggle that narrows the nav to an icon rail; `collapsed` sets the initial state and `persist="key"` remembers it in `localStorage`. Item labels, group headings, and chevrons collapse to icon-only via pure CSS (no per-item wiring), while links keep their accessible names. The expanded width is overridable via the `--wk-sidebar-w` variable (default 16rem). Default off — existing sidebars render unchanged.

- **[`<x-wirekit::calendar>`](https://docs.wirekit.app/components/calendar) gains multi-month display and quick navigation.** `months` (1–4, default 1) renders consecutive months side by side, with arrow-key focus crossing between grids; `selectableHeader` swaps the static month/year label for native `<select>` jump controls (whose open-arrow now matches [`<x-wirekit::select>`](https://docs.wirekit.app/components/select)); and a new `weekStartsOn` prop (`0` Sunday / `1` Monday) sets the first day of the week. `months` and `selectableHeader` default off.

- **[`<x-wirekit::combobox>`](https://docs.wirekit.app/components/combobox) gains option grouping.** Pass a nested map (`['Europe' => ['de' => 'Germany', …]]`, mirroring [`<x-wirekit::select>`](https://docs.wirekit.app/components/select)) to render `<optgroup>`-style headings. The keyboard model stays flat — arrow keys flow across group boundaries as one list and empty groups auto-hide. Ungrouped comboboxes are unaffected.

- **[`<x-wirekit::input>`](https://docs.wirekit.app/components/input) gains `clearable` and `copyable` trailing affordances.** `clearable` shows an X button (visible only while the field has content) that empties and refocuses the field; `copyable` shows a copy-to-clipboard button with a brief "Copied" state announced to screen readers. Both default off and coexist with the prefix/suffix slots; `wire:model` / `x-model` stay in sync.

- **[`<x-wirekit::color-picker>`](https://docs.wirekit.app/components/color-picker) gains a `withClear` "no color" button and a custom `trigger` slot (both popover mode).** `withClear` adds a button that clears the bound value to empty and resets the picker to "no color" — the swatch, plane marker, hue, and value field all reflect the cleared state (the native `<input type="color">` can't represent an empty value, so this is popover-only by design). A `<x-slot:trigger>` replaces the default swatch with your own content while WireKit keeps the open/close, anchoring, and dialog semantics wired. Both default off — existing pickers render unchanged.

- **New `wirekit-tiptap.js` editor adapter bundle (~2 KB gzip).** A standalone bundle that registers only the editor's Alpine factory, so developers loading the lean `wirekit.core.js` can add the rich-text [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) without pulling in the full overlay bundle. It mirrors the chart adapter's shape — MIT glue only, Tiptap stays your peer dependency. Purely additive: the editor still ships in `wirekit.js` and `wirekit-alpine.js`. See [Dependencies](https://docs.wirekit.app/dependencies).

- **New `php artisan wirekit:editor-preset` command.** Scaffolds the `window.tiptapEditor(config)` factory the editor calls at init, pre-wired per toolbar preset (`basic` or `full`) with every callback forwarded and the security-correct link-protocol configuration in place. `--write=<path>` writes to a file; `--force` overwrites an existing target. See the [CLI reference](https://docs.wirekit.app/cli-reference).

### Changed

- **[`<x-wirekit::calendar>`](https://docs.wirekit.app/components/calendar) now starts the week on Monday by default (was Sunday).** This aligns the standalone calendar with [`<x-wirekit::event-calendar>`](https://docs.wirekit.app/components/event-calendar) and the rest of WireKit's date components. The weekday header and day grid shift accordingly; pass `week-starts-on="0"` (or set `components.calendar.week-starts-on` in `config/wirekit.php`) for a Sunday-first week. This is the only rendered-output change in this release.

### Fixed

- **[`<x-wirekit::reading-spine>`](https://docs.wirekit.app/components/reading) with `boundary="container"` now stays pinned instead of scrolling away with the article.** When the reading surface is a bounded scroll container (the [reading-sidebar recipe](https://docs.wirekit.app/blueprints/recipes/reading-sidebar), a modal or panel reading view), the sidebar previously scrolled out of view with the content; it now pins to the container's viewport, offset by the new `--reading-spine-offset-top` variable (default `1rem`). Also applies via [`<x-wirekit::reading-shell>`](https://docs.wirekit.app/components/reading) with `boundary="container"`.

- **`php artisan wirekit:install` now creates an app layout when your project doesn't have one yet.** A fresh Laravel + Livewire 4 project (without a starter kit) ships no `resources/views/components/layouts/app.blade.php`, so Livewire page components failed at runtime with "layout view not found" and the installer previously only told you to add one by hand. The installer now delegates to Livewire's own `php artisan livewire:layout` to create the layout, then injects `@wirekitStyles` + `@wirekitScripts` — ordering `@wirekitScripts` before `@livewireScripts` so WireKit's Alpine plugins register before Livewire boots Alpine. `wirekit:doctor`'s hint and the [Getting Started](https://docs.wirekit.app/getting-started) walkthrough were updated to match (including that Livewire 4 scaffolds single-file components by default — pass `--class` for the class-based form shown in the guide).

- **Form controls no longer trigger mobile Safari's zoom-on-focus.** [`<x-wirekit::select>`](https://docs.wirekit.app/components/select), [`<x-wirekit::input>`](https://docs.wirekit.app/components/input), [`<x-wirekit::textarea>`](https://docs.wirekit.app/components/textarea), and every composite control that renders its own field — [combobox](https://docs.wirekit.app/components/combobox), [multi-select](https://docs.wirekit.app/components/multi-select), [command-palette](https://docs.wirekit.app/components/command-palette), [number](https://docs.wirekit.app/components/number-input)/[password](https://docs.wirekit.app/components/password-input)/[OTP](https://docs.wirekit.app/components/otp-input)/[tags](https://docs.wirekit.app/components/tags-input) input, [date](https://docs.wirekit.app/components/date-picker)/[time](https://docs.wirekit.app/components/time-picker) picker, the [color-picker](https://docs.wirekit.app/components/color-picker) hex field, and the [filter-builder](https://docs.wirekit.app/components/filter-builder)/[data-table](https://docs.wirekit.app/components/data-table) search field — rendered below 16px. iOS Safari zooms and shifts the viewport whenever a focused field is smaller than 16px, so tapping a control made the page jump and the field read as too small. On touch devices these controls are now pinned to the 16px threshold; desktop keeps the compact design-token sizing.

- **[`<x-wirekit::calendar>`](https://docs.wirekit.app/components/calendar) fills the available width on phones instead of a fixed narrow grid.** The standalone month grid renders at a compact ~312px content width; on a narrow phone — especially inside a padded container — that looked too narrow and could clip the trailing weekday column. Below the `sm` breakpoint the calendar now expands to fill its container; from `sm` up it keeps the compact content-width grid unchanged.

### Documentation

- **[`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) gains an "Advanced: mentions, slash commands & collaboration" section.** Documents how to use the editor's `extensions` array to add `@mention` autocomplete, slash-command menus (including AI-assist items that call your own endpoint — WireKit never calls an AI service itself), and real-time collaboration via Yjs. The collaboration guidance is explicit that it requires a sync backend you run.

- **New reading-sidebar recipe.** A vertical sticky reading sidebar that fuses an always-expanded table of contents, active-section highlighting, and an article-scoped progress bar, composed from [`<x-wirekit::reading-shell>`](https://docs.wirekit.app/components/reading). See [Reading sidebar](https://docs.wirekit.app/blueprints/recipes/reading-sidebar).

- **[`<x-wirekit::sticky-panel>`](https://docs.wirekit.app/components/sticky-panel) auto-collapse pattern gains a runnable preview.** The documented compose-with-[`<x-wirekit::collapsible>`](https://docs.wirekit.app/components/collapsible) auto-collapse pattern now ships a live preview, so it's copy-pasteable rather than prose-only.

---

## [2.6.6] — 2026-06-16

**Patch release.** Implements the documented `value` pre-selection prop on multi-select, fixes icon-name rendering on the sidebar components and the replay button's default icon, registers the `list` component in the machine-readable manifests, and documents the Livewire binding contract on the stateful form controls.

### Added

- **[`<x-wirekit::multi-select>`](https://docs.wirekit.app/components/multi-select) now implements the `value` prop for pre-selecting options on load.** Pass an array of option keys (`:value="['php', 'js']"`) or a comma-separated string and the matching pills render immediately. The prop was already documented but not previously implemented, so the pre-selection example rendered empty.

### Fixed

- **[`<x-wirekit::list>`](https://docs.wirekit.app/components/list) now appears in `wirekit:list`, `wirekit:export-json`, and the generated `.wirekit-schema.json` manifest.** The component shipped and was fully documented, but was absent from the internal component registry that drives those manifests — so AI tooling and IDE extensions reading the catalog never saw it. The component itself always rendered correctly; only the machine-readable manifest omitted it.

- **[`<x-wirekit::sidebar.item>`](https://docs.wirekit.app/components/sidebar) and [`<x-wirekit::sidebar.collapsible>`](https://docs.wirekit.app/components/sidebar) now resolve a string `icon` name to an SVG.** Passing `icon="cube"` previously rendered the literal text "cube" instead of the icon; it now resolves through the icon system, consistent with the other components that accept an icon name. A custom `<x-slot:icon>` still works as before.

- **[`<x-wirekit::replay-button>`](https://docs.wirekit.app/components/replay-button) renders its default circular-arrow icon when no custom content is given.** The default icon was previously suppressed, leaving an empty button.

### Documentation

- **Every stateful form control now documents the Livewire binding pattern.** [`number-input`](https://docs.wirekit.app/components/number-input), [`slider`](https://docs.wirekit.app/components/slider), [`combobox`](https://docs.wirekit.app/components/combobox), [`color-picker`](https://docs.wirekit.app/components/color-picker), [`rating`](https://docs.wirekit.app/components/rating), and [`filter-builder`](https://docs.wirekit.app/components/filter-builder) gained a "Livewire Integration" section explaining how to seed the initial display with `:value` alongside `wire:model`.

---

## [2.6.5] — 2026-06-16

**Patch release.** Adds one opt-in, fully backward-compatible event to Tabs so a server can observe tab switches; no breaking changes.

### Added

- **[`<x-wirekit::tabs>`](https://docs.wirekit.app/components/tabs) now dispatches a `wirekit:tab-changed` browser event on every tab switch**, so a Livewire component can react to a change without rebuilding the tablist by hand. The event bubbles to `window`, and its `detail` carries `{ tab, label }` (the activated item's key and its label). It fires on change only — not on the initial render, and not when the already-active tab is re-clicked. Listen for it on a wrapper and forward it into your component, e.g. `<div x-on:wirekit:tab-changed="$wire.onTabChanged($event.detail.tab)">`. Rendering stays client-side and existing tabs are unaffected (an unobserved event is a no-op). See [Observing tab changes server-side](https://docs.wirekit.app/components/tabs).

### Fixed

- **`php artisan wirekit:doctor:a11y --theme-contrast` no longer fails on resting decorative borders.** Per WCAG 1.4.11 (non-text contrast applies only to UI elements that convey state or identify a boundary), the audit now treats the resting decorative borders (`border`, `border-strong`) as advisory — printed as `INFO (decorative, WCAG 1.4.11 exempt)` — and hard-checks only the borders that communicate at 3:1 (the focus ring, `border-error`, `border-success`). Previously it failed the decorative border/background pairing against a flat 3:1 bar, so even a stock install reported a failure on its own intentionally low-contrast dividers. See the [theming guide](https://docs.wirekit.app/theming) for the exact exempt token pairings.

### Documentation

- **[`<x-wirekit::tabs>`](https://docs.wirekit.app/components/tabs)** — the page now leads with a live example, and a new "Observing tab changes server-side" section documents the `wirekit:tab-changed` event end-to-end.
- **[`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor)** — added a "Factory `config` contract" table listing every key WireKit passes to your `window.tiptapEditor(config)` factory, so you can write the factory from the reference instead of reading the source.
- **[`<x-wirekit::icon>`](https://docs.wirekit.app/components/icon)** — documented the single-alias override (map one alias directly instead of stacking a whole extension preset) and why `live` stays a marketing alias.
- **[`<x-wirekit::slider>`](https://docs.wirekit.app/components/slider)** — added a pitfall: a dragged slider bound with `wire:model.live` sends a Livewire round-trip per step; use `.debounce` / `.lazy` instead.
- **[Authoring custom components](https://docs.wirekit.app/extending/authoring-custom-components)** — added a recipe for asserting WireKit's debug-mode "unknown prop" warning in a test.

---

## [2.6.4] — 2026-06-15

**Patch release.** CLI introspection + editor-default fixes, fully backward-compatible.

### Changed

- **The [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) `extensions` config now defaults to an empty array** (was `['StarterKit']`). Your `window.tiptapEditor` factory owns the real Tiptap extension set; the `extensions` value is only an optional list of name hints for a factory that reads them. The old non-empty string default was a foot-gun — a factory that spread it straight into Tiptap's `extensions` would throw (strings aren't Extension objects). The documented factory ignores the hints and is unaffected; set your own hints only if your factory consumes them.

### Fixed

- **`php artisan wirekit:show <component>` now lists the component's slots** (both the human output and `--as=json`), matching `wirekit:export-json` and the `.wirekit-schema.json` manifest. Previously `show` printed props and sub-components but omitted slots entirely, so introspecting a component (e.g. `wirekit:show dropdown`) never surfaced its named-slot quick-form contract such as [`<x-wirekit::dropdown>`](https://docs.wirekit.app/components/dropdown)'s `<x-slot:trigger>`.
- **Four common [`<x-wirekit::icon>`](https://docs.wirekit.app/components/icon) aliases — `copy`, `globe`, `book`, `lightbulb` — now resolve on every base icon preset** (heroicons, lucide, phosphor, tabler) instead of throwing. Previously they lived only in the optional heroicons-app / marketing preset layers, so a base-only setup raised an exception unless those presets were stacked. They're now part of the shared base alias set, with each library's correct icon (e.g. tabler's `world` / `bulb`).

---

## [2.6.3] — 2026-06-15

**Patch release.** CLI and component-usage fixes, fully backward-compatible.

### Changed

- **Invalid input now exits with code `1` consistently across every `php artisan wirekit:*` command.** A few commands (`wirekit:install`, `wirekit:verify` / `wirekit:doctor`) previously exited `2` on a bad flag value or an invalid invocation, while the rest exited `1`. They now all exit `1` — the Laravel/Artisan failure convention. Both are non-zero, so `if ! command` checks are unaffected; only a script branching on the specific code `2` needs to switch to `1`. The `wirekit:install --ignore-failed-flags` help text — which wrongly implied the exit code equaled the number of failed flags — now correctly states it is `1`.

### Fixed

- **[`<x-wirekit::chart>`](https://docs.wirekit.app/components/chart) now fails with a clear, actionable message instead of a cryptic "Undefined variable" error.** The chart is a class-based component: written as the anonymous `<x-wirekit::chart>` (double colon) instead of `<x-wirekit-chart>` (single hyphen) it has no class to supply its data and previously threw `Undefined variable $height`. It now explains which tag to use and links to the docs.
- **`php artisan wirekit:class-by-area` now rejects an unknown `--format` instead of silently falling back to the summary.** A typo'd `--format=jsom` previously rendered the summary and exited `0`; it now exits non-zero with an `Available: summary, full, json` list, matching `--area` and every other command's option validation.
- **`php artisan wirekit:install --diff` now reports the actual layout file it would edit.** The dry-run hard-coded a single layout path; it now resolves and names the same candidate the real install would touch — including the `resources/views/layouts/app.blade.php` convention — or lists every candidate it probes when none exist yet.

### Documentation

- **The [prop-naming conventions guide](https://docs.wirekit.app/extending/prop-naming-conventions) now documents back-compat prop aliases.** It clarifies that [`<x-wirekit::progress>`](https://docs.wirekit.app/components/progress)'s `variant` prop is a deprecated alias of `intent` (both set the bar color), not the surface-treatment `variant` that `alert` / `card` use.

---

## [2.6.2] — 2026-06-14

**Patch release.** A diagnostic-tool fix and release-notes accuracy, fully backward-compatible — no changes to components or styles.

### Fixed

- **`php artisan wirekit:doctor:a11y --theme-contrast` no longer skips a token whose value falls back to a color function.** A token written as `var(--your-token, oklch(…))` — a `var()` with a parenthesized fallback such as `oklch(…)`, `rgb(…)`, or `hsl(…)` — was reported as "unsupported color format" and skipped instead of being contrast-checked. The audit now resolves those fallbacks, so the pairing is evaluated like any other.
- **The changelog now shows a release date for every published version.** A tagged release could previously ship with its section for that version still labeled "Unreleased", so the version you installed and the notes you read could disagree. Every published version now records its release date.

---

## [2.6.1] — 2026-06-14

**Patch release.** Bug fixes across the schema-export tooling, the editor, the diagnostic commands (`doctor` / `doctor:a11y`), and the theme-preset system, all backward-compatible.

### Fixed

- **`php artisan wirekit:export-json` (and the `.wirekit-schema.json` it writes to your project root) now reports props correctly for components whose `@props` block carries a comment with an odd number of quote characters.** A comment such as `// each item's group` or `// without a leading "` contains an unpaired `'` or `"`; the prop extractor mistook it for the start of a string literal, lost track of where the `@props([…])` array ended, and gave up — reporting the component's props as `(none)` and listing the prop *names* under `slots` instead. IDE autocomplete and AI tooling fed from the manifest saw the wrong shape for [`<x-wirekit::skip-link>`](https://docs.wirekit.app/components/skip-link), [`<x-wirekit::sticky-panel>`](https://docs.wirekit.app/components/sticky-panel), and [`<x-wirekit::notification-center>`](https://docs.wirekit.app/components/notification-center). The extractor now skips comment spans before tracking string and bracket state, so every component's props and slots export accurately. Re-run `php artisan wirekit:install` (or `wirekit:export-json`) to refresh a committed `.wirekit-schema.json`.
- **[`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) now logs its "Tiptap not loaded" console hint once per page instead of once per editor.** When Tiptap (the editor's peer dependency) isn't loaded, the editor degrades to a plain textarea and logs a one-time hint pointing you at the install steps — but the hint fired on every editor mount, so a page with several editors filled the console with duplicates. It's now deduplicated behind a page-global flag, matching the chart and map components' missing-dependency hints. The textarea fallback itself is unchanged (each editor still degrades independently).
- **`php artisan wirekit:doctor` now reminds you about the editor and map front-end peer dependencies.** Its optional-dependency report covered Chart.js and the QR-code package but stayed silent on Tiptap (for [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor)) and a map engine — MapLibre GL or Leaflet — (for [`<x-wirekit::map>`](https://docs.wirekit.app/components/map)). Those are browser globals a PHP command can't probe, so they appear as informational reminders ("only if you use those components"), not pass/fail checks. The integration guide also gained an at-a-glance peer-dependency table.
- **`php artisan wirekit:theme <preset>` now applies the light palette correctly on the `@wirekitStyles` (`<link>`) path.** The generated light block was wrapped in a Tailwind `@theme {}` block, which compiles into the `theme` cascade layer; because the prebuilt `dist/wirekit.css` ships its default tokens unlayered, the layered light palette lost the cascade and silently did nothing in light mode (the accent stayed near-black, inline `code` stayed gray, the chrome stayed on the default font) while dark mode worked. The light palette is now emitted as a plain `:root {}` block — mirroring the `.dark {}` block it already emitted — so it wins the cascade and the preset applies in both modes. If you previously worked around this by hand-converting the generated block to `:root {}`, that edit is now redundant (but harmless).
- **The `aurora` preset's generated CSS no longer breaks the build.** A nested block comment in its `--theme-hue` override example closed early (CSS block comments don't nest), so the trailing prose leaked into the stylesheet as invalid tokens and the build failed. The annotation is now plain text.
- **`php artisan wirekit:doctor:a11y --theme-contrast` now audits hue-driven theme tokens instead of skipping them.** A preset that expresses its palette through a single source hue — `oklch(L C var(--theme-hue))` — previously had its entire accent family reported as "unsupported color format" and skipped, because the audit didn't substitute the `var()` reference before parsing the color. It now resolves embedded custom-property references (recursively, with a cycle guard) from the same token table, so those pairings are actually contrast-checked.

### Documentation

- **The [integration guide](https://docs.wirekit.app/getting-started/integration) now collects every optional peer dependency in one at-a-glance table.** Previously Tiptap (for [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor)) and a map engine (for [`<x-wirekit::map>`](https://docs.wirekit.app/components/map)) were documented only on their component pages, while [`<x-wirekit::chart>`](https://docs.wirekit.app/components/chart) and [`<x-wirekit::qr-code>`](https://docs.wirekit.app/components/qr-code) had their own scattered notes. The guide now lists all four side by side — each cross-linked to its component page — with concise Editor and Map setup sections.
- **The [map documentation](https://docs.wirekit.app/components/map) now shows how to keep the map engine out of your global bundle.** A new bundle-size section on the [MapLibre GL guide](https://docs.wirekit.app/components/map/maplibre) demonstrates loading the engine only on the routes that actually render a map — the engine is your own dependency (WireKit never bundles it), so a global import would otherwise add its weight to every page.

---

## [2.6.0] — 2026-06-12

**Minor release.** A wave of new components for data-dense SaaS apps, plus the earlier editor / layout additions and a broad set of enrichments to existing components — all additive and backward-compatible. The SaaS set: [`<x-wirekit::data-table>`](https://docs.wirekit.app/components/data-table) (client-mode sort / search / select / column-manager + a server contract), [`<x-wirekit::filter-builder>`](https://docs.wirekit.app/components/filter-builder) (active-filter chips + a typed add/edit popover), [`<x-wirekit::status-matrix>`](https://docs.wirekit.app/components/status-matrix) (a 2D grid of tristate / toggle / status / heat cells), [`<x-wirekit::notification-center>`](https://docs.wirekit.app/components/notification-center) (a bell + grouped, realtime-capable panel), [`<x-wirekit::usage-meter>`](https://docs.wirekit.app/components/usage-meter) (usage-vs-limit meters + a plan-paywall gate), [`<x-wirekit::event-calendar>`](https://docs.wirekit.app/components/event-calendar) (month / week / agenda scheduling views), and [`<x-wirekit::map>`](https://docs.wirekit.app/components/map) (a MapLibre / Leaflet adapter with an accessible marker list). Also new: [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor), a Tiptap rich-text adapter; [`<x-wirekit::sticky-panel>`](https://docs.wirekit.app/components/sticky-panel), a sticky companion column; and [`<x-wirekit::collapsible>`](https://docs.wirekit.app/components/collapsible), a standalone WAI-ARIA disclosure. [`<x-wirekit::color-picker>`](https://docs.wirekit.app/components/color-picker) gains a from-scratch `popover` HSV picker. Existing components grow: checkbox / radio `size` + `variant="card"`; a `success` (valid) state and `field.set` / `field.legend` grouping for form controls; per-tab icons / badges and vertical tabs; select `<optgroup>` groups; dropdown checkbox / radio items with shortcut hints; slider step marks + value tooltip; a vertical carousel; a frozen table column; textarea `rows="auto"`; an image-compare `ratio` prop; and an empty-state `variant`.

### Security

- **The sandbox preview renderer no longer compiles developer-supplied prop or slot values as Blade.** `Pushery\WireKit\Sandbox\SandboxRenderer` assembled its preview markup by interpolating prop and slot values into a Blade string that was then compiled — so a value containing Blade echo or directive syntax (`{{ … }}`, `@…`) was evaluated server-side rather than rendered as text. Values are now bound as runtime data and referenced through Blade expressions, so they never reach the Blade compiler as source. HTML-escaping of string values is unchanged. If you embed the sandbox renderer behind any untrusted input, update to this release.
- **The sandbox audit log neutralizes control characters in the component-name field.** A component name carrying a tab or newline could forge an extra tab-delimited log record; control characters are now collapsed before the line is written.
- **The ApexCharts adapter's config merge skips prototype-polluting keys.** As defense-in-depth, the deep-merge that layers your chart config over WireKit's themed defaults now drops `__proto__` / `constructor` / `prototype` keys, so a config assembled from app-influenced JSON can't tamper with object prototypes.

### Added

- **New [`<x-wirekit::data-table>`](https://docs.wirekit.app/components/data-table) — a sortable, searchable, selectable data table.** The ergonomic client-mode wrapper handles the common case entirely in the browser: pass a `rows` array and a `columns` definition and it sorts (string + numeric, with `aria-sort` headers), searches (a filter box), selects rows (per-row checkboxes plus a tri-state select-all and a bulk-action bar), toggles column visibility (a column manager), and switches density. Cell types: `text`, `number`, and `badge` (a status word maps to a tinted intent pill). For large datasets the `server` flag emits `sort-change` / `search-change` / `selection-change` events so you re-query from Livewire (a documented `WithDataTable` trait is the contract). The table sits in a labeled, keyboard-reachable scroll region; slots cover `toolbar`, `bulkActions`, and `rowActions`.
- **New [`<x-wirekit::filter-builder>`](https://docs.wirekit.app/components/filter-builder) — an active-filter chip bar.** Active filters render as removable chips; an "Add filter" popover walks the user from a field to an operator valid for that field's type (no `contains` on a number) to a typed value editor (`text` / `number` / `select` / `date` / `bool`). It emits the normalized `[{field, op, value}]` array as a `filter-change` event and as JSON on a hidden input, so `wire:model` and plain forms both bind; an optional `searchable` box emits its own `search-change`. Chips are keyboard-removable and the popover is a labeled `role="dialog"` with focus management.
- **New [`<x-wirekit::status-matrix>`](https://docs.wirekit.app/components/status-matrix) — a 2D grid of typed status cells.** One engine, four cell types via `cell-type`: `tristate` (inherit → allow → deny, editable — a role-permission matrix), `toggle` (on/off — a preferences grid), `status` (an intent badge — a compliance/controls grid), and `heat` (a color-scaled value — a retention heatmap). Sticky row and column headers; editable cells emit the normalized cell map (`cell-change` + JSON bridge) and diff against a baseline. Every encoding is conveyed in text or shape, never color alone — tristate cells use distinct check / cross / dash glyphs and carry the state word in their `aria-label`, heat cells always print their value. The heat ramp is themeable via `heatFrom` → `heatTo` (a cold→hot color interpolation), and each heat value rides in a contrast-checked chip so it stays readable at any point on the ramp.
- **New [`<x-wirekit::notification-center>`](https://docs.wirekit.app/components/notification-center) — a bell with a grouped notification panel.** An unread badge opens a `role="dialog"` panel of grouped, actionable notifications with an optional single-select type filter (a radiogroup with arrow-key selection), an empty state, a "see all" footer, and optimistic realtime insertion (an optional window-event bridge for Laravel Echo). Activating a row marks it read and emits `notification-action` with `{ id, href }` — items with `href` render as real links, and `actionLabel` adds a call-to-action line. Read changes emit `notification-read` / `notification-read-all` and mirror the unread count to a hidden input. The bell's accessible name carries the unread count in words, so the state never depends on the badge color; an `open` prop supports inline embeds.
- **New [`<x-wirekit::usage-meter>`](https://docs.wirekit.app/components/usage-meter) — usage-vs-limit meters for billing surfaces.** Shows how much of a plan limit has been consumed with a labeled bar (composing [`<x-wirekit::progress>`](https://docs.wirekit.app/components/progress)) and a `used / limit (%)` readout, shifting intent as usage approaches the limit. The over-limit and approaching-limit states are spelled out in words, never color alone. Ships `<x-wirekit::usage-meter.panel>` (a responsive grid of meters) and `<x-wirekit::usage-meter.gate>` (a plan-paywall wrapper that dims and inert-disables a gated action with a reason and an upgrade prompt). `:limit="null"` renders an unlimited tier; thresholds are config-overridable.
- **New [`<x-wirekit::event-calendar>`](https://docs.wirekit.app/components/event-calendar) — a scheduling calendar with month / week / agenda views.** The month view is a day grid with event pills and a "+N more" overflow; the week view is an hour-row time grid with overlap-split event blocks and a current-time line; the agenda view is a chronological list grouped by day. Navigation and the view switcher recompute the visible window, and clicking an event emits `event-click`. A `dayMarkers` prop tags individual days as holiday, working, or blocked — blocked days carry a diagonal-hatch overlay (the `wk-day-blocked` class) and spell out the unavailable state in their accessible name, never relying on color alone. Built on CSS grid with no external dependency, token-themed, and distinct from [`<x-wirekit::calendar>`](https://docs.wirekit.app/components/calendar) (the date-picker widget). Every event is a focusable button with a full date/time label.
- **New [`<x-wirekit::map>`](https://docs.wirekit.app/components/map) — a map adapter (MapLibre / Leaflet peer dependency).** WireKit ships the themed chrome, the declarative API, the Alpine glue, and — mandatory for accessibility — an always-present marker list; the heavy map engine and the tiles are your app's concern. With no library loaded it degrades gracefully to the marker list plus a placeholder. It diffs markers reactively for Echo-pushed live positions and emits `marker-click` on selection. Clicking a marker — on the map pin OR in the list — selects it: the map pans and the matching list entry is highlighted, so the two stay in sync. A marker's `intent` colors both its list dot and its map pin (the pin via MapLibre's marker color or a themed Leaflet pin). Each pin opens a hover bubble whose content follows the marker's data shape — a text-only label, a styled name + detail card (`body`), a photo card (`image`), or a photo-only bubble (`tooltip: 'image'`) — and the selected list row highlights via `highlight="ring|fill"` in a choosable `highlight-color`. Both engines render on-canvas zoom controls, and an `attribution` prop passes tile attribution (e.g. `© OpenStreetMap contributors`) through to the map's attribution control — required by some tile providers' usage policies. The marker list is the screen-reader path — each entry a focusable button labeled with the name and coordinates, so the data never depends on the map being visible.

- **`variant="card"` on [`<x-wirekit::checkbox>`](https://docs.wirekit.app/components/checkbox) and [`<x-wirekit::radio>`](https://docs.wirekit.app/components/radio) — the selectable-card pattern.** Turns the whole control into a bordered, fully-clickable card that highlights (accent border + tinted surface) when checked — ideal for feature toggles and pricing-tier pickers. It reacts to its own input via CSS `:has()`, so no JavaScript is required. `variant="default"` (the inline control + label) is unchanged.
- **`size` prop on [`<x-wirekit::checkbox>`](https://docs.wirekit.app/components/checkbox) and [`<x-wirekit::radio>`](https://docs.wirekit.app/components/radio).** Both controls now scale via `sm` / `md` (default) / `lg`, matching the `size` already on [`<x-wirekit::toggle>`](https://docs.wirekit.app/components/toggle) — so a form mixing checkboxes, radios, and switches stays visually consistent at any size. The box (or circle) and its checkmark / dot scale together. Defaults to `md`, so existing checkboxes and radios are unchanged.
- **`success` (valid) state on [`<x-wirekit::input>`](https://docs.wirekit.app/components/input), [`<x-wirekit::select>`](https://docs.wirekit.app/components/select), and [`<x-wirekit::textarea>`](https://docs.wirekit.app/components/textarea).** The mirror of the existing error state: pass a string to render a green border plus a confirmation message below the field (e.g. `success="Username available"`), or `:success="true"` for just the green border. The field stays valid — it never sets `aria-invalid`; the message is linked via `aria-describedby` instead. `error` always wins when both are set. Backed by a new `--color-wk-border-success` design token (aligned with `--color-wk-success-text`, exactly as `--color-wk-border-error` aligns with `--color-wk-danger-text`), so the border and confirmation text read as a coherent pair in both light and dark mode.
- **`rows="auto"` on [`<x-wirekit::textarea>`](https://docs.wirekit.app/components/textarea) grows the field with its content.** No JavaScript — it uses the CSS `field-sizing: content` feature (supported across the WireKit browser baseline). The numeric `rows` still serves as the minimum height. Numeric `rows` behaves exactly as before.
- **`ratio` prop on [`<x-wirekit::image-compare>`](https://docs.wirekit.app/components/image-compare) sizes the component itself.** Image-compare layers two absolutely-positioned images, so it needs a box with explicit dimensions. Previously you wrapped it in an element with an `aspect-ratio` (or a fixed height); now pass `ratio="16/9"` (or `"4/3"`, `"1/1"`, …) directly and the component applies the aspect-ratio to itself — no wrapper needed. **Additive and backward-compatible:** omit `ratio` and the component keeps filling the height of whatever box you give it, so an existing `<div style="aspect-ratio: …"><x-wirekit::image-compare … /></div>` continues to work unchanged.
- **`variant` on [`<x-wirekit::empty-state>`](https://docs.wirekit.app/components/empty-state).** `default` (no container chrome — unchanged), `outline` (a dashed-bordered placeholder that reads as a drop zone), or `muted` (a filled muted-surface card). Lets an empty state stand on its own as a card without wrapping it in a `<x-wirekit::card>`.
- **`animation` prop on [`<x-wirekit::skeleton>`](https://docs.wirekit.app/components/skeleton) — `shimmer` / `pulse` / `none`.** `shimmer` (default) is the gradient sweep; `pulse` is the lighter opacity fade (half the GPU layers); `none` is a static placeholder with no motion. The legacy `:shimmer="false"` still works (equivalent to `animation="pulse"`). All three respect `prefers-reduced-motion: reduce`.
- **[`<x-wirekit::dropdown>`](https://docs.wirekit.app/components/dropdown) gains checkbox / radio menu items and a `shortcut` hint.** `<x-wirekit::dropdown.checkbox-item>` is a self-toggling `menuitemcheckbox` (Alpine owns the checked state, seeded from `:checked`); `<x-wirekit::dropdown.radio-item>` is a `menuitemradio` whose selection is coordinated by a shared Alpine variable named via `model`. Any item (including these) accepts a `shortcut` prop that pins a keyboard-shortcut hint (e.g. `⌘K`) to the inline-end. All follow the WAI-ARIA Menu pattern.
- **[`<x-wirekit::table>`](https://docs.wirekit.app/components/table) gains a `stickyColumn` prop.** Freezes the leftmost column while the rest of the table scrolls horizontally — ideal for wide tables where the first column is the row's identity. The frozen column keeps a solid background, and pairs with `stickyHeader` (the top-left corner stays on top). (The footer/totals row was already available via `<x-wirekit::table.foot>`.)
- **[`<x-wirekit::carousel>`](https://docs.wirekit.app/components/carousel) gains `orientation="vertical"`.** Scrolls slides up/down instead of left/right — the previous/next buttons move to the top and bottom edges, the indicators stack on the side, and the carousel pins a default viewport height (override with a height class or `style`) so each slide fills it. Horizontal stays the default and is unchanged.
- **[`<x-wirekit::slider>`](https://docs.wirekit.app/components/slider) gains step `marks` and a value `tooltip`.** `marks` draws tick marks along the track — a list of positions (`[0, 25, 50, 75, 100]`) or a position-to-label map (`[0 => 'Min', 100 => 'Max']`). `tooltip` floats a value bubble above the thumb that follows it as the user drags. The slider reserves its own vertical space for both (no hand-padding around the field, no clipped bubble inside `overflow: hidden` ancestors, no labels overlapping the content below), and it carries the same min-width usability floor as `<x-wirekit::range-slider>` so it stays draggable in shrink-to-fit contexts (flex/grid auto items, table cells). Both features are off by default, so existing sliders are unchanged.
- **[`<x-wirekit::color-picker>`](https://docs.wirekit.app/components/color-picker) gains a `popover` mode — a full custom HSV picker.** Add `popover` for a design-system-consistent panel: a saturation/value plane, hue + alpha sliders, a format-aware text field (cycle HEX → RGB → HSL → OKLCH — `oklch` is WireKit's own token color space), a screen eyedropper (where supported), copy-to-clipboard with an inline copied-checkmark confirmation, your `presets` swatches, and a recent-colors history (localStorage). It's built from scratch — no third-party library, ~6 KB of glue. The plane and sliders are keyboard-operable (`role="slider"` + arrow keys). On touch devices you can keep the platform's own color sheet: opt-in `native-on-mobile` opens the OS dialog on touch-primary pointers (`pointer: coarse`) while desktop pointers keep the popover. The default (native `<input type="color">`) is unchanged byte-for-byte, and the form-binding contract is identical, so flipping `popover` on never breaks submission.
- **New [`<x-wirekit::editor>`](https://docs.wirekit.app/components/editor) — a rich-text editor (Tiptap peer-dependency adapter).** Drops into a Livewire form like `<x-wirekit::textarea>`: a Blade tag, a hidden form field, `wire:model` support, theme-token styling, full keyboard access. It adapts Tiptap (built on ProseMirror), which stays a **peer dependency** you install from npm — WireKit ships only the lightweight Alpine glue (and gracefully falls back to a `<textarea>` if Tiptap isn't loaded). Props cover `toolbar` presets (`basic` / `full` / `custom`), `format` (`html` / `json`), `editable`, `placeholder`, `maxLength` (renders a live, soft character counter in the bottom bar with a debounced screen-reader announcement), `size`, `maxHeight` (cap the editable area so a long document scrolls inside the field instead of growing the page), and the usual `label` / `hint` / `error`. The undo / redo toolbar buttons disable themselves when there is nothing to undo or redo. Ships with `<x-wirekit::editor.toolbar>` (a `role="toolbar"` of `aria-pressed` command buttons) and token-driven document typography (`wk-editor-content`). The content region is a `role="textbox"` with `aria-multiline`. Sanitize saved HTML on store (the docs page calls this out).
- **New [`<x-wirekit::sticky-panel>`](https://docs.wirekit.app/components/sticky-panel) — a sticky companion-column layout primitive.** Pins a panel beside an article while the page scrolls (a cart summary, filter sidebar, table of contents, comparison builder) using native CSS `position: sticky` for the pinning itself. It has optional non-scrolling `header` and `footer` slots with a scrollable body between them, and it's bounded to the viewport height (`100dvh`, so the footer never scrolls out of reach). The body is a keyboard-reachable scroll region (WCAG 2.1.1) with `overscroll-behavior: contain`, and shows top/bottom scroll shadows when it overflows (opt out with `:scrollShadow="false"`) — they render as overlays *above* the content, so a hovered row at a scroll edge never covers them, and auto-hide at the scroll extremes. A pure-CSS `wk-scroll-shadow` utility class plus the overlay classes (`wk-scroll-shadow-top` / `wk-scroll-shadow-bottom`) are exposed for your own containers. Below the `hideBelow` breakpoint it un-sticks and flows inline (or set `mobileBehavior="hide"`).
- **New [`<x-wirekit::collapsible>`](https://docs.wirekit.app/components/collapsible) — a standalone single disclosure.** One trigger toggles one collapsible region — the bare WAI-ARIA Disclosure pattern, no card chrome or group coordination. The trigger is a real `<button>` (keyboard-operable), wired with `aria-expanded` + `aria-controls`; the region animates open/closed via Alpine's `x-collapse`. Pass `trigger="…"` for a plain label or a `trigger` slot for rich content, and `:open="true"` to start expanded. Reach for it for a "read more" or an advanced-options panel; use [`<x-wirekit::accordion>`](https://docs.wirekit.app/components/accordion) when you have a coordinated *group* of panels.
- **[`<x-wirekit::select>`](https://docs.wirekit.app/components/select) options now support `<optgroup>` groups and per-option `disabled`.** The `:options` array accepts three shapes you can mix freely: flat (`['de' => 'Germany']`), grouped (`['Europe' => ['de' => 'Germany']]` → an `<optgroup label="Europe">`), and per-option attributes (`['de' => ['label' => 'Germany', 'disabled' => true]]`). Flat options render exactly as before.
- **[`<x-wirekit::field>`](https://docs.wirekit.app/components/field) gains `orientation="horizontal"`, plus new `<x-wirekit::field.set>` / `<x-wirekit::field.legend>` for grouped fields.** `orientation="horizontal"` places the label in a column beside the control (the classic settings-form layout) with the error/hint still aligned under the control. `<x-wirekit::field.set legend="…" hint="…">` renders a native `<fieldset>` + `<legend>` — the WCAG-recommended way to group a radio or checkbox set so the legend is announced before each control; use `<x-wirekit::field.legend>` in the slot for rich legend content. Defaults are unchanged (vertical).
- **[`<x-wirekit::tabs>`](https://docs.wirekit.app/components/tabs) gains per-tab `icon` + `badge` and a `vertical` orientation.** Using the array-of-objects items shape, each tab can carry an `icon` (a leading glyph) and a `badge` (a trailing count/status chip): `['key' => 'inbox', 'label' => 'Inbox', 'icon' => 'inbox', 'badge' => 8]`. `orientation="vertical"` stacks the tabs in a column beside their panels (settings screens, side nav) with the correct WAI-ARIA keyboard model — Up/Down arrows for a vertical tablist, Left/Right for horizontal. The tab bar was already horizontally scrollable when labels overflow. All additive; existing tabs are unchanged.
- **`wirekit:install` now stops with a clear message when the project is still on Tailwind CSS v3.** WireKit's styles are built on the Tailwind v4 engine (`@theme`, `@source`, `color-mix()`, `@property`) and cannot run on v3. The Tailwind version lives in npm — out of reach of Composer's `require` constraints — so the install command is the first place WireKit can check it: a v3 project now aborts before anything is written and points you at `npm install tailwindcss@latest @tailwindcss/vite@latest` plus the Tailwind v4 upgrade guide, instead of failing later with a confusing wall of errors. `wirekit:doctor` reports the same guidance as a failed check. Detection only acts on positive v3 evidence, so a valid v4 (or an undetermined) setup is never blocked.
- **New `--text-wk-2xs` design token — the smallest text size in the scale.** A micro/caption step below `--text-wk-xs`, for dense UI like table sub-labels, chip counts, and timestamps. Available to your own CSS like every other `--text-wk-*` token and documented in the theming reference.
- **[`<x-wirekit::replay-button>`](https://docs.wirekit.app/components/replay-button) is now publicly documented.** The component was always callable in the package; its documentation page, README listing, the Public CSS API catalog row, and the machine-readable component manifests now all surface it. The button re-mounts the closest `[data-replay-target]` ancestor from a saved snapshot — it powers the `↻ Replay` control on docs.wirekit.app's live previews, and it's a ready-made building block for re-running an animation, resetting an interactive demo, or restoring a dismissed badge or alert in your own app.

### Changed

- **`dist/wirekit.js`, `dist/wirekit-alpine.js`, and `dist/wirekit.esm.js` grew to absorb the new editor adapter, the from-scratch color picker, and the SaaS data components (data-table, filter-builder, status-matrix, notification-center, event-calendar, map).** Raw sizes: `wirekit.js` ~148 KB (~43 KB gzip), `wirekit-alpine.js` ~194 KB (~59 KB gzip), `wirekit.esm.js` ~148 KB (~43 KB gzip). The core chart-only bundle (`wirekit.core.js`, ~11 KB) is unchanged.

### Fixed

- **[`<x-wirekit::charts-apex>`](https://docs.wirekit.app/components/charts-apex) tooltip now HTML-escapes the series color before rendering.** The unified tooltip renderer escaped the x-axis label, series name, and data value but interpolated the resolved series color into the marker's inline `style="background: …"` unescaped. An app that wired chart colors from unvalidated user input could break out of the attribute — now the color goes through the same escape helper as its sibling values, so only a valid CSS color reaches the DOM.
- **[`<x-wirekit::range-slider>`](https://docs.wirekit.app/components/range-slider) value bubbles now track, clamp, and merge cleanly.** Each thumb carries a live value bubble above its handle that follows the handle as you drag. At the ends the bubbles close flush with their handles instead of overhanging the track; when the two thumbs sit close enough that the bubbles would collide they collapse into a single combined "min – max" bubble centered between the handles — which now lines up at exactly the same height as the individual bubbles. All of it updates live during a drag.
- **[`<x-wirekit::slider tooltip>`](https://docs.wirekit.app/components/slider) value bubble now sits over the thumb when the slider is given a width.** Passing an explicit width could push the floating value bubble far to the side of the handle; the width is now applied so the bubble, the track, and the tick `marks` all share it, keeping the bubble aligned with the thumb (and the marks aligned with the track) at any size.
- **[`<x-wirekit::range-slider>`](https://docs.wirekit.app/components/range-slider) and [`<x-wirekit::image-compare>`](https://docs.wirekit.app/components/image-compare) no longer force a layout read on every drag frame.** Both read the track's bounding rectangle once at the start of a drag instead of re-measuring it on each `pointermove` — the track doesn't move while dragging, so the per-frame measurement was wasted work that could cause jank on long tracks or low-end devices. Dragging is now smoother; the click-to-position path still measures fresh.
- **`wirekit:doctor` no longer false-warns that no font CSS was found when fonts are correctly published.** The font-assets check scanned only the top level of `public/vendor/wirekit/fonts/`, but `vendor:publish --tag=wirekit-fonts` writes font CSS one level deeper (`fonts/<category>/<name>/<name>.css`) — so a correct publish was reported as "Font directory exists but no CSS files found". The check now scans recursively. On a fresh install using the default font (where the system-ui stack means no font CSS is published), the empty directory is reported as informational rather than a warning.

---

## [2.5.0] — 2026-06-05

**Minor release.** A new **Aurora** theme preset — color-confident, toned to the WireKit brand magenta, with dashboard-tuned radii, soft layered shadows (visible in both light and dark mode), and a single `--theme-hue` variable for one-line retinting. Its scope is bounded to the accent surface only — semantic intents stay on the WireKit default palette. Paired with a foundational cascade fix in `dist/wirekit.css` that lets every developer-side `:root {}` token override win without specificity tricks (matters across every preset, not just Aurora). Two new accessibility surfaces: [`<x-wirekit::skip-link>`](https://docs.wirekit.app/components/skip-link) ships as a drop-in WCAG 2.4.1 (Bypass Blocks) helper, and `<x-wirekit::dropdown.trigger>` auto-adds a fallback `aria-label` (the `ariaLabelFallback` prop, default `"Open menu"`) when its rendered button has no accessible name (icon-only triggers, responsive layouts that hide the visible label below `sm`). The `wirekit:doctor:a11y` linter gains an opt-in `--theme-contrast` stage that computes WCAG ratios for the active theme's token pairings.

### Added

- **New `aurora` theme preset — modern, color-confident, toned to the WireKit brand magenta (hue 306°).** `php artisan wirekit:theme aurora` applies it to your `app.css` end-to-end, or copy-paste the block from the theming guide — both produce identical output. Modestly rounded, soft layered shadows, cool-tinted surfaces, and a body text color softened off pure black with a whisper of the brand hue (`--color-wk-text: oklch(0.24 0.02 var(--theme-hue))`, WCAG AAA at 16.3:1) so it reads as part of the theme rather than stark monochrome on the tinted surfaces. The interactive `--color-wk-accent` reads `oklch(0.55 0.22 var(--theme-hue))` — WCAG-AA-clean at 5.28:1 (light) / 6.29:1 (dark) against accent-fg — while two extra tokens, `--color-wk-accent-brand` and `--color-wk-accent-brand-fg`, preserve the brand-exact L=0.605 magenta for decorative-only surfaces (logo backplate, hero glyphs) where body text never sits on top.
- **`--theme-hue` single-source pattern on Aurora.** Every hue-dependent token reads `oklch(L C var(--theme-hue))` and the preset declares `--theme-hue: 306` once at the top of the block. Developers can retint the entire Aurora palette to any hue in ONE LINE — `:root { --theme-hue: 264; }` produces an indigo Aurora; `:root { --theme-hue: 24; }` a warm-orange one — without touching the 14 hue-touching tokens individually. The L values are chosen to stay WCAG-AA-clean across the full hue rotation, so the retint is AA-safe at any hue.
- **[`<x-wirekit::skip-link>`](https://docs.wirekit.app/components/skip-link) — drop-in WCAG 2.4.1 (Bypass Blocks) component.** Renders a visually-hidden anchor that becomes a focused pill when keyboard-tabbed, jumping to a target landmark. Default target is `#main-content`; override via `target` prop. Pair with `<x-wirekit::main id="main-content">` (the `id` is now a first-class prop on `main`). Auto-styled to inherit the active theme's accent color and radius.
- **`id` prop on [`<x-wirekit::main>`](https://docs.wirekit.app/components/main).** First-class prop so skip-link targeting reads naturally as `<x-wirekit::main id="main-content">`. Defaults to `null` (no id emitted). When set, the main element also becomes programmatically focusable (`tabindex="-1"`) so JS-routing edge cases and direct fragment navigation move keyboard focus into the landmark.
- **`wirekit:doctor:a11y --theme-contrast` — WCAG-contrast audit of the active theme.** New optional stage on the existing `wirekit:doctor:a11y` command. Reads the developer's `resources/css/app.css`, parses every `--color-wk-*` override under `:root` / `.dark`, computes WCAG 2.1 contrast ratios for the canonical token pairings (`accent` as text on `bg`, `accent-fg` on `accent`, `text` on `bg`, etc.), and reports PASS / WARN / FAIL per pairing for both light and dark modes. Catches the bug class where a developer customizes `--color-wk-accent` without verifying the new value still clears 4.5:1 against `--color-wk-accent-fg`. Opt-in stage (the existing static blade-scan continues to run first); enable with `--theme-contrast` or set `WIREKIT_DOCTOR_THEME_CONTRAST=1`.
- **Inline `<code>` is now themeable via two new tokens — `--color-wk-code` (text) and `--color-wk-code-bg` (background).** Both default to the previous values (body text on the muted surface), so every existing theme is unchanged. [`<x-wirekit::code>`](https://docs.wirekit.app/components/code) reads them automatically. The **Aurora** preset sets them to the brand magenta on a light magenta-tint highlight (WCAG AA at 5.74:1 light / 7.56:1 dark, hue-stable across `--theme-hue` retints), so inline code reads as part of the theme rather than as plain body text.
- **[`<x-wirekit::accordion>`](https://docs.wirekit.app/components/accordion) gains `variant` and `size`.** `variant` controls the container chrome: `bordered` (default — the existing self-contained card), `flush` (no outline, just row dividers — for an FAQ that sits inline in page content), and `separated` (each item becomes its own standalone card with a gap between them). `size="lg"` gives the trigger roomier padding and a larger title for marketing and touch-first layouts. Both default to the previous look, so existing accordions are unchanged.
- **[`<x-wirekit::date-picker>`](https://docs.wirekit.app/components/date-picker) gains a `range` flag.** Renders a linked start + end pair of native date inputs — the end can't be set before the start and vice versa (a small reactive link, no calendar dependency). They submit as `name[start]` / `name[end]`; pass an initial range as an array `['start' => .., 'end' => ..]` or a `YYYY-MM-DD/YYYY-MM-DD` string. The single-date default is unchanged.
- **New [`<x-wirekit::button.group>`](https://docs.wirekit.app/components/button) — joined / segmented button bar.** Wrap buttons to join them into a single control: inner corners are squared, the shared border seam collapses to one line, and the active button rises above the seam. Supports `orientation="vertical"` and a `label` for the group's accessible name. The same wrapper also joins an input with a trailing button (attached search field, newsletter signup). RTL-safe (logical properties).
- **[`<x-wirekit::breadcrumb>`](https://docs.wirekit.app/components/breadcrumb) items accept an `icon` key.** Add `'icon' => 'home'` to any breadcrumb item to render a decorative glyph before its label (a leading home icon, per-category icons, etc.). The label stays the accessible link text — the icon is `aria-hidden` and is never written into the JSON-LD structured data. Items without an `icon` are unchanged.
- **New [`<x-wirekit::avatar.group>`](https://docs.wirekit.app/components/avatar) — stacked / overlapping avatars.** Wrap a set of avatars to render them as an overlapping stack, each ringed in the surface color so the discs stay distinct. Set `:remaining` for a trailing "+N" overflow chip, `label` for the group's accessible name, and `size` to match the avatars inside. Plus a documented "avatar with name & detail" composition pattern on the avatar page (Row + Stack + Text — no new component needed).
- **New [`<x-wirekit::spinner>`](https://docs.wirekit.app/components/spinner) — accessible loading indicator.** A lightweight CSS spinner for in-place activity that has no measurable progress. Four sizes (`sm`/`md`/`lg`/`xl`), an optional semantic `intent` color (defaults to `currentColor` so it inherits its context — drop it inside a button or badge and it matches), and a screen-reader `label` (default `"Loading"`) announced via `role="status"`. Use it when you can't show a layout preview (Skeleton) or a percentage (Progress).
- **[`<x-wirekit::badge>`](https://docs.wirekit.app/components/badge) gains `surface`, `dismissible`, and `trailingIcon`.** `surface` chooses how the intent color is applied — `soft` (default, the tinted chip), `solid` (filled intent background with on-color text), or `outline` (transparent with an intent-colored ring). `dismissible` adds a keyboard-operable close button that hides the badge and dispatches a `wirekit:badge-dismissed` event (great for removable filter chips), with the button's accessible name set via `dismissLabel` (default `"Remove"`). `trailingIcon` mirrors `leadingIcon` after the label. All three default off / to the existing look, so existing badges are unchanged.

### Changed

- **`dist/wirekit.css` `:root` / `.dark` blocks now wrap in `:where(...)` for specificity 0.** Foundational cascade fix: any developer-side `:root { --color-wk-accent: ... }` override (specificity 0,1,0) now wins over WireKit's defaults regardless of source order — including the very common `@wirekitStyles` setup where this stylesheet is injected via a separate `<link>` tag AFTER the developer's compiled `app.css`. Pre-fix, developers had to either `@import` `wirekit.css` from inside `app.css` OR use a higher-specificity selector like `html:root` to win the cascade. Both workarounds are now unnecessary. Backward compatible — developers already using `html:root` continue to work (their higher specificity still wins).
- **[`<x-wirekit::dropdown.trigger>`](https://docs.wirekit.app/components/dropdown) auto-injects a fallback `aria-label` when the inner button has no accessible name.** The component inspects its interactive child (button / link / `role="button"`); when that element has no `aria-label`, no `aria-labeledby`, and no non-empty text content (visible OR sr-only) — the icon-only-trigger and mobile-collapsed-label cases — it sets an `aria-label` from the new `ariaLabelFallback` prop (default `"Open menu"`). Any explicit `aria-label` / `aria-labeledby` / text on the trigger still wins. Closes the bug class where a responsive layout hid the label below the `sm` breakpoint and the icon-only trigger read as just "button" to screen readers.
- **`dist/wirekit.esm.js` now registers every component plugin, matching the IIFE bundle.** The ES-module build previously omitted `stat-animate`, `animate`, and the `reading-spine` / `reading-minimap` / `reading-toc` plugins, so developers registering WireKit from the ESM entry point saw `<x-wirekit::stat animate>` and [`<x-wirekit::reveal>`](https://docs.wirekit.app/components/reveal) (plus the reading primitives) render their static value with no animation behavior. All 29 plugins are now registered (bundle: ~31 KB gzip / 109 KB raw, up from ~25 KB / 82 KB).
- **Badge border + depth are now theme-tokens; the Aurora preset renders softer, flat tinted badges.** Two new tokens control the badge outline: `--border-wk-badge-width` (default: the global `--border-wk-width`) and `--shadow-wk-badge` (default: the inset-ring + faint drop that lifts the chip). The **Aurora** preset sets both to a flatter look — no outline, no inset ring — so badges read as soft tinted chips rather than bordered pills. Every other theme is unchanged, and you can restore the bordered look under Aurora (or harden it anywhere) by setting the two tokens back to their defaults in your `:root`.
- **Dismissible [`<x-wirekit::badge>`](https://docs.wirekit.app/components/badge) and [`<x-wirekit::alert>`](https://docs.wirekit.app/components/alert) now emit the `data-replayable` contract.** A badge or alert with `dismissible` set renders `data-replayable="true"`, so a `<x-wirekit::replay-button>` placed in the same DOM tree can restore the element after it has been dismissed (the button re-mounts the closest `[data-replay-target]` from a saved snapshot). The attribute is inert on its own — it does nothing unless a replay button is wired up — and the dismissible behavior itself is unchanged.

### Fixed

- **Theme overrides loaded via the `@wirekitStyles` setup no longer get silently overwritten by WireKit's defaults.** Symptom: a developer adds `:root { --color-wk-accent: ... }` to their `app.css`, runs `npm run build`, and finds the value is ignored in the browser because `wirekit.css` loads AFTER `app.css` and (until this fix) its `:root` block had equal specificity (0,1,0) plus later source order. The `:where()` wrap on every top-level `:root` / `.dark` block in `dist/wirekit.css` brings WireKit's defaults to specificity 0, so any developer override wins. Affects every theme preset, not just Aurora.
- **Built-in theme presets that recolor the accent now pin a co-tuned `--color-wk-accent-fg`, so primary-button labels clear WCAG AA.** `php artisan wirekit:theme retro-terminal` previously left the accent foreground at the default near-white, which paired with the light-green accent at only 2.13:1 — well below the 4.5:1 AA threshold for the button label. `retro-terminal` now pins a near-black label (8.06:1); `soft`, `material`, and `cupertino` pin a near-white label (5.03–7.73:1). The accent / accent-foreground pair is written together so it stays aligned in both light and dark mode.
- **[`<x-wirekit::range-slider>`](https://docs.wirekit.app/components/range-slider) no longer collapses to an undraggable sliver in shrink-to-fit containers.** The track is a full-width block with no intrinsic width of its own, so inside a `width: fit-content` wrapper, a flex/grid auto item, or a table cell it shrank to the width of its two bound labels (~60px). The component now pins a `16rem` min-width floor while still expanding to 100% in normal block flow, so it stays operable everywhere.
- **`<x-wirekit::hero variant="dark">` and `<x-wirekit::cta variant="dark">` now establish a dark token context for their content — no more white-on-white nested surfaces.** Previously the dark variant only painted a dark band with light text; a token-surfaced child (e.g. [`<x-wirekit::code-block>`](https://docs.wirekit.app/components/code-block), `card`, `input`, `table`) still read light-mode `--color-wk-*` values, rendering a near-white panel on the dark band — invisible. The dark variant now carries a dark token context so every nested token-driven surface renders dark. As a consequence a `variant="dark"` band now also stays genuinely dark in **dark mode** (it previously flipped to near-white). Light-mode appearance is unchanged; `variant="accent"` is unaffected (it is a colored surface, not a dark one).
- **[`<x-wirekit::stat animate>`](https://docs.wirekit.app/components/stat) count-up no longer risks sticking at "0".** Both start-triggers — the `animateIn` entrance keyframe and the standalone scroll-into-view observer — can occasionally miss on real browsers (a coalesced keyframe; an `IntersectionObserver` edge case on some touch browsers), which would leave the live counter frozen at "0" over the correct server-rendered value. A safety-net timer now starts the count-up if neither trigger fires, so `animate` always resolves to its target.
- **Muted text on [`<x-wirekit::hero variant="accent">`](https://docs.wirekit.app/components/hero) / `<x-wirekit::cta variant="accent">` now clears WCAG AA on every preset.** The previous muted-text formula on `variant="accent"` surfaces blended `--color-wk-accent-fg` at 72% opacity over the accent, which clears AA on dark-accent themes but dropped to ~3.4:1 on medium-lightness accents (Aurora, Soft, Material, and Cupertino — their regular accent-fg-on-accent contrast is only ~4.8–5.3:1, leaving no AA-safe muting headroom). Muted text on a colored accent surface now renders at the full `--color-wk-accent-fg`, so it inherits the surface's regular text contrast (≥ 4.5:1 on every preset, both light and dark). Real muting is unchanged on `variant="dark"` and every non-colored surface.

---

## [2.4.2] — 2026-06-03

**Patch release.** Bug-fix window on top of v2.4.1.

### Fixed

- **`<x-wirekit::badge tooltip="…">` now uses the WireKit tooltip, not the browser's native one.** The badge `tooltip` prop previously set a native `title=""`, so the explanation rendered as the browser's plain default tooltip — inconsistent with the rest of the library and not touch- or keyboard-friendly. It now composes WireKit's own tooltip component, so the explanation appears on hover, focus, touch, and keyboard, themed to match, and is announced to screen readers via `aria-describedby`. The `tooltip` API is unchanged; a badge without a `tooltip` still renders as a bare `<span>` with no added markup.

---

## [2.4.1] — 2026-06-03

**Patch release.** Bug-fix and polish window on top of the v2.4.0 baseline.

### Fixed

- **Streamlined the copy-paste code in component-docs previews.** The Show-Code panels on `/components/sparkline`, `/components/charts-apex/sparklines`, `/components/accordion`, `/components/breadcrumb`, `/components/button`, `/components/hover-card`, `/components/icon`, `/components/input`, `/components/popover`, `/components/qr-code`, `/components/scroll-to-top`, `/components/spine-aware`, `/components/toast`, and `/components/tooltip` now compose WireKit primitives — `<x-wirekit::stats>` + `<x-wirekit::stat>` (with the `sparkline` slot), `<x-wirekit::card>` + `<x-wirekit::card.body>`, `<x-wirekit::grid>`, `<x-wirekit::row>`, `<x-wirekit::stack>`, `<x-wirekit::prose>`, `<x-wirekit::text>`, and `<x-wirekit::heading>` — instead of hand-rolled `<div>` layout scaffolding. The rendered previews are unchanged; the code you copy is cleaner, shorter, and consistent with how the library is meant to be composed.
- **Accessible names on read-only example inputs in the docs.** Several read-only example inputs — the popover share-link / share-URL fields plus a couple of search / filter fields elsewhere in the docs — now carry an `aria-label`, so screen readers announce them instead of an unnamed text field (WCAG 4.1.2).

---

## [2.4.0] — 2026-06-02

**Minor release.** New display components (`stage-card`, `activity-row`),
an `intent` color axis on `stat` and `progress`, deterministic
`avatar from-initials`, badge `tooltip` / `leadingIcon` support, a
`md-compact` size tier on inputs and buttons, and chart tooltip value
formatting (`valueDecimals` / `valuePrefix` / `valueSuffix`). Plus
reading-primitive `boundary` scoping, a `hero tightOnMobile` option,
menubar / navigation-menu dropdown positioning fixes, sortable-table
fixes, and a round of mobile-layout polish. Additive-only — every
existing template renders identically. No back-compat breaks.

### Added

- **New `<x-wirekit::stage-card>` component.** A pipeline / kanban / roadmap stage card: an intent-colored left stripe, a faintly intent-tinted body, an optional item-count pill (announced as "N items"), and an optional progress bar. Replaces the per-column inline-style block dashboards previously hand-rolled. `role="group"` + `aria-label` from the stage `label`.
- **New `<x-wirekit::activity-row>` component.** An activity-feed / timeline / changelog row: a kind-colored leading dot, optional bold actor, content, right-aligned timestamp, and a `badge` slot. The `kind` → color map (commit / merge / deploy / comment / system / user) is extensible via `config('wirekit.components.activity-row.kinds')`; unknown kinds fall back to a muted dot. The dot is decorative; the kind is exposed as a visually-hidden label.
- **`<x-wirekit::stat intent="…">` KPI-tile chrome.** Setting `intent` (primary / success / warning / danger / info / neutral) gives the stat an intent-colored left stripe + a faintly tinted body and turns it into a labeled `role="group"`. Default (no `intent`) renders the existing plain card surface unchanged.
- **`<x-wirekit::progress intent="…">`.** `intent` is now the canonical color axis (matching badge / button / alert), adding `info` and `neutral`. `variant` keeps working as a back-compat alias; `intent` wins when both are set.
- **`<x-wirekit::avatar from-initials>`.** Derives a deterministic, theme-independent background color from the initials hash (AA-contrast background + white text) so the same person always renders the same color. `WireKit::avatarPaletteFor($key)` exposes the same palette for custom inline chips.
- **`size="md-compact"` on `<x-wirekit::input>`, `<x-wirekit::select>`, and `<x-wirekit::button>`.** A 2.25rem middle tier between `sm` and `md` for dense list/filter toolbars.
- **`<x-wirekit::badge>` gains `tooltip` and `leadingIcon`.** `tooltip` sets a discoverable native `title`; `leadingIcon` renders a decorative status glyph before the label. Every badge also gains a subtle depth shadow (inset ring + faint drop shadow) so it reads less flat.
- **`<x-wirekit::card.body :padded="false">`.** Opt out of body padding for edge-to-edge content (a flush hero image or full-bleed table).
- **`<x-wirekit::dropdown>` accepts an optional `<x-slot:trigger>` named slot.** When the slot is supplied, the parent auto-wraps the trigger element in `<x-wirekit::dropdown.trigger>` and the default slot in `<x-wirekit::dropdown.panel>`, so the canonical composition no longer has to be repeated per dropdown. The explicit-sub-component form keeps working unchanged — use whichever reads better for the page. Both forms produce identical rendered HTML and ARIA wiring.
- **Reading-* `boundary` prop on `reading-progress`, `reading-spine`, and `reading-bookmark`.** Default `null` preserves the existing viewport-pinned behavior. Pass `boundary="container"` to scope the primitive to its nearest positioned ancestor instead — useful when embedding a reading surface inside a modal body, sidebar pane, preview iframe, or Livewire panel. The progress bar swaps to `position: sticky`; the spine and bookmark swap to Tailwind `absolute`. Documented under "Scoping a primitive to its parent" with the required wrapper recipe.
- **`<x-wirekit::hero tightOnMobile>` opt-in prop.** When `true` on a `size="lg"` hero, mobile padding drops from `--space-wk-section-md` (5 rem) to `--space-wk-section-sm` (3 rem) while desktop keeps `--space-wk-section-lg` (7 rem) — useful for dark-variant heroes with gradient overlays where the previous 5 rem mobile padding showed as a visible dead-zone on iPhone-class viewports. No-op for `size="sm"` and `size="md"` (mobile already runs at the tightest tier).
- **New `docs/strict-validation.md` page.** A context-matrix reference for WireKit's prop-validation gate: when does an invalid value throw vs log + fall back, how to grep the Laravel log for silent typos that shipped, the env-knob recipe to opt staging into fail-loudly mode, and a Pest test pattern for asserting strict-mode rejection. Linked into the docs sidebar between Variants & Intents and Customization.
- **`<x-wirekit-chart>` gains `valueDecimals`, `valuePrefix`, and `valueSuffix` (ApexCharts).** Round raw float y-values in the tooltip to N decimal places and wrap them with a unit or currency affix — `valueDecimals="2" valueSuffix=" ms"` turns a hovered `50.523626895740676` into `50.52 ms`. All three default to `null` (raw value, no affix), so existing charts are unchanged. No-op on the Chart.js adapter.
- **`.wk-reading-content` responsive article wrapper class.** Wrap the `<article>` in a reading-spine composition with `class="wk-reading-content"` and it reserves the spine's inline-end gutter **only at `md`+** (where the spine is visible) while using the **full width below `md`** (where `reading-spine` defaults to `hideBelow="md"` and is hidden). Replaces the hand-rolled inline `max-width: calc(100% - 12rem)` / `padding-right: 12rem` reserve, which — being an inline style — could not `@media` and so stayed applied on phones, collapsing the article to a single character per line. Override the gutter with `--reading-content-spine-gutter` (default `12rem`).

### Fixed

- **`<x-wirekit::table alpine-sort>` now actually reorders rows when a header is clicked.** The Alpine sort read the table body from the clicked `<th>` instead of the table root, so it found no `<tbody>` and bailed out — the sort-direction arrow flipped but the rows never moved. Clicking a sortable header now sorts the rows as expected.
- **`<x-wirekit::table alpine-sort>` sorts date and other non-numeric columns correctly.** Numeric detection used `parseFloat`, which read a leading number out of any string — so every ISO date (`2025-01-15`) collapsed to its year (`2025`), making a whole date column compare as equal and never reorder. Values are now only compared numerically when the entire cell is a number; dates, sizes (`12px`), prices (`$5`), and counts (`3 items`) fall through to locale-aware comparison, which orders ISO dates chronologically.
- **`<x-wirekit::data-list>` no longer renders a doubled border under the last row.** The inter-row separator was an inline `border-bottom` on every item, including the last — which doubled against the container's own bottom border into a 2px seam. The separator now omits the final row.
- **Muted text stays legible on `<x-wirekit::hero>` / `<x-wirekit::cta>` `variant="dark"` and `variant="accent"`.** A nested `<x-wirekit::text variant="muted">` previously resolved against the light-theme muted color on the near-black surface — invisible. It now reads a high-contrast muted color scoped to the dark/accent surface, auto-adapting to any theme.
- **Raw `<td>` / `<th>` cells inside `<x-wirekit::table>` now receive readable padding.** Previously a raw table (cells not wrapped in the `table.*` sub-components) rendered cramped flush-to-border; raw cells now get the same padding `table.td` uses, complementing the existing debug-mode warning. An edge-to-edge `card.body` pads a flush raw table's cells too.
- **`<x-wirekit::range-slider>` is more usable on touch devices.** The thumb grows to a comfortable 28px on coarse pointers, `touch-action: none` stops a horizontal drag from scrolling the page, and discrete sliders (`step > 1`) with a readable step count render snap tick-marks.
- **`wirekit:export-json` manifest no longer false-positively flags class-side public properties as required template slots.** Class-based components (currently `<x-wirekit-chart>`) expose public properties like `$alpineComponent` and `$chartConfig` that the Blade template references as `{{ $name }}`. The slot-detection layer treated these as `<x-slot:...>` requirements, so the manifest's chart entry emitted `{name: alpineComponent, required: true}` — leading AI tooling to wrap chart content in `<x-slot:alpineComponent>` and produce broken Blade. The exclude is applied to both `wirekit:export-json` and the `.wirekit-schema.json` file written by `wirekit:install`.
- **`<x-wirekit::tabs>` tab bar is now horizontally scrollable on narrow viewports.** The tablist previously used a non-scrolling `inline-flex`, so a layout with five or more tabs overflowed the viewport on mobile with no way to reach the off-screen tabs. The tablist now caps at the container width and scrolls (the standard mobile tab-bar pattern); tabs keep their natural label width (`shrink-0` + `whitespace-nowrap`) instead of squishing. Desktop rendering is unchanged. Keyboard access is preserved — `role="tablist"` with arrow-key navigation already owns its keyboard model.
- **`<x-wirekit::data-list>` labels and values wrap long single tokens instead of overflowing.** A `<dt>` carried `white-space: nowrap`, so a long single-word label (e.g. a long compound noun, a URL, or a file path) bled past its 33% track and pushed the row wider than its container on narrow viewports. Both `<dt>` and `<dd>` now use `overflow-wrap: anywhere` + `min-width: 0`.
- **`<x-wirekit::menubar>` and `<x-wirekit::navigation-menu>` dropdown panels now open at their trigger even inside a transformed ancestor.** Both teleport their `position: fixed` panels to `<body>` (like modal / drawer / context-menu / tooltip), so a menubar or navigation-menu nested inside any element with a `transform` / `filter` / `perspective` / `contain` (which establishes a containing block for fixed descendants) no longer opens its dropdown far from the trigger. Keyboard navigation, outside-click close, and hover behavior are unchanged.
- **`<x-wirekit::sparkline>` no longer clips sibling cards in a multi-column KPI grid on mobile.** A block-mode sparkline now carries `min-width: 0; overflow: hidden` so the ApexCharts canvas's fixed intrinsic width can't floor a surrounding CSS-grid track — previously a `repeat(2, 1fr)` KPI grid kept both columns at the chart's ~300px width and pushed the right-hand cells off-screen on narrow viewports.
- **`<x-wirekit::toolbar>` wraps to a second row on narrow viewports instead of cramming.** The leading (search) cluster used `min-w-0`, so on a phone the search field collapsed toward zero width while the filter selects and action button squeezed beside it. The leading cluster now keeps a usable minimum width (`min-w-[min(100%,14rem)]`, growing to fill spare space) and the default-slot wrapper carries `flex-wrap justify-between`, so the filters and actions drop to a second row once they no longer fit beside a usable search field. Desktop layout is unchanged.

### Changed

- **`<x-wirekit::reading-progress>`, `<x-wirekit::reading-spine>`, `<x-wirekit::reading-bookmark>` `boundary` prop now accepts CSS-selector strings** in addition to `'container'`. Pass a selector like `boundary="#article"` and the Alpine init logs a `console.warn` when no ancestor matches — runtime verification that the developer's intended ancestor exists. The default (`null`) and `'container'` shapes are unchanged.
- **`<x-wirekit::reading-shell>` gains a `boundary` prop** that pass-through to every composed primitive — set once on the shell and the entire reading surface stays inside the parent positioned ancestor.
- **`<x-wirekit::dropdown>`, `<x-wirekit::modal>`, `<x-wirekit::alert-dialog>` `<x-slot:trigger>` named-slot path** now covered by both Pest tests and Playwright regression tests so the optional named-slot affordance can't silently drift.
- **`<x-wirekit::icon>` accessibility hardening** — when the underlying SVG package source ships a baked-in `aria-hidden="true"` on the SVG root (blade-heroicons does this), the component now strips that attribute and re-emits exactly one resolved aria-hidden value matching the developer's intent. Fixes a screen-reader regression where icons marked informative via `aria-label` / `role="img"` were silently skipped by assistive tech.
- **`IconResolver` fallthrough** now correctly resolves bare heroicon-family names like `<x-wirekit::icon name="pencil-square">` to `heroicon-m-pencil-square`. Previously the fallthrough probed a binding key that has never existed in blade-ui-kit/blade-icons, so every such call threw an "Unknown icon alias" exception.
- **`wirekit:doctor` (`wirekit:verify`) gains an environment-tier silent-typo log scan.** Walks `storage/logs/laravel*.log` for `WireKit [...]` ERROR / WARNING lines emitted by the prop-validation fallback path and surfaces them as a WARN with example lines. SAFE-DEGRADE at every failure mode (missing log file, non-file LOG_CHANNEL, custom log channels) — the helper NEVER blocks the doctor's exit code. Opt-out via `wirekit.doctor.scan_logs` config or `WIREKIT_DOCTOR_SCAN_LOGS=false`.
- **`wirekit:export-json` manifest entries carry a new `component_kind` field** (`anonymous` | `class`) so downstream AI tooling can branch on the value when generating composition shapes — anonymous components accept `<x-slot:name>`; class-based components accept only constructor-mapped props.
- **Hero docs ship a new "Tight mobile padding for `size='lg'`" subsection** demonstrating the `tightOnMobile` prop with a preview, plus a new `tightOnMobile` row on the Props table.
- **Reading docs gain a new "Scoping a primitive to its parent" subsection** with a contained-surface preview block and a 3-row behavior matrix covering `null` / `'container'` / `'<css-selector>'` boundary shapes.
- **Dropdown docs gain a new "Composition forms" section** walking both the quick-form (`<x-slot:trigger>`) and the explicit-sub-component path with previews and a do-not-mix tip.
- **Icon docs + Variants & Intents docs** carry explicit cross-reference notes documenting that the four status names (`info`, `success`, `warning`, `danger`) appear deliberately in both the icon-alias namespace and the canonical intent-value enum — the shared keyword is a feature, not a namespace collision.
- **Chart docs Troubleshooting section** names the silent-failure mode (chart adapter bundle not loaded at all → Alpine references an unknown factory) and contrasts it with the in-DOM advisory panel rendered when the adapter bundle IS loaded but the underlying chart library (`window.ApexCharts` / `window.Chart`) is missing. Chart.js + ApexCharts missing-library `console.error` now emits ONCE per page-load (deduplicated) instead of once per chart instance.

---

## [2.3.1] — 2026-05-27

Patch release.

### Fixed

- **`.cursor/rules/wirekit.mdc` section-heading polish.** Several headings have been simplified to cleaner product copy. Developers running `php artisan wirekit:cursor-rules` after `composer update` pick up the polished rules file.

---

## [2.3.0] — 2026-05-26

**Minor release.** Five surface areas touched: form-input accessibility (`multi-select`, `range-slider`), shared-prop-vocabulary aliases (`feature`, `card`, `button`, `heading`, `reveal`), icon-system completeness (8 new base aliases per preset + bare-name fallthrough resolution), new `main` max-width default + `button` `forceLoading`, two new artisan-command capabilities (`wirekit:show <parent>.<child>`, `wirekit:verify --fix`), plus a new static-analysis a11y linter `wirekit:doctor:a11y`. Documentation site grows the unified `/blueprints/` route with `/blueprints/partials/*` (8 marketing-section building blocks) and `/blueprints/recipes/*` (10 worked-example compositions) under a fresh index page.

### Added

- **`ariaLabel` prop on `<x-wirekit::multi-select>`.** Optional explicit override for the screen-reader-announced label of the component's internal combobox `<input>`. When unset, the component auto-derives a sensible label from (in order) any passthrough `aria-label` attribute, then the `label` prop, then the `placeholder`, then finally the `name` — so the internal input is never unlabeled even if the consuming code does not supply one explicitly.
- **`forceLoading` prop on `<x-wirekit::button>`.** When `true`, renders the spinner unconditionally and disables the button regardless of any `wire:loading` gate. Useful for static documentation demos, non-Livewire contexts, or stories that want to preview the loading state without a backend request in flight. The existing `loading` semantics are unchanged — `loading=true` alone still auto-detects the wire-action context.
- **`max` prop on `<x-wirekit::main>` with default `"2xl"`.** Caps the inner content width to the matching `--size-wk-container-*` token tier. Default `"2xl"` matches the existing `container` component default and prevents dashboards from stretching edge-to-edge on 1900 px+ monitors. Opt out with `max="none"` to preserve the pre-2.3.0 unbounded behavior, or pick another tier (`sm`/`md`/`lg`/`xl`/`full`). The config key `wirekit.components.main.max` overrides the default app-wide.
- **8 new base icon aliases in every preset** — `home`, `moon`, `sun`, `book-open`, `sign-out`, `megaphone`, `map`, `file-text`. The four base presets (Heroicons, Lucide, Phosphor, Tabler) now ship 34 aliases each (was 26). Every common first-install icon (theme toggle, sidebar home, sign-out button, file row) resolves without an additional preset.
- **Icon-alias fallthrough resolution.** When `<x-wirekit::icon name="briefcase">` references an alias that isn't in the active preset, the resolver now tries the underlying blade-icons identifier (`heroicon-m-briefcase`, then `-o-`, then `-s-`) before throwing `Unknown icon alias`. Logs an INFO line so the developer can add the alias to their preset. Catches the bug class where a developer reaches for a heroicons name that ships in `blade-ui-kit/blade-heroicons` but wasn't aliased by WireKit.
- **`php artisan wirekit:show <parent>.<child>` resolves dotted sub-component names.** Pre-fix, `php artisan wirekit:show card` advertised `Sub-components: card.body` but `php artisan wirekit:show card.body` returned "Unknown component" because the component registry tracks only top-level components. The artisan command now resolves sub-components by reading the nested Blade file directly and extracting props via the same parser the top-level command uses. Honors `--as=json` so AI tooling can introspect sub-components the same way as top-level components.
- **`wirekit:verify --fix` self-heals missing public assets.** When the doctor finds `public/vendor/wirekit/wirekit.css` or `wirekit.js` missing AND `--fix` is set, it proactively runs `vendor:publish --tag=wirekit-assets --force` and re-tests the asset paths so the verify report comes back clean. Closes the fresh-clone-fails-the-doctor gap where the `.gitignored` `public/vendor/wirekit/` directory is empty until the publish runs. Without `--fix`, the existing "Run: php artisan vendor:publish ..." hint is augmented with an "Or: php artisan wirekit:verify --fix" alternative.
- **`wirekit:doctor:a11y` — new static-analysis a11y linter.** Scans every `.blade.php` under a given path for three high-value WCAG-AA bug classes: icon-only `<x-wirekit::button>` without `aria-label`, `role="dialog"`/`"alertdialog"` without `aria-label`/`aria-labeledby`, `role="img"` without `aria-label`. CLI: `php artisan wirekit:doctor:a11y [path] [--fail-on=error|warning|none]`. Exit 1 on any finding ≥ threshold. Pair with `wirekit:verify` in CI to gate both integration health AND a11y in one pass.
- **`tag_alias` field in `wirekit:export-json` for class-based components.** Class-based components whose canonical tag uses the single-hyphen form (`<x-wirekit-chart>`) now also emit a `tag_alias` field carrying the double-colon form (`<x-wirekit::chart>`). Lets downstream tool integrators that grep against the historical shape still match. Normal anonymous components have no alias — the field is omitted entirely so the schema stays minimal for the common case.
- **`library` prop on `<x-wirekit-chart>` — per-instance chart-library override.** Accepts the same values as `config('wirekit.charts.library')` — the built-in keys `"chartjs"` / `"apexcharts"`, or a fully qualified class name implementing `ChartAdapter`. When `null` (default), the chart uses whatever the global config resolves to — matching pre-v2.3.0 behavior. When set, this chart instance binds to the named library regardless of the app default, so two charts on the same page can use different libraries. Useful when a specific chart type requires a specific library (`boxplot` / `candlestick` / `heatmap` / `treemap` / `column` are ApexCharts-only and throw `TypeNotSupportedException` against the Chart.js adapter) inside an app whose default is the other library. Backwards compatible — existing charts that don't set the prop continue to read from config exactly as before. The wrapper components `<x-wirekit::chart-mixed>` and `<x-wirekit::sparkline>` accept and forward `library` to the underlying chart, so mixed-library pages work via either tag shape. Example: `<x-wirekit-chart type="column" library="apexcharts" ... />` renders the ApexCharts column chart even when the app's default is `chartjs`.

### Changed

- **`<x-wirekit::feature>` `tone` prop accepts the canonical intent vocabulary.** `primary` is now accepted as an alias for `accent` (same color role), and `info` as an alias for `soft` (same tinted-accent treatment). The pre-2.3.0 names (`accent`, `neutral`, `soft`, `success`, `warning`, `danger`) are unchanged. Developers copying `intent="primary"` from a `<x-wirekit::button>` no longer get a 500 on `<x-wirekit::feature>`.
- **`<x-wirekit::heading>` `size` prop accepts `md` / `4xl` / `5xl`.** The size enum now reads `sm | md | base | lg | xl | 2xl | 3xl | 4xl | 5xl`. `md` is an alias for the existing `base` tier (matches the rest of the WireKit's middle-tier convention); `4xl` (2.25 rem) and `5xl` (3 rem) are new tiers for hero copy. The `--text-wk-4xl` and `--text-wk-5xl` design tokens are shipped in `dist/wirekit.css`.
- **`<x-wirekit::card>` accepts `variant="outline"` as alias for `outlined`.** `<x-wirekit::button>` accepts `surface="outlined"` as alias for `outline`. Same visual treatment, two spellings — the WireKit's pre-2.3.0 vocabulary diverged. Both forms now work on both components; canonical spellings (`outlined` for card, `outline` for button) are unchanged.
- **`<x-wirekit::profile>` `avatar` prop accepts `string | array{src?, initials?, alt?}`.** Pre-fix the prop required a URL string; passing an array crashed with a `htmlspecialchars(): Argument #1 ($string) must be of type string` error. Now matches the shape that `message.author` and the avatar primitive already accept. The initials fallback renders a rounded-full muted-background span with `aria-label` set to the profile's `name` so screen readers still announce who the profile belongs to.
- **`<x-wirekit::reveal>` `preset` prop accepts `fade-up` / `fade-down` / `fade-left` / `fade-right`.** Aliases for `slide-up-in` / `slide-down-in` / `slide-left-in` / `slide-right-in`. Matches the `fade-*` shorthand naming convention designers reach for first. Existing `slide-*-in` callers unaffected.
- **`animateIn` prop on every marketing component accepts the same `fade-*` aliases.** Card, feature, hero, stat, callout, alert, empty-state, cta, and footer all route through `WireKit::resolveAnimateIn()`, which now resolves the four `fade-*` shorthand aliases identically to `<x-wirekit::reveal>`. Pre-fix, `<x-wirekit::card animateIn="fade-up">` rejected the alias as an unknown enum value (silent no-op in production, `InvalidArgumentException` in debug). Now byte-identical resolution: `fade-up` → `slide-up-in` on every surface. Existing callers unaffected.
- **`<x-wirekit::sidebar.item>` consumes `data-current` as fallback for `:active`.** Livewire 4 emits `data-current` automatically on `wire:navigate` links. Pre-fix, sidebar.item ignored the attribute and required the developer to manually pass `:active="request()->is('posts*')"`, duplicating routing knowledge already encoded in `routes/web.php`. The explicit `:active` prop still wins; if unset and the attribute bag carries `data-current="true"` / `data-current="page"` / `data-current="1"`, the item highlights automatically.
- **`/blueprints/` documentation route — `partials/*` and `recipes/*` sub-sections.** The `/blueprints/` index page lists every entry with per-card previews and copy-paste guidance, organized under two sub-sections:
  - **`/blueprints/partials/*`** — 8 drop-in marketing-section building blocks: hero, features, pricing, testimonials, logo-cloud, FAQ, CTA, footer. Each partial is a single Blade snippet you paste into a parent layout and customize.
  - **`/blueprints/recipes/*`** — 10 worked examples composing several WireKit primitives into a complete UI shape: long-form article shell, documentation reader, on-page TOC, marketing landing page (with TOC variant), hero-with-code-aside, live KPI strip, stat-with-sparkline, feature-with-numbered-marker, toolbar-filter-bar.
- **`<x-wirekit::brand>` accepts `<x-slot:logo>` for custom logo markup.** Pre-fix the prop required a URL string for `<img src>`; passing a slot stringified the slot HTML inside the `<img src>` attribute and produced broken DOM output (visible attribute-fragment text). The component now detects a `ComponentSlot` instance and renders the slot directly. URL-string callers unaffected.

### Fixed

- **`<x-wirekit::multi-select>` internal combobox `<input>` now always carries an `aria-label`.** Previously, the parent `<x-wirekit::field label="…">`'s emitted `<label for="$id">` did not reach the internal combobox input (whose id is `$id-input`, not `$id`), so screen readers + axe's `label` rule reported an unlabeled form element. The component now synthesizes an `aria-label` via the fallback chain above and emits it on the internal input. WCAG 2.1 AA-compliant for any developer that has been wrapping multi-select in a `<x-wirekit::field>` with a `label` — no developer-side change required. Backward compatible.
- **`<x-wirekit::range-slider>` thumb-circles now sit vertically centered ON the track line.** Pre-fix, the two thumb `<div>`s were siblings of the track div, so their `top: 50%; transform: translateY(-50%);` resolved relative to the FULL component wrapper (track + edge labels + value display) and the thumbs visually dropped BELOW the track line. The thumbs now live INSIDE the track div, so their vertical centering resolves to the 8 px track height and the circles sit pixel-centered on the line.
- **`<x-wirekit::range-slider>` edge labels now show the slider BOUNDS, not the current values.** Pre-fix, the bottom-of-component value display rendered `minVal` / `maxVal` (the current handle positions) at the container edges via `justify-between`. The result was that the visible label "20" sat at position 0 % of the container, while the thumb representing value 20 sat at its proportional position (e.g. 6.7 % on a 0–300 range), and the two looked disconnected — and when the values matched the bounds exactly, the label and the thumb collided at the same pixel. Now: the edge labels render the constant slider min / max (`{{ $min }}` and `{{ $max }}`), and the current values surface as a tooltip-style badge ABOVE each thumb that tracks the handle horizontally as it moves. Screen-reader users still get a live announcement of the current range via an `aria-live="polite"` text node ("Range: 20 to 200") that updates on drag.
- **Prop validation no longer crashes the whole page in dev HTTP requests.** Pre-fix, `<x-wirekit::heading size="4xl">` (or any other invalid prop value) threw `InvalidArgumentException` in `APP_DEBUG=true` and the whole blade view 500'd. Now: strict-mode is split into two paths. CLI / Pest / `wirekit.validation.throw_on_invalid=true` still throws (fail-fast — the signal a dev wants in a test or artisan command). HTTP dev requests log at ERROR level and render with the first-allowed fallback so a single prop typo doesn't take down the entire page. The dev still sees the typo loudly in the log; iteration continues without a request-response restart.
- **`<x-wirekit::button>` warns at log level on unknown prop keys.** Pre-fix, `<x-wirekit::button variant="ghost">` (the prop is `surface`, not `variant`) was silently swallowed — the button rendered with default `surface="filled"` and the developer got no signal that their intended `ghost` treatment didn't apply. The new `WireKit::warnUnknownProps()` helper logs a Levenshtein-ranked Did-you-mean warning for any unknown key not in the canonical Blade-passthrough allowlist (`aria-*`, `data-*`, `wire:*`, `x-*`, `@*`, `:*`, plus reserved HTML attrs). Public helper — opt-in for every component that wants the warning; `button` and `main` already adopt it.
- **`<x-wirekit::reading-spine>` hover-boundary flicker.** Cursor jitter (1–2 px) at the spine's top or left edge fired alternating `mouseenter`/`mouseleave` events on every frame, toggling the spine expanded ↔ collapsed once per frame and producing a visible flicker. Hover-collapse now debounces by 120 ms — a re-entry within that window cancels the pending collapse, absorbing the jitter. Expand still fires immediately on initial hover, so the responsive direction is unchanged. Focus-driven expansion (`expandOnFocus` / `collapseOnFocus`) is unaffected.
- **`<x-wirekit::reading-spine>` implicit horizontal scrollbar inside the spine.** The spine carried `overflow-y-auto` but no explicit `overflow-x`. Per CSS spec, setting one axis to `auto` while the other defaults to `visible` computes BOTH axes to `auto` — so long `white-space: nowrap` heading labels inside the spine produced a thin horizontal scrollbar gutter even though the ellipsis truncation already clipped them visually. Pinned to `overflow-x-hidden overflow-y-auto` for true vertical-only scroll.
- **Themed scrollbar (`.wk-scrollbar`) applied across every component with an internal scroll region.** `<x-wirekit::modal>` body, `<x-wirekit::drawer>` body, `<x-wirekit::alert-dialog>`, `<x-wirekit::code-block>`, `<x-wirekit::combobox>` dropdown list, `<x-wirekit::command-palette>` overlay + inner list, `<x-wirekit::kanban>` (horizontal row) + `<x-wirekit::kanban-column>` (vertical list), `<x-wirekit::main>`, `<x-wirekit::reading-spine>`, and `<x-wirekit::reading-toc>` previously showed the OS-default scrollbar (thick on Windows, hidden-until-scroll on macOS). Each now applies WireKit's themed scrollbar — thin width, theme-aware thumb / track colors, dark-mode reactive. `<x-wirekit::prose>` `<pre>` children inherit the same treatment via a `.wk-prose pre` CSS rule (no template change required). `<x-wirekit::multi-select>`, `<x-wirekit::scroll-area>`, and `<x-wirekit::table>` already carried `.wk-scrollbar` and are unchanged. Override the colors / width via `--color-wk-scrollbar-thumb`, `--color-wk-scrollbar-track`, `--color-wk-scrollbar-thumb-hover`, `--size-wk-scrollbar` in `:root {}`.

---

## [2.2.0] — 2026-05-22

**Minor release.** Form-input upgrades (range-slider Livewire integration, input HTML5 props, number-input grid-snap), entrance-animation polish (zero-flash + delay-prop fix universal), AI-tooling schema fields (`sub_components`, `slots` required flag, `@example`, ClassPropsExtractor for class-based components), two new artisan commands (`wirekit:fonts`, `wirekit:icons`), and three new docs pages (`prop-naming-conventions`, `overlays/events`, `recipes/on-page-toc`).

### Added

- **`<x-wirekit::range-slider>` first-class `wire:model` integration.** Pre-fix `wire:model="priceRange"` on the component tag was silently dropped into the outer `<div>`'s attribute bag — Livewire only watches input/select/textarea elements, not divs. Dragging the slider didn't update the Livewire property. Now: the component detects any `wire:model*` directive on the tag, strips it from the outer bag (no double-render), and re-emits it on the two hidden inputs as `wire:model="propName.min"` / `wire:model="propName.max"`. Developer declares `public array $priceRange = ['min' => 20, 'max' => 80];`; dragging either handle updates `$priceRange['min']` / `$priceRange['max']` live. All modifiers (`.live`, `.lazy`, `.debounce.500ms`, `.blur`) flow through unchanged. `docs/components/range-slider.md` "Livewire Integration" section rewritten to show the array-property pattern.
- **`docs/extending/prop-naming-conventions.md` — reference page for the three semantic-modifier prop families (`intent` / `variant` / `tone`).** Documents the contract behind each name, the canonical 7-value `intent` enum (`primary` / `accent` / `neutral` / `danger` / `success` / `warning` / `info`), and a per-family decision tree for component authors. Cross-component example table covers `<x-wirekit::button>` (`intent`), `<x-wirekit::alert>` / `<x-wirekit::card>` (`variant`), and `<x-wirekit::feature>` (`tone`). Single source of truth for "which prop name should my new component use?" — closes the historical gap where the three families were inferred from reading the source. Registered under the "Extending" group in the docs navigation.
- **`docs.wirekit.app/recipes/on-page-toc` — canonical recipe for the right-edge sticky sidebar Table-of-Contents pattern.** Pairs `<x-wirekit::reading-spine>` and `<x-wirekit::reading-progress>` to document the in-page TOC shape common to modern documentation sites. Covers the sidebar shape end-to-end (canonical wiring, CSS-variable knobs for expanded width and sticky offset, integration with a sticky `<x-wirekit::brand-bar>`, breadcrumb composition); cross-links to the `marketing-landing-toc` recipe for the horizontal strip variant. Targets the `<main>` selector by default so `H2` / `H3` headings auto-populate the spine without manual wiring.
- **`docs/overlays/events.md` — canonical event-vocabulary reference page.** Complete matrix of every overlay component's event names + payload shapes (modal / drawer / alert-dialog / command-palette / tour / toast-region — 6 overlays). Three sections: the matrix table, Alpine `$dispatch` + Livewire `$this->dispatch()` examples per event family, and "Conventions + footguns" enumerating the 4 historical irregularities (hyphen-not-colon, tour's name-in-event-name shape, toast's variant-vs-intent divergence, fire-and-forget show events). Single source of truth for AI / IDE tooling that previously had to discover this by reading `resources/js/components/*.js`. Registered under a new "Overlays" group in the docs navigation.
- **`<x-wirekit::alert-dialog.cancel>` sub-component — pre-wired close button for alert-dialogs.** Analogous to `<x-wirekit::modal.close>`, but specific to the destructive-action confirmation dialog. The wrapping `<div>` carries `x-on:click="close()"` (the parent's `wirekitAlertDialog` Alpine x-data exposes the method); slot defaults to a `<x-wirekit::button intent="neutral" surface="filled">Cancel</x-wirekit::button>` so the typical case is a one-tag affair. Override the label by passing slot text (`<x-wirekit::alert-dialog.cancel>Back</x-wirekit::alert-dialog.cancel>`) or wrap your own `<x-wirekit::button>` for full control (custom variant / size / icon). Replaces the manual-wiring pattern (`x-on:click="$dispatch('wirekit-alert-dialog-close', { name: '...' })"`) — that pattern still works but is no longer required.
- **`wirekit:make recipe:<name>` scaffolds for all 9 documented recipes.** Each `recipe:` template generates a Livewire class + Blade view from a shipped stub mirroring the corresponding `docs.wirekit.app/recipes/<name>` page's structural composition. Class names derive PascalCase from the kebab-case recipe slug (`marketing-landing-page` → `MarketingLandingPage`). Available recipes: `documentation-reader`, `feature-numbered-marker`, `hero-with-code-aside`, `live-kpi-strip`, `long-form-article`, `marketing-landing-page`, `marketing-landing-toc`, `stat-with-sparkline`, `toolbar-filter-bar`. Each generated view ends with a comment cross-linking to `docs.wirekit.app/recipes/<name>` for the full reference. Unknown recipes fail with a Levenshtein-ranked Did-you-mean hint.
- **`slots` schema field now flags required slots.** Slot entries in `wirekit:export-json` / `.wirekit-schema.json` / `wirekit:show` were previously a flat list of names — they didn't tell developers (or AI tooling) which slots were OPTIONAL (guarded by `@isset`) and which were REQUIRED (referenced bare via `{{ $name }}`). The shape now reads `[{name: 'trigger', required: true}, {name: 'header', required: false}, ...]` per slot. Detection heuristic: an `@isset($name)` / `isset($name)` guard marks the slot as optional; a bare `{{ $name }}` / `{!! $name !!}` / `$name->method()` reference without an enclosing guard marks it as required. The fix closes the popover / hover-card / context-menu gap — all three reference `{{ $trigger }}` directly and 500 with `Undefined variable $trigger` when developers omit the slot; the manifest now reports `trigger` as `required: true`.
- **`Pushery\WireKit\Support\ClassPropsExtractor` — public Reflection-based prop extractor for class-based Blade components.** Companion to `PropsParser`. Class-based components like `<x-wirekit-chart>` (registered via `loadViewComponentsAs(...)`) don't have an `@props([...])` block — the prop surface IS the constructor signature. `ClassPropsExtractor::extract(string $className)` walks the constructor parameters via Reflection and returns the same shape `PropsParser` emits, so downstream callers don't branch. Constructor parameter names are kebab-cased for Blade-attribute compatibility (`wireStream` → `wire-stream`); type hints stringify to their declared form (`string`, `?string`, `int`, `bool`, `array`, union types). Default values stringify as PHP literals matching what PropsParser captures. `ComponentRegistry::extractProps('chart')` now returns 11 props (was empty). The schema entries from `wirekit:export-json` / `.wirekit-schema.json` / `wirekit:show chart` all carry the full constructor surface — AI tooling and IDE extensions see chart's `type` / `labels` / `datasets` / `wire-stream` / etc. instead of the previous empty `props: []`.
- **`@example "..."` annotations in `@props` blocks surface as a new `examples` field in the schema.** `PropsParser` now reads `// @example "value"` annotations from the trailing same-line comment after a prop's comma and exposes them as a `list<string>` field on every schema entry next to `name` / `default` / `comment`. Use for props whose accepted value-shape is non-obvious from the default alone — the canonical first user is `<x-wirekit::grid>` `cols`, which accepts a Tailwind-style space-separated string (`"1 md:2 lg:4"`); the default `1` doesn't telegraph that. Multiple `@example` annotations per comment are all captured; backslash-escaped quotes inside the value are supported (`@example "Press \\"Enter\\" to confirm"`). Empty array (not null) when no annotation present, so the field is type-stable across the catalog.
- **`wirekit:icons` artisan command** — list every icon alias shipped with WireKit, grouped by preset. Each section shows the alias-count summary, an `[active]` / `[opt-in]` indicator against the current `wirekit.icons.preset` / `wirekit.icons.presets` config, and every alias → Blade-Icon identifier mapping. Same API shape as `wirekit:list` / `wirekit:fonts` (`--preset=...`, `--as=count|presets|aliases|json`, `--format=` alias, Levenshtein-ranked Did-you-mean on unknown preset). The `--as=aliases` flavor emits a unique alphabetised alias list across the (optionally filtered) preset set — useful for "do any presets define `bolt`?" lookups. JSON output per preset: `{key, count, active, requires, aliases}` so AI tooling sees the active-vs-opt-in distinction without re-reading config. The pre-existing `IconResolver::availablePresets()` PHP-side helper is unchanged; the CLI is a thin discovery wrapper. Also: the `bolt` example in `docs/components/icon.md`'s Sizing section is replaced with `search` (which is in the default `heroicons` preset) — `bolt` lives only in `heroicons-marketing` and required opt-in to render.
- **`wirekit:fonts` artisan command** — list every font preset shipped with WireKit, grouped by category. Mirrors `wirekit:list`'s API surface (`--category=sans|serif|mono`, `--as=count|slugs|categories|json`, `--format=` alias, Levenshtein-ranked Did-you-mean on unknown category). Each row shows the preset key, label, and font-family; the keys map 1:1 to the values accepted by `wirekit:install --font={key}`. Previously the only way to discover the preset list was via `tinker` or a typo'd install-error path. New PHP-side surface: `Pushery\WireKit\Fonts\FontRegistry::all()` / `category()` / `get()` (already existed; the CLI now wraps it).
- **`sub_components` array on every entry in `wirekit:export-json` + `.wirekit-schema.json`.** Previously only `wirekit:show <name>` surfaced the sibling-directory `.blade.php` files (e.g. `card.body`, `card.header`, `card.footer`). The JSON-manifest paths read by AI tooling, IDE extensions, and the `.wirekit-schema.json` written by `wirekit:install` now carry the same list — first-class field next to `props` and `slots`. Sorted alphabetically; skips `index.blade.php` (Laravel's anonymous-component index file). Components with no sibling directory get an empty array (not null) so the field is type-stable. Closes the "card looks empty because the body wrapper is invisible to tooling" gap.
- **`<x-wirekit::tabs>` accepts `items` as array-of-objects in addition to the legacy keyed-assoc form.** Two list-input shapes now coexist on the component: `['profile' => 'Profile']` (compact, ergonomic for static inline data) AND `[['key' => 'profile', 'label' => 'Profile']]` (matches typical API responses, easier to compose from a Livewire `@computed` property). The component normalizes both at the template edge — pick whichever your data source already produces. Both shapes produce byte-identical rendered HTML (same `aria-controls` / `id` wiring, same Alpine bindings) so screen-reader behavior is stable across the two. When the `label` field is missing in array-of-objects form, the `key` is used as the visible label.
- **`<x-wirekit::input>` HTML5 form-state props in `@props`.** Five props now declared explicitly: `required`, `disabled`, `readonly`, `autocomplete`, `placeholder`. Previously these only worked via attribute-bag passthrough (`<x-wirekit::input required>`) — invisible to the schema, AI tooling, and IDE autocomplete. The `required` prop additionally flows through to the associated `<x-wirekit::label>` so its required indicator (`*`) renders without manual wiring. Back-compat preserved: the attribute-bag passthrough still works exactly as before, and mixing the two forms (`:required="true" required`) produces a single `required` token on the underlying `<input>` — guarded by an explicit regression test.
- **`<x-wirekit::profile>` `interactive` prop for dropdown-trigger composition.** Profile previously rendered a presentational `<div>` with no focusable child. Wrapping it in `<x-wirekit::dropdown.trigger>` (the canonical user-menu pattern) opened the menu on mouse-click but was unreachable by keyboard — Tab skipped past the profile entirely because the trigger's focusable-descendant search returned null. New `interactive` prop (default `false`) emits `role="button"`, `tabindex="0"`, Enter / Space keyboard handlers synthesizing a click, plus a focus-visible ring matching the canonical button focus state. Default `interactive=false` preserves the pre-existing presentational `<div>` byte-for-byte. New "Interactive profile inside a dropdown trigger" preview block on the docs page shows the canonical composition.
- **`<x-wirekit::brand>` `mobileLogo` + `mobileBreakpoint` props for responsive logo swap.** A wide wordmark (`/brand/wirekit-wordmark.svg`) on a brand-bar overflows narrow viewports — `mobileLogo` pairs with a compact mark (`/brand/wirekit-mark.svg`) that renders below the chosen Tailwind breakpoint, with the wide wordmark taking over at + breakpoint. `mobileBreakpoint` accepts `sm` / `md` / `lg` / `xl`, default `sm` (640 px). When `mobileLogo` is null, behavior is unchanged (single `<img>` at every viewport — back-compat). Both images carry the same accessibility shape (`alt=""` + `aria-hidden="true"`); the `<a>`'s `aria-label` provides the accessible name. Invalid `mobileBreakpoint` values throw in debug, fall back to `sm` in production via `StrictnessGate`. Six tests cover back-compat (single-logo unchanged), default breakpoint swap, custom breakpoint, invalid value throw, mobileLogo-without-main-logo no-op, accessibility (two images both decorative + aria-label preserved).
- **`Pushery\WireKit\Support\StrictnessGate` — public single source of truth for runtime validation strictness.** Central decision behind `WireKit::validateProp()` (component-level prop validation) and `IconResolver` (icon-alias / preset lookups): strict mode throws `InvalidArgumentException` with a Did-you-mean hint, lenient mode logs a warning (now WITH the actual fallback value annotated) and returns the first allowed value (or a caller-supplied override via the new `fallback` parameter). `StrictnessGate::isStrict()` returns the current decision; `StrictnessGate::enforce($context, $key, $value, $allowed, fallback: null)` is the canonical entry point; `StrictnessGate::formatMessage(...)` exposes the message-builder so callers that throw their own typed exception (like `IconResolver`) inherit identical wording.
- **`wirekit.validation.strict` config key (env `WIREKIT_STRICT_VALIDATION`)** — explicit override of the strict-vs-lenient decision. `null` (default) keeps the historical behavior (strict in `APP_DEBUG=true`, lenient in prod). `true` forces strict everywhere — useful for CI / staging hardening. `false` forces lenient everywhere — useful for snapshot CI runs that want to assert rendered output regardless of prop typos. Documented in `config/wirekit.php` with a per-value behavior matrix.

### Changed

- **Integration Guide URL moved from `/integration` to `/getting-started/integration`.** The guide is conceptually a sub-page of Getting Started — installation walkthrough, optional dependencies, asset publishing, verification checklist — so the URL now reflects that hierarchy. Every in-docs cross-link has been updated in lockstep (10 inbound references across `getting-started.md`, `cli-reference.md`, `livewire-starter-kit.md`, `components/resizable.md`, `extending/console-output-baseline.md`, `cli-reference/wirekit-doctor.md`). External bookmarks pointing at the legacy `/integration` URL will need updating; the new URL is the canonical location going forward.
- **`wirekit:doctor` now WARNs when `'charts.library' => 'chartjs'` is configured but `resources/js/app.js` is missing the `Chart.register(...registerables)` registration.** Catches the #1 first-run Chart.js gotcha upstream: `npm install chart.js` + `'chartjs'` in config IS NOT enough — the JS-side `import { Chart, registerables } from 'chart.js'; Chart.register(...registerables);` must be in `app.js` or the chart component renders + mounts but draws nothing (Chart.js logs a friendly console.error and gives up). The doctor's WARN prints the actionable snippet inline. Comments stripped before scanning so a `// Missing: Chart.register(...)` hint comment doesn't false-pass the detection. `getting-started.md` Optional Features section gains an explicit three-step callout (config + npm + JS-side registration) cross-linked to `/integration#optional-dependencies`.
- **`<x-wirekit::table>` warns in debug mode when plain HTML descendants (`<thead>` / `<tbody>` / `<tr>` / `<th>` / `<td>`) are passed inside the slot instead of the `<x-wirekit::table.*>` sub-components.** Same composition-required-for-padding pattern as `<x-wirekit::card>`: the bare `<table>` only carries the outer styling — padding, row dividers, stripe + hover wiring live on the sub-components. Dropping raw HTML inside compiles cleanly but produces a visually-broken table with no error. The console.warn now surfaces the silent breakage with a link to the canonical composition. Production stays silent. All 5 sub-components (`table.head` / `table.body` / `table.row` / `table.th` / `table.td`) now emit a `data-wk-table-*` marker on their root element — used by the detector to distinguish "wrapped" from "raw". Markers are inert at runtime; they exist purely as detection signals.
- **`<x-wirekit::tabs>` warns in debug mode when `wire:model*` is passed on the component tag.** Pre-fix the wire:model attribute was silently dropped into the outer `<div>`'s attribute bag (Livewire only watches `<input>`, `<select>`, `<textarea>` — not divs). Tabs are client-only Alpine state; clicking a tab mutates a private `x-data="{ active: '...' }"` scope and does NOT update Livewire state. The console.warn now surfaces the silent-breakage with a link to `docs.wirekit.app/components/tabs` for the named-slot contract. Production stays silent (no console.warn). The component's behavior is unchanged — the warn is observability-only. Docs page rewritten with a "Contract: named slots, NOT wire:model" section under the H1 explaining the client-only state model, the named-slot pattern with a complete example, and the @computed-property workaround for server-side initial state.
- **`<x-wirekit::alert-dialog>` ESC key always closes, even when `dismissible="false"`.** Pre-fix the dialog was completely keyboard-locked when non-dismissible — ESC inert, backdrop click inert, Cancel button wasn't pre-wired. The only escape was page reload. Now: backdrop click stays inert on non-dismissible (the safety-strict half — don't approve destructive action by a stray click), but ESC always fires the close event. Keyboard users always have an escape hatch. Implementation: `createOverlay` gained an `escapeAlwaysCloses` option; alert-dialog passes `true`; focus-trap uses `escapeDeactivates: dismissible || escapeAlwaysCloses`. JS bundles rebuilt.
- **`<x-wirekit::replay-button>` removed from the README components table and the `wk-replay-button` row in the Public CSS API catalog no longer carries a docs link.** The component remains fully callable in the package (the `<x-wirekit::replay-button>` tag still renders, the `wk-replay-button` CSS class still ships in `dist/wirekit.css`) — replay-button is preview-chrome infrastructure rather than a developer-pickable primitive, so it's no longer surfaced in the catalog tables.
- **`IconResolver` exception messages now include Did-you-mean hints** on close-typo alias AND preset names. Previously `Unknown icon alias 'clse'` listed only the available aliases (long list); now reads `Unknown icon alias 'clse'. Did you mean: close, eye, plus? Available aliases: …` — same Levenshtein-ranked suggestion contract as `WireKit::validateProp()` and every `wirekit:*` CLI surface. Same enhancement for `Unknown icon preset 'heroicns'` (suggests `heroicons`). Behavior-preserving — the `InvalidArgumentException` class and the leading message prefix are unchanged so existing exception-catching tests stay green.
- **`WireKit::validateProp()` lenient-mode log message now annotates the fallback value.** The pre-existing log line read `WireKit [button]: Invalid variant "purple". Allowed: primary, secondary.` — readers had to know the fallback contract to interpret what the component actually rendered. Now reads `… Falling back to "primary".` so prod log readers see the concrete substitution without consulting the source. The new annotation flows automatically from `StrictnessGate::enforce()`.
- **`wirekit:show chart` / `wirekit:list --format=json` / `wirekit:export-json` / `wirekit:export-api-map` now emit the class-based Blade tag form `<x-wirekit-chart>`** (not the broken anonymous form `<x-wirekit::chart>`). The chart component is registered class-based via `loadViewComponentsAs(...)` so the working tag is the prefixed-class shape; the anonymous Blade file at `resources/views/components/chart.blade.php` exists for the class-based path to render through but reaching it via `<x-wirekit::chart>` hits an undefined `$alpineComponent` variable and 500s. New `ComponentRegistry::tag(string $name): string` helper is the single source of truth, with a small per-component override map (currently only `chart`). Every CLI surface that prints a "Tag:" field reads from it, so every "what tag do I use?" report now prints the WORKING form.

### Fixed

- **`<x-wirekit::file-upload>` file list now visibly separates from the dropzone above it.** Pre-fix the `<ul>` carried an inline `style="list-style: none; margin: 0; padding: 0;"` (required so the bullet-list cleanup works in the sandbox-iframe rendering context where Tailwind classes don't resolve) AND a class string with `mt-[var(--padding-wk-y-sm)]` (the intended dropzone gap). Inline `margin: 0` wins over the class via CSS specificity, so the gap was always zero and the file list sat flush against the dropzone's bottom edge. The spacing BETWEEN file rows (driven by `gap-[var(--space-wk-sm)]` on the flex parent, not by per-child margin) was unaffected, which made the bug look like "spacing works between files but not against the dropzone above". The fix migrates the dropzone gap into the inline declaration (`margin: var(--padding-wk-y-sm) 0 0 0`), so the gap resolves in BOTH rendering contexts — developer apps and sandbox iframes both load `dist/wirekit.css`, so the CSS variable resolves identically.
- **`<x-wirekit::reveal>` `delay` prop silently dropped whenever the caller also passed an inline `style="…"` attribute.** The template emitted TWO `style=` attributes on the root `<div>` — one from `$attributes->merge(...)` carrying the caller-passed value, and one from a separate `@if($delayValue) style="..."` directive carrying `animation-delay: {value}`. Per HTML5 parsing rules, duplicate attributes on the same element resolve to the FIRST and silently drop the second, so the `animation-delay` was never honored whenever any caller-supplied inline style was present. Every gallery preview on `docs/animations.md` passes `style="--wk-stagger-step: 0ms"`, so the bug was universal on the gallery and on every developer composition that passed inline style for any reason — `delay="lg"` / `:delay="1000"` / etc. all rendered but had zero visual effect. The fix composes caller-style + internal-animation-delay into a single attribute via a `@php`-block merge before the `<div>`, so the root now carries exactly one `style="caller-styles; animation-delay: …"` attribute and the delay actually takes effect.
- **Every `<x-wirekit::reveal>` and `animateIn`-driven entrance animation (`<x-wirekit::hero animateIn>`, `<x-wirekit::card animateIn>`, `<x-wirekit::feature animateIn>`, `<x-wirekit::cta animateIn>`, `<x-wirekit::stat animateIn>`, `<x-wirekit::callout animateIn>`, `<x-wirekit::alert animateIn>`, `<x-wirekit::empty-state animateIn>`, `<x-wirekit::footer animateIn>`) no longer flashes when the element is already in the viewport at page-load.** Pre-fix the `animation-fill-mode: both` on `[class*='wk-animate-']` included `backwards`, which snapped the element to its keyframe `from` state (opacity 0) the instant Alpine added the animation class. For elements not yet in viewport this was invisible — the element wasn't visible anyway. For elements ALREADY in viewport at page-load (the marketing-landing-page hero, for example), the IntersectionObserver fired almost immediately on Alpine init, the class arrived, and the browser visibly snapped the element from `opacity: 1` (no class) to `opacity: 0` (from-state) before animating back. User perceived a brief "flash to invisible → 300 ms fade-in" — the flicker. New CSS rule in `dist/wirekit.css` pre-hides any element with `x-data="wirekitAnimate('*-in')"` and no `wk-animate-*` class yet, scoped to viewport-trigger only (click and manual triggers are explicitly excluded so click-to-animate cards stay visible until clicked). The `:not([class*="wk-animate-"])` clause deactivates the rule the moment Alpine fires; the keyframe `from` opacity matches the pre-hide opacity, producing a clean entrance with no snap. Pure CSS fix — no Blade or JS code changes — upgrades every existing reveal / animateIn usage transparently.
- **`<x-wirekit::reading-toc>` link horizontal padding now follows the design-token grid (`--padding-wk-x-sm`, 10px) instead of the hardcoded `px-2` (8px) Tailwind atom.** The link's `px-2 py-1` declaration was a pre-token-system convention left over from the component's first authoring — never reconciled with the project's `--padding-wk-x-*` scale. The 2px offset misaligned the active-link highlight chrome with the surrounding content-padding column (every other prose container sits on the 10px grid), reading as "the strip is 2 px out". Now `px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]` — 10px × 4px — so the link's left edge lands on the same vertical line as the prose paragraphs beneath the strip. Y-axis kept at the equivalent token (`y-xs` = 4px) to preserve the strip's overall height.
- **`<x-wirekit::number-input>` stepper buttons now snap to the step grid instead of skipping values when the starting value is off-grid.** Pre-fix `value=1.78 step=0.1` clicked `+` jumped to `1.9` (skipping `1.8`) because the Alpine handler added `step` to the current value (`1.78 + 0.1 = 1.88`) then ran `toFixed(precision)` which rounded `1.88 → 1.9`. The intermediate grid point `1.8` was unreachable. The fix snaps to the step grid anchored at `min` (falling back to `0` when no `min` is set): increase goes to the next grid point ABOVE the current value (`1.78 → 1.8`), decrease goes to the next grid point BELOW (`1.78 → 1.7`) — matching the W3C native `<input type=number>` stepper contract. A `1e-10` tolerance absorbs binary-float drift so on-grid values like `1.8` (which can be stored as `1.7999999998`) still advance to the correct next step. Anti-regression test `it('emits the precision/round helpers + grid-snap stepper wiring in Alpine x-data')` pins the new shape.
- **`<x-wirekit::file-upload>` file-row layout now pushes size + remove button to the right edge with proper horizontal padding.** Pre-fix the filename, size, and `×` remove button clustered on the LEFT of each row leaving a large empty area on the right, AND the row's all-sides `p-[var(--padding-wk-y-xs)]` declaration used the tight Y-axis token for horizontal padding too — content felt cramped against the row's left edge with no breathing room. Filename now carries `flex-1` so it grows to fill available space (pushing the size + X to the right edge in the standard file-uploader UX shape), and padding split into `px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-xs)]` so horizontal breathing room is generous while vertical stays compact. File rows carry variable-length content, not nav-chip text, so the asymmetric padding shape is correct.
- **`<x-wirekit::stat animate animateIn="…">` race condition that left random counters stuck at "0" on hard refresh.** When both `animate` and `animateIn` were set, the Blade template wrapped the inner counter root in an outer `<div x-data="wirekitAnimate('…')">` carrying the entrance keyframe (e.g. `wk-slide-up-in`: `translateY(1rem)` → `translateY(0)`). The entrance keyframe shifted the inner element's geometry for ~300 ms. The inner counter's own `IntersectionObserver` (`threshold: 0.4`) could fire while the entrance keyframe was active — the shifted bounding box pushed the inner below threshold, the callback returned without starting the counter, and the stat stayed at "0" indefinitely. The race was random per card because it depended on Alpine mount-order vs paint-timing; no console errors and no determinate reproduction in a static test environment. Now: when the entrance wrapper is present, the counter listens for `animationend` on the outer rather than running its own `IntersectionObserver` — the outer's `wirekitAnimate` plugin already owns scroll-into-view via its own observer, so the counter just needs a deterministic "start" signal that doesn't depend on transform-affected geometry. Standalone counters (no entrance wrapper, the dominant deployment shape) keep the IO behavior unchanged. The counter-tick logic was factored into a shared `runCounter` closure so both paths call the identical ease-out-cubic-over-1.2 s tick.
- **`<x-wirekit::tour.step>` `index` prop now auto-assigns sequentially within its parent `<x-wirekit::tour>`.** Pre-fix every step defaulted to `index=0`, so every step rendered with `data-wk-tour-step="0"`. The tour's `start()` correctly counted 3 steps, `next()` incremented `currentStep` to 1, then `_positionStep()` queried `[data-wk-tour-step="1"]` — found nothing → all step divs hidden because none match `currentStep === 0` anymore. Tour appeared to "break" after the first Next click — actually all steps just disappeared. Now: `index` defaults to `null`; when null, the step picks the next integer from a per-render counter (`Pushery\WireKit\Support\TourStepCounter`) that the parent tour component resets at the top of its `@php` block. Steps with explicit `:index="N"` bypass the counter (the developer's choice; the counter only advances on `next()` calls). Multiple tours on the same page each restart numbering from 0 — Alpine scopes isolate per-tour `currentStep`. The natural usage pattern — just list the steps, no `:index` props — now works correctly.
- **`<x-wirekit::range-slider>` value-display spans referenced undefined Alpine variables.** The two `<span x-text="minVal">` / `<span x-text="maxVal">` elements rendered AFTER the `</div>` that closed the x-data scope — every page using the slider flooded the browser console with `Alpine Expression Error: minVal is not defined` (twice per render) and the displayed numbers never updated when the user dragged. The slider handles still rendered + dragged correctly because their bindings sit on Alpine-scoped attributes (`:style`, `@pointerdown`) within the x-data root; only the read-out spans were affected. Now restructured so the value-display row sits INSIDE the x-data root, between the max-thumb `<div>` and the scope-closing `</div>`. Regression test depth-counts `<div>` opens / closes through the rendered HTML and asserts both `x-text` substrings appear before the matching close — catches future structural regressions.
- **`<x-wirekit::badge intent="accent">` threw `Invalid intent "accent"` in debug mode** (broke live previews on `docs.wirekit.app/recipes/marketing-landing-page` + `docs.wirekit.app/components/hero` after v2.1.x). `accent` was missing from the badge's `validateProp` allowed list — a regression vs the canonical 7-value intent enum documented in `docs/extending/prop-naming-conventions.md`. Now restored as a first-class intent with a high-contrast filled rendering (`bg-[var(--color-wk-accent)]` + `text-[color:var(--color-wk-accent-fg)]`) — the "stand out against surrounding chrome" variant used on CTA hero pills, pricing-card "Most popular" markers, and marketing "New" eyebrows. Dot indicator uses accent-fg for contrast on the filled bg. Lockstep enforcement scans every `<x-wirekit::badge intent="X">` literal in the docs and asserts X appears in the validateProp allowed list — catches future drift in either direction (intent enum change OR docs author using a fresh intent name).
- **Animation-emitting components now uniformly emit `data-replayable="true"`** when their `animateIn` prop is set — `<x-wirekit::card>` / `<x-wirekit::feature>` / `<x-wirekit::hero>` / `<x-wirekit::cta>` / `<x-wirekit::footer>` previously emitted the `x-data="wirekitAnimate(...)"` hook without the replay marker, so `docs.wirekit.app`'s preview-chrome replay button missed them. Existing emitters (`<x-wirekit::reveal>`, `<x-wirekit::stat animate>`, `<x-wirekit::chart>`) are unchanged. The attribute is otherwise inert; on developer apps without a replay surface it's a harmless no-op. New `docs/animations.md` subsection documents the same contract for utility-class animations (raw `class="wk-animate-fade-in"` on HTML elements — developer adds `data-replayable="true"` manually since there's no PHP render path). The contract is pinned across all 11 animation-emitting components: every one carries the attribute when the `animateIn` prop is set, and does NOT carry it when inactive.
- **`<x-wirekit::hero>` aside column overflowed the page on `<lg` viewports when fed wide unbreakable content.** The most common shape: developer drops a `<x-wirekit::code-block>` in the `<x-slot:aside>`; `<pre>`'s `white-space: pre` keeps the longest line non-breakable, so a 40-char code line drives the aside column to ~425 px on a 393-px iPhone viewport — overflows the section's padding-x boundary, code-block hangs out of the right edge. The copy column carried the same theoretical risk (long URL in lede, ascii-art header). Both columns now carry `min-w-0 w-full lg:w-auto` on the flex-col / flex-row break: `min-w-0` escapes flex-shrink's default `min-content` floor, `w-full lg:w-auto` takes parent's full inline-size on flex-col then reverts to content-sized on `lg:flex-row`. The code-block's own `overflow-x-auto` on its `<pre>` then handles intra-line horizontal scrolling within the contained column. Back-compat: existing layouts at `lg+` keep the same `flex-row` content-sized columns.
- **`<x-wirekit::button loading>` without a `wire:*` action attribute was a visual no-op.** The component emitted `wire:loading` on the spinner SVG + `wire:loading.attr="disabled"` on the button — both Livewire directives that only activate WHILE a Livewire request is in flight. So `<x-wirekit::button :loading="true">` on a non-Livewire page (or a button with no `wire:click` / `wire:submit`) rendered an invisible spinner and a clickable button. The component now distinguishes two intents: (a) `loading=true` + any `wire:*` action present → unchanged behavior (spinner + disabled while the Livewire request runs), (b) `loading=true` + NO `wire:*` action present → DECLARATIVE loading state, spinner always visible + native `disabled` attribute set. Developers who want "this button is in a loading state, period" now get the expected visual without wiring up a Livewire request.
- **Reading-family widgets (`<x-wirekit::reading-spine>` / `<x-wirekit::reading-toc>` / `<x-wirekit::reading-minimap>` / `<x-wirekit::reading-progress>`) silently rendered empty when the developer-supplied `target` selector matched no element.** A typo'd id (`target="#summry"`) or a renamed ancestor class produced a blank spine / empty TOC / non-rendering minimap with no console signal — the developer had to reverse-engineer the silent miss from the DOM. Each widget now emits a single `console.warn` at init time when `target` is non-default AND `document.querySelector(target)` returns null, naming the offending selector and the component tag form (`Check the selector on <x-wirekit::reading-spine target="…" />`). The default `'main, article'` selector stays silent — a miss there is a page-shape concern rather than a developer typo. reading-progress is the special case: its default `target` is null (track viewport), so any supplied selector triggers the check.
- **`<x-wirekit-chart>` (or `<x-wirekit::chart-mixed>` / `<x-wirekit::chart-spark>`) crashed the whole page on a fresh install** with a `RuntimeException: Charts are disabled. Set 'charts.library' in config/wirekit.php …` because the package ships `'charts' => ['library' => null]` as the safe default (avoids forcing every developer into Chart.js installation). In `APP_DEBUG=true` the chart now renders a developer-visible placeholder div instead — names the missing config step inline (`Set 'charts.library' => 'chartjs' in config/wirekit.php, then npm install chart.js`) and preserves the developer-supplied `height` so layout stays stable when the developer fixes the config. In production (`APP_DEBUG=false`) the hard-throw stays — silent fallback would hide a real misconfiguration from end users. New `wirekit:doctor` check `checkChartUsageWithoutAdapter()` surfaces the same diagnostic at install time: WARN when any Blade file under `resources/views/` references the chart tag and `config('wirekit.charts.library')` is null. Both diagnostics ignore Blade-comment references (`{{-- <x-wirekit-chart> --}}`) so docs pages don't false-positive.
- **`wirekit:show <name>` printed `Docs: docs/components/<name>.md`** — but `/docs` is export-ignored from the published package, so the path never resolved on a developer install. The line now prints `Docs: https://docs.wirekit.app/components/<name>` (the live URL), matching what `wirekit:export-json`'s `docs_url` field already emitted everywhere else.
- **`<x-wirekit::chart-mixed>` silently dropped six props** that the underlying class-based chart delegate accepts: `wireStream`, `wireStreamMode`, `wireStreamCap`, `annotations`, `inline`, `replayable`. The anonymous-Blade wrapper only declared `labels`/`datasets`/`options`/`height`/`scope`, and Laravel's attribute-bag doesn't flow into a nested `<x-wirekit-chart>` tag unless the props are bound by name. Added all six to `chart-mixed`'s `@props` and forwarded via explicit `:wireStream="$wireStream"` etc. Existing callers pass through unchanged; mixed charts with `wire:stream` bindings or annotations now work as documented.
- **`wirekit:doctor` warned about font assets on every fresh install** because the package ships `'sans' => 'inter'` as the default config and the matching asset directory isn't auto-published. The warning read as if something failed; it's the natural state of a bare install. Now distinguishes the package-default font from a developer override — default + assets-not-published emits a new INFO hint (still suggests `vendor:publish --tag=wirekit-fonts` for self-hosting; system-ui fallback works without it); a developer override (e.g. `'sans' => 'roboto'`) + assets-not-published stays WARN because the requested font genuinely won't render until shipped.
- **`wirekit:doctor` printed two FAIL lines for missing `@wirekitStyles` / `@wirekitScripts` directives on a fresh Laravel 12 install** before the developer had a chance to add a layout file at the conventional path. The report read like the install broke when actually the next step is on the developer. The bare-install case (no `resources/views/components/layouts/*.blade.php`, no `resources/views/layouts/*.blade.php`, no `resources/views/components/layout.blade.php`, AND no `@import 'wirekit.css'` in `resources/css/app.css`) now emits a single INFO line — `Layout file not yet created — \`wirekit:install\` injects @wirekitStyles + @wirekitScripts on a re-run once you add …` — and skips the directive scan. Once any of those paths exists OR the `@import` alternative is configured, the scan runs normally and FAILs on missing directives in real misconfiguration scenarios.

## [2.1.1] — 2026-05-21

**Patch release.** Accessibility hardening sweep across every public docs page rendered on `docs.wirekit.app`. Targets the WCAG 2.2 AA contract WireKit promises in `README.md`. No API changes, no behavior changes, no token changes — every existing developer drops in `v2.1.1` and observes a stricter screen-reader / keyboard-reader contract with no migration work.

### Fixed

- **`/recipes/toolbar-filter-bar` filter controls had no accessible name.** The two `<x-wirekit::select>` filters and the search `<x-wirekit::input>` on the toolbar recipe page shipped without a `label`, `aria-label`, or wrapping `<x-wirekit::field>` — axe-core's `select-name` rule fires critical for that shape because screen readers announce each filter as "blank, combobox" with no semantic context. Added `aria-label="Filter by status"` / `aria-label="Filter by category"` / `aria-label="Search products"` to both the rendered preview AND the developer-facing source snippet so copy-paste users inherit the fix automatically.

## [2.1.0] — 2026-05-20

**Minor release.** Strict-by-default install hygiene, full pre-flight validation, token-clobber detection, plus five new install flags (`--no-strict`, `--force`, `--ignore-failed-flags`, `--diff`, `--rollback`) for explicit developer control over the install lifecycle. Public CSS API catalog (`/extending/public-css-api`) + content-edge spine contract (`/extending/spine-contract`) ship as the canonical AI-tooling discovery surfaces for `wk-*` classes and layout participation. New `<x-wirekit::spine-aware>` helper wrapper + `WireKit::spinePadding()` static let developer-authored components opt into the page-edge spine with one line. ALSO ships marketing-primitive composition ergonomics: brand-bar gains `container` + `max` props, hero/cta gain responsive `size` props with mobile-tightened vertical padding, hero's gradient overlay is contained to the content wrapper (no more empty-dark-area class of bug on mobile), footer gains a `max` prop replacing the prior hardcoded `xl` inner width. A new canonical recipe at `/recipes/marketing-landing-page` shows the full composition, and a new explainer at `/extending/composition-patterns` documents the chrome-vs-content axis as a design contract. Command-surface consistency: every "Unknown X" error now emits a uniform Levenshtein-ranked Did-you-mean hint, `wirekit:theme default` succeeds (and removes any existing preset block) instead of returning FAILURE, `wirekit:list --category=Marketing` lists the four canonical conversion primitives (cta, feature, feature-grid, hero), `wirekit:component my-button` derives `--base=button` automatically, `wirekit:doctor` becomes a Symfony alias of `wirekit:verify` (both names resolve to the SAME command instance), and `<x-wirekit::cta>` / `<x-wirekit::hero>` runtime prop validation reuses the same suggestion contract. **Two behavior changes** flagged below: `wirekit:install` now aborts (exit 2) on invalid flag values or token-clobber warnings, where v2.0.0 printed a warning and continued with exit 0; AND the `cta` / `feature` / `feature-grid` / `hero` components migrated from category `Display` to category `Marketing` — developers reading `wirekit:export-json` see the changed category strings. Developers who depended on the legacy "warn-and-proceed" semantics combine `--no-strict --ignore-failed-flags` for byte-stable v2.0.0 behavior. Every other change is additive.

### Added

- **Comprehensive mobile-viewport browser-test coverage across every category where viewport behavior matters.** Builds on the marketing-primitive mobile coverage with Tier-1 PR-gate `Tier1MobileGateTest.php` (6 cross-category representatives — hero, modal, sidebar, input, table, reading-spine) plus Tier-2 on-demand sweeps for **Overlays** (modal / drawer / popover / tooltip / dropdown / alert-dialog / command-palette / hover-card / context-menu), **Navigation** (navbar / sidebar / tabs / breadcrumb / stepper / pagination / menubar / navigation-menu / brand-bar + reading-toc hideBelow breakpoint test), **Forms** (input / select / combobox / textarea / checkbox / toggle / date-picker / time-picker / segmented-control / slider / file-upload / field — with WCAG 2.5.5 AAA 44×44 touch-target audit), **Reading family** (progress / spine / toc / minimap / bookmark / meta / shell), **Data display** (table / kanban / data-list / timeline / tree-view / stat / stats / sparkline / pagination / progress / ticker / price / calendar — with horizontal-overflow guard), AND a generic `ComponentPreviewMobileSweepTest` that scans every `docs/components/**` `:::preview` block at iPhone 14 Pro viewport. New shared `AssertsMobileViewport` trait centralizes the `matchMedia` override + console-error capture + scroll-settle scaffold so per-test boilerplate stays minimal.
- **`docs/extending/public-css-api.md` — canonical Public CSS API catalog.** Single source of truth for every `wk-*` class WireKit emits to developers — via `dist/wirekit.css` literal selectors AND via Blade-template static-string emissions. ~40 catalogd classes across four groups (Layout / chrome markers, Reading family, Animation / motion, Display / loading), each with a stability tier (Stable / Provisional / Internal-with-exception) and customization contract. The catalog enforces lockstep both directions — every shipped class has a row, every row references a real shipped class. Surfaces as machine-readable JSON via `wirekit:export-api-map`'s new `css-classes` group for AI tooling / IDE-extension consumption.
- **`docs/extending/spine-contract.md` — content-edge spine participation contract.** Documents the per-component inline-padding spine — which components join `--padding-wk-x-lg` (brand-bar, main, container, header, footer, cta, hero outer-only, spine-aware) and why others (sidebar, reading-toc, section's non-lg tiers) deliberately don't. Hero's "outer-yes / inner-no" asymmetry is now explicit; developers no longer measure the discrepancy by hand. Every documented participant carries a `// wirekit:spine-participant` marker comment in source so adding a new spine-aware component without the marker fails the build upstream.
- **`<x-wirekit::spine-aware>` — NEW opt-in spine wrapper for developer-authored components.** Pass any slot content; the outer `<div>` reads `--padding-wk-x-lg` so the content aligns with brand-bar / main / footer / etc. on the same vertical content edge. The `tier` prop (`sm` / `md` / `lg` / `xl`) reads a different `--padding-wk-x-*` tier when the developer wants to deliberately step off the spine. Helper-driven (uses the new `WireKit::spinePadding()` static) so a future spine-padding refactor propagates automatically.
- **`Pushery\WireKit\WireKit::spinePadding(string $tier = 'lg'): string` — NEW public helper.** Returns the canonical Tailwind utility string for the named padding tier (`px-[var(--padding-wk-x-{tier})]`). Use directly in developer-authored Blade class strings to opt INTO spine participation with one helper call — protects against tier typos AND gives the drift-audit guard a single canonical detection target. Tier values mirror the `--padding-wk-x-*` token family.
- **`wirekit:doctor --tier={package|environment}` filter.** Run only the package-tier checks (asset / config / directive / Tailwind-source / token-alignment / Alpine-cleanup) OR only the environment-tier checks (currently just compiled-view freshness). Default behavior unchanged — running without `--tier` walks every check. Useful for interactive dev sessions where the developer cares about only one tier's signal.
- **`wirekit:doctor` compiled-views-freshness check.** Detects when `resources/views/` mtimes exceed `storage/framework/views/` by ≥60 seconds (the canonical "your test asserts a new prop but the assertion fails" failure mode caused by Laravel's view-cache lag). Emits a WARN with the actionable `php artisan view:clear` fix, short-circuits the diagnostic chain to the real cause. Honors the 60-second buffer to avoid false-positives on fast file-edit cycles. Categorized under the new `environment` tier.
- **`wirekit:export-api-map` new `css-classes` group.** Machine-readable enumeration of every public `wk-*` class — same inventory as `docs/extending/public-css-api.md` table, one entry per class with `tier` + `docs_url`. AI tooling consumes via `php artisan wirekit:export-api-map --pretty | jq '.groups[] | select(.id == "css-classes")'` for autocomplete / type-checker awareness.
- **`--wk-stagger-step` token default in `:root {}`.** The CSS variable now ships with a `75ms` default in `dist/wirekit.css` (previously developers needed to set it via the prop OR risk a silent `0ms` cascade). Documented in `docs/theming.md` under the new "Motion tokens" subsection alongside the existing `--motion-wk-*` token family.
- **`Marketing` category in `Pushery\WireKit\ComponentRegistry`.** Four conversion-focused primitives (`cta`, `feature`, `feature-grid`, `hero`) migrate from category `Display` to category `Marketing`. `footer` stays `Layout` (structural primitive), `brand-bar` stays `Navigation` (header chrome), `reveal` stays `Display` (generic animation). `wirekit:list --category=Marketing` now exits 0 and prints the four primitives; the canonical category enum becomes eight values (`Form`, `Layout`, `Typography`, `Navigation`, `Overlay`, `Display`, `Marketing`, `System`). See [`/extending/component-registry#categories`](https://docs.wirekit.app/extending/component-registry#categories) for the per-category definition + usage criteria.
- **`Pushery\WireKit\Theming\ThemePresetRegistry`** — public single source of truth for every theme preset. `keys()` / `get(string $key)` / `all()` / `isValid(string $key)` / `isDefault(string $key)` cover read access; `register(string $key, array $preset)` lets downstream packages add custom presets at runtime from a service-provider boot hook. `wirekit:theme`, `wirekit:install --preset=`, and `wirekit:export-api-map themesGroup()` all read from this class — drift between their lists is impossible.
- **`Pushery\WireKit\Support\SuggestSimilar`** — public Levenshtein helper underpinning every CLI "Did you mean?" hint AND the runtime `WireKit::validateProp()` suggestion line. `byLevenshtein(string $needle, array $haystack, int $max = 3, int $maxDistance = 3)` returns up to N ranked candidates; `byLevenshteinScored(...)` exposes the distance scores for ranked-weighting developers (MCP servers, recently-used-bias suggestions); `format(array $suggestions)` pretty-prints the result. Cross-cutting use across `wirekit:show`, `wirekit:theme`, `wirekit:list`, `wirekit:publish-icons`, `wirekit:component`, `wirekit:install`, plus `WireKit::validateProp()`.
- **`wirekit:list --category=Marketing,Display` multi-category filter.** Comma-separated list returns the union of the per-category sets. Useful for "show me everything that could go on a landing page" queries.
- **`wirekit:list --format=...` alias for `--as=...`.** Matches the `--format=json` convention used by other Laravel commands. Both flags accept the same value set (`count|slugs|categories|json`) and resolve to the same code path; passing both simultaneously with different values is rejected.
- **`wirekit:component --interactive` flag.** Forces the `--base` chooser prompt even when TTY detection misfires (Herd / Docker / WSL). Pairs with the new derivation chain so `wirekit:component customer-dashboard` walks: right-segment strip → Levenshtein suggestion → interactive choice() prompt → fail-fast with actionable error when no candidate matches.
- **`<x-wirekit::brand-bar>` `container` + `max` props.** When `container=true`, the brand-bar's CHROME (background, border, sticky behavior) stays edge-to-edge while CONTENT (brand, tagline, actions) aligns with the body's container-wrapped column via the inner max-width wrapper. `max` accepts the six-value container enum (`sm/md/lg/xl/2xl/full`), default `xl`. Reads `--size-wk-container-*` tokens so brand-bar + body align on the same vertical content-edge spine. Default `container=false` preserves the v2.0.0 edge-to-edge render.
- **`<x-wirekit::navbar>` `container` + `max` props.** Same shape as brand-bar's pair — when `container=true`, the navbar's outer `<nav>` (background, border, sticky behavior) stays viewport-edge-to-viewport-edge while the inner flex row (brand, nav items, actions) AND the mobile-menu panel wrap to `max-w-[var(--size-wk-container-{tier})]` + `mx-auto`. `max` accepts `sm/md/lg/xl/2xl/full`, defaults to `xl`. Lets a marketing-landing-page navbar align its brand + nav items on the same content-edge spine as the body below without losing the edge-to-edge chrome. Default `container=false` preserves the v2.0.0 full-bleed content render.
- **`<x-wirekit::hero>` `size` prop + responsive vertical padding.** `size="sm|md|lg"` (default `lg`) names the `sm+` tier of `--space-wk-section-*`; the mobile viewport (< sm breakpoint) automatically drops one tier so the section never overshoots a small viewport. Closes the historical "14 rem vertical padding on every viewport" footgun without breaking the default `lg` semantic on tablet and up.
- **`<x-wirekit::hero>` gradient overlay containment.** When `gradient` is set, the overlay is now anchored INSIDE the content wrapper (max-w-xl) instead of as a direct child of the `<section>`. The previous shape filled the entire section including the vertical-padding band, producing a visible-empty-dark-area below content on mobile under `variant="dark"` + `gradient`. The overlay carries `pointer-events-none` so it never intercepts clicks.
- **`<x-wirekit::cta>` `size` prop.** Same shape as hero's `size` — three-tier responsive vertical padding (default `md`). Mobile drops one tier automatically.
- **`<x-wirekit::footer>` `max` prop.** Replaces the prior hardcoded `xl` inner-content max-width. Same six-value enum as brand-bar / container. All three inner wrappers (columns grid, brand+legal row, default slot) read from a single shared value — set `max` once and every wrapper updates. Default `xl`. Hardcoded fallback values in `var(--token, fallback)` pairs are removed (per user direction "kein hardcoded") — the tokens ARE the source of truth.
- **Two new documentation pages** under the "Extending" + "Recipes" sidebar groups: [`Composition Patterns — Chrome vs. Content`](https://docs.wirekit.app/extending/composition-patterns) (canonical design contract explainer for the chrome-vs-content composition axis across every page-edge primitive) and [`Marketing Landing Page`](https://docs.wirekit.app/blueprints/recipes/marketing-landing-page) (copy-paste-ready worked example composing brand-bar + hero + feature-grid + stats + cta + footer with full visual alignment on the same content-edge spine).
- **`wirekit:install --diff` dry-run mode.** Reports what WOULD change in `resources/css/app.css`, the layout file, `config/wirekit.php`, and `public/vendor/wirekit/` without writing any files. Read-only preview. Exits 0 after rendering. Use to preview an install before committing — pairs with code-review workflows.
- **`wirekit:install --rollback` mode.** Reverses the most-recent install session by replaying recorded before-snapshots from `.wirekit-install.log` at the developer project root. Per-file restore. Returns 0 on full success, 1 on partial restore. Mutually exclusive with every other install flag.
- **`wirekit:install --no-strict` opt-out flag.** Pre-flight warnings print but do not abort. Use only for legacy CI scripts that depended on the v2.0.0 warnings-as-success semantic. Mutually exclusive with `--force`.
- **`wirekit:install --force` flag.** Bypasses pre-flight warnings (token clobber, hand-edited marker blocks). Errors still abort. Use only when you understand the consequences. Mutually exclusive with `--no-strict`.
- **`wirekit:install --ignore-failed-flags` flag.** Per-flag failures report but do not abort the install. Other flags still apply. Exit code reflects partial failure (non-zero so CI still detects it). Requires `--no-strict` (since strict-default would abort first).
- **`.wirekit-install.log` written by `wirekit:install` on success.** Append-only JSON-Lines audit trail at the developer project root. Each tracked side-effect records its file path + before-snapshot. Enables `--rollback`. Failed installs discard pending entries (don't pollute the log with partial state).
- **Token-clobber scan** during pre-flight validation. When a `--font*` flag is set AND `resources/css/app.css` already declares the matching `--font-wk-{category}` token OUTSIDE the WireKit marker block, the install aborts (or warns under `--no-strict`/`--force`) with an actionable error message naming the conflicting declaration's line + value. Closes a long-standing silent-overwrite footgun where developer-set tokens lost cleanly to WireKit's marker block on cascade order.
- **Pre-flight validation pass** in `wirekit:install`. Every flag is validated BEFORE any side-effect runs. Errors are aggregated in one pass — the user sees the full picture in one round-trip, not one error at a time. Filesystem is byte-stable across a failed validation.
- **CI / Deploy script discipline** section in `docs/integration.md` — canonical fail-fast install shape, exit-code reference, GitHub Actions example, legacy opt-out recipe, dry-run preview pattern, rollback workflow.
- **`wirekit:doctor` cleanup-hygiene check for custom Alpine plugins.** Static-analysis scan of the developer's `resources/js/` tree that flags two anti-patterns: an `IntersectionObserver` / `MutationObserver` / `ResizeObserver` instantiation without a `destroy()` cleanup hook, and a `disconnect()` call inside an observer callback without a preceding null-guard against post-teardown fire. Heuristic-based — emits a soft warning with a docs cross-link and respects a `// wirekit-doctor: cleanup-ok` opt-out comment for intentional patterns the heuristic doesn't recognize.
- **Three new documentation pages** under the "Extending" sidebar group: [`Authoring Custom Alpine Plugins`](https://docs.wirekit.app/extending/authoring-custom-alpine-plugins) (defensive-cleanup pattern + worked example for developer-authored Alpine plugins), [`Pest Browser-Test Setup`](https://docs.wirekit.app/extending/pest-browser-setup) (recipe for wiring `pestphp/pest-plugin-browser` into a WireKit-using Laravel app, including known-issue workarounds), and [`Console Output Baseline`](https://docs.wirekit.app/extending/console-output-baseline) (catalog of what a clean WireKit install should emit on first paint, during interaction, and from optional-dependency warnings — helps developers triage unexpected output).
- **`Pushery\WireKit\Support\BladeParser`** — companion to `PropsParser` for the broader Blade-content surface (named slots, directive enumeration, comment extraction, component-reference discovery). Replaces the inline slot-detection regex previously embedded in `ExportJsonCommand`.
- **`wirekit:list --as=count|json|slugs|categories`** flag emits machine-readable output for script + AI consumption. `--as=count` produces a single integer with no decoration (`WK_COUNT=$(php artisan wirekit:list --as=count)`), `--as=slugs` newline-separated component names, `--as=categories` a JSON object mapping each category to its component count, `--as=json` the full per-component array with name + tag + category + description. Default human-readable output unchanged.
- **`wirekit:show <name> --as=json`** flag emits the per-component structured schema as JSON to stdout (no decoration). Includes `name`, `tag`, `category`, `description`, `docs_url`, `props[]` (full prop records with `default`, `default_normalized`, `comment`), and `sub_components[]`. Tooling that needs one component's schema without loading the full export-json output.
- **`wirekit:show <name> --validate-against=<path>`** flag lints a developer Blade file against the component's known prop set. Walks every `<x-wirekit::name ...>` usage, warns on unknown attributes with the closest matching prop name (Levenshtein-ranked) so typos like `intnet` → `intent` surface immediately. Pre-runtime catch of "did I typo a prop?". Exits 1 on any unknown attribute — wire into pre-commit hooks for prop-spelling enforcement.
- **`.wirekit-schema.json` written by `wirekit:install`.** Drop-in IDE-extension + AI-tool feeder file at the developer's project root. Contains the same JSON manifest as `wirekit:export-json --pretty` (every component's name + tag + category + description + full prop records + slots). Re-running `wirekit:install` regenerates the file — checked into git for stable autocomplete across teammates, or gitignored to keep installs fresh. Non-fatal on write-failure (read-only filesystem etc.).
- **Two new documentation pages** under the "Extending" sidebar group: [`ComponentRegistry — Programmatic Discovery`](https://docs.wirekit.app/extending/component-registry) (canonical PHP-API for reading the component catalog at runtime) and [`Authoring Custom Blade Components`](https://docs.wirekit.app/extending/authoring-custom-components) (eight tested `@props` syntax shapes WireKit's tooling understands, plus CLI tooling for custom components).

### Changed

- **`wirekit:install` is now strict-by-default.** Invalid font keys, category mismatches, unknown preset names, invalid `--apex-license` tiers, AND detected token-clobbers all abort with exit 2 (INVALID) before any filesystem mutation. **v2.0.0 BEHAVIOR CHANGE.** Previously these conditions printed a warning to stderr and the install continued with exit 0 — a silent failure mode that masked install bugs from CI. To preserve the legacy semantic, combine `--no-strict` (lets pre-flight warnings pass) + `--ignore-failed-flags` (lets per-flag exceptions pass).
- **`wirekit:install` exit codes are now precise:** `0` = success, `1` = runtime failure mid-install (verify failed, theme application failed, partial rollback), `2` = pre-flight validation rejected the input (no filesystem mutations happened). Wire these into CI as documented in [docs/integration.md "CI / Deploy script discipline"](https://docs.wirekit.app/getting-started/integration#ci--deploy-script-discipline).
- **Four components recategorised from `Display` to `Marketing`.** `cta`, `feature`, `feature-grid`, and `hero` carry their conversion-funnel intent in the category field now. **API-VISIBLE for developers reading `wirekit:export-json` / `wirekit:list --as=json` / `Pushery\WireKit\ComponentRegistry::all()`** — the four entries' `category` field changed value. Rendering, props, slots, and tags unchanged; no other API surface affected.
- **`wirekit:doctor` is now a Symfony alias of `wirekit:verify`.** Both names resolve to the SAME command instance — running either walks the identical check pipeline. `php artisan list wirekit` still shows two rows (the alias appears with an `[wirekit:doctor]` annotation on the verify row), but the standalone `DoctorCommand` class file is removed and both invocations share one underlying handler. **Internal API change** for developers who imported `Pushery\WireKit\Console\DoctorCommand` directly in a custom service provider's command-registration array — switch to `VerifyInstallationCommand` (which now declares `wirekit:doctor` via `setAliases()`).
- **`Pushery\WireKit\ComponentRegistry::extractProps()` return shape.** Was `array<string, string>` (flat name → default-string map). Now returns the structured `list<array{name, default, default_normalized, type_hint, comment}>` shape produced by `PropsParser`. **BREAKING for developers of the prior shape.** Migration: iterate the returned list, read each entry's `name` / `default` keys, and the inline-comment metadata (previously hidden) is now available via the `comment` field. No back-compat shim — the structured shape is the canonical surface going forward.

### Fixed

- **Silent exit-code lie in `wirekit:install --font=<invalid-key>`.** Previously the catch-block printed `✗ Unknown font key '...'` to stderr but did NOT propagate a non-zero exit code; the install continued through publishing / theming / verification and returned `self::SUCCESS`. CI scripts wrapped in `set -e` saw exit 0 and continued. Now pre-flight aborts immediately with exit 2 and the actionable error message.
- **`wirekit:theme default` returned FAILURE.** The `default` preset wasn't in `ThemeCommand`'s inline PRESETS array, so running `wirekit:theme default` exited 1 with "Unknown preset" — despite `wirekit:install --preset=default` being a valid no-op. Symmetry restored: `wirekit:theme default` now exits 0, actively removes any existing `wirekit:theme start/end` block from `app.css` (returning to the bundled token values), and emits an actionable status line either way.
- **`wirekit:show buttn` (and every other "Unknown X" error) had unreliable suggestions.** The historical `str_contains` bidirectional match in `ShowComponentCommand` produced suggestions only when the typo's characters were a substring of a real name (or vice versa). Single-char typos like `buttn` (distance 1 from `button`) never matched. The new `Pushery\WireKit\Support\SuggestSimilar::byLevenshtein` covers every "Unknown X" report uniformly across `wirekit:show`, `wirekit:theme`, `wirekit:list`, `wirekit:publish-icons`, `wirekit:component`, AND `WireKit::validateProp()` runtime messages.
- **`wirekit:component <name>` defaulted `--base` to `<name>` itself.** When `--base` wasn't passed, the default pointed at the new component's own (non-existent) source — every invocation without explicit `--base` failed with "Unknown base component". The new derivation chain (right-segment strip → Levenshtein fallback → interactive prompt → fail-fast with actionable error) handles the common `my-X → X` pattern automatically (e.g. `wirekit:component my-button` derives `--base=button` and copies `button.blade.php`).
- **Two regex-based `@props([...])` parsers consolidated into a single tokenizer-backed `Pushery\WireKit\Support\PropsParser`.** The historical parsers (`ComponentRegistry::extractProps` and `Console\ExportJsonCommand::extractProps`) silently truncated prop defaults that contained commas inside function-call argument lists (`config('x.y', null)` was split into two phantom props) AND leaked trailing `// comment` blocks into the captured default value. Both bug classes broke `wirekit:show` output AND the `wirekit:export-json` manifest — IDE tooling and MCP servers reading the export got incomplete prop catalogs. The replacement uses PHP's own `token_get_all()` tokenizer and handles every legitimate `@props` shape: `config(...)` defaults, multi-line array literals, `match(...)` expressions, mixed-quote keys, heredoc / nowdoc values. New regex-based `@props` parsers in `src/` are blocked from being added going forward.
- **Unguarded observer callbacks in `wirekitStatAnimate` and `wirekitAnimate` Alpine plugins.** Browser-queued `IntersectionObserver` callbacks could fire AFTER Alpine teardown set `this._observer = null` (Livewire morph removing the host element pre-intersection is the canonical trigger), causing `TypeError: Cannot read properties of null (reading 'disconnect')` to log to the console. The page rendered correctly, but the silent error reded every developer's `assertNoSmoke()` / `assertNoJavascriptErrors()` browser-test against any WireKit page using `<x-wirekit::stat animate>` or `<x-wirekit::reveal>`. The fix adds a null-guard immediately before the in-callback `disconnect()` call in both plugins plus the `reading-minimap` initialization `IntersectionObserver` that shared the bug shape. Sweep audit of the remaining observer instantiations across `reading-spine`, `reading-toc`, `reading-minimap` (4 sites), `chart`, and `chart-apex` confirmed they don't access the observer reference inside the callback, so no race exists.

## [2.0.0] — 2026-05-18

**Major release.** First publicly-cut tag after v1.6.3, aggregating every change since. Two breaking changes (both in `### Changed`): custom `Pushery\WireKit\Contracts\ChartAdapter` implementations must add three new methods to satisfy the expanded interface, AND `<x-wirekit::container>` inline-padding tiers now read from the `--padding-wk-x-*` token family instead of `--space-wk-*` so a container nested in `<x-wirekit::main>` inherits the same content-edge spine. Developers using only the built-in `ChartJsAdapter` or the new `ApexChartsAdapter`, and developers using `<x-wirekit::container>` with default padding values, see no behavioral change beyond a 0.5-rem horizontal alignment correction. Every other change is additive and back-compatible.

### Added

- **Optional ApexCharts chart adapter alongside the existing Chart.js adapter.** Switch with one line in `config/wirekit.php`: `'charts' => ['library' => 'apexcharts']`. Same `<x-wirekit::chart>` Blade tag for both libraries; zero breaking change. ApexCharts unlocks 9 chart types Chart.js does not ship natively — candlestick, boxplot, range-bar, range-area, heatmap, treemap, funnel, radial-bar, sparkline. Dedicated `dist/wirekit-apex.js` adapter bundle (~2 KB gzip glue, no ApexCharts library code; the developer installs `apexcharts` via npm). **Note: ApexCharts is not MIT-licensed — the free Community License covers organizations under $2M USD annual revenue; Commercial License required above. WireKit ships only the adapter glue (MIT). See [docs/components/chart.md#license-apexcharts-only](https://docs.wirekit.app/components/chart) for the full terms.**
- **`<x-wirekit::sparkline>` — NEW first-class component.** Inline trend sparkline (axis-less line chart) for KPI strips and dashboard cells. Six props (`data` / `trend` / `inline` / `height` / `scope`). Auto-detects trend from first-vs-last data and tints green / red / muted accordingly; manual `trend="up|down|neutral"` override available. Inline mode renders at surrounding text height (1.25em / 4rem width); block mode at configurable height (default 2.5rem). Delegates to `<x-wirekit::chart type="sparkline">` so both adapters render correctly — ApexCharts uses native sparkline mode, Chart.js falls back to a plain line.
- **`<x-wirekit::chart-mixed>` — NEW component for multi-axis dashboards.** Each dataset declares its own `type` (line / bar / column / area) plus an optional `yAxisID` for multi-axis configurations. Both adapters consume the per-dataset type field natively — Chart.js via the per-dataset `type` field, ApexCharts via `series[].type`.
- **`wireStream` prop on `<x-wirekit::chart>` for real-time data updates.** Subscribe a chart to a Livewire-emitted event and append each fired payload via the library's imperative API (`chart.update('none')` for Chart.js / `chart.appendData()` for ApexCharts). `wireStreamMode="strict"` (default) FIFO-trims at `wireStreamCap` points (default 100); `wireStreamMode="stream"` grows unbounded.
- **`annotations` prop on `<x-wirekit::chart>` for vertical lines / horizontal regions / point callouts.** ApexCharts has annotations built-in; Chart.js requires `chartjs-plugin-annotation` (the Alpine factory emits a console.warn when annotations are supplied but the plugin is missing — graceful degradation).
- **Smooth dark-mode preset transitions on chart re-theming** (~250 ms ease-out). Both adapters interpolate colors during `.dark` toggle instead of snapping. Collapsed to instant under `prefers-reduced-motion: reduce`.
- **Two new chart-system docs subhierarchies on docs.wirekit.app:** `/components/charts-chartjs/` (full Chart.js demo set — bar / line / area / pie-doughnut / scatter-bubble / radar-polar / advanced / theming) and `/components/charts-apex/` (full ApexCharts demo set — line / area / bar / column / range-bar / pie-donut / radial-bar / radar / scatter-bubble / heatmap / treemap / candlestick / boxplot / funnel / timeline / sparklines / annotations / streaming / mixed / motion / theming). Every page ships realistic seed data drawn from B2B SaaS, e-commerce, DevOps, finance, marketing domains.
- **`wirekit:install --apex-license=community|commercial|oem` flag.** Records the developer's license tier into `config/wirekit.php` `charts.apex_license` after printing the License Notice once at install time. Suppresses the `wirekit:doctor` reminder for `commercial` and `oem` tiers.
- **`wirekit:doctor` ApexCharts checks.** Detects `apexcharts` npm package presence, validates the active license tier, confirms `dist/wirekit-apex.js` is published.
- **`wirekit-apex.js` registered as publishable** under both the `wirekit-scripts` and `wirekit-assets` tags. Running `php artisan vendor:publish --tag=wirekit-assets` now copies the adapter glue to `public/vendor/wirekit/wirekit-apex.js` alongside the other JS bundles. The route fallback at `/wirekit/wirekit-apex.js` also works for setups that prefer route-based serving over publishing. Eliminates the manual `cp vendor/.../dist/wirekit-apex.js public/vendor/wirekit/` step that ApexCharts adopters previously had to run after every `composer update`.
- **`<x-wirekit::reading-toc>` — NEW primitive in the reading family.** Horizontal sticky-strip TOC sibling to `reading-spine`. Same auto-build-from-headings + IntersectionObserver active-section model, different rendered shape (a flat row of links across the top or bottom of the article container). Use case: marketing landing pages with 3-4 anchored sections (Hero, Features, Pricing, FAQ) where a vertical sidebar feels excessive. Seven props (`target`, `levels`, `position`, `offset`, `hideBelow`, `flush`, `scope`). Defaults to `levels="2"` for landing-page flat structure; mobile-hidden via `hideBelow="sm"` since narrow viewports cannot host a horizontal strip without overflow. The `flush` prop zeroes the first link's left-edge padding and the last link's right-edge padding so the visible text aligns flush with the strip's content edges (use when the TOC sits directly under `<x-wirekit::brand-bar>` or `<x-wirekit::main>` and the developer wants the first link on the same vertical content-edge spine as the surrounding brand text and h2 headings). Real `<a href="#section-id">` links inside a `<nav aria-label="Page sections">` landmark — keyboard-navigable + screen-reader native. `prefers-reduced-motion: reduce` collapses smooth-scroll to instant. Reuses spine's `--reading-spine-color-idle` / `-active` for theme consistency; adds 5 layout-specific tokens (`--reading-toc-bg` / `-padding-y` / `-padding-x` / `-gap` / `-link-max-width`) plus 2 color aliases.
- **`<x-wirekit::brand-bar>` — NEW navigation primitive.** Page-chrome wrapper for the canonical "logo + tagline + actions" header pattern. Three named slots: `brand` (typically a `<x-wirekit::brand>` primitive), `tagline` (secondary muted text), `actions` (right-anchored sign-in / theme-toggle / account-widget content via `margin-left: auto`). Props: `as` (`header` | `nav`), `divider` (`bottom` | `none`), `padding` (`none`/`sm`/`md`/`lg`/`xl`), `sticky` (pins to `top: 0` during scroll with an opaque background fallback). Carries the content-edge spine via `padding="lg"` reading from `--padding-wk-x-lg`, so the brand visible-text aligns with `<x-wirekit::main padding="lg">`, `<x-wirekit::reading-toc flush>`, and the article h2 headings below on the same vertical X-coordinate — out of the box, no custom CSS.
- **`<x-wirekit::prose :density>` — NEW prop on the existing component.** `comfortable` (default) keeps the long-form-article heading scale (h2 mt = 2.5 rem for generous section breaks, h1 = 1.875 rem). `compact` tightens for marketing pages (h2 mt = 0.75 rem, h1 one type-scale tier smaller, p mb = 0.5 rem). Eliminates the need to override prose typography with developer-side `.my-section h2 { margin: ... }` rules when the prose lives inside a tight marketing layout. Default `comfortable` keeps backward-compatible rendering.
- **`<x-wirekit::reading-shell :toc="true">` — opt-in toc toggle.** Defaults to `false` in every density preset (comfortable / compact / minimal). Marketing landing pages explicitly opt in via `:toc="true" :spine="false"`; blog posts and docs pages keep the spine sidebar.
- **Marketing Landing TOC recipe** at `/recipes/marketing-landing-toc` — sibling to long-form-article. Demonstrates the canonical Hero / Features / Pricing / FAQ landing-page pattern with the new sticky TOC strip, plus the `offset` prop pattern for layouts with a fixed nav above the strip.
- **Long-form Article recipe** at `/recipes/long-form-article` — canonical Medium / Substack-style article layout: reading-progress bar at the top, reading-spine sidebar on the right, reading-bookmark resume pill, reading-meta time-to-read. Demonstrates the full reading-* family in one composition, both via the `<x-wirekit::reading-shell>` sugar wrapper and via direct primitive composition for power-user customization.
- **Documentation Reader recipe** at `/recipes/documentation-reader` — documentation-style article shell with reading-progress bar, dense-section reading-spine, fixed-nav offset on the TOC strip, reading-bookmark across multi-page-session reads. Shows the docs-rhythm pattern (heading-scale + dense per-paragraph code blocks) alongside the same reading-* primitives.
- **`<x-wirekit::reading-minimap>` — NEW primitive in the reading family.** Every-item density overview of a scrollable container, with two rendering modes:
  - `mode="stripes"` (default) — every item matched by `itemSelector` renders as a 1–2 px stripe at proportional vertical position. `itemStyle="block"` upgrades to per-paragraph rectangles whose height tracks the source item's natural height (skeleton-style content texture).
  - `mode="rendered"` — code-editor-style abstract content canvas. Walks every text-bearing node via `TreeWalker`, gets per-rendered-line rects via `Range.getClientRects()`, draws each as a rectangle on a DPR-aware canvas. Per-element-type color palette (h1–h6 each its own alpha tier, code indigo, table rose, blockquote slate-bolder, image amber, WireKit `wk-*` components emerald, prose / default slate-muted). 13 `--reading-minimap-color-{h1..h6,code,table,blockquote,prose,wirekit,image,default}` tokens for full re-theming without JS.
  - Translucent viewport-overlay rectangle tracks the host's visible region; click stripe → smooth-scroll target so item is centered (instant under `prefers-reduced-motion: reduce`); drag overlay → pan host scroll position with browser-scrollbar-matched translation; hover stripe (non-touch) → tooltip following the cursor with the item label.
  - Two canonical use cases: long-form article density-overview (sibling to `reading-spine`) and sidebar navigation density-overview.
  - 5000-tag silent fallback to stripe mode on very long articles. No DOM clone in rendered mode → no parse step → no surface for HTML-injection vectors.
- **`<x-wirekit::reading-meta perParagraph>` — Medium-style inline annotations.** Opt-in mode that injects small `<span class="wk-reading-meta-paragraph">N min</span>` annotations immediately before each `<p>` in the target with at least `paragraphMinWords` words (default 30). Annotations show estimated remaining-time FROM that paragraph onward, re-computed on scroll. `aria-hidden="true"` on every annotation — canonical SR text remains the total/remaining display. Default off; opt-in.
- **`<x-wirekit::reading-progress variant="auto">` — theme-reactive fill mode.** Falls back to `currentColor` when the developer hasn't set `--reading-progress-fill` — useful for embedded contexts (iframes, browser extensions) where the bar should match the surrounding text color. Joins the canonical 6-value variant set (`primary | neutral | success | warning | danger | info`).
- **`<x-wirekit::reading-shell density>` preset prop.** Three values (`comfortable` | `compact` | `minimal`) adjust the shell's per-primitive defaults: progress-bar height, spine expand mode, which primitives render by default. Per-primitive toggles (`:spine="false"` etc.) win over the density preset.
- **14 new design tokens for the reading-* family**: 1 on `reading-progress` (`--reading-progress-fill`), 7 on the new `reading-minimap` (width, stripe-height, stripe-gap, color-idle, color-active, viewport-bg, viewport-border), 2 on `reading-meta` perParagraph mode (paragraph-color, paragraph-spacing). All themeable in `:root {}`.
- **`<x-wirekit::stat>` composes `animate` (counter count-up) with `animateIn` (entrance reveal).** The Blade template emits an outer wrapper that carries the entrance reveal while the inner element keeps the counter handler — the two scopes layer cleanly. Single-flag usages render byte-identical.
- **`<x-wirekit::cta>` and `<x-wirekit::footer>` accept the `animateIn` prop.** Both marketing primitives now match the existing `animateIn` surface on `<x-wirekit::card>`, `<x-wirekit::feature>`, `<x-wirekit::hero>`, and others. Passing `animateIn="slide-up"` (or any of the 11 base presets) wires `<x-wirekit::reveal>` semantics inline without an extra wrapper element. Honors `prefers-reduced-motion: reduce`.
- **`stagger` prop on `<x-wirekit::feature-grid>` and `<x-wirekit::stats>`.** When set on the wrapper and an entrance preset is configured on the children, each child's animation fires with an incremental delay — produces a clean cascade instead of every card landing at once. Boolean form uses a 75ms step (`stagger`); integer form overrides for custom rhythm (`:stagger="125"`). Pure CSS via `:nth-child` rules with an index cap at 8 to bound delay on long lists; collapses to 0 under reduced motion. Zero JS bundle impact.
- **`delay` prop on `<x-wirekit::reveal>`.** Holds the entrance for a beat before it begins — useful for sequencing cards or letting a hero-section heading land before the supporting copy follows. Accepts five named tokens (`none`, `sm`, `md`, `lg`, `xl`) mapping to themeable CSS variables, or a raw integer in milliseconds for one-offs. Composed via inline `animation-delay` on the existing `wk-animate-{preset}` class — no JS plugin change needed. Collapses to `0ms` under `prefers-reduced-motion: reduce`.
- **Five new motion-delay tokens in `dist/wirekit.css`:** `--motion-wk-delay-none` (`0ms`), `--motion-wk-delay-sm` (`75ms`), `--motion-wk-delay-md` (`150ms`), `--motion-wk-delay-lg` (`300ms`), `--motion-wk-delay-xl` (`500ms`). All developer-overridable in `:root {}`.
- **CSS class `.wk-stagger`** plus eight `:nth-child` rules and a cap-at-8 index ceiling. Drives the `stagger` prop on feature-grid and stats. Reduced-motion override zeroes per-child delays inside the existing `@media (prefers-reduced-motion: reduce)` block.
- **`wirekit:doctor` thirteenth check — `:root` vs `.dark` color-token symmetry.** The doctor now reads the developer's `resources/css/app.css`, extracts the `:root {}` and `.dark {}` blocks, and emits a warning when `--color-wk-*` tokens are declared in one block but missing from the other. Asymmetric color tokens produce theme drift the developer sees as "looks wrong in dark mode" without an obvious cause.
- **`<x-wirekit::reading-progress>`** — viewport-pinned reading-progress indicator for long-form articles. Bar (default) or dot (`indicator="dot"`) variants; both fill 0 → 100% on scroll using compositor-only properties (`transform: scaleX` / `stroke-dasharray`). Five `variant` colors, three `height` tokens (`sm`/2px, `md`/3px, `lg`/5px), `showAfter` scroll threshold, `target=` selector, `segments` chapter markers, `milestones` events (`wirekit:reading-progress:milestone` Alpine dispatch fired ONCE per session at 25 / 50 / 75 / 100% boundaries). `role="progressbar"` + dynamic `aria-valuenow`. Zero-KB bundle impact.
- **`<x-wirekit::reading-spine>`** — sidebar mini-TOC that auto-builds from page headings. Pins to the right edge at md+ breakpoints; tracks scroll position via `IntersectionObserver`; expands on hover or focus. Configurable `target=`, `levels=`, `position=`, `expand=` (`hover` / `focus` / `always` / `always-md`), `offset=`. Real anchor links so navigation works without JS. Five opt-in extensions: `numbered`, `fillSections`, `backToTop`, `expand="always-md"`, and a `wirekit:reading-spine:section-changed` event. Filter slot composition pattern. Bundle impact: ~600 bytes gzip.
- **`<x-wirekit::reading-bookmark>`** — persists scroll position to `localStorage` while reading; surfaces a "Resume reading where you left off?" pill on return-visit when conditions are met (previous-session dwell time ≥ `minDwellSeconds`, scroll moved past `threshold * scrollHeight`). Cross-tab consistency via `storage` event. `try/catch` wrapping every storage op — silently degrades on private-browsing / quota-exceeded. Required `key` prop (typically `"article-{slug}"`).
- **`<x-wirekit::reading-meta>`** — small text element showing `~12 min read` (initial estimate) and optionally `~5 min remaining` (live-tracking on scroll). Skips non-prose nodes (`pre`, `code`, `figure`, `figcaption`, `img`, `picture`, `svg`, `[data-language]`). CJK-aware: when more than 40% of text is CJK ideographs, falls back to character-based estimation with a configurable `cjkCharsPerMinute` baseline (default 500). `wpm` clamps to `≥ 50`; `totalMinutes` floors to 1. `role="status" aria-live="polite"`.
- **`<x-wirekit::reading-shell>`** — composition wrapper that renders `<x-wirekit::reading-progress>` + `<x-wirekit::reading-spine>` + `<x-wirekit::reading-bookmark>` + `<x-wirekit::reading-minimap>` + `<x-wirekit::reading-meta>` around the slot content in one tag. Mirrors the `<x-wirekit::app-shell>` "one tag, full UX" pattern. Per-component opt-out via `:progress="false"` etc. Forwards every documented child prop via flat surface.
- **CSS variable tokens for the reading-* family in `dist/wirekit.css`:** three height tokens for the bar (`--reading-progress-height-{sm,md,lg}`), the dot diameter (`--reading-progress-dot-size`), seven spine layout / color tokens, three bookmark pill tokens, two reading-meta tokens.
- **`@media print { display: none !important }`** rule for every reading-* primitive — only the article body prints, not the reading chrome.
- **`prefers-reduced-motion: reduce` gating for the entire reading-* family** in the global `@media (prefers-reduced-motion: reduce)` block.
- **`<x-wirekit::replay-button>` — NEW companion primitive.** Renders an icon-button that walks the closest `[data-replay-target]` ancestor and re-mounts it (`Alpine.initTree`) so its `x-data="wirekitAnimate(...)"` / counter / chart-motion fires again. Standardizes the "re-play this animation" affordance every animation-capable component carries via the `data-replayable="true"` contract — developers can place the button explicitly anywhere alongside a primitive, or rely on docs.wirekit.app's preview chrome auto-injecting it. Two props: `label` (button `aria-label`, default `"Replay"`), `scope` (named personalization scope).
- **`replayable` prop on `<x-wirekit-chart>`.** Opt INTO the docs.wirekit.app `↻ Replay` button surface by emitting `data-replayable="true"` on the chart root. Set it explicitly when the entrance animation is worth re-watching (bar-grow / line-trace / slice-sweep on `/components/charts-apex/motion`), or rely on the auto-detect path: whenever `wireStream` is bound (every streaming chart on `/components/charts-apex/streaming`), the attribute is emitted automatically. Back-compat preserved: callers who already passed `data-replayable` raw via the attribute bag continue to work — the Blade view skips its own emission to avoid duplication.

### Changed

- **`Pushery\WireKit\Contracts\ChartAdapter` interface expanded from 4 → 7 methods.** Net-new: `name()` / `rendersTo()` / `supportedTypes()`. Existing `scripts()` / `normalizeData()` / `defaultOptions()` / `alpineComponent()` unchanged. Developers using only built-in adapters see no impact (both `ChartJsAdapter` and `ApexChartsAdapter` ship the new methods). Custom adapter implementations need to add the three new methods to satisfy the interface. **BREAKING for developers with a hand-written `ChartAdapter` implementation.**
- **`<x-wirekit::container>` inline-padding moved from `--space-wk-*` to `--padding-wk-x-*`.** `padding="sm|md|lg|xl"` now reads from the `--padding-wk-x-*` content-edge spine instead of the `--space-wk-*` block-rhythm scale. Resolves a silent 0.5 rem horizontal drift when nesting `<x-wirekit::container padding="lg">` inside `<x-wirekit::main padding="lg">` — both now share the same content-edge X-coordinate. Developers who depended on the wider previous value (e.g. `padding="lg"` was 1.5 rem, now 1 rem) should bump to `padding="xl"` (also 1.5 rem) for unchanged visual output. **BREAKING for developers relying on the exact pixel value of container's horizontal padding.**
- **`<x-wirekit::sidebar>` inline-padding fixed.** Was applying `--padding-wk-y-sm` (the y-axis token) on all four edges; now correctly splits into `px-[var(--padding-wk-x-sm)] py-[var(--padding-wk-y-sm)]` and moves the inter-item `gap` to `--space-wk-sm` (the canonical gap token). Sidebar items at the inboard edge now align with `<x-wirekit::header>` and `<x-wirekit::main>` rendered alongside on the same vertical content-edge spine. Back-compat: visual change is a few-pixel adjustment to the sidebar's internal padding; no API change.
- **Color-prop unification across `<x-wirekit::message>`, `<x-wirekit::alert>`, `<x-wirekit::callout>`, and `<x-wirekit::progress>`.** Every component that exposes a color prop now accepts the same canonical six-value set (`primary, neutral, success, warning, danger, info`). `progress` adopts `primary` as the canonical name; the historical `accent` value continues to work as a back-compat alias. New [Variants & Intents](https://docs.wirekit.app/variants-and-intents) page documents the canonical set, the per-component prop tables, and the visual-synonym semantics in one place.
- **Bundle size**: `dist/wirekit.js` grew from 25 → 26 KB gzip (83 → 86 KB raw) covering the new reading-* family and chart-adapter expansion. Stays within the ±2 KB drift budget per `docs/dependencies.md`. Core bundle (chart-only) unchanged at ~2 KB gzip / 11 KB raw. ESM bundle ~25 KB gzip / 82 KB raw.
- **`.wk-reading-toc__list` inline-padding ships without `!important`.** Developers running a typography prose-wrapper (e.g. a `@tailwindcss/typography` body or any `body.prose`-style class) should carve out `[class*="wk-"]` from their `ul/ol/li` rules — same pattern they already use for the `max-width: 75ch` typography clamp. Without the carve-out the `padding-left: 1.75rem` in a typical prose stylesheet wins on specificity over `.wk-reading-toc__list { padding-inline: 0.25rem }` and pushes WireKit list-based components (reading-toc, list, breadcrumb) off the content-edge spine. Recommended typography rule shape: `.prose ul:not([class*="wk-"]), .prose ol:not([class*="wk-"]) { padding-left: 1.75rem }`.

### Documentation

- **`docs/integration.md` — new "Performance — Conditional Asset Loading" section** documenting a server-side flag pattern for emitting `highlight.js` and `Chart.js` `<script>` tags only on pages that actually use `<x-wirekit::code-block>` or `<x-wirekit::chart>`. Saves ~95 KB gzip on lightweight routes (landing pages, theming guide, recipes without code/charts) without breaking caching or first-paint correctness. Pattern is documentation-only; no helper class shipped — adapt the `str_contains()` detection to your DocsParser / class-naming conventions.
- **`/theming/design-tokens` — NEW comprehensive reference page** for every WireKit CSS variable: colors, typography, motion, sizing, component-specific tokens, and the chart-theming palette. Single landing page where developers can find any token by name before reaching for its component's docs page.
- **`/getting-started/livewire-starter-kit` — NEW recipe page** for fitting WireKit into an existing Livewire Starter Kit project. Walks the four touchpoints where Starter-Kit defaults differ from a clean-room `wirekit:install` (theme preset, font stack, dark-mode flip, sidebar conflict resolution) so existing Starter-Kit developers don't have to discover them ad-hoc.

---

## [1.6.3] — 2026-04-30

Patch release covering one developer-facing dependency fix, one CI build-stability fix, and a README link-table cleanup. No new API surface; no breaking changes.

### Fixed

- **`livewire/livewire` is now a direct require, not a require-dev.** Running `composer require pushery/wirekit` previously installed the package without pulling Livewire — developers who hadn't already added Livewire to their project then ran `php artisan wirekit:install` and hit confusing "class not found" errors at first component render. Livewire 4+ is a stated minimum requirement (per the README's stack badges and the integration guide); composer.json now reflects that contract and pulls Livewire into the developer's project automatically. Existing developers who already have Livewire in their `composer.json` see no change — composer will just keep their pinned version. Net effect: zero-step Livewire setup for new installs.

- **CI markdown lint stabilized against `markdownlint-cli2` minor-version drift.** The CI workflow runs `npx markdownlint-cli2` which always pulls the latest published version, while the local dev environment was pinned to `^0.21.0`. When `0.22.1` shipped a stricter `MD038` rule interpretation, CI started failing on a markdown file that the local tooling considered clean. Bumped the local pin to `^0.22.1` so local + CI run the same lint version, and fixed the lingering `MD038` violation surfaced by the new rule.

- **README documentation links no longer 404.** The `Components` badge and the "Documentation" table linked to `docs.wirekit.app/components` and `docs.wirekit.app/recipes`, neither of which existed as index routes — both returned 404. Replaced with links to working pages: the docs root (`docs.wirekit.app`), the getting-started guide, and the theming guide. Pointing readers at routes that resolve.

---

## [1.6.2] — 2026-04-30

Patch release covering three small surface-polish fixes spotted in the v1.6.1 README + docs walk. No new API surface; no breaking changes; every component renders byte-identical to v1.6.1.

### Changed

- **`README.md` — removed the "Tests" CI badge.** The badge image returned 404 and rendered as broken on every README view. May return in a future release if a green-build signal can be sourced cleanly; for now, removing the broken image is the honest choice.

### Fixed

- **`<x-wirekit::code-block>` copy-button cursor.** The toolbar copy button rendered without a `cursor: pointer` on hover, leaving keyboard / mouse users unsure whether it was clickable. Added `cursor-pointer` to the button class — hover state now signals interactivity unambiguously, matching every other interactive control in WireKit.

- **`docs/cli/wirekit-doctor.md` — "Common failures and fixes" section restructured.** The four subsection headings previously embedded the literal doctor command output (with `⚠` / `i` glyphs and inline-code backticks at H3 size), which rendered awkwardly on docs.wirekit.app — small icons forced to heading scale, monospace at heading weight. Each subsection now uses a descriptive title (e.g. "Sans font mismatch") and quotes the exact doctor line in a `text` code block in the body. Reads cleanly at every viewport.

---

## [1.6.1] — 2026-04-30

Patch release covering five developer-visible fixes plus a README restructure and badge expansion. No new API surface; no breaking changes; every component renders byte-identical to v1.6.0 unless its specific bug applied.

### Fixed

- **`prefers-reduced-motion: reduce` now actually honored on `<x-wirekit::reveal>` and `wk-animate-*` utilities (WCAG 2.3.3).** The component documentation and helper docblock claimed the OS-level reduced-motion preference was respected, but the global `@media (prefers-reduced-motion: reduce)` block in `dist/wirekit.css` only gated `[x-transition]` selectors — the 22 keyframe animations driving every preset ran at full speed regardless of user preference. The block now gates `[class*='wk-animate-']` as well, snapping `animation-duration` to `0.01ms` so reduced-motion users see an instant snap-to-final state. Behavior now matches the documented contract.

- **Nested `<x-wirekit::list>` margins no longer accumulate inside typography wrappers.** When the component is rendered inside a developer's `.prose` / `.docs-prose` / `@tailwindcss/typography` context, the wrapper's `<ol>` / `<ul>` / `<li>` rules injected `margin: 1em 0` onto every level — and the previous Tailwind-utility approach was too low-specificity to win the cascade. Nested lists looked progressively more spaced out at deeper levels regardless of the `spacing` prop. The component now ships with a `wk-list` marker class plus dedicated `wk-list-spacing-{none,sm,md}` rules in `dist/wirekit.css` whose doubled-class selectors win on specificity alone (no `!important`, so developers can still override with even-higher-specificity rules when they explicitly need to). The `spacing` prop is now the single source of truth for inter-item vertical rhythm at every nesting depth.

- **`bounce` and `spring` reveal presets — final state no longer fades back to invisible.** The `wk-bounce-in` and `wk-spring-in` keyframes declared `opacity: 1` only at the 50% / 60% peaks; the 70% and 100% frames left opacity unspecified. With `animation-fill-mode: both`, some browser implementations held opacity at 1 from the last specified frame, but others interpolated back toward the underlying inline `opacity: 0` after the animation settled — the element became visible briefly, then vanished. All four `bounce` / `spring` keyframe blocks now declare `opacity` at every frame, so the final-state behavior is browser-independent.

### Changed

- **`README.md` restructured from a comprehensive standalone reference to a concise getting-started overview.** Installation walkthrough, Quick Start example, and the browser-support table are retained on the GitHub landing page; extended component tables and the customization reference now live at docs.wirekit.app where they're searchable and link-rich. Net effect: the README acts as a 30-second "what is this and how do I install it" landing page; docs.wirekit.app remains the authoritative reference for everything else.

- **`README.md` badge row expanded from 3 to 11 badges across two visual rows.** Row 1 (project status): Packagist version, Total Downloads, GitHub Actions test status, MIT license, GitHub Stars, component count. Row 2 (stack): PHP ≥ 8.4, Laravel 12+, Livewire 4+, Tailwind CSS v4, Alpine.js. The CI badge in particular gives evaluators an at-a-glance signal that the build is green on `main`. Component-count badge auto-validates against `ComponentRegistry` via a Pest sync guard so the displayed number can never drift from the actual registry size.

---

## [1.6.0] — 2026-04-29

Minor release covering font-installation flags, doctor-command token diagnostics, three new opt-in props on `<x-wirekit::stat>`, a complete motion subsystem with the new `<x-wirekit::reveal>` component, and supporting CLI helpers. No breaking changes; every new flag, prop, and opt-in surface defaults to off so v1.5.0 developers see byte-identical output.

### Added

- **`wirekit:install --font=<sans-key>`, `--font-serif=<key>`, `--font-mono=<key>`** — three optional flags for choosing which bundled WireKit font your project uses. Resolves the key against the bundled `FontRegistry` (curated Google Fonts bundled locally for GDPR compliance), validates the category, and idempotently injects an override block into `resources/css/app.css` setting BOTH `--font-{cat}` (drives Tailwind `font-{cat}` utilities) AND `--font-wk-{cat}` (drives WireKit chrome). The two stay aligned automatically — closes the foot­gun where Tailwind utilities and WireKit chrome rendered different families. All three flags combinable; re-running with the same key produces byte-identical output. Wrong-category passes throw with a list of valid keys for the right category. Local fonts only — nothing is fetched from a CDN.

- **`wirekit:install` Tailwind config writer detection** — the install command detects whether your project uses CSS-first Tailwind v4 config (`@theme` block in `app.css`) or the legacy JS-config (`tailwind.config.js theme.extend.fontFamily`) and writes the font override to the right destination. CSS-first wins on tie (Tailwind v4 deprecates JS config). Custom config shapes that the auto-edit can't safely match log an actionable manual-edit hint instead of risking AST corruption.

- **`wirekit:install` interactive mode** — running the command without flags in an interactive TTY opens a guided setup prompting for theme preset + sans/serif/mono font selection. CI / `--no-interaction` / scripted contexts skip prompts and run with v1.5.0-identical defaults.

- **`wirekit:doctor` token-alignment diagnostic** — new section comparing seven Tailwind tokens against their matching WireKit tokens: `--font-sans/serif/mono`, `--color-accent`, `--color-accent-foreground`, `--radius`, `--shadow`. Each pair emits `✓ aligned`, `⚠ mismatch` (with actionable fix hint), or `i skipped` (var() reference or unset). Surfaces token drift at install-time rather than letting it ship to production.

- **`docs/cli/wirekit-doctor.md`** — dedicated documentation page for the doctor command. Covers every diagnostic check with example output, three "Common failures and fixes" walkthroughs (font mismatch, accent-color mismatch, var() skip), and a GitHub Actions integration recipe.

- **`<x-wirekit::stat animate>` Three Description Options** — three opt-in props govern how the description text behaves during the value count-up animation. Option A `descriptionDeferred` defers the description fade-in until the counter settles (200ms ease-out), with `aria-hidden` mirroring visibility for screen-reader contract. Option B (default, status quo) renders the description statically. Option C `descriptionAnimate` animates the description text color from `--color-wk-text-muted` → `--color-wk-text` synchronously with the value count-up. Options A and C are mutually exclusive — passing both throws. All three honor `prefers-reduced-motion: reduce`. New `### Animation Scope` subsection in `docs/components/stat.md` documents the contract.

- **`<x-wirekit::reveal>` component** — NEW thin Blade wrapper that animates its slot content into view. One `preset` prop selects from 11 bases × in/out variants (`fade`, `slide-up`, `slide-down`, `slide-left`, `slide-right`, `scale`, `zoom`, `flip`, `rotate`, `bounce`, `spring`). Three trigger modes: `viewport` (default, IntersectionObserver), `click`, `manual` (developer dispatches `wirekit:reveal` event). Three duration tokens: `fast` (150ms), `normal` (300ms, default), `slow` (600ms). Full `prefers-reduced-motion: reduce` honoring — element snaps to final state. New docs page `docs/components/reveal.md` with eight live preview blocks.

- **`docs/animations.md`** — NEW reference page covering the entire motion subsystem: six new design tokens, 22 keyframes (11 bases × in/out), 22 utility classes (`.wk-animate-{preset}-{in|out}` plus three duration modifiers `.wk-animate-{fast|normal|slow}`), the `wirekitAnimate` Alpine helper API, and the reduced-motion contract.

- **Six new motion design tokens** in `dist/wirekit.css`: `--motion-wk-duration-fast: 150ms`, `--motion-wk-duration-normal: 300ms`, `--motion-wk-duration-slow: 600ms`, `--motion-wk-easing-out` (decelerating cubic), `--motion-wk-easing-in` (accelerating cubic), `--motion-wk-easing-spring` (overshoot). The legacy `--transition-wk-duration` is aliased to `--motion-wk-duration-fast` for back-compat.

- **`animateIn` prop on 7 marketing components** — `<x-wirekit::card>`, `<x-wirekit::feature>`, `<x-wirekit::hero>`, `<x-wirekit::stat>`, `<x-wirekit::callout>`, `<x-wirekit::alert>`, and `<x-wirekit::empty-state>` accept an optional `animateIn` prop that wires the `wirekitAnimate` Alpine helper to the root. Pass a base name (`fade`, `slide-up`, `bounce`, …) or a full preset name (`fade-in`, `slide-up-out`). Default `null` preserves v1.5.0 render exactly. Components with built-in entrance transitions (`modal`, `drawer`, `toast`, `tooltip`, `dropdown`, `popover`) deliberately skip this prop to avoid double-motion conflicts.

- **`wirekit:export-api-map`** — new top-level `helpers` group covering Alpine helpers exposed by the WireKit JS bundle. `wirekitAnimate` lists its full 22-preset enum, three trigger modes, three duration tokens, reduced-motion contract, and Blade-wrapper hint. `wirekitStatAnimate` documents its reactive state catalog (`value`, `animating`, `progress`). The exported map gives editor tooling and IDE extensions a single source of truth for the correct `x-data="…"` shape without grepping the source.

- **`<x-wirekit::list>` four new ordered marker types** — `lower-roman` (i, ii, iii), `upper-roman` (I, II, III), `lower-alpha` (a, b, c), `upper-alpha` (A, B, C) join the existing `disc`, `decimal`, `none` set. All four render as `<ol>` with Tailwind v4 arbitrary-value `list-style-type` utilities. Mix freely across nested levels for legal-contract / academic / spec-style outlines.

### Changed

- **`<x-wirekit::main>` horizontal padding aligned with `<x-wirekit::header>`.** Previously `padding="lg"` produced 1.5rem all around (via `--space-wk-lg`); now uses `--padding-wk-x-{size}` for the horizontal axis (1rem at `lg`) — matching the same-name token used by Header. A sibling Header + Main pair (the canonical app-shell layout) now shares one vertical alignment line. Vertical padding stays on the generic `--space-wk-{size}` scale for breathing room. Applies to all five sizes (`none`/`sm`/`md`/`lg`/`xl`); `none` unchanged.

- **`<x-wirekit::app-shell>` sidebar breathing room.** Sidebar `<aside>` wrapper gained `lg:mt-[var(--space-wk-md,1rem)] lg:ml-[var(--padding-wk-x-lg)]` so the in-flow column position at `lg+` no longer sits flush against the header divider. Mobile / off-canvas behavior unchanged.

- **`dist/wirekit.js`** bundle grew by ~0.4 KB (the `wirekitAnimate` Alpine helper, ~1 KB minified). Full bundle is 78.0 KB. Core bundle (chart-only) unchanged.

- **`docs/cli.md`** documents all new install flags + the interactive mode + the new doctor token-alignment section + a cross-link to the dedicated `wirekit:doctor` reference page.

- **`docs/animations.md` interactive preset gallery** — fourteen toggle-button preview blocks covering all eleven `<x-wirekit::reveal>` presets (fade, slide-up, slide-down, slide-left, slide-right, scale, zoom, flip, rotate, bounce, spring) plus a duration comparison row (fast / normal / slow). Each block has a button that toggles between the `-in` and `-out` variants on successive clicks; clicks during an animation are ignored until `animationend` fires. Reduced-motion users see the final state immediately.

- **`docs/dependencies.md` bundle sizes refreshed** to reflect the v1.6.0 additions: full bundle now ~23 KB gzip (78 KB raw, +`wirekitAnimate` Alpine helper), core unchanged at ~2 KB gzip (8 KB raw), ESM ~23 KB gzip (76 KB raw). Verified via `gzip -c | wc -c`.

- **Public package archive** no longer ships `.gitattributes` or `.gitignore`. Both were dev-tooling artifacts whose only purpose was to filter the source tree at archive time; once the package is installed via `composer require pushery/wirekit`, neither file has a downstream role. Their absence from the published tarball makes developer installs marginally cleaner. v1.6.0 is the first release where this applies.

### Fixed

- **`<x-wirekit::stat animate>` reduced-motion display formatting.** Previously the reduced-motion code path snapped `value` to the raw `data-target` string (e.g. `"12500"`); the in-flight animation tick formatted via `toLocaleString()` (e.g. `"12,500"`). Result: a user with `prefers-reduced-motion: reduce` saw a different format than a user without. Both paths now share a `formatValue()` helper so display is locale-consistent regardless of motion preference. Suffix preservation (`$`, `%`, etc.) also unified across paths.

- **Sandbox `code-block` schema — `language` is now an enum** with 20 highlight.js-aligned grammar values (`bash`, `php`, `blade`, `html`, `javascript`, `typescript`, `python`, `ruby`, `go`, `rust`, `sql`, `yaml`, `markdown`, `dockerfile`, …). The `wirekit:export-api-map` output and any Sandbox-driven UI now expose the discoverable allowed-values list instead of leaving developers to guess what the syntax-highlighter accepts.

- **Sandbox `PropsValidator` — HTML-form scalar coercion** for `type: int` / `type: bool` / `type: float`. HTML form submissions are always strings (`"4"` from a `<select>`, `""` from an unchecked checkbox); previously the strict type check rejected with `expected int, got string`. The validator now conservatively coerces unambiguous string shapes into the declared scalar type before the type check, while preserving rejection for non-numeric strings against `int`. Makes every schema with a typed-int / typed-bool prop usable from a Live-Sandbox UI without developers needing to coerce client-side.

---

## [1.5.0] — 2026-04-28

Minor release with eight developer-facing improvements: a code-block screen-reader announcement fix, a `wirekit:doctor` post-build CSS sanity check, a hero-row `xl` size on `<x-wirekit::feature>`, a counter-animation `animate` prop on `<x-wirekit::stat>`, an `asideWidth` ratio refinement on `<x-wirekit::hero>`, eight new marketing-copy semantic aliases on `heroicons-marketing`, plus the syntax-highlighter contract documented in `docs/theming.md`.

No breaking changes — fully backward-compatible with v1.4.0.

### Added

- **`<x-wirekit::stat animate>`** — opt-in counter-animation prop. When set, the value text wraps in an Alpine `wirekitStatAnimate` data handler that animates 0 → target over 1.2s (ease-out cubic) once the stat scrolls 40% into view (`IntersectionObserver`). Respects `prefers-reduced-motion: reduce` — the value snaps to target with no animation if the OS-level setting is enabled. Static value remains visible inside the `<span x-text="value">` fallback so search engines, no-JS browsers, and Alpine-pre-init paint all see the real number. Default `false` preserves v1.4.x output. Numeric prefixes / suffixes (`$`, `%`, etc.) are preserved through the animation; `toLocaleString()` formats the in-flight value with locale-aware thousand-separators.

- **`<x-wirekit::hero asideWidth>`** — opt-in copy:aside ratio refinement under `layout="balanced"`. Five values: `1/3` (aside ⅓), `2/5` (aside ⅖), `1/2` (50/50, matches default), `3/5` (aside ⅗), `2/3` (aside ⅔). Under any non-balanced layout (`lead` / `centered` / `stacked`) `asideWidth` throws via `WireKit::validateProp` in debug mode and is silently ignored in production. Default `null` preserves v1.4.x balanced 50/50.

- **`<x-wirekit::feature size="xl">`** — fourth chip-size value for hero-row features. Renders a 64×64 chip with a 32×32 inner icon. Existing `sm` / `md` / `lg` values keep their semantics; `md` remains the default.

- **Eight semantic copy aliases on the `heroicons-marketing` preset** — `live` (signal), `pulse` (arrow-path-rounded-square), `a11y` (finger-print), `sparkle` (sparkles), `security` (lock-closed), `speed` (bolt), `open-source` (code-bracket), `ai` (cpu-chip). Names map to landing-page bullet copy rather than to the underlying icon name. Anti-collision verified by existing test suite — none of these shadow a base or `heroicons-app` alias.

- **Post-build CSS sanity check on `wirekit:doctor` / `wirekit:verify`** — final check after the existing source-side `@source` verification. If `public/build/manifest.json` exists, the doctor walks every CSS file in the manifest and looks for any `--color-wk-*` token reference. Fails with a "run `npm run build`" hint if no CSS bundle in the manifest references WireKit tokens — catches the silent-failure mode where a developer adds the `@source` line to `app.css` but forgets to rebuild. Skips silently in environments without a manifest (dev / pre-build / package-test scenarios).

- **`docs/theming.md` "Syntax-highlighter contract" subsection** — under Accessibility & Contrast. Tells developers wiring their own `highlight.js` / Prism / Shiki theme that token-level contrast must hit ≥4.5:1 against the active `--color-wk-bg-elevated`, in BOTH light and dark mode, across every theme preset. Documents the two foot-guns (unscoped WCAG overrides bleeding cross-mode; per-theme `bg-elevated` variations breaking single-pair audits) with a sketch of a Playwright contrast-audit recipe.

### Fixed

- **`<x-wirekit::code-block copy>` symmetric screen-reader announcements.** The polite live region (`<span role="status" aria-live="polite">`) was already in DOM but only spoke on success; the error path was silent (clipboard.writeText() rejection on non-secure-context or denied-permission cases threw an unhandled rejection and the user heard nothing). Click handler now wraps the clipboard write in `.then()/.catch()` so success AND failure each set a distinct, polite SR-only string: "Code copied to clipboard" / "Copy failed". WCAG 2.2 SC 4.1.3 (Status Messages) — both code paths now satisfy.

---

## [1.4.0] — 2026-04-28

Minor release covering accessibility, sandbox-schema, and component-render fixes that surfaced after v1.3.0 shipped, plus three additive opt-in prop additions: `<x-wirekit::action-bar mode="static">`, `<x-wirekit::toast-region eventScope>`, and the new `SandboxRenderer::BODY_WRAPPERS` map.

No breaking changes — fully backward-compatible with v1.3.0.

### Added

- **`<x-wirekit::action-bar mode="static">`** — second layout mode for the action bar. The default `mode="floating"` keeps the existing `position: fixed` + viewport-centring transforms (fully back-compat). The new `mode="static"` flows inline with surrounding content (drops the fixed positioning + the centring transforms; keeps the same chrome — border, shadow, padding, rounded corners). Useful when the bar is part of a card / panel / dashboard rather than a viewport-floating overlay.

- **`<x-wirekit::toast-region eventScope="…">`** — optional CSS selector that scopes incoming toast events by DOM containment. When set, only events whose dispatching element is inside an ancestor matching the selector are handled. Useful for "per-section toast surfaces" where multiple toast regions on the same page must not cross-talk. The existing `name` parameter (event-name routing) is unchanged and still works in parallel; `eventScope` is additive. Default `null` preserves the global-listener behavior.

- **`Pushery\WireKit\Sandbox\SandboxRenderer::BODY_WRAPPERS` map** — auto-wraps the sandbox body slot in a sub-component for primitives whose composition requires it. The Card schema now wraps its body in `<x-wirekit::card.body>` so developer-side sandbox renders carry the full card chrome with padded body content instead of a bare rounded pill. Open extension point: future multi-slot primitives (tabs, accordion, …) can opt in by adding an entry.

### Fixed

- **Resizable handle drag no longer flickers from text-selection in adjacent panels.** The handle's `pointerdown` already called `setPointerCapture()`, but the body `user-select` was never disabled during drag — so the cursor crossing a sibling panel highlighted that text and the browser repainted the selection mid-drag (visible as a "flicker"). `onPointerDown` now captures the prior inline value of `body.style.userSelect` and sets it to `'none'`; `onPointerUp` restores it. `pointercancel` already routes through `onPointerUp` so a tab-switch mid-drag also cleans up — no leak.

- **Vertical `<x-wirekit::resizable>` no longer collapses to 0 height when the wrapper has no explicit size.** `[data-wk-resizable][data-wk-direction="vertical"]` carries `contain: size`, which requires an explicit container size — without one, the panels' percent heights resolve against a 0-height container and the whole component disappears. Added a default `min-height: 16rem` on the vertical wrapper so unstyled containers still render visibly. Authors with explicit inline `style="min-height: …"` keep their value (CSS specificity favors the inline rule).

- **WCAG 4.1.2 (Name, Role, Value) sweep across 11 interactive components.** Alpine's reactive `:aria-*` bindings only emit the attribute after the JS boots — initial server-rendered HTML lacked the required `aria-expanded` (combobox, multi-select), `aria-valuenow` (image-compare, range-slider), or `aria-checked` (rating, segmented-control). Each affected component now ships a static `aria-foo="default-value"` immediately before the `:aria-foo="reactive-binding"` so server-rendered HTML is WCAG-complete from the first paint and Alpine overrides reactively after hydration. Plus: `<x-wirekit::brand>` in logo-only mode auto-injects `aria-label="Home"` (caller's `aria-label` still wins); `<x-wirekit::toast-region>` adds `role="region"` so its `aria-label="Notifications"` is permitted; `<x-wirekit::date-picker>`, `<x-wirekit::color-picker>`, `<x-wirekit::slider>` accept a new `label` prop and fall back to a sr-only label derived from `name` when no label / `aria-label` / `aria-labeledby` is provided; `<x-wirekit::navigation-menu.item>` link-mode now falls back to the default slot when no `trigger` prop is set so the canonical `<x-wirekit::navigation-menu.item href="/x">Label</x-wirekit::navigation-menu.item>` pattern produces a non-empty link.

- **Card sandbox schema rendered as a bare rounded pill instead of a proper card.** The Card sandbox schema declared `padded` / `bordered` / `elevated` boolean props that the Card primitive never read in its `@props([...])` block — the renderer emitted them as raw HTML attributes (silently no-op), and the body slot was never wrapped in `<x-wirekit::card.body>`, so slot text pressed flush against the rounded card edges. Schema rewritten to use the real `variant` prop (`outlined` / `elevated` / `flat`); body slot now auto-wraps via the new `BODY_WRAPPERS` map.

- **`code-block` sandbox schema declared `lang`, but the actual prop is `language`.** The renderer emitted `<x-wirekit::code-block lang="php">` which the component ignored — the syntax-highlight language class was never applied. Renamed the schema key to match the component contract.

- **`text` sandbox schema declared a `muted` boolean.** The Text primitive has no `muted` prop; it uses a string `variant` enum with allowed values `default` / `muted` / `subtle` / `accent` / `success` / `warning` / `danger`. Replaced the boolean with the real `variant` enum.

- **Callout sandbox schema dropped the un-renderable `title` reference.** `title` is a named slot in the Callout primitive (`@isset($title)`), not a `@props` entry, so the renderer's string-prop-as-HTML-attribute path could never populate it. Schema now carries only the props the renderer can actually deliver. (`alert` keeps `title` because `<x-wirekit::alert>` declares `title` as a real `@props` entry — different shape.)

- **`button` and `badge` sandbox schemas: slot key `label` → `body`.** Earlier iterations declared `label` as the slot-content key, which the renderer treated as an HTML attribute and never inserted into the slot — sandbox renders produced empty buttons / badges. Renamed to the renderer's reserved `body` convention.

- **`clipboard-button` button-width regression on state change.** The first iteration of the stable-width fix used `x-show` to toggle which label rendered inside the grid cell. `x-show` sets `display: none` on the inactive label, removing it from layout entirely; the grid cell then collapsed to whichever child was visible — defeating the stable-width goal. Replaced with `:style="{ visibility: ... }"` toggling so both labels stay in layout permanently and the grid cell sizes to the wider one. The copied-state span also carries a static `style="visibility: hidden"` so its layout slot is reserved before Alpine init evaluates the bindings — no flicker on first paint, no width jump on state change.

- **`ticker` rendered `++8.4%` for already-signed-string deltas.** The delta-formatting block prepended `+` to positive deltas, then interpolated the original input verbatim — inputs that already carried an explicit sign rendered with double prefix. Strip leading `+` from string deltas before re-deriving the sign from the numeric value. Both `"8.4"` and `"+8.4"` now render identically as `+8.4%`. Negative signed strings (`"-1.2"`) keep their leading `-`. Numeric inputs (int / float) and unsigned strings remain unchanged.

- **`app-shell` defaulted to a width that didn't fill its parent.** Added `w-full` to the shell's base classes so it correctly fills its parent container regardless of layout context.

- **`code-block` defensive styling against inherited inner `<pre>` / `<code>` background.** Added `bg-transparent` and `radius-none` on the inner elements to prevent host-page CSS from bleeding through.

- **WCAG 1.4.3 contrast sweep for soft-bg foreground tokens.** Round-2 polish on calendar week-view chips, finance cells, opacity-overlaid cards, and CTA buttons — every soft-tinted background now pairs with a foreground token that meets the 4.5:1 ratio in both light and dark mode. Plus a P0 fix to `dist/wirekit.css` (`:root {}` instead of `@theme {}` so a plain `<link rel="stylesheet">` load works without the build pipeline).

- **A11y polish on shipped components**: center component fills its parent, callout becomes a `<section>` for proper landmark semantics, kanban-column scroll body gets the missing scroll-region a11y wiring.

### Changed

- **`README.md` icon section** now lists both stackable extension presets (`heroicons-app`, `heroicons-marketing`) consistently. Earlier the stackable presets only surfaced in the Available-Presets table near the bottom of the icon README block while top-of-page sections (Requirements tip, Configuration block, Switching Presets) referenced base presets only — discoverability gap closed.

---

## [1.3.0] — 2026-04-26

Minor release covering eight new blueprint primitive components, an Artisan
command suite for scaffolding / diagnostics / asset publishing / AI-tooling
integration / machine-readable manifests, the Livewire sandbox primitives library,
and a security-hardening sweep across `target="_blank"` link rendering plus the
JSON-encoder layer.

No breaking changes — fully backward-compatible with v1.2.x.

### Added

- **Blueprint primitive components (8)** — `<x-wirekit::price>` (currency formatting with size variants), `<x-wirekit::date-separator>` (timeline / chat date divider), `<x-wirekit::reaction>` (emoji reaction button with count), `<x-wirekit::ticker>` (live data ticker with delta indicator), `<x-wirekit::toolbar>` (button group bar with slots), `<x-wirekit::message>` (chat / thread message bubble with alignment), `<x-wirekit::kanban>` and `<x-wirekit::kanban-column>` (kanban board with column composition). All use design tokens exclusively, support the Intent × Surface API where applicable, and follow WAI-ARIA patterns.

- **Sandbox primitives library** (`src/Sandbox/`) — reusable security-hardened render pipeline for any developer project that needs to render WireKit components from untrusted JSON props (live-preview iframes, prop-editor UIs, etc.):
  - **`SandboxRenderer::render($component, $props, $ip): RenderResult`** — main entry point. Validates → sanitizes → renders → audit-logs. Returns `RenderResult` (success or 422-shaped rejection); never throws.
  - **`PropsValidator`** — enforces per-component prop schema, type-checks, rejects strings >10 KB and arrays nested >5 deep (DoS defense), HTML-escapes every string defense-in-depth so even a slot using `{!! !!}` cannot surface raw payload content.
  - **`ComponentAllowlist`** — strict kebab-case regex + `ComponentRegistry` cross-check + sandbox-schema presence guard. Path-traversal characters, namespace separators, whitespace, uppercase — all rejected with 422-shape, never 500.
  - **`SandboxSchemaRegistry`** — in-memory registry of per-component prop allowlists with `allowed_values` enums. Initial coverage of 11 starter components (button, badge, callout, alert, card, code, code-block, kbd, heading, text, link); the renderer is functional with whatever schemas are seeded, full coverage will follow incrementally.
  - **`SandboxAuditLog`** — file-based daily-rotating log (`storage/logs/sandbox/YYYY-MM-DD.log`). IPs sha256-truncated to 16 chars so logs are useful for rate-pattern auditing but not for tracking individuals.
  - **`RenderResult` / `ValidationResult`** — immutable result objects with public-readable `ok` / `violations` / `html` / `schema` properties so developer-side prop-editor UIs can read them via `get_object_vars()`.

- **`<x-wirekit::ticker>` dark-mode contrast fix** — switched the delta text from the bare `--color-wk-success` / `--color-wk-danger` foundation tokens to the `*-text` variants (which are calibrated for ≥4.5:1 WCAG 1.4.3 contrast against surface tokens in BOTH light and dark mode).

- **`.cursor/rules/wirekit.mdc`** — single-file (~150-line) Cursor rules ruleset covering component invocation syntax, the Intent × Surface variant system, design tokens, icon usage, layout primitives, typography primitives, modal / drawer / dropdown trigger patterns, accessibility defaults, Livewire integration patterns, browser-support baseline, and the full CLI. Cursor / Codeium / other native `.mdc` editors pick up the rules automatically for every `*.blade.php` and `*.css` file in the project.

- **`php artisan wirekit:component {name}`** — scaffolds a custom Blade component derived from a WireKit base into `resources/views/components/custom/{name}.blade.php`. `--base` flag picks the source; `--force` allows overwriting an existing custom file. Resolves both flat (`button`) and dotted (`card.header`) base names. (The v2.0.0 default of `--base={name}` was superseded in v2.1.0 by a derived-parent default — `wirekit:component my-button` now derives `--base=button` automatically; see the v2.1.0 entry.)

- **`php artisan wirekit:publish-icons {preset}`** — targeted icon publishing. Copies a single preset's SVG directory from `vendor/{package}/resources/svg/` to `public/vendor/wirekit/icons/{preset}/`. Refuses with a precise `composer require ...` fix line when the underlying icon-set package is not installed. Supports `heroicons`, `heroicons-app`, `heroicons-marketing`, `lucide`, `phosphor`, `tabler`.

- **`php artisan wirekit:doctor`** — alias for `wirekit:verify` under the more conventional Laravel-ecosystem name. Existing CI scripts and docs that reference either name keep working. (The v2.0.0 shipped two parallel registrations; v2.1.0 collapses them to a single Symfony alias — see the v2.1.0 `### Changed` entry for the internal-import consequence.)

- **`php artisan wirekit:cursor-rules`** — copies the package's `.cursor/rules/wirekit.mdc` into the developer project's `.cursor/rules/` directory. `--force` to overwrite an existing copy.

- **`php artisan wirekit:export-api-map [--pretty]`** — emits an AI-friendly hierarchical sitemap covering eight groups: components, themes, fonts, icons, layouts, blueprints, recipes, and commands. Superset of `wirekit:export-json`. Output is XSS-safe via `JSON_HEX_TAG`. Designed for MCP servers and other AI tooling that need a single entry point to enumerate every WireKit surface.

- **`php artisan wirekit:export-blocks [--pretty]`** — emits a machine-readable JSON manifest of every layout + blueprint with frontmatter metadata (`category`, `tags`, `dependencies`, `responsive`, `dark_compatible`) plus generated `preview_url` and `source_url`. Consumable by gallery UIs and AI tooling for filterable browsing.

- **Password input accessibility** — toggle button has a static `aria-label` fallback for pre-Alpine-hydration accessibility scans.

- **Toggle auto-labeling** — component auto-generates an `aria-label` from the `name` prop when neither `label` nor `aria-label` is provided.

- **Config defaults** — `config/wirekit.php` ships size defaults for `<x-wirekit::price>` and `<x-wirekit::ticker>`.

### Changed

- **`Pushery\WireKit\Sandbox\RenderResult`** now carries a public-readable `?array $schema` property in addition to `ok` / `html` / `violations`. `SandboxRenderer::success()` echoes the per-component schema back so developer-side prop-editor UIs can render the editor without a second round-trip to the schema registry.

- **`target="_blank"` auto-protection hardened** — the `rel` attribute injection in all 13 link-rendering components (Button, Dropdown Item, Command Palette Item, Menubar Item, Navigation Menu Item, Navigation Menu Link, Navbar Item, Sidebar Item, Link, Brand, Card) now uses an explicit override pattern that prevents caller-supplied `rel` values from silently defeating the `noopener noreferrer` protection. Coverage extended to four additional components that were previously missing the pattern: Link, Navbar Item, Navigation Menu Link, and Sidebar Item.

- **Chart.js dark-mode refresh** — `chart.update()` replaces `chart.update('none')` in the MutationObserver callback. Chart.js v4's `'none'` mode skips the style-resolver pass when only color properties change, leaving stale colors rendered. The observer now watches both `<html>` and `<body>` for `.dark` class changes, supporting both mounting conventions.

### Fixed

- **WCAG 1.4.3 dark-mode contrast on `<x-wirekit::ticker>` delta text** — the `--color-wk-success` / `--color-wk-danger` foundation tokens previously yielded 3.66:1 / 4.05:1 against dark surface tokens (fails the 4.5:1 AA threshold for small text). Fix uses the `*-text` token variants which are calibrated for ≥4.5:1 in both modes.

- **CTA accent variant dark-mode contrast** — the accent variant used a hardcoded `text-white` class that fails when `--color-wk-accent` inverts in dark mode. Now uses `text-[var(--color-wk-accent-fg)]` which auto-switches correctly.

- **Section accent background token** — the accent background variant referenced a non-existent `--color-wk-primary` CSS variable. Replaced with `--color-wk-accent` background and `--color-wk-accent-fg` text color, matching the established pattern used by CTA and Badge.

- **Dropdown trigger ARIA** — `aria-haspopup`, `aria-expanded`, and `aria-controls` were placed on a non-interactive `<div>` wrapper. Moved to the inner interactive element via `x-init`.

- **Progress bar accessible name** — component only wired `aria-labeledby` when the `label` prop was set. Usages without labels now receive a sensible `"Progress"` default.

- **App-shell sidebar backdrop** — the mobile sidebar dim overlay was traversable by screen readers, defeating the focus-trap intent. Added `aria-hidden="true"`.

- **`dist/wirekit.css` parses correctly when loaded via `<link>`.** Previously the entire token palette lived inside a Tailwind v4 `@theme {}` compiler block. Browsers correctly skip unknown at-rules per the CSS spec, which meant the documented "fastest path" — using the `@wirekitStyles` Blade directive that embeds the file via `<link rel="stylesheet">` — left zero `--color-wk-*` variables defined in the CSSOM. Components rendered without color tokens. The file now emits a standard `:root {}` (light) and `.dark {}` (dark) block directly, so both consumption paths resolve identically: the `@wirekitStyles` directive AND `@import` from `app.css`. The `@custom-variant dark (&:where(.dark, .dark *));` directive is preserved at the top of the file (harmless under `<link>`, useful under `@import` for Tailwind `dark:` variant support).

- **WCAG 1.4.3 contrast — light-mode `*-text` and `text-muted` tokens recalibrated.** Three "soft-bg foreground" tokens were below the AA 4.5:1 threshold against the 12% `color-mix()` soft-tone backgrounds used by badge / alert / callout / feature / message / reaction / toast-region, and `text-muted` was below threshold on `bg-muted`:
  - `--color-wk-success-text`: green-700 → green-800. Was 4.33:1 on soft-success bg, now ~6.17:1.
  - `--color-wk-danger-text`: red-500 → red-700. Was 3.89:1 on soft-danger bg, now ~5.13:1.
  - `--color-wk-warning-text`: amber-700 → amber-800. Was 4.41:1 on soft-warning bg, now ~6.04:1.
  - `--color-wk-text-muted`: neutral-500 → neutral-550. Was 4.26:1 on `bg-muted` (#f7f7f7), now ~6.13:1. `text-subtle` and `text-placeholder` are unchanged (they only appear on white where neutral-500 already meets 4.74:1).

- **Bare `text-[var(--color-wk-{success,warning,danger})]` on text content swept to `*-text` variants.** Affected component-internal usages: `<x-wirekit::text>` semantic variants (`success` / `warning`), `<x-wirekit::stat>` `up` trend, `<x-wirekit::price>` delta intent (`success` / `danger`), `<x-wirekit::feature>` soft tones (`success` / `warning`). Decorative `aria-hidden` SVG icons inside alert / callout / toast-region / code-block / rating left at the bare tone (graphic-element semantics, 3:1 threshold via WCAG 1.4.11).

- **`<x-wirekit::calendar>` event chips no longer render white text on green-500 / amber-500.** Month-view and week-view event chips (`background: var(--color-wk-success); color: var(--color-wk-bg)`) yielded 3.21:1 / 2.13:1 — fail. Component now uses `color: var(--color-wk-success-fg)` / `color: var(--color-wk-warning-fg)` (zinc-900 on the tone bg — ~6.95:1 / ~9.03:1).

- **`<x-wirekit::calendar>` week-view chip subtexts collapsed.** Opacity-blended subtexts (`<span style="opacity: 0.8">…</span>`) dropped contrast below 4.5:1 in light mode (4.23:1 against the tone bg). Replaced the two-line `Primary` + opacity-dimmed subtext pattern with a single-line `Primary · Subtext` middle-dot label that passes both modes at the full token contrast.

### Security

- **`/components.json` JSON encoder hardened with `JSON_HEX_TAG`** — brings `wirekit:export-json` in line with the existing `wirekit:export-api-map` and `wirekit:export-blocks` contracts. Without `JSON_HEX_TAG`, a component description containing `</script>` could break out of a `<script type="application/ld+json">` block where the manifest is embedded.

---

## [1.2.1] — 2026-04-20

Tag re-cut after v1.2.0 to address a release-pipeline issue. Package contents are identical to v1.2.0 — no developer action required when upgrading from v1.2.0.

---

## [1.2.0] — 2026-04-20

Substantial feature release. WireKit now covers full-page composition: a 10-component
layout primitives system, a 9-component typography primitives system, app-shell
scaffolding, and a marketing-page toolkit — plus a new unified `intent × surface`
variant API, a component registry, five new artisan commands, and a rewritten
release pipeline.

### Added

- **Layout primitives (10 components)** — `<x-wirekit::container>` (width-constrained
  wrapper with max/padding/center props), `<x-wirekit::stack>` (vertical flex),
  `<x-wirekit::row>` (horizontal flex), `<x-wirekit::grid>` (responsive column
  syntax `cols="1 sm:2 lg:3"`), `<x-wirekit::section>` (full-width with background
  and divider variants), `<x-wirekit::spacer>` (flex-grow), `<x-wirekit::divider>`
  (horizontal/vertical with label and variants), `<x-wirekit::center>` (flex
  centering), `<x-wirekit::aspect-ratio>` (native CSS `aspect-ratio`), and
  `<x-wirekit::visually-hidden>` (sr-only wrapper).
- **Typography primitives (9 components)** — `<x-wirekit::heading>` (h1–h6 with
  auto-sizing, accent, tracking), `<x-wirekit::text>` (body text with size,
  variant, weight, align, truncate, line-clamp), `<x-wirekit::link>` (styled
  anchor with external-link detection), `<x-wirekit::code>` (inline monospace),
  `<x-wirekit::code-block>` (multi-line with copy button and filename),
  `<x-wirekit::kbd>` (keyboard key indicator), `<x-wirekit::list>` (ul/ol with
  spacing), `<x-wirekit::blockquote>` (left-border with citation), and
  `<x-wirekit::mark>` (text highlight wrapper).
- **`<x-wirekit::highlight>`** — typography helper that highlights query matches
  inside a block of text. Pairs with the Prose component's new `variant` prop.
- **App Shell components (6 components)** — `<x-wirekit::app-shell>` (full-page
  layout with responsive sidebar toggle), `<x-wirekit::header>` (sticky header
  with optional container), `<x-wirekit::main>` (content area with padding
  variants), `<x-wirekit::brand>` (logo + name link), `<x-wirekit::profile>`
  (avatar + name display), and `<x-wirekit::sidebar.toggle>` (hamburger button).
- **Marketing components (5 components)** — `<x-wirekit::hero>` (landing-page
  hero with variants, gradient, slots), `<x-wirekit::feature-grid>` (responsive
  feature-card grid), `<x-wirekit::feature>` (individual card),
  `<x-wirekit::cta>` (call-to-action banner with dark and accent variants), and
  `<x-wirekit::footer>` (columns, brand, legal slots).
- **Unified Intent × Surface variant system** — new `intent` and `surface` props
  on Button and Badge. Six intents (`neutral`, `accent`, `success`, `warning`,
  `danger`, `info`) × five surfaces (`filled`, `soft`, `outline`, `ghost`, `link`)
  generate consistent combinations via a central `VariantResolver`. The legacy
  `variant=` API is preserved for full backward compatibility.
- **`ComponentRegistry`** — central catalog of every WireKit component with
  category and description metadata. Anti-drift tests enforce registry ↔
  filesystem consistency (every Blade file has an entry, no stale entries,
  valid categories, non-empty descriptions).
- **`php artisan wirekit:list`** — lists all components grouped by category.
- **`php artisan wirekit:show {name}`** — displays props, sub-components, and
  docs path for a given component.
- **`php artisan wirekit:install`** — one-command setup: publishes config and
  assets, prints layout directive snippet, adds published assets to `.gitignore`.
- **`php artisan wirekit:theme {preset}`** — injects a theme preset's CSS block
  into the developer's `app.css`.
- **`php artisan wirekit:make {name}`** — scaffolds a Livewire page pre-wired
  with WireKit components.
- **Design tokens** — new spacing scale (`--space-wk-xs` through `--space-wk-2xl`),
  container max widths (`--size-wk-container-sm` through `--size-wk-container-2xl`),
  `--text-wk-3xl`, `--font-wk-heading-line-height`, and semantic colors
  (`--color-wk-bg-inverse`, `--color-wk-text-inverse`, `--color-wk-border-strong`,
  `--color-wk-warning-bg`) with dark-mode counterparts.
- **Accessibility sections in docs** — Button, Input, Textarea, and Label pages
  document label pairing, `aria-invalid`/`aria-describedby` wiring,
  `:user-invalid` styling, focus-visible rings, disabled states, icon-only
  `aria-label` guidance, external-link auto-protection, and required-indicator
  semantics.

### Changed

- **`target="_blank"` auto-protection** — all link-rendering components
  (Button, Dropdown Item, Brand, Card, Navigation Menu Item, Link, Menubar Item,
  Command Palette Item) now automatically inject `rel="noopener noreferrer"`
  (tabnabbing prevention) and a `<span class="sr-only">(opens in new tab)</span>`
  screen-reader hint whenever `target="_blank"` is set. No developer changes
  needed. Components with both button and link modes include a guard so the
  injection only fires when `href` is present.
- **`<x-wirekit::prose>`** — gained a `variant` prop for tighter integration with
  the new typography primitives.

### Fixed

- **Grid and Feature Grid responsive classes missing from developer bundles** —
  `grid.blade.php` and `feature-grid.blade.php` built Tailwind class names via
  runtime PHP string concatenation (`"{$breakpoint}:grid-cols-{$value}"`).
  Tailwind's content scanner only sees literal strings, so these classes were
  dropped from developer CSS and previews rendered as stacked items instead of
  responsive grids. Replaced with a literal class map covering all valid
  breakpoint × column combinations (Grid: 72 entries; Feature Grid: 36 entries).
  Invalid tokens surface via `WireKit::validateProp()`.
- **Layout-doc preview rendering** — 40 `<x-wirekit::card>` occurrences in
  container, grid, stack, row, section, spacer, and divider docs wrapped raw
  content directly in the card without `<x-wirekit::card.body>`. Card provides
  border/radius/background but not padding — previews rendered as thin-bordered
  boxes with cramped text. All 40 occurrences now use `card.body`. Aspect-ratio
  preview refactored to a styled centering frame.
- **README component links** — the Feature row now links to its own docs page
  instead of Feature Grid. Highlight component added to the Typography table.

---

## [1.1.1] — 2026-04-18

Patch release. Re-cut after v1.1.0 to consolidate the complete theme overhaul, two new components, and accessibility improvements listed below. v1.1.0 and v1.1.1 ship the same set of changes; upgrade directly to v1.1.1.

### Added

- **`<x-wirekit::image-compare>`** — new before/after image comparison slider
  with horizontal and vertical orientation, pointer/touch drag, full WAI-ARIA
  Slider Pattern keyboard support, `wire:model` binding (deferred, live, and
  debounced), screen-reader live region, reduced-motion guard, and four
  personalization blocks. Ships in both the full and core JS bundle.
- **Liquid Glass extension** — optional glassmorphism module installed via
  `php artisan wirekit:glass install`. Tier 1 provides frosted-glass
  `backdrop-filter` effects (all browsers). Tier 2 adds SVG `feDisplacementMap`
  refraction (Chrome/Chromium only). CSS classes: `.wk-glass`,
  `.wk-glass-refract`. Blade component `<x-wirekit::glass />` for the layout
  head.
- **VT323 font** — bundled as 21st locally served font (OFL 1.1 license).
- **Modal and drawer headers** now auto-render a close button by default.
  The button respects the `dismissible` prop and can be disabled via
  `:close="false"` on the header. Personalizable via
  `WireKit::personalize('modal.header', ['close' => '...'])`.

### Changed

- **Complete theme overhaul** — all 7 presets rewritten with WCAG 2.2 AA
  compliance verified for every text-on-surface, button-label, and
  semantic-message pair in both light and dark mode.
- **Default theme** migrated from zinc to neutral palette (zero chroma, no blue
  cast). Inter font set as default sans. Updated shadows, letter-spacing, and
  dark-mode borders.
- **Cupertino theme** — new Apple-aesthetic preset replacing Slick. Uses
  `-apple-system` font stack, iOS ease-out easing, Apple HIG dark colors,
  `blue-600` accent, and 0.5px hairline borders.
- **Minimal theme** — rewrite: 0px radius, 2px ring, neutral-200 input
  backgrounds for visible boundaries.
- **Soft theme** — rewrite: DM Sans font, violet accent, wider blur shadows,
  ease-out easing.
- **Material theme** — rewrite: M3 standard decelerate easing, indigo accent,
  Roboto font.
- **Brutalist theme** — rewrite: neutral palette, JetBrains Mono font, explicit
  border-color tokens fixing WCAG 1.4.11 contrast failures.
- **Retro Terminal theme** — rewrite with 3 WCAG fixes: ring width 1px→2px,
  danger-fg contrast fix, explicit success/warning foreground tokens.
- **Context menu** panel now teleports to `<body>` by default, consistent with
  Modal, Drawer, and other overlay components. Opt-out via `teleport="false"`.
- **Resizable panels** now support symmetric pair-drag in 3+ panel layouts —
  dragging a handle resizes both adjacent panels proportionally, matching
  industry-standard splitter behavior.

### Fixed

- **Tour component** overlay and step panels now teleport to `<body>` via
  Alpine's `x-teleport`, matching the pattern used by Modal and Drawer. This
  ensures correct Floating UI positioning regardless of ancestor CSS transforms
  or containing blocks. Steps use `x-show` instead of `x-if` for simpler DOM
  lifecycle.
- **Tour step** no longer flickers at (0,0) on first show — a CSS fallback
  parks steps off-screen until Floating UI positions them.
- **Chart component** `style` attribute collision — caller-supplied `style` now
  merges correctly with the component's own height/background declarations.
- **Timeline** last-item trailing padding now correctly collapses when
  `after="true"` adds a continuation line.
- **Toggle** OFF track now meets WCAG 1.4.11 non-text contrast (changed from
  `bg-muted` to `border` token).
- **Checkbox and radio** hover border added for interactive discoverability on
  small 20×20 elements.
- **WCAG 2.2 AA contrast** fixes across all base tokens: `success-fg`,
  `warning-fg`, `text-subtle`, `text-placeholder`, and dark-mode `danger`
  values corrected.
- **Scroll-to-top preview** — button was invisible in documentation previews
  because `x-cloak` + `x-show` hid it before scroll events could fire. Added
  `forceVisible` prop that disables the scroll listener and keeps the button
  permanently visible.
- **Range slider** — added missing `pointercancel` event listener cleanup to
  prevent listener accumulation on interrupted drag gestures.
- **External doc link** — Liquid Glass cross-reference in theming guide changed
  from absolute URL to internal path for environment portability.

### Removed

- **Slick theme** — merged into Default. The theme set is now: Default,
  Minimal, Soft, Material, Brutalist, Retro Terminal, Cupertino.

---

## [1.0.1] — 2026-04-16

Patch release to correct bundle version headers and distribution metadata.

### Fixed

- **`dist/` version headers** bumped from `v0.2.0` to `v1.0.0` in all four
  bundles (wirekit.css, wirekit.js, wirekit.core.js, wirekit.esm.js).
- **Repository URLs** corrected across `README.md`, `CONTRIBUTING.md`, and
  `composer.json` for the Packagist distribution.

---

## [1.0.0] — 2026-04-16

First stable release of WireKit — a free, MIT-licensed UI component library for
Laravel Livewire built on Tailwind CSS v4, Alpine.js, and PHP 8.4+.

### Added

- **71 Blade components** spanning form controls, overlays, navigation, display,
  feedback, and specialized categories — every component uses design tokens
  exclusively (zero hardcoded colors), auto-switches between light and dark mode
  via `.dark` class, and supports personalization via `WireKit::personalize()`.
- **Form controls:** Input, Textarea, Select, Checkbox, Toggle, Radio Group,
  Range Slider, Color Picker, Date Picker, File Upload, Pin Input, OTP Input,
  Multi-Select, Combobox, Rich Text Editor (Tiptap).
- **Overlay components:** Modal, Drawer, Dropdown, Popover, Tooltip, Hover Card,
  Command Palette, Context Menu, Toast (with toast region), Confirm Dialog, Tour.
- **Navigation:** Navigation Menu, Breadcrumb, Pagination, Tabs, Stepper,
  Scroll-to-Top.
- **Display components:** Avatar, Badge, Card, Accordion, Timeline, Carousel,
  Image Compare, Scroll Area, Skeleton, Prose, QR Code, Clipboard Button,
  Collapsible, Separator, Resizable Panels, Tree View, Calendar, Stat Card,
  Data Table.
- **Feedback:** Alert, Progress Bar, Rating, Spinner.
- **Typography & layout:** Fonts (21 GDPR-compliant Google Fonts served locally),
  Icon system (4 presets: Heroicons, Lucide, Phosphor, Tabler with 26 semantic
  aliases), Chart (via Chart.js adapter).
- **Theming system** with ~80 CSS custom properties, 7 theme presets (Default,
  Minimal, Soft, Material, Brutalist, Retro, Cupertino), all WCAG 2.2 AA compliant
  in both light and dark mode.
- **Font system** — 21 locally bundled Google Fonts (10 sans, 5 serif, 6 mono),
  zero external requests, configurable via `config/wirekit.php`, served from the
  app's own domain for CSP and GDPR compliance.
- **Icon system** — pluggable SVG icon presets with resolved aliases, caching,
  and a `<x-wirekit::icon>` component that renders any icon by name.
- **Chart system** — adapter-based architecture with a Chart.js adapter, a
  class-based `<x-wirekit-chart>` component, and dark-mode-aware color tokens.
- **JavaScript bundles:** full bundle (Floating UI + focus-trap, ~76 KB),
  core bundle (chart + image-compare only, ~7.5 KB), and ESM bundle for
  tree-shaking.
- **Accessibility:** every interactive component follows WAI-ARIA Authoring
  Practices — proper `role`, `aria-*` attributes, keyboard navigation, focus
  management, screen-reader announcements via `aria-live` regions. Tour and
  modal use `role="dialog"`, combobox implements full ARIA 1.2 combobox pattern,
  all form components support error and description associations.
- **Personalization system** — three levels of customization: CSS variable
  overrides (theme presets), `WireKit::personalize()` for global class overrides,
  and `WireKit::scope()` for per-instance class overrides.
- **Reduced-motion support** — `@media (prefers-reduced-motion: reduce)` disables
  all WireKit animations and transitions, including skeleton pulse, progress bar
  indeterminate, and Alpine x-transition durations.

### Browser Support

Chrome 111+, Edge 111+, Safari 16.4+, Firefox 128+ — matching the Tailwind CSS v4
browser baseline.
