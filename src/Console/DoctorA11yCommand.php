<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;

/**
 * `wirekit:doctor:a11y` — static-analysis a11y linter for an app's
 * Blade templates.
 *
 * v2.3.0 ships the static scan-only half — no browser. A full
 * axe-core runtime integration is deferred to a later release;
 * static scanning catches the high-value class of issues
 * (icon-only buttons without aria-label, role="dialog" without
 * aria-labelledby, role="img" without alt-text) at a fraction of
 * the runtime cost and works in CI without Playwright.
 *
 * Per-rule findings are categorised as ERROR (likely WCAG AA fail)
 * or WARNING (likely WCAG AA pass but worth review).
 */
class DoctorA11yCommand extends Command
{
    protected $signature = 'wirekit:doctor:a11y
        {path? : Path to scan (defaults to resources/views in the host app)}
        {--fail-on= : Treat findings at this severity or higher as a non-zero exit. One of `error` (default), `warning`, or `none`. Use `warning` in CI to gate on every finding.}';

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

        if ($findings === []) {
            $this->info('✓ No a11y issues found across '.count($bladeFiles).' Blade files.');

            return self::SUCCESS;
        }

        // Group by severity for readable output.
        $errors = array_filter($findings, fn ($f) => $f['severity'] === 'error');
        $warnings = array_filter($findings, fn ($f) => $f['severity'] === 'warning');

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

        return match ($failOn) {
            'error' => $errors !== [] ? self::FAILURE : self::SUCCESS,
            'warning' => ($errors !== [] || $warnings !== []) ? self::FAILURE : self::SUCCESS,
            'none' => self::SUCCESS,
        };
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
