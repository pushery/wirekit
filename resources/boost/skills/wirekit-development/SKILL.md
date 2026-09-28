---
name: wirekit-development
description: "Use when building or changing Blade views that use WireKit components (<x-wirekit::*>) in a Laravel Livewire application: forms, layout, navigation, overlays, tables, charts and theming. Covers looking up props and their values, the intent and surface conventions, design tokens, and the WireKit MCP tools."
---

# WireKit Development

## Look it up, do not guess

Props and their allowed values differ from component to component, and a value outside a
component's list is reported and replaced by a default. Before writing a component:

1. Run `php artisan wirekit:show <name>` for the props, slots and sub-components of the installed
   version.
2. Ask the WireKit MCP server (`php artisan wirekit:mcp-serve`) for `get_component_examples`:
   usage that was reviewed on the documentation site.

## Forms

```blade
<x-wirekit::card>
    <x-wirekit::card.body>
        <x-wirekit::stack gap="md">
            <x-wirekit::input label="Email" type="email" wire:model="email" :error="$errors->first('email')" />
            <x-wirekit::button type="submit" intent="primary">Save</x-wirekit::button>
        </x-wirekit::stack>
    </x-wirekit::card.body>
</x-wirekit::card>
```

## Buttons and icons

`intent` sets the meaning (`primary`, `neutral`, `success`, `warning`, `danger`, `info`) and
`surface` the weight (`filled`, `outline`, `soft`, `ghost`, `link`). An icon goes into the
`iconLeft` or `iconRight` slot:

```blade
<x-wirekit::button intent="neutral" surface="outline" wire:click="export">
    <x-slot:iconLeft><x-wirekit::icon name="download" size="sm" /></x-slot:iconLeft>
    Export
</x-wirekit::button>
```

## Layout

Compose `stack` (vertical), `row` (horizontal) and `grid` with their `gap` prop instead of margin
utilities. For a signed-in dashboard, compose `app-shell`, `sidebar`, `header` and `main`.

## Theming

- `php artisan wirekit:theme <preset>` applies a theme preset to `resources/css/app.css`.
- To change a color, override its `--color-wk-*` token in `:root` and in `.dark` rather than
  restyling a component.

## Reference

The documentation at <https://docs.wirekit.app> has a page per component, with its props, slots,
accessibility notes and live examples.
