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
    /** @return list<array{name: string, category: string, description: string}> */
    public function components(): array
    {
        $out = [];
        foreach (ComponentRegistry::all() as $name => $meta) {
            $out[] = [
                'name' => $name,
                'category' => $meta['category'] ?? 'Other',
                'description' => $meta['description'] ?? '',
            ];
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
            static fn (array $c): bool => str_contains(mb_strtolower($c['name']), $query)
                || str_contains(mb_strtolower($c['category']), $query)
                || str_contains(mb_strtolower($c['description']), $query),
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
        $meta = ComponentRegistry::get($name);
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
            'tag' => "<x-wirekit::{$name}>",
            'props' => $props,
        ];
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
