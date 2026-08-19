<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use BaconQrCode\Renderer\ImageRenderer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pushery\WireKit\Fonts\FontCss;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Support\DirectoryHash;
use Pushery\WireKit\Support\TailwindVersion;
use Pushery\WireKit\WireKit;

/**
 * Verifies that WireKit is correctly integrated into the host application.
 *
 * Checks asset publishing, Blade directives, Tailwind @source, directive order,
 * and optional dependencies. Run after `composer install/update` or when
 * components look unstyled or non-interactive.
 *
 * Usage:
 *   php artisan wirekit:verify
 *
 * Returns exit code 1 on failure — can be wired into CI or a pre-commit hook.
 * Reference: https://docs.wirekit.app/getting-started/integration
 */
class VerifyInstallationCommand extends Command
{
    protected $signature = 'wirekit:verify
        {--tier= : Filter to a single check tier — "package" (asset / config / directive checks for the WireKit install itself) or "environment" (Laravel-level state checks like compiled-view freshness). Default = run every check.}
        {--fix : proactively self-heal missing public/vendor/wirekit/* assets by triggering `vendor:publish --tag=wirekit-assets --force`. Useful right after a fresh clone (where the assets are .gitignored) to avoid a red doctor on first run.}';

    protected $description = 'Verify WireKit integration (assets, directives, Tailwind @source, optional deps)';

    /**
     * Register the `wirekit:doctor` alias on the SAME Symfony command
     * instance. v2.0.0 shipped a separate DoctorCommand subclass which
     * appeared as TWO entries in `php artisan list wirekit`; v2.1.0
     * collapses both names to one canonical entry — `php artisan list`
     * now shows `wirekit:verify` with `Aliases: wirekit:doctor` underneath,
     * matching the de-facto Laravel ecosystem norm for diagnostic
     * commands.
     *
     * Existing CI scripts and docs that reference `wirekit:doctor`
     * continue to work — Symfony Console routes alias invocations to
     * the canonical command without behavior change.
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setAliases(['wirekit:doctor']);
    }

    private int $passed = 0;

    private int $warned = 0;

    private int $failed = 0;

    /** @var string[]|null Memoized layout file paths (used by multiple checks) */

    /** @var string[]|null Memoized all blade file paths */
    private ?array $allBladeFiles = null;

    public function handle(): int
    {
        $tier = $this->option('tier');
        if ($tier !== null && ! in_array($tier, ['package', 'environment'], true)) {
            $this->error("Unknown tier '{$tier}'. Available: package, environment.");

            return self::FAILURE;
        }

        $this->info('WireKit Integration Check');
        $this->line('');

        // Package-tier checks — verify the WireKit install itself
        // (assets, config, directives, optional deps). These bite when
        // the package's own install / upgrade misfires.
        if ($tier === null || $tier === 'package') {
            $this->checkTailwindVersion();
            $this->checkPublishedAssets();
            $this->checkAssetFreshness();
            $this->checkTailwindSource();
            $this->checkConfigPublished();
            $this->checkBladeDirectives();
            $this->checkAlpineJs();
            $this->checkBundleConfig();
            $this->checkPublishedViewsStaleness();
            $this->checkReplacingPersonalizations();
            $this->checkAiManifestStaleness();
            $this->checkFontAssets();
            $this->checkCssImportAntiPattern();
            $this->checkOptionalDependencies();
            $this->checkChartUsageWithoutAdapter();
            $this->checkChartJsRegistration();
            $this->checkBuiltCssHasWireKitUtilities();
            $this->checkTokenAlignment();
            $this->checkRootDarkSymmetry();
            $this->checkAlpinePluginCleanupHygiene();
        }

        // Environment-tier checks — verify the Laravel host environment
        // (compiled-view freshness, config-cache vs source drift, etc.).
        // These bite during interactive dev / CI even when the package
        // install is clean. Run `wirekit:doctor --tier=environment` to
        // get only these without the package-tier noise.
        if ($tier === null || $tier === 'environment') {
            $this->checkCompiledViewsFreshness();
            $this->checkSilentValidationTypos();
        }

        // ── Summary ──
        $this->line('');
        $this->line(sprintf(
            '  %s passed, %s warnings, %s failed',
            $this->passed,
            $this->warned,
            $this->failed
        ));

        if ($this->failed > 0) {
            $this->line('');
            $this->error('Integration incomplete — see failures above.');
            $this->line('  Reference: https://docs.wirekit.app/getting-started/integration');

            return self::FAILURE;
        }

        if ($this->warned > 0) {
            $this->line('');
            $this->components->warn('Integration OK with warnings — consider fixing them.');

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    /**
     * Check that wirekit.css and wirekit.js are published to public/vendor/wirekit/.
     */
    private function checkPublishedAssets(): void
    {
        $vendorDir = public_path('vendor/wirekit');
        // The vendor directory exists but the JS/CSS files don't — strong signal
        // that the developer ran `wirekit:install` once (which created the dir
        // and added it to .gitignore), then deployed without `vendor:publish
        // --force` in the post-deploy hook (or pulled with the dir gitignored
        // and the deploy stripped the contents). Different from the
        // never-installed case: the first-time-install fix is a single
        // `vendor:publish`; the missed-deploy-hook fix is wiring the publish
        // into every future deploy.
        $vendorDirExists = is_dir($vendorDir);

        $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.css'));
        $jsMissing = ! file_exists(public_path('vendor/wirekit/wirekit.js'));

        // --fix self-heal. When the
        // developer runs `wirekit:verify --fix` right after a fresh
        // clone (the common case where `public/vendor/wirekit/` is
        // gitignored), proactively trigger the publish so the doctor
        // can re-verify instead of just printing the publish command.
        if (($cssMissing || $jsMissing) && $this->option('fix')) {
            $this->line('  <fg=yellow>--fix:</> Publishing wirekit-assets...');
            $this->call('vendor:publish', [
                '--tag' => 'wirekit-assets',
                '--force' => true,
            ]);
            // Re-test the asset paths after the publish — if they're
            // now present, treat the check as a pass.
            $cssMissing = ! file_exists(public_path('vendor/wirekit/wirekit.css'));
            $jsMissing = ! file_exists(public_path('vendor/wirekit/wirekit.js'));
        }

        if ($cssMissing) {
            $this->reportFail('wirekit.css not found in public/vendor/wirekit/');
        } else {
            $this->reportPass('wirekit.css published');
        }

        if ($jsMissing) {
            $this->reportFail('wirekit.js not found in public/vendor/wirekit/');
        } else {
            $this->reportPass('wirekit.js published');
        }

        // Only emit the consolidated fix hint once (not twice for css+js).
        if ($cssMissing || $jsMissing) {
            $this->line('  Fix: php artisan vendor:publish --tag=wirekit-assets --force');
            $this->line('  Or:  php artisan wirekit:verify --fix   (self-heal)');
            if ($vendorDirExists) {
                // Empty-but-existing directory — point at the deploy-hook scenario.
                $this->line('  Hint: public/vendor/wirekit/ exists but is empty.');
                $this->line('        Wire `vendor:publish --tag=wirekit-assets --force` into your post-deploy hook.');
                $this->line('        Default `wirekit:install` adds the dir to .gitignore, so deploys strip it.');
                $this->line('        See https://docs.wirekit.app/getting-started/integration "Deploy Checklist" for Forge / Envoyer / GitHub Actions snippets.');
            }
        }
    }

    /**
     * Compare MD5 hashes of published assets vs source files in the package.
     * Outdated assets cause subtle bugs (missing new CSS variables, stale JS).
     */
    private function checkAssetFreshness(): void
    {
        // The ground set is DERIVED from what the package ships, not listed here.
        //
        // It was a list — `wirekit.css` and `wirekit.js` — written when those were
        // the only two bundles. Every bundle added since inherited no check, because
        // nothing required the list to grow with them, and a list that nothing
        // requires to grow is a list that stops being true quietly.
        //
        // What that cost is not theoretical. Measured in an application straight
        // after `composer update`: six published bundles differed from the package
        // (`wirekit-alpine.js`, `wirekit-alpine.csp.js`, `wirekit-apex.js`,
        // `wirekit-optimistic.js`, `wirekit-tiptap.js`, `wirekit.core.js`) and this
        // command reported 22 passed, 0 failed. New markup from the new PHP, animated
        // by the old JavaScript — which is precisely the state this command exists to
        // name.
        //
        // And it is the state an upgrade GUARANTEES rather than risks: `composer
        // update` does not touch `public/vendor/wirekit/`, that directory is normally
        // gitignored, and nothing refreshes it until a person remembers. CI publishes
        // fresh on every run, so CI is green over a condition it never has — the two
        // drift apart and the green confirms the developer in the wrong one.
        //
        // A file present in the package but never published stays the presence
        // check's business: checkFileFreshness() returns early when the published
        // copy is absent, so nothing is reported twice.
        $distDir = __DIR__.'/../../dist';
        $sources = glob($distDir.'/*.{css,js}', GLOB_BRACE) ?: [];
        sort($sources);

        foreach ($sources as $source) {
            $name = basename($source);

            $this->checkFileFreshness($name, $source, public_path('vendor/wirekit/'.$name));
        }

        // The Liquid Glass extension, but ONLY where it has been installed —
        // checkFileFreshness stays silent when the published file is absent, so
        // an application that never opted in hears nothing about it.
        //
        // Its own command publishes it, not `vendor:publish`, and that is the
        // whole reason this check exists: the extension is COPIED into
        // public/, so a composer update refreshes vendor/ and leaves the served
        // copy untouched. The page then keeps rendering the old stylesheet
        // through any number of deploys, and nothing anywhere says so — which
        // is exactly how a corrected refraction shipped and stayed invisible.
        foreach (['wirekit-glass.css', 'wirekit-glass.js'] as $file) {
            $this->checkFileFreshness(
                $file,
                __DIR__.'/../../resources/glass/'.$file,
                public_path('vendor/wirekit/glass/'.$file),
                'php artisan wirekit:glass install'
            );
        }
    }

    private function checkFileFreshness(string $name, string $sourcePath, string $publishedPath, ?string $fixCommand = null): void
    {
        if (! file_exists($publishedPath) || ! file_exists($sourcePath)) {
            return; // Already reported as missing in checkPublishedAssets
        }

        if (md5_file($sourcePath) !== md5_file($publishedPath)) {
            $this->reportWarn("{$name} is outdated (source differs from published)");
            $this->line('  Fix: '.($fixCommand ?? 'php artisan vendor:publish --tag=wirekit-assets --force'));
        } else {
            $this->reportPass("{$name} is up to date");
        }
    }

    /**
     * Tailwind CSS v4+ is a hard requirement — WireKit's CSS uses the v4 engine
     * (the `@theme` / `@source` at-rules, `color-mix()`, `@property`). This
     * backstops the wirekit:install pre-flight gate — a v3 project that somehow
     * reached the doctor gets the SAME clear "upgrade to v4" message here,
     * instead of the misleading "Missing @source" the source-directive check
     * would emit (v3 has no `@source` concept). Detection is conservative
     * (positive evidence only).
     */
    private function checkTailwindVersion(): void
    {
        $basePath = base_path();

        if (TailwindVersion::isPreV4($basePath)) {
            $detected = TailwindVersion::detectMajor($basePath);
            $this->reportFail('Tailwind CSS v4+ required — detected '.($detected !== null ? "v{$detected}" : 'a pre-v4 release'));
            $this->line('  WireKit cannot run on Tailwind v3 — it uses the v4 engine (@theme, @source, color-mix(), @property).');
            $this->line('  Fix: npm install tailwindcss@latest @tailwindcss/vite@latest');
            $this->line('       then migrate your CSS to v4 (https://tailwindcss.com/docs/upgrade-guide) and rebuild.');

            return;
        }

        $major = TailwindVersion::detectMajor($basePath);
        if ($major !== null) {
            $this->reportPass("Tailwind CSS v{$major} (v4+ required)");
        }
        // Undetermined (no package.json tailwindcss entry, no v3 directives):
        // stay silent rather than add a noisy line — the assets/@source checks
        // still cover the practical setup.
    }

    /**
     * Check that resources/css/app.css has a @source directive scanning WireKit Blade templates.
     * Without this, Tailwind v4 won't generate utility classes used by WireKit components.
     */
    private function checkTailwindSource(): void
    {
        $cssFiles = glob(resource_path('css/*.css')) ?: [];
        $hasSource = false;

        foreach ($cssFiles as $file) {
            $content = file_get_contents($file);
            if (str_contains($content, 'wirekit') && str_contains($content, '@source')) {
                $hasSource = true;

                break;
            }
        }

        if ($hasSource) {
            $this->reportPass('Tailwind @source includes WireKit templates');
        } else {
            $this->reportFail('Missing @source for WireKit in Tailwind CSS');
            $this->line('  Fix: Add to resources/css/app.css:');
            $this->line('  @source "../../vendor/pushery/wirekit/resources/views/**/*.blade.php";');
        }
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
    }

    /**
     * Report top-level sections the published config never learned about.
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
     * It used to be top-level-only as well, with the same reasoning — that listing
     * every nested key would bury the signal. The reasoning was sound and the
     * conclusion was not, because the check kept its PASS line: of 186 leaves in
     * the shipped config it compared exactly TWO, and then told the developer
     * their file "covers every option this version offers". Reconstructed against
     * real tags: a config published at v2.20.0 and checked against v2.22.0 prints
     * that line while `a11y.motion_attribute` is missing from the file.
     *
     * The damage is pure invisibility — the runtime still resolves the missing
     * keys, since the merge is recursive — but an application cannot configure
     * what its own config file does not show, and it was explicitly told there
     * was nothing to see.
     *
     * So the comparison is now over flattened leaf paths, and the output is
     * grouped by owning section so 184 findings read as a handful of lines
     * instead of a wall. Burying the signal was the right fear; the answer is to
     * summarize it, not to stop measuring.
     */
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
    ];

    private function checkConfigDrift(): void
    {
        $publishedPath = config_path('wirekit.php');
        $packagePath = __DIR__.'/../../config/wirekit.php';

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
        $missingLeaves = array_diff(
            array_keys(self::flattenConfig($package)),
            array_keys(self::flattenConfig($published))
        );

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

                // OPAQUE MAPS. Some nodes are keyed by the DEVELOPER, not by this
                // package: an icon alias they invent, a font family they host. The
                // stub cannot list those keys, so a diff against it reports every
                // correct use of the feature as a dead option — and then tells them
                // to delete it.
                //
                // Measured from a consuming project: ten reported orphans, ten of
                // them wrong. Two were `icons.aliases.sun` / `.moon`, which drive the
                // glyphs of the theme toggle that page renders; following the advice
                // silently swaps the outline icon for the mini one — nothing throws,
                // nothing is missing, the drawing is just different. Four more were
                // `fonts.fallbacks.*`, a feature that SHIPPED IN THE SAME RELEASE as
                // this check, whose stub value is `[]` so that no correct use can
                // ever match.
                foreach (self::OPAQUE_CONFIG_MAPS as $prefix) {
                    if ($path === $prefix || str_starts_with($path, $prefix.'.')) {
                        return false;
                    }
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
        if ($missingSections !== [] || $missingComponents !== [] || $missingLeaves !== []) {
            $this->reportWarn('published config predates options this version offers');
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
        // The other situation is a config published long ago, or never: this package
        // has 195 leaves, 168 of them under `components`, so naming each would bury
        // the reader instead of informing them. That is why the grouping exists and it
        // stays for that case.
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

            // Deliberately weaker than it was. This is a diff against the stub, and
            // a diff is evidence, not a verdict — it cannot see a key read by code
            // that never appears in the stub. The previous wording ("Nothing reads
            // these … Delete them") sent a reader to delete configuration that was
            // driving what their page rendered.
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

    /**
     * Check that @wirekitStyles and @wirekitScripts directives are present in layout files.
     * Also verifies directive ordering: @wirekitScripts must come before @livewireScripts
     * so Alpine.js component registrations are available when Livewire initializes.
     */
    private function checkBladeDirectives(): void
    {
        // Bare-install INFO: when no layout file exists at any canonical
        // install path AND no @import alternative is configured in
        // app.css, emit a single INFO hint and skip the directive scan.
        // The natural state right after `wirekit:install` on a fresh
        // Laravel skeleton is "layout not yet written" — emitting two
        // FAIL lines plus the "Built app CSS" FAIL reads as if the
        // install failed, when actually the developer's next step is
        // simply to write the layout. Subsumes the historical "No Blade
        // files found" WARN because the empty-views-dir case is a
        // strict subset of "no canonical layout".
        if (! $this->hasAnyLayoutFile() && ! $this->hasWirekitCssImportInAppCss()) {
            $this->reportInfo('No app layout yet — run `php artisan wirekit:install`: it creates `resources/views/components/layouts/app.blade.php` via Livewire\'s `livewire:layout` and injects @wirekitStyles + @wirekitScripts (before @livewireScripts). Or create it yourself with `php artisan livewire:layout`, then re-run install.');

            return;
        }

        $bladeFiles = $this->findAllBladeFiles();

        if ($bladeFiles === []) {
            $this->reportWarn('No Blade files found — cannot verify directives');
            $this->line('  Searched: resources/views/');

            return;
        }

        $foundStyles = false;
        $foundScripts = false;
        $orderOk = true;
        $orderFailedFile = null;

        foreach ($bladeFiles as $file) {
            $rawContent = file_get_contents($file);
            // Strip Blade comments before scanning — otherwise a comment
            // containing the literal text `@livewireScripts` (e.g.
            // `{{-- Note: @livewireScripts must come AFTER @wirekitScripts --}}`)
            // makes strpos() return the comment's position, producing a
            // false-positive on the order check. Strip Blade comments
            // before scanning so an inline annotation referencing the
            // directive name doesn't mis-cue the order check.
            $content = preg_replace('/\{\{--.*?--\}\}/s', '', $rawContent) ?? $rawContent;

            if (str_contains($content, '@wirekitStyles')) {
                $foundStyles = true;
            }

            if (str_contains($content, '@wirekitScripts')) {
                $foundScripts = true;

                // Check directive order in every file where both directives appear
                if (str_contains($content, '@livewireScripts')) {
                    $wirekitPos = strpos($content, '@wirekitScripts');
                    $livewirePos = strpos($content, '@livewireScripts');

                    if ($wirekitPos > $livewirePos) {
                        $orderOk = false;
                        $orderFailedFile ??= $file;
                    }
                }
            }
        }

        // The @wirekitStyles directive is one of two valid setup paths;
        // the OTHER valid path is `@import 'wirekit.css'` in app.css.
        // checkCssImportAntiPattern() detects the second path and reports
        // it as PASS. To avoid a contradictory FAIL/PASS pair on the same
        // install, only fail @wirekitStyles when neither path is present.
        $hasImportPath = $this->hasWirekitCssImportInAppCss();

        if ($foundStyles) {
            $this->reportPass('@wirekitStyles directive found');
        } elseif ($hasImportPath) {
            $this->reportPass('@wirekitStyles not used (covered by `@import wirekit.css` in app.css — valid alternative)');
        } else {
            $this->reportFail('@wirekitStyles not found in any Blade file');
            $this->line('  Fix: Add @wirekitStyles in <head> of your layout');
            $this->line('  Or: @import \'../../vendor/pushery/wirekit/dist/wirekit.css\' in resources/css/app.css');
        }

        if ($foundScripts) {
            $this->reportPass('@wirekitScripts directive found');
        } else {
            $this->reportFail('@wirekitScripts not found in any Blade file');
            $this->line('  Fix: Add @wirekitScripts in <body> of your layout');
        }

        if ($foundScripts && ! $orderOk) {
            $this->reportFail('@wirekitScripts must appear BEFORE @livewireScripts');
            $this->line('  Reason: WireKit Alpine components must register before Livewire starts Alpine');
            if ($orderFailedFile !== null) {
                $this->line('  Found in: '.str_replace(base_path().DIRECTORY_SEPARATOR, '', $orderFailedFile));
            }
        } elseif ($foundScripts) {
            $this->reportPass('@wirekitScripts is before @livewireScripts (or no explicit @livewireScripts)');
        }
    }

    /**
     * Detect whether resources/css/app.css imports wirekit.css via a CSS
     * `@import` rule. This is the alternative-but-equivalent setup path
     * to the `@wirekitStyles` Blade directive — see the integration docs
     * "Tip: Both setup paths work in v1.3.0+".
     */
    private function hasWirekitCssImportInAppCss(): bool
    {
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            return false;
        }
        // Strip CSS comments first (see stripCssComments) so a commented
        // reference to an @import of wirekit.css isn't read as a real one.
        $content = $this->stripCssComments((string) file_get_contents($appCss));

        return (bool) preg_match('/@import\b[^;]*wirekit\.css/', $content);
    }

    /**
     * Check that Alpine.js is available in the application.
     * Without Alpine, all interactive WireKit components (modals, dropdowns, tooltips, etc.)
     * render as static HTML with no interactivity — the #1 reported issue.
     */
    private function checkAlpineJs(): void
    {
        // Livewire v4+ bundles Alpine.js — no separate import needed
        if ($this->detectLivewireVersion() >= 4) {
            $this->reportPass('Alpine.js provided by Livewire v4+');

            return;
        }

        $hasAlpine = false;

        // Check JS entry files for Alpine import
        $jsFiles = array_merge(
            glob(resource_path('js/app.js')) ?: [],
            glob(resource_path('js/app.ts')) ?: [],
            glob(resource_path('js/bootstrap.js')) ?: [],
            glob(resource_path('js/bootstrap.ts')) ?: [],
        );

        foreach ($jsFiles as $file) {
            $content = file_get_contents($file);
            if (preg_match('/alpinejs|alpine\.js/', $content)) {
                $hasAlpine = true;

                break;
            }
        }

        // Also check all blade files for CDN script tag
        if (! $hasAlpine) {
            foreach ($this->findAllBladeFiles() as $file) {
                $content = file_get_contents($file);
                if (str_contains($content, 'alpinejs') || str_contains($content, 'alpine.js') || str_contains($content, 'Alpine.start')) {
                    $hasAlpine = true;

                    break;
                }
            }
        }

        if ($hasAlpine) {
            $this->reportPass('Alpine.js detected');
        } else {
            $this->reportFail('Alpine.js not detected in JS entry files or layout');
            $this->line('  Fix: npm install alpinejs, then import and start in resources/js/app.js');
            $this->line('  Or add CDN: <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>');
        }
    }

    /**
     * Validate that wirekit.scripts.bundle config value is valid.
     * A typo (e.g. "ful" instead of "full") causes a 404 on the JS asset.
     */
    private function checkBundleConfig(): void
    {
        $bundle = config('wirekit.scripts.bundle', 'full');

        // `csp` belongs here. It is a shipped bundle with its own dist file and its own
        // documented reason to exist — an Alpine build that needs no 'unsafe-eval' — and
        // this list did not know about it. So `wirekit:verify` reported the one correct
        // choice a CSP-constrained app can make as an invalid value, and advised taking
        // it back. A check that tells you to undo a correct setting is worse than no
        // check: it is confidently wrong, in the one place a developer goes when they are
        // already unsure.
        $valid = ['full', 'core', 'csp'];

        if (in_array($bundle, $valid, true)) {
            $this->reportPass("JS bundle configured: {$bundle}");
        } else {
            $this->reportFail("Invalid wirekit.scripts.bundle value: '{$bundle}'");
            $this->line('  Valid values: full, core, csp');
        }
    }

    /**
     * Warn if WireKit views have been published (vendor override).
     * Published views override package views — after a WireKit update,
     * the published copies may be outdated and miss new features or fixes.
     */
    /**
     * The generated AI catalogs (`.boost/wirekit.json`, `.wirekit-schema.json`)
     * are written ONCE and never refreshed on their own — `wirekit:boost-skills`
     * and `wirekit:install` both bail early when the file already exists unless
     * `--force` is passed. So after a `composer update` that adds components or
     * props, a committed manifest silently goes stale and keeps feeding an AI
     * tool the OLD API surface. Warn when a manifest is older than the installed
     * package source it is derived from.
     */
    private function checkAiManifestStaleness(): void
    {
        // The newest mtime among the package sources the manifests are built
        // from — the registry + the component views. If a manifest predates it,
        // it was generated against an older package.
        $packageNewest = filemtime(__DIR__.'/../ComponentRegistry.php') ?: 0;
        $componentsDir = __DIR__.'/../../resources/views/components';
        if (is_dir($componentsDir)) {
            foreach (File::allFiles($componentsDir) as $file) {
                $packageNewest = max($packageNewest, $file->getMTime());
            }
        }

        $manifests = [
            '.boost/wirekit.json' => 'php artisan wirekit:boost-skills --force',
            '.wirekit-schema.json' => 'php artisan wirekit:export-json --pretty > .wirekit-schema.json',
        ];

        foreach ($manifests as $relative => $refreshCmd) {
            $target = base_path($relative);
            if (! is_file($target)) {
                continue; // not generated — nothing to check
            }
            if (filemtime($target) < $packageNewest) {
                $this->reportWarn("Generated AI catalog {$relative} is older than the installed WireKit package");
                $this->line('  It was generated against an earlier version — an AI tool reading it sees a stale API surface');
                $this->line("  Fix: {$refreshCmd}");
            }
        }
    }

    /**
     * Name the personalized blocks the application now owns.
     *
     * `WireKit::personalize()` takes two value shapes for a block, and they differ
     * in one consequence nobody is told about:
     *
     *   'base' => 'inline-flex …'                        REPLACES the shipped block
     *   'base' => fn (string $vendor) => $vendor.' …'    EXTENDS it
     *
     * A replacement is a legitimate choice and often the right one. What it also
     * does is end the flow of later improvements to that block — permanently, and
     * without a word. The personalization keeps looking like a decision somebody
     * made, which it is; that it has since swallowed three upstream changes is
     * visible nowhere. A consuming application found this by reading the installed
     * package, not by being told.
     *
     * So this reports, and does not judge: a WARN that names the blocks, because
     * the same output on a deliberate replacement is the point — the developer
     * sees what they own and can decide again. It is not a FAIL, and it never
     * suggests removing the personalization; the fix line offers the closure form
     * for the case where the delta was all that was wanted.
     */
    private function checkReplacingPersonalizations(): void
    {
        $owned = [];

        foreach (WireKit::personalizedComponents() as $component) {
            foreach (WireKit::personalizationFor($component) as $block => $value) {
                // A closure receives the vendor default and returns its own delta,
                // so it keeps inheriting. Only a finished string severs the link.
                if (is_string($value)) {
                    $owned[] = $component.'.'.$block;
                }
            }
        }

        if ($owned === []) {
            return; // nothing personalized, or every block extends — the quiet case
        }

        $count = count($owned);

        $this->reportWarn(
            "{$count} personalized class ".($count === 1 ? 'block replaces' : 'blocks replace').
            ' the shipped one'
        );
        $this->line('  '.implode(', ', $owned));
        $this->line('  A replaced block stops receiving later WireKit changes to it — silently, and for good');
        $this->line("  Fix (only if you wanted a delta): 'block' => fn (string \$vendor) => \$vendor.' your-classes'");
    }

    private function checkPublishedViewsStaleness(): void
    {
        $publishedViewsPath = resource_path('views/vendor/wirekit');

        if (! is_dir($publishedViewsPath)) {
            return; // Not published — nothing to check, this is the normal case
        }

        // Count published view files recursively (PHP glob() does not support **)
        $fileCount = count(File::allFiles($publishedViewsPath));

        if ($fileCount > 0) {
            $this->reportWarn("Published WireKit views detected ({$fileCount} files in views/vendor/wirekit/)");
            $this->line('  These override package views — after composer update they may be outdated');
            $this->line('  Fix: Delete resources/views/vendor/wirekit/ to use latest package views');
            $this->line('  Or re-publish: php artisan vendor:publish --tag=wirekit-views --force');
        }
    }

    /**
     * Check that font CSS files are published if custom fonts are configured.
     * When a font preset is set in config but the font files aren't published,
     * the GDPR-compliant self-hosted fonts won't load.
     */
    private function checkFontAssets(): void
    {
        $fontConfig = config('wirekit.fonts', []);

        // Reported before the publish state, and deliberately not gated on a
        // font being configured: a typo here is corrected to `swap` while
        // serving, so it produces no error anywhere and no visible difference
        // from having asked for `swap` on purpose. The only way to learn the
        // value never took is to be told.
        $rawDisplay = $fontConfig['display'] ?? FontCss::DEFAULT;

        if (! is_string($rawDisplay) || ! in_array(strtolower(trim($rawDisplay)), FontCss::VALID, true)) {
            $shown = is_string($rawDisplay) ? $rawDisplay : gettype($rawDisplay);
            $this->reportWarn("wirekit.fonts.display is '{$shown}', which is not a font-display value — falling back to '".FontCss::DEFAULT."'");
            $this->line('  Valid: '.implode(', ', FontCss::VALID));
        }

        $hasCustomFont = false;

        foreach (['sans', 'serif', 'mono'] as $category) {
            if (! empty($fontConfig[$category])) {
                $hasCustomFont = true;

                break;
            }
        }

        if (! $hasCustomFont) {
            return; // Using system fonts — no font assets needed
        }

        $fontDir = public_path('vendor/wirekit/fonts');

        if (is_dir($fontDir)) {
            // Font CSS publishes into nested category/name subdirs
            // (fonts/<category>/<name>/<name>.css — vendor:publish copies
            // resources/fonts/ verbatim), so scan RECURSIVELY. A top-level
            // glob("{$fontDir}/*.css") finds nothing even when fonts ARE
            // correctly published, producing a false "no CSS files found" warning.
            $cssFiles = $this->findFontCssFiles($fontDir);
            if ($cssFiles !== []) {
                // Presence alone used to PASS — but a directory of the previous
                // release's bytes satisfies that fully, so a stale-after-upgrade
                // state had no signal. Compare the published bytes against the
                // bundled ones, the same md5 check checkAssetFreshness runs for
                // wirekit.css / wirekit.js.
                // Named before the byte compare, because a display mismatch also
                // shows up there — and "outdated" would send someone looking for
                // an upgrade they never missed. Same fix, precise cause.
                $mismatches = $this->fontDisplayMismatches();
                $mismatchedKeys = array_column($mismatches, 'key');

                if ($mismatches !== []) {
                    $configured = FontCss::display();
                    $named = implode(', ', array_map(
                        static fn (array $m): string => "{$m['key']} declares {$m['declared']}",
                        $mismatches,
                    ));
                    $this->reportWarn("Published fonts do not carry the configured font-display '{$configured}' ({$named})");
                    $this->line('  Cause: `vendor:publish` copies the files verbatim, so they keep the shipped default.');
                    $this->line('  Fix: php artisan wirekit:publish-fonts --force');
                }

                $stale = array_values(array_diff($this->staleFontFamilies(), $mismatchedKeys));

                if ($stale !== []) {
                    $this->reportWarn('Font assets are outdated — the bundled release differs from the published copy ('.implode(', ', $stale).')');
                    $this->line('  Fix: php artisan wirekit:publish-fonts --force');
                } elseif ($mismatches === []) {
                    $this->reportPass('Font assets published ('.count($cssFiles).' font CSS files)');
                }
            } elseif ($this->isPackageDefaultFontConfig($fontConfig)) {
                // Empty dir + the package default ('inter'): the system-ui
                // fallback works out of the box, so this is an INFO — the same
                // treatment as the dir-missing branch below, not a false WARN.
                $this->reportInfo("Default 'inter' sans font configured (system-ui fallback works out of the box)");
                $this->line('  To self-host: php artisan vendor:publish --tag=wirekit-fonts');
            } else {
                // A non-default font was requested but no CSS is present — it
                // genuinely won't render until published. Real warning.
                $this->reportWarn('Font directory exists but no CSS files found');
                $this->line('  Fix: php artisan vendor:publish --tag=wirekit-fonts --force');
            }
        } else {
            // Package-default font configuration ('sans' => 'inter' shipped
            // in config/wirekit.php) + no published assets is the natural
            // state of a fresh install — emit an INFO hint, not a WARN,
            // so the doctor summary doesn't read as if something failed.
            // Anything OTHER than the package default IS a real warning
            // (the developer asked for a non-default font but the assets
            // never got published, which means it won't render).
            if ($this->isPackageDefaultFontConfig($fontConfig)) {
                $this->reportInfo("Default 'inter' sans font configured (system-ui fallback works out of the box)");
                $this->line('  To self-host: php artisan vendor:publish --tag=wirekit-fonts');
            } else {
                $this->reportWarn('Custom fonts configured but font assets not published');
                $this->line('  Fix: php artisan vendor:publish --tag=wirekit-fonts');
            }
        }
    }

    /**
     * Configured font families whose PUBLISHED bytes differ from the bundled
     * release — the stale-after-`composer update` state the presence-only check
     * could not see. Mirrors checkAssetFreshness's md5 compare for the font tree.
     *
     * @return list<string>
     */
    private function staleFontFamilies(): array
    {
        $stale = [];

        foreach (['sans', 'serif', 'mono'] as $category) {
            $key = config("wirekit.fonts.{$category}");

            if ($key === null || $key === '') {
                continue;
            }

            $preset = FontRegistry::get((string) $key);

            if ($preset === null) {
                continue;
            }

            $relative = dirname($preset->cssFile);
            $source = __DIR__.'/../../resources/fonts/'.$relative;
            $target = public_path('vendor/wirekit/fonts/'.$relative);

            // The transform is what `wirekit:publish-fonts` writes, not what the
            // package ships: a stylesheet gets `wirekit.fonts.display` substituted
            // on the way out. Comparing against the raw source instead would call
            // every non-default `font-display` permanently stale, and the fix it
            // printed would not change the answer.
            if (is_dir($target) && ! DirectoryHash::matches($source, $target, FontCss::publishTransform())) {
                $stale[] = $preset->key;
            }
        }

        return $stale;
    }

    /**
     * Configured families whose published stylesheet declares a different
     * `font-display` than the config asks for.
     *
     * This is the one path `wirekit.fonts.display` cannot reach on its own: a
     * plain `vendor:publish --tag=wirekit-fonts` is a framework-side file copy,
     * so those files keep the `swap` the package ships no matter what the config
     * says. Reporting it is what keeps the key a switch instead of a decoration
     * — the failure is otherwise completely silent, because nothing breaks when
     * a font loads with the wrong display, it just does not do what was asked.
     *
     * @return list<array{key: string, declared: string}>
     */
    private function fontDisplayMismatches(): array
    {
        $configured = FontCss::display();
        $mismatched = [];

        foreach (['sans', 'serif', 'mono'] as $category) {
            $key = config("wirekit.fonts.{$category}");

            if ($key === null || $key === '') {
                continue;
            }

            $preset = FontRegistry::get((string) $key);

            if ($preset === null) {
                continue;
            }

            $published = public_path($preset->publishedCssPath());

            if (! is_file($published)) {
                continue;
            }

            $declared = FontCss::declaredDisplays((string) file_get_contents($published));

            // A stylesheet declaring none is not a disagreement — there is no
            // promise to break. Only a value that is present and different is.
            if ($declared !== [] && $declared !== [$configured]) {
                $mismatched[] = ['key' => $preset->key, 'declared' => implode('/', $declared)];
            }
        }

        return $mismatched;
    }

    /**
     * Recursively collect every `.css` file under the published fonts dir.
     * Font CSS lands at fonts/<category>/<name>/<name>.css, so a shallow
     * glob("{$fontDir}/*.css") misses it — this iterator finds it at any depth.
     *
     * @return list<string>
     */
    private function findFontCssFiles(string $fontDir): array
    {
        $found = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fontDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
                $found[] = $file->getPathname();
            }
        }

        return $found;
    }

    /**
     * Detect whether the font config matches the package-shipped default
     * (config/wirekit.php → 'sans' => 'inter', serif + mono null). When
     * true, the "assets not published" state is a natural bare-install
     * condition, not a warning.
     */
    private function isPackageDefaultFontConfig(array $fontConfig): bool
    {
        return ($fontConfig['sans'] ?? null) === 'inter'
            && empty($fontConfig['serif'] ?? null)
            && empty($fontConfig['mono'] ?? null);
    }

    /**
     * Mirror of InstallCommand::addBladeDirectives() layout-path detection.
     * Used by checkBladeDirectives() to distinguish "no layout file yet"
     * (INFO — bare install, next step is on the developer) from "layout
     * exists but directives missing" (FAIL — real misconfiguration).
     *
     * Returns true when EITHER a single conventional layout file exists
     * (`views/components/layout.blade.php`) OR any `.blade.php` file lives
     * inside one of the conventional layout DIRECTORIES (Laravel 12's
     * `views/components/layouts/` or the legacy `views/layouts/`).
     * The directory-scan flavor matches real-world projects that ship
     * multiple sibling layouts (`app.blade.php`, `guest.blade.php`, etc.).
     */
    private function hasAnyLayoutFile(): bool
    {
        $singleFile = resource_path('views/components/layout.blade.php');
        if (file_exists($singleFile)) {
            return true;
        }

        $layoutDirs = [
            resource_path('views/components/layouts'),
            resource_path('views/layouts'),
        ];

        foreach ($layoutDirs as $dir) {
            if (! is_dir($dir)) {
                continue;
            }
            $files = glob($dir.'/*.blade.php');
            if ($files !== false && count($files) > 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Report which CSS-loading path the developer's app.css uses for wirekit.css.
     *
     * Both paths work as of v1.3.0 (the file ships with `:root {}` / `.dark {}`
     * blocks that resolve in any consumption context):
     *   1. @wirekitStyles Blade directive — emits a `<link>` tag (the
     *      "fastest" path, no Tailwind compile step required).
     *   2. @import from app.css — Tailwind v4 picks up the variables;
     *      slightly slower compile but useful when developers want a single
     *      bundled CSS file from Vite.
     *
     * Pre-v1.3.0 versions used `@theme {}` which browsers skipped as an
     * unknown at-rule via the `<link>` path, breaking the documented setup.
     * That's now fixed; this check just emits an informational line.
     */
    private function checkCssImportAntiPattern(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            return; // No app.css — nothing to check
        }

        // Strip CSS comments first — a commented "(not an @import of
        // wirekit.css)" note must not trip a false @import PASS.
        $content = $this->stripCssComments((string) file_get_contents($appCss));

        if (preg_match('/@import\b.*wirekit\.css/', $content)) {
            $this->reportPass('wirekit.css is @import-ed in app.css (valid setup path)');
        }
    }

    /**
     * Check optional dependencies: Chart.js adapter, QR Code package, and the
     * editor / map front-end peer dependencies (Tiptap, MapLibre GL / Leaflet).
     * These are INFO-level only — not required for core functionality.
     */
    private function checkOptionalDependencies(): void
    {
        $chartConfig = config('wirekit.charts.library');

        if ($chartConfig === 'chartjs') {
            $this->reportPass('Chart.js adapter configured');
        } elseif ($chartConfig === 'apexcharts' || config('wirekit.scripts.apex', false)) {
            // `scripts.apex` alone is enough to reach this check, and it was not.
            //
            // That switch emits the adapter bundle on every page independently of
            // `charts.library`, so an app can ship the adapter while the chart library is
            // set to something else — or to nothing. The check keyed on the library
            // alone, so exactly that configuration went unexamined: the adapter loads,
            // looks for `window.ApexCharts`, finds nothing, and every chart stays blank
            // while verify reports the installation healthy.
            $this->checkApexChartsAdapter();
        } else {
            $this->line('  <fg=cyan>i</> Chart adapter not configured (optional — set charts.library to "chartjs" or "apexcharts" in config/wirekit.php to enable <x-wirekit-chart>)');
        }

        if (class_exists(ImageRenderer::class)) {
            $this->reportPass('bacon/bacon-qr-code installed');
        } else {
            $this->line('  <fg=cyan>i</> bacon/bacon-qr-code not installed (optional — only needed for <x-wirekit::qr-code>)');
        }

        // Front-end peer dependencies for <x-wirekit::editor> and <x-wirekit::map>.
        // These are browser globals (window.wirekitEditor / window.maplibregl /
        // window.L), so a PHP command can't probe whether they're loaded — they
        // surface as a contextual INFO reminder, not a pass/fail check. Listed
        // here so the onboarding doctor mentions them, not just the component
        // pages. Each component degrades gracefully if its dependency is absent.
        $this->line('  <fg=cyan>i</> <x-wirekit::editor> needs a ProseMirror editor (optional — Tiptap recommended: npm install @tiptap/core @tiptap/starter-kit and expose window.wirekitEditor; only if you use the editor)');
        $this->line('  <fg=cyan>i</> <x-wirekit::map> needs a map engine (optional — npm install maplibre-gl or leaflet and load it before WireKit; only if you use the map)');
    }

    /**
     * Does the app's own JavaScript assign a browser global anywhere?
     *
     * Four things the previous one-line regex got wrong, all of them reported by
     * a developer whose working installation was told it was broken:
     *
     *   * `window.X ??= …` did not match. `\s*=` wants an `=` directly after the
     *     whitespace and finds `?`, so the idiomatic "assign unless something
     *     already did" form — exactly what a shared entry point uses — read as no
     *     assignment at all. `||=` and `&&=` have the same shape.
     *   * `globalThis.X` and `window['X']` did not match, though both are the
     *     same assignment written by someone following a different house style.
     *   * Only `.js` was walked, so a TypeScript entry point was invisible.
     *   * And in the other direction: `window.X == null` DID match, because the
     *     first `=` of `==` satisfied the pattern. A comparison was read as an
     *     assignment, which is the failure that produces a PASS over an app that
     *     never assigns anything — the more expensive of the two mistakes.
     *
     * Comments are stripped first, for the reason `checkChartJsRegistration()`
     * already gives: a line someone commented out while debugging is evidence of
     * the opposite of what it says.
     *
     * Still a heuristic, so still a WARN at the call site. An app may assign the
     * global from somewhere this scan cannot see, and a check that FAILED on that
     * would be confidently wrong about a working installation.
     */
    private function assignsBrowserGlobal(string $globalName): bool
    {
        $jsRoot = base_path('resources/js');

        if (! is_dir($jsRoot)) {
            return false;
        }

        $quoted = preg_quote($globalName, '/');

        // `(?!=)` after the `=` is what refuses `==` and `===`. The optional
        // `??` / `||` / `&&` in front is what accepts the logical-assignment
        // forms without also accepting a bare `?`.
        $pattern = '/(?:window|globalThis|self)\s*(?:\.\s*'.$quoted.'|\[\s*[\'"]'.$quoted.'[\'"]\s*\])\s*(?:\?\?|\|\||&&)?=(?!=)/';

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($jsRoot, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if (! $entry->isFile()) {
                continue;
            }

            $extension = strtolower($entry->getExtension());

            if (! in_array($extension, ['js', 'mjs', 'cjs', 'ts', 'mts', 'cts'], true)) {
                continue;
            }

            $source = (string) file_get_contents($entry->getPathname());
            $source = (string) preg_replace('#//[^\n]*#', '', $source);
            $source = (string) preg_replace('#/\*.*?\*/#s', '', $source);

            if (preg_match($pattern, $source) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Three-step ApexCharts adapter check:
     *   1. Confirm the apexcharts npm package is installed (FAIL on absence —
     *      otherwise the chart renders blank with a console.error).
     *   2. License-tier reminder — PASS on 'commercial' / 'oem'; WARN on
     *      'community' (confirming the value, and saying why it still speaks),
     *      on an unrecognized value (naming it back), and when unset. Never FAIL
     *      purely on tier choice (license compliance is the developer's
     *      responsibility, not a config error).
     *   3. Adapter-bundle presence — confirm dist/wirekit-apex.js was published
     *      to the public/vendor folder. WARN on absence with a republish hint.
     */
    private function checkApexChartsAdapter(): void
    {
        $this->reportPass('ApexCharts adapter configured');

        // Step 1: verify the apexcharts npm package is installed.
        $packageJsonPath = base_path('package.json');
        if (file_exists($packageJsonPath)) {
            $packageJson = json_decode((string) file_get_contents($packageJsonPath), true) ?: [];
            $deps = array_merge(
                $packageJson['dependencies'] ?? [],
                $packageJson['devDependencies'] ?? [],
            );
            if (! isset($deps['apexcharts'])) {
                $this->reportFail(
                    'apexcharts npm package not found in package.json. '
                    .'Install it with `npm install apexcharts`, then assign the global: '
                    .'`import ApexCharts from "apexcharts"; window.ApexCharts = ApexCharts;`. '
                    .'In your global entry point that is the simple case, and it puts roughly '
                    .'850 KB on every page including the ones without a chart — for an app '
                    .'where charts are the exception, import it in the chart route\'s own '
                    .'entry instead and assign the global there.'
                );
            } else {
                $this->reportPass('apexcharts npm package installed');
            }
        } else {
            $this->line('  <fg=cyan>i</> package.json not found — skipping apexcharts npm presence check');
        }

        // Step 1b: is the global actually ASSIGNED anywhere?
        //
        // The manifest check above proves the package can be resolved, and that is a
        // different question from whether it reaches the page. The adapter reads
        // `window.ApexCharts` and nothing puts it there on its own — an app that installs
        // the npm package and never writes the assignment ships a bundle that finds
        // nothing, and every chart stays blank with no error.
        //
        // A heuristic over the app's own JS, so it WARNS rather than fails: a project may
        // assign the global from a file this scan does not know about, and a check that
        // failed on that would be wrong about a working installation.
        $assignmentFound = $this->assignsBrowserGlobal('ApexCharts');

        if ($assignmentFound) {
            $this->reportPass('window.ApexCharts is assigned in your JavaScript');
        } else {
            $this->reportWarn(
                'no `window.ApexCharts = …` assignment found under resources/js. The adapter reads '
                .'that global and nothing sets it for you, so every chart renders blank with no error. '
                .'Add `import ApexCharts from "apexcharts"; window.ApexCharts = ApexCharts;` to your '
                .'entry point — or to the chart route\'s own entry, if you would rather not put '
                .'850 KB on pages that have no chart. Ignore this if you assign it somewhere this '
                .'scan cannot see.'
            );
        }

        // Step 2: license-tier reminder. WARN-only; never FAIL on this.
        //
        // Four outcomes, not two. The WARN condition is deliberate and stays:
        // the $2M threshold is a CONTINUING condition, not an install step, so
        // a project crosses it without any file changing and a reminder that
        // went quiet would go quiet at exactly the wrong moment.
        //
        // What changed is the advice. The old single WARN offered three values
        // and promised that recording one would silence the reminder — and two
        // of the three did not. Someone on the community tier followed the
        // instruction, saw the identical message with the identical advice, and
        // the reasonable conclusion is that the doctor is imprecise. That is the
        // erosion this command cannot afford: a warning nobody can act on
        // teaches people to skim, and then the real findings go unread too.
        //
        // So a declared community tier gets its own line that confirms the value
        // arrived AND says why it still speaks, and the unset case keeps the
        // full explanation minus the promise it could not keep.
        $tier = config('wirekit.charts.apex_license');

        if ($tier === 'commercial' || $tier === 'oem') {
            $this->reportPass(sprintf('ApexCharts license tier declared: %s', $tier));
        } elseif ($tier === 'community') {
            $this->reportWarn(
                'Declared tier: community. This reminder stays — the $2M USD revenue '
                .'threshold for the ApexCharts Community License is a continuing '
                .'condition, not an install step. Purchase a Commercial License at '
                .'https://apexcharts.com/license/ once you pass it.'
            );
        } elseif (is_string($tier) && $tier !== '') {
            // A typo is the same defect one level up: it falls into no branch,
            // and telling someone who DID record a tier to go record one is the
            // instruction that cannot be followed all over again. Name the value
            // back so the difference is visible without opening the source.
            $this->reportWarn(sprintf(
                'Unrecognized ApexCharts license tier `%s` in `charts.apex_license`. '
                .'Accepted values: community / commercial / oem. Until it matches one '
                .'of those it is treated as undeclared.',
                $tier
            ));
        } else {
            $this->reportWarn(
                'ApexCharts is non-MIT. Confirm your organization is below the '
                .'$2M USD revenue threshold for the Community License, or purchase a '
                .'Commercial License at https://apexcharts.com/license/. '
                .'Record your tier via `charts.apex_license` in config/wirekit.php '
                .'(values: community / commercial / oem).'
            );
        }

        // Step 3: adapter-bundle presence — wirekit-apex.js needs to be
        // accessible at the public asset path.
        $publishedAdapterBundle = public_path('vendor/wirekit/wirekit-apex.js');
        if (file_exists($publishedAdapterBundle)) {
            $this->reportPass('dist/wirekit-apex.js published to public/vendor/wirekit/');
        } else {
            $this->reportWarn(
                'dist/wirekit-apex.js not found at '.$publishedAdapterBundle.'. '
                .'Run `php artisan vendor:publish --tag=wirekit-assets --force` to publish '
                .'the ApexCharts adapter bundle alongside the main bundle.'
            );
        }
    }

    /**
     * Catches the first-run-chart-crash UX: a developer drops
     * `<x-wirekit-chart>` (or `<x-wirekit::chart-mixed>` / a sparkline)
     * into a fresh app but `config('wirekit.charts.library')` is still
     * the package default of `null`. Without this check, the symptom is
     * either a 500 (production) or a placeholder div (debug) — both
     * land on the developer with no upstream signal that the doctor
     * could have caught the misconfiguration. Emits a single WARN
     * naming the first file the chart-tag is referenced in.
     */
    private function checkChartUsageWithoutAdapter(): void
    {
        // Adapter already configured — nothing to surface.
        if (config('wirekit.charts.library') !== null) {
            return;
        }

        $offenders = [];
        foreach ($this->findAllBladeFiles() as $file) {
            $content = (string) file_get_contents($file);
            // Strip Blade comments first — same comment-leakage class
            // the directive-order check guards against. A docs page
            // describing the chart tag inside `{{-- ... --}}` should
            // not count as a real usage.
            $stripped = preg_replace('/\{\{--.*?--\}\}/s', '', $content) ?? $content;
            if (
                str_contains($stripped, '<x-wirekit-chart')
                || str_contains($stripped, '<x-wirekit::chart-mixed')
                || str_contains($stripped, '<x-wirekit::chart-spark')
            ) {
                $offenders[] = $file;
            }
        }

        if ($offenders === []) {
            return;
        }

        $first = $offenders[0];
        $count = count($offenders);
        $extraSuffix = $count > 1 ? sprintf(' (+%d more)', $count - 1) : '';

        $this->reportWarn('<x-wirekit-chart> used but charts.library is null'.$extraSuffix);
        $this->line("  First reference: {$first}");
        $this->line('  Fix: set `\'charts\' => [\'library\' => \'chartjs\']` in config/wirekit.php, then `npm install chart.js`.');
        $this->line('  In APP_DEBUG=true the chart renders a placeholder div instead of crashing the page.');
    }

    /**
     * The "config says chartjs but the JS bundle never registered it"
     * gap: developer flipped `charts.library` to `chartjs` AND ran
     * `npm install chart.js` BUT didn't add the
     * `Chart.register(...registerables)` line to `resources/js/app.js`.
     * The chart component renders + mounts; the Alpine adapter runs;
     * Chart.js then prints a friendly "Chart.js is not loaded" error
     * to the browser console (chart.js:107) and silently fails to draw.
     *
     * Doctor catches this UPSTREAM by scanning `resources/js/app.js`
     * for the canonical registration pattern. WARN with the actionable
     * snippet when:
     *   - `config('wirekit.charts.library') === 'chartjs'` (developer
     *     enabled the chartjs adapter)
     *   - `resources/js/app.js` exists
     *   - The file does NOT contain BOTH an `import ... chart.js` AND
     *     a `Chart.register(` call.
     *
     * Silently skip in every other scenario:
     *   - chartjs not selected → no JS bootstrap needed
     *   - resources/js/app.js missing → bare-install path (handled by
     *     other checks)
     *   - registration already present → developer has done the right
     *     thing; nothing to surface.
     */
    private function checkChartJsRegistration(): void
    {
        if (config('wirekit.charts.library') !== 'chartjs') {
            return;
        }

        $appJsPath = resource_path('js/app.js');
        if (! file_exists($appJsPath)) {
            return;
        }

        $contents = (string) file_get_contents($appJsPath);

        // Strip JS // comments before scanning so a "// Missing:
        // Chart.register(...)" hint comment doesn't false-pass the
        // detection. Block comments are left as-is (rare in the
        // register-snippet area; treating them naively would risk
        // stripping legitimate code inside a `/* */` block).
        $stripped = preg_replace('~//[^\n]*~', '', $contents) ?? $contents;

        // Match the canonical patterns the chart.js documentation
        // recommends. Be lenient on whitespace and quote style.
        $hasImport = (bool) preg_match('/import\s+.*\s+from\s+[\'"]chart\.js[\'"]/', $stripped);
        $hasRegister = str_contains($stripped, 'Chart.register(');

        if ($hasImport && $hasRegister) {
            return;
        }

        // The self-hosted UMD build is a second, equally valid way to provide
        // Chart.js, and it needs no registration at all: it sets `window.Chart`
        // and registers every controller itself. `Chart.register(...registerables)`
        // is required only for the tree-shaken ESM import.
        //
        // Without this branch the doctor told applications whose charts demonstrably
        // draw that every chart "renders but draws nothing" — a warning that is not
        // just noise but actively misleading, and it was reported from an app doing
        // exactly the supported thing.
        if ($this->providesChartUmdBuild()) {
            return;
        }

        $this->reportWarn('Chart.js adapter selected but resources/js/app.js is missing the registration snippet');
        $this->line('  Fix: add the following to resources/js/app.js, then `npm run build`:');
        $this->line('');
        $this->line('    import { Chart, registerables } from \'chart.js\';');
        $this->line('    Chart.register(...registerables);');
        $this->line('');
        $this->line('  Without this, every <x-wirekit-chart> renders but draws nothing — chart.js logs a friendly');
        $this->line('  console.error at runtime and gives up. See '.WireKit::DOCS_URL.'/getting-started/integration#optional-dependencies');
        $this->line('  for the full setup walkthrough.');
    }

    /**
     * Does any `.disconnect()` in this source lack a guard of its own?
     *
     * Judged per call site. Guarded means one of:
     *   - `this._observer?.disconnect()` — the optional-chaining form, on the line
     *   - a guard on `this._…` in the lines just above: either `if (! this._x)`
     *     (early return) or `if (this._x) {` (positive, wrapping the call)
     *
     * The look-back is small on purpose. A guard six lines up with a branch in
     * between does not protect this call, and accepting it would reintroduce the
     * file-wide blindness this replaced.
     */
    private function hasUnguardedDisconnect(string $source): bool
    {
        $lines = preg_split('/\R/', $source) ?: [];

        foreach ($lines as $i => $line) {
            if (preg_match('/\.disconnect\s*\(\s*\)/', $line) !== 1) {
                continue;
            }

            // Optional chaining guards itself.
            if (preg_match('/\?\.\s*disconnect\s*\(/', $line) === 1) {
                continue;
            }

            $context = implode("\n", array_slice($lines, max(0, $i - 3), min(3, $i)));

            $guarded = preg_match('/if\s*\(\s*!\s*this\._\w+/', $context) === 1
                || preg_match('/if\s*\(\s*this\._\w+\s*\)/', $context) === 1;

            if (! $guarded) {
                return true;
            }
        }

        return false;
    }

    /**
     * Is Chart.js provided as the self-hosted UMD build?
     *
     * That build sets `window.Chart` and registers its own controllers, so the
     * ESM registration snippet is not merely optional there — it does not apply.
     * Looked for in the two places it can legitimately live: shipped under
     * `public/`, or referenced from a Blade layout (a CDN tag, or an asset()
     * call pointing at a vendored copy).
     *
     * Deliberately a shallow filename check rather than a parse. The question is
     * only "is there another route by which Chart reaches the page", and a false
     * NEGATIVE here just restores the old warning — while a false positive costs
     * a developer a hunt for a defect that does not exist.
     */
    private function providesChartUmdBuild(): bool
    {
        foreach (glob(public_path('vendor/**/chart*.js')) ?: [] as $path) {
            if (str_contains(basename($path), 'chart')) {
                return true;
            }
        }

        $layouts = glob(resource_path('views/**/*.blade.php')) ?: [];

        foreach (array_merge($layouts, glob(resource_path('views/*.blade.php')) ?: []) as $path) {
            $blade = (string) file_get_contents($path);

            if (preg_match('/chart(?:\.umd)?(?:\.min)?\.js/i', $blade) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Final post-build sanity: if a Vite manifest exists, the BUILT app CSS
     * should reference at least one WireKit token. Catches the silent-failure
     * mode where a developer adds the @source line to app.css but forgets to
     * run `npm run build` — the source-side check would still pass while the
     * page renders without WireKit utilities.
     *
     * Skipped silently in environments without `public/build/manifest.json`
     * (dev / pre-build / package-test scenarios).
     */
    private function checkBuiltCssHasWireKitUtilities(): void
    {
        $manifestPath = public_path('build/manifest.json');
        if (! file_exists($manifestPath)) {
            // Dev mode / pre-build — silently skip. Other checks already
            // surface the source-side state.
            return;
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            $this->reportWarn('Vite manifest at public/build/manifest.json is not valid JSON — skipping built-CSS check');

            return;
        }

        // Vite manifest shape varies across major versions. Walk the entire
        // structure and collect every value whose `file` ends in `.css`.
        $cssEntries = [];
        $walk = function ($node) use (&$walk, &$cssEntries) {
            if (! is_array($node)) {
                return;
            }
            if (isset($node['file']) && is_string($node['file']) && str_ends_with($node['file'], '.css')) {
                $cssEntries[] = $node['file'];
            }
            // Some manifests nest CSS files in a `css` array on a JS entry.
            if (isset($node['css']) && is_array($node['css'])) {
                foreach ($node['css'] as $css) {
                    if (is_string($css) && str_ends_with($css, '.css')) {
                        $cssEntries[] = $css;
                    }
                }
            }
            foreach ($node as $child) {
                if (is_array($child)) {
                    $walk($child);
                }
            }
        };
        $walk($manifest);

        if (empty($cssEntries)) {
            // No CSS in the build output at all — likely a JS-only developer.
            // Not necessarily a problem; skip silently.
            return;
        }

        foreach (array_unique($cssEntries) as $cssEntry) {
            $cssPath = public_path('build/'.$cssEntry);
            if (! file_exists($cssPath)) {
                continue;
            }
            $css = (string) file_get_contents($cssPath);
            // Look for any --color-wk-* token reference. Aggressive minifiers
            // could rename CSS custom properties in theory, but Tailwind v4
            // preserves them; if even one is missing across every CSS bundle
            // we flag the rebuild.
            if (str_contains($css, '--color-wk-')) {
                $this->reportPass('Built app CSS contains WireKit utility rules');

                return;
            }
        }

        $this->reportFail('Built app CSS does not reference WireKit utilities');
        $this->line('    Hint: run `npm run build` after adding the @source line for WireKit templates to app.css.');
    }

    /**
     * Find ALL Blade files in resources/views/ recursively.
     * Scans beyond layout directories to catch directives in any template.
     *
     * @return string[]
     */
    private function findAllBladeFiles(): array
    {
        if ($this->allBladeFiles !== null) {
            return $this->allBladeFiles;
        }

        $viewsPath = resource_path('views');

        if (! is_dir($viewsPath)) {
            $this->allBladeFiles = [];

            return [];
        }

        $this->allBladeFiles = collect(File::allFiles($viewsPath))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file) => $file->getPathname())
            ->values()
            ->all();

        return $this->allBladeFiles;
    }

    /**
     * Detect Livewire major version from composer.lock.
     * Livewire v4+ bundles Alpine.js, so a separate Alpine check is unnecessary.
     *
     * Composer's `version` field uses several string shapes:
     *   - "4.1.0"      → 4   (plain SemVer)
     *   - "v4.1.0"     → 4   (v-prefixed — common from git tags)
     *   - "dev-main"   → 0   (branch alias — caller treats as "unknown major")
     *   - "4.x-dev"    → 4   (branch alias of a major line)
     *   - "4.1.0-RC1"  → 4   (pre-release)
     *
     * The previous implementation `(int) $version[0]` returned 0 for every
     * v-prefixed string because `(int)"v" === 0` — flagging Alpine as
     * missing on every install where Composer kept the v prefix in the lock
     * file. The regex below scans for the first integer run anywhere in the
     * version string, so all five shapes above resolve correctly.
     */
    public function detectLivewireVersion(?string $lockPath = null): int
    {
        $lockPath ??= base_path('composer.lock');

        // `file_exists` is true for a DIRECTORY and for a file the process cannot read, and in
        // both cases `file_get_contents` returns false. Under strict_types that is a TypeError
        // inside `wirekit:verify` — a fatal on the machine of the person running the doctor to
        // find out what is wrong. The five other json_decode sites in this file already cast;
        // this one did not.
        if (! is_file($lockPath) || ! is_readable($lockPath)) {
            return 0;
        }

        $lock = json_decode((string) file_get_contents($lockPath), true);
        if (! is_array($lock)) {
            return 0;
        }

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if (($package['name'] ?? null) === 'livewire/livewire') {
                $version = (string) ($package['version'] ?? '');
                // Match the FIRST run of digits anywhere in the version string.
                // Handles "v4.1.0", "4.1.0", "4.x-dev", "4.1.0-RC1".
                if (preg_match('/(\d+)/', $version, $m)) {
                    return (int) $m[1];
                }

                return 0;
            }
        }

        return 0;
    }

    /**
     * Print a green ✓ check result and increment passed counter.
     * Named reportPass/reportFail/reportWarn to avoid collisions with
     * Illuminate\Console\Command's own fail() and warn() methods.
     */
    private function reportPass(string $message): void
    {
        $this->line("  <fg=green>✓</> {$message}");
        $this->passed++;
    }

    private function reportFail(string $message): void
    {
        $this->line("  <fg=red>✗</> {$message}");
        $this->failed++;
    }

    private function reportWarn(string $message): void
    {
        $this->line("  <fg=yellow>!</> {$message}");
        $this->warned++;
    }

    /**
     * INFO tier — informational, expected on bare installs, NOT a problem.
     * Distinct from PASS (everything's fine) and WARN (something the
     * developer should look at). INFO is "this is the natural state of a
     * fresh install; here's the next step if you want to act on it."
     * Counts as PASS in the summary tally so the summary line doesn't
     * read as if something failed.
     */
    private function reportInfo(string $message): void
    {
        $this->line("  <fg=blue>i</> {$message}");
        $this->passed++;
    }

    /**
     * Token-alignment diagnostic — compares Tailwind tokens against WireKit
     * tokens in `resources/css/app.css`. Surfaces the footgun where WireKit
     * chrome renders in a different font than the body copy at install-time,
     * rather than letting the mismatch ship to production.
     *
     * Checks:
     *   --font-sans   ↔ --font-wk-sans
     *   --font-serif  ↔ --font-wk-serif
     *   --font-mono   ↔ --font-wk-mono
     *   --color-accent ↔ --color-wk-accent
     *   --color-accent-foreground ↔ --color-wk-accent-fg
     *   --radius      ↔ --radius-wk
     *   --shadow      ↔ --shadow-wk
     *
     * Skips any pair where either side is a `var(...)` reference (the developer
     * is intentionally aliasing) or unset. Emits ✓ when families match, ⚠ when
     * they differ with actionable hint.
     */
    private function checkTokenAlignment(): void
    {
        $this->line('');
        $this->line('  Token alignment:');

        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->reportWarn('  resources/css/app.css not found — skipping token-alignment checks');

            return;
        }

        // Strip CSS comments first — the per-pair scanner greps the FIRST
        // `--token: value` it finds, so an example pasted in a comment
        // above the real declaration would otherwise mask it.
        $content = $this->stripCssComments((string) file_get_contents($appCss));

        $checks = [
            ['Sans font', '--font-sans', '--font-wk-sans', 'php artisan wirekit:install --font=<key>'],
            ['Serif font', '--font-serif', '--font-wk-serif', 'php artisan wirekit:install --font-serif=<key>'],
            ['Mono font', '--font-mono', '--font-wk-mono', 'php artisan wirekit:install --font-mono=<key>'],
            ['Accent color', '--color-accent', '--color-wk-accent', 'set --color-accent in @theme to match WireKit accent'],
            ['Accent foreground', '--color-accent-foreground', '--color-wk-accent-fg', 'set --color-accent-foreground in @theme'],
            ['Border radius', '--radius', '--radius-wk', 'set --radius in @theme to match --radius-wk'],
            ['Shadow', '--shadow', '--shadow-wk', 'set --shadow in @theme to match --shadow-wk'],
        ];

        foreach ($checks as [$label, $tw, $wk, $hint]) {
            $this->compareTokenPair($content, $label, $tw, $wk, $hint);
        }
    }

    /**
     * Compares one Tailwind token vs. WireKit token pair and reports outcome.
     */
    private function compareTokenPair(string $cssContent, string $label, string $twToken, string $wkToken, string $hint): void
    {
        $twValue = $this->extractTokenValue($cssContent, $twToken);
        $wkValue = $this->extractTokenValue($cssContent, $wkToken);

        // Skip if either token is unset — and say WHICH one. The condition has
        // always tested both sides, but the message named the Tailwind side
        // unconditionally, so a missing WireKit-side token sent the reader to
        // the one file where nothing was wrong. Printing the token name also
        // removes the need to know which side is called "Tailwind" and which
        // "WireKit" in order to read the line at all.
        if ($twValue === null || $wkValue === null) {
            $reason = match (true) {
                $twValue === null && $wkValue === null => "neither {$twToken} nor {$wkToken} set",
                $twValue === null => "{$twToken} unset",
                default => "{$wkToken} unset",
            };

            $this->line("    <fg=blue>i</> {$label}: skipped ({$reason})");

            return;
        }

        // Skip if either side is a var(...) reference (intentional aliasing)
        if (str_contains($twValue, 'var(') || str_contains($wkValue, 'var(')) {
            $this->line("    <fg=blue>i</> {$label}: skipped (var(...) reference — intentional alias)");

            return;
        }

        $twNormalized = $this->normalizeTokenValue($twValue);
        $wkNormalized = $this->normalizeTokenValue($wkValue);

        if ($twNormalized === $wkNormalized) {
            $this->line("    <fg=green>✓</> {$label}: aligned ({$twNormalized})");
            $this->passed++;
        } else {
            $this->reportWarn("  {$label}: mismatch — Tailwind `{$twValue}` vs WireKit `{$wkValue}`. Fix: {$hint}");
        }
    }

    /**
     * Strip CSS block comments before any raw-text scan of app.css.
     *
     * Several checks grep app.css as plain text (the @import-path
     * detection, the token-alignment scanner, the :root/.dark block
     * extractor). A CSS comment that happens to contain the scanned phrase
     * — e.g. a commented "not an @import of wirekit.css" note, or an
     * example "--font-wk-sans: …" pasted above the real declaration —
     * would otherwise be read as if it were live CSS, producing a false
     * PASS or masking the real value. This mirrors the Blade-comment strip
     * in checkBladeDirectives() and the JS comment strip in
     * checkChartJsRegistration(): sanitize once, scan the live CSS only.
     * (parseColorTokens() already strips its block the same way; this
     * centralizes the pattern for every app.css reader.)
     */
    private function stripCssComments(string $css): string
    {
        // Non-greedy across newlines (/s) so each comment span is removed
        // individually; a CSS value never contains a comment delimiter, so
        // this can't corrupt a real declaration.
        return preg_replace('~/\*.*?\*/~s', '', $css) ?? $css;
    }

    /**
     * Extracts the value of a CSS custom property from the content.
     *
     * Returns null if the token is not found (developer hasn't set it).
     */
    private function extractTokenValue(string $cssContent, string $token): ?string
    {
        $pattern = '/'.preg_quote($token, '/').'\s*:\s*([^;\n]+)/';

        if (preg_match($pattern, $cssContent, $matches) === 1) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Normalizes a token value for cross-comparison.
     *
     * For font families: extracts the first comma-separated token, lowercases,
     * trims quotes. So `'Inter', ui-sans-serif` and `"Inter", ui-sans` both
     * normalize to `inter`.
     *
     * For other values: lowercases + trims whitespace.
     */
    private function normalizeTokenValue(string $value): string
    {
        $first = trim(explode(',', $value)[0]);
        $first = trim($first, "'\"");

        return mb_strtolower($first);
    }

    /**
     * Detect `:root` ↔ `.dark` color-token override asymmetry.
     *
     * If a developer overrides `--color-wk-accent` in `:root` but DOES NOT
     * provide a matching declaration in `.dark`, dark mode silently falls
     * back to WireKit's default. The existing checkTokenAlignment()
     * compares Tailwind↔WireKit pairs, not the developer's own root vs
     * dark blocks — so this complementary check fills that gap.
     *
     * Restricts to `--color-wk-*` family. Font / radius / shadow / motion
     * tokens are typically theme-agnostic (same value in both modes), so
     * asymmetry there is not a bug. Reads `resources/css/app.css` only —
     * the source of truth for developer overrides; the built bundle aggregates
     * Tailwind output with the developer's source so reading the source is
     * cleaner.
     */
    private function checkRootDarkSymmetry(): void
    {
        // Every path out of this check now SAYS something, and the four that used
        // to return in silence are the reason. The doctor's contract is that every
        // check emits at least one observable line — the note on the Alpine-hygiene
        // check states it, and a silent return there once made a test flaky, which
        // is the cheap version of the same problem.
        //
        // The expensive version is what a developer sees: a check that vanishes and
        // one that passes look identical in the output, so an app whose stylesheet
        // this check cannot read is told nothing at all — not that it is fine, not
        // that it was skipped, not why. That shape is common rather than exotic: an
        // application using the `@theme` block from the theming guide has no `:root`
        // rule of its own and lands here every time.
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            $this->reportInfo('Token symmetry: skipped — no resources/css/app.css to read');

            return;
        }
        $content = file_get_contents($appCss);
        if ($content === false) {
            $this->reportInfo('Token symmetry: skipped — resources/css/app.css could not be read');

            return;
        }
        // Strip CSS comments first so a `:root {` / `.dark {` written
        // inside a comment can't mis-anchor extractCssBlock().
        $content = $this->stripCssComments($content);

        $rootBlock = $this->extractCssBlock($content, ':root');
        $darkBlock = $this->extractCssBlock($content, '.dark');

        if ($rootBlock === '' || $darkBlock === '') {
            // No :root or no .dark — no asymmetry to report. The developer
            // either has neither (clean default) or has only :root with
            // no dark intention (also fine — they're light-only).
            //
            // Named rather than merged into one line, because the two say different
            // things: no `:root` means this check has nothing to compare, and no
            // `.dark` means there is no dark theme for it to be asymmetric with.
            $this->reportInfo($rootBlock === ''
                ? 'Token symmetry: skipped — no `:root { … }` rule in resources/css/app.css'
                : 'Token symmetry: skipped — no `.dark { … }` rule in resources/css/app.css (light-only theme)');

            return;
        }

        $rootTokens = $this->parseColorTokens($rootBlock);
        $darkTokens = $this->parseColorTokens($darkBlock);

        if ($rootTokens === []) {
            $this->reportInfo('Token symmetry: skipped — the `:root` rule overrides no `--color-wk-*` token');

            return;
        }

        $asymmetric = array_diff_key($rootTokens, $darkTokens);

        if ($asymmetric === []) {
            $this->reportPass('Token symmetry: every overridden `--color-wk-*` token has a matching `.dark` declaration');

            return;
        }

        $this->reportWarn('Token symmetry: '.count($asymmetric).' color token(s) overridden in `:root` but not in `.dark`');
        foreach (array_keys($asymmetric) as $token) {
            $this->line("    <fg=gray>•</> {$token}");
        }
        $this->line('    <fg=gray>Dark mode falls back to WireKit defaults for these tokens.</>');
        $this->line('    <fg=gray>Add matching declarations to your `.dark { … }` block.</>');
    }

    /**
     * Static analysis for Alpine-plugin defensive-cleanup hygiene.
     *
     * Scans the developer's `resources/js/` tree (the canonical location for
     * custom Alpine plugins extending WireKit) and flags two anti-patterns
     * that historically pollute developer browser-test console-error
     * assertions:
     *
     *  - **Observer instantiation WITHOUT a `destroy()` cleanup hook.** A
     *    `new IntersectionObserver(...)` / `new MutationObserver(...)` /
     *    `new ResizeObserver(...)` stored on `this` survives the Alpine
     *    instance's GC eligibility because the observer holds a reference
     *    to the host element. Memory leak + future-callback timing
     *    surface. Without `destroy()` the observer is never disconnected.
     *
     *  - **`disconnect()` call inside an observer callback WITHOUT a
     *    null-guard on the observer reference.** Browser-queued callbacks
     *    can execute AFTER Alpine teardown set `this._observer = null`
     *    (Livewire morph removing the host element pre-intersection is
     *    the canonical trigger). Without the guard, the callback throws
     *    `TypeError: Cannot read properties of null` — the bug class
     *    that WireKit's own `wirekitStatAnimate` / `wirekitAnimate`
     *    plugins shipped in earlier versions and patched in v2.0.0.
     *
     * Heuristic — not a perfect AST analysis, but covers the canonical
     * shape WireKit's own plugins follow. Edge cases (callback bound via
     * `.bind(this)`, observer reference held under a different name like
     * `this._intersectionObserver`) emit a soft WARN with a
     * docs-cross-link instead of a hard FAIL so developers can opt out
     * with a `// wirekit-doctor: cleanup-ok` comment when their pattern
     * is intentionally different.
     */
    private function checkAlpinePluginCleanupHygiene(): void
    {
        $developerJsDir = resource_path('js');
        if (! is_dir($developerJsDir)) {
            // Developers without a `resources/js/` tree have nothing for
            // this check to walk — but the doctor's contract is "every
            // check emits at least one observable line", so emit an INFO
            // here instead of returning silently. Tests asserting the
            // check ran (e.g. DoctorAliasTest "still runs and produces
            // structurally identical output") then see the marker
            // regardless of worker-sandbox state.
            $this->reportInfo('Alpine plugin cleanup hygiene: skipped — no resources/js/ directory (no developer-side Alpine plugins to scan)');

            return;
        }

        // Defensive iterator wrapping — if the directory exists but the
        // recursive walk throws (race-deleted sub-tree, permission flip
        // during parallel test runs, exotic filesystem error), surface
        // the issue as an INFO and degrade. Without this, an unhandled
        // throw aborts the whole doctor handle() pipeline mid-flight.
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($developerJsDir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
        } catch (\Throwable $e) {
            $this->reportInfo('Alpine plugin cleanup hygiene: scan skipped — '.$e::class.': '.mb_substr($e->getMessage(), 0, 100));

            return;
        }

        $issues = [];

        // Defensive iteration — wrap the foreach so a mid-walk throw
        // (filesystem race, vanished sub-directory) degrades to an INFO
        // emission instead of aborting the doctor's check pipeline.
        try {
            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'js') {
                    continue;
                }
                $path = $file->getPathname();
                $source = @file_get_contents($path);
                if ($source === false || $source === '') {
                    continue;
                }

                // Opt-out comment lets developers acknowledge intentional patterns.
                if (str_contains($source, '// wirekit-doctor: cleanup-ok')) {
                    continue;
                }

                $relativePath = str_replace($developerJsDir.'/', '', $path);

                // Anti-pattern 1: observer instantiation without destroy()
                $hasObserver = preg_match(
                    '/new\s+(?:IntersectionObserver|MutationObserver|ResizeObserver)\s*\(/',
                    $source
                ) === 1;
                $hasDestroy = (
                    preg_match('/\bdestroy\s*\(\s*\)\s*\{/', $source) === 1
                    || preg_match('/\bdestroy\s*:\s*(?:function\s*)?\(/', $source) === 1
                );

                // An observer built at MODULE level is not the same finding as one
                // built per component instance, and only the second can accumulate.
                // A page-lifetime observer — one MutationObserver on <html> or
                // <body>, inside a top-level IIFE, driving something for as long as
                // the document lives — has nothing to tear down; giving it a
                // destroy() would be the defect. Reported as a leak, it sends people
                // looking for a bug that is not there.
                //
                // The discriminator is where the construction sits: inside an Alpine
                // factory (`Alpine.data(...)`, an `init()`, a returned object) it is
                // per-instance; at top level it is not.
                $perInstance = (
                    preg_match('/Alpine\s*\.\s*data\s*\(/', $source) === 1
                    || preg_match('/\binit\s*\(\s*\)\s*\{/', $source) === 1
                    || preg_match('/\binit\s*:\s*(?:function\s*)?\(/', $source) === 1
                );

                if ($hasObserver && ! $hasDestroy && $perInstance) {
                    $issues[$relativePath][] = 'observer-without-destroy';
                }

                // Anti-pattern 2: a `disconnect()` that nothing guards.
                //
                // Checked PER CALL SITE rather than per file, and that distinction
                // is the whole correctness of this detector. A file-wide question —
                // "is there a guard anywhere in here" — gets both answers wrong:
                //
                //   • It rejected the POSITIVE guard form, `if (this._observer) { … }`,
                //     because the pattern only knew the negative and optional-chaining
                //     spellings. That is the same check written the other way round,
                //     and it usually does MORE (it nulls the reference afterwards).
                //     Reported from a consuming app as a false positive, twice over.
                //
                //   • Teaching it the positive form file-wide would have been worse:
                //     a component that guards correctly in destroy() and calls
                //     disconnect() unguarded inside its observer callback would then
                //     read as clean. That is the real anti-pattern, and it lives in
                //     the same files as the correct guard.
                //
                // So each call site is judged by its own immediate context: the
                // optional-chaining form on the line itself, or a guard on `this._…`
                // within the few lines above it. A heuristic, deliberately — but one
                // whose failure mode is a warning rather than silence.
                if ($hasObserver && $this->hasUnguardedDisconnect($source)) {
                    $issues[$relativePath][] = 'disconnect-without-null-guard';
                }
            }
        } catch (\Throwable $e) {
            $this->reportInfo('Alpine plugin cleanup hygiene: scan partial — '.$e::class.': '.mb_substr($e->getMessage(), 0, 100));

            return;
        }

        if ($issues === []) {
            $this->reportPass('Alpine plugin cleanup hygiene: no developer-side observer-leak or null-guard anti-patterns detected');

            return;
        }

        $this->reportWarn('Alpine plugin cleanup hygiene: '.count($issues).' file(s) with potential anti-patterns');
        foreach ($issues as $relativePath => $detected) {
            $this->line("    <fg=gray>•</> {$relativePath}: ".implode(', ', $detected));
        }
        $this->line('    <fg=gray>See: https://docs.wirekit.app/extending/authoring-custom-alpine-plugins</>');
        $this->line('    <fg=gray>Opt out per-file with a `// wirekit-doctor: cleanup-ok` comment if intentional.</>');
    }

    /**
     * Extract the bodies of every CSS rule whose selector list names
     * `$selector` — `:root { … }`, `.dark { … }`, or a shared head like
     * `:root, .dark { … }`, which counts for BOTH sides. Returns the inner
     * text without the wrapping braces, or an empty string when no rule
     * names the selector.
     *
     * Anchored at the rule head rather than found with strpos(), because a
     * substring search matches those characters wherever they occur. `.dark`
     * occurs inside `html.dark` and inside `.dark-mode` — and, the case that
     * made this check worse than merely noisy, inside
     * `@custom-variant dark (&:where(.dark, .dark *));`, the line the
     * integration guide tells every developer to write. That at-rule carries
     * no braces of its own, so the old search anchored inside it and then
     * walked forward to the NEXT rule's opening brace: on the documented
     * setup — the `@custom-variant` line, then `:root`, then `.dark` — it
     * handed back the `:root` body as the dark block, the two token sets
     * came out identical by construction, and the asymmetry check could
     * never fire for anyone who had followed the guide. The same search
     * invented asymmetry for anyone whose theme class sits on `<html>`.
     * Both directions, one cause: the selector was never anchored.
     *
     * Every matching block is concatenated rather than only the first, so a
     * `:root` split across two blocks is measured whole. The joining `;`
     * stops the last declaration of one body from fusing with the first of
     * the next, since parseColorTokens() splits on `;`.
     *
     * Non-matching blocks are descended into rather than stepped over,
     * which is what keeps `@layer theme { .dark { … } }` — the shape every
     * theme preset in the theming guide prints — reachable. A MATCHED block
     * is consumed whole and not re-entered: a rule nested inside `:root` is
     * a descendant rule (`:root .dark` styles elements inside the root, not
     * the root), so reading its declarations as root-level overrides would
     * answer a different question than the one the check asks.
     */
    private function extractCssBlock(string $css, string $selector): string
    {
        $out = '';
        $len = strlen($css);
        $headStart = 0;
        $i = 0;

        while ($i < $len) {
            $char = $css[$i];

            // A `;` or `}` terminates whatever preceded it, so the next rule
            // head begins on the far side. Without this the head would
            // accumulate every declaration and brace-less at-rule since the
            // last block boundary — which is precisely how the
            // `@custom-variant` line used to end up attached to `:root`.
            if ($char === ';' || $char === '}') {
                $headStart = $i + 1;
                $i++;

                continue;
            }

            if ($char !== '{') {
                $i++;

                continue;
            }

            $head = trim(substr($css, $headStart, $i - $headStart));

            // Walk to the brace that closes this block, counting nesting.
            $depth = 1;
            $bodyStart = $i + 1;
            $j = $bodyStart;
            while ($j < $len && $depth > 0) {
                if ($css[$j] === '{') {
                    $depth++;
                } elseif ($css[$j] === '}') {
                    $depth--;
                }
                $j++;
            }
            // A truncated stylesheet has no closing brace to land on; take
            // the remainder rather than dropping its last character.
            $bodyEnd = $depth === 0 ? $j - 1 : $len;

            if ($this->selectorListMatches($head, $selector)) {
                $out .= substr($css, $bodyStart, $bodyEnd - $bodyStart).';';
                $i = $j;
                $headStart = $i;

                continue;
            }

            // Not ours — step INSIDE the block so a rule nested in a
            // container at-rule stays reachable.
            $i = $bodyStart;
            $headStart = $i;
        }

        return $out;
    }

    /**
     * True when a rule head names `$selector` as a whole entry of its
     * selector list. At-rule heads (`@media`, `@layer`, `@supports`,
     * `@custom-variant`) never match — they are containers, and
     * extractCssBlock() reaches what is inside them by descending instead.
     *
     * A root-qualified compound counts as the same element: `html.dark`,
     * `body.dark` and `:root.dark` are all the dark root written the long
     * way, and a custom property declared on any of them inherits to the
     * whole document exactly as one declared on `.dark` does. The
     * DESCENDANT form (`.dark .prose`) deliberately does not count — those
     * declarations apply to `.prose`, not to the root, so reading them as
     * the dark theme's token set would answer a different question than the
     * one the check asks.
     *
     * A comma inside a functional selector (`:root:not(.a, .b)`) splits an
     * entry that should have stayed whole. That yields a non-match, which is
     * the safe direction here: the check goes quiet rather than measuring
     * the wrong block and naming tokens that are not missing.
     */
    private function selectorListMatches(string $head, string $selector): bool
    {
        if ($head === '' || str_starts_with($head, '@')) {
            return false;
        }

        foreach (explode(',', $head) as $entry) {
            // Collapse the whitespace a multi-line selector list carries, so
            // an entry written on its own indented line still compares as
            // the bare selector.
            $entry = trim((string) preg_replace('/\s+/', ' ', $entry));

            if ($entry === $selector) {
                return true;
            }

            if ($selector === '.dark' && preg_match('/^(?:html|body|:root)\.dark$/', $entry) === 1) {
                return true;
            }

            if ($selector === ':root' && $entry === 'html:root') {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse `--color-wk-*: value;` declarations from a CSS block body.
     * Returns ['--color-wk-name' => 'value', ...]. Comments stripped first.
     * Restricted to the color-wk family — font/radius/shadow/motion tokens
     * are theme-agnostic and don't need .dark counterparts.
     */
    private function parseColorTokens(string $block): array
    {
        $block = preg_replace('~/\*.*?\*/~s', '', $block) ?? '';
        $tokens = [];
        foreach (explode(';', $block) as $decl) {
            $decl = trim($decl);
            if ($decl === '' || ! str_starts_with($decl, '--color-wk-')) {
                continue;
            }
            [$name, $value] = array_pad(array_map('trim', explode(':', $decl, 2)), 2, '');
            if ($name !== '') {
                $tokens[$name] = $value;
            }
        }

        return $tokens;
    }

    /**
     * Detects compiled-view staleness — the canonical reason a developer
     * test sees "the new prop isn't there" even after their Blade source
     * carries it. Laravel's `storage/framework/views/` retains pre-edit
     * compiled templates whose filemtime granularity (1-second) AND
     * filesystem-cache lag can let stale output survive a fast file-edit
     * cycle. The first diagnostic chain a developer walks is "did I wire
     * the prop?" — this check short-circuits that and points at
     * `php artisan view:clear`.
     *
     * Threshold: 60-second buffer between newest source mtime and
     * newest compiled-view mtime. Below the threshold = no warning
     * (normal fast-edit window). Above = WARN with the actionable hint.
     *
     * False-positive mitigation: this is WARN (not FAIL), the recommended
     * action is non-destructive, and slow filesystems (NFS / Docker on
     * macOS) get the same advice they'd give themselves anyway. The
     * threshold is tuned to bite on "I edited an hour ago and the test
     * still fails" — not on "I just hit save".
     */
    private function checkCompiledViewsFreshness(): void
    {
        $compiledDir = storage_path('framework/views');
        $sourceDir = resource_path('views');

        // No compiled views = fresh state (Laravel will compile on the
        // next render). Silent skip — nothing meaningful to report.
        if (! is_dir($compiledDir) || ! is_dir($sourceDir)) {
            return;
        }

        // Only *.php, which is what a compiled Blade template is. Without the filter the
        // scan also saw the shipped `.gitignore`, so a freshly cleared cache never looked
        // empty — and the advice became a loop: `view:clear` produces exactly the state
        // the warning then reports, so following it re-triggers the warning that sent you
        // there. A developer runs the suggested command, sees the same message, and
        // reasonably concludes the tool is broken.
        $newestCompiled = $this->newestMtimeUnder($compiledDir, ['php']);
        if ($newestCompiled === 0) {
            // Compiled directory holds no templates — the fresh state after view:clear.
            return;
        }

        $newestSource = $this->newestMtimeUnder($sourceDir, ['php']);
        if ($newestSource === 0) {
            return;
        }

        $lagSeconds = $newestSource - $newestCompiled;
        $thresholdSeconds = 60;

        if ($lagSeconds < $thresholdSeconds) {
            $this->reportPass('Compiled views are fresh (no staleness detected)');

            return;
        }

        $this->reportWarn(sprintf(
            'Compiled views may be stale (resources/views/ has files newer than storage/framework/views/ by %s).',
            $this->humanDuration($lagSeconds)
        ));
        $this->line('  Run: php artisan view:clear');
        $this->line('  This is the canonical fix when a developer test asserts a Blade prop / class that');
        $this->line('  was just wired in source but the assertion still fails — the compiled-view cache');
        $this->line('  retained the pre-edit template.');
    }

    /**
     * Recursive newest-mtime scanner with an optional extension filter.
     * Used by checkCompiledViewsFreshness() for both the source and
     * compiled directory traversals.
     *
     * @param  list<string>  $extensionsAllowlist  Empty = every file qualifies.
     */
    private function newestMtimeUnder(string $dir, array $extensionsAllowlist = []): int
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        $newest = 0;
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }
            if ($extensionsAllowlist !== [] && ! in_array($file->getExtension(), $extensionsAllowlist, true)) {
                continue;
            }
            $mtime = $file->getMTime();
            if ($mtime > $newest) {
                $newest = $mtime;
            }
        }

        return $newest;
    }

    /**
     * Pretty-print a duration in seconds as "Xh Ym" / "Xm Ys" / "Xs".
     * Used by the compiled-views-staleness check to produce the
     * actionable lag-amount in the WARN message.
     */
    private function humanDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds.'s';
        }
        if ($seconds < 3600) {
            $minutes = (int) floor($seconds / 60);
            $remainder = $seconds % 60;

            return $remainder > 0 ? "{$minutes}m {$remainder}s" : "{$minutes}m";
        }
        $hours = (int) floor($seconds / 3600);
        $minutes = (int) floor(($seconds % 3600) / 60);

        return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
    }

    /**
     * v2.4.0 Extension 3 — surface silent prop typos detected in the
     * Laravel log. The StrictnessGate emits `local.ERROR: WireKit [...]`
     * lines in HTTP dev mode when a developer passes an invalid prop
     * value (the gate logs + renders the fallback instead of throwing).
     * Without this scan a typo can sit in production code for weeks —
     * the page renders with the fallback and the developer never sees
     * the log.
     *
     * SAFE-DEGRADE contract — this check is an OPTIONAL helper, never
     * required, never blocks. Every branch handles its failure mode
     * gracefully:
     *   - Log file missing → INFO ("scan skipped — log not at default path")
     *   - Log file unreadable → INFO ("scan skipped — permission denied")
     *   - Log level filters below WARNING → INFO ("scan saw nothing —
     *     LOG_LEVEL may filter strict-validation writes")
     *   - Custom log channel (Slack, daily, stack-with-others) → INFO
     *     ("scan skipped — single-file scan can't see custom channels")
     *   - Found WireKit lines → WARN with example count + first 3 lines
     *   - Found none → PASS
     * The check NEVER returns FAIL — the doctor's overall exit code is
     * unaffected.
     *
     * Opt-out: set `wirekit.doctor.scan_logs` to `false` in config OR
     * pass `--no-scan-logs` flag on the command (registered separately).
     */
    private function checkSilentValidationTypos(): void
    {
        // SAFE-DEGRADE outer guard — wrap the entire method body in a
        // try/catch so ANY unexpected error (filesystem race during
        // parallel test runs, transient permission issue, glob() / fopen()
        // edge case) silently degrades to an INFO line instead of aborting
        // the rest of the doctor's check pipeline. The doctor's contract
        // (see method docblock) is that this check NEVER blocks; we extend
        // the contract here to "NEVER aborts subsequent checks either".
        // Without this guard, a transient PHP warning escalated to
        // exception under Pest's strict error handling would abort
        // `handle()` mid-pipeline and leak as a flaky test ("Alpine plugin
        // cleanup hygiene" missing because the check never ran).
        try {
            $this->checkSilentValidationTyposBody();
        } catch (\Throwable $e) {
            $this->reportInfo(
                'silent-typo log scan skipped — transient I/O error during scan ('.
                $e::class.': '.mb_substr($e->getMessage(), 0, 100).'). '.
                'The scan is an optional helper; subsequent doctor checks are unaffected.'
            );
        }
    }

    private function checkSilentValidationTyposBody(): void
    {
        // Honor opt-out config — defaults to true (helper IS on by default
        // in dev environments; production developers may disable explicitly).
        $enabled = (bool) config('wirekit.doctor.scan_logs', true);
        if (! $enabled) {
            return;
        }

        // Custom log channels — when the developer routes logs anywhere
        // OTHER than the default single-file channel ('single' or 'daily'),
        // scanning a static file path would miss the writes entirely. Bail
        // out with an INFO instead of falsely reporting "no typos".
        $defaultChannel = (string) config('logging.default', 'stack');
        $stackChannels = (array) config('logging.channels.stack.channels', ['single']);
        $scannableChannels = ['single', 'daily', 'stack'];
        if (! in_array($defaultChannel, $scannableChannels, true)) {
            $this->reportInfo(sprintf(
                'silent-typo log scan skipped — LOG_CHANNEL=%s routes elsewhere (Slack / Papertrail / Sentry / custom). '.
                'Inspect that destination for `WireKit [...]` ERROR/WARNING lines manually.',
                $defaultChannel
            ));

            return;
        }
        if ($defaultChannel === 'stack') {
            $usableInStack = array_intersect($stackChannels, ['single', 'daily']);
            if ($usableInStack === []) {
                $this->reportInfo(
                    'silent-typo log scan skipped — LOG_STACK contains only non-file channels. '.
                    'Inspect the configured destinations for `WireKit [...]` ERROR/WARNING lines manually.'
                );

                return;
            }
        }

        // Resolve the log file path — Laravel's default 'single' channel
        // writes to storage/logs/laravel.log. The 'daily' channel rotates
        // per day (laravel-YYYY-MM-DD.log); scan today's file.
        $logDir = storage_path('logs');
        if (! is_dir($logDir)) {
            $this->reportInfo(
                'silent-typo log scan skipped — storage/logs directory missing (fresh install? skipped log writes?).'
            );

            return;
        }

        $logFiles = [];
        $singlePath = $logDir.'/laravel.log';
        if (is_file($singlePath) && is_readable($singlePath)) {
            $logFiles[] = $singlePath;
        }
        $dailyPattern = $logDir.'/laravel-*.log';
        $dailyFiles = glob($dailyPattern) ?: [];
        // Sort by mtime — race-safe via @suppression so a sibling worker
        // deleting a daily-rotated log between glob() and filemtime()
        // doesn't escalate a warning to a fatal under Pest's strict
        // error handling. Missing mtime sorts to 0 (treated as oldest).
        usort($dailyFiles, fn ($a, $b) => ((int) @filemtime($a)) <=> ((int) @filemtime($b)));
        foreach (array_slice($dailyFiles, -3) as $daily) {
            // Re-verify readability AT read-time (the file may have
            // vanished between glob() and now under parallel test runs).
            if (is_file($daily) && is_readable($daily)) {
                $logFiles[] = $daily;
            }
        }

        if ($logFiles === []) {
            $this->reportInfo(
                'silent-typo log scan skipped — no readable log files found at storage/logs/laravel*.log. '.
                'If your app writes logs elsewhere, this check is a no-op (expected behavior).'
            );

            return;
        }

        // Scan each log file for `local.ERROR: WireKit [...]` or
        // `local.WARNING: WireKit [...]` lines. We use a streaming
        // scanner — read line-by-line — so a huge log file doesn't
        // exhaust memory.
        //
        // BOUNDED BY AGE, and without that bound this check could never come back green.
        // It reads the whole log history, so a typo fixed weeks ago keeps its line in
        // laravel.log forever and the warning stays yellow for the life of the file. A
        // developer who did exactly what they were told sees no change, which teaches
        // them to stop reading this check — the one outcome a diagnostic cannot afford.
        //
        // The window is configurable, and 0 restores the old read-everything behavior for
        // anyone who wants it.
        $matches = [];
        $matchCount = 0;
        $recentCount = 0;
        $matchPattern = '/^\[(?<ts>[^\]]+)\] [a-z]+\.(ERROR|WARNING): WireKit \[/i';

        $windowHours = (int) config('wirekit.doctor.scan_logs_window_hours', 24);
        $cutoff = $windowHours > 0 ? time() - ($windowHours * 3600) : null;

        foreach ($logFiles as $path) {
            $fh = @fopen($path, 'r');
            if ($fh === false) {
                continue;
            }
            while (($line = fgets($fh)) !== false) {
                if (! preg_match($matchPattern, $line, $m)) {
                    continue;
                }

                $matchCount++;

                // A line whose timestamp will not parse counts as RECENT. The opposite
                // default would silently drop real findings to keep the output tidy,
                // which is the failure this whole check exists to catch, one level up.
                $stamp = strtotime($m['ts']);
                $isRecent = $cutoff === null || $stamp === false || $stamp >= $cutoff;

                if (! $isRecent) {
                    continue;
                }

                $recentCount++;

                // Examples from the RECENT set: a sample drawn from the whole history
                // would show lines the developer has already fixed.
                if (count($matches) < 3) {
                    $matches[] = trim($line);
                }
            }
            fclose($fh);
        }

        // Only recent lines decide the verdict. `$matchCount` stays for the message,
        // because "clean in the last 24h, 47 older" is worth saying — it tells the
        // reader the file is not empty without pretending the past is a finding.
        $olderCount = $matchCount - $recentCount;
        $matchCount = $recentCount;

        if ($matchCount === 0) {
            $this->reportPass(sprintf(
                'silent-typo log scan clean — no `WireKit [...]` ERROR/WARNING lines in %d log file(s)%s. '.
                '(If your env logs at level=critical/emergency only, fallback warnings get filtered out — that is expected.)',
                count($logFiles),
                $olderCount > 0
                    ? sprintf(' within the last %dh (%d older line(s) ignored)', $windowHours, $olderCount)
                    : ''
            ));

            return;
        }

        // Found something — surface as WARN, NEVER as FAIL. The doctor
        // shouldn't refuse to exit zero just because the developer left
        // a typo in a button. Show count + first 3 example lines.
        $exampleLines = implode("\n      ", array_map(
            fn ($l) => mb_substr($l, 0, 180).(mb_strlen($l) > 180 ? '…' : ''),
            $matches
        ));
        $this->reportWarn(sprintf(
            "silent prop-typo signals found in storage/logs: %d `WireKit [...]` ERROR/WARNING line(s). Examples:\n      %s\n      Fix each component prop value to match its allowed enum. See https://docs.wirekit.app/strict-validation.",
            $matchCount,
            $exampleLines
        ));
    }
}
