<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\View;
use Pushery\WireKit\Support\AlpineRegistrations;
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
        {--vendor : Also scan the view directories packages registered with loadViewsFrom.}
        {--registrations=* : JavaScript file or directory to read Alpine.data registrations from. Repeatable, and ADDED to the built and published bundles.}
        {--registrations-only : Read registrations ONLY from --registrations, ignoring the built and published bundles.}
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

        // The surface question, answered before any verdict is formed: which registered view
        // directories this run did NOT read. A report that cannot distinguish "clean" from
        // "clean over part of the tree" is indistinguishable from a complete one, and that
        // indistinguishability is the damage — not the missing option.
        $unscanned = $this->unscannedNamespaces($paths);

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

            if ($unscanned !== []) {
                $this->line('');
                $this->line(sprintf(
                    '%d registered view namespace(s) sit outside this surface: %s. Run with --vendor to include them.',
                    count($unscanned),
                    implode(', ', array_slice(array_keys($unscanned), 0, 10)),
                ));
            }

            return self::FAILURE;
        }

        // What is actually registered, read before any verdict — because for `x-data` the
        // grammar answers the wrong question, and the right one needs this set.
        $registrationScan = $this->registrations($paths);

        $verdicts = $this->parse(array_column($found, 'expression'));

        if ($verdicts === null) {
            return self::FAILURE;
        }

        $offenders = [];
        $unchecked = [];
        $warnings = [];
        $unresolved = [];
        $unregistered = [];

        foreach ($found as $i => $entry) {
            // A call whose callee is a LITERAL parses and is dead, and that combination is
            // invisible to both halves of this audit: the grammar accepts it, and there is
            // no Blade left in it for the substitution check to object to.
            //
            // It exists because `contextualizeExpression()` mirrors Livewire's own SKIP
            // list, correctly — `true`, `false`, `null` and `undefined` are NOT prefixed
            // with `$wire.`, so `wire:click="true(1)"` reaches the browser as `true(1)` and
            // throws `true is not a function`. Reported from a consuming package that
            // measured all four cases: `delete(1)` was found, `true(1)` and `null(1)` were
            // certified, and the run exited 0 over two dead controls.
            //
            // Decidable without a runtime, and deliberately narrow: only a CALL, and only
            // where Livewire would otherwise have prefixed it. In an `x-` attribute a bare
            // `true` is an ordinary value (`x-show="true"`), and `$wire['true'](1)` — the
            // repair this command already recommends — is a string key rather than a
            // callee, so neither is touched.
            $literalCall = str_starts_with((string) ($entry['attribute'] ?? ''), 'wire:')
                ? self::literalCallee($entry['expression'])
                : null;

            if ($literalCall !== null) {
                $offenders[] = $entry + ['error' => sprintf(
                    'Calls the literal `%s`, which parses and then throws `%s is not a function`. '
                    .'Livewire does not prefix the four literals, so this reaches the browser '
                    ."unchanged. Use index access: \$wire['%s'](…).",
                    $literalCall,
                    $literalCall,
                    $literalCall,
                )];

                continue;
            }

            // ⚠️ FOR `x-data` THE GRAMMAR ANSWERS THE WRONG QUESTION, and it answers it
            // affirmatively in both directions that matter. `x-data="{ open: false }"`
            // parses and is exactly the form a CSP build has no factory for;
            // `x-data="wirekitAlertDialog({…})"` parses and leaves the element WITHOUT A
            // SCOPE when nothing registered that name.
            //
            // The second is the dangerous one because it fails upward: no scope means
            // `x-show` cannot evaluate, so it never sets `display: none`, while Alpine's
            // init removes `x-cloak` regardless. The panel is visible and every control in
            // it is dead — which reads as a layout bug, not as a missing script.
            //
            // Measured in an adopting application: this command reported PASS over a tree
            // with 81 unregistered kit components and six inline `x-data` beside them.
            //
            // ⚠️ AND NOT OVER A SUBSTITUTION. An `x-data="@js(…)"` reaches this loop as a
            // placeholder, and a placeholder is a bare identifier — so the very first run of
            // this check reported six of this command's own fixtures as unregistered
            // factories. That is the same trap `$unchecked` exists for one branch down: a
            // verdict about Blade dressed as a verdict about the template.
            $judgeable = $entry['unresolved'] === null
                && ! BladeParser::hasServerSideConstruct($entry['expression']);

            if ($judgeable && ($entry['attribute'] ?? null) === 'x-data' && $registrationScan['names'] !== []) {
                $needs = AlpineRegistrations::requiredName($entry['expression']);

                if ($needs !== null && ! in_array($needs, $registrationScan['names'], true)) {
                    $unregistered[] = $entry + ['name' => $needs];

                    continue;
                }
            }

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
                // Additive, and the reason they are here rather than only on the report: a
                // build step reading this payload has the same blind spot a reader does.
                'surface' => array_values($paths),
                'unscanned_namespaces' => $unscanned,
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
                // Both halves, because a reader of this payload has the same blind spot a
                // reader of the report does: zero unregistered names over zero registrations
                // scanned is not a clean result, it is an unasked question.
                'registrations_seen' => count($registrationScan['names']),
                'registration_files' => count($registrationScan['files']),
                // ONE unambiguous field, because the two counts above were already here and the
                // report they came from still got read as clean. A developer has to know to look
                // at them, and `unregistered: 0` sits right next to them saying the reassuring
                // thing. This says whether the question was asked at all.
                //
                // A field rather than an exit code, deliberately. Failing would redden every
                // application that has no Alpine registrations because it has no Alpine, which is
                // not a finding. Being able to tell the two apart is what was missing, and a
                // boolean is the whole of it.
                'x_data_checked' => $registrationScan['names'] !== [],
                'unregistered' => count($unregistered),
                'unregistered_components' => $unregistered,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG));

            return $offenders === [] && $inScript === [] && $unregistered === [] ? self::SUCCESS : self::FAILURE;
        }

        return $this->report($found, $offenders, $unchecked, $warnings, $unresolved, $inScript, $paths, $unscanned, $unregistered, $registrationScan);
    }

    /**
     * The Alpine registrations this run can see, and where it looked.
     *
     * ⚠️ AN EMPTY SCAN IS NOT A FINDING, and the whole `x-data` check is gated on that.
     * A pattern that reads a bundle full of registrations as empty produces the same
     * output as an application that registered nothing — and the second reading turns
     * every `x-data` in the tree into an offender. So zero registrations disables the
     * check and says so, rather than reporting a catalog of defects that do not exist.
     *
     * The reporting side names the surface for the same reason the view surface is named:
     * "nothing unregistered" and "nothing looked at" have to be different sentences.
     *
     * @param  array<int, string>  $paths  The view directories this run is scanning. A package
     *                                     whose views are in there contributes its own
     *                                     registration source; see below.
     * @return array{names: list<string>, files: list<string>}
     */
    private function registrations(array $paths = []): array
    {
        /** @var array<int, string> $given */
        $given = (array) $this->option('registrations');

        // Absolute for the same reason `--path` is, one step over: the files read from here
        // are recorded by the spelling they came in with, and the report later asks whether
        // a package's own source is among them by comparing prefixes against an absolute
        // root. A relative `--registrations` never matched, so the hint told a developer to
        // point at the very file the run had already read.
        $given = array_map(self::normalizePath(...), $given);

        $candidates = [
            // Where a Laravel application's own bundle lands, in the two shapes Vite and a
            // hand-rolled build produce.
            base_path('public/build'),
            base_path('public/js'),
            // Published package assets — this package's own included, which is what puts
            // the kit's factories in reach without the developer naming them.
            base_path('public/vendor'),
            // The sources, for a tree that has not been built yet. A registration reads the
            // same there; only the receiver's name differs, and this scan does not use it.
            base_path('resources/js'),
            // And the package's own shipped bundles, so an application that loads them from
            // the route fallback rather than a published copy is still measured.
            dirname(__DIR__, 2).'/dist',
        ];

        // Every package whose views this run is SCANNING contributes its own registration
        // source. A package that serves its bundle from its own route, or ships
        // it unbuilt, is in none of the defaults above, so every factory it registers reads
        // here as "named by a view, registered by nothing": the same words as a genuinely
        // dead panel, and the opposite meaning.
        //
        // ⚠️ This overturns a decision that was written down two methods below, and the
        // objection there is worth answering rather than deleting: going looking on its own
        // "would mean guessing a package layout, and a wrong guess re-introduces the same
        // ambiguity one level down". The reason that does not apply is that nothing here
        // guesses -- `registrationSourceIn()` probes three concrete directory names with
        // `is_dir()` and returns null when none is there. The command ALREADY trusts that
        // probe enough to print its result as a paste-ready command; a path good enough to
        // hand a developer is good enough to read.
        //
        // Scoped to the packages already in the scan surface, deliberately. Reading every
        // package under vendor/ would answer a question nobody asked, and a stale bundle in
        // an unrelated package could then register a name away.
        foreach ($paths as $path) {
            if (preg_match('#^(.*/vendor/[^/]+/[^/]+)/#', rtrim($path, '/').'/', $match) !== 1) {
                continue;
            }

            if (($source = $this->registrationSourceIn($match[1])) !== null) {
                $candidates[] = $source;
            }
        }

        // `--registrations` ADDS to that set; `--path` replaces its own. The asymmetry is
        // deliberate, and the two flags are not the same kind of thing.
        //
        // `--path` names WHAT to check, so narrowing it is the whole point of passing it.
        // `--registrations` names where to find EVIDENCE that something is registered, and
        // narrowing that does not narrow the check: it makes it wrong. A set that is missing
        // a source does not report less, it reports MORE -- every factory registered only in
        // the dropped source becomes an offender that does not exist.
        //
        // Which is precisely the trap the report's own hint used to walk a reader into: it
        // prints `--registrations=<package source>`, and under the old replacing semantics
        // that one flag threw away the application's own bundles on the way in.
        //
        // ⚠️ ISOLATION IS STILL REACHABLE, and it had to stay: replacing was not only a
        // default, it was the only way to ask "what does THIS bundle register, and nothing
        // else". Making the flag additive without a replacement for that would have removed
        // a working capability silently -- no test would have failed for the right reason,
        // and the answer would just quietly have become a different question.
        $candidates = $this->option('registrations-only') === true
            ? $given
            : array_merge($candidates, $given);

        $files = [];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $files[] = $candidate;

                continue;
            }

            if (! is_dir($candidate)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($candidate));

            foreach ($it as $entry) {
                if ($entry instanceof \SplFileInfo && $entry->isFile() && $entry->getExtension() === 'js') {
                    $files[] = $entry->getPathname();
                }
            }
        }

        /*
         * ⚠️ AND THE BLADE FILES OF THE SCANNED SURFACE, because a registration does not
         * have to live in a `.js` file to be real.
         *
         * Livewire's documented way to register an Alpine component is an `@script` block
         * in the component's own Blade view, and `Alpine.data('name', …)` inside one is a
         * registration like any other. Reading only `.js` therefore reported a CORRECT
         * registration as missing — and the report's own wording makes that expensive: it
         * says the element "renders VISIBLE and dead", which sends a developer to repair
         * something that already works.
         *
         * Reported twice from one application against v2.48.0.
         *
         * The surface is the one the audit already walks for `x-data`, so this adds no
         * reach beyond what is being judged: a name is looked for exactly where it is used.
         * `AlpineRegistrations::inSource()` is content-agnostic — it matches the call, not
         * the file type — so nothing else has to change.
         */
        $bladeFiles = [];

        foreach ($paths as $viewPath) {
            if (! is_dir($viewPath)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewPath));

            foreach ($it as $entry) {
                if ($entry instanceof \SplFileInfo && $entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')) {
                    $bladeFiles[] = $entry->getPathname();
                }
            }
        }

        $files = array_values(array_unique($files));
        $bladeFiles = array_values(array_unique($bladeFiles));

        $names = AlpineRegistrations::inFiles($files);

        /*
         * ⚠️ ONLY THE `@script` REGIONS OF A BLADE FILE, NEVER THE WHOLE FILE — and the
         * difference is the one the reporter warned about before this was written.
         *
         * `@script` is the only placement that actually registers: Livewire re-runs it when
         * the component initializes, which is before its directives are evaluated. A bare
         * `<script>` in a Livewire view is NOT re-executed on a DOM patch, and an
         * `Alpine.data()` call after `alpine:init` arrives too late to register anything.
         *
         * Counting both alike would trade a false alarm for SILENCE, and silence is the
         * more expensive direction: it hides exactly the class this arm exists to report —
         * an element that renders visible and dead.
         */
        foreach ($bladeFiles as $bladeFile) {
            $source = (string) file_get_contents($bladeFile);

            if (preg_match_all('/@script\b(.*?)@endscript\b/s', $source, $blocks) !== false) {
                foreach ($blocks[1] as $block) {
                    $names = array_merge($names, AlpineRegistrations::inSource($block));
                }
            }
        }

        $names = array_values(array_unique($names));
        sort($names);

        return ['names' => $names, 'files' => array_merge($files, $bladeFiles)];
    }

    /**
     * @return array<int, string>
     */
    private function resolvePaths(): array
    {
        /** @var array<int, string> $given */
        $given = (array) $this->option('path');

        // An explicit `--path` names the surface outright, so `--vendor` does not widen it:
        // the two would otherwise combine into a set the developer did not ask for. Nothing
        // is hidden by that — the namespaces outside the surface are still reported, which is
        // what makes the narrower reading safe rather than merely simpler.
        if ($given !== []) {
            // ABSOLUTE, and that is a correctness requirement rather than tidiness. Two
            // readers downstream ask which package a path belongs to by looking for a
            // `vendor` segment with a separator in front of it, and the spelling a
            // developer actually types has nothing in front of it at all:
            // `--path=vendor/acme/widgets/resources/views` begins AT `vendor`. Both readers
            // then found no package, so its own registration source was never added and
            // every factory it registers correctly was reported as registered by nothing —
            // the report for a genuinely dead panel, in the same words. The same run with
            // the absolute spelling of the same directory passed.
            //
            // Resolved against the working directory rather than `base_path()`, because
            // that is what `is_dir()` on the line below just did: a relative path that
            // survives the filter is by definition one the working directory resolves. A
            // path that does not is dropped here and reported as no surface at all.
            return array_map(
                self::normalizePath(...),
                array_values(array_filter($given, 'is_dir')),
            );
        }

        /** @var array<int, string> $viewPaths */
        $viewPaths = (array) config('view.paths', []);

        $paths = array_values(array_filter($viewPaths, 'is_dir'));

        if ($this->option('vendor') !== true) {
            return $paths;
        }

        foreach ($this->namespacedPaths() as $dirs) {
            foreach ($dirs as $dir) {
                if (! self::isCovered($dir, $paths)) {
                    $paths[] = $dir;
                }
            }
        }

        return $paths;
    }

    /**
     * The view directories a package registered under a namespace.
     *
     * `config('view.paths')` holds the APPLICATION's directories, and nothing else ever
     * enters it: `loadViewsFrom` calls `View::addNamespace()`, which writes a hint on the
     * finder instead. So the default surface of this audit — the application's own views —
     * excludes every packaged template by construction, and a package that ships a CSP-unsafe
     * directive is invisible to it.
     *
     * That is the failure shape this whole command exists against, at the one place it did
     * not look. Measured in an adopting application: the default run reported PASS over 24
     * expressions while a `--path` over one package's views rejected 23 of 60, in the same
     * app in the same moment. The pages had been throwing for three weeks.
     *
     * `getHints()` is on `FileViewFinder`, NOT on `ViewFinderInterface` — an application may
     * bind a finder of its own — so the call is guarded rather than assumed. An unreadable
     * surface is reported as unknown, never as empty: those two must not print alike, which
     * is the same rule the zero-expression branch in `handle()` is built on.
     *
     * @return array<string, array<int, string>>
     */
    private function namespacedPaths(): array
    {
        $finder = View::getFinder();

        if (! method_exists($finder, 'getHints')) {
            return [];
        }

        $hints = [];

        /** @var array<string, array<int, string>|string> $raw */
        $raw = $finder->getHints();

        foreach ($raw as $namespace => $dirs) {
            $existing = array_values(array_filter((array) $dirs, 'is_dir'));

            if ($existing !== []) {
                $hints[$namespace] = $existing;
            }
        }

        return $hints;
    }

    /**
     * The registered namespaces whose directories the scan surface does not reach.
     *
     * Reported rather than scanned by default, and that split is deliberate: a defect in a
     * package's views is not something the adopting application can fix, so failing its
     * gate on one would make this command unusable there. Naming the gap costs nothing and
     * is the whole difference between "clean" and "clean over part of the tree" — the two
     * states this report was indistinguishable between.
     *
     * @param  array<int, string>  $paths
     * @return array<string, array<int, string>>
     */
    private function unscannedNamespaces(array $paths): array
    {
        $unscanned = [];

        foreach ($this->namespacedPaths() as $namespace => $dirs) {
            $outside = array_values(array_filter(
                $dirs,
                static fn (string $dir): bool => ! self::isCovered($dir, $paths),
            ));

            if ($outside !== []) {
                $unscanned[$namespace] = $outside;
            }
        }

        return $unscanned;
    }

    /**
     * Whether a directory already sits inside the scan surface.
     *
     * A published package namespace resolves to `resources/views/vendor/<namespace>`, which
     * is UNDER an application view path and therefore already scanned — reporting it as a
     * gap would send a developer to re-scan what they just scanned. The containment test is
     * on the path with a trailing separator, so `…/views/vendor-x` is not read as a child of
     * `…/views/vendor`.
     *
     * @param  array<int, string>  $paths
     */
    private static function isCovered(string $dir, array $paths): bool
    {
        $dir = self::normalizePath($dir);

        foreach ($paths as $path) {
            $path = self::normalizePath($path);

            if ($dir === $path || str_starts_with($dir.DIRECTORY_SEPARATOR, $path.DIRECTORY_SEPARATOR)) {
                return true;
            }
        }

        return false;
    }

    private static function normalizePath(string $path): string
    {
        $real = realpath($path);

        return rtrim($real !== false ? $real : $path, DIRECTORY_SEPARATOR);
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
     * happens to matter.
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

        // A directive NAME carries more structure than its base: any number of further
        // `:segment` parts, and — on a bare base — `.modifier` parts. Both are repeatable,
        // and the pattern allowed exactly ONE optional colon segment and no dot tail at all.
        //
        // That is not a cosmetic tightness. An event name may itself contain a colon —
        // `x-on:visual-feedback:open.window` is how a package namespaces its events — and
        // such an attribute matched no alternative here, so it was never collected, never
        // parsed, and never counted. It did not read as a gap: the run reported a smaller
        // `Scanned N` and a clean verdict, which is the one shape this command must never
        // take. Measured in an adopting application, the two expressions that opened its
        // widget were both written that way; under a policy without `unsafe-eval` neither
        // could run, and the audit named 60 expressions without either of them.
        //
        // The same tightness had four more faces, each found by putting the SAME broken
        // expression on a scanned sibling and on the unscanned form in one file:
        // `@vf:open.window` (the `x-on` shorthand), `wire:vf:open` (which Livewire rewrites
        // to exactly that), `:xlink:href` (the `x-bind` shorthand) and `x-model.live` /
        // `x-intersect.once` (a dot modifier on a bare base). One rule, so there is one
        // tightness to reason about rather than four.
        //
        // What stays OUT stays out by not being on the name list — `x-ref`, `x-cloak`,
        // `x-teleport` and `x-transition:enter` take a name, a selector or a class list, and
        // none of them is a prefix of a name here. The lookbehind still keeps `wire:model`
        // from matching as a bare `:model`, which is what it was added for.
        $segments = '(?::[\w.-]+)*';
        $pattern = '/(?<![\w:@.-])(?<attr>(?:'.$names.')(?:[.:][\w.-]+)*|wire:[\w.-]+'.$segments.'|@[\w.-]+'.$segments.'|:[\w.-]+'.$segments.')\s*=\s*(?<q>["\'])(?<expr>.*?)(?<!\\\\)\g{q}/s';

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
     * The literal a rewritten Livewire expression calls, or null when it calls none.
     *
     * The four names are Livewire's own SKIP list minus the ones that cannot be a callee:
     * `this`, `$wire` and `$event` are objects and `JSON` is a real global, so calling any
     * of them is a different question. What is left is exactly the set that parses as a
     * call and evaluates to a non-function.
     *
     * The lookbehind is what keeps the recommended repair out of the findings: in
     * `$wire['true'](1)` the name sits behind a quote, and in `a.true(…)` behind a dot —
     * both are member access rather than a bare identifier, and both are fine.
     */
    private static function literalCallee(string $expression): ?string
    {
        if (preg_match('/(?<![\w$.\\\'"\[])(true|false|null|undefined)\s*\(/u', $expression, $m) === 1) {
            return $m[1];
        }

        return null;
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
     * @param  array<int, string>  $paths
     * @param  array<string, array<int, string>>  $unscanned
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string, name: string}>  $unregistered
     * @param  array{names: list<string>, files: list<string>}  $registrationScan
     */
    private function report(array $found, array $offenders, array $unchecked, array $warnings = [], array $unresolved = [], array $inScript = [], array $paths = [], array $unscanned = [], array $unregistered = [], array $registrationScan = ['names' => [], 'files' => []]): int
    {
        // Named rather than implied. "Scanned" reads as "checked, and it works", and
        // the difference between what this measures and what a reader hears is the
        // exact gap a developer fell into: three dead listeners were found here, the
        // FIX for them passed here, and it was just as dead. The audit had produced
        // well-founded confidence, and on the second round that confidence did not
        // hold.
        $this->line(sprintf('Scanned %d Alpine expression(s) for GRAMMAR and resolvability.', count($found)));

        // WHERE, on the same footing as WHAT. The count is true of the directories that were
        // read, and a reader cannot tell from it which those were.
        if ($paths !== []) {
            $this->line(sprintf('Surface: %s', implode(', ', $paths)));
        }

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

        // The registration surface, and it sits AFTER the qualifier above rather than
        // between it and its claim. `CspAuditStatesItsBoundaryTest` measures the distance
        // between those two sentences in the source and fails when something drifts in —
        // which this block did, by 521 characters, on its first placement.
        //
        // It belongs on the same footing as the view surface for the same reason: the
        // `x-data` check is only as good as this set, and an empty one disables it silently.
        if ($registrationScan['names'] === []) {
            $this->line(sprintf(
                'No Alpine.data registration was found in %d JavaScript file(s), so `x-data` is NOT '
                .'checked against them.',
                count($registrationScan['files']),
            ));
            $this->line('Reported rather than assumed: zero registrations and zero found files look the same');
            $this->line('from here, and treating either as "nothing is registered" would make every `x-data`');
            $this->line('in the tree an offender. Point --registrations=… at your built bundle.');
        } else {
            $this->line(sprintf(
                'Registrations: %d name(s) across %d JavaScript file(s) — `x-data` is held against these.',
                count($registrationScan['names']),
                count($registrationScan['files']),
            ));
        }

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

        // Last of the qualifiers, so it sits directly above the verdict it qualifies.
        //
        // This is a statement about the SURFACE rather than about any expression, which is
        // why it is neither a violation nor a warning about the code: nothing was found
        // here, because nothing here was read. It stays out of the exit code deliberately —
        // a package's views are not the application's to repair, and reddening its gate on
        // one would get this command removed from the build rather than fixed.
        if ($unscanned !== []) {
            $this->line('');
            $this->warn(sprintf('%d registered view namespace(s) were NOT part of this run.', count($unscanned)));
            $this->line('');
            $this->line('A package registers its views with loadViewsFrom, which writes a namespace hint on');
            $this->line('the view finder and never enters view.paths — so packaged templates are outside');
            $this->line('the default surface of this audit. Their expressions run in the same page under');
            $this->line('the same policy, and one outside the grammar is just as dead there.');
            $this->line('');

            foreach (array_slice($unscanned, 0, 10, true) as $namespace => $dirs) {
                $this->line(sprintf('  %s::', $namespace));

                foreach ($dirs as $dir) {
                    $this->line(sprintf('    %s', $dir));
                }
            }

            if (count($unscanned) > 10) {
                $this->line(sprintf('  …and %d more.', count($unscanned) - 10));
            }

            $this->line('');
            $this->line('Run with --vendor to include them, or --path=… to scan one on its own. This is');
            $this->line('not counted as a failure: a defect in a package\'s views is not yours to fix, and');
            $this->line('an audit that fails your build over one gets deleted rather than read.');
        }

        // ⚠️ REPORTED BEFORE THE VERDICT, because a scope that was never registered is not a
        // grammar problem and would otherwise sit under a PASS.
        //
        // What it looks like on the page: the element gets no scope, `x-show` cannot
        // evaluate and therefore never sets `display: none`, while Alpine's init removes
        // `x-cloak` regardless. The panel is VISIBLE with every control in it dead — so the
        // symptom points at the stylesheet and the cause is a missing script.
        if ($unregistered !== []) {
            $this->line('');
            $this->error(sprintf(
                '%d `x-data` expression(s) name a factory nothing registers:',
                count($unregistered),
            ));
            $this->line('');

            foreach (array_slice($unregistered, 0, 20) as $hit) {
                $this->line(sprintf('  %s:%d', $hit['file'], $hit['line']));
                $this->line(sprintf('    x-data="%s" — `%s` is not registered.', $hit['expression'], $hit['name']));
            }

            if (count($unregistered) > 20) {
                $this->line(sprintf('  …and %d more.', count($unregistered) - 20));
            }

            $this->line('');
            $this->line('Each of these renders VISIBLE and dead: no scope means `x-show` never evaluates,');
            $this->line('so it never hides anything, and Alpine still removes `x-cloak`. Register the factory');
            $this->line('with Alpine.data(), or load the bundle that does.');

            foreach ($this->registrationSourceHint($unregistered, $registrationScan) as $line) {
                $this->line($line);
            }
        }

        if ($offenders === [] && ($inScript !== [] || $unregistered !== [])) {
            return self::FAILURE;
        }

        if ($offenders === []) {
            // The unqualified sentence is reserved for the run that earned it. It used to be
            // printed whenever nothing failed, which is how a developer read "resolves in
            // scope" off a run where the deciding text had been substituted away before the
            // parser ever saw it.
            $verdict = match (true) {
                $unchecked === [] && $unresolved === [] => 'PASS — every expression parses under Alpine\'s CSP grammar and resolves in scope.',
                $unchecked === [] => 'PASS — with '.count($unresolved).' expression(s) resting on a substitution (listed above).',
                default => 'PASS — every expression this audit could check parses under Alpine\'s CSP grammar.',
            };

            // The verdict carries the surface, for the same reason it carries the substitution
            // count: a developer who reads PASS stops looking, so everything that qualifies it
            // has to be in the sentence they stop on. An unqualified PASS over an incomplete
            // surface reads exactly like one over a complete surface — and that is how an
            // application ran green for three weeks while its pages threw on every request.
            if ($unscanned !== []) {
                $verdict = rtrim($verdict, '.').sprintf(
                    ' — over the scanned surface only, with %d registered namespace(s) outside it (listed above).',
                    count($unscanned),
                );
            }

            // The `x-data` half of this command is GATED on a non-empty registration set, so a
            // PASS earned with an empty one is a pass over a check that never ran.
            // The qualifier belongs in the verdict for the same reason the unscanned one does:
            // a developer who reads PASS stops reading, and everything that limits it has to be
            // in the sentence they stop on. It is already explained three paragraphs up; that is
            // where the repair is, and this is where the reader is.
            if ($registrationScan['names'] === []) {
                $verdict = rtrim($verdict, '.').' — grammar only. `x-data` was NOT checked: no '
                    .'Alpine.data registration was found to hold it against.';
            }

            $this->info($verdict);

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

    /**
     * The question that comes BEFORE the repair, when an offending view is a package's.
     *
     * ⚠️ Reported from a starter kit whose gate stayed red over a package that was
     * CORRECT. A package that serves its bundle from its own route — a deliberate
     * choice, because an inline `<script>` under a nonce-less `script-src 'self'` is
     * refused with no error and no log — appears in neither of the sources this run
     * defaults to. Every factory it registers then reads as "named by a view, registered
     * by nothing", which is word-for-word the report for a genuinely dead panel. The two
     * cases are indistinguishable in the output.
     *
     * That matters because the repair printed above is expensive in the wrong direction:
     * a reader who follows it files a false ticket against a correct package and turns a
     * working screen off. The ticket costs somebody else's time; the shutdown is noticed
     * only when a person misses the panel.
     *
     * So this asks whether the run READ the package's own source, and hands over the
     * command that answers it. What it deliberately does NOT do is go looking on its own:
     * that would mean guessing a package layout, and a wrong guess here re-introduces the
     * same ambiguity one level down. The flag exists and is documented — what was missing
     * is the pointer to it at the place somebody needs it.
     *
     * @param  array<int, array{file: string, line: int, attribute: string, expression: string, name: string}>  $unregistered
     * @param  array{names: list<string>, files: list<string>}  $registrationScan
     * @return list<string>
     */
    private function registrationSourceHint(array $unregistered, array $registrationScan): array
    {
        $packages = [];

        foreach ($unregistered as $hit) {
            if (preg_match('#^(.*/vendor/[^/]+/[^/]+)/#', $hit['file'], $match) === 1) {
                $packages[$match[1]] = true;
            }
        }

        if ($packages === []) {
            return [];
        }

        $lines = [
            '',
            sprintf(
                'At least one of these lives in a package, and that changes the first question. This run '
                .'read %d JavaScript file(s) for registrations;',
                count($registrationScan['files']),
            ),
            'a package that serves its bundle from its own route, or ships it unbuilt, is in none of them.',
            'A factory it registers correctly then reads here exactly like one nothing registers — same',
            'words, opposite meaning. Point --registrations at the package\'s own source before filing',
            'anything upstream or turning a surface off:',
            '',
        ];

        foreach (\array_slice(array_keys($packages), 0, 5) as $root) {
            $views = is_dir($root.'/resources/views') ? $root.'/resources/views' : $root;
            $source = $this->registrationSourceIn($root);

            // The scan now reads a scanned package's own source by itself, so for
            // most packages the printed command is advice the run already took. Telling a
            // reader to point at a file this run has read is worse than saying nothing: they
            // run it, get the identical report, and conclude the tool is broken. When it was
            // already read, the finding is CONFIRMED rather than pending -- which is the more
            // useful sentence, and the one the closing line below promises.
            $alreadyRead = $source !== null && array_filter(
                $registrationScan['files'],
                static fn (string $f): bool => $f === $source || str_starts_with($f, rtrim($source, '/').'/'),
            ) !== [];

            if ($source === null) {
                $lines[] = sprintf('  %s — no resources/js/ or dist/ to point at; find what its provider publishes.', self::shorten($root));
            } elseif ($alreadyRead) {
                $lines[] = sprintf(
                    '  %s — its own %s was read by this run and does not register the name; the finding stands.',
                    self::shorten($root),
                    basename($source),
                );
            } else {
                $lines[] = sprintf(
                    '  php artisan wirekit:csp-audit --path=%s --registrations=%s',
                    self::shorten($views),
                    self::shorten($source),
                );
            }
        }

        if (\count($packages) > 5) {
            $lines[] = sprintf('  …and %d more package(s).', \count($packages) - 5);
        }

        $lines[] = '';
        $lines[] = 'If it still reports them with the package\'s own source in scope, the finding is real.';

        return $lines;
    }

    /**
     * Where a package plausibly keeps the registrations this run did not read.
     *
     * Ordered by how likely it is to be the SOURCE rather than a copy: an unbuilt
     * `resources/js` is what an in-house package ships, `dist` is what a package builds
     * for itself, and `public` is what it publishes. The first that exists wins — this is
     * a pointer for a person to check, not a resolution the verdict rests on.
     */
    private function registrationSourceIn(string $packageRoot): ?string
    {
        foreach (['/resources/js', '/dist', '/public'] as $candidate) {
            if (is_dir($packageRoot.$candidate)) {
                return $packageRoot.$candidate;
            }
        }

        return null;
    }

    /**
     * Trim the application root off a path so the printed command is one a person can paste.
     *
     * An absolute path from a container prints a directory the reader does not have.
     */
    private static function shorten(string $path): string
    {
        $root = base_path().'/';

        return str_starts_with($path, $root) ? substr($path, \strlen($root)) : $path;
    }
}
