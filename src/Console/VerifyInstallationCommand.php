<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Console\Verify\Checks\AiManifestStalenessCheck;
use Pushery\WireKit\Console\Verify\Checks\AlpineJsCheck;
use Pushery\WireKit\Console\Verify\Checks\AlpinePluginCleanupHygieneCheck;
use Pushery\WireKit\Console\Verify\Checks\AssetFreshnessCheck;
use Pushery\WireKit\Console\Verify\Checks\BladeDirectivesCheck;
use Pushery\WireKit\Console\Verify\Checks\BuiltCssHasWireKitUtilitiesCheck;
use Pushery\WireKit\Console\Verify\Checks\BundleConfigCheck;
use Pushery\WireKit\Console\Verify\Checks\ChartJsRegistrationCheck;
use Pushery\WireKit\Console\Verify\Checks\ChartUsageWithoutAdapterCheck;
use Pushery\WireKit\Console\Verify\Checks\CompiledViewsFreshnessCheck;
use Pushery\WireKit\Console\Verify\Checks\ConfigPublishedCheck;
use Pushery\WireKit\Console\Verify\Checks\CssImportAntiPatternCheck;
use Pushery\WireKit\Console\Verify\Checks\FontAssetsCheck;
use Pushery\WireKit\Console\Verify\Checks\IconPresetPackagesCheck;
use Pushery\WireKit\Console\Verify\Checks\InstalledPackageMatchesLockCheck;
use Pushery\WireKit\Console\Verify\Checks\OptionalDependenciesCheck;
use Pushery\WireKit\Console\Verify\Checks\PageShellsLoadWireKitCheck;
use Pushery\WireKit\Console\Verify\Checks\PublishedAssetsCheck;
use Pushery\WireKit\Console\Verify\Checks\PublishedViewsStalenessCheck;
use Pushery\WireKit\Console\Verify\Checks\ReplacingPersonalizationsCheck;
use Pushery\WireKit\Console\Verify\Checks\RootDarkSymmetryCheck;
use Pushery\WireKit\Console\Verify\Checks\SilentValidationTyposCheck;
use Pushery\WireKit\Console\Verify\Checks\TailwindSourceCheck;
use Pushery\WireKit\Console\Verify\Checks\TailwindVersionCheck;
use Pushery\WireKit\Console\Verify\Checks\TokenAlignmentCheck;
use Pushery\WireKit\Console\Verify\Checks\TokensInWrappersThatCannotWinCheck;
use Pushery\WireKit\Console\Verify\Checks\TranslationKeyCollisionsCheck;
use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Console\Verify\VerifyContext;
use Pushery\WireKit\Console\Verify\VerifyReport;
use Pushery\WireKit\Support\SuggestSimilar;
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
        {--fix : proactively self-heal missing public/vendor/wirekit/* assets by triggering `vendor:publish --tag=wirekit-assets --force`. Useful right after a fresh clone (where the assets are .gitignored) to avoid a red doctor on first run.}
        {--fail-on= : Severity that makes this command exit non-zero — "error" (default: only FAIL findings), "warning" (WARN findings too, which is what makes it usable as a CI gate), or "none" (report only, always exit 0).}';

    protected $description = 'Verify WireKit integration (assets, directives, Tailwind @source, optional deps)';

    /**
     * The ApexCharts majors WireKit's adapter is TESTED against — not the ones it is
     * guessed to work with.
     *
     * WireKit ships adapter glue, never the chart library, so the developer picks the
     * version and nothing in the package pins it (ApexCharts itself is non-MIT, see
     * https://apexcharts.com/license/). That leaves a question the developer
     * cannot answer for themselves, and the failure mode is the reason it matters: a
     * chart that breaks on a new major breaks silently: the server renders 200, the
     * markup carries the chart tag, and only a real browser shows the empty box.
     *
     * A major is listed once the sample's chart suite passes against it in a real browser,
     * which it does for 5.16.0, 6.10.0 and 7.1.0. The three differ in the statics the loaded
     * library exposes: 6 adds `_crossfilterFactory` and `_crossfilterGet`, 7 drops both
     * again, and the adapter references neither, which is why 7 costs it nothing.
     *
     * Keep this in lockstep with the supported-versions sentence in
     * `docs/components/chart.md`; `ApexTestedMajorsAreInLockstepTest` fails the build
     * when the two disagree.
     *
     * @var list<int>
     */
    public const APEXCHARTS_TESTED_MAJORS = [5, 6, 7];

    /**
     * Register the `wirekit:doctor` alias on the SAME Symfony command
     * instance, so `php artisan list` shows one canonical entry,
     * `wirekit:verify` with `Aliases: wirekit:doctor` underneath, matching
     * the de-facto Laravel ecosystem norm for diagnostic commands.
     *
     * CI scripts and docs that reference `wirekit:doctor` work unchanged:
     * Symfony Console routes an alias to the canonical command.
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setAliases(['wirekit:doctor']);
    }

    /**
     * The two scopes `--tier` accepts.
     *
     * Named rather than inlined because the value set was written out three times in one
     * method — once in the `in_array` check, once in the message's "Available:" list, and a
     * third time as the hint's haystack. Three copies of one list is three chances for the
     * rejection message to name a value the check does not accept.
     *
     * @var list<string>
     */
    private const TIERS = ['package', 'environment'];

    /**
     * The severities `--fail-on` accepts, in ascending strictness.
     *
     * Same reason as TIERS. `wirekit:doctor:a11y` carries the identical set for the identical
     * flag; the two are separate constants because neither command depends on the other, and
     * a shared one would couple them for the sake of three strings.
     *
     * @var list<string>
     */
    private const FAIL_ON_LEVELS = ['error', 'warning', 'none'];

    /**
     * The checks, by tier, in the order they report.
     *
     * The package tier verifies the WireKit install itself (assets, config, directives,
     * optional dependencies); these bite when the package's own install or upgrade misfires.
     * The environment tier verifies the Laravel host (compiled-view freshness, the installed
     * package against the lock file, silent prop typos in the logs); these bite during
     * interactive development and CI even when the package install is clean. Run
     * `wirekit:doctor --tier=environment` for only the second without the first.
     *
     * @var array<string, list<class-string<VerifyCheck>>>
     */
    private const CHECKS = [
        'package' => [
            TailwindVersionCheck::class,
            PublishedAssetsCheck::class,
            AssetFreshnessCheck::class,
            TailwindSourceCheck::class,
            ConfigPublishedCheck::class,
            BladeDirectivesCheck::class,
            PageShellsLoadWireKitCheck::class,
            AlpineJsCheck::class,
            BundleConfigCheck::class,
            PublishedViewsStalenessCheck::class,
            ReplacingPersonalizationsCheck::class,
            AiManifestStalenessCheck::class,
            FontAssetsCheck::class,
            CssImportAntiPatternCheck::class,
            IconPresetPackagesCheck::class,
            TranslationKeyCollisionsCheck::class,
            OptionalDependenciesCheck::class,
            ChartUsageWithoutAdapterCheck::class,
            ChartJsRegistrationCheck::class,
            BuiltCssHasWireKitUtilitiesCheck::class,
            TokenAlignmentCheck::class,
            RootDarkSymmetryCheck::class,
            TokensInWrappersThatCannotWinCheck::class,
            AlpinePluginCleanupHygieneCheck::class,
        ],
        'environment' => [
            CompiledViewsFreshnessCheck::class,
            InstalledPackageMatchesLockCheck::class,
            SilentValidationTyposCheck::class,
        ],
    ];

    public function handle(): int
    {
        $tier = $this->option('tier');
        if ($tier !== null && ! in_array($tier, self::TIERS, true)) {
            $this->error("Unknown tier '{$tier}'. Available: ".implode(', ', self::TIERS).'.');

            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein((string) $tier, self::TIERS));
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        // Validated here rather than read at the end, so a typo costs a sentence instead of
        // a whole check run that then exits on the wrong rule.
        $failOn = (string) ($this->option('fail-on') ?: 'error');

        if (! in_array($failOn, self::FAIL_ON_LEVELS, true)) {
            $this->error("Unknown --fail-on value '{$failOn}'. Expected one of: ".implode(', ', self::FAIL_ON_LEVELS).'.');

            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($failOn, self::FAIL_ON_LEVELS));
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            // FAILURE, never INVALID: every wirekit:* command exits 1 on every error path,
            // including a usage error.
            return self::FAILURE;
        }

        $this->info('WireKit Integration Check');
        $this->line('');

        // A fresh report and context for every run: Artisan resolves a command once per process
        // and hands the same instance to every call, so totals or a memoized file list held on
        // the command would carry one run into the next.
        $report = new VerifyReport($this);
        $context = new VerifyContext($this, $report);

        foreach (self::CHECKS as $checkTier => $checks) {
            if ($tier !== null && $tier !== $checkTier) {
                continue;
            }

            foreach ($checks as $check) {
                (new $check($context))->run();
            }
        }

        // ── Summary ──
        $this->line('');
        $this->line(sprintf(
            '  %s passed, %s warnings, %s failed',
            $report->passed,
            $report->warned,
            $report->failed
        ));

        if ($report->failed > 0) {
            $this->line('');
            $this->error('Integration incomplete — see failures above.');
            $this->line('  Reference: '.WireKit::DOCS_URL.'/getting-started/integration');

            return $failOn === 'none' ? self::SUCCESS : self::FAILURE;
        }

        if ($report->warned > 0) {
            $this->line('');
            $this->components->warn('Integration OK with warnings — consider fixing them.');

            // A warning can gate a pipeline: a published config predating this version's
            // options is reported here, the question a deploy most wants answered, and without
            // the threshold the process would exit 0 and tell a pipeline asking "is this
            // install sound?" yes.
            //
            // Raised by threshold rather than by promoting drift to a failure: the WARN tier
            // is reported from many call sites, and turning any of them red by default would
            // fail hosts that are working fine over a finding they have consciously accepted.
            // The caller says which severity matters to them, exactly as `wirekit:doctor:a11y`
            // and `wirekit:doctor:props` let them.
            if ($failOn === 'warning') {
                $this->line('  --fail-on=warning was given, so the warnings above are a failure rather than a note.');

                return self::FAILURE;
            }

            return self::SUCCESS;
        }

        $this->line('');
        $this->info('All checks passed.');

        return self::SUCCESS;
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
     * Reading the first character as an integer would return 0 for every
     * v-prefixed string, because `(int) "v" === 0`, and report Alpine as
     * missing wherever Composer kept the prefix in the lock file. The regex
     * below takes the first integer run anywhere in the version string, so
     * all five shapes above resolve.
     */
    public function detectLivewireVersion(?string $lockPath = null): int
    {
        $lockPath ??= base_path('composer.lock');

        // `file_exists` is true for a DIRECTORY and for a file the process cannot read. On a
        // directory `file_get_contents` returns an empty string with a notice, on an unreadable
        // file it returns false, and false under strict_types is a TypeError inside
        // `wirekit:verify`, a fatal for the person running the doctor to find out what is wrong.
        // So both are ruled out before the read.
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
}
