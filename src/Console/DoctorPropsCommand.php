<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Support\AttributeTarget;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\LegacyAxisProps;
use Pushery\WireKit\Support\PropsParser;
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

    protected $description = 'Static-analysis template linter — finds unknown or misspelled props on WireKit components, HTML attributes the rendered element does not use, slots passed as attributes, slot closing tags Blade does not compile as one, and older prop spellings on the shared semantic axes';

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

        $this->info("Scanning {$path} for unknown props, slots passed as attributes, swallowed slot closes and older prop spellings...");
        $this->line('');

        $findings = [];
        $slotFindings = [];
        $slotAttributeFindings = [];
        $inertAttributeFindings = [];
        $legacyFindings = [];
        $scanned = 0;

        // Per component, the named slots that are not also props: read from the component's
        // own template once per run rather than once per usage.
        $slotOnlyNames = [];

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

        // An application's own components that hand their attributes to one WireKit component,
        // read from the `components` directory of the scanned path. A call site of one is checked
        // against the props of the component it hands them to.
        $wrappers = $this->forwardingWrappers($path.'/components');

        foreach ($bladeFiles as $file) {
            $contents = (string) file_get_contents($file);

            $usesWrapper = false;

            foreach (array_keys($wrappers) as $wrapperName) {
                if (str_contains($contents, '<x-'.$wrapperName)) {
                    $usesWrapper = true;

                    break;
                }
            }

            if (! str_contains($contents, '<x-wirekit::') && ! $usesWrapper) {
                continue;
            }

            $scanned++;

            foreach ($this->gluedSlotCloses($contents) as $glued) {
                $slotFindings[] = [
                    'file' => str_replace(base_path().'/', '', $file),
                    'line' => $glued['line'],
                    'snippet' => $glued['snippet'],
                    'swallows' => $glued['swallows'],
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

                // A named slot written as an attribute never reaches the slot. The value lands
                // in the attribute bag, a name that is also an HTML attribute renders as one
                // (`title` becomes a tooltip), and the slot stays empty, so the heading or the
                // action it was meant to be is simply missing. Only a slot that is not also a
                // prop is meant here: a name that is both is right either way. A template with no
                // resolvable `@props` is skipped, as the unknown-prop check below skips it: an
                // internal partial handed its data as attributes reads every `$name` it prints
                // as a slot, and there is nothing to tell a slot from a variable against.
                $slotOnlyNames[$usage['name']] ??= $declared === [] ? [] : array_values(array_diff(
                    array_column(ComponentRegistry::slotsOf($usage['name']), 'name'),
                    ['slot'],
                    $declared,
                ));

                $asAttribute = array_values(array_filter(
                    $usage['attributes'],
                    fn (string $attribute): bool => in_array(
                        lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $attribute)))),
                        $slotOnlyNames[$usage['name']],
                        true,
                    ),
                ));

                foreach ($asAttribute as $attribute) {
                    $slotAttributeFindings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'component' => $usage['name'],
                        'attribute' => $attribute,
                        'slot' => lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $attribute)))),
                    ];
                }

                // An empty declared list means the component has no `@props` to
                // resolve (`glass`), and against an empty list EVERY attribute
                // reads as unknown. The gate returns early on exactly this, so the wave of
                // phantom findings cannot happen here — but say so, because a reader
                // wondering why a file is quiet deserves the reason in the code.
                // Reported once, as a slot, rather than a second time as an unknown prop.
                foreach (StrictnessGate::unknownPropNames(array_fill_keys(array_diff($usage['attributes'], $asAttribute), true), $declared) as $unknown) {
                    $suggestions = SuggestSimilar::byLevenshtein($unknown, $declared);

                    $findings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'component' => $usage['name'],
                        'prop' => $unknown,
                        'suggestions' => $suggestions,
                    ];
                }

                // Valid HTML, but not on the element this component renders its attributes onto:
                // `value` on a component whose bag lands on a `<span>` is an attribute nothing
                // reads, and the value never reaches the page.
                foreach (AttributeTarget::meaninglessOn($usage['name'], array_values(array_diff($usage['attributes'], $asAttribute)), $declared) as $attribute) {
                    $inertAttributeFindings[] = [
                        'file' => str_replace(base_path().'/', '', $file),
                        'component' => $usage['name'],
                        'attribute' => $attribute,
                        'element' => (string) AttributeTarget::elementFor($usage['name']),
                    ];
                }
            }

            // Call sites of the application's forwarding components. Only the unknown-prop question
            // is asked there: the wrapper sets what it sets, and the caller's attributes reach the
            // WireKit component as they would on its own tag.
            if ($usesWrapper) {
                foreach (BladeParser::tagsFromSource($contents) as $tag) {
                    $wrapperName = substr($tag['name'], 2);

                    if (! str_starts_with($tag['name'], 'x-') || ! isset($wrappers[$wrapperName]) || $tag['terminator'] === '<') {
                        continue;
                    }

                    $wrapper = $wrappers[$wrapperName];
                    $declared = ComponentRegistry::acceptedPropNames($wrapper['target']);

                    // A target whose props cannot be resolved would make every attribute unknown.
                    if ($declared === []) {
                        continue;
                    }

                    $declared = array_values(array_unique([...$declared, ...$wrapper['own']]));
                    $attributes = array_values(array_unique(array_map(
                        static fn (string $a): string => ltrim($a, ':'),
                        $tag['attributes'],
                    )));

                    foreach (StrictnessGate::unknownPropNames(array_fill_keys($attributes, true), $declared) as $unknown) {
                        $findings[] = [
                            'file' => str_replace(base_path().'/', '', $file),
                            'component' => $wrapper['target'],
                            'prop' => $unknown,
                            'suggestions' => SuggestSimilar::byLevenshtein($unknown, $declared),
                            'via' => $wrapperName,
                        ];
                    }
                }
            }
        }

        if ($findings === [] && $slotFindings === [] && $slotAttributeFindings === [] && $inertAttributeFindings === [] && $legacyFindings === []) {
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
                // many were in scope — and a tree full of templates and no
                // WireKit component reads as a large number and looks healthy.
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
                '  <fg=yellow>%s</> — %s has no prop <fg=red>%s</>%s',
                $finding['file'],
                isset($finding['via'])
                    ? "<x-{$finding['via']}>, which hands its attributes to <x-wirekit::{$finding['component']}>,"
                    : "<x-wirekit::{$finding['component']}>",
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

        foreach ($slotAttributeFindings as $finding) {
            $this->line(sprintf(
                '  <fg=yellow>%s</> — <x-wirekit::%s> takes <fg=red>%s</> as a slot, not an attribute: pass it as %s',
                $finding['file'],
                $finding['component'],
                $finding['attribute'],
                OutputFormatter::escape('<x-slot:'.$finding['slot'].'>')
            ));
        }

        if ($slotAttributeFindings !== []) {
            $this->line('');
            $this->warn(sprintf('%d slot(s) passed as an attribute.', count($slotAttributeFindings)));
            $this->line('A slot written as an attribute never reaches the slot: the value lands in the attribute');
            $this->line('bag, where a name that is also an HTML attribute renders as one (a `title` becomes a');
            $this->line('tooltip), and the slot stays empty. A test that looks for the text still passes, because');
            $this->line('the text is on the page, inside the attribute.');
        }

        foreach ($inertAttributeFindings as $finding) {
            $this->line(sprintf(
                '  <fg=yellow>%s</> — <x-wirekit::%s> renders <fg=red>%s</> onto %s, where it means nothing',
                $finding['file'],
                $finding['component'],
                $finding['attribute'],
                OutputFormatter::escape('<'.$finding['element'].'>')
            ));
        }

        if ($inertAttributeFindings !== []) {
            $this->line('');
            $this->warn(sprintf('%d attribute(s) the rendered element does not use.', count($inertAttributeFindings)));
            $this->line('Each is valid HTML on some element, but not on the one this component renders its');
            $this->line('attributes onto, so the value never reaches the page. A test that looks for the text');
            $this->line('still passes, because the text is on the page, inside the attribute. Pass it through');
            $this->line('the component\'s own prop or slot instead.');
        }

        foreach ($slotFindings as $finding) {
            // The snippet is markup, and the console formatter reads `<…>` as its own styling
            // tags — printed raw, the very thing being reported would be eaten on its way to
            // the reader.
            $this->line(sprintf(
                '  <fg=yellow>%s:%d</> — a slot closing tag is followed directly by %s: <fg=red>%s</>',
                $finding['file'],
                $finding['line'],
                $finding['swallows'] ? 'a parenthesis' : 'text',
                OutputFormatter::escape($finding['snippet'])
            ));
        }

        if ($slotFindings !== []) {
            $swallowing = count(array_filter($slotFindings, static fn (array $finding): bool => $finding['swallows']));

            $this->line('');
            $this->warn(sprintf('%d slot closing tag(s) Blade does not compile as written.', count($slotFindings)));
            $this->line('Blade rewrites a slot closing tag to `@endslot` with a space before it and nothing');
            $this->line('after, so whatever follows becomes part of the directive.');

            if ($swallowing < count($slotFindings)) {
                $this->line('Followed by a word or `::`, the directive is one Blade does not know: the slot never');
                $this->line('closes, its content lands in the default slot, the component is handed an empty');
                $this->line('string for it, and the directive is printed on the page as text.');
            }

            if ($swallowing > 0) {
                $this->line('Followed by a parenthesis, the parenthesis becomes the directive\'s argument list: the');
                $this->line('slot closes, and the parenthesized text never reaches the page.');
            }

            $this->line('Put a line break after the closing tag — or a space, unless the next character is a');
            $this->line('parenthesis.');
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
            if ($findings === [] && $slotFindings === [] && $slotAttributeFindings === [] && $inertAttributeFindings === []) {
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
            || $slotAttributeFindings !== []
            || $inertAttributeFindings !== []
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
     * which stays in the page as text, and `endSlot()` does not run: the named slot is still
     * the empty string the opening call seeded, its content is captured into the default
     * slot, and an output buffer stays open. A parenthesis — even after spaces or tabs —
     * becomes the argument list of `@endslot` itself, so the slot closes and only the
     * parenthesized text never reaches the page; `swallows` marks that form.
     *
     * Nothing about that throws or logs. It was found through a test runner reporting
     * "did not close its own output buffers", which an application has no equivalent of.
     *
     * The tag half of the pattern is Laravel's own, so this matches exactly what the
     * compiler rewrites and nothing it leaves alone.
     *
     * @return list<array{line: int, snippet: string, swallows: bool}>
     */
    private function gluedSlotCloses(string $contents): array
    {
        $hits = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (preg_match('/<\/\s*x[\-:]slot[^>]*>(?=\w|::|[ \t]*\()/', $line) === 1) {
                $hits[] = [
                    'line' => $index + 1,
                    'snippet' => mb_substr(trim($line), 0, 120),
                    // The parenthesis form only when no word or `::` form sits on the same line,
                    // because that one leaves the slot open whatever else the line holds.
                    'swallows' => preg_match('/<\/\s*x[\-:]slot[^>]*>(?=\w|::)/', $line) !== 1,
                ];
            }
        }

        return $hits;
    }

    /**
     * The application's components that hand their attributes to exactly one WireKit component.
     *
     * An application often gives a component a role of its own, `<x-button.main>` for a
     * `<x-wirekit::button surface="filled" intent="primary" {{ $attributes }}>`, and every
     * attribute a caller writes on the role reaches the WireKit component. A misspelled prop there
     * lands in the attribute bag exactly as it does on the WireKit tag, so it is checked against
     * the same props, and against the props the wrapper declares for itself.
     *
     * Only a template that passes `$attributes` to exactly ONE WireKit tag counts: with two, which
     * of them a caller's attribute is meant for cannot be known. A wrapper of a wrapper is not
     * followed.
     *
     * @return array<string, array{target: string, own: list<string>}> tag name => WireKit component and the wrapper's own props
     */
    private function forwardingWrappers(string $componentsDir): array
    {
        if (! is_dir($componentsDir)) {
            return [];
        }

        $wrappers = [];
        $root = rtrim($componentsDir, '/');

        foreach ($this->collectBladeFiles($root) as $file) {
            $contents = (string) file_get_contents($file);

            if (! str_contains($contents, '<x-wirekit::') || ! str_contains($contents, '$attributes')) {
                continue;
            }

            $targets = [];

            foreach (BladeParser::tagsFromSource($contents) as $tag) {
                if (! str_starts_with($tag['name'], 'x-wirekit::') || $tag['terminator'] === '<') {
                    continue;
                }

                if (str_contains(substr($contents, $tag['attrStart'], $tag['attrEnd'] - $tag['attrStart']), '$attributes')) {
                    $targets[] = substr($tag['name'], strlen('x-wirekit::'));
                }
            }

            if (count($targets) !== 1 || preg_match('/^[a-z0-9\-.]+$/', $targets[0]) !== 1) {
                continue;
            }

            // `button/main.blade.php` is `<x-button.main>`, and `button/index.blade.php` is `<x-button>`.
            $name = str_replace('/', '.', substr($file, strlen($root) + 1, -strlen('.blade.php')));
            $name = (string) preg_replace('/(^|\.)index$/', '', $name);

            if ($name === '') {
                continue;
            }

            $wrappers[$name] = [
                'target' => $targets[0],
                'own' => array_column(PropsParser::parseSource($contents), 'name'),
            ];
        }

        return $wrappers;
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
