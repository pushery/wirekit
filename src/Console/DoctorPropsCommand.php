<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\LegacyAxisProps;
use Pushery\WireKit\Support\StrictnessGate;
use Pushery\WireKit\Support\SuggestSimilar;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * Find misspelled props before a page renders.
 *
 * An unknown prop on a WireKit component is not an error at runtime. Blade passes it
 * through to the attribute bag, so `<x-wirekit::button intnet="danger">` renders a
 * perfectly good button in the WRONG intent, and the only trace is a log line the
 * developer has to already suspect exists in order to look for it.
 *
 * The strictness gate has known how to detect this since it shipped. What was missing is a
 * way to ASK: the knowledge only fired while a page was being rendered, so finding a typo
 * required visiting the page that carried it — which is exactly the visit somebody skips
 * when a page looks right.
 *
 * Static, so it covers a template nobody has opened yet, and modeled on
 * `wirekit:doctor:a11y` down to the `--fail-on` contract, because two doctors that behave
 * differently are two things to learn.
 *
 * It reports a second shape, and that one is a compiler rule rather than a typo: a slot
 * closing tag followed directly by a word is not a closing tag any more. Blade rewrites it
 * to `@endslot` with nothing after it, the word joins the directive name, and the slot never
 * closes — its content is captured into the DEFAULT slot, the component receives an empty
 * string where it expected the slot, and the directive is printed on the page as text. The
 * failure has no error and no log line of its own; it was found through a test runner
 * reporting an unclosed output buffer, which an application has no equivalent of.
 *
 * It lives here rather than in a doctor of its own because it answers the question this
 * command already asks — what will this template do when it renders — over the same files in
 * the same walk.
 */
class DoctorPropsCommand extends Command
{
    protected $signature = 'wirekit:doctor:props
        {path? : Path to scan (defaults to resources/views in the host app)}
        {--fail-on= : Treat findings as a non-zero exit. One of `error` (default) or `none`.}
        {--require-in-scope : Fail when no scanned template uses a WireKit component. For an application that uses WireKit everywhere, an empty scope means the linter went blind.}
        {--fail-on-legacy-axis : Also exit 1 on an older prop spelling. Off by default, because both spellings are supported API for the whole of v2.}';

    protected $description = 'Static-analysis template linter — finds unknown or misspelled props on WireKit components, slot closing tags Blade does not compile as one, and older prop spellings on the shared semantic axes';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('resources/views');

        if (! is_dir($path)) {
            $this->error("Path not found or not a directory: {$path}");

            return self::FAILURE;
        }

        $failOn = (string) ($this->option('fail-on') ?: 'error');

        if (! in_array($failOn, ['error', 'none'], true)) {
            $this->error("Invalid --fail-on value: {$failOn}. Allowed: error / none.");

            // FAILURE, never INVALID. Every wirekit:* command exits 1 on every error path.
            return self::FAILURE;
        }

        $this->info("Scanning {$path} for unknown props, swallowed slot closes and older prop spellings...");
        $this->line('');

        $findings = [];
        $slotFindings = [];
        $legacyFindings = [];
        $scanned = 0;

        // The walk is hoisted so its emptiness can be answered separately from the
        // finding set. Reading zero files and reporting "no unknown props" is a
        // pass about nothing — the same failure `wirekit:csp-audit` refuses one
        // command over, and the reason it refuses applies here word for word: a
        // linter that read nothing and said "clean" is worse than no linter,
        // because you stop looking.
        $bladeFiles = $this->collectBladeFiles($path);

        if ($bladeFiles === []) {
            $this->error(sprintf('Found no Blade templates in %s.', $path));
            $this->line('');
            $this->line('That is a failure rather than a pass: a linter that read nothing');
            $this->line('and reported "clean" is worse than no linter. Check the path.');

            return self::FAILURE;
        }

