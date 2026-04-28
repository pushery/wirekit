<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;

/**
 * emit a `/blocks.json` machine-readable manifest of every
 * layout + blueprint with its frontmatter metadata. Consumed by the docs-app's
 * `/blocks` gallery UI for filterable browsing.
 *
 * Output shape:
 *   {
 *     version: "1.x.x",
 *     generated_at: ISO-8601,
 *     count: N,
 *     blocks: [
 *       {
 *         slug, title, description, category, tags, dependencies,
 *         responsive, dark_compatible, kind ('layout' | 'blueprint'),
 *         preview_url, source_url
 *       }
 *     ]
 *   }
 *
 * Output is XSS-safe: `JSON_HEX_TAG` is set so user-controlled string
 * values containing `</script>` cannot break out of a consuming
 * `<script>` block.
 */
class ExportBlocksCommand extends Command
{
    protected $signature = 'wirekit:export-blocks
        {--pretty : Pretty-print (multi-line) output}';

    protected $description = 'Emit a machine-readable JSON manifest of every layout + blueprint';

    public function handle(): int
    {
        $packageRoot = realpath(__DIR__.'/../..');
        if ($packageRoot === false) {
            $this->error('Could not resolve package root.');

            return self::FAILURE;
        }

        $blocks = array_merge(
            $this->scanDirectory($packageRoot, 'layouts'),
            $this->scanDirectory($packageRoot, 'blueprints'),
        );

        usort($blocks, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        $payload = [
            'version' => $this->detectVersion($packageRoot),
            'generated_at' => date('c'),
            'count' => count($blocks),
            'blocks' => $blocks,
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG;
        if ($this->option('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $this->line(json_encode($payload, $flags));

        return self::SUCCESS;
    }

    private function detectVersion(string $packageRoot): string
    {
        $composerPath = $packageRoot.'/composer.json';
        if (! file_exists($composerPath)) {
            return 'unknown';
        }
        $manifest = json_decode((string) file_get_contents($composerPath), true);

        return is_array($manifest) && isset($manifest['version']) ? (string) $manifest['version'] : 'dev-develop';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanDirectory(string $packageRoot, string $kind): array
    {
        $base = $packageRoot.'/docs/'.$kind;
        if (! is_dir($base)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $blocks = [];
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'md') {
                continue;
            }
            // Skip indexes + partials (composition fragments, not whole blocks)
            if ($file->getBasename() === 'index.md') {
                continue;
            }
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR)) {
                continue;
            }

            $relativePath = ltrim(str_replace($base, '', $file->getPathname()), '/\\');
            $slug = $kind.'/'.preg_replace('/\.md$/', '', $relativePath);
            $slug = str_replace(DIRECTORY_SEPARATOR, '/', (string) $slug);

            $frontmatter = $this->parseFrontmatter($file->getPathname());
            if ($frontmatter === null) {
                continue;
            }

            $blocks[] = [
                'slug' => $slug,
                'kind' => rtrim($kind, 's'),  // 'layouts' → 'layout'
                'title' => $frontmatter['title'] ?? null,
                'description' => $frontmatter['description'] ?? null,
                'category' => $frontmatter['category'] ?? null,
                'tags' => $frontmatter['tags'] ?? [],
                'dependencies' => $frontmatter['dependencies'] ?? [],
                'responsive' => $frontmatter['responsive'] ?? null,
                'dark_compatible' => $frontmatter['dark_compatible'] ?? null,
                // Surfaced so the docs-app can filter the gallery by per-request
                // session tier (guest sessions never see admin-only blocks).
                'visibility' => $frontmatter['visibility'] ?? 'guest',
                'draft' => $frontmatter['draft'] ?? false,
                'preview_url' => 'https://docs.wirekit.app/'.$slug,
                'source_url' => 'https://github.com/pushery/wirekit/blob/develop/docs/'.$slug.'.md',
            ];
        }

        return $blocks;
    }

    /**
     * Parse the YAML-ish frontmatter at the top of a Markdown file. Only
     * supports the subset of YAML we use in WireKit docs (scalars + flat
     * lists in `[a, b, c]` notation), so we don't pull in a YAML
     * dependency for what is essentially a key/value file.
     *
     * @return array<string, mixed>|null
     */
    private function parseFrontmatter(string $path): ?array
    {
        $content = (string) file_get_contents($path);
        if (! preg_match('/^---\s*\n(.*?)\n---/s', $content, $m)) {
            return null;
        }

        $result = [];
        foreach (explode("\n", $m[1]) as $line) {
            $line = rtrim($line);
            if ($line === '' || str_starts_with(trim($line), '#')) {
                continue;
            }
            if (! preg_match('/^([a-z_]+):\s*(.*)$/', $line, $kv)) {
                continue;
            }
            [$_, $key, $value] = $kv;
            $value = trim($value, " \t'\"");

            // Flat list shorthand: [a, b, c]
            if (preg_match('/^\[(.*)\]$/', $value, $lm)) {
                $list = array_filter(array_map('trim', explode(',', $lm[1])), fn ($x) => $x !== '');
                $result[$key] = array_values(array_map(fn ($x) => trim($x, " \t'\""), $list));

                continue;
            }

            // Bool
            if ($value === 'true') {
                $result[$key] = true;

                continue;
            }
            if ($value === 'false') {
                $result[$key] = false;

                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
