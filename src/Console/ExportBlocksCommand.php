<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Support\VersionResolver;
use Pushery\WireKit\WireKit;

/**
 * Emits a `/blocks.json` machine-readable manifest of every
 * layout + blueprint with its frontmatter metadata. Consumed by the docs site's
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
        {--pretty : Pretty-print (multi-line) output}
        {--public : Emit the blocks docs.wirekit.app serves at its /blocks.json endpoint (the default emits all blocks).}';

    protected $description = 'Emit a machine-readable JSON manifest of every layout + blueprint';

    public function handle(): int
    {
        $packageRoot = realpath(__DIR__.'/../..');
        if ($packageRoot === false) {
            $this->error('Could not resolve package root.');

            return self::FAILURE;
        }

        // The sources have to BE there, and until now their absence looked like a result.
        //
        // `docs/` is `export-ignore`d, so it is not in the distributed package at all —
        // measured, not assumed: `git archive HEAD` contains no docs/ entry. In every
        // developer install this command therefore scanned two directories that do not
        // exist, merged two empty arrays, printed well-formed JSON with `"count": 0` and
        // exited 0. A developer wiring this into a build step gets a green run and an empty
        // catalog, and nothing anywhere says the input was missing rather than empty.
        //
        // An absence is not an answer. It fails now, by name, and the message says which of
        // the two states it is in — because "the package does not carry these" and "the
        // catalog is empty" want completely different reactions from whoever reads it.
        // One directory now, not two: page layouts moved under `docs/blueprints/` when the
        // two sections merged, so `docs/layouts/` no longer exists and looking for it would
        // report a package as broken for carrying exactly what it should.
        $missing = array_values(array_filter(
            ['blueprints'],
            fn (string $kind): bool => ! is_dir($packageRoot.'/docs/'.$kind),
        ));

        if ($missing !== []) {
            $this->error(sprintf(
                'The block sources are not part of the distributed package: %s missing under %s/docs/.',
                implode(' and ', $missing),
                $packageRoot,
            ));
            $this->line('');
            $this->line('This command reads the blueprint pages, and those are export-ignored —');
            $this->line('a published release does not carry them. It works in a checkout of the package');
            $this->line('repository and cannot work from a Composer install.');

            // FAILURE, not the usage-error code Symfony also offers. Every wirekit:* command
            // exits 1 on every error path — the house convention, and CliUniformityAuditTest
            // enforces it by scanning this file's TEXT, so naming the other constant here
            // would trip the guard on a comment.
            return self::FAILURE;
        }

        $blocks = $this->scanDirectory($packageRoot, 'blueprints');

        // --public: hard-filter to the public blocks at the SOURCE. A
        // public machine-readable manifest (served at /blocks.json) must NEVER
        // list a block that isn't public — non-public blocks and drafts are
        // dropped HERE so the guarantee can't depend on a downstream serve
        // remembering to filter (an absolute project rule: a public artifact
        // never carries non-public content). Without the flag the full
        // manifest (every block + its visibility field) stays available as the
        // documentation site's build input.
        if ($this->option('public')) {
            $blocks = array_values(array_filter(
                $blocks,
                fn (array $b): bool => ($b['visibility'] ?? 'guest') === 'guest'
                    && ($b['draft'] ?? false) !== true,
            ));
        }

        usort($blocks, fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']));

        $payload = [
            'version' => $this->detectVersion($packageRoot),
            // The newest RELEASED version, which is a different question from
            // `version` above: that one is the build installed here, and on a
            // deployment pinned to a development branch it is literally that branch
            // name. So it cannot be compared against a version a page claims to
            // show. This field is the comparable half — it exists because a
            // documentation page served a changelog frozen four minors back and no
            // artifact anywhere carried both sides of a comparison that would have
            // said so out loud.
            'released_version' => VersionResolver::released($packageRoot),
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

    /**
     * Resolve the running WireKit version. Delegates to VersionResolver so
     * the three export commands stay in lockstep — see VersionResolver for
     * the priority order. `$packageRoot` is preserved for backward-compat
     * with the existing call site but no longer consulted directly.
     */
    private function detectVersion(string $packageRoot): string
    {
        unset($packageRoot);

        return VersionResolver::resolve();
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
            // Skip indexes + partials + recipes (composition fragments and
            // worked-example pages — not standalone vertical-blueprint blocks).
            // Recipes live under docs/blueprints/recipes/ and carry a lighter
            // frontmatter shape without the responsive / dark_compatible /
            // category fields.
            // `page-layouts.md` joins them: it is the introduction the page-layout sections
            // brought with them when they moved under blueprints, and it was `index.md`
            // until the rename. Same kind of page, same reason to skip — it describes the
            // five shapes and links to them, and has no block of its own to export.
            if ($file->getBasename() === 'index.md' || $file->getBasename() === 'page-layouts.md') {
                continue;
            }
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'partials'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'recipes'.DIRECTORY_SEPARATOR)) {
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
                // Surfaced so a gallery can filter blocks by their visibility field.
                'visibility' => $frontmatter['visibility'] ?? 'guest',
                'draft' => $frontmatter['draft'] ?? false,
                'preview_url' => WireKit::DOCS_URL.'/'.$slug,
                // The docs site's raw-markdown route, NOT a repository URL.
                //
                // This pointed at `github.com/pushery/wirekit/blob/develop/docs/…`,
                // which cannot resolve for two independent reasons: the public
                // mirror has no `develop` branch — it carries releases, tagged
                // from main — and `docs/` is export-ignored, so the directory is
                // not in that repository at all. Both were measured, not assumed.
                //
                // The raw route is the right target rather than a corrected
                // repository path: it is the exact sibling of `preview_url` above,
                // and its visibility follows the manifest's own model, because the
                // route 404s for a gated page. So every entry in the `--public`
                // manifest resolves by construction, with no cross-repo work.
                'source_url' => WireKit::DOCS_URL.'/'.$slug.'.md',
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
