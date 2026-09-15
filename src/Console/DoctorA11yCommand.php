<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\Theming\WcagContrast;

/**
 * `wirekit:doctor:a11y` — static-analysis a11y linter for an app's
 * Blade templates plus an optional theme-contrast audit stage.
 *
 * Per-rule findings are categorized as ERROR (likely WCAG AA fail)
 * or WARNING (likely WCAG AA pass but worth review).
 *
 * Two stages:
 *
 *   1. Blade static scan (default). Scans every .blade.php under the
 *      given path for icon-only buttons without aria-label,
 *      role="dialog" without aria-labelledby, role="img" without
 *      alt-text. Always runs.
 *
 *   2. Theme-contrast audit (opt-in via `--theme-contrast` flag OR
 *      `WIREKIT_DOCTOR_THEME_CONTRAST=1` env). Parses the developer's
 *      `resources/css/app.css` for `--color-wk-*` token overrides
 *      under `:root` and `.dark` blocks, then computes WCAG 2.1
 *      contrast ratios for the canonical token pairings (`accent` as
 *      text on `bg`, `accent-fg` on `accent`, `text` on `bg`, etc.).
 *      Reports PASS / WARN / FAIL per pairing × mode. Catches the bug
 *      class where a developer customizes `--color-wk-accent` without
 *      verifying the new value still clears 4.5:1 against
 *      `--color-wk-accent-fg`.
 *
 *      Border handling follows WCAG 1.4.11. The COMMUNICATING borders
 *      (focus ring, stateful error / success borders, AND the
 *      `border-strong` / `border-strong-hover` form-control edge — the
 *      2.16.0/2.17.0 token whose whole purpose is 1.4.11 compliance) are
 *      hard-checked at 3:1, `border-strong` against the field fill
 *      (`--color-wk-bg-input`) it actually sits on. Only the RESTING
 *      DECORATIVE border (`--color-wk-border`, the card / divider edge)
 *      is exempt — printed as advisory INFO with its ratio but never
 *      counted toward the verdict or exit code. This matches
 *      docs/theming.md "Intentional trade-offs" and keeps WireKit's own
 *      decorative border (intentionally ~1.3-2.5:1) auditing clean while
 *      no longer letting a sub-3:1 CONTROL border pass silently.
 */
class DoctorA11yCommand extends Command
{
    /**
     * The severities `--fail-on` accepts, in ascending strictness.
     *
     * The set was written out four times in this file — the `in_array` check, the `--fail-on`
     * description in the signature, the reachable rejection's "Allowed:" list and the
     * defensive `match` arm's. A rejection message that names a value the check does not
     * accept is the failure that shape invites, and it is invisible until someone types it.
     *
     * @var list<string>
     */
    private const FAIL_ON_LEVELS = ['error', 'warning', 'none'];

    protected $signature = 'wirekit:doctor:a11y
        {path? : Path to scan (defaults to resources/views in the host app)}
        {--path=* : Directory to scan, repeatable and ADDITIVE to the default ground set. Same shape as wirekit:csp-audit.}
        {--fail-on= : Treat findings at this severity or higher as a non-zero exit. One of `error` (default), `warning`, or `none`. Use `warning` in CI to gate on every finding.}
        {--theme-contrast : Also audit the active theme tokens for WCAG 2.1 AA contrast against the canonical pairings. Reads resources/css/app.css.}';

    protected $description = 'Static-analysis a11y linter for WireKit components in Blade templates';

