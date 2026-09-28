# WireKit

WireKit (`pushery/wirekit`) is a UI component library for Laravel Livewire. Its components are
Blade components with a double-colon prefix, `<x-wirekit::button>`, never a single colon. The
chart is a class component and takes a hyphen: `<x-wirekit-chart>`.

## Look a component up before writing it

- `php artisan wirekit:show <name>` prints its props, slots, sub-components and documentation
  link, for the installed version.
- `php artisan wirekit:list` lists every component by category.
- The WireKit MCP server, `php artisan wirekit:mcp-serve`, serves the same catalog as tools. Ask
  `get_component_examples` for reviewed usage before composing a component you have not used yet.

## Conventions

- Color comes from the `intent` and `surface` props and the `--color-wk-*` design tokens. Do not
  hardcode colors, do not use Tailwind palette classes such as `gray-*`, and do not use the `dark:`
  prefix in WireKit markup: the tokens switch under the `.dark` class.
- Lay out with `stack`, `row` and `grid` and their `gap` prop. Components carry no outer margin,
  so do not add `space-y-*` or `mb-*` around them.
- `card` has no padding of its own. Put its content in `card.body`, `card.header` or `card.footer`.
- An icon is `<x-wirekit::icon name="…" size="…" />`. A `button` takes one through its `iconLeft`
  or `iconRight` slot; it has no `icon` prop.
- Form fields bind with `wire:model` and show a validation message through their `error` prop.

Documentation: <https://docs.wirekit.app>. The `wirekit-development` skill carries worked examples.
