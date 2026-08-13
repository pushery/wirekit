<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Support\BladeParser;
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
     *
     * `x-teleport` was on this list and is the same mistake one attribute over.
     * Alpine hands its value straight to `document.querySelector()` — its
     * `getTarget()` is that call and a warning when it finds nothing — so the
     * value is a CSS selector. A selector that starts with `#` is not an
     * expression that starts with an operator, and reporting it as one is a
     * finding a reader cannot act on: there is nothing to rewrite.
     *
     * It cost more than a wrong line. Measured over this repository's own views,
     * eleven of twenty-seven rejections were `x-teleport="#wk-overlay-root"` —
     * so nearly half of what the audit reported was the audit misreading its own
     * input, in a command whose only value is that its output can be trusted.
     */
    private const EXPRESSION_ATTRIBUTES = [
        'x-data', 'x-show', 'x-if', 'x-text', 'x-html', 'x-model', 'x-modelable',
        'x-init', 'x-effect', 'x-bind', 'x-on', 'x-intersect', 'x-id',
    ];

    /**
     * The `wire:` names whose value is NOT handed to Alpine's evaluator.
     *
     * Kept as a deny-list because Livewire's own wildcard is one, and copying
     * the shape is the same discipline that makes the verdict come from Alpine's
     * parser rather than from a pattern catalog: the set of EVENTS is open, so
     * only the exceptions can be enumerated. Measured from the installed
     * `livewire.esm.js`, `js/directives/wire-wildcard.js`:
     *
     *     on("directive.init", ({ el, directive, ... }) => {
     *       if (["snapshot","effects","model","init","loading","poll","ignore",
     *            "id","data","key","target","dirty","sort"].includes(directive.value)) return;
     *       if (customDirectiveHasBeenRegistered(directive.value)) return;
     *       let attribute = directive.rawName.replace("wire:", "x-on:");
     *
     * Everything not listed there becomes an `x-on:` binding, which means its
     * value goes through the same evaluator as every Alpine expression — and is
     * therefore subject to the same CSP restriction, which is the whole reason
     * this scan exists.
     *
     * The second list is the custom directives Livewire registers, which return
     * early for the same reason. They are named separately because they come
     * from a different mechanism and will drift on a different release.
     *
     * `wire:model="form.items.0.name"` is the case that makes the deny-list
     * mandatory rather than tidy: it is a property path, and scanning it would
     * report every bound field in the application as a broken expression — the
     * same false-positive class that the component-tag carve-out above removes.
     */
    private const WIRE_NON_EXPRESSION = [
        // The wildcard's own early return.
        'snapshot', 'effects', 'model', 'init', 'loading', 'poll', 'ignore',
        'id', 'data', 'key', 'target', 'dirty', 'sort',
        // Registered custom directives, which never reach the wildcard.
        'anchor', 'collapse', 'confirm', 'intersect', 'mask', 'navigate',
        'offline', 'replace', 'resize', 'trap', 'stream', 'current', 'transition',
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
        $unchecked = [];

        foreach ($found as $i => $entry) {
            if (($verdicts[$i]['ok'] ?? false) === true) {
                continue;
            }

            $entry += ['error' => $verdicts[$i]['error'] ?? 'unknown'];

            // A rejection is only a finding when the parser was shown what the browser sees.
            // Where Blade is left over, it was not — and the verdict is then about Blade
            // rather than about the expression, which is a fact concerning this command and
            // not the developer's template.
            //
            // Counted apart rather than dropped, because unmeasured and clean are different
            // states and this command's whole worth is that it does not confuse them.
            if (BladeParser::hasServerSideConstruct($entry['expression'])) {
                $unchecked[] = $entry;

                continue;
            }

            $offenders[] = $entry;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'scanned' => count($found),
                'failed' => count($offenders),
                'offenders' => $offenders,
                'unchecked' => count($unchecked),
                'unparsable' => $unchecked,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

            return $offenders === [] ? self::SUCCESS : self::FAILURE;
        }

        return $this->report($found, $offenders, $unchecked);
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
     * Where a tag begins and ends is Blade's question, not Alpine's, so it is answered
     * once — in `BladeParser::tagsFromSource()` — and this command reads the boundaries
     * it returns. That walk had been written a second time here, at a shallower depth,
     * and the difference was not academic: a `{{-- don't --}}` between attributes ran the
     * cursor off the end of the file and threw a raw `ValueError` out of this method,
     * before the command could report anything at all. Where the apostrophes happened to
     * balance there was no crash and no signal either — the swallowed span was attributed
     * to the tag it started on, and if that tag was a component, the bare-colon exemption
     * below then discarded every Alpine binding inside it.
     *
     * What stays here is the part that is about Alpine: which attributes carry an
     * expression, and which stand-in a Blade hole leaves behind in one. Removing the holes
     * is Blade's question and lives next to the walk; choosing what replaces them is a
     * statement about Alpine's grammar and belongs to this command.
     *
     * @return array<int, array{line: int, attribute: string, expression: string}>
     */
    private function expressionsIn(string $contents): array
    {
        $hits = [];

        foreach (BladeParser::tagsFromSource($contents) as $tag) {
            // A tag another element interrupted never closed, so what follows it is not
            // its attribute region. Auditing it anyway produces findings that cannot be
            // found at the line they name — and a report nobody can act on is how a
            // developer learns to stop reading this one.
            if ($tag['terminator'] === '<') {
                continue;
            }

            $region = substr($contents, $tag['attrStart'], $tag['attrEnd'] - $tag['attrStart']);

            foreach ($this->attributesIn($region, $tag['isComponent']) as $attribute) {
                $hits[] = [
                    'line' => substr_count(substr($contents, 0, $tag['attrStart'] + $attribute['offset']), "\n") + 1,
                    'attribute' => $attribute['name'],
                    'expression' => $attribute['expression'],
                ];
            }
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
        $pattern = '/(?<![\w:@.-])(?<attr>(?:'.$names.')(?::[\w.-]+)?|wire:[\w.-]+|@[\w.-]+|:[\w.-]+)\s*=\s*(?<q>["\'])(?<expr>.*?)(?<!\\\\)\g{q}/s';

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

            // A `wire:` name is scanned only when Livewire would hand its value to
            // Alpine — see WIRE_NON_EXPRESSION for how that set is derived. The
            // base name is what decides: `wire:model.live.debounce.500ms` is still
            // `model`, and comparing the whole raw name would scan it.
            if (str_starts_with($name, 'wire:')) {
                $base = strtok(substr($name, strlen('wire:')), '.');

                if ($base === false || in_array($base, self::WIRE_NON_EXPRESSION, true)) {
                    continue;
                }
            }

            // Blade in a value is a server-side hole in a client-side expression: a comment,
            // an echo, an `@js(…)` payload. All of it is gone by the time Alpine reads the
            // attribute, so what reaches the parser has to be what is LEFT. Which construct
            // is which, and in what order they have to go, is Blade's question — it is
            // answered once in BladeParser and read here.
            //
            // The substitute is an identifier and not a literal, which is the one part of
            // this that is Alpine's question rather than Blade's. A hole does not only sit at
            // a value position: `@click="{{ $model }} = ! {{ $model }}"` puts one at an
            // assignment target, and an object literal can put one at a key. Measured against
            // the parser, `0` is rejected at both ("Invalid assignment target", "Expected
            // property key") and `"BLADE"` at the first; the identifier is accepted at every
            // position a hole was found in. Permissive is the right direction here — a false
            // violation costs a developer a hunt through a template that is fine, and after
            // one of those they stop reading the report that was their only warning.
            $expression = trim(BladeParser::substituteServerSideConstructs($match['expr'][0], 'BLADE'));

            if ($expression === '') {
                continue;
            }

            $found[] = [
                'name' => $name,
                'expression' => $expression,
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
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string, error: string}>  $unchecked
     */
    private function report(array $found, array $offenders, array $unchecked): int
    {
        $this->line(sprintf('Scanned %d Alpine expression(s).', count($found)));

        // Listed before the verdict, so the verdict is the last thing on screen and cannot be
        // read without this qualifying it.
        if ($unchecked !== []) {
            $this->line('');
            $this->warn(sprintf('%d expression(s) could not be checked.', count($unchecked)));
            $this->line('');
            $this->line('Blade left something in them this audit cannot substitute — a directive that');
            $this->line('opens a block has no stand-in, because what remains is a fragment rather than an');
            $this->line('expression. Parsing it anyway would produce a verdict about Blade, so these are');
            $this->line('listed rather than counted:');
            $this->line('');

            foreach (array_slice($unchecked, 0, 10) as $entry) {
                $this->line(sprintf('  %s:%d', $entry['file'], $entry['line']));
                $this->line(sprintf('    %s="%s"', $entry['attribute'], $entry['expression']));
                $this->line('');
            }

            if (count($unchecked) > 10) {
                $this->line(sprintf('  …and %d more.', count($unchecked) - 10));
                $this->line('');
            }
        }

        if ($offenders === []) {
            $this->info($unchecked === []
                ? 'PASS — every expression parses under Alpine\'s CSP grammar.'
                : 'PASS — every expression this audit could check parses under Alpine\'s CSP grammar.');

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
