<?php

declare(strict_types=1);

namespace Pushery\WireKit\Mcp;

use Pushery\WireKit\ComponentRegistry;

/**
 * Read-only catalog the MCP server exposes to AI coding assistants.
 *
 * It is sourced ENTIRELY from surfaces that ship in the Packagist tarball —
 * `ComponentRegistry` (props via the PropsParser-backed `extractProps`) and the
 * compiled `dist/wirekit.css` design tokens. It deliberately reads NOTHING from
 * `docs/` (export-ignored — absent in a real `composer require` install) and
 * never re-implements prop parsing (the PropsParser caller-drift guard forbids
 * regex `@props` scanners; this routes through the canonical registry instead).
 */
final class McpCatalog
{
    /**
     * The component catalog.
     *
     * Sub-components ride along on their parent's entry rather than as entries of
     * their own: they ARE part of the API an agent must reach for, but they are
     * not components in the sense the counts in this project use the word, and
     * listing them flat would take "173 components" to 243 without one new
     * component shipping. Naming them on the parent means an agent listing the
     * catalog SEES that card has a body — the thing it needs to know — and can
     * then ask for `card.body` directly.
     *
     * @return list<array{name: string, category: string, description: string, sub_components?: list<string>}>
     */
    public function components(): array
    {
        $out = [];
        foreach (ComponentRegistry::all() as $name => $meta) {
            $entry = [
                'name' => $name,
                'category' => $meta['category'] ?? 'Other',
                'description' => $meta['description'] ?? '',
            ];

            $subs = ComponentRegistry::subComponentsOf($name);

            if ($subs !== []) {
                $entry['sub_components'] = $subs;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * Substring search across name / category / description.
     *
     * @return list<array{name: string, category: string, description: string}>
     */
    public function searchComponents(string $query, int $limit = 20): array
    {
        $query = trim(mb_strtolower($query));
        $limit = max(1, min($limit, 100));

        if ($query === '') {
            return array_slice($this->components(), 0, $limit);
        }

        $matches = array_values(array_filter(
            $this->components(),
            // The sub-component names are part of what a parent matches on: an
            // agent searching "card.body" or "th" should land on the component
            // that carries it rather than on nothing.
            static fn (array $c): bool => str_contains(mb_strtolower($c['name']), $query)
                || str_contains(mb_strtolower($c['category']), $query)
                || str_contains(mb_strtolower($c['description']), $query)
                || str_contains(mb_strtolower(implode(' ', $c['sub_components'] ?? [])), $query),
        ));

        return array_slice($matches, 0, $limit);
    }

    /**
     * Full detail for one component: metadata + the declared props (name,
     * default, and the inline allowed-value comment, which is exactly what an
     * editor wants for autocomplete).
     *
     * @return array{name: string, category: string, description: string, tag: string, props: list<array{name: string, default: ?string, comment: ?string}>}|null
     */
    public function getComponent(string $name): ?array
    {
        // resolve() answers for BOTH shapes — `card` and `card.body`. Asking only
        // for top-level components returned null for every sub-component, which an
        // agent reads as "no such component"; it then puts content directly into
        // <x-wirekit::card> instead of card.body — the exact mistake the shipped
        // AGENTS.md spends a paragraph preventing. Telling an agent to use a thing
        // and then denying it exists is the worst of both.
        $meta = ComponentRegistry::resolve($name);
        if ($meta === null) {
            return null;
        }

        $props = [];
        foreach (ComponentRegistry::extractProps($name) as $prop) {
            $props[] = [
                'name' => $prop['name'],
                'default' => $prop['default'] ?? null,
                'comment' => $prop['comment'] ?? null,
            ];
        }

        return [
            'name' => $name,
            'category' => $meta['category'] ?? 'Other',
            'description' => $meta['description'] ?? '',
            // Derive the tag from the registry — the interpolated "<x-wirekit::{name}>"
            // form is wrong for a class-based component like `chart`, whose real tag is
            // the hyphenated <x-wirekit-chart> (WIRE-209).
            'tag' => ComponentRegistry::tag($name),
            'props' => $props,
        ] + (isset($meta['parent']) ? ['parent' => $meta['parent']] : []);
    }

    /**
     * Every `--*-wk-*` design token defined in the shipped `dist/wirekit.css`
     * `:root` block, as name → value pairs.
     *
     * @return list<array{name: string, value: string}>
     */
    public function tokens(): array
    {
        $cssPath = $this->distCssPath();
        if ($cssPath === null || ! is_file($cssPath)) {
            return [];
        }

        $css = (string) file_get_contents($cssPath);
        if (! preg_match_all('/^\s*(--[a-z0-9]+-wk-[a-z0-9-]+)\s*:\s*([^;]+);/m', $css, $m, PREG_SET_ORDER)) {
            return [];
        }

        $seen = [];
        $out = [];
        foreach ($m as $match) {
            $tokenName = $match[1];
            if (isset($seen[$tokenName])) {
                continue;
            }
            $seen[$tokenName] = true;
            $out[] = ['name' => $tokenName, 'value' => trim($match[2])];
        }

        return $out;
    }

    /**
     * Resolve the shipped `dist/wirekit.css`. From `src/Mcp/` the package root
     * is two levels up; falls back to the published-asset location so the
     * server also works when only published assets are present.
     */
    private function distCssPath(): ?string
    {
        $packageCss = \dirname(__DIR__, 2).'/dist/wirekit.css';
        if (is_file($packageCss)) {
            return $packageCss;
        }

        if (\function_exists('public_path')) {
            $published = public_path('vendor/wirekit/wirekit.css');
            if (is_file($published)) {
                return $published;
            }
        }

        return null;
    }
}
