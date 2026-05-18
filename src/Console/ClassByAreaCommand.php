<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Drift\ClassInventory;
use Pushery\WireKit\Drift\CompiledCssParser;

/**
 * Class-by-area inventory + diff analyser.
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

    public function handle(): int
    {
        $projectRoot = base_path('vendor/pushery/wirekit');
        if (! is_dir($projectRoot)) {
            // Running from inside the package itself
            $projectRoot = dirname(__DIR__, 2);
        }

        $areas = $this->collectAreas($projectRoot);

        $format = (string) $this->option('format');
        $filter = (array) $this->option('area');

        if ($filter !== []) {
            $areas = array_intersect_key($areas, array_flip($filter));
            if ($areas === []) {
                $this->error('No areas match the --area filter.');

                return self::FAILURE;
            }
        }

        return match ($format) {
            'json' => $this->renderJson($areas),
            'full' => $this->renderFull($areas),
            default => $this->renderSummary($areas),
        };
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function collectAreas(string $projectRoot): array
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

    private function locateCompiledCss(string $projectRoot): ?string
    {
        // Prefer the sample app's build output — that's the canonical
        // post-Tailwind artefact this repo's audit references.
        $matches = glob($projectRoot.'/sample/public/build/assets/app-*.css');
        if ($matches !== false && count($matches) > 0) {
            return $matches[0];
        }

        return null;
    }

    /**
     * @param  array<string, list<string>>  $areas
     */
    private function renderSummary(array $areas): int
    {
        $this->line('');
        $this->line('<fg=cyan>Class-by-Area Inventory</>');
        $this->line(str_repeat('─', 60));

        foreach ($areas as $name => $classes) {
            $this->line(sprintf(
                '  <fg=yellow>%-15s</> %5d classes',
                $name,
                count($classes),
            ));
        }

        $this->line('');
        $this->line('<fg=cyan>Inter-area diffs</>');
        $this->line(str_repeat('─', 60));

        foreach ($this->diffMatrix($areas) as [$label, $count, $sample]) {
            $sampleHint = $sample !== '' ? ' (e.g. '.$sample.')' : '';
            $this->line(sprintf(
                '  <fg=yellow>%-50s</> %5d%s',
                $label,
                $count,
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
     * @param  array<string, list<string>>  $areas
     */
    private function renderFull(array $areas): int
    {
        $this->renderSummary($areas);

        $this->line('<fg=cyan>Per-area class lists (first 50 each)</>');
        $this->line(str_repeat('─', 60));
        foreach ($areas as $name => $classes) {
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
        foreach ($this->diffPairs($areas) as [$label, $entries]) {
            $this->line('');
            $this->line(sprintf('<fg=yellow>%s</> (%d total)', $label, count($entries)));
            foreach (array_slice($entries, 0, 50) as $class) {
                $this->line('  '.$class);
            }
            if (count($entries) > 50) {
                $this->line(sprintf('  … +%d more', count($entries) - 50));
            }
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $areas
     */
    private function renderJson(array $areas): int
    {
        $report = [
            'areas' => array_map(
                fn (array $classes) => ['count' => count($classes), 'classes' => $classes],
                $areas,
            ),
            'diffs' => [],
        ];

        foreach ($this->diffPairs($areas) as [$label, $entries]) {
            $report['diffs'][$label] = [
                'count' => count($entries),
                'entries' => $entries,
            ];
        }

        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @param  array<string, list<string>>  $areas
     * @return list<array{0:string, 1:int, 2:string}>
     */
    private function diffMatrix(array $areas): array
    {
        $rows = [];
        foreach ($this->diffPairs($areas) as [$label, $entries]) {
            $rows[] = [$label, count($entries), $entries[0] ?? ''];
        }

        return $rows;
    }

    /**
     * Five canonical inter-area diffs that surface real risk classes.
     * Each diff returns the SET of classes in the first area minus the
     * second.
     *
     * @param  array<string, list<string>>  $areas
     * @return list<array{0:string, 1:list<string>}>
     */
    private function diffPairs(array $areas): array
    {
        $blade = $areas['blade'] ?? [];
        $php = $areas['php'] ?? [];
        $js = $areas['js'] ?? [];
        $compiled = $areas['compiled'] ?? [];
        $wirekitCss = $areas['wirekit-css'] ?? [];

        return [
            ['blade ∖ compiled (Blade-emitted but Tailwind didn\'t generate)',
                array_values(array_diff($blade, $compiled))],
            ['php ∖ compiled (PHP-emitted but Tailwind never saw)',
                array_values(array_diff($php, $compiled))],
            ['js ∖ compiled (JS-emitted but Tailwind never saw)',
                array_values(array_diff($js, $compiled))],
            ['compiled ∖ (blade ∪ php ∪ js) (compiled but no source)',
                array_values(array_diff($compiled, array_merge($blade, $php, $js)))],
            ['wirekit-css selectors (BEM custom classes from dist/wirekit.css)',
                $wirekitCss],
        ];
    }
}
