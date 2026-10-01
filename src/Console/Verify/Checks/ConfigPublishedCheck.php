<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class ConfigPublishedCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkConfigPublished();
    }

    /**
     * Check that config/wirekit.php has been published.
     * Not strictly required (mergeConfigFrom provides defaults), but recommended
     * so developers can customize fonts, icons, and chart adapters.
     */
    private function checkConfigPublished(): void
    {
        if (! file_exists(config_path('wirekit.php'))) {
            $this->reportWarn('config/wirekit.php not published (optional but recommended)');
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-config');

            return;
        }

        $this->reportPass('config/wirekit.php published');

        $this->checkConfigDrift();
        $this->checkEnvReads();
    }

    /**
     * Report the `WIREKIT_*` switches the published config still reads through `env()`.
     *
     * Since 2.59.0 the stub reads every switch through `EnvValue::get()`, where a blank line
     * such as `WIREKIT_DEDUPE_IDS=` in `.env` keeps the default. `env()` returns that line as
     * an empty string, and a config published before then keeps calling it: the fix lives in
     * the stub and never reaches the copy. Information, like a config that predates options,
     * because the copy still works as it did.
     */
    private function checkEnvReads(): void
    {
        $source = file_get_contents(config_path('wirekit.php'));

        if (! is_string($source)) {
            return;
        }

        $switches = self::switchesReadThroughEnv($source);

        if ($switches === []) {
            return;
        }

        $this->reportInfo('published config reads '.count($switches).' WIREKIT_* switch(es) through env()');
        $this->line('  '.implode(', ', $switches));
        $this->line('  Since 2.59.0 the config stub reads them through EnvValue::get(), which keeps');
        $this->line('  the default for a blank line such as WIREKIT_DEDUPE_IDS= in .env; env()');
        $this->line('  returns it as an empty string. Replace each env(\'WIREKIT_…\') with');
        $this->line('  \\Pushery\\WireKit\\Support\\EnvValue::get(\'WIREKIT_…\').');
    }

    /**
     * The `WIREKIT_*` names a PHP source passes to the `env()` helper as its first argument, in
     * order of appearance and without repeats.
     *
     * Read with PHP's tokenizer, so a mention in a comment or inside a string does not count,
     * and neither does a method or a static call that happens to be named `env`.
     *
     * @return list<string>
     */
    private static function switchesReadThroughEnv(string $source): array
    {
        $tokens = array_values(array_filter(
            token_get_all($source),
            static fn (array|string $token): bool => ! is_array($token)
                || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        $names = [];

        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }

            $isHelper = ($token[0] === T_STRING && strtolower($token[1]) === 'env')
                || ($token[0] === T_NAME_FULLY_QUALIFIED && strtolower($token[1]) === '\\env');

            if (! $isHelper) {
                continue;
            }

            $before = $tokens[$i - 1] ?? null;

            if (is_array($before) && in_array($before[0], [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true)) {
                continue;
            }

            $argument = $tokens[$i + 2] ?? null;

            if (($tokens[$i + 1] ?? null) !== '(' || ! is_array($argument) || $argument[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $name = substr($argument[1], 1, -1);

            if (str_starts_with($name, 'WIREKIT_') && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Config nodes whose KEYS belong to the developer, not to this package.
     *
     * An icon alias they invent, a font family they host. The stub can document
     * that such a map exists; it can never list what will be in it. So a diff of
     * key names against the stub is structurally incapable of saying anything
     * true about these nodes — every correct use looks like an option that was
     * removed.
     *
     * Add a node here when its keys are chosen by the reader. The test that
     * covers this asserts both directions, so an entry that stops being opaque
     * shows up rather than lingering.
     *
     * @var list<string>
     */
    private const OPAQUE_CONFIG_MAPS = [
        'icons.aliases',
        'fonts.fallbacks',
        // The class seam. `WireKit::resolveClasses()` reads
        // `wirekit.components.<name>.classes.<block>`, and the block names are the
        // component's own — `base`, `segment-selected`, and so on for every component in
        // the catalog. The stub cannot list them: it would have to carry a `classes` entry
        // for every component times each of its blocks, and the entry is a full class
        // string a developer replaces rather than a default they tweak.
        //
        // So without this entry every correct use of a supported seam would be reported as
        // an option that had been removed, and with `--fail-on=warning` that is a red gate
        // whose three ways out are all bad: throw away working styling, run red forever, or
        // stop reading the lane.
        //
        // A `*` matches exactly one path segment. That precision is the point: it exempts
        // `components.button.classes.base` and leaves `components.button.legacyRounding`
        // reportable, which is the case the counter-check next door pins.
        'components.*.classes',
        // And the same seam one segment deeper, because a SUB-component's name is
        // dotted. `card.header` and `sidebar.item` are single components with two-segment
        // names, so their seam is `components.sidebar.item.classes.active` — which
        // `components.*.classes` cannot match, since `*` is exactly one segment and that
        // precision is deliberate.
        //
        // Reported twice from two applications before this line existed, and the first
        // report was closed by the pattern above: `components.link.classes.base` and
        // `components.segmented-control.classes.segment-selected` are flat names and were
        // fixed, while `components.sidebar.item.classes.active` kept being called residue.
        // A partly-repaired exemption reads as a repaired one — the second report looks
        // like a regression rather than the half that was never covered.
        //
        // Two segments and no more: sub-components nest exactly one level in this catalog
        // (a registry key is a flat name or a singly-dotted one, never deeper), so a third `*`
        // would exempt paths that do not exist and weaken the counter-case next door.
        'components.*.*.classes',
    ];

    /**
     * Whether a flattened config path sits under one of the developer-keyed nodes.
     *
     * A `*` in a pattern matches exactly one segment, never a run of them, so a pattern
     * cannot quietly widen into its neighbors as the config grows.
     */
    private static function isOpaqueConfigPath(string $path): bool
    {
        foreach (self::OPAQUE_CONFIG_MAPS as $pattern) {
            if (! str_contains($pattern, '*')) {
                if ($path === $pattern || str_starts_with($path, $pattern.'.')) {
                    return true;
                }

                continue;
            }

            $patternSegments = explode('.', $pattern);
            $pathSegments = explode('.', $path);

            // The path has to reach the node before it can be inside it.
            if (count($pathSegments) < count($patternSegments)) {
                continue;
            }

            $matches = true;

            foreach ($patternSegments as $i => $segment) {
                if ($segment !== '*' && $segment !== $pathSegments[$i]) {
                    $matches = false;

                    break;
                }
            }

            if ($matches) {
                return true;
            }
        }

        return false;
    }

    /**
     * Report the options the published config never learned about, grouped by section.
     *
     * A published config is a snapshot of the day it was published. WireKit now
     * merges recursively, so a missing key still resolves and nothing breaks —
     * but the developer's own file no longer shows the full configurable surface,
     * and they cannot set what they cannot see. Naming the gap is the difference
     * between "my config lists everything" and "my config lists what existed in
     * February".
     *
     * Deliberately a WARN: re-publishing overwrites the developer's edits, so this
     * is information, not an instruction.
     *
     * The comparison is over flattened leaf paths rather than top-level sections: a
     * missing nested key is as invisible to the developer as a missing section, and a
     * comparison of sections alone would report such a file as covering every option
     * this version offers. The runtime still resolves missing keys, since the merge is
     * recursive, but an application cannot configure what its own config file does not
     * show. The output is grouped by owning section, so a config that predates most of
     * them reads as a handful of lines instead of a wall.
     */
    private function checkConfigDrift(): void
    {
        $publishedPath = config_path('wirekit.php');
        $packagePath = dirname(__DIR__, 2).'/../../config/wirekit.php';

        if (! is_file($packagePath)) {
            return;
        }

        $published = require $publishedPath;
        $package = require $packagePath;

        if (! is_array($published) || ! is_array($package)) {
            return;
        }

        $missingSections = array_diff(array_keys($package), array_keys($published));

        // Components are the section that grows every release, so it gets its own
        // count rather than being reported as one missing key among others.
        $missingComponents = [];

        if (isset($package['components'], $published['components'])
            && is_array($package['components']) && is_array($published['components'])) {
            $missingComponents = array_diff(
                array_keys($package['components']),
                array_keys($published['components'])
            );
        }

        // Every option, not just the two scalars at the top. A leaf missing inside
        // a section the file already declares was invisible to the old comparison,
        // and that is the common case: sections are added rarely, options within
        // them every release.
        //
        // The same three filters as the other direction. Without them a configuration with
        // working `icons.aliases` would be told the option is missing, and the printed remedy
        // — `vendor:publish --force` — would overwrite the aliases it is complaining about.
        //
        // The mechanism is the leaf/branch case below, mirrored. The stub carries
        // `icons.aliases => []`, a LEAF. A project that uses the feature carries
        // `icons.aliases.slack`, `.discord`, …, a BRANCH. Flattened, the package name exists
        // and the project's does not, so a plain diff calls it missing.
        $publishedKeys = array_keys(self::flattenConfig($published));

        $missingLeaves = array_values(array_filter(
            array_diff(array_keys(self::flattenConfig($package)), $publishedKeys),
            static function (string $path) use ($publishedKeys): bool {
                if (preg_match('/(^|\.)\d+(\.|$)/', $path) === 1) {
                    return false;
                }

                if (self::isOpaqueConfigPath($path)) {
                    return false;
                }

                // Leaf here, branch there — the mirror of the case below. A package leaf the
                // project has gone DEEPER on is not missing; it is in use.
                foreach ($publishedKeys as $publishedKey) {
                    if (str_starts_with($publishedKey, $path.'.')) {
                        return false;
                    }
                }

                return true;
            }
        ));

        // The other direction: a key the published file still carries and the package
        // no longer offers. Nothing breaks — the merge simply keeps it — so it is
        // silent forever, and it is the residue a major upgrade leaves behind: a knob
        // the developer believes is doing something, still sitting in their file.
        //
        // A differing VALUE is deliberately NOT drift. The published file is where an
        // application records its own decisions, and a check that objected to those
        // would be wrong about its own purpose. Only the presence of a name is
        // compared, in both directions.
        //
        // List contents are skipped, because a numeric index is not the name of a
        // knob: a developer whose list is shorter than the package's would otherwise
        // be told that `foo.3` has gone missing.
        $packageKeys = array_keys(self::flattenConfig($package));

        $orphanedLeaves = array_values(array_filter(
            array_diff(array_keys(self::flattenConfig($published)), $packageKeys),
            static function (string $path) use ($packageKeys): bool {
                // A numeric index is not the name of a knob.
                if (preg_match('/(^|\.)\d+(\.|$)/', $path) === 1) {
                    return false;
                }

                // Opaque maps. Some nodes are keyed by the developer, not by this
                // package: an icon alias they invent, a font family they host. The
                // stub cannot list those keys, so a diff against it would report every
                // correct use of the feature as a dead option and then tell them to
                // delete it: `icons.aliases.sun` and `.moon` drive the glyphs of the
                // theme toggle, and `fonts.fallbacks.*` has `[]` as its stub value, so
                // no correct use of either can ever match.
                if (self::isOpaqueConfigPath($path)) {
                    return false;
                }

                // LEAF HERE, BRANCH THERE. `components.checkbox => []` in the
                // developer's file is a leaf; the stub carries
                // `components.checkbox => ['size' => 'md', …]`, a branch. Flattening
                // puts `components.checkbox` on one side and `components.checkbox.size`
                // on the other, and a plain diff calls the first an orphan.
                //
                // It is not: the key IS offered, at a different depth. An empty
                // override of a current component is a no-op, not residue from an
                // earlier version — and the difference matters, because the remedy
                // printed below is deletion.
                foreach ($packageKeys as $packageKey) {
                    if (str_starts_with($packageKey, $path.'.')) {
                        return false;
                    }
                }

                return true;
            }
        ));

        if ($missingSections === [] && $missingComponents === [] && $missingLeaves === []
            && $orphanedLeaves === []) {
            $this->reportPass('published config covers every option this version offers');

            return;
        }

        // Two different facts, so two different sentences. "Predates" tells the reader
        // to republish; an orphan tells them to delete a line, and reporting both under
        // one heading would send them to the wrong remedy for half of it.
        //
        // And two different severities. An option the published file does not show still
        // resolves, because the configuration is merged recursively, so nothing is
        // misconfigured: this is information, and it does not trip `--fail-on=warning`.
        // Reported as a warning, it turned the gate of every application that runs with
        // that threshold red after each release that added an option, although nothing in
        // those applications had changed. An orphan below stays a warning: it is a setting
        // the developer believes is doing something.
        if ($missingSections !== [] || $missingComponents !== [] || $missingLeaves !== []) {
            $this->reportInfo('published config predates options this version offers');
        }

        if ($missingSections !== []) {
            $this->line('  Missing sections: '.implode(', ', $missingSections));
        }

        if ($missingComponents !== []) {
            $this->line('  Missing component defaults: '.count($missingComponents)
                .' ('.implode(', ', array_slice($missingComponents, 0, 5))
                .(count($missingComponents) > 5 ? ', …' : '').')');
        }

        // Named while the list is short, grouped once it is long — because the two
        // situations are different situations.
        //
        // The realistic one is an upgrade across a minor: one to five new options, and
        // there the NAME is the whole message. "Missing options: 2 across 2 key(s)"
        // hides which two, and the two can need very different attention — one may be a
        // real decision (`assets.middleware`, where the asset routes left the `web`
        // group and unnamed middleware is lost silently) and the other purely
        // informational. A reader in a hurry files the summary under "config is old"
        // and walks past the decision.
        //
        // The other situation is a config published long ago, or never: the published
        // file carries a couple of hundred leaves, the large majority of them under
        // `components`, so naming each would bury the reader instead of informing them.
        // That is why the grouping exists and it stays for that case.
        //
        // The default is printed alongside, because the command is holding it already —
        // it read the package's own config to compute the difference. Without it the
        // reader's next step is to open a file and look up what they were just told
        // about.
        $packageLeaves = self::flattenConfig($package);
        $nameThreshold = 12;

        if ($missingLeaves !== [] && count($missingLeaves) <= $nameThreshold) {
            $this->line('  Missing options: '.count($missingLeaves));

            foreach ($missingLeaves as $path) {
                $this->line(sprintf(
                    '    %-34s (default: %s)',
                    $path,
                    self::describeConfigValue($packageLeaves[$path] ?? null)
                ));
            }
        } elseif ($missingLeaves !== []) {
            $byOwner = [];

            foreach ($missingLeaves as $path) {
                $owner = str_contains($path, '.') ? substr($path, 0, strrpos($path, '.')) : $path;
                $byOwner[$owner] = ($byOwner[$owner] ?? 0) + 1;
            }

            arsort($byOwner);

            $this->line('  Missing options: '.count($missingLeaves).' across '.count($byOwner).' key(s)');

            foreach (array_slice($byOwner, 0, 8, true) as $owner => $count) {
                $this->line('    '.$owner.': '.$count.($count === 1 ? ' option' : ' options'));
            }

            if (count($byOwner) > 8) {
                $this->line('    … and '.(count($byOwner) - 8).' more');
            }
        }

        if ($missingSections !== [] || $missingComponents !== [] || $missingLeaves !== []) {
            $this->line('  They still resolve — WireKit merges recursively — but your file');
            $this->line('  does not show them. Re-publish to see the full surface (this');
            $this->line('  OVERWRITES your edits, so diff first):');
            $this->line('  php artisan vendor:publish --tag=wirekit-config --force');
        }

        if ($orphanedLeaves !== []) {
            $this->reportWarn('published config carries '.count($orphanedLeaves)
                .' option(s) this version no longer offers');

            // Printed in full. The truncated form hid half of a finding whose
            // whole point was which keys were named: a reader shown "… and 2 more"
            // cannot tell whether the hidden two are the same false alarm as the
            // eight above or something real.
            foreach ($orphanedLeaves as $path) {
                $this->line('    '.$path);
            }

            // Deliberately a suggestion, not an instruction. This is a diff against the
            // stub, and a diff is evidence, not a verdict — it cannot see a key read by
            // code that never appears in the stub, and telling the reader to delete such
            // keys could remove configuration that drives what their page renders.
            $this->line('  These names are not in this version\'s config stub. That usually means');
            $this->line('  they are left over from an earlier version — check whether anything');
            $this->line('  still needs them before removing.');
        }
    }

    /**
     * A config default, short enough to sit at the end of a report line.
     *
     * The point is that the reader does not have to open a file to find out what they
     * were just told about — so it has to be readable rather than complete. A long
     * array is summarized by its size: knowing an option defaults to eleven entries is
     * the useful part, and printing all eleven would push the NEXT missing option off
     * the screen.
     *
     * `mixed`, because a config default is any value a PHP config file can return.
     */
    private static function describeConfigValue(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return $value === '' ? "''" : "'".$value."'";
        }

        if (is_array($value)) {
            return $value === [] ? '[]' : '['.count($value).' entries]';
        }

        return gettype($value);
    }

    /**
     * Flatten a config array to dotted leaf paths.
     *
     * A LEAF is any non-array value, plus an empty array — an empty array is a real, settable
     * option (`'presets' => []`), and treating it as "nothing below here" would drop it from
     * the comparison silently.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed> dotted path => value
     */
    private static function flattenConfig(array $config, string $prefix = ''): array
    {
        $flat = [];

        foreach ($config as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== []) {
                $flat += self::flattenConfig($value, $path);

                continue;
            }

            $flat[$path] = $value;
        }

        return $flat;
    }
}
