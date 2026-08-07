<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Check every Alpine expression in an application's Blade templates against
 * Alpine's CSP grammar.
 *
 * ## Why an application needs this at all
 *
 * Under `script-src` without `'unsafe-eval'`, Alpine's CSP build does not
 * compile expressions — it interprets them with a tokenizer, a parser and an
 * AST evaluator. That grammar is narrower than JavaScript, and an expression
 * outside it is **never evaluated**: it throws nothing, logs nothing, and the
 * page looks correct while the control is dead. There is no symptom, which is
 * exactly why the check has to be mechanical.
 *
 * ## Why the verdict comes from node
 *
 * The only correct oracle is Alpine's own parser. A list of forbidden patterns
 * would be a guess about that grammar, and the grammar is wider than it looks —
 * object literals, chains, ternaries and index access all parse, so reading an
 * expression and judging it by eye over-reports badly. This command finds the
 * expressions (it knows the view paths) and hands the verdict to the parser.
 */
class CspAuditCommand extends Command
{
    protected $signature = 'wirekit:csp-audit
        {--path=* : Directory to scan. Repeatable. Defaults to the application view paths.}
        {--json : Emit machine-readable JSON instead of a report.}';

    protected $description = 'Check every Alpine expression in your Blade views against Alpine\'s CSP grammar';

    /**
     * The attributes whose VALUE Alpine evaluates as an expression.
     *
     * Deliberately not "every x-* attribute": `x-ref`, `x-transition` and
     * `x-cloak` take a name or nothing, so scanning them would report a
     * perfectly good `x-ref="panel"` as a broken expression.
     *
     * `x-for` is absent for the same reason — its value is `item in items`,
     * which is Alpine's own iteration syntax, not an expression.
     */
    private const EXPRESSION_ATTRIBUTES = [
        'x-data', 'x-show', 'x-if', 'x-text', 'x-html', 'x-model', 'x-modelable',
        'x-init', 'x-effect', 'x-bind', 'x-on', 'x-teleport', 'x-intersect', 'x-id',
    ];

    public function handle(): int
    {
        $paths = $this->resolvePaths();

        if ($paths === []) {
            $this->error('No directory to scan. Pass --path=… or make sure your view paths exist.');

            return self::FAILURE;
        }

        $found = $this->collectExpressions($paths);

        // A run that measured nothing must never look like a clean run.
        //
        // This is the failure that makes an audit worse than no audit: it reports
        // PASS, the developer stops looking, and the thing it was supposed to check
        // was never checked. Zero expressions in a directory the developer named is
        // far more likely to mean the wrong directory than a template with no Alpine
        // in it at all.
        if ($found === []) {
            $this->error(sprintf(
                'Found no Alpine expressions in %s.',
                implode(', ', $paths),
            ));
            $this->line('');
            $this->line('That is reported as a failure rather than a pass: an audit that measured');
            $this->line('nothing and said "clean" is worse than no audit, because you would stop looking.');
            $this->line('Check the path, or pass --path=… explicitly.');

            return self::FAILURE;
        }

        $verdicts = $this->parse(array_column($found, 'expression'));

        if ($verdicts === null) {
            return self::FAILURE;
        }

        $offenders = [];

        foreach ($found as $i => $entry) {
            if (($verdicts[$i]['ok'] ?? false) !== true) {
                $offenders[] = $entry + ['error' => $verdicts[$i]['error'] ?? 'unknown'];
            }
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'scanned' => count($found),
                'failed' => count($offenders),
                'offenders' => $offenders,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

            return $offenders === [] ? self::SUCCESS : self::FAILURE;
        }

        return $this->report($found, $offenders);
    }

    /**
     * @return array<int, string>
     */
    private function resolvePaths(): array
    {
        /** @var array<int, string> $given */
        $given = (array) $this->option('path');

        if ($given !== []) {
            return array_values(array_filter($given, 'is_dir'));
        }

        /** @var array<int, string> $viewPaths */
        $viewPaths = (array) config('view.paths', []);

        return array_values(array_filter($viewPaths, 'is_dir'));
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{file: string, line: int, attribute: string, expression: string}>
     */
    private function collectExpressions(array $paths): array
    {
        $found = [];

        foreach ($paths as $path) {
            /** @var \RecursiveIteratorIterator<\RecursiveDirectoryIterator> $files */
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = (string) file_get_contents($file->getPathname());

                foreach ($this->expressionsIn($contents) as $hit) {
                    $found[] = [
                        'file' => $file->getPathname(),
                        'line' => $hit['line'],
                        'attribute' => $hit['attribute'],
                        'expression' => $hit['expression'],
                    ];
                }
            }
        }

        return $found;
    }

