<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Drift\ClassInventory;
use Pushery\WireKit\Drift\CompiledCssParser;
use Pushery\WireKit\Support\SuggestSimilar;

/**
 * Class-by-area inventory + diff analyzer.
 *
 * Lists every class across the five layers WireKit's component output
 * is built from (Blade / PHP / JS / Tailwind-compiled / WireKit-CSS-
 * declared selectors) and surfaces the diffs between any pair of them.
 *
 * Use cases:
 *   - "Which classes are emitted from PHP that Tailwind never sees?"
 *   - "Which compiled selectors have no source emission anywhere?"
 *   - "Which Blade-only classes are not yet in the compiled output?"
 *   - "How many wirekit-namespaced custom selectors does dist/wirekit.css ship?"
 *
 * Output formats:
 *   --format=summary  (default) human-readable per-area counts + 5 diffs
 *   --format=json                machine-consumable structured report
 *   --format=full                summary + first 50 entries of every diff
 */
class ClassByAreaCommand extends Command
{
    protected $signature = 'wirekit:class-by-area
        {--format=summary : Output format (summary|full|json)}
        {--area=* : Restrict the analysis to specific areas (blade, php, js, compiled, wirekit-css)}';

    protected $description = 'Inventory + diff classes across the five WireKit source layers';

    /**
     * Whether the compiled stylesheet was found and read.
     *
     * A property rather than a return value because `collectAreas()` already returns the
     * inventory, and threading a second value out of it would mean a tuple that every caller
     * has to destructure for a fact only the JSON renderer reads.
     */
    private bool $compiledCssMeasured = true;

    /**
     * The five area keys, in report order.
     *
     * Named once so the `--area` vocabulary, the `Available:` line and the suggestion
     * haystack cannot say three different things — a hand-written second copy of a list
     * is exactly how an error message comes to name a value the command does not accept.
     *
     * @var list<string>
     */
    private const AREAS = ['blade', 'php', 'js', 'compiled', 'wirekit-css'];

