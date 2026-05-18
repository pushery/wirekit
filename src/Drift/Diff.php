<?php

declare(strict_types=1);

namespace Pushery\WireKit\Drift;

/**
 * Structured drift report combining the source-class inventory with
 * the compiled-CSS reality. Reused by every audit tier so violation
 * reports share a single shape consumable by downstream tooling
 * (release-readiness orchestrator, drift-history archive, etc.).
 *
 * @phpstan-type ForwardDriftEntry array{layer: string, class: string, file: string, line: int}
 * @phpstan-type DiffReport array{
 *     forward_drift: list<ForwardDriftEntry>,
 *     reverse_dead: list<string>,
 *     undeclared_wk_tokens: list<string>,
 *     summary: array{forward_drift: int, reverse_dead: int, undeclared_wk_tokens: int},
 * }
 */
final class Diff
{
    /**
     * Compute the full drift report for a project root + compiled CSS path.
     *
     * Forward drift: every source-emitted class missing from compiled CSS.
     * Reverse dead: every compiled selector with no source emission.
     * Undeclared wk tokens: every var(--*-wk-*) in compiled CSS not declared
     * either in compiled CSS itself or in dist/wirekit.css.
     *
     * @return DiffReport
     */
    public static function compute(
        ClassInventory $inventory,
        string $compiledCssPath,
        string $wirekitCssPath,
    ): array {
        $compiledSelectors = CompiledCssParser::extractGeneratedSelectors($compiledCssPath);
        $compiledSet = array_flip($compiledSelectors);

        $forward = self::computeForwardDrift($inventory, $compiledSet);
        $reverseDead = self::computeReverseDead($inventory, $compiledSelectors);
        $undeclaredWk = self::computeUndeclaredWirekitTokens($compiledCssPath, $wirekitCssPath);

        return [
            'forward_drift' => $forward,
            'reverse_dead' => $reverseDead,
            'undeclared_wk_tokens' => $undeclaredWk,
            'summary' => [
                'forward_drift' => count($forward),
                'reverse_dead' => count($reverseDead),
                'undeclared_wk_tokens' => count($undeclaredWk),
            ],
        ];
    }

    /**
     * Render a structured report.
     *
     * @param  DiffReport  $report
     * @param  'human'|'json'  $format  Output shape:
     *                                  - `'human'` (default): WARN-mode
     *                                  text with summary line + grouped
     *                                  sections + truncation. Verbosity
     *                                  controlled via the env var
     *                                  `WIREKIT_DRIFT_VERBOSITY` (`short`
     *                                  default, `full` lists every entry).
     *                                  - `'json'`: machine-consumable
     *                                  structured payload with optional
     *                                  repository / ref / tier metadata
     *                                  (passed via the `$context` arg).
     *                                  Shape is the cross-repo aggregator
     *                                  envelope shared with the docs site.
     * @param  array{repository?: string, ref?: string, tier?: int}  $context
     *                                                                         Optional metadata included in the
     *                                                                         JSON envelope. Ignored for `'human'`.
     */
    public static function renderReport(array $report, string $format = 'human', array $context = []): string
    {
        if ($format === 'json') {
            return self::renderJson($report, $context);
        }

        return self::renderHuman($report);
    }

