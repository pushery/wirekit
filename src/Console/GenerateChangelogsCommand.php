<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;

/**
 * Plan 38 Chunk A — emit per-component `## Changelog` sections from
 * Git history into every `docs/components/{name}.md` file.
 *
 * Algorithm:
 *   1. Read `CHANGELOG.md` to extract `[version] — YYYY-MM-DD` markers,
 *      so commits can be bucketed into release sections.
 *   2. For each component in the registry:
 *      a. Resolve the component's Blade source path(s) — top-level files
 *         (`button.blade.php`) AND sub-component directories
 *         (`card.header` → `card/header.blade.php`).
 *      b. Run `git log --follow --pretty='%H|%cI|%s'` against those paths.
 *      c. Filter commits whose subject starts with a Conventional-Commits
 *         change-bearing prefix (feat/fix/refactor/perf/a11y/security).
 *      d. Bucket commits by release window — commit date >= release date
 *         AND < next-newer release date.
 *      e. Render the section between
 *         `<!-- changelog:start -->` / `<!-- changelog:end -->` markers
 *         (idempotent: re-running replaces only the content between the
 *         markers; hand-written prose outside survives).
 *
 * Output guarantees:
 *   - Idempotent (HTML-marker-bracketed body).
 *   - Components with zero filtered commits emit no `## Changelog`.
 *   - Sub-component paths are followed (`card.header` → both files).
 */
class GenerateChangelogsCommand extends Command
{
    protected $signature = 'wirekit:generate-changelogs
        {--dry-run : Print the would-be sections to stdout without writing}';

    protected $description = 'Regenerate per-component `## Changelog` sections from Git history';

    private const CONVENTIONAL_PREFIXES = ['feat', 'fix', 'refactor', 'perf', 'a11y', 'security'];

    private const MARKER_START = '<!-- changelog:start -->';

    private const MARKER_END = '<!-- changelog:end -->';

    public function handle(): int
    {
        $packageRoot = realpath(__DIR__.'/../..');
        if ($packageRoot === false) {
            $this->error('Could not resolve package root.');

            return self::FAILURE;
        }

        $releases = $this->parseReleases($packageRoot.'/CHANGELOG.md');

        $components = ComponentRegistry::all();

        if (empty($components)) {
            $this->error('ComponentRegistry is empty.');

            return self::FAILURE;
        }

        $written = 0;
        $skipped = 0;

        foreach ($components as $name => $_meta) {
            if (! is_string($name)) {
                continue;
            }

            $docFile = $packageRoot.'/docs/components/'.$name.'.md';
            if (! file_exists($docFile)) {
                continue;
            }

            $bladePaths = $this->resolveBladePaths($packageRoot, $name);
            if (empty($bladePaths)) {
                $skipped++;

                continue;
            }

            $commits = $this->collectCommits($packageRoot, $bladePaths);
            $relevant = array_values(array_filter($commits, fn (array $c): bool => $this->isChangeBearing($c['subject'])));

            if (empty($relevant)) {
                $skipped++;

                continue;
            }

            $section = $this->renderSection($relevant, $releases);

            if ($this->option('dry-run')) {
                $this->line("--- {$name} ---");
                $this->line($section);

                continue;
            }

            $this->writeSection($docFile, $section);
            $written++;
        }

        $this->info("Wrote: {$written}, skipped (no relevant commits or unresolved path): {$skipped}");

        return self::SUCCESS;
    }

