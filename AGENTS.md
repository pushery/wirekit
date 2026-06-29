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
- Spacing/rhythm comes from `stack` / `row` / `grid` / `section` plus their `gap`
  prop (the prop is `gap`, not `space`). Components carry no outer margins, so do
  not hand-roll `space-y-*` / `mb-*`.
- For a signed-in dashboard, compose the `app-shell` + `sidebar` + `header` +
  `main` system. `navbar` is a separate, alternative top-nav shell with its own
  mobile menu — do not nest it inside the app-shell header.
- Icons use `<x-wirekit::icon name="..." size="..." />` — never `class="h-N w-N"`.

## Full reference

- Documentation: <https://docs.wirekit.app>
- AI-tooling guide: <https://docs.wirekit.app/ai-tooling>
- Detailed authoring rules ship in `.cursor/rules/wirekit.mdc` inside the installed
  package (publish into a project with `php artisan wirekit:cursor-rules`). This
  file is the tool-neutral pointer to those rules.