    /**
     * JSON envelope per the cross-repo shared-fixture shape.
     *
     * @param  DiffReport  $report
     * @param  array{repository?: string, ref?: string, tier?: int}  $context
     */
    private static function renderJson(array $report, array $context): string
    {
        $envelope = array_merge(
            [
                'repository' => $context['repository'] ?? 'pushery/wirekit',
                'ref' => $context['ref'] ?? null,
                'tier' => $context['tier'] ?? 2,
            ],
            $report,
        );

        return (string) json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param  DiffReport  $report
     */
    private static function renderHuman(array $report): string
    {
        $verbosity = getenv('WIREKIT_DRIFT_VERBOSITY') ?: 'short';
        $forwardLimit = $verbosity === 'full' ? PHP_INT_MAX : 20;
        $reverseLimit = $verbosity === 'full' ? PHP_INT_MAX : 10;
        $tokenLimit = $verbosity === 'full' ? PHP_INT_MAX : 10;

        $lines = [];
        $lines[] = sprintf(
            'Drift summary: forward=%d  reverse-dead=%d  undeclared-wk-tokens=%d',
            $report['summary']['forward_drift'],
            $report['summary']['reverse_dead'],
            $report['summary']['undeclared_wk_tokens'],
        );

        if (count($report['forward_drift']) > 0) {
            $lines[] = '';
            $lines[] = 'Forward drift (source classes missing from compiled CSS):';
            foreach (array_slice($report['forward_drift'], 0, $forwardLimit) as $entry) {
                $lines[] = sprintf(
                    '  - [%s] %s (first emitted at %s:%d)',
                    $entry['layer'],
                    $entry['class'],
                    $entry['file'],
                    $entry['line'],
                );
            }
            if ($forwardLimit < count($report['forward_drift'])) {
                $lines[] = sprintf(
                    '  … +%d more (set WIREKIT_DRIFT_VERBOSITY=full for the complete list)',
                    count($report['forward_drift']) - $forwardLimit,
                );
            }
        }

        if (count($report['undeclared_wk_tokens']) > 0) {
            $lines[] = '';
            $lines[] = 'Undeclared wirekit tokens in compiled CSS:';
            foreach (array_slice($report['undeclared_wk_tokens'], 0, $tokenLimit) as $token) {
                $lines[] = '  - '.$token;
            }
            if ($tokenLimit < count($report['undeclared_wk_tokens'])) {
                $lines[] = sprintf(
                    '  … +%d more',
                    count($report['undeclared_wk_tokens']) - $tokenLimit,
                );
            }
        }

        if (count($report['reverse_dead']) > 0) {
            $lines[] = '';
            $lines[] = 'Reverse-dead (compiled selectors with no source):';
            foreach (array_slice($report['reverse_dead'], 0, $reverseLimit) as $sel) {
                $lines[] = '  - '.$sel;
            }
            if ($reverseLimit < count($report['reverse_dead'])) {
                $lines[] = sprintf(
                    '  … +%d more',
                    count($report['reverse_dead']) - $reverseLimit,
                );
            }
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Variant-scope marker classes that Tailwind v4 does NOT generate a
     * standalone utility rule for — they exist purely as selector anchors
     * for `group-*` / `peer-*` variants. Forward-diff would always flag
     * them (in source, never in compiled), so we exclude them upfront.
     *
     * Background: in Tailwind v4, `class="group"` enables `group-hover:foo`
     * descendant selectors via `.group:hover .group-hover\:foo { … }`.
     * The `.group` class itself never gets a `{ … }` body of its own.
     * Same shape for `.peer` and the dark-mode wrapper class `.dark`.
     */
    private const SCOPE_MARKER_CLASSES = [
        'group',
        'peer',
        'dark',
    ];

    /**
     * @param  array<string, int>  $compiledSet
     * @return list<ForwardDriftEntry>
     */
    private static function computeForwardDrift(ClassInventory $inventory, array $compiledSet): array
    {
        $sources = [
            'php' => $inventory->phpEmittedClasses(),
            'js' => $inventory->jsEmittedClasses(),
            'blade' => $inventory->bladeClasses(),
        ];

        $drift = [];
        foreach ($sources as $layer => $classes) {
            foreach ($classes as $class => $occurrences) {
                if (in_array($class, self::SCOPE_MARKER_CLASSES, true)) {
                    continue;
                }
                if (! isset($compiledSet[$class])) {
                    $first = $occurrences[0];
                    $drift[] = [
                        'layer' => $layer,
                        'class' => $class,
                        'file' => $first['file'],
                        'line' => $first['line'],
                    ];
                }
            }
        }

        return $drift;
    }

    /**
     * @param  list<string>  $compiledSelectors
     * @return list<string>
     */
    private static function computeReverseDead(ClassInventory $inventory, array $compiledSelectors): array
    {
        $sourceSet = array_flip(array_merge(
            array_keys($inventory->bladeClasses()),
            array_keys($inventory->phpEmittedClasses()),
            array_keys($inventory->jsEmittedClasses()),
        ));

        $dead = [];
        foreach ($compiledSelectors as $selector) {
            if (! isset($sourceSet[$selector])) {
                $dead[] = $selector;
            }
        }

        return $dead;
    }

    /**
     * @return list<string>
     */
    private static function computeUndeclaredWirekitTokens(string $compiledCssPath, string $wirekitCssPath): array
    {
        $compiledCss = (string) file_get_contents($compiledCssPath);
        preg_match_all('/var\(\s*(--[a-zA-Z0-9_-]+)/', $compiledCss, $references);
        preg_match_all('/(--[a-zA-Z0-9_-]+)\s*:/', $compiledCss, $compiledDecls);

        $wirekitCss = (string) file_get_contents($wirekitCssPath);
        preg_match_all('/(--[a-zA-Z0-9_-]+)\s*:/', $wirekitCss, $wkDecls);

        $allDeclarations = array_unique(array_merge($compiledDecls[1], $wkDecls[1]));

        $wirekitReferences = array_filter(
            array_unique($references[1]),
            fn ($name) => str_contains($name, '-wk-') || str_starts_with($name, '--wk-'),
        );

        return array_values(array_diff($wirekitReferences, $allDeclarations));
    }
}