    public function handle(): int
    {
        $format = (string) $this->option('format');
        $filter = (array) $this->option('area');

        // Validate --format BEFORE the expensive inventory scan — collectAreas()
        // instantiates ClassInventory and parses every Blade/PHP/JS file plus the
        // compiled CSS, so a typo'd value should fail fast, not after that cost.
        // The same way --area errors below and every other command validates
        // --as/--format. Without this the `match` default arm silently swallowed
        // an unknown value as `summary` (exit 0) — a typo'd --format=jsom in CI
        // would yield summary text instead of the JSON asked for.
        if (! in_array($format, ['summary', 'full', 'json'], true)) {
            $this->error("Unknown --format value: {$format}. Available: summary, full, json");

            /*
             * The Levenshtein hint, which `--area` below has and this arm did not — while the
             * comment above it cites `--format` as the example every sibling follows. So the
             * flag held up as the reference was the one missing the behavior, and a developer
             * who typed `--format=jsom` got a list where `--area` would have said "Did you
             * mean json?".
             */
            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein($format, ['summary', 'full', 'json'])
            );

            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        // --area gets the SAME treatment, and until now it did not: an unknown value fell
        // through to the intersection below and produced "No areas match the --area filter."
        // — a sentence that names neither the accepted values nor the closest one, from a
        // repeatable flag where a typo is the likeliest way to reach it. Every sibling in
        // the family answers an unknown enum with an `Available:` list and a Levenshtein
        // hint, the reference page states that as the family contract, and the guard beside
        // the --format case asserted this command already did it.
        //
        // Validated here rather than after collectAreas() for the reason stated above it:
        // the scan parses every Blade, PHP and JS file plus the compiled CSS, and a typo
        // should not have to pay for that first.
        $unknownAreas = array_values(array_diff($filter, self::AREAS));

        if ($unknownAreas !== []) {
            $this->error(sprintf(
                'Unknown --area value: %s. Available: %s',
                implode(', ', $unknownAreas),
                implode(', ', self::AREAS),
            ));

            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein((string) reset($unknownAreas), self::AREAS)
            );
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        $projectRoot = base_path('vendor/pushery/wirekit');
        if (! is_dir($projectRoot)) {
            // Running from inside the package itself
            $projectRoot = dirname(__DIR__, 2);
        }

        $areas = $this->collectAreas($projectRoot, $format);

        // ⚠️ `--area` says which rows are ASKED FOR. It must never say which operands EXIST.
        //
        // The filter used to prune `$areas` itself, and diffPairs() reads its operands back
        // out with `$areas['compiled'] ?? []` — so a filtered-away layer arrived as an EMPTY
        // SET rather than as an absent question, and every one of the five diff rows came out
        // falsified, in both directions, with nothing in the output saying a set had been
        // emptied. Measured under `--area=blade`: `blade ∖ compiled` reported 1272 against a
        // true 16, while the three real gaps (928, 152, 190) all reported 0 — which a reader
        // takes as clean. The documented `--area=blade --area=compiled` example was wrong the
        // same way, and `--format=json`, which the reference page pitches for CI dashboards,
        // carried the same numbers.
        //
        // It is the failure mode collectAreas() already warns about from a different cause:
        // an empty compiled column and a compiled column that was never read look identical.
        //
        // So the operands stay whole and the SCOPE decides which rows can be computed at all.
        // A row whose operands are not all in scope is SUPPRESSED and says why — a suppressed
        // row cannot be misread, an emptied one can.
        $scope = $filter === [] ? self::AREAS : $filter;
        $scoped = array_intersect_key($areas, array_flip($scope));

        // Unreachable while the --area validation above holds — every accepted value is a key
        // collectAreas() returns. Kept fail-closed against the two lists drifting apart, which
        // is exactly the drift that would otherwise print an inventory of nothing.
        if ($scoped === []) {
            $this->error('No areas match the --area filter.');

            return self::FAILURE;
        }

        return match ($format) {
            'json' => $this->renderJson($areas, $scope),
            'full' => $this->renderFull($areas, $scope),
            default => $this->renderSummary($areas, $scope),
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function collectAreas(string $projectRoot, string $format): array
    {
        $inventory = new ClassInventory(
            projectRoot: $projectRoot,
            // Empty skip lists so this report is COMPLETE — not the
            // pre-filtered audit view. The user is asking "what's in
            // each area?", they want every candidate.
            skipPathPrefixesForClassExtraction: [],
            skipPathPrefixesForTokenReferences: [],
        );

        $blade = array_keys($inventory->bladeClasses());
        $php = array_keys($inventory->phpEmittedClasses());
        $js = array_keys($inventory->jsEmittedClasses());

        sort($blade);
        sort($php);
        sort($js);

        $compiled = [];
        $compiledCss = $this->locateCompiledCss($projectRoot);
        if ($compiledCss !== null) {
            $compiled = CompiledCssParser::extractGeneratedSelectors($compiledCss);
            sort($compiled);
        } else {
            $this->compiledCssMeasured = false;

            /*
             * Say so — an empty compiled column and a compiled column that was never read look
             * identical in the output, and the second one silently reports every class as
             * un-emitted.
             *
             * ⚠️ NOT ON THE JSON PATH. Laravel's console components write to STDOUT, so this
             * line landed in front of the document `renderJson()` emits four steps later, and
             * `jq` aborts on it while the command still exits 0. The reference page sells
             * `--format=json` for CI dashboards and audit-history pipelines, and the condition
             * that triggers this is the ordinary state of a fresh checkout — the exact moment
             * somebody wires the report up for the first time.
             *
             * The absence is not dropped, it MOVES: `compiled_measured` travels in the
             * document, which is the shape the diff rows already use one layer further out.
             */
            if ($format !== 'json') {
                $this->components->warn(
                    'No compiled CSS found, so the compiled column is empty rather than measured. '.
                    'Run your asset build first (it is looked for at public/build/assets/*.css).'
                );
            }
        }

        $wirekitCssSelectors = $this->extractCustomCssSelectors($projectRoot.'/dist/wirekit.css');
        sort($wirekitCssSelectors);

        return [
            'blade' => $blade,
            'php' => $php,
            'js' => $js,
            'compiled' => $compiled,
            'wirekit-css' => $wirekitCssSelectors,
        ];
    }

    /**
     * The custom CSS selectors that ship from dist/wirekit.css —
     * selectors of `.foo { … }` rules in the file (the wk-* BEM
     * classes used by reading-progress, scrollbar, etc.). Distinct
     * from the Tailwind-compiled output which dist/wirekit.css does
     * NOT contain.
     *
     * @return list<string>
     */
    private function extractCustomCssSelectors(string $cssPath): array
    {
        if (! file_exists($cssPath)) {
            return [];
        }

        return CompiledCssParser::extractGeneratedSelectors($cssPath);
    }

    /**
     * The post-Tailwind stylesheet to compare the source inventory against.
     *
     * ⚠️ THE ONLY LOCATION THIS LOOKED IN WAS `sample/public/build/assets/app-*.css`, WHICH
     * NO DEVELOPER INSTALL CONTAINS — `sample/` is export-ignored, so on a real installation
     * the glob matched nothing, `$compiled` stayed empty, and the command reported its
     * compiled-CSS column as blank without saying why. Every class looked un-emitted.
     *
     * A developer's own build output is the artifact that answers the same question for
     * them, so it is looked for first. The package's own sample is the fallback, for a run
     * from inside this repository.
     */
    private function locateCompiledCss(string $projectRoot): ?string
    {
        $candidates = [];

        // The adopting application's Vite output, which is what a developer actually has.
        if (function_exists('base_path')) {
            $candidates[] = base_path('public/build/assets/*.css');
        }

        // This repository's sample app, for a run from inside the package.
        $candidates[] = $projectRoot.'/sample/public/build/assets/app-*.css';

        foreach ($candidates as $pattern) {
            $matches = glob($pattern);

            if ($matches !== false && count($matches) > 0) {
                return $matches[0];
            }
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $areas  every layer, unpruned — see handle()
     * @param  list<string>  $scope  the layers --area asked for
     */
    private function renderSummary(array $areas, array $scope): int
    {
        $this->line('');
        $this->line('<fg=cyan>Class-by-Area Inventory</>');
        $this->line(str_repeat('─', 60));

        foreach ($this->scopedAreas($areas, $scope) as $name => $classes) {
            $this->line(sprintf(
                '  <fg=yellow>%-15s</> %5d classes',
                $name,
                count($classes),
            ));
        }

        $this->line('');
        $this->line('<fg=cyan>Inter-area diffs</>');
        $this->line(str_repeat('─', 60));

        foreach ($this->diffPairs($areas, $scope) as $row) {
            if ($row['outOfScope'] !== []) {
                $this->line(sprintf(
                    '  <fg=yellow>%-50s</>     — not computed, %s outside --area',
                    $row['label'],
                    implode(', ', $row['outOfScope']),
                ));

                continue;
            }

            $sample = $row['entries'][0] ?? '';
            $sampleHint = $sample !== '' ? ' (e.g. '.$sample.')' : '';
            $this->line(sprintf(
                '  <fg=yellow>%-50s</> %5d%s',
                $row['label'],
                count($row['entries']),
                $sampleHint,
            ));
        }

        $this->line('');
        $this->line('Re-run with --format=full for the first 50 entries of every diff.');
        $this->line('Re-run with --format=json for machine-consumable structured output.');
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $areas  every layer, unpruned — see handle()
     * @param  list<string>  $scope  the layers --area asked for
     */
    private function renderFull(array $areas, array $scope): int
    {
        $this->renderSummary($areas, $scope);

        $this->line('<fg=cyan>Per-area class lists (first 50 each)</>');
        $this->line(str_repeat('─', 60));
        foreach ($this->scopedAreas($areas, $scope) as $name => $classes) {
            $this->line('');
            $this->line(sprintf('<fg=yellow>%s</> (%d total)', $name, count($classes)));
            foreach (array_slice($classes, 0, 50) as $class) {
                $this->line('  '.$class);
            }
            if (count($classes) > 50) {
                $this->line(sprintf('  … +%d more', count($classes) - 50));
            }
        }
        $this->line('');

        $this->line('<fg=cyan>Diff details (first 50 entries each)</>');
        $this->line(str_repeat('─', 60));
        foreach ($this->diffPairs($areas, $scope) as $row) {
            $this->line('');

            if ($row['outOfScope'] !== []) {
                $this->line(sprintf(
                    '<fg=yellow>%s</> — not computed, %s outside --area',
                    $row['label'],
                    implode(', ', $row['outOfScope']),
                ));

                continue;
            }

            $this->line(sprintf('<fg=yellow>%s</> (%d total)', $row['label'], count($row['entries'])));
            foreach (array_slice($row['entries'], 0, 50) as $class) {
                $this->line('  '.$class);
            }
            if (count($row['entries']) > 50) {
                $this->line(sprintf('  … +%d more', count($row['entries']) - 50));
            }
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $areas  every layer, unpruned — see handle()
     * @param  list<string>  $scope  the layers --area asked for
     */
    private function renderJson(array $areas, array $scope): int
    {
        $report = [
            // Whether the compiled column was READ, not whether it was empty. The two are
            // indistinguishable downstream, and one of them means every class looks
            // un-emitted. Carries the same fact the human formats get as a warning line.
            'compiled_measured' => $this->compiledCssMeasured,
            'areas' => array_map(
                fn (array $classes) => ['count' => count($classes), 'classes' => $classes],
                $this->scopedAreas($areas, $scope),
            ),
            'diffs' => [],
        ];

        foreach ($this->diffPairs($areas, $scope) as $row) {
            // `computed` on BOTH shapes, and a suppressed row carries no `count` at all.
            // A downstream reader that keys on `count` must not be able to read a
            // suppressed row as a measured zero — that is the whole defect this shape
            // replaces, one layer further out.
            $report['diffs'][$row['label']] = $row['outOfScope'] !== []
                ? [
                    'computed' => false,
                    'not_computed_because' => sprintf(
                        '%s outside --area',
                        implode(', ', $row['outOfScope']),
                    ),
                ]
                : [
                    'computed' => true,
                    'count' => count($row['entries']),
                    'entries' => $row['entries'],
                ];
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * The per-area report, restricted to what --area asked for, in report order.
     *
     * @param  array<string, list<string>>  $areas
     * @param  list<string>  $scope
     * @return array<string, list<string>>
     */
    private function scopedAreas(array $areas, array $scope): array
    {
        return array_filter(
            $areas,
            static fn (string $name): bool => in_array($name, $scope, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Five canonical inter-area diffs that surface real risk classes.
     * Each diff returns the SET of classes in the first area minus the
     * second.
     *
     * A row is computed only when EVERY layer it reads is in scope. Otherwise it comes
     * back with the missing operands named and no entries, and every renderer prints that
     * rather than a number: the operands are always whole here, so a zero would be a
     * measurement and must only ever be printed as one.
     *
     * @param  array<string, list<string>>  $areas  every layer, unpruned — see handle()
     * @param  list<string>  $scope  the layers --area asked for
     * @return list<array{label: string, entries: list<string>, outOfScope: list<string>}>
     */
    private function diffPairs(array $areas, array $scope): array
    {
        $blade = $areas['blade'] ?? [];
        $php = $areas['php'] ?? [];
        $js = $areas['js'] ?? [];
        $compiled = $areas['compiled'] ?? [];
        $wirekitCss = $areas['wirekit-css'] ?? [];

        /** @var list<array{0:string, 1:list<string>, 2:callable(): list<string>}> $definitions */
        $definitions = [
            ['blade ∖ compiled (Blade-emitted but Tailwind didn\'t generate)',
                ['blade', 'compiled'],
                static fn (): array => array_values(array_diff($blade, $compiled))],
            ['php ∖ compiled (PHP-emitted but Tailwind never saw)',
                ['php', 'compiled'],
                static fn (): array => array_values(array_diff($php, $compiled))],
            ['js ∖ compiled (JS-emitted but Tailwind never saw)',
                ['js', 'compiled'],
                static fn (): array => array_values(array_diff($js, $compiled))],
            ['compiled ∖ (blade ∪ php ∪ js) (compiled but no source)',
                ['compiled', 'blade', 'php', 'js'],
                static fn (): array => array_values(array_diff($compiled, array_merge($blade, $php, $js)))],
            ['wirekit-css selectors (BEM custom classes from dist/wirekit.css)',
                ['wirekit-css'],
                static fn (): array => $wirekitCss],
        ];

        $rows = [];

        foreach ($definitions as [$label, $operands, $compute]) {
            $outOfScope = array_values(array_diff($operands, $scope));

            $rows[] = [
                'label' => $label,
                // Not computed at all when suppressed — a diff nobody may read is work
                // nobody asked for, and the empty list is never mistaken for a result.
                'entries' => $outOfScope === [] ? $compute() : [],
                'outOfScope' => $outOfScope,
            ];
        }

        return $rows;
    }
}
