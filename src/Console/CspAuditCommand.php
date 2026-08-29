<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Support\BladeParser;
use Symfony\Component\Process\ExecutableFinder;
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
 * outside it is **never evaluated**, and the page looks correct while the
 * control is dead.
 *
 * It is not entirely silent, and this docblock used to say it was — "it throws
 * nothing, logs nothing". Measured against the shipped CSP bundle, that is
 * false: it carries `Alpine Expression Error` and `CSP Parser Error`, emitted
 * through console.error and console.warn. The sentence mattered because
 * developers quote it, and quoting it tells them not to look in the one place
 * that would have told them.
 *
 * The correction changes what to DO about it, which is why the wording
 * mattered. "It logs nothing" says there is no net, and the conclusion drawn
 * from that is that a browser suite cannot see this class at all — so nobody
 * points one at it. The truth is narrower and more useful: the message fires
 * when the expression is EVALUATED. A wire:click no test ever clicks stays
 * silent, and the page looks correct meanwhile.
 *
 * So the net exists and has to be TRIGGERED. A browser check catches this
 * exactly when it operates the control rather than merely rendering the page
 * — which is a thing worth writing, where "it cannot be caught" is not.
 *
 * The static audit remains the reliable half for the same reason: it does not
 * depend on anyone having exercised the right control on the right page.
 *
 * ## Why the verdict comes from node
 *
 * The only correct oracle is Alpine's own parser. A list of forbidden patterns
 * would be a guess about that grammar, and the grammar is wider than it looks —
 * object literals, chains, ternaries and index access all parse, so reading an
 * expression and judging it by eye over-reports badly. This command finds the
 * expressions (it knows the view paths) and hands the verdict to the parser.
 *
 * The verdict shape is declared ONCE, here, because it was previously written out
 * three times and all three drifted together the moment the bridge gained a key:
 * a docblock, a `@var` on the decoded payload, and a `@var` on the extracted list
 * all said the warning path did not exist, while the bridge emitted it and the
 * command consumed it. PHP does not read any of them, so nothing failed — static
 * analysis proved the reading loop unreachable, which was the only visible symptom.
 * `warnings` is optional because the branch reporting a SYNTAX error returns before
 * a warning could exist.
 *
 * @phpstan-type CspVerdict array{ok: bool, error: string|null, globals: array<int, string>, warnings?: array<int, string>}
 */
class CspAuditCommand extends Command
{
    protected $signature = 'wirekit:csp-audit
        {--path=* : Directory to scan. Repeatable. Defaults to the application view paths.}
        {--json : Emit machine-readable JSON instead of a report.}';

    protected $description = 'Check every Alpine expression in your Blade views against Alpine\'s CSP grammar (needs node)';

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

        // A placement question, not an expression one: collected here so it is reported even
        // when every expression in the tree parses cleanly, which is the ordinary case.
        $inScript = $this->collectEncoderInScript($paths);

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
        $warnings = [];
        $unresolved = [];