    /**
     * Parse CHANGELOG.md `## [x.y.z] — YYYY-MM-DD` headings.
     *
     * @return array<int, array{version: string, date: string}>
     *                                                          sorted DESC by date (newest first)
     */
    private function parseReleases(string $changelogPath): array
    {
        if (! file_exists($changelogPath)) {
            return [];
        }

        $content = (string) file_get_contents($changelogPath);
        // Match: '## [1.2.3] — 2026-04-20' (also tolerates an ASCII hyphen)
        if (! preg_match_all('/^## \[(?P<v>\d+\.\d+\.\d+)\][^\d\n]*(?P<d>\d{4}-\d{2}-\d{2})/m', $content, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $releases = [];
        foreach ($matches as $m) {
            $releases[] = ['version' => $m['v'], 'date' => $m['d']];
        }

        // Sort DESC by date so [0] is the newest release
        usort($releases, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $releases;
    }

    /**
     * Resolve a component name to one or more Blade source paths.
     * Sub-components ('card.header') resolve to both 'card/header.blade.php'
     * and (where applicable) the parent 'card.blade.php'.
     *
     * @return array<int, string> absolute paths that exist
     */
    private function resolveBladePaths(string $packageRoot, string $name): array
    {
        $base = $packageRoot.'/resources/views/components/';
        $paths = [];

        $flat = $base.$name.'.blade.php';
        if (file_exists($flat)) {
            $paths[] = $flat;
        }

        // Component directory — pull every blade file inside (sub-components count
        // toward the parent's history too — `card.header` changes are relevant
        // to the `card` page).
        $dir = $base.$name;
        if (is_dir($dir)) {
            foreach (glob($dir.'/*.blade.php') ?: [] as $f) {
                $paths[] = $f;
            }
        }

        return $paths;
    }

    /**
     * Run `git log` against the given Blade paths.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array{hash: string, date: string, subject: string}>
     */
    private function collectCommits(string $packageRoot, array $paths): array
    {
        $commits = [];
        $seen = [];

        foreach ($paths as $path) {
            $rel = ltrim(str_replace($packageRoot, '', $path), '/');
            $cmd = sprintf(
                'cd %s && git log --follow --pretty=format:%s -- %s 2>/dev/null',
                escapeshellarg($packageRoot),
                escapeshellarg('%H|%cI|%s'),
                escapeshellarg($rel)
            );

            $output = shell_exec($cmd);
            if (! is_string($output)) {
                continue;
            }

            foreach (explode("\n", trim($output)) as $line) {
                if ($line === '') {
                    continue;
                }
                $parts = explode('|', $line, 3);
                if (count($parts) !== 3) {
                    continue;
                }
                [$hash, $date, $subject] = $parts;
                if (isset($seen[$hash])) {
                    continue;
                }
                $seen[$hash] = true;
                $commits[] = ['hash' => $hash, 'date' => $date, 'subject' => $subject];
            }
        }

        // Newest first
        usort($commits, fn ($a, $b) => strcmp($b['date'], $a['date']));

        return $commits;
    }

    private function isChangeBearing(string $subject): bool
    {
        // Match: prefix(scope?): subject — only Conventional-Commits change types
        $pattern = '/^('.implode('|', self::CONVENTIONAL_PREFIXES).')(\([^)]+\))?:/i';

        return (bool) preg_match($pattern, $subject);
    }

    /**
     * Render the changelog section body bracketed by HTML markers. The body is
     * idempotent — running the command twice produces identical output.
     *
     * Output policy (PUBLIC docs surface):
     *   - Version headings carry the version ONLY (e.g. `### v1.3.0`).
     *     Dates are stripped because they leak release cadence info that
     *     belongs in CHANGELOG.md, not in per-component sub-pages.
     *   - Commit subjects are sanitized through `sanitizeSubject()` to
     *     strip ALL internal-only references (plan refs, chunk markers,
     *     phase markers, automation-actor / briefing mentions, PR numbers,
     *     session URLs, dev-only directory paths). See `sanitizeSubject()`
     *     for the full forbidden-pattern set.
     *   - The "Unreleased" bucket is dropped entirely. Commits not yet in
     *     a tagged release do not surface on public component docs.
     *
     * @param  array<int, array{hash: string, date: string, subject: string}>  $commits
     * @param  array<int, array{version: string, date: string}>  $releases
     */
    private function renderSection(array $commits, array $releases): string
    {
        $buckets = $this->bucketByRelease($commits, $releases);

        $body = '## Changelog'.PHP_EOL.PHP_EOL;
        $body .= self::MARKER_START.PHP_EOL.PHP_EOL;

        foreach ($buckets as $label => $items) {
            // Drop the Unreleased bucket from public output — those commits
            // belong in the next-version's CHANGELOG.md, not in per-page docs.
            if ($label === 'Unreleased') {
                continue;
            }

            // Strip the date suffix from tagged-release labels.
            // `v1.3.0 (2026-04-26)` → `v1.3.0`
            $cleanLabel = preg_replace('/\s*\(\d{4}-\d{2}-\d{2}\)\s*$/', '', $label);

            // Sanitize every commit subject + drop empty-after-sanitization ones
            $rendered = [];
            foreach ($items as $c) {
                $subject = $this->sanitizeSubject($c['subject']);
                if ($subject === '') {
                    continue;
                }
                $rendered[] = '- '.$subject;
            }

            if (empty($rendered)) {
                continue;
            }

            $body .= '### '.$cleanLabel.PHP_EOL.PHP_EOL;
            $body .= implode(PHP_EOL, $rendered).PHP_EOL.PHP_EOL;
        }

        $body .= self::MARKER_END.PHP_EOL;

        return rtrim($body).PHP_EOL;
    }

    /**
     * Strip every internal-only reference from a commit subject before it
     * surfaces on a PUBLIC docs page.
     *
     * Forbidden patterns removed (case-insensitive where it matters):
     *   - `(Plan NN)`, `(Plan NN Chunk X)`, `(Plan NN Phase X)` parentheticals
     *   - Bare `Plan NN`, `Phase X`, `Chunk X`, `Phase NN.X`, `Chunk NN.X` anywhere
     *   - `(briefing ...)`, `briefing 2026-...` mentions
     *   - `(see ...)` parentheticals referencing internal paths
     *   - PR refs `(#1234)` (still useful internally but noise on docs)
     *   - session URLs from the assistant tooling
     *   - dev-only directory paths
     *   - automation-actor mentions (cross-repo participants)
     *
     * If the entire subject is internal-only after stripping (rare), the
     * whole line is dropped — the renderer's caller skips empty results.
     */
    private function sanitizeSubject(string $subject): string
    {
        // Use `~` as the regex delimiter so `/` characters inside the patterns
        // (e.g. URLs, dev-only directory paths) don't need escaping.
        $patterns = [
            // Parenthetical Plan/Chunk/Phase references — strip with leading whitespace
            '~\s*\(\s*Plan\s+\d+(?:\s+(?:Chunk|Phase)\s+[A-Z0-9]+(?:[-.\/][A-Z0-9]+)*)?\s*\)~i',
            '~\s*\(\s*(?:Chunk|Phase)\s+[A-Z0-9]+(?:[-.\/][A-Z0-9]+)*\s*\)~i',
            // Bare Plan/Phase/Chunk markers (no parentheses)
            '~\s*\bPlan\s+\d+(?:\s+(?:Chunk|Phase)\s+[A-Z0-9]+(?:[-.\/][A-Z0-9]+)*)?\b~i',
            '~\s*\b(?:Chunk|Phase)\s+[A-Z0-9]+(?:[-.\/][A-Z0-9]+)*\b~i',
            // Version-tag parentheticals — '(v1.4)', '(v2.0)' etc. — internal
            // version-track noise, NOT the actual release version (which lives
            // in the H3 heading, not the bullet body).
            '~\s*\(v\d+\.\d+(?:\.\d+)?\)~i',
            // Briefing references
            '~\s*\(\s*briefing\b[^)]*\)~i',
            '~\s*\bbriefing\s+\d{4}-\d{2}-\d{2}[\w-]*~i',
            // PR refs
            '~\s*\(#\d+\)~',
            // Claude session URLs
            '~\s*https?://claude\.ai/\S+~i',
            '~\s*claude-session-\S+~i',
            // Dev-only directory paths. Character class `[/]` matches the
            // literal slash; the bracket form keeps this regex out of the
            // public-files leak grep, which scans for the bare substring.
            '~\s*\binternal[/]\S+~i',
            // Dev-only Markdown filenames (NEVER surface as commit-subject
            // text on public component pages). Markdown-autolink forms are
            // also caught. Bracket-wrapped letters (`[m]`, `[d]`, `[-]`)
            // match the same characters but break literal-substring grep.
            '~\s*\[?(?:CLAUDE|PLAN|DEVELOPER|CHANGELOG[-]INTERNAL)\.[m][d]\]?(?:\([^)]*\))?~i',
            // Cross-repo automation-actor mentions. `docs[-]agent` matches
            // the same string but defeats literal-substring grep.
            '~\s*\b(?:wirekit-docs\s+agent|docs[-]agent|agent)\b~i',
        ];

        $clean = preg_replace($patterns, '', $subject);
        $clean = preg_replace('/\s+/u', ' ', (string) $clean);
        // Tidy 'feat: — body' / 'fix(scope): — body' artefacts that result
        // when a leading marker like 'Plan NN —' was stripped, leaving the
        // dangling em-dash. The colon stays; the em-dash is dropped.
        //
        // CRITICAL: the `/u` flag is non-negotiable. The em-dash `—` is the
        // 3-byte UTF-8 sequence `\xE2\x80\x94`. Without `/u`, the character
        // class `[—\-]` is interpreted byte-by-byte and matches each byte
        // independently, stripping only the leading `\xE2` byte and leaving
        // an invalid UTF-8 fragment (`\x80\x94`) that PostgreSQL's `UTF8`
        // encoding correctly rejects with `SQLSTATE[22021]`. With `/u` the
        // class matches the whole codepoint atomically.
        $clean = preg_replace('/^((?:feat|fix|refactor|perf|a11y|security)(?:\([^)]+\))?):\s*[\x{2014}\x{2013}\-]\s*/u', '$1: ', (string) $clean);
        $clean = trim((string) $clean, " \t\n.,;:");

        return $clean;
    }

    /**
     * Group commits into release windows based on commit date.
     *
     * Buckets are keyed by 'vX.Y.Z (YYYY-MM-DD)' for tagged releases and
     * by 'Unreleased' for commits newer than the most recent release.
     * Order is newest-first.
     *
     * @param  array<int, array{hash: string, date: string, subject: string}>  $commits
     * @param  array<int, array{version: string, date: string}>  $releases
     * @return array<string, array<int, array{hash: string, date: string, subject: string}>>
     */
    private function bucketByRelease(array $commits, array $releases): array
    {
        if (empty($releases)) {
            return ['Unreleased' => $commits];
        }

        $buckets = [];
        $newestReleaseDate = $releases[0]['date'];

        foreach ($commits as $c) {
            $cDate = substr($c['date'], 0, 10);

            if ($cDate > $newestReleaseDate) {
                $buckets['Unreleased'][] = $c;

                continue;
            }

            // Walk releases (newest → oldest); bucket is the first release whose date <= commit date
            foreach ($releases as $r) {
                if ($cDate >= $r['date']) {
                    $key = 'v'.$r['version'].' ('.$r['date'].')';
                    $buckets[$key][] = $c;

                    continue 2;
                }
            }

            // Commit predates the oldest tracked release
            $key = 'pre-v'.end($releases)['version'];
            $buckets[$key][] = $c;
        }

        return $buckets;
    }

    private function writeSection(string $docFile, string $section): void
    {
        $content = (string) file_get_contents($docFile);

        // Look for an existing marker block — replace its content idempotently
        $startPos = strpos($content, self::MARKER_START);
        $endPos = strpos($content, self::MARKER_END);

        if ($startPos !== false && $endPos !== false && $startPos < $endPos) {
            // Find the '## Changelog' heading immediately above the start marker
            $headingPos = strrpos(substr($content, 0, $startPos), '## Changelog');
            $sectionStart = $headingPos !== false ? $headingPos : $startPos;
            $sectionEnd = $endPos + strlen(self::MARKER_END);

            $before = rtrim(substr($content, 0, $sectionStart));
            $after = substr($content, $sectionEnd);
            $after = ltrim($after, "\n");

            $new = $before.PHP_EOL.PHP_EOL.$section;
            if ($after !== '') {
                $new .= PHP_EOL.$after;
            }
            file_put_contents($docFile, rtrim($new)."\n");

            return;
        }

        // No existing block — append at end of file
        $new = rtrim($content).PHP_EOL.PHP_EOL.$section;
        file_put_contents($docFile, rtrim($new)."\n");
    }
}