    /**
     * Every Alpine expression in this file, with the tag it sits on.
     *
     * A tag WALKER rather than one regex over the whole file, and the reason is a
     * `>` inside a quoted attribute value. Blade array props are full of them —
     * `:series="[[\'x\' => \'Spec\']]"` carries two — so anything that decides where a
     * tag ends by searching backwards for the nearest `>` loses the beat at the
     * first one and mis-attributes every attribute after it. That is not a bug to
     * patch; a backwards search cannot answer the question at all, because the
     * character it keys on is not rare inside values. Quotes are tracked instead.
     *
     * @return array<int, array{line: int, attribute: string, expression: string}>
     */
    private function expressionsIn(string $contents): array
    {
        $hits = [];
        $length = strlen($contents);
        $cursor = 0;

        while (($open = strpos($contents, '<', $cursor)) !== false) {
            $cursor = $open + 1;

            if (! preg_match('/\G([a-zA-Z][\w:.-]*)/', $contents, $nameMatch, 0, $cursor)) {
                continue;
            }

            $tag = $nameMatch[1];
            $cursor += strlen($tag);
            // `<x-…` is a Blade component; the distinction decides how a bare `:` reads.
            $isComponent = (bool) preg_match('/^x[-:]/i', $tag);

            $attrStart = $cursor;
            $quote = null;

            // Walk to the tag's real end, which is the first `>` OUTSIDE a quoted value.
            while ($cursor < $length) {
                $char = $contents[$cursor];

                if ($quote !== null) {
                    if ($char === $quote) {
                        $quote = null;
                    }
                    $cursor++;

                    continue;
                }

                if ($char === '"' || $char === "'") {
                    $quote = $char;
                    $cursor++;

                    continue;
                }

                if ($char === '>') {
                    break;
                }

                $cursor++;
            }

            foreach ($this->attributesIn(substr($contents, $attrStart, $cursor - $attrStart), $isComponent) as $attribute) {
                $hits[] = [
                    'line' => substr_count(substr($contents, 0, $attrStart + $attribute['offset']), "\n") + 1,
                    'attribute' => $attribute['name'],
                    'expression' => $attribute['expression'],
                ];
            }

            $cursor++;
        }

        return $hits;
    }

