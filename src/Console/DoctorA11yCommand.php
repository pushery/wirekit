<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
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
 *      (focus ring, stateful error / success borders) are hard-checked
 *      at 3:1, while the RESTING DECORATIVE borders (`border`,
 *      `border-strong` on `bg`) are exempt — printed as advisory INFO
 *      with their ratio but never counted toward the verdict or exit
 *      code. This matches docs/theming.md "Intentional trade-offs" and
 *      keeps WireKit's own default palette (intentionally ~1.3-2.5:1
 *      decorative borders) auditing clean.
 */
class DoctorA11yCommand extends Command
{
    protected $signature = 'wirekit:doctor:a11y
        {path? : Path to scan (defaults to resources/views in the host app)}
        {--fail-on= : Treat findings at this severity or higher as a non-zero exit. One of `error` (default), `warning`, or `none`. Use `warning` in CI to gate on every finding.}
        {--theme-contrast : Also audit the active theme tokens for WCAG 2.1 AA contrast against the canonical pairings. Reads resources/css/app.css.}';

    protected $description = 'Static-analysis a11y linter for WireKit components in Blade templates';

    public function handle(): int
    {
        $path = $this->argument('path') ?: base_path('resources/views');

        if (! is_dir($path)) {
            $this->error("Path not found or not a directory: {$path}");

            return self::FAILURE;
        }

        $failOn = (string) ($this->option('fail-on') ?: 'error');
        if (! in_array($failOn, ['error', 'warning', 'none'], true)) {
            $this->error("Invalid --fail-on value: {$failOn}. Allowed: error / warning / none.");

            return self::FAILURE;
        }

        $this->info("Scanning {$path} for a11y issues...");
        $this->line('');

        $bladeFiles = $this->collectBladeFiles($path);
        $findings = [];

        foreach ($bladeFiles as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }
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
            return $this->maybeRunThemeContrast($bladeExit, $failOn);
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

        return $this->maybeRunThemeContrast($bladeExit, $failOn);
    }

    /**
     * Opt-in theme-contrast stage. Runs AFTER the blade scan so the
     * static findings always surface first; if either stage fails,
     * the overall command exits non-zero.
     */
    private function maybeRunThemeContrast(int $bladeExit, string $failOn): int
    {
        $flag = (bool) $this->option('theme-contrast')
            || (string) getenv('WIREKIT_DOCTOR_THEME_CONTRAST') === '1';
        if (! $flag) {
            return $bladeExit;
        }

        $this->line('');
        $themeExit = $this->runThemeContrastAudit($failOn);

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
     */
    private function runThemeContrastAudit(string $failOn): int
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
            ['name' => 'danger-fg on danger', 'fg' => '--color-wk-danger-fg', 'bg' => '--color-wk-danger', 'threshold' => 'text'],
            ['name' => 'success-fg on success', 'fg' => '--color-wk-success-fg', 'bg' => '--color-wk-success', 'threshold' => 'text'],

            // Communicating borders — WCAG 1.4.11 DOES require >= 3:1 for these:
            // a focus ring and the stateful (error / success) borders convey
            // state or mark an active boundary, so they are hard-checked.
            ['name' => 'focus ring on ring-offset', 'fg' => '--color-wk-ring', 'bg' => '--color-wk-ring-offset', 'threshold' => 'ui'],
            ['name' => 'border-error on bg', 'fg' => '--color-wk-border-error', 'bg' => '--color-wk-bg', 'threshold' => 'ui'],
            ['name' => 'border-success on bg', 'fg' => '--color-wk-border-success', 'bg' => '--color-wk-bg', 'threshold' => 'ui'],

            // Decorative resting borders — WCAG 1.4.11 EXEMPTS pure dividers that
            // neither convey state nor identify an active boundary. WireKit ships
            // these intentionally low-contrast (~1.3-2.5:1), matching every major
            // design system; see docs/theming.md "Intentional trade-offs". They are
            // audited for INFORMATION ONLY ('advisory'): the ratio is printed, but
            // the result never counts toward PASS/WARN/FAIL totals and never affects
            // the exit code. Hard-FAILing them contradicted both the docs AND
            // WireKit's own default palette (which sits squarely in the exempt band).
            ['name' => 'border on bg', 'fg' => '--color-wk-border', 'bg' => '--color-wk-bg', 'threshold' => 'ui', 'advisory' => true],
            ['name' => 'border-strong on bg', 'fg' => '--color-wk-border-strong', 'bg' => '--color-wk-bg', 'threshold' => 'ui', 'advisory' => true],
        ];

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
                    $this->line(sprintf('    <fg=gray>SKIP</> %-44s   unsupported color format', $pair['name']));

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

        $extract = function (string $selector) use ($css): array {
            $escaped = preg_quote($selector, '/');
            $pattern = '/(?<![\w-])(?::where\(\s*)?'.$escaped.'(?:\s*\))?\s*\{([^}]*)\}/u';
            preg_match_all($pattern, $css, $matches);
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

        $light = $extract(':root');
        $dark = $extract('.dark');

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
     * Collect every .blade.php file under the given root, excluding
     * vendor / node_modules / storage / cache directories.
     *
     * @return list<string>
     */
    private function collectBladeFiles(string $root): array
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
            if (
                str_contains($path, '/vendor/') ||
                str_contains($path, '/node_modules/') ||
                str_contains($path, '/storage/framework/')
            ) {
                continue;
            }
            $files[] = $path;
        }

        return $files;
    }
}
