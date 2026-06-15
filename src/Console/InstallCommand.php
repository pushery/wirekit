<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\ClassPropsExtractor;
use Pushery\WireKit\Support\PropsParser;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\Support\TailwindVersion;
use Pushery\WireKit\Support\VersionResolver;
use Pushery\WireKit\Theming\ThemePresetRegistry;
use Pushery\WireKit\WireKit;

class InstallCommand extends Command
{
    protected $signature = 'wirekit:install
        {--preset=default : Theme preset — see ThemePresetRegistry::keys() for the canonical list (default, minimal, soft, material, brutalist, retro-terminal, cupertino, aurora at v2.5.0; downstream packages may register additional presets via ThemePresetRegistry::register()).}
        {--font= : Inject sans font-family override (must be a sans-category key from FontRegistry)}
        {--font-serif= : Inject serif font-family override (must be a serif-category key from FontRegistry)}
        {--font-mono= : Inject mono font-family override (must be a mono-category key from FontRegistry)}
        {--apex-license= : Set ApexCharts license tier when opting into the apexcharts adapter — accepts community, commercial, or oem. Sets charts.library => apexcharts AND charts.apex_license => <tier> in config/wirekit.php. ApexCharts is non-MIT; see https://apexcharts.com/license/ for terms.}
        {--interactive : Force interactive prompts even when TTY detection misfires (Herd / Docker / WSL setups)}
        {--no-gitignore : Skip auto-adding /public/vendor/wirekit to .gitignore (commit published assets to repo for environments without vendor:publish in deploy)}
        {--no-strict : Opt out of strict-by-default mode — pre-flight warnings print but do not abort. Use only for legacy CI scripts that depend on the v2.0.0 "warnings as success" behavior.}
        {--force : Bypass pre-flight warnings (token clobber, hand-edited marker blocks). Errors still abort. Mutually exclusive with --no-strict.}
        {--ignore-failed-flags : Per-flag failures report but do NOT abort. Other flags still apply. Exit code is 1 if any flag failed (Laravel FAILURE), never the raw count. Mutually exclusive with default-strict mode (use --no-strict to combine).}
        {--diff : Dry-run mode — report what WOULD change in app.css / tailwind.config.js / layout / config without writing any files. Exit code reflects the would-be-state; nothing on disk is touched.}
        {--rollback : Reverse the most-recent install actions recorded in .wirekit-install.log. Restores file contents to their pre-install state for every tracked entry. Mutually exclusive with every other flag except --no-interaction.}';

    protected $description = 'Install WireKit into your Laravel application';

    public function handle(): int
    {
        // Rollback mode is a separate code path — it doesn't install anything,
        // it reverses recorded prior installs. Combining --rollback with any
        // install-side flag is incoherent (rollback bypasses every other code
        // path), so we reject the combination at boot instead of silently
        // honoring --rollback and ignoring the rest.
        if ($this->option('rollback')) {
            // Boolean flags — only flagged if explicitly enabled.
            // `--no-interaction` is the documented exception (Symfony
            // global flag, semantically compatible with --rollback —
            // rollback doesn't prompt anyway). All other boolean install
            // flags are install-side and incoherent with the rollback
            // path; reject the combination at boot.
            $conflicting = array_values(array_filter(
                ['force', 'no-strict', 'ignore-failed-flags', 'diff', 'interactive'],
                fn ($flag) => (bool) $this->option($flag),
            ));
            // String options — only flagged if non-empty.
            foreach (['font', 'font-serif', 'font-mono', 'apex-license'] as $flag) {
                if (! empty($this->option($flag))) {
                    $conflicting[] = $flag;
                }
            }
            // --preset has a default of 'default'; only conflict on explicit override.
            $preset = $this->option('preset');
            if ($preset !== null && $preset !== 'default') {
                $conflicting[] = 'preset';
            }

            if ($conflicting !== []) {
                $this->error('--rollback is mutually exclusive with every other install flag. Got: --'.implode(', --', $conflicting));
                $this->line('  Run --rollback by itself to reverse the most recent install session.');

                return self::FAILURE;
            }

            return $this->handleRollback();
        }

        // Mutually-exclusive flag validation — flag combinations that don't
        // semantically compose are rejected at boot, NOT silently honored.
        if ($this->option('force') && $this->option('no-strict')) {
            $this->error('--force and --no-strict are mutually exclusive. Pick one — see `wirekit:install --help`.');

            return self::FAILURE;
        }
        if ($this->option('ignore-failed-flags') && ! $this->option('no-strict')) {
            $this->error('--ignore-failed-flags requires --no-strict (default strict mode aborts on the first failed flag). Combine with `--no-strict --ignore-failed-flags`.');

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('diff');
        if ($isDryRun) {
            $this->info('WireKit install — DRY RUN (--diff mode, no files will be modified)');
        } else {
            $this->info('Installing WireKit...');
        }
        $this->line('');

        $this->maybeRunInteractivePrompts();

        // Pre-flight validation — collects EVERY error in one pass so the
        // user sees the full picture, not just the first failure. Errors
        // always abort. Warnings abort under default-strict OR under
        // `--strict` (legacy spelling — kept as alias), proceed under
        // `--no-strict` or `--force`. Decision matrix documented in
        // docs/cli-reference.md.
        [$errors, $warnings] = $this->preflightValidate();

        if ($errors !== []) {
            $this->renderPreflightFindings('Pre-flight ERRORS', $errors, 'red');
            $this->line('');
            $this->error('Install aborted — pre-flight validation found '.count($errors).' error(s).');

            return self::FAILURE;
        }

        if ($warnings !== []) {
            $this->renderPreflightFindings('Pre-flight WARNINGS', $warnings, 'yellow');
            $this->line('');

            $strict = ! $this->option('no-strict');
            $force = (bool) $this->option('force');

            if ($strict && ! $force) {
                $this->error('Install aborted — pre-flight warnings under default-strict mode.');
                $this->line('  Re-run with <fg=cyan>--force</> to bypass, or <fg=cyan>--no-strict</> to treat warnings as advisory.');

                return self::FAILURE;
            }
            if ($force) {
                $this->line('  <fg=yellow>i</> --force set — proceeding despite warnings.');
            } else {
                $this->line('  <fg=blue>i</> --no-strict set — warnings are advisory; proceeding.');
            }
        }

        if ($isDryRun) {
            $this->renderDiffPreview();

            return self::SUCCESS;
        }

        // Begin tracked install. Each side-effecting helper below records
        // its target file path + pre-modification snapshot via
        // trackInstallAction(), and closeInstallLog(failed: false) flushes
        // the accumulated entries to .wirekit-install.log so --rollback
        // can replay the snapshots.
        $this->openInstallLog();

        $this->publishConfig();
        $this->publishAssets();
        $this->addTailwindSource();
        $this->addBladeDirectives();
        if (! $this->option('no-gitignore')) {
            $this->addGitignoreEntry();
        }
        $fontFlagsResult = $this->processFontFlags();
        if ($fontFlagsResult !== self::SUCCESS && ! $this->option('ignore-failed-flags')) {
            $this->error('Install aborted — one or more font flags failed validation.');
            $this->closeInstallLog(failed: true);

            return $fontFlagsResult;
        }
        $this->writeWireKitSchemaJson();

        $preset = $this->option('preset');
        if ($preset !== 'default') {
            // Snapshot app.css before delegating to wirekit:theme so rollback
            // can reverse the preset injection. Dedupe in trackInstallAction
            // means if app.css is already tracked (from addTailwindSource /
            // font-injection earlier), this is a no-op — the FIRST snapshot
            // wins and represents the true pre-install state.
            $appCssForTheme = resource_path('css/app.css');
            if (file_exists($appCssForTheme)) {
                $this->trackInstallAction('apply-theme-preset', $appCssForTheme, (string) file_get_contents($appCssForTheme));
            }
            $themeResult = $this->call('wirekit:theme', ['preset' => $preset]);
            if ($themeResult !== self::SUCCESS && ! $this->option('ignore-failed-flags')) {
                $this->error('Install aborted — theme preset application failed.');
                $this->closeInstallLog(failed: true);

                return $themeResult;
            }
        }

        $apexLicenseResult = $this->processApexLicenseFlag();
        if ($apexLicenseResult !== self::SUCCESS) {
            $this->closeInstallLog(failed: true);

            return $apexLicenseResult;
        }

        $this->line('');
        $verifyResult = $this->call('wirekit:verify');
        if ($verifyResult !== self::SUCCESS && ! $this->option('ignore-failed-flags')) {
            $this->closeInstallLog(failed: true);

            return self::FAILURE;
        }

        $this->closeInstallLog(failed: false);

        $this->line('');
        if ($this->failedFlagCount > 0) {
            $this->components->warn(sprintf(
                'WireKit installed with %d failed flag(s). See output above for details.',
                $this->failedFlagCount
            ));

            return self::FAILURE;
        }

        $this->info('WireKit installed successfully!');

        return self::SUCCESS;
    }

    /** Counter for --ignore-failed-flags reporting. */
    private int $failedFlagCount = 0;

    /** Pending install-log entries, flushed on closeInstallLog(success). */
    private array $pendingInstallLog = [];

    /**
     * Pre-flight validation — collects every actionable error / warning
     * in one pass. Errors always abort; warnings honor strict / force.
     *
     * @return array{0: list<string>, 1: list<string>} [errors, warnings]
     */
    private function preflightValidate(): array
    {
        $errors = [];
        $warnings = [];

        // 0) Tailwind CSS v4+ is a HARD requirement — WireKit's CSS uses the v4
        //    engine (@theme, @source, color-mix(), @property) and cannot run on
        //    v3. Composer can't gate it (Tailwind is an npm dependency, and
        //    WireKit is not an npm package) and `wirekit:doctor` runs only
        //    post-install, so this pre-flight ERROR is the earliest checkpoint —
        //    it hard-aborts before any files are written. Conservative:
        //    TailwindVersion::isPreV4() trips only on positive pre-v4 evidence,
        //    so a valid v4 install is never blocked.
        if (TailwindVersion::isPreV4(base_path())) {
            $detected = TailwindVersion::detectMajor(base_path());
            $errors[] = sprintf(
                "Tailwind CSS v4+ is required — detected %s. WireKit's CSS is built on the Tailwind v4 ".
                "engine (@theme, @source, color-mix(), @property) and cannot run on Tailwind v3.\n".
                "Upgrade first:\n".
                "  npm install tailwindcss@latest @tailwindcss/vite@latest\n".
                'then migrate your CSS to the v4 syntax (https://tailwindcss.com/docs/upgrade-guide) '.
                'and re-run `php artisan wirekit:install`.',
                $detected !== null ? "Tailwind v{$detected}" : 'a pre-v4 Tailwind'
            );
        }

        // 1) Font-key validation (errors). Each --font* must resolve to a
        //    real preset in the correct category.
        foreach (['sans' => 'font', 'serif' => 'font-serif', 'mono' => 'font-mono'] as $category => $optionName) {
            $key = $this->option($optionName);
            if (empty($key)) {
                continue;
            }
            $preset = FontRegistry::get((string) $key);
            if ($preset === null) {
                $available = array_keys(FontRegistry::category($category));
                $errors[] = sprintf(
                    "Unknown font key '%s' for --%s. Available %s fonts: %s",
                    $key,
                    $optionName,
                    $category,
                    implode(', ', $available)
                );

                continue;
            }
            if ($preset->category !== $category) {
                $available = array_keys(FontRegistry::category($category));
                $errors[] = sprintf(
                    "Font '%s' is category '%s', not '%s'. Available %s fonts: %s",
                    $key,
                    $preset->category,
                    $category,
                    $category,
                    implode(', ', $available)
                );
            }
        }

        // 2) Preset validation (error). Reads from ThemePresetRegistry —
        // single source of truth shared with ThemeCommand + ExportApiMap.
        $preset = (string) $this->option('preset');
        $validPresets = ThemePresetRegistry::keys();
        if (! in_array($preset, $validPresets, true)) {
            $message = sprintf(
                "Unknown preset '%s' for --preset. Available: %s",
                $preset,
                implode(', ', $validPresets)
            );
            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein($preset, $validPresets)
            );
            if ($hint !== null) {
                $message .= ' '.$hint;
            }
            $errors[] = $message;
        }

        // 3) Apex-license validation (error).
        $tier = $this->option('apex-license');
        if (! empty($tier)) {
            $allowed = ['community', 'commercial', 'oem'];
            if (! in_array($tier, $allowed, true)) {
                $errors[] = sprintf(
                    "Invalid --apex-license value '%s'. Allowed: %s",
                    $tier,
                    implode(', ', $allowed)
                );
            }
        }

        // 4) Token-clobber scan (warnings) — only when a --font* flag is set.
        foreach (['sans' => 'font', 'serif' => 'font-serif', 'mono' => 'font-mono'] as $category => $optionName) {
            $key = $this->option($optionName);
            if (empty($key)) {
                continue;
            }
            $clobber = $this->scanForExternalTokenOverrides($category);
            if ($clobber !== null) {
                $warnings[] = sprintf(
                    "--font-wk-%s already overridden in %s (current value: %s, line %d).\n  This injection (--%s=%s) would supersede it.",
                    $category,
                    $clobber['file'],
                    $clobber['value'],
                    $clobber['line'],
                    $optionName,
                    $key
                );
            }
        }

        // 5) @import-aware advisory (warning) — only when --font* is set
        //    AND the app.css uses @import to a NON-tailwindcss source.
        //    Token-clobber scan only looks at app.css; transitive imports
        //    are out of scope (tracked as a future enhancement). The
        //    advisory is scoped narrowly so the canonical
        //    `@import 'tailwindcss';` doesn't false-trigger.
        $appCss = resource_path('css/app.css');
        if (file_exists($appCss)) {
            $hasFontFlag = ! empty($this->option('font'))
                || ! empty($this->option('font-serif'))
                || ! empty($this->option('font-mono'));
            $contents = (string) file_get_contents($appCss);
            if ($hasFontFlag && preg_match_all('/^\s*@import\s+["\']([^"\']+)["\']/m', $contents, $imports)) {
                $nonStandard = array_filter($imports[1], fn (string $target) => $target !== 'tailwindcss' && ! str_starts_with($target, 'tailwindcss/'));
                if ($nonStandard !== []) {
                    $warnings[] = sprintf(
                        "resources/css/app.css uses @import to non-standard source(s): %s\n  The token-clobber scan cannot follow transitive imports. Verify that no imported CSS declares --font-wk-{sans,serif,mono} before --force-ing or proceeding under --no-strict.",
                        implode(', ', $nonStandard)
                    );
                }
            }
        }

        return [$errors, $warnings];
    }

    /**
     * Render preflight findings (errors or warnings) with a uniform shape.
     *
     * @param  list<string>  $findings
     */
    private function renderPreflightFindings(string $title, array $findings, string $color): void
    {
        $this->line("  <fg={$color};options=bold>{$title}:</>");
        foreach ($findings as $finding) {
            $lines = explode("\n", $finding);
            $first = array_shift($lines);
            $this->line("    <fg={$color}>•</> {$first}");
            foreach ($lines as $line) {
                $this->line("      {$line}");
            }
        }
    }

    /**
     * Scan resources/css/app.css for `--font-wk-{category}:` declarations
     * OUTSIDE the wirekit:font-{category}:start/end marker pair. Returns
     * the first match with line + value + file path, or null when clean.
     *
     * @return array{file: string, line: int, value: string}|null
     */
    private function scanForExternalTokenOverrides(string $category): ?array
    {
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            return null;
        }
        $contents = (string) file_get_contents($appCss);
        if ($contents === '') {
            return null;
        }

        // Strip the wirekit marker block from consideration — the scan
        // looks at USER-OWNED CSS only. Same scrubbing on /* … */ block
        // comments so a commented-out declaration doesn't false-trigger.
        $startMarker = "/* wirekit:font-{$category}:start */";
        $endMarker = "/* wirekit:font-{$category}:end */";
        $markerPattern = '/'.preg_quote($startMarker, '/').'.*?'.preg_quote($endMarker, '/').'/su';
        $scrubbed = preg_replace($markerPattern, str_repeat("\n", 5), $contents) ?? $contents;
        $scrubbed = preg_replace('~/\*(?!\s*wirekit:).*?\*/~s', '', $scrubbed) ?? $scrubbed;

        // Locate any `--font-wk-{category}: value` declaration.
        if (! preg_match('/--font-wk-'.preg_quote($category, '/').'\s*:\s*([^;]+);/u', $scrubbed, $match, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $offset = $match[0][1];
        $line = substr_count(substr($scrubbed, 0, $offset), "\n") + 1;
        $value = trim($match[1][0]);

        return [
            'file' => 'resources/css/app.css',
            'line' => $line,
            'value' => $value,
        ];
    }

    /** Open the install log session (clears pending buffer). */
    private function openInstallLog(): void
    {
        $this->pendingInstallLog = [];
        $this->failedFlagCount = 0;
    }

    /**
     * Append a tracked file-mutation to the pending install log. Flushed
     * on closeInstallLog(failed: false). Each entry captures the file
     * path + the BEFORE content snapshot so rollback can restore.
     *
     * Dedupe rule: only the FIRST track call per file in this session
     * is recorded — the before-snapshot it captured is the canonical
     * pre-install state. Subsequent modifications of the same file
     * (e.g. app.css edited by addTailwindSource, then injectFontOverrides,
     * then wirekit:theme) are no-ops here, so rollback always restores
     * to the true pre-install state in a single pass.
     */
    private function trackInstallAction(string $action, string $filePath, ?string $beforeContent): void
    {
        $relativeFile = str_replace(base_path().'/', '', $filePath);
        foreach ($this->pendingInstallLog as $existing) {
            if ($existing['file'] === $relativeFile) {
                return;
            }
        }
        // Binary-safe snapshot: text files (app.css, layout, .gitignore,
        // schema.json, tailwind.config.js) keep readable content in the
        // log so developers can eyeball it; binary files (published JS / CSS
        // bundles in public/vendor/wirekit/) get base64-encoded so
        // json_encode doesn't choke on non-UTF-8 bytes.
        $isBinary = $beforeContent !== null && ! mb_check_encoding($beforeContent, 'UTF-8');
        $this->pendingInstallLog[] = [
            'timestamp' => date('c'),
            'action' => $action,
            'file' => $relativeFile,
            'is_binary' => $isBinary,
            'before_snapshot' => match (true) {
                $beforeContent === null => null,
                $isBinary => base64_encode($beforeContent),
                default => $beforeContent,
            },
        ];
    }

    /**
     * Finalize the install log. Successful installs flush the pending
     * entries to base_path('.wirekit-install.log') as a JSON-Lines file
     * (one entry per line, append-only). Failed installs discard the
     * pending entries — they reflect partial state that rollback
     * wouldn't know how to reason about.
     */
    private function closeInstallLog(bool $failed): void
    {
        if ($failed || $this->pendingInstallLog === []) {
            $this->pendingInstallLog = [];

            return;
        }

        $logPath = base_path('.wirekit-install.log');
        $sessionId = uniqid('install-', true);
        $sessionHeader = ['session_id' => $sessionId, 'started_at' => date('c'), 'actions' => $this->pendingInstallLog];

        $lines = [];
        $lines[] = json_encode($sessionHeader, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            File::append($logPath, implode("\n", $lines)."\n");
            $this->line('  <fg=gray>i</> Install actions recorded in .wirekit-install.log (use `wirekit:install --rollback` to reverse).</>');
        } catch (\Throwable $e) {
            // Log-write is non-fatal — installs still succeed.
        }
        $this->pendingInstallLog = [];
    }

    /**
     * Reverse the most-recent install session by replaying its
     * before_snapshot for every tracked file. Errors during rollback are
     * non-fatal per-file — we report what we couldn't restore and
     * continue.
     */
    private function handleRollback(): int
    {
        $logPath = base_path('.wirekit-install.log');
        if (! file_exists($logPath)) {
            $this->error('No .wirekit-install.log found at the developer project root.');
            $this->line('  Rollback requires a prior `wirekit:install` to have recorded actions.');

            return self::FAILURE;
        }

        $lines = array_filter(explode("\n", (string) file_get_contents($logPath)));
        if ($lines === []) {
            $this->error('.wirekit-install.log is empty — nothing to roll back.');

            return self::FAILURE;
        }

        $lastSession = json_decode(end($lines), true);
        if (! is_array($lastSession) || ! isset($lastSession['actions'])) {
            $this->error('Malformed .wirekit-install.log session — cannot parse last session.');

            return self::FAILURE;
        }

        $this->info(sprintf('Rolling back session %s (%d action(s))…', $lastSession['session_id'] ?? '?', count($lastSession['actions'])));
        $this->line('');

        $restored = 0;
        $failed = 0;
        foreach ($lastSession['actions'] as $action) {
            $absolutePath = base_path($action['file']);
            try {
                if ($action['before_snapshot'] === null) {
                    // File didn't exist before — remove if it exists now.
                    if (file_exists($absolutePath)) {
                        File::delete($absolutePath);
                    }
                } else {
                    $content = ($action['is_binary'] ?? false)
                        ? base64_decode($action['before_snapshot'], true)
                        : $action['before_snapshot'];
                    if ($content === false) {
                        throw new \RuntimeException('base64 decode failed');
                    }
                    File::put($absolutePath, $content);
                }
                $this->line('  <fg=green>✓</> Restored '.$action['file']);
                $restored++;
            } catch (\Throwable $e) {
                $this->line('  <fg=red>✗</> Failed to restore '.$action['file'].': '.$e->getMessage());
                $failed++;
            }
        }

        $this->line('');
        if ($failed === 0) {
            $this->info(sprintf('Rollback complete — %d file(s) restored.', $restored));

            return self::SUCCESS;
        }
        $this->components->warn(sprintf('Rollback partial — %d restored, %d failed.', $restored, $failed));

        return self::FAILURE;
    }

    /**
     * Render the dry-run diff preview: what WOULD change in each
     * touched file under the current flag combination. Read-only — no
     * filesystem writes happen.
     */
    private function renderDiffPreview(): void
    {
        $this->line('  Files that WOULD change:');
        $this->line('');

        // config/wirekit.php — published if missing.
        $configPath = config_path('wirekit.php');
        if (! file_exists($configPath)) {
            $this->line('    <fg=green>+</> '.str_replace(base_path().'/', '', $configPath).' (would publish)');
        } else {
            $this->line('    <fg=gray>·</> '.str_replace(base_path().'/', '', $configPath).' (already exists — would skip)');
        }

        // resources/css/app.css — @source + (optionally) font-block.
        $appCss = resource_path('css/app.css');
        if (! file_exists($appCss)) {
            $this->line('    <fg=yellow>!</> '.str_replace(base_path().'/', '', $appCss).' (does NOT exist — install would emit a manual-action hint)');
        } else {
            $contents = (string) file_get_contents($appCss);
            $needsSource = ! (str_contains($contents, 'wirekit') && str_contains($contents, '@source'));
            if ($needsSource) {
                $this->line('    <fg=cyan>~</> resources/css/app.css (would inject @source directive)');
            }
            foreach (['sans' => 'font', 'serif' => 'font-serif', 'mono' => 'font-mono'] as $cat => $opt) {
                if (! empty($this->option($opt))) {
                    $this->line('    <fg=cyan>~</> resources/css/app.css (would inject --font-wk-'.$cat.' = '.$this->option($opt).' marker block)');
                }
            }
        }

        // Theme block.
        $preset = $this->option('preset');
        if ($preset !== 'default') {
            $this->line('    <fg=cyan>~</> resources/css/app.css (would inject theme preset '.$preset.')');
        }

        // Layout file — Blade directives. Report the path the REAL install would
        // resolve (the first existing candidate), or, if none exist yet, the full
        // candidate list it probes — so the dry-run never names a narrower path
        // than the actual install touches.
        $layout = $this->resolveLayoutFile();
        if ($layout !== null) {
            $rel = str_replace(base_path().DIRECTORY_SEPARATOR, '', $layout);
            $this->line("    <fg=cyan>~</> {$rel} (would inject @wirekitStyles + @wirekitScripts if missing)");
        } else {
            $candidates = implode(', ', array_map(
                fn (string $p): string => str_replace(base_path().DIRECTORY_SEPARATOR, '', $p),
                $this->layoutCandidates()
            ));
            $this->line("    <fg=cyan>~</> layout: none of the probed candidates exist yet ({$candidates}) — add @wirekitStyles/@wirekitScripts manually");
        }

        // public/vendor/wirekit/ — assets.
        $this->line('    <fg=cyan>~</> public/vendor/wirekit/* (would publish CSS + JS bundles)');

        // .wirekit-schema.json — schema feeder.
        $this->line('    <fg=cyan>~</> .wirekit-schema.json (would generate IDE-extension schema feeder)');

        // .gitignore — vendor/wirekit entry.
        if (! $this->option('no-gitignore')) {
            $this->line('    <fg=cyan>~</> .gitignore (would append /public/vendor/wirekit)');
        }

        $this->line('');
        $this->info('Dry-run complete. No files modified. Re-run without --diff to apply.');
    }

    /**
     * Interactive prompt mode for `wirekit:install`.
     *
     * Fires when:
     *   1. No flags are passed (preset is default, all --font* options empty), AND
     *   2. The command is running in an interactive TTY ($this->input->isInteractive()).
     *
     * In CI / --no-interaction / scripted contexts, this method is a no-op and
     * the command runs with v1.5.0-identical defaults. Beginners running
     * `php artisan wirekit:install` interactively get a guided choose-your-
     * preset / choose-your-fonts flow.
     *
     * Selected values are written back into the option array so the rest of
     * `handle()` picks them up via `$this->option(...)` as if they had been
     * passed as flags.
     */
    private function maybeRunInteractivePrompts(): void
    {
        // Skip when any flag was passed — explicit invocation wins over prompts.
        $hasFlags = $this->option('preset') !== 'default'
            || ! empty($this->option('font'))
            || ! empty($this->option('font-serif'))
            || ! empty($this->option('font-mono'));

        if ($hasFlags) {
            return;
        }

        // The user can force interactive prompts even when Symfony's TTY
        // detection misfires (common in Herd / Docker / WSL setups where the
        // terminal stream goes through a wrapper that fails posix_isatty()).
        // `--interactive` overrides every skip condition below.
        $forceInteractive = (bool) $this->option('interactive');

        // Skip in non-interactive contexts (CI, --no-interaction, piped scripts)
        // unless --interactive is set.
        if (! $forceInteractive && ! $this->input->isInteractive()) {
            // Hint the developer that prompts are available — without this line
            // a user on Herd / Docker / WSL whose TTY detection misfires would
            // see no prompts and assume "interactive mode is broken". The flag
            // is the documented escape hatch for exactly this case. Suppress
            // the hint when --no-interaction was passed explicitly (the user
            // signaled they want a quiet scripted run).
            if (! $this->option('no-interaction')) {
                $this->line('  <fg=blue>i</> Interactive prompts skipped (no TTY detected). Re-run with <fg=cyan>--interactive</> to force the guided setup, or pass flags directly (e.g. <fg=cyan>--preset=cupertino</>).');
            }

            return;
        }

        // Skip in unit tests — Laravel's artisan() test runner considers itself
        // interactive but doesn't forward real stdin. Without this guard, every
        // existing test that doesn't explicitly mock the prompts would fail.
        // Honored even with --interactive — tests must pass flag values explicitly.
        if (app()->runningUnitTests()) {
            return;
        }

        $this->line('  <fg=blue>i</> Interactive setup — press Enter at any prompt to skip.');
        $this->line('');

        $preset = $this->choice(
            'Theme preset',
            ThemePresetRegistry::keys(),
            'default'
        );
        if ($preset !== 'default') {
            $this->input->setOption('preset', $preset);
        }

        // Sans font — top-5 popular keys + skip
        $sansChoice = $this->choice(
            'Sans-serif font (skip = use bundled defaults)',
            ['skip', 'inter', 'roboto', 'open-sans', 'lato', 'montserrat'],
            'skip'
        );
        if ($sansChoice !== 'skip') {
            $this->input->setOption('font', $sansChoice);
        }

        $serifChoice = $this->choice(
            'Serif font (optional)',
            ['skip', 'lora', 'playfair-display', 'merriweather'],
            'skip'
        );
        if ($serifChoice !== 'skip') {
            $this->input->setOption('font-serif', $serifChoice);
        }

        $monoChoice = $this->choice(
            'Monospace font (optional)',
            ['skip', 'jetbrains-mono', 'fira-code', 'source-code-pro'],
            'skip'
        );
        if ($monoChoice !== 'skip') {
            $this->input->setOption('font-mono', $monoChoice);
        }

        $this->line('');
    }

    /**
     * Routes each `--font*` flag through the font-override injector.
     *
     * Centralized here so future multi-font flags (`--font-serif`,
     * `--font-mono`) can be added by extending the categories array.
     */
    private function processApexLicenseFlag(): int
    {
        $tier = $this->option('apex-license');
        if ($tier === null || $tier === '') {
            return self::SUCCESS;
        }

        $allowedTiers = ['community', 'commercial', 'oem'];
        if (! in_array($tier, $allowedTiers, true)) {
            $this->error(sprintf(
                'Invalid --apex-license value: "%s". Allowed values: %s.',
                $tier,
                implode(', ', $allowedTiers),
            ));

            return self::FAILURE;
        }

        // Echo the License Notice once before mutating config/wirekit.php so
        // every developer who picks an apexcharts tier sees it AT LEAST ONCE.
        // The notice is also rendered on docs/components/chart.md and emitted
        // by wirekit:doctor; see Decision Log row "License-acceptance flag".
        $this->line('');
        $this->warn('ApexCharts License Notice');
        $this->line('  ApexCharts is not MIT-licensed.');
        $this->line('  - Community License (free): personal use, non-profits, education,');
        $this->line('    AND organizations under $2 million USD annual revenue.');
        $this->line('  - Commercial License (paid): organizations at or above the threshold.');
        $this->line('  - OEM/Redistribution License (paid, separate): when embedding into a');
        $this->line('    redistributed product. NOT applicable to a normal WireKit developer install.');
        $this->line('');
        $this->line('  WireKit ships only the adapter glue (MIT). The ApexCharts JS library');
        $this->line('  is your responsibility to install + license. See:');
        $this->line('    https://apexcharts.com/license/');
        $this->line('');
        $this->line(sprintf('  You declared tier: %s', $tier));
        $this->line('');

        // Persist into config/wirekit.php — load existing config, merge,
        // write back.
        $this->writeApexConfig($tier);

        $this->info(sprintf('Set charts.library => apexcharts and charts.apex_license => %s', $tier));

        return self::SUCCESS;
    }

    /**
     * Persist charts.library => 'apexcharts' AND charts.apex_license => <tier>
     * to the developer's config/wirekit.php. Falls back to a console hint when
     * the file is unwritable (e.g. read-only deploy).
     */
    private function writeApexConfig(string $tier): void
    {
        $configPath = config_path('wirekit.php');
        if (! file_exists($configPath)) {
            $this->warn(sprintf(
                'config/wirekit.php not found — please add manually: '
                ."'charts' => ['library' => 'apexcharts', 'apex_license' => '%s'],",
                $tier,
            ));

            return;
        }

        $contents = (string) file_get_contents($configPath);

        // Replace the existing 'library' => '<value>' line within the charts
        // block with apexcharts. Tolerates either single- or double-quoted
        // values; the captured prefix preserves indentation.
        $replaced = preg_replace(
            "/('library'\s*=>\s*)('[^']*'|\"[^\"]*\"|null)/u",
            "$1'apexcharts'",
            $contents,
            1,
        ) ?? $contents;

        // Add or replace 'apex_license' => '<tier>' inside the charts block.
        if (preg_match("/'apex_license'\s*=>/u", $replaced)) {
            $replaced = preg_replace(
                "/('apex_license'\s*=>\s*)('[^']*'|\"[^\"]*\"|null)/u",
                sprintf("$1'%s'", $tier),
                $replaced,
                1,
            );
        } else {
            // Insert apex_license alongside library — match the quote style
            // and indentation of the line we just edited.
            $replaced = preg_replace(
                "/('library'\s*=>\s*'apexcharts',?)/u",
                "$1\n        'apex_license' => '".$tier."',",
                $replaced,
                1,
            );
        }

        @file_put_contents($configPath, $replaced);
    }

    /**
     * Apply font overrides for each --font* flag set. Returns the count
     * of failures encoded as a Symfony exit code: 0 on clean run,
     * self::FAILURE on any per-flag exception. Pre-flight validation
     * catches unknown keys / wrong categories BEFORE this method runs,
     * so any exception here represents a runtime-side failure (file-
     * system, CSS-shape mismatch, etc.) rather than a typo.
     */
    private function processFontFlags(): int
    {
        $categories = [
            'sans' => 'font',
            'serif' => 'font-serif',
            'mono' => 'font-mono',
        ];

        $hadFailure = false;
        foreach ($categories as $category => $optionName) {
            $key = $this->option($optionName);
            if ($key === null || $key === '') {
                continue;
            }

            try {
                $this->injectFontOverrides($category, (string) $key);
                $this->publishFontAssets();
            } catch (InvalidArgumentException $e) {
                $this->line('  <fg=red>✗</> '.$e->getMessage());
                $hadFailure = true;
                $this->failedFlagCount++;
            }
        }

        return $hadFailure ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolves the font key, validates the category, and idempotently writes
     * a marker-pair-bracketed @theme + :root override block into app.css.
     *
     * Marker shape: `/* wirekit:font-{category}:start *\/` … `:end *\/`
     * Re-running with the same key produces byte-identical output; re-running
     * with a different key swaps the bracketed content.
     *
     * Throws on unknown key or wrong category.
     */
    private function injectFontOverrides(string $category, string $fontKey): void
    {
        $preset = FontRegistry::get($fontKey);

        if ($preset === null) {
            $available = array_keys(FontRegistry::category($category));

            throw new InvalidArgumentException(
                "Unknown font key '{$fontKey}'. Available {$category} fonts: ".implode(', ', $available)
            );
        }

        if ($preset->category !== $category) {
            $available = array_keys(FontRegistry::category($category));

            throw new InvalidArgumentException(
                "Font '{$fontKey}' is category '{$preset->category}', not '{$category}'. Available {$category} fonts: ".implode(', ', $available)
            );
        }

        $shape = $this->detectTailwindConfigShape();

        match ($shape) {
            'css-first', 'both' => $this->injectFontOverridesCssFirst($category, $preset, $shape === 'both'),
            'js-config' => $this->injectFontOverridesJsConfig($category, $preset),
            'none' => $this->line("  <fg=yellow>!</> Neither resources/css/app.css nor tailwind.config.js found — skipping --font-{$category}={$fontKey} injection"),
        };
    }

    /**
     * Detects which Tailwind config shape the developer uses.
     *
     * Priority on tie: CSS-first (Tailwind v4 default) wins over JS-config.
     * Tailwind v4 deprecates the JS config; we lean into the recommended path.
     *
     * @return 'css-first'|'js-config'|'both'|'none'
     */
    private function detectTailwindConfigShape(): string
    {
        $appCss = resource_path('css/app.css');
        $jsConfig = base_path('tailwind.config.js');

        $cssFirst = file_exists($appCss) && str_contains((string) file_get_contents($appCss), '@theme');
        $jsConfigPresent = file_exists($jsConfig);

        return match (true) {
            $cssFirst && $jsConfigPresent => 'both',
            $cssFirst => 'css-first',
            $jsConfigPresent => 'js-config',
            file_exists($appCss) => 'css-first', // app.css exists but no @theme yet — still write CSS-first
            default => 'none',
        };
    }

    /**
     * CSS-first injection — writes @theme + :root override block into app.css.
     */
    private function injectFontOverridesCssFirst(string $category, $preset, bool $isBoth = false): void
    {
        $appCss = resource_path('css/app.css');
        $content = (string) file_get_contents($appCss);
        $block = $this->buildFontOverrideBlock($category, $preset->fontFamily());

        $startMarker = "/* wirekit:font-{$category}:start */";
        $endMarker = "/* wirekit:font-{$category}:end */";

        $pattern = '/'.preg_quote($startMarker, '/').'.*?'.preg_quote($endMarker, '/').'/su';

        if (preg_match($pattern, $content) === 1) {
            $newContent = preg_replace($pattern, $block, $content, 1);
        } else {
            $newContent = rtrim($content)."\n\n".$block."\n";
        }

        if ($newContent === $content) {
            $this->line("  <fg=yellow>!</> Font {$category}={$preset->key} already injected — no change");

            return;
        }

        $this->trackInstallAction("inject-font-{$category}", $appCss, $content);
        File::put($appCss, $newContent);

        if ($isBoth) {
            $this->line('  <fg=blue>i</> Both app.css @theme and tailwind.config.js detected — writing CSS-first per Tailwind v4 recommendation');
        }

        $this->line("  <fg=green>✓</> Injected --font-{$category} + --font-wk-{$category} = {$preset->family} into app.css");
    }

    /**
     * JS-config injection — writes into tailwind.config.js theme.extend.fontFamily.{cat}.
     *
     * Anchored regex match against the well-defined Tailwind v3 config shape.
     * On shape mismatch (custom config layout, comments interleaved, etc.),
     * logs an actionable skip message rather than risking AST corruption.
     */
    private function injectFontOverridesJsConfig(string $category, $preset): void
    {
        $jsConfig = base_path('tailwind.config.js');
        $content = (string) file_get_contents($jsConfig);
        $family = $preset->fontFamily();
        // Tailwind expects an array literal — split fallbacks into JS array form
        $jsArray = $this->cssFontFamilyToJsArray($family);

        // Match theme.extend.fontFamily.{cat}: [...] inside the config object
        $catKey = preg_quote($category, '/');
        $existingPattern = '/(theme\s*:\s*\{[^}]*extend\s*:\s*\{[^}]*fontFamily\s*:\s*\{[^}]*)'.$catKey.'\s*:\s*\[[^\]]*\]/su';

        if (preg_match($existingPattern, $content) === 1) {
            $newContent = preg_replace(
                $existingPattern,
                '$1'.$category.': '.$jsArray,
                $content,
                1
            );
            $this->trackInstallAction("inject-font-{$category}-js", $jsConfig, $content);
            File::put($jsConfig, $newContent);
            $this->line("  <fg=green>✓</> Updated tailwind.config.js theme.extend.fontFamily.{$category} = {$preset->family}");

            return;
        }

        // Insert into existing fontFamily block (no key for this category yet)
        $extendPattern = '/(fontFamily\s*:\s*\{)([^}]*)(\})/su';
        if (preg_match($extendPattern, $content) === 1) {
            $newContent = preg_replace(
                $extendPattern,
                '$1$2        '.$category.': '.$jsArray.",\n      \$3",
                $content,
                1
            );
            $this->trackInstallAction("inject-font-{$category}-js", $jsConfig, $content);
            File::put($jsConfig, $newContent);
            $this->line("  <fg=green>✓</> Added {$category} to tailwind.config.js theme.extend.fontFamily");

            return;
        }

        $this->line("  <fg=yellow>!</> tailwind.config.js shape mismatch — couldn't locate theme.extend.fontFamily. Add manually: {$category}: {$jsArray}");
    }

    /**
     * Converts a CSS font-family value (`'Inter', ui-sans-serif, system-ui`) to a JS array literal.
     */
    private function cssFontFamilyToJsArray(string $cssFontFamily): string
    {
        $parts = array_map('trim', explode(',', $cssFontFamily));
        $quoted = array_map(fn ($p) => str_starts_with($p, "'") ? '"'.trim($p, "'").'"' : '"'.$p.'"', $parts);

        return '['.implode(', ', $quoted).']';
    }

    /**
     * Builds the marker-bracketed @theme + :root override block.
     *
     * Two CSS variables are written:
     *   --font-{category}     → drives Tailwind utilities (font-sans, etc.)
     *   --font-wk-{category}  → drives WireKit chrome
     *
     * Setting both ensures Tailwind utilities and WireKit components stay
     * visually aligned, preventing the footgun where WireKit chrome renders
     * in a different font than the body copy.
     */
    private function buildFontOverrideBlock(string $category, string $fontFamily): string
    {
        return <<<CSS
/* wirekit:font-{$category}:start */
@theme {
    --font-{$category}: {$fontFamily};
}

@layer base {
    :root {
        --font-wk-{$category}: {$fontFamily};
    }
}
/* wirekit:font-{$category}:end */
CSS;
    }

    /**
     * Triggers the existing `vendor:publish --tag=wirekit-fonts` flow so the
     * resolved preset's local CSS file lands in `public/vendor/wirekit/fonts/`.
     *
     * Non-overwriting (`--force=false`) — re-running won't clobber developer
     * customizations. Idempotent: silently skips already-published files.
     */
    private function publishFontAssets(): void
    {
        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-fonts']);
    }

    private function publishConfig(): void
    {
        $configPath = config_path('wirekit.php');
        if (file_exists($configPath)) {
            $this->line('  <fg=yellow>!</> config/wirekit.php already exists — skipping');

            return;
        }

        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-config']);
        if (file_exists($configPath)) {
            $this->trackInstallAction('publish-config', $configPath, null);
        }
        $this->line('  <fg=green>✓</> Published config/wirekit.php');
    }

    private function publishAssets(): void
    {
        // Snapshot the published-assets directory BEFORE publish so rollback
        // can remove files that didn't exist before AND restore files that
        // were overwritten. Re-runs of wirekit:install always force-overwrite,
        // so an upgrade flow needs the before-snapshot too — not just the
        // first-install "delete if absent before" semantic.
        $assetsDir = public_path('vendor/wirekit');
        $assetsBefore = [];
        if (is_dir($assetsDir)) {
            foreach (File::allFiles($assetsDir) as $existing) {
                $assetsBefore[$existing->getPathname()] = (string) file_get_contents($existing->getPathname());
            }
        }

        $this->callSilently('vendor:publish', ['--tag' => 'wirekit-assets', '--force' => true]);

        if (is_dir($assetsDir)) {
            foreach (File::allFiles($assetsDir) as $published) {
                $path = $published->getPathname();
                $before = $assetsBefore[$path] ?? null;
                $this->trackInstallAction('publish-asset', $path, $before);
            }
        }

        $this->line('  <fg=green>✓</> Published assets to public/vendor/wirekit/');
    }

    private function addTailwindSource(): void
    {
        $appCss = resource_path('css/app.css');

        if (! file_exists($appCss)) {
            $this->line('  <fg=yellow>!</> resources/css/app.css not found — add @source manually');

            return;
        }

        $contentBefore = (string) file_get_contents($appCss);

        if (str_contains($contentBefore, 'wirekit') && str_contains($contentBefore, '@source')) {
            $this->line('  <fg=yellow>!</> Tailwind @source already configured — skipping');

            return;
        }

        $sourceLine = "@source '../../vendor/pushery/wirekit/resources/views/**/*.blade.php';";

        // Insert after @import 'tailwindcss' if present, otherwise append
        if (str_contains($contentBefore, "@import 'tailwindcss'") || str_contains($contentBefore, '@import "tailwindcss"')) {
            $content = preg_replace(
                '/(@import\s+[\'"]tailwindcss[\'"];?)/u',
                "$1\n{$sourceLine}",
                $contentBefore,
                1
            );
        } else {
            $content = $contentBefore."\n{$sourceLine}\n";
        }

        $this->trackInstallAction('add-tailwind-source', $appCss, $contentBefore);
        File::put($appCss, $content);
        $this->line('  <fg=green>✓</> Added @source for WireKit to app.css');
    }

    /**
     * The layout files the install probes, in priority order. Single source of
     * truth shared by the real install (addBladeDirectives) and the --diff
     * dry-run, so the dry-run can never name a different path than the install
     * actually resolves.
     *
     * @return list<string>
     */
    private function layoutCandidates(): array
    {
        return [
            resource_path('views/components/layouts/app.blade.php'),
            resource_path('views/layouts/app.blade.php'),
            resource_path('views/components/layout.blade.php'),
        ];
    }

    /**
     * The first existing layout candidate (the one the install would edit), or
     * null when none exist yet.
     */
    private function resolveLayoutFile(): ?string
    {
        foreach ($this->layoutCandidates() as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function addBladeDirectives(): void
    {
        $layoutFile = $this->resolveLayoutFile();

        if (! $layoutFile) {
            $this->line('  <fg=yellow>!</> No layout file found — add @wirekitStyles/@wirekitScripts manually');

            return;
        }

        $contentBefore = (string) file_get_contents($layoutFile);
        $content = $contentBefore;
        $modified = false;

        if (! str_contains($content, '@wirekitStyles')) {
            // Add before </head> or @vite
            if (str_contains($content, '</head>')) {
                $content = str_replace('</head>', "    @wirekitStyles\n</head>", $content);
                $modified = true;
            }
        }

        if (! str_contains($content, '@wirekitScripts')) {
            // Add before </body>
            if (str_contains($content, '</body>')) {
                $content = str_replace('</body>', "    @wirekitScripts\n</body>", $content);
                $modified = true;
            }
        }

        if ($modified) {
            $this->trackInstallAction('add-blade-directives', $layoutFile, $contentBefore);
            File::put($layoutFile, $content);
            $this->line('  <fg=green>✓</> Added Blade directives to '.basename($layoutFile));
        } else {
            $this->line('  <fg=yellow>!</> Blade directives already present in layout');
        }
    }

    private function addGitignoreEntry(): void
    {
        $gitignorePath = base_path('.gitignore');

        if (! file_exists($gitignorePath)) {
            return;
        }

        $contentBefore = (string) file_get_contents($gitignorePath);

        if (str_contains($contentBefore, 'vendor/wirekit')) {
            return;
        }

        $this->trackInstallAction('add-gitignore-entry', $gitignorePath, $contentBefore);
        File::append($gitignorePath, "\n/public/vendor/wirekit\n");
        $this->line('  <fg=green>✓</> Added public/vendor/wirekit to .gitignore');
        $this->line('    <fg=gray>Published assets are auto-rebuilt on deploy via the route fallback —</>');
        $this->line('    <fg=gray>no manual vendor:publish step required in your deploy script.</>');
        $this->line('    <fg=gray>Use --no-gitignore on install if you prefer committing public/vendor/wirekit/.</>');
    }

    /**
     * Writes `.wirekit-schema.json` at the developer project root.
     *
     * The schema is the same JSON manifest emitted by `wirekit:export-json`
     * — every component's name, tag, category, description, full prop
     * records (with default expressions + inline comments), and slot
     * names. Drop-in for IDE extensions, AI tooling, and editor
     * autocomplete plugins that want a single zero-configuration
     * entry-point for "what does WireKit offer?".
     *
     * Re-running `wirekit:install` regenerates the file (the manifest
     * changes with every package upgrade). Developers can either commit
     * the file (stable autocomplete across CI / teammates) or gitignore
     * it (re-generated on every install). The default is to NOT
     * gitignore — most IDE-extension authors expect the file to be
     * checked in.
     *
     * Safe to fail silently: the file is a discoverability enhancement,
     * not a runtime dependency. Install completes successfully even if
     * the schema write fails (e.g. read-only filesystem in some deploy
     * scenarios).
     */
    private function writeWireKitSchemaJson(): void
    {
        $targetPath = base_path('.wirekit-schema.json');

        try {
            // Build the schema directly via the registry + parser helpers.
            // Same output shape as `wirekit:export-json --pretty`, single
            // source of truth, no duplicated work — invoking the artisan
            // command separately would walk the same registry twice.
            $components = [];
            foreach (ComponentRegistry::all() as $name => $meta) {
                $bladePath = $this->resolveBladePathForSchema($name);
                // Single source of truth — handles both anonymous-Blade
                // (PropsParser) and class-based (ClassPropsExtractor)
                // components uniformly.
                $props = ComponentRegistry::extractProps($name);
                $componentClass = ComponentRegistry::componentClass($name);
                $classPublicProps = $componentClass !== null
                    ? ClassPropsExtractor::publicPropertyNames($componentClass)
                    : [];
                $slots = $bladePath !== null
                    ? BladeParser::extractSlotsWithMetadataFromSource(
                        (string) file_get_contents($bladePath),
                        $bladePath,
                        $classPublicProps
                    )
                    : [];
                $subComponents = $this->discoverSubComponentsForSchema($name);
                $components[] = [
                    'name' => $name,
                    'tag' => ComponentRegistry::tag($name),
                    'category' => $meta['category'],
                    'description' => $meta['description'],
                    'docs_url' => WireKit::DOCS_URL."/components/{$name}",
                    'props' => $props,
                    'slots' => $slots,
                    'sub_components' => $subComponents,
                ];
            }

            $document = [
                'version' => VersionResolver::resolve(),
                'generated_at' => date('c'),
                'components' => $components,
            ];

            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_PRETTY_PRINT;
            $json = json_encode($document, $flags);
            if ($json === false) {
                $this->line('  <fg=yellow>!</> Skipped .wirekit-schema.json — JSON encode failed.');

                return;
            }

            $beforeSnapshot = file_exists($targetPath) ? (string) file_get_contents($targetPath) : null;
            $this->trackInstallAction('write-schema-json', $targetPath, $beforeSnapshot);
            File::put($targetPath, $json);
            $this->line('  <fg=green>✓</> Wrote .wirekit-schema.json (IDE-extension / AI-tool feeder)');
        } catch (\Throwable $e) {
            // Non-fatal: schema-write is a DX enhancement, not a
            // runtime dependency. Log the cause and move on.
            $this->line('  <fg=yellow>!</> Skipped .wirekit-schema.json — '.$e->getMessage());
        }
    }

    /**
     * Resolve the package-relative Blade path for a component name.
     * Handles flat names + dotted sub-component shapes.
     */
    private function resolveBladePathForSchema(string $name): ?string
    {
        $base = __DIR__.'/../../resources/views/components/';
        $flat = $base.$name.'.blade.php';
        if (file_exists($flat)) {
            return $flat;
        }
        $dotted = $base.str_replace('.', '/', $name).'.blade.php';
        if (file_exists($dotted)) {
            return $dotted;
        }

        return null;
    }

    /**
     * Mirror of ExportJsonCommand::discoverSubComponents() — kept
     * inline here so the .wirekit-schema.json writer doesn't have to
     * spawn a sub-process. Same heuristic: scan the sibling
     * `resources/views/components/<name>/` directory, skip
     * `index.blade.php`, return sorted dot-separated qualified names.
     *
     * @return list<string>
     */
    private function discoverSubComponentsForSchema(string $name): array
    {
        $subDir = __DIR__.'/../../resources/views/components/'.$name;
        if (! is_dir($subDir)) {
            return [];
        }
        $subFiles = glob($subDir.'/*.blade.php') ?: [];
        $subs = [];
        foreach ($subFiles as $file) {
            $subName = basename($file, '.blade.php');
            if ($subName === 'index') {
                continue;
            }
            $subs[] = $name.'.'.$subName;
        }
        sort($subs);

        return $subs;
    }
}
