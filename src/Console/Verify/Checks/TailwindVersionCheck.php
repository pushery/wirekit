<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\TailwindVersion;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class TailwindVersionCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkTailwindVersion();
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
}