    public function handle(): int
    {
        // ⚠️ THE POSITIONAL REPLACES, `--path` ADDS, and the difference is the whole ask.
        //
        // A package component mounted BY NAME renders its Blade from `vendor/`, outside the
        // application's own view paths — so a page that mounts four package panels is
        // reported clean because the audit never opens their files. `wirekit:csp-audit`
        // already answers that with a repeatable, additive `--path`; this command had only
        // the positional, which replaces the ground set instead of extending it, so
        // checking a package meant giving up checking the application in the same run.
        /** @var array<int, string> $named */
        $named = (array) $this->option('path');

        $positional = $this->argument('path');

        $roots = $named !== []
            ? array_merge($positional !== null ? [$positional] : [base_path('resources/views')], $named)
            : [$positional ?: base_path('resources/views')];

        $roots = array_values(array_unique(array_filter($roots, 'is_string')));

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                $this->error("Path not found or not a directory: {$root}");

                return self::FAILURE;
            }
        }

        $path = implode(', ', $roots);

        $failOn = (string) ($this->option('fail-on') ?: 'error');
        if (! in_array($failOn, self::FAIL_ON_LEVELS, true)) {
            $this->error("Invalid --fail-on value: {$failOn}. Allowed: ".implode(' / ', self::FAIL_ON_LEVELS).'.');

            $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($failOn, self::FAIL_ON_LEVELS));
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        $this->info("Scanning {$path} for a11y issues...");
        $this->line('');

        $bladeFiles = [];

        foreach ($roots as $root) {
            // `$named` decides whether the vendor filter applies — see `collectBladeFiles()`.
            $bladeFiles = array_merge($bladeFiles, $this->collectBladeFiles($root, in_array($root, $named, true) || $positional !== null));
        }

        $bladeFiles = array_values(array_unique($bladeFiles));

        // Zero files is not a clean app, it is an unanswered question. Reporting
        // "no a11y issues found across 0 Blade files" reads as a pass and is what
        // a wrong `--path` produces — the same refusal `wirekit:csp-audit` makes
        // one command over, for the same reason.
        //
        // Unlike the props linter there is no second empty state here: these rules
        // scan any Blade file, not only ones using a WireKit component, so a
        // non-empty walk always had something in scope.
        //
        // The refusal is conditioned on the theme-contrast stage, and that is the
        // substance rather than a special case: this command has TWO stages, and
        // the rule is "a run that measured nothing must not report a pass", not
        // "a stage that measured nothing". A `--theme-contrast` run reads the
        // token table and answers a real question with no Blade file involved.
        // Failing it for an empty template walk would refuse a run that did in
        // fact measure something.
        if ($bladeFiles === [] && ! $this->themeContrastRequested()) {
            $this->error(sprintf('Found no Blade templates in %s.', $path));
            $this->line('');
            $this->line('That is a failure rather than a pass: an audit that read nothing');
            $this->line('and reported "clean" is worse than none. Check the path.');

            return self::FAILURE;
        }

        $findings = [];

        foreach ($bladeFiles as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            // A comment is where somebody QUOTED the markup these rules look for, usually to
            // explain why the real thing is correct — so it is the likeliest place to find a
            // finding that is not one. Blanked rather than removed, so every line number below
            // still points at the line it did.
            $contents = BladeParser::blankComments($contents);

            $rel = str_replace(base_path().'/', '', $file);

            foreach ($this->rules() as $rule) {
                foreach (($rule['scan'])($contents, $rel) as $finding) {
                    $findings[] = $finding;
                }
            }
        }

        // Group by severity for readable output. Computed BEFORE the
        // early-return path so the theme-contrast stage below sees a
        // consistent local-variable shape regardless of the blade-scan
        // outcome.
        $errors = array_filter($findings, fn ($f) => $f['severity'] === 'error');
        $warnings = array_filter($findings, fn ($f) => $f['severity'] === 'warning');

        if ($findings === []) {
            $this->info('✓ No a11y issues found across '.count($bladeFiles).' Blade files.');
            $bladeExit = self::SUCCESS;

            // Fall through to the theme-contrast stage check below
            // instead of an early-return — the developer may have
            // passed `--theme-contrast` on a clean app and still
            // expects that stage to run.
            return $this->maybeRunThemeContrast($bladeExit, $failOn, $bladeFiles);
        }

        if ($errors !== []) {
            $this->line('<fg=red>ERRORS</> ('.count($errors).')');
            foreach ($errors as $f) {
                $this->line("  <fg=red>✗</> {$f['file']}:{$f['line']} — {$f['message']}");
            }
            $this->line('');
        }

        if ($warnings !== []) {
            $this->line('<fg=yellow>WARNINGS</> ('.count($warnings).')');
            foreach ($warnings as $f) {
                $this->line("  <fg=yellow>⚠</> {$f['file']}:{$f['line']} — {$f['message']}");
            }
            $this->line('');
        }

        $this->line('Scanned '.count($bladeFiles).' Blade files. '.count($errors).' errors, '.count($warnings).' warnings.');

        $bladeExit = match ($failOn) {
            'error' => $errors !== [] ? self::FAILURE : self::SUCCESS,
            'warning' => ($errors !== [] || $warnings !== []) ? self::FAILURE : self::SUCCESS,
            'none' => self::SUCCESS,
        };

        return $this->maybeRunThemeContrast($bladeExit, $failOn, $bladeFiles);
    }

    /**
     * Whether the opt-in theme-contrast stage will run.
     *
     * Read in two places now — once to decide whether an empty template walk is
     * a refusal, and once to run the stage — so it lives in one method. Two
     * copies of this condition would drift, and the drift would be silent in the
     * direction that matters: the run would refuse a `--theme-contrast` audit
     * that was about to measure something.
     */
    private function themeContrastRequested(): bool
    {
        return (bool) $this->option('theme-contrast')
            || (string) getenv('WIREKIT_DOCTOR_THEME_CONTRAST') === '1';
    }

    /**
     * Opt-in theme-contrast stage. Runs AFTER the blade scan so the
     * static findings always surface first; if either stage fails,
     * the overall command exits non-zero.
     *
     * @param  array<int, string>  $bladeFiles
     */
    private function maybeRunThemeContrast(int $bladeExit, string $failOn, array $bladeFiles = []): int
    {
        if (! $this->themeContrastRequested()) {
            return $bladeExit;
        }

        $this->line('');
        $themeExit = $this->runThemeContrastAudit($failOn, $bladeFiles);

        return ($bladeExit === self::FAILURE || $themeExit === self::FAILURE)
            ? self::FAILURE
            : self::SUCCESS;
    }

    /**
     * Theme-contrast audit stage. Reads the developer's app.css,
     * resolves the effective `--color-wk-*` token table for both
     * `:root` and `.dark` blocks (falling back to vendor defaults in
     * `dist/wirekit.css` when the developer hasn't overridden a token),
     * computes WCAG 2.1 ratios for the canonical pairings, and prints
     * a PASS / WARN / FAIL report.
     *
     * @param  array<int, string>  $bladeFiles
     */
    private function runThemeContrastAudit(string $failOn, array $bladeFiles = []): int
    {
        $this->line('<fg=cyan>Theme contrast audit</>');
        $this->line('');

        $appCss = base_path('resources/css/app.css');
        $vendorCss = base_path('vendor/pushery/wirekit/dist/wirekit.css');
        if (! is_file($vendorCss)) {
            // Workbench / monorepo case where the package is in-tree, not vendored.
            $vendorCss = dirname(__DIR__, 2).'/dist/wirekit.css';
        }

        $vendorTokens = $this->parseTokens(is_file($vendorCss) ? (string) file_get_contents($vendorCss) : '');
        $appTokens = $this->parseTokens(is_file($appCss) ? (string) file_get_contents($appCss) : '');

        // Effective token tables: developer override wins, vendor fills the gap.
        $light = array_merge($vendorTokens['light'] ?? [], $appTokens['light'] ?? []);
        $dark = array_merge($vendorTokens['dark'] ?? [], $appTokens['dark'] ?? []);
        // Dark mode inherits light tokens for any key not redeclared.
        $dark = array_merge($light, $dark);

        // Canonical WCAG-relevant pairings. `text` threshold is 4.5:1
        // (normal body text); `ui` threshold is 3.0:1 (focus rings,
        // active borders, large text).
        $pairings = [
            ['name' => 'text on bg', 'fg' => '--color-wk-text', 'bg' => '--color-wk-bg', 'threshold' => 'text'],
            ['name' => 'text on bg-elevated', 'fg' => '--color-wk-text', 'bg' => '--color-wk-bg-elevated', 'threshold' => 'text'],
            ['name' => 'text-muted on bg', 'fg' => '--color-wk-text-muted', 'bg' => '--color-wk-bg', 'threshold' => 'text'],
            ['name' => 'accent as text on bg', 'fg' => '--color-wk-accent', 'bg' => '--color-wk-bg', 'threshold' => 'text'],
            ['name' => 'accent-content as text on bg', 'fg' => '--color-wk-accent-content', 'bg' => '--color-wk-bg', 'threshold' => 'text'],
            ['name' => 'accent-fg on accent (primary button)', 'fg' => '--color-wk-accent-fg', 'bg' => '--color-wk-accent', 'threshold' => 'text'],
            ['name' => 'accent-fg on accent-hover', 'fg' => '--color-wk-accent-fg', 'bg' => '--color-wk-accent-hover', 'threshold' => 'text'],
            // Every semantic filled button, at rest AND under the pointer. The
            // hover rows are here because the list carried `accent-fg on
            // accent-hover` and none of its three semantic siblings, and the
            // derived scan cannot make up the difference: it reads a literal
            // `class="…"`, while these classes come out of VariantResolver at
            // render time. So a theme could darken --color-wk-success-hover under
            // a near-black label and the doctor stayed silent — which is exactly
            // what the shipped default did (5.51:1 at rest, 3.59:1 on hover).
            ['name' => 'danger-fg on danger', 'fg' => '--color-wk-danger-fg', 'bg' => '--color-wk-danger', 'threshold' => 'text'],
            ['name' => 'danger-fg on danger-hover', 'fg' => '--color-wk-danger-fg', 'bg' => '--color-wk-danger-hover', 'threshold' => 'text'],
            ['name' => 'success-fg on success', 'fg' => '--color-wk-success-fg', 'bg' => '--color-wk-success', 'threshold' => 'text'],
            ['name' => 'success-fg on success-hover', 'fg' => '--color-wk-success-fg', 'bg' => '--color-wk-success-hover', 'threshold' => 'text'],
            ['name' => 'warning-fg on warning', 'fg' => '--color-wk-warning-fg', 'bg' => '--color-wk-warning', 'threshold' => 'text'],
            ['name' => 'warning-fg on warning-hover', 'fg' => '--color-wk-warning-fg', 'bg' => '--color-wk-warning-hover', 'threshold' => 'text'],

            // Communicating borders — WCAG 1.4.11 DOES require >= 3:1 for these:
            // a focus ring and the stateful (error / success) borders convey
            // state or mark an active boundary, so they are hard-checked.
            ['name' => 'focus ring on ring-offset', 'fg' => '--color-wk-ring', 'bg' => '--color-wk-ring-offset', 'threshold' => 'ui'],
            ['name' => 'border-error on bg', 'fg' => '--color-wk-border-error', 'bg' => '--color-wk-bg', 'threshold' => 'ui'],
            ['name' => 'border-success on bg', 'fg' => '--color-wk-border-success', 'bg' => '--color-wk-bg', 'threshold' => 'ui'],
            // border-strong is the COMMUNICATING form-control border introduced in
            // 2.16.0 precisely because WCAG 1.4.11 requires >= 3:1 for it.
            // It is NOT decorative — it is the resting + hover edge of input / select
            // / textarea / checkbox, so it is hard-checked, and against the field
            // FILL (--color-wk-bg-input) it actually sits on, not the page bg.
            ['name' => 'border-strong on bg-input', 'fg' => '--color-wk-border-strong', 'bg' => '--color-wk-bg-input', 'threshold' => 'ui'],
            ['name' => 'border-strong-hover on bg-input', 'fg' => '--color-wk-border-strong-hover', 'bg' => '--color-wk-bg-input', 'threshold' => 'ui'],

            // Decorative resting borders — WCAG 1.4.11 EXEMPTS pure dividers that
            // neither convey state nor identify an active boundary. Only
            // --color-wk-border qualifies: it is the card / divider edge WireKit
            // ships intentionally low-contrast (~1.3-2.5:1), matching every major
            // design system; see docs/theming.md "Intentional trade-offs". It is
            // audited for INFORMATION ONLY ('advisory'): the ratio is printed, but
            // never counts toward PASS/WARN/FAIL totals and never affects the exit
            // code. (border-strong used to live here too — that was the
            // bug: the tool was blind to the one token it mattered most for.)
            ['name' => 'border on bg', 'fg' => '--color-wk-border', 'bg' => '--color-wk-bg', 'threshold' => 'ui', 'advisory' => true],
        ];

        // ⚠️ AND THE PAIRINGS THIS TREE ACTUALLY RENDERS, which the list above cannot know.
        //
        // The canonical list is a list. It covers the pairings this package's own components
        // use, and a component that puts a different foreground on a different background —
        // its own, or one from a package the application mounts — appears in none of its
        // rows. The contrast is then unchecked, and unchecked SILENTLY: the run reports PASS
        // over the pairings it knows and says nothing about the one on the page.
        //
        // Both nets have their hole in the same place. This one is not in the Blade rules
        // either, because contrast is not one of them.
        //
        // Measured over this package's own views before building it: 23 distinct pairings
        // are rendered and 19 of them are outside the canonical list — the most frequent
        // being muted text on a muted background, fifteen times. So the gap had content
        // rather than being a hypothetical.
        $derived = $this->derivedPairings($bladeFiles, $pairings);
        $pairings = array_merge($pairings, $derived);

        // ⚠️ AND THE COUNT IS SAID OUT LOUD, because a derivation that finds nothing
        // reports no contrast failure — which is indistinguishable from a tree that has
        // none. Zero over a non-empty template set is a scan to repair, not a clean result,
        // and without this line the two print identically.
        if ($bladeFiles !== []) {
            $this->line($derived === []
                ? '  <fg=yellow>No rendered pairing was derived from '.count($bladeFiles).' template(s) — the scan found nothing to add, which is not the same as nothing being there.</>'
                : sprintf('  <fg=gray>%d pairing(s) derived from the templates themselves, beyond the canonical list.</>', count($derived)));
            $this->line('');
        }

        $totals = ['pass' => 0, 'warn' => 0, 'fail' => 0, 'skip' => 0, 'exempt' => 0];

        foreach (['light' => $light, 'dark' => $dark] as $mode => $tokens) {
            $this->line(sprintf('  <fg=cyan>%s mode</>', ucfirst($mode)));

            foreach ($pairings as $pair) {
                $fg = $tokens[$pair['fg']] ?? null;
                $bg = $tokens[$pair['bg']] ?? null;
                if ($fg === null || $bg === null) {
                    $totals['skip']++;
                    $this->line(sprintf('    <fg=gray>SKIP</> %-44s   token unresolved', $pair['name']));

                    continue;
                }

                // Substitute any embedded custom-property var() (e.g.
                // `oklch(L C var(--theme-hue))` on a single-source-hue preset)
                // from the same token table before parsing — otherwise the hue
                // channel is an unparseable token and the pairing SKIPs.
                $fg = WcagContrast::resolveCssVars($fg, $tokens);
                $bg = WcagContrast::resolveCssVars($bg, $tokens);

                $ratio = WcagContrast::ratio($fg, $bg);
                if ($ratio === null) {
                    $totals['skip']++;
                    // Two different findings, and only one is about how a value is written. A translucent
                    // background has no contrast of its own, so calling it a format problem would send a
                    // developer looking for a typo in a value that parsed perfectly well.
                    $this->line(sprintf(
                        '    <fg=gray>SKIP</> %-44s   %s',
                        $pair['name'],
                        WcagContrast::unmeasurableReason($fg, $bg) === 'translucent-background'
                            ? 'translucent background, contrast depends on what lies beneath it'
                            : 'unsupported color format',
                    ));

                    continue;
                }

                // Decorative resting borders are WCAG 1.4.11 exempt — print the
                // ratio for visibility but never let it drive the verdict or the
                // exit code (the exit logic below reads fail/warn totals only).
                if ($pair['advisory'] ?? false) {
                    $totals['exempt']++;
                    $this->line(sprintf(
                        '    <fg=blue>INFO</> %-44s   %.2f:1  (decorative, WCAG 1.4.11 exempt)',
                        $pair['name'],
                        $ratio,
                    ));

                    continue;
                }

                $klass = WcagContrast::classify($ratio, $pair['threshold']);
                $totals[$klass]++;
                $label = match ($klass) {
                    'pass' => '<fg=green>PASS</>',
                    'warn' => '<fg=yellow>WARN</>',
                    'fail' => '<fg=red>FAIL</>',
                    default => 'SKIP',
                };
                $this->line(sprintf(
                    '    %s %-44s   %.2f:1  (>= %s)',
                    $label,
                    $pair['name'],
                    $ratio,
                    $pair['threshold'] === 'ui' ? '3.0' : '4.5',
                ));
            }
            $this->line('');
        }

        $this->line(sprintf(
            'Theme contrast totals: %d PASS / %d WARN / %d FAIL / %d SKIP / %d EXEMPT (decorative).',
            $totals['pass'],
            $totals['warn'],
            $totals['fail'],
            $totals['skip'],
            $totals['exempt'],
        ));

        return match ($failOn) {
            'error' => $totals['fail'] > 0 ? self::FAILURE : self::SUCCESS,
            'warning' => ($totals['fail'] > 0 || $totals['warn'] > 0) ? self::FAILURE : self::SUCCESS,
            'none' => self::SUCCESS,
            // An unrecognized --fail-on. Without this arm PHP raises UnhandledMatchError,
            // which reaches the developer as a stack trace rather than as a command telling
            // them what they mistyped. Exit 1 like every other failure here: this repo uses
            // FAILURE for invalid input too, never Symfony's INVALID (2).
            default => $this->reportUnknownFailOn($failOn),
        };
    }

    /**
     * Parse `--color-wk-*` declarations under `:root` and `.dark`
     * blocks (including the `:where(...)` wrapped forms). Resolves
     * simple `var(--other)` aliases by chaining through the same
     * token table.
     *
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    private function parseTokens(string $css): array
    {
        if ($css === '') {
            return ['light' => [], 'dark' => []];
        }
        $css = (string) preg_replace('!/\*.*?\*/!s', '', $css);

        // Which presentation each block actually describes, before any of it is read as a
        // token table. See splitByPresentation() for the defect this answers.
        $byPresentation = self::splitByPresentation($css);

        $extract = function (string $selector, string $source): array {
            $escaped = preg_quote($selector, '/');

            // ⚠️ THE SELECTOR MAY SIT IN A LIST. A theme writes `:root, .light` for a token that
            // changes with the mode, as the theming guide asks, and the shipped stylesheet once
            // declared its own light tokens on `:where(:root), :where(.light)`. Requiring the
            // brace to follow the selector immediately read such a rule as absent — and absent is
            // indistinguishable from empty here, so every pairing came back "token unresolved"
            // rather than wrong.
            //
            // `[^{};]*` and not `[^{}]*`: a selector list cannot contain a semicolon, and
            // without that exclusion a `.dark` inside a DECLARATION (`:where(.dark, .dark *)`)
            // runs across the gap to the next rule and captures a block it does not belong to.
            $pattern = '/(?<![\w-])(?::where\(\s*)?'.$escaped.'(?:\s*\))?(?:\s*,[^{};]*)?\s*\{([^}]*)\}/u';
            preg_match_all($pattern, $source, $matches);
            $tokens = [];
            foreach ($matches[1] ?? [] as $body) {
                // Capture EVERY custom property (not just --color-wk-*) so that
                // a hue-driven token like `oklch(L C var(--theme-hue))` can have
                // its `--theme-hue` reference substituted before parsing.
                // The pairings still only evaluate --color-wk-* pairs; the extra
                // entries are var()-resolution sources.
                if (preg_match_all('/(--[\w-]+)\s*:\s*([^;]+);/', $body, $declMatches, PREG_SET_ORDER)) {
                    foreach ($declMatches as $entry) {
                        $tokens[$entry[1]] = trim($entry[2]);
                    }
                }
            }

            return $tokens;
        };

        $light = $extract(':root', $byPresentation['screen']);

        // A theme can express dark two ways, and both are legitimate: the `.dark` class the
        // kit's own toggler writes, and `@media (prefers-color-scheme: dark)` for a theme
        // that follows the system with no toggle of its own. The second used to land in the
        // LIGHT table, because a `:root` block is a `:root` block wherever it sits -- so a
        // theme written that way had its light mode audited against its dark colors, which
        // is the worse direction of the two: light is the mode people spot-check.
        //
        // The class wins where both declare the same token. It is the documented switch,
        // and it is what is actually on the element when the toggler has run.
        $dark = array_merge(
            $extract(':root', $byPresentation['dark']),
            $extract('.dark', $byPresentation['dark']),
            $extract('.dark', $byPresentation['screen']),
        );

        // Resolve var(--other-token) aliases within the same block.
        // Bounded recursion: max 5 hops per token to avoid cycles.
        $resolve = function (array $tokens): array {
            foreach ($tokens as $name => $value) {
                $hops = 0;
                while (preg_match('/^\s*var\(\s*(--[\w-]+)\s*(?:,[^)]*)?\)\s*$/', $value, $m) === 1 && $hops < 5) {
                    $alias = $m[1];
                    if (! isset($tokens[$alias])) {
                        break;
                    }
                    $value = $tokens[$alias];
                    $hops++;
                }
                $tokens[$name] = $value;
            }

            return $tokens;
        };

        return [
            'light' => $resolve($light),
            'dark' => $resolve($dark),
        ];
    }

    /**
     * The stylesheet split by the presentation each block actually applies to.
     *
     * ⚠️ AN AT-RULE IS INVISIBLE TO A SELECTOR PATTERN, AND THAT IS THE WHOLE DEFECT. The
     * extractor above finds `:root` and `.dark` wherever they stand; an `@media` wrapper is
     * just text in between. So a print sheet written exactly as it should be --
     *
     *     @media print {
     *         :root,
     *         .dark { --color-wk-bg: #fff; --color-wk-text: #000; }
     *     }
     *
     * -- was read as a description of the screen, and being the LAST `.dark` match in the
     * file it replaced the entire dark table. Measured in a developer stylesheet: the audit
     * reported 21.00:1 for text on bg in dark mode -- pure black on pure white, the highest
     * ratio that exists -- where the real navy surface measures 18.27:1, plus one FAIL and
     * two WARN on border pairings whose real ratios all pass.
     *
     * ⚠️ Light was untouched, and that is a trap rather than a clue: `:root,` ends in a
     * comma, and the selector pattern needs a brace. A reader who takes "light is correct"
     * as evidence looks for a bug in the dark PATH, and there is none -- that path resolves
     * an inherited `:root` custom property and an embedded `var()` correctly, both proven
     * separately. The difference is which half of one print selector happened to match.
     *
     * ⚠️ AN UNRECOGNIZED PRELUDE STAYS WHERE IT IS. Dropping a block this classifier does
     * not understand would narrow the audit silently, and a narrower audit reports fewer
     * findings -- which reads exactly like a cleaner theme.
     *
     * @return array{screen: string, dark: string}
     */
    private static function splitByPresentation(string $css): array
    {
        $screen = '';
        $dark = '';
        $offset = 0;

        while (($at = strpos($css, '@media', $offset)) !== false) {
            $braceAt = strpos($css, '{', $at);

            if ($braceAt === false) {
                break;
            }

            $end = self::matchingBrace($css, $braceAt);

            if ($end === null) {
                break;
            }

            $screen .= substr($css, $offset, $at - $offset);

            $prelude = substr($css, $at + 6, $braceAt - $at - 6);
            $body = substr($css, $braceAt + 1, $end - $braceAt - 1);

            match (self::presentationOf($prelude)) {
                // Flattened rather than kept wrapped, which is equivalent here: the
                // extractor reads declaration blocks and never the at-rule around them.
                'screen' => $screen .= $body,
                'dark' => $dark .= $body,
                // Paper, speech and the alternative-preference themes describe something
                // this audit does not claim to measure.
                default => null,
            };

            $offset = $end + 1;
        }

        $screen .= substr($css, $offset);

        return ['screen' => $screen, 'dark' => $dark];
    }

    /**
     * Which of the audited presentations an `@media` prelude describes.
     *
     * Deliberately a short list of things that are definitely NOT the default screen,
     * rather than an attempt at a media-query parser: the cost of the two directions is not
     * symmetric. Misreading a block as screen puts a wrong number in a report somebody can
     * check against their own page; dropping one they meant makes a finding disappear.
     */
    private static function presentationOf(string $prelude): string
    {
        $prelude = strtolower(trim($prelude));

        // `not print` is every medium EXCEPT paper, so the word alone decides nothing. A
        // negated query is left where it is rather than guessed at.
        if (preg_match('/(?<![\w-])not(?![\w-])/', $prelude) === 1) {
            return 'screen';
        }

        if (preg_match('/prefers-color-scheme\s*:\s*dark/', $prelude) === 1) {
            return 'dark';
        }

        // The media TYPE, which is a bare word rather than a feature in parentheses.
        if (preg_match('/(^|[\s,(])(print|speech)(?![\w-])/', $prelude) === 1) {
            return 'drop';
        }

        // Alternative themes a user preference switches on. Each is a real surface and each
        // deserves its own audit; what none of them is, is the default one.
        if (preg_match('/prefers-contrast|forced-colors|inverted-colors/', $prelude) === 1) {
            return 'drop';
        }

        return 'screen';
    }

    /**
     * The index of the `}` closing the `{` at $open, or null when the file is unbalanced.
     *
     * An unbalanced stylesheet ends the walk rather than guessing, so a truncated file is
     * read as far as it is trustworthy and no further.
     */
    private static function matchingBrace(string $css, int $open): ?int
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * The static rules. Each rule is a closure receiving the file
     * contents + relative path, returning a list of finding arrays.
     *
     * @return array<int, array{name: string, scan: callable}>
     */
    private function rules(): array
    {
        return [
            [
                'name' => 'icon-only-button-missing-aria-label',
                'scan' => $this->scanIconOnlyButtonMissingAriaLabel(...),
            ],
            [
                'name' => 'dialog-role-missing-label',
                'scan' => $this->scanDialogRoleMissingLabel(...),
            ],
            [
                'name' => 'img-role-missing-aria-label',
                'scan' => $this->scanImgRoleMissingAriaLabel(...),
            ],
        ];
    }

    /**
     * Rule: every <x-wirekit::button> whose only child is an
     * <x-wirekit::icon> (no slot text, no body) MUST carry an
     * aria-label attribute.
     *
     * Severity: ERROR (WCAG 2.1 "Buttons must have discernible text").
     *
     * @return list<array{file: string, line: int, severity: string, message: string}>
     */
    private function scanIconOnlyButtonMissingAriaLabel(string $contents, string $file): array
    {
        $findings = [];
        // Match <x-wirekit::button ...><x-wirekit::icon ... /></x-wirekit::button>
        // OR <x-wirekit::button ...><svg ...></svg></x-wirekit::button>
        // where the OUTER button tag does NOT contain aria-label.
        $pattern = '/<x-wirekit::button(?P<attrs>[^>]*)>\s*(?P<inner><x-wirekit::icon[^>]*\/?>|<svg[^>]*>.*?<\/svg>)\s*<\/x-wirekit::button>/su';
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $i => $match) {
            $attrs = $matches['attrs'][$i][0];
            if (preg_match('/\baria-label\s*=/', $attrs) === 1) {
                continue;
            }
            $line = substr_count(substr($contents, 0, $match[1]), "\n") + 1;
            $findings[] = [
                'file' => $file,
                'line' => $line,
                'severity' => 'error',
                'message' => '<x-wirekit::button> with only an icon child has no aria-label — screen readers will announce only "button" without a name. Add aria-label="..." describing the action.',
            ];
        }

        return $findings;
    }

    /**
     * Rule: every element with role="dialog" or role="alertdialog"
     * MUST carry aria-label OR aria-labelledby.
     *
     * Severity: ERROR (WCAG 4.1.2 + ARIA APG dialog pattern).
     *
     * @return list<array{file: string, line: int, severity: string, message: string}>
     */
    private function scanDialogRoleMissingLabel(string $contents, string $file): array
    {
        $findings = [];
        $pattern = '/<[a-z][\w-]*(?P<attrs>[^>]*\brole\s*=\s*"(?P<role>(?:alert)?dialog)"[^>]*)>/u';
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $i => $match) {
            $attrs = $matches['attrs'][$i][0];
            $role = $matches['role'][$i][0];
            if (preg_match('/\baria-label(?:ledby)?\s*=/', $attrs) === 1) {
                continue;
            }
            $line = substr_count(substr($contents, 0, $match[1]), "\n") + 1;
            $findings[] = [
                'file' => $file,
                'line' => $line,
                'severity' => 'error',
                'message' => "<{$role}> role has no aria-label or aria-labelledby — screen readers will not announce the dialog's purpose. Add aria-labelledby pointing at the dialog title's id, or aria-label as a fallback.",
            ];
        }

        return $findings;
    }

    /**
     * Rule: every element with role="img" MUST carry aria-label
     * OR aria-labelledby (or an alt-equivalent on the underlying
     * element).
     *
     * Severity: ERROR (WCAG 1.1.1 non-text content).
     *
     * @return list<array{file: string, line: int, severity: string, message: string}>
     */
    private function scanImgRoleMissingAriaLabel(string $contents, string $file): array
    {
        $findings = [];
        $pattern = '/<[a-z][\w-]*(?P<attrs>[^>]*\brole\s*=\s*"img"[^>]*)>/u';
        if (preg_match_all($pattern, $contents, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $i => $match) {
            $attrs = $matches['attrs'][$i][0];
            if (preg_match('/\baria-label(?:ledby)?\s*=/', $attrs) === 1) {
                continue;
            }
            $line = substr_count(substr($contents, 0, $match[1]), "\n") + 1;
            $findings[] = [
                'file' => $file,
                'line' => $line,
                'severity' => 'error',
                'message' => 'role="img" element has no aria-label or aria-labelledby — screen readers will skip the content. Add aria-label describing the image semantics.',
            ];
        }

        return $findings;
    }

    /**
     * The foreground/background token pairings the scanned templates actually render.
     *
     * An element carrying both a `bg-[var(--color-wk-X)]` and a
     * `text-[color:var(--color-wk-Y)]` is a pairing this tree puts on a page, whether or
     * not anybody listed it. Derived rather than enumerated for the reason the canonical
     * list exists at all: a list covers what its author knew about.
     *
     * Deliberately conservative — only a class attribute, only the two arbitrary-value
     * shapes this package emits, and only tokens. A guess here becomes a contrast finding
     * in somebody's application about a pairing that never renders, and this command's
     * whole worth is that its output can be trusted.
     *
     * @param  array<int, string>  $bladeFiles
     * @param  array<int, array<string, mixed>>  $known
     * @return list<array<string, mixed>>
     */
    private function derivedPairings(array $bladeFiles, array $known): array
    {
        $seen = [];

        foreach ($known as $pair) {
            $seen[$pair['fg'].'|'.$pair['bg']] = true;
        }

        $found = [];

        foreach ($bladeFiles as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                continue;
            }

            // ⚠️ A LITERAL `class` ONLY — never `:class`, `x-bind:class` or `@class`.
            //
            // A bound class list is where the BRANCHES live, and two classes in different
            // branches never render together. The first version of this scan matched them
            // and produced SIX contrast failures over this package's own views, every one
            // of them a pairing that cannot occur: `text on accent 1.10:1` came from an
            // event-calendar ternary whose true branch sets the accent background with
            // inverse text and whose false branch sets ordinary text on nothing.
            //
            // Six invented findings is not a rough edge. It is the failure mode this whole
            // command is written against, and it would have shipped as a FAIL.
            preg_match_all('/(?<![:\w-])class="([^"]{0,2000})"/', $contents, $chunks);

            foreach ($chunks[1] as $chunk) {
                preg_match_all('/\bbg-\[var\((--color-wk-[a-z0-9-]+)\)\]/', $chunk, $bgs);
                preg_match_all('/text-\[color:var\((--color-wk-[a-z0-9-]+)\)\]/', $chunk, $fgs);

                foreach (array_unique($bgs[1]) as $bg) {
                    foreach (array_unique($fgs[1]) as $fg) {
                        $key = $fg.'|'.$bg;

                        if (isset($seen[$key])) {
                            continue;
                        }

                        $seen[$key] = true;
                        $found[] = [
                            'name' => sprintf(
                                '%s on %s (rendered)',
                                str_replace('--color-wk-', '', $fg),
                                str_replace('--color-wk-', '', $bg),
                            ),
                            'fg' => $fg,
                            'bg' => $bg,
                            'threshold' => 'text',
                        ];
                    }
                }
            }
        }

        return $found;
    }

    /**
     * Collect every .blade.php file under the given root, excluding
     * vendor / node_modules / storage / cache directories.
     *
     * @return list<string>
     */
    private function collectBladeFiles(string $root, bool $named = false): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php')) {
                continue;
            }
            // ⚠️ THE FILTER IS FOR THE DEFAULT SWEEP, NOT FOR A ROOT SOMEBODY NAMED.
            // Naming a directory is the statement that its contents are in scope, and
            // refusing to read it then reports "found no Blade templates" over a tree full
            // of them.
            //
            // It was worse than a refusal, because it depended on how the path was SPELLED.
            // The test is `/vendor/` with both separators, so a RELATIVE
            // `vendor/foo/resources/views` slipped through while the same directory written
            // absolutely did not. Measured on one tree: `4 Blade files, clean` one way and
            // `Found no Blade templates` with a non-zero exit the other — the same
            // question, two answers, and the failing one is the spelling a script produces.
            if (
                ! $named && (
                    str_contains($path, '/vendor/') ||
                    str_contains($path, '/node_modules/') ||
                    str_contains($path, '/storage/framework/')
                )
            ) {
                continue;
            }
            $files[] = $path;
        }

        return $files;
    }

    /**
     * Report an unrecognized --fail-on value and return the failure code.
     *
     * A separate method only because a `match` arm cannot hold a statement — the alternative
     * was an inline `tap()`, which reads as cleverness at the exact moment a developer is
     * trying to find out what they typed wrong.
     */
    private function reportUnknownFailOn(string $failOn): int
    {
        $this->error("Unknown --fail-on value '{$failOn}'. Expected one of: ".implode(', ', self::FAIL_ON_LEVELS).'.');

        $hint = SuggestSimilar::format(SuggestSimilar::byLevenshtein($failOn, self::FAIL_ON_LEVELS));
        if ($hint !== null) {
            $this->line('  '.$hint);
        }

        return self::FAILURE;
    }
}
