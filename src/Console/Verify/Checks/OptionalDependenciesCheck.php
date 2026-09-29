<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use BaconQrCode\Renderer\ImageRenderer;
use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Console\VerifyInstallationCommand;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class OptionalDependenciesCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkOptionalDependencies();
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
            $this->reportInfo('Chart adapter not configured (optional — set charts.library to "chartjs" or "apexcharts" in config/wirekit.php to enable <x-wirekit-chart>)');
        }

        if (class_exists(ImageRenderer::class)) {
            $this->reportPass('bacon/bacon-qr-code installed');
        } else {
            $this->reportInfo('bacon/bacon-qr-code not installed (optional — only needed for <x-wirekit::qr-code>)');
        }

        // Front-end peer dependencies for <x-wirekit::editor> and <x-wirekit::map>.
        // These are browser globals (window.wirekitEditor / window.maplibregl /
        // window.L) or an engine handed over through registerMapEngine(), so a PHP
        // command can't probe whether they're loaded — they
        // surface as a contextual INFO reminder, not a pass/fail check. Listed
        // here so the onboarding doctor mentions them, not just the component
        // pages. Each component degrades gracefully if its dependency is absent.
        $this->reportInfo('<x-wirekit::editor> needs a ProseMirror editor (optional — Tiptap recommended: npm install @tiptap/core @tiptap/starter-kit and expose window.wirekitEditor; only if you use the editor)');
        $this->reportInfo('<x-wirekit::map> needs a map engine (optional — npm install maplibre-gl, 6.4.1 or newer, or leaflet, and hand it to WireKit through window.maplibregl, window.L or registerMapEngine(); only if you use the map)');
    }

    /**
     * Does the app's own JavaScript assign a browser global anywhere?
     *
     * Four shapes a one-line regex gets wrong, and each tells a working installation it is
     * broken or a broken one that it works:
     *
     *   * `window.X ??= …`. `\s*=` wants an `=` directly after the whitespace and finds
     *     `?`, so the idiomatic "assign unless something already did" form — exactly
     *     what a shared entry point uses — would read as no assignment at all. `||=`
     *     and `&&=` have the same shape.
     *   * `globalThis.X` and `window['X']`, the same assignment written by someone
     *     following a different house style.
     *   * A TypeScript entry point, which a walk over `.js` alone never sees.
     *   * And in the other direction: `window.X == null`, where the first `=` of `==`
     *     satisfies such a pattern. A comparison read as an assignment produces a PASS
     *     over an app that never assigns anything, the more expensive of the two mistakes.
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
     * Name the ApexCharts major in play, and say whether the adapter has been tested against it.
     *
     * The presence check above proves the package can be resolved. It says nothing about WHICH
     * major, and that is the half a developer cannot answer from the outside: WireKit ships no
     * `package.json` in its vendor directory, so there are no `peerDependencies` to read, and a
     * chart that breaks on a new major breaks silently — 200, correct markup, empty box.
     *
     * The INSTALLED version is preferred over the declared range because they disagree routinely:
     * `^6.4.0` in a manifest resolves to whatever the lockfile pinned. The range is the fallback
     * for a tree with no `node_modules`, and it is read as its lower bound, which is the only
     * major a caret range is guaranteed to permit.
     *
     * An untested major is a WARN and deliberately not a FAIL. WireKit cannot know that the next
     * major breaks anything — only that nobody has looked. A FAIL would turn every app red on the
     * day ApexCharts ships a version this package has not caught up with, which punishes the
     * developer for our release cadence.
     */
    private function checkApexChartsMajor(string $declaredRange): void
    {
        $tested = VerifyInstallationCommand::APEXCHARTS_TESTED_MAJORS;
        $range = implode(' / ', array_map(static fn (int $m): string => $m.'.x', $tested));

        $installedPath = base_path('node_modules/apexcharts/package.json');
        $installed = null;
        if (file_exists($installedPath)) {
            $manifest = json_decode((string) file_get_contents($installedPath), true);
            if (is_array($manifest) && isset($manifest['version']) && is_string($manifest['version'])) {
                $installed = $manifest['version'];
            }
        }

        $source = $installed !== null ? 'installed' : 'declared';
        $subject = $installed ?? $declaredRange;

        // The leading integer is the major in both shapes — "7.1.0" and "^6.4.0" alike. A range
        // with no digits at all ("latest", a git URL, a workspace link) yields no major, and that
        // is reported as unknown rather than silently treated as passing.
        $major = preg_match('/(\d+)/', $subject, $m) === 1 ? (int) $m[1] : null;

        if ($major === null) {
            $this->reportWarn(sprintf(
                'apexcharts version could not be read from the %s value "%s" — WireKit\'s adapter is tested against %s',
                $source,
                $subject,
                $range,
            ));

            return;
        }

        if (in_array($major, $tested, true)) {
            $this->reportPass(sprintf('apexcharts %s (%s) is within the tested range %s', $subject, $source, $range));

            return;
        }

        $this->reportWarn(sprintf(
            'apexcharts %s (%s) is OUTSIDE the range WireKit\'s adapter is tested against (%s). '
            .'It may work — nobody has measured it. A chart that breaks on a new major breaks '
            .'silently: the page renders, the markup is correct, and only a browser shows the '
            .'empty box. Check your charts visually, and report what you find.',
            $subject,
            $source,
            $range,
        ));
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
                $this->checkApexChartsMajor((string) $deps['apexcharts']);
            }
        } else {
            $this->reportInfo('package.json not found — skipping apexcharts npm presence check');
        }

        // Step 1b: is the global actually ASSIGNED anywhere?
        //
        // The manifest check above proves the package can be resolved, and that is a
        // different question from whether it reaches the page. The adapter reads
        // `window.ApexCharts` and nothing puts it there on its own — an app that installs
        // the npm package and never writes the assignment ships a bundle that finds
        // nothing, and every chart shows an "ApexCharts is not loaded" notice instead of drawing.
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
                .'that global and nothing sets it for you, so every chart shows an "ApexCharts is not loaded" notice instead of drawing. '
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
            // INFO, not WARN, and the distinction is what makes `--fail-on=warning` usable
            // as a gate. This line is a standing license reminder — it is true on every run
            // of a correctly configured install and will never stop being true, so counted
            // as a warning it holds the exit code at 1 forever. A gate that is permanently
            // red is a gate everybody learns to pass with `|| true`, and then the config
            // drift it was actually meant to catch goes with it. The reminder still prints.
            $this->reportInfo(
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
}
