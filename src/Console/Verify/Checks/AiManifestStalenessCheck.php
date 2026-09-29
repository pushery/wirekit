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
final class AiManifestStalenessCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkAiManifestStaleness();
    }

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
        $packageNewest = $this->packageNewestMtime();

        $manifests = [
            '.boost/wirekit.json' => 'php artisan wirekit:boost-skills --force',
            '.wirekit-schema.json' => 'php artisan wirekit:export-json --public --pretty > .wirekit-schema.json',
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
}