    /**
     * The Alpine-evaluated attributes inside one tag's attribute region.
     *
     * @return array<int, array{name: string, expression: string, offset: int}>
     */
    private function attributesIn(string $region, bool $isComponent): array
    {
        // The shorthands are included because an application written in them would
        // otherwise scan as if it had no Alpine at all — a clean report over an
        // unexamined template. `@click` is `x-on:click`, `:class` is `x-bind:class`.
        //
        // The lookbehind keeps `wire:model` out: without it the `:model` half matches
        // the bare-colon alternative and every Livewire binding is scanned as Alpine.
        $names = implode('|', array_map('preg_quote', self::EXPRESSION_ATTRIBUTES));
        $pattern = '/(?<![\w:@.-])(?<attr>(?:'.$names.')(?::[\w.-]+)?|@[\w.-]+|:[\w.-]+)\s*=\s*(?<q>["\'])(?<expr>.*?)(?<!\\\\)\g{q}/s';

        if (! preg_match_all($pattern, $region, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $found = [];

        foreach ($matches as $match) {
            $name = $match['attr'][0];

            // On a component tag a bare `:` is Blade's prop binding and its value is
            // PHP — `:options="[\'xaxis\' => [\'type\' => \'datetime\']]"`. Alpine's parser
            // reads that `=>` as a stray operator, so scanning component tags turns
            // every array prop in the application into a finding. An Alpine binding on
            // a component tag is written `x-bind:`, which still matches.
            //
            // Found by running this against a real application rather than fixtures —
            // the only place it could be found, because the construct is correct Blade,
            // correct PHP and correct WireKit. Only the scanner was wrong.
            if ($isComponent && str_starts_with($name, ':')) {
                continue;
            }

            $expression = trim($match['expr'][0]);

            if ($expression === '') {
                continue;
            }

            // Blade interpolation is a server-side hole in a client-side expression.
            // Substituted with an identifier rather than a literal because a payload can
            // legitimately be an object or a string, and the substitute has to be the
            // most permissive shape or the audit passes what the browser refuses.
            $found[] = [
                'name' => $name,
                'expression' => (string) preg_replace('/\{\{.*?\}\}/su', 'BLADE', $expression),
                'offset' => (int) $match[0][1],
            ];
        }

        return $found;
    }

    /**
     * Hand the expressions to Alpine's own parser.
     *
     * @param  array<int, string>  $expressions
     * @return array<int, array{ok: bool, error: string|null}>|null null when the audit could not run
     */
    private function parse(array $expressions): ?array
    {
        $script = realpath(__DIR__.'/../../resources/csp/parse-expressions.mjs');

        if ($script === false) {
            $this->error('The CSP parser bridge is missing from the package.');

            return null;
        }

        $process = new Process(['node', $script]);
        $process->setInput((string) json_encode(['expressions' => array_values($expressions)]));
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (\Throwable $e) {
            $this->error('Could not run node: '.$e->getMessage());
            $this->line('This audit needs node, because the verdict has to come from Alpine\'s own parser.');

            return null;
        }

        /** @var array{ok?: bool, error?: string, results?: array<int, array{ok: bool, error: string|null}>}|null $payload */
        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            $this->error($payload['error'] ?? 'The CSP parser bridge produced no verdict.');

            if (! is_array($payload) && trim($process->getErrorOutput()) !== '') {
                $this->line(trim($process->getErrorOutput()));
            }

            return null;
        }

        /** @var array<int, array{ok: bool, error: string|null}> $results */
        $results = $payload['results'] ?? [];

        if (count($results) !== count($expressions)) {
            $this->error(sprintf(
                'The parser returned %d verdicts for %d expressions — the audit cannot say which is which.',
                count($results),
                count($expressions),
            ));

            return null;
        }

        return $results;
    }

    /**
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string}>  $found
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string, error: string}>  $offenders
     */
    private function report(array $found, array $offenders): int
    {
        $this->line(sprintf('Scanned %d Alpine expression(s).', count($found)));

        if ($offenders === []) {
            $this->info('PASS — every expression parses under Alpine\'s CSP grammar.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->error(sprintf('%d expression(s) will never be evaluated under a CSP without \'unsafe-eval\':', count($offenders)));
        $this->line('');

        foreach (array_slice($offenders, 0, 40) as $offender) {
            $this->line(sprintf('  %s:%d', $offender['file'], $offender['line']));
            $this->line(sprintf('    %s="%s"', $offender['attribute'], $offender['expression']));
            $this->line(sprintf('    %s', $offender['error']));
            $this->line('');
        }

        if (count($offenders) > 40) {
            $this->line(sprintf('  …and %d more.', count($offenders) - 40));
            $this->line('');
        }

        // The fix is the same one nearly every time, so it is worth stating once
        // rather than leaving each developer to rediscover it.
        $this->line('The fix is almost always the same: move the logic into your Alpine component');
        $this->line('factory and call a method from the directive. A factory is plain JavaScript in a');
        $this->line('bundled file, where none of these restrictions apply — and an expression reduced');
        $this->line('to a method call parses under BOTH builds, so one template serves both.');
        $this->line('');
        $this->line('The grammar accepts: assignments, ternaries, object and array literals, calls,');
        $this->line('member and index access, ++/--, unary, and the usual binary/logical operators.');
        $this->line('It rejects: arrow functions, template literals, optional chaining, nullish');
        $this->line('coalescing, spread, `new`, function expressions, and several statements in one');
        $this->line('attribute. A method whose name is a JavaScript keyword needs index access:');
        $this->line('`$wire.delete(…)` does not parse, `$wire[\'delete\'](…)` does.');

        return self::FAILURE;
    }
}