        foreach ($found as $i => $entry) {
            if (($verdicts[$i]['ok'] ?? false) === true) {
                // Parsing and resolving are not the same as EVALUATING, and the gap
                // between them is where a control dies quietly. Collected apart and
                // never counted as a failure: the rule behind it over-approximates a
                // runtime question, and a finding that turns out to be nothing costs
                // this command the credibility of the findings that are not.
                foreach ($verdicts[$i]['warnings'] ?? [] as $warning) {
                    $warnings[] = $entry + ['warning' => $warning];
                }

                // A PASS earned by a placeholder is recorded as such. This is the half of
                // the audit that used to be silent, and silence here reads as measurement.
                if ($entry['unresolved'] !== null) {
                    $unresolved[] = $entry;
                }

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
                'warned' => count($warnings),
                'warnings' => $warnings,
                'unresolved' => count($unresolved),
                'unresolved_expressions' => $unresolved,
                'encoder_in_script' => count($inScript),
                'encoder_in_script_hits' => $inScript,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

            return $offenders === [] && $inScript === [] ? self::SUCCESS : self::FAILURE;
        }

        return $this->report($found, $offenders, $unchecked, $warnings, $unresolved, $inScript);
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
     * Where a package encoder sits inside a `<script>` block.
     *
     * `AlpinePayload::from()` is for a directive ATTRIBUTE, and its docblock says so. In a
     * script context HTML escaping does not apply, so a payload containing the closing-tag
     * sequence ends the block and turns everything after it into markup. The docblock is a
     * true statement that only reaches whoever reads it; this reaches the build.
     *
     * ⚠️ This is the arm rather than the flag, and the difference is reach. Escaping every
     * slash in the output defuses ONE vector of a context the encoder was never meant for —
     * it makes the placement less harmful without making it right, and it costs every
     * developer a source full of escaped slashes for a mistake most of them never make. A
     * static check says "this is in the wrong place", at build time, whichever vector
     * happens to matter. Decided 2026-08-28; the reasoning is in the ticket this came from.
     *
     * This library holds its own views to the same rule, and has since the encoder shipped —
     * a build here fails if a payload appears inside a script block anywhere in the package.
     * What is new is that a developer can now run the check over theirs.
     *
     * @param  array<int, string>  $paths
     * @return array<int, array{file: string, line: int, encoder: string}>
     */
    private function collectEncoderInScript(array $paths): array
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

                foreach (self::encoderInScriptHits($contents) as $hit) {
                    $found[] = ['file' => $file->getPathname()] + $hit;
                }
            }
        }

        return $found;
    }

    /**
     * The detector, kept separate so a test can drive it without a filesystem.
     *
     * @return array<int, array{line: int, encoder: string}>
     */
    public static function encoderInScriptHits(string $source): array
    {
        $hits = [];

        // Non-greedy to the first closing tag: a page with two script blocks must be two
        // spans, not one span swallowing the markup between them.
        if (! preg_match_all('/<script\b.*?<\/script>/is', $source, $matches, PREG_OFFSET_CAPTURE)) {
            return $hits;
        }

        foreach ($matches[0] as [$block, $offset]) {
            foreach (['AlpinePayload'] as $encoder) {
                $at = strpos($block, $encoder);

                if ($at === false) {
                    continue;
                }

                $hits[] = [
                    'line' => substr_count($source, "\n", 0, $offset + $at) + 1,
                    'encoder' => $encoder,
                ];
            }
        }

        return $hits;
    }

    /**
     * @param  array<int, string>  $paths
     * @return array<int, array{file: string, line: int, attribute: string, expression: string, unresolved: string|null}>
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
                        'unresolved' => $hit['unresolved'],
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
     * @return array<int, array{line: int, attribute: string, expression: string, unresolved: string|null}>
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
                    'unresolved' => $attribute['unresolved'],
                ];
            }
        }

        return $hits;
    }

    /**
     * A Livewire action expression as Livewire hands it to Alpine's evaluator.
     *
     * Mirrors `contextualizeExpression()` in the installed `livewire.esm.js`, which
     * every `wire:<event>` value passes through before evaluation. It prefixes each
     * bare identifier with the component proxy, so the author's `alert` is evaluated
     * as `$wire.alert` — a method on their component, not a window global.
     *
     * Auditing the raw source instead is wrong in the expensive direction: it calls a
     * working handler dead. Measured over the browser globals that are plausible
     * method names, six of them (`location`, `self`, `confirm`, `top`, `alert`,
     * `history`) were reported as unresolvable while `print`, `close` and `focus` were
     * not — a split that makes any small sample look clean.
     *
     * The skip list is upstream's, verbatim. Upstream ALSO skips the Alpine scope keys
     * of the element, which cannot be known from source; the consequence of prefixing
     * one of those anyway is `$wire.x` where `x` was meant, and both are member
     * expressions that parse and resolve — so the unknowable half can only ever cost a
     * finding we would not have made, never add a false one.
     */
    private static function asLivewireEvaluatesIt(string $expression): string
    {
        // Upstream: SKIP = ["JSON","true","false","null","undefined","this","$wire","$event"].
        $skip = ['JSON', 'true', 'false', 'null', 'undefined', 'this', '$wire', '$event'];

        // String literals are masked first so an identifier spelled inside one is left
        // alone — upstream does the same, and for the same reason.
        $literals = [];
        $masked = (string) preg_replace_callback(
            '/([\'"`])(?:(?!\1)[^\\\\]|\\\\.)*\1/',
            function (array $m) use (&$literals): string {
                $literals[] = $m[0];

                return '___'.(count($literals) - 1).'___';
            },
            $expression
        );

        $result = (string) preg_replace_callback(
            '/(^|[^.\w$])(\$?[a-zA-Z_]\w*)/',
            function (array $m) use ($skip, $masked): string {
                [$whole, $wholeOffset] = $m[0];
                $lead = $m[1][0];
                $identifier = $m[2][0];

                if (in_array($identifier, $skip, true) || preg_match('/^___\d+___$/', $identifier) === 1) {
                    return $whole;
                }

                // An identifier followed by `:` is an object-literal key, not a name to
                // resolve. Read off the ORIGINAL subject, which is what upstream does —
                // the replacement is not built up as it goes.
                if (($masked[$wholeOffset + strlen($whole)] ?? '') === ':') {
                    return $whole;
                }

                return $lead.'$wire.'.$identifier;
            },
            $masked,
            -1,
            $count,
            PREG_OFFSET_CAPTURE
        );

        return (string) preg_replace_callback(
            '/___(\d+)___/',
            fn (array $m): string => $literals[(int) $m[1]] ?? $m[0],
            $result
        );
    }

    /**
     * The Alpine-evaluated attributes inside one tag's attribute region.
     *
     * @return array<int, array{name: string, expression: string, unresolved: string|null, offset: int}>
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
            $raw = $match['expr'][0];
            $expression = trim(BladeParser::substituteServerSideConstructs($raw, 'BLADE'));

            if ($expression === '') {
                continue;
            }

            // What the substitution COST is recorded, because the placeholder is the point
            // at which this audit stops measuring and starts assuming.
            //
            // `BLADE` parses anywhere an identifier parses, which is what makes the check
            // usable at all — but it also means the expression passes on the strength of a
            // token that stands in for text nobody here has seen. The rendered form can be
            // anything, and one of the things it commonly is happens to be the exact
            // failure this command exists to find.
            //
            // The version that reported `JSON.parse(…)` as unresolvable therefore caught
            // the form nobody writes by hand and passed the form everyone writes, in the
            // same file on the same line — and a reader checking whether the fix had landed
            // planted a literal probe, watched it fire, and concluded the opposite of the
            // truth. That is the worst direction for an audit to be wrong in: it does not
            // merely miss, it actively certifies.
            $unresolved = self::unresolvedReason($raw);

            // A Livewire action is judged as Livewire PRESENTS it. `wire:click="alert"`
            // never reaches Alpine as the bare identifier `alert` — it is rewritten to a
            // member of the component proxy first — so reading the raw source declares a
            // working handler dead whenever a method name happens to collide with a
            // browser global.
            if (str_starts_with($name, 'wire:')) {
                $expression = self::asLivewireEvaluatesIt($expression);
            }

            $found[] = [
                'name' => $name,
                'expression' => $expression,
                'unresolved' => $unresolved,
                'offset' => (int) $match[0][1],
            ];
        }

        return $found;
    }

    /**
     * Why this expression's verdict rests on a substitution — or null when it does not.
     *
     * A Blade COMMENT is deliberately not a reason. It is removed outright rather than
     * replaced, because a comment renders to nothing: taking it out reproduces exactly what
     * the browser sees. Every other construct leaves a placeholder, and a placeholder is a
     * promise this command cannot keep.
     *
     * `encoder` is separated from `echo` because its rendered shape is known up to the data.
     * Laravel's `Js::from()` — which `@js()` calls — wraps its payload in `JSON.parse('…')`
     * for a non-empty ARRAY or OBJECT, and `JSON` is precisely what Alpine's CSP evaluator
     * cannot resolve. Every other shape comes back a bare literal, INCLUDING a string of any
     * length with quotes and non-ASCII in it, alongside numbers, booleans, null, `[]` and
     * `{}`. It is still not reported as a violation, because this command cannot see which
     * shape the data will take. Naming it as a probable cause is honest; calling it a finding
     * would be a guess wearing a verdict's clothes, and one wrong violation costs the next
     * hundred their credibility.
     *
     * This paragraph said "any non-empty string, array or object" until 2026-08-17, and a
     * developer followed it to the wrong repair: their one flagged expression passed a
     * string, was already fully resolvable, and the advice below told them to replace the
     * encoder with hand-written interpolation — trading a safe encoding for one the next
     * apostrophe breaks. A hint that leads from correct code to unsafe code is worse than
     * no hint. A guard in the package's own suite derives the claim from `Js::from()`
     * itself rather than restating it here.
     */
    private static function unresolvedReason(string $raw): ?string
    {
        $withoutComments = (string) preg_replace('/\{\{--.*?--\}\}/su', '', $raw);

        if (preg_match('/@js\s*\(|\bJs::from\s*\(/u', $withoutComments) === 1) {
            return 'encoder';
        }

        if (preg_match('/\{\{|\{!!|@[a-zA-Z][a-zA-Z0-9_]*\s*\(/u', $withoutComments) === 1) {
            return 'echo';
        }

        return null;
    }

    /**
     * Hand the expressions to Alpine's own parser.
     *
     * The shape is the bridge's, and it is written out rather than loosened: `warnings`
     * is optional because the branch that reports a SYNTAX error never reaches the point
     * where a warning could exist. That optionality is the whole reason this docblock is
     * worth keeping accurate — while it omitted the key entirely, static analysis could
     * prove the loop reading it unreachable, and it was right: the type said the warning
     * path did not exist. The command ran it anyway, because PHP does not read docblocks.
     * A type that disagrees with the code is not documentation, it is a second
     * implementation that nothing runs.
     *
     * @param  array<int, string>  $expressions
     * @return array<int, CspVerdict>|null null when the audit could not run
     */
    private function parse(array $expressions): ?array
    {
        $script = realpath(__DIR__.'/../../resources/csp/parse-expressions.mjs');

        if ($script === false) {
            $this->error('The CSP parser bridge is missing from the package.');

            return null;
        }

        // Asked BEFORE the process starts, because afterwards the answer is a guess.
        //
        // A missing `node` does not throw: the shell runs, reports 127, and `run()`
        // returns normally. The catch below therefore never fires for the one case it
        // was written for, and what a reader saw instead was "The CSP parser bridge
        // produced no verdict" — a sentence that does not contain the word `node` and
        // sends them looking for a broken script inside this package.
        //
        // That lands at the worst moment: the first time somebody wires this into a
        // CI image, which is when they know least about it. The step fails as ordinary
        // red, and the exit code is 1 rather than 127, so nothing points at the
        // environment either.
        //
        // A finder answers it outright instead of pattern-matching a shell's wording,
        // which differs between shells and locales.
        if ((new ExecutableFinder)->find('node') === null) {
            $this->error('Could not run node: it is not on PATH.');
            $this->line('This audit needs node, because the verdict has to come from Alpine\'s own parser.');

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

        /** @var array{ok?: bool, error?: string, results?: array<int, CspVerdict>}|null $payload */
        $payload = json_decode($process->getOutput(), true);

        if (! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            $this->error($payload['error'] ?? 'The CSP parser bridge produced no verdict.');

            if (! is_array($payload) && trim($process->getErrorOutput()) !== '') {
                $this->line(trim($process->getErrorOutput()));
            }

            return null;
        }

        /** @var array<int, CspVerdict> $results */
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
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string, warning: string}>  $warnings
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string, unresolved: string|null}>  $unresolved
     * @param  array<int, array{file: string, line: int, encoder: string}>  $inScript
     */
    private function report(array $found, array $offenders, array $unchecked, array $warnings = [], array $unresolved = [], array $inScript = []): int
    {
        // Named rather than implied. "Scanned" reads as "checked, and it works", and
        // the difference between what this measures and what a reader hears is the
        // exact gap a developer fell into: three dead listeners were found here, the
        // FIX for them passed here, and it was just as dead. The audit had produced
        // well-founded confidence, and on the second round that confidence did not
        // hold.
        $this->line(sprintf('Scanned %d Alpine expression(s) for GRAMMAR and resolvability.', count($found)));

        // ⚠️ AND THE ONE THING IT CANNOT DECIDE FROM THE SOURCE, NAMED IN THE SAME BREATH.
        //
        // "Resolvability" here means: does the identifier exist in a scope this audit can
        // see in the template. It cannot mean: does that scope SURVIVE the rendered markup.
        // A caller who passes their own `x-data` to a component that sets its own has the
        // second one silently thrown away — HTML keeps the first of two identical attributes
        // — so every identifier in it becomes unresolvable at runtime while remaining
        // perfectly resolvable at the source this audit reads.
        //
        // That is not a defect in the check; it is a boundary of it. It is printed because
        // of what happened without it: a developer read PASS over 83 expressions, believed
        // the scope question settled, and looked at the markup layer LAST — after the
        // runtime error had already named their own expression and sent them the other way.
        // An oracle that does not state its boundary is read as covering it.
        $this->line('This does NOT decide whether a scope survives the rendered markup — a passed-through');
        $this->line('`x-data` on a component that sets its own is discarded, and every identifier in it is');
        $this->line('then dead at runtime while resolving fine here. WireKit warns about that collision');
        $this->line('separately, in the application log, when app.debug is on.');

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

        // The half that used to be silent.
        //
        // These expressions PASSED, and the pass is real as far as it goes — the grammar
        // accepted what was left after Blade came out. What it does not cover is the text
        // that was taken out, and the reader has no way to know that from a PASS alone.
        if ($unresolved !== []) {
            $encoders = array_values(array_filter($unresolved, static fn (array $e): bool => $e['unresolved'] === 'encoder'));

            $this->line('');
            $this->warn(sprintf('%d expression(s) passed on a substitution rather than a measurement.', count($unresolved)));
            $this->line('');
            $this->line('Blade renders these before Alpine reads them, so this audit stood an identifier');
            $this->line('in for the part it cannot see. The grammar accepted what was left; nothing here');
            $this->line('says the rendered form is accepted, and this is NOT counted as a failure.');

            if ($encoders !== []) {
                $this->line('');
                $this->line(sprintf('%d of them call Js::from() or @js(), which is worth checking first:', count($encoders)));
                $this->line('');
                $this->line("  That encoder renders JSON.parse('…') for a non-empty ARRAY or OBJECT — and");
                $this->line('  JSON is the one name Alpine\'s CSP evaluator cannot resolve, so such an');
                $this->line('  expression parses, runs, and dies silently. If this value is an array or an');
                $this->line('  object, pass a bare literal instead.');
                $this->line('');
                $this->line('  A STRING is not affected, and neither are numbers, booleans, null, [] or {}:');
                $this->line('  the encoder renders those as literals, quotes and non-ASCII included. Do not');
                $this->line('  replace one with hand-written interpolation — that trades a safe encoding for');
                $this->line('  one the next apostrophe breaks. This is a pointer, not a verdict, because this');
                $this->line('  command cannot see which shape the data will take.');
            }

            // ONLY the encoders are listed. Measured over this library's own views, 226
            // expressions rest on a substitution — an interpolated prop in an `x-data`
            // object is the ordinary way to write Alpine in Blade, and printing ten
            // arbitrary `BLADE = BLADE` lines out of that teaches nothing while making the
            // encoder block, which is the actionable part, scroll off the top.
            //
            // The count carries the honest half (a PASS here does not cover the rendered
            // form); the list carries the part someone can act on. A report that buries the
            // second in the first is read once.
            $this->line('');

            foreach (array_slice($encoders, 0, 10) as $entry) {
                $this->line(sprintf('  %s:%d', $entry['file'], $entry['line']));
                $this->line(sprintf('    %s="%s"', $entry['attribute'], $entry['expression']));
                $this->line('');
            }

            if (count($encoders) > 10) {
                $this->line(sprintf('  …and %d more encoder call(s).', count($encoders) - 10));
                $this->line('');
            }
        }

        // Before the verdict, for the same reason `unchecked` is: a PASS read without
        // this next to it says more than the audit measured.
        if ($warnings !== []) {
            $this->line('');
            $this->warn(sprintf('%d expression(s) parse but may not EVALUATE.', count($warnings)));
            $this->line('');
            $this->line('The CSP evaluator refuses a VALUE, not a name: it throws on any property access');
            $this->line('that lands in the set built from `globalThis`. So a chain can resolve completely,');
            $this->line('run, and be rejected at the moment it touches something that happens to be a');
            $this->line('global — `$el.ownerDocument.location` reaches `window.location` by another route.');
            $this->line('');
            $this->line('Warnings, not violations: this rule over-approximates a question about runtime');
            $this->line('values, so check each one rather than trusting it.');
            $this->line('');

            foreach (array_slice($warnings, 0, 20) as $entry) {
                $this->line(sprintf('  %s:%d', $entry['file'], $entry['line']));
                $this->line(sprintf('    %s="%s"', $entry['attribute'], $entry['expression']));
                $this->line(sprintf('    %s', $entry['warning']));
                $this->line('');
            }

            if (count($warnings) > 20) {
                $this->line(sprintf('  …and %d more.', count($warnings) - 20));
                $this->line('');
            }

            $this->line('The supported shape is a registered component:');
            $this->line('');
            $this->line("  Alpine.data('reload', () => ({ reload() { window.location.reload() } }))");
            $this->line('  <div x-data="reload" x-on:locale-changed.window="reload">');
            $this->line('');
        }

        // A placement finding, printed before the verdict for the same reason the unchecked
        // ones are: the verdict must be the last thing on screen and must not be readable
        // without this qualifying it.
        if ($inScript !== []) {
            $this->line('');
            $this->error(sprintf(
                '%d Alpine payload(s) sit inside a <script> block, where HTML escaping does not reach:',
                count($inScript),
            ));
            $this->line('');

            foreach (array_slice($inScript, 0, 10) as $entry) {
                $this->line(sprintf('  %s:%d  %s', $entry['file'], $entry['line'], $entry['encoder']));
            }

            if (count($inScript) > 10) {
                $this->line(sprintf('  …and %d more.', count($inScript) - 10));
            }

            $this->line('');
            $this->line('A payload containing the closing-tag sequence ends the block there, and every');
            $this->line('character after it is parsed as markup rather than as data. Nothing escapes it in');
            $this->line('this context — that is what a script context means.');
            $this->line('');
            $this->line('AlpinePayload is the encoder for a directive ATTRIBUTE. Inside a script block the');
            $this->line('right one is Laravel\'s Js::from, which escapes the angle brackets as well. In an');
            $this->line('attribute Js::from is wrong for the opposite reason, so this is a placement');
            $this->line('question rather than a preference.');
            $this->line('');
        }

        if ($offenders === [] && $inScript !== []) {
            return self::FAILURE;
        }

        if ($offenders === []) {
            // The unqualified sentence is reserved for the run that earned it. It used to be
            // printed whenever nothing failed, which is how a developer read "resolves in
            // scope" off a run where the deciding text had been substituted away before the
            // parser ever saw it.
            $this->info(match (true) {
                $unchecked === [] && $unresolved === [] => 'PASS — every expression parses under Alpine\'s CSP grammar and resolves in scope.',
                $unchecked === [] => 'PASS — with '.count($unresolved).' expression(s) resting on a substitution (listed above).',
                default => 'PASS — every expression this audit could check parses under Alpine\'s CSP grammar.',
            });

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
        $this->line('attribute. A method named after an operator or a literal needs index access:');
        $this->line('`$wire.delete(…)` does not parse, `$wire[\'delete\'](…)` does. That is the whole');
        // Naming the set rather than the category, because the category is much wider than
        // the set: every other reserved word is read as an ordinary identifier here, so a
        // developer told "a JavaScript keyword" renames methods that were never affected.
        $this->line('set: delete false in instanceof new null true typeof undefined void.');

        return self::FAILURE;
    }
}
