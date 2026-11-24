# WireKit — guidance for AI coding assistants

WireKit is a free, MIT-licensed UI component library for Laravel Livewire
(`pushery/wirekit`, namespace `Pushery\WireKit`, component prefix
`<x-wirekit::*>`). If you are an AI coding assistant generating Blade, CSS, or
PHP for a project that depends on WireKit, read this first — it is the
tool-neutral entry point, discoverable without a Cursor-specific config.

## Discover the API before authoring — do not guess

Run these from the project root for authoritative, version-matched data instead
of grepping `vendor/`:

```bash
php artisan wirekit:list                  # every component, grouped by category
php artisan wirekit:show <component>      # props, slots, sub-components, docs URL
php artisan wirekit:icons                 # every icon alias, grouped by preset
php artisan wirekit:export-json --pretty  # full machine-readable component manifest
```

`php artisan wirekit:install` also writes a `.wirekit-schema.json` manifest to the
project root — read it for zero-configuration autocomplete. For an MCP-native
editor, the WireKit MCP server exposes the same catalog as live tools.

## Core conventions

- Components use the double-colon anonymous-namespace syntax —
  `<x-wirekit::button>`, never a single colon.
- `card` is a frame with no intrinsic padding — put content inside
  `<x-wirekit::card.body>` (or `card.header` / `card.footer`).
- Color comes from the Intent × Surface variant system plus the `--*-wk-*` design
  tokens. Never hardcode colors, never use Tailwind palette classes (`gray-*`,
  `zinc-*`), and never use the `dark:` prefix inside WireKit markup — tokens
  auto-switch under the `.dark` class.
- Spacing/rhythm comes from `stack` / `row` / `grid` plus their `gap` prop (the
  prop is `gap`, not `space`); a page band is a `section`, spaced by its `padding`
  prop. Components carry no outer margins, so do not hand-roll `space-y-*` / `mb-*`.
- For a signed-in dashboard, compose the `app-shell` + `sidebar` + `header` +
  `main` system. `navbar` is a separate, alternative top-nav shell with its own
  mobile menu — do not nest it inside the app-shell header.
- Icons use `<x-wirekit::icon name="..." size="..." />` — never `class="h-N w-N"`.
  To put an icon inside a `button` or `link`, use the `iconLeft` / `iconRight`
  slots — `button` has NO `icon` prop (a stray `icon="…"` renders nothing and
  leaks a dead attribute). `icon` IS a prop on ~20 other components (e.g.
  `sidebar.item`), so don't carry the attribute form over to `button` / `badge`.
- Buttons are filled. An action takes its rank from `intent`: the main one in the
  accent, another `intent="neutral"`, a destructive one `intent="danger"`. A
  button with words sets no `surface`: an outline, soft or ghost button beside a
  filled one is a second style, not a lower rank. Three kinds of control may wear
  another surface, because it says something other than a rank: one half of a
  toggle (`aria-pressed`), a button with an icon alone (`icon-only`), and a
  control that opens something in place, a disclosure (`aria-expanded`) or the
  trigger of a dropdown or a popover. A link in running text is `x-wirekit::link`.
- The content of a screen stands in cards, one per part. A card of settings has a
  head: `card.header` with an icon, the name of the part and a one-sentence
  `description` of what it is for.
- A date or a time a reader sees stays on one line, in an element with
  `whitespace-nowrap`, so a narrow column never leaves the hour under the day.
- The actions of a table's row stand at its end, side by side: the cell that
  holds them is `table.td align="end"` or `table.td actions`.
- A destructive action asks first. A button or a menu entry that deletes,
  removes, cancels or revokes is `intent="danger"` (`danger` on an entry) and
  opens an `alert-dialog`, whose `alert-dialog.confirm` runs the action; it never
  runs it from its own `wire:click`, and `wire:confirm` asks in the browser's
  dialog instead of the kit's.
- `php artisan wirekit:doctor:props` reports a place that leaves one of the five
  conventions above as a hint.

## Full reference

- Documentation: <https://docs.wirekit.app>
- AI-tooling guide: <https://docs.wirekit.app/ai-tooling>
- Detailed authoring rules ship in `.cursor/rules/wirekit.mdc` inside the installed
  package (publish into a project with `php artisan wirekit:cursor-rules`). This
  file is the tool-neutral pointer to those rules.