        foreach ($bladeFiles as $file) {
            $contents = (string) file_get_contents($file);

            if (! str_contains($contents, '<x-wirekit::')) {
                continue;
            }

            $scanned++;

            foreach ($this->gluedSlotCloses($contents) as $glued) {
                $slotFindings[] = [
                    'file' => str_replace(base_path().'/', '', $file),
                    'line' => $glued['line'],
                    'snippet' => $glued['snippet'],
                ];
            }

            foreach (BladeParser::extractWireKitComponentUsagesFromSource($contents) as $usage) {
                // A flat LIST of names, and getting this shape wrong is silent in both
                // directions. `extractProps` returns prop RECORDS (name, default, type_hint
                // …), while `unknownPropNames` compares with `in_array($key, $declared)` —
                // against the VALUES. Hand it the records and every attribute is unknown;
                // hand it a name-keyed map and every attribute is unknown again, because the
                // names are then keys. Neither mistake throws; both just report a correct
                // template as full of typos.
                // Read before the unknown-prop pass, and deliberately not inside it: an older
                // spelling IS a declared prop, so it never reaches `unknownPropNames` and
                // cannot be found by widening that check.
                foreach (LegacyAxisProps::findIn($usage['name'], $usage['attributes']) as $legacy => $canonical) {
                    $legacyFindings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'component' => $usage['name'],
                        'legacy' => $legacy,
                        'canonical' => $canonical,
                    ];
                }

                // `acceptedPropNames()`, not `extractProps()`: the question here is whether
                // Blade would do anything with this attribute, and it does for an `@aware` key
                // written straight onto the tag as much as for a declared prop. Asking the
                // narrower question reported `variant` on `accordion.item` — a call in this
                // package's own `faq-item` — as a typo, while the runtime warning over the same
                // render stayed correctly quiet.
                $declared = ComponentRegistry::acceptedPropNames($usage['name']);

                // An empty declared list means the component's `@props` could not be
                // resolved (`glass`, `fonts`), and against an empty list EVERY attribute
                // reads as unknown. The gate returns early on exactly this, so the wave of
                // phantom findings cannot happen here — but say so, because a reader
                // wondering why a file is quiet deserves the reason in the code.
                foreach (StrictnessGate::unknownPropNames(array_fill_keys($usage['attributes'], true), $declared) as $unknown) {
                    $suggestions = SuggestSimilar::byLevenshtein($unknown, $declared);

                    $findings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'component' => $usage['name'],
                        'prop' => $unknown,
                        'suggestions' => $suggestions,
                    ];
                }
            }
        }

        if ($findings === [] && $slotFindings === [] && $legacyFindings === []) {
            // Two different clean results, and collapsing them is how the first one
            // hides. Templates exist but none of them use a WireKit component: the
            // run is honest, there was simply nothing in scope — so it succeeds and
            // says the count out loud rather than implying it checked something.
            if ($scanned === 0) {
                // Two readings of one state, and which is right depends on the
                // caller rather than on us. For a library, "nothing in scope" is
                // not a fault: an application may simply not use the components,
                // and a linter that failed there would be switched off. For an
                // application that uses them EVERYWHERE, the same state means the
                // linter went blind — a second view path, a renamed directory, a
                // `path` argument pointing somewhere empty.
                //
                // So the default stays permissive and the caller gets a flag. The
                // alternative a developer is left with otherwise is matching this
                // sentence in a shell script, which is a text interface nobody
                // agreed to: rewording it silently deletes their check. That is
                // the same dependency the empty-directory and no-templates cases
                // were given exits for; this is the third layer.
                //
                // Deliberately NOT a scraped count. "Scanned N template(s)"
                // answers the neighboring question — how many files ran, not how
                // many were in scope — and a tree with thirty templates and no
                // WireKit component reads as thirty and looks healthy.
                if ($this->option('require-in-scope')) {
                    $this->error(sprintf(
                        'Scanned %d Blade template(s); none of them use a WireKit component.',
                        count($bladeFiles),
                    ));
                    $this->line('');
                    $this->line('--require-in-scope was given, so this is a failure rather than a pass:');
                    $this->line('the linter had nothing to measure. Check the path and the view tree.');

                    return self::FAILURE;
                }

                $this->info(sprintf(
                    'Scanned %d Blade template(s); none of them use a WireKit component, so there was nothing to check.',
                    count($bladeFiles),
                ));

                return self::SUCCESS;
            }

            $this->info(sprintf(
                'No unknown props found, and no slot closing tag swallowed by the text after it, across %d template(s) using WireKit components.',
                $scanned
            ));

            return self::SUCCESS;
        }

        foreach ($findings as $finding) {
            $this->line(sprintf(
                '  <fg=yellow>%s</> — <x-wirekit::%s> has no prop <fg=red>%s</>%s',
                $finding['file'],
                $finding['component'],
                $finding['prop'],
                $finding['suggestions'] === []
                    ? ''
                    : ' — did you mean '.implode(' or ', array_map(fn ($s) => "`{$s}`", $finding['suggestions'])).'?'
            ));
        }

        if ($findings !== []) {
            $this->line('');
            $this->warn(sprintf('%d unknown prop(s) across %d template(s).', count($findings), $scanned));
            $this->line('An unknown prop is not an error at render time — it lands in the attribute bag and');
            $this->line('the component renders with its default instead. That is why these are worth finding');
            $this->line('here rather than by noticing a page looks subtly wrong.');
        }

        foreach ($slotFindings as $finding) {
            // The snippet is markup, and the console formatter reads `<…>` as its own styling
            // tags — printed raw, the very thing being reported would be eaten on its way to
            // the reader.
            $this->line(sprintf(
                '  <fg=yellow>%s:%d</> — a slot closing tag is followed directly by text: <fg=red>%s</>',
                $finding['file'],
                $finding['line'],
                OutputFormatter::escape($finding['snippet'])
            ));
        }

        if ($slotFindings !== []) {
            $this->line('');
            $this->warn(sprintf('%d slot closing tag(s) Blade does not compile as one.', count($slotFindings)));
            $this->line('Blade rewrites a slot closing tag to `@endslot` with a space before it and nothing');
            $this->line('after, so whatever follows becomes part of the directive. The slot then never closes:');
            $this->line('its content lands in the default slot, the component is handed an empty string for');
            $this->line('it, and the directive is printed on the page as text. Put a line break after the');
            $this->line('closing tag — or a space, unless the next character is a parenthesis.');
        }

        foreach ($legacyFindings as $finding) {
            $this->line(sprintf(
                '  <fg=yellow>%s</> — <x-wirekit::%s %s=…> is the older spelling of <fg=green>%s</>',
                $finding['file'],
                $finding['component'],
                $finding['legacy'],
                $finding['canonical']
            ));
        }

        if ($legacyFindings !== []) {
            $this->line('');

            // Said out loud when this is the only class present, because the all-clean line
            // lives in a branch this run did not take. Without it a reader sees a list of
            // advisories and cannot tell whether the linter got as far as the two checks
            // that actually break a page.
            if ($findings === [] && $slotFindings === []) {
                $this->info(sprintf(
                    'No unknown props, and no slot closing tag swallowed by the text after it, across %d template(s).',
                    $scanned
                ));
                $this->line('');
            }

            $this->line(sprintf('%d prop(s) written in the older spelling of a shared axis.', count($legacyFindings)));
            $this->line('Nothing is broken: both spellings resolve to the same value and both are supported');
            $this->line('for the whole of v2. The kit settled on `intent` for the color role and `surface` for');
            $this->line('the surface treatment, so one vocabulary reads the same across every component —');
            $this->line('this is where you find out which one that is, rather than by comparing two pages.');

            if (! $this->option('fail-on-legacy-axis')) {
                $this->line('Pass --fail-on-legacy-axis to make these a failure once your own tree is converted.');
            }
        }

        // Two independent verdicts under one master switch. Folding them together is what
        // would make the advisory class dishonest: an older spelling is valid API, so it must
        // not turn a passing lint into a failing one unless the caller asked for exactly that.
        // `--fail-on=none` still wins over both — it is the documented "report, never gate".
        $failed = $failOn !== 'none' && (
            $findings !== []
            || $slotFindings !== []
            || ($legacyFindings !== [] && $this->option('fail-on-legacy-axis'))
        );

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Slot closing tags Blade will not compile as one.
     *
     * `ComponentTagCompiler::compileSlots()` rewrites every slot closing tag to `@endslot`
     * with a space before it and nothing after, and the directive compiler then reads what
     * follows as part of the directive. A word or `::` makes it a single unknown directive,
     * which stays in the page as text; a parenthesis — even after spaces or tabs — becomes
     * its argument list and never reaches the page at all. Either way `endSlot()` does not
     * run, so the named slot is still the empty string the opening call seeded, its content
     * is captured into the default slot, and an output buffer stays open.
     *
     * Nothing about that throws or logs. It was found through a test runner reporting
     * "did not close its own output buffers", which an application has no equivalent of.
     *
     * The tag half of the pattern is Laravel's own, so this matches exactly what the
     * compiler rewrites and nothing it leaves alone.
     *
     * @return list<array{line: int, snippet: string}>
     */
    private function gluedSlotCloses(string $contents): array
    {
        $hits = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match('/<\/\s*x[\-:]slot[^>]*>(?=\w|::|[ \t]*\()/', $line) === 1) {
                $hits[] = [
                    'line' => $index + 1,
                    'snippet' => mb_substr(trim($line), 0, 120),
                ];
            }
        }

        return $hits;
    }

    /**
     * @return list<string>
     */
    private function collectBladeFiles(string $root): array
    {
        $files = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry->isFile() && str_ends_with($entry->getFilename(), '.blade.php')) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
