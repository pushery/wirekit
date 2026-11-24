<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\VersionResolver;

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
     * are written once and never refreshed on their own — `wirekit:boost-skills`
     * and `wirekit:install` both bail early when the file already exists unless
     * `--force` is passed. So after a `composer update` that adds components or
     * props, a committed manifest silently goes stale and keeps feeding an AI
     * tool the old API surface. Warn when a manifest was written for another
     * version than the one installed.
     *
     * Each manifest names the version it was written for, and that is what is
     * compared. Its modification time says nothing about its content where the
     * file is committed: a fresh checkout clones it and then installs the package,
     * so every package file is newer than every committed one. The age is read
     * only for a manifest that names no version.
     */
    private function checkAiManifestStaleness(): void
    {
        $installed = VersionResolver::resolve();
        $packageNewest = null;

        $manifests = [
            '.boost/wirekit.json' => 'php artisan wirekit:boost-skills --force',
            '.wirekit-schema.json' => 'php artisan wirekit:export-json --public --pretty > .wirekit-schema.json',
        ];

        foreach ($manifests as $relative => $refreshCmd) {
            $target = base_path($relative);
            if (! is_file($target)) {
                continue; // not generated — nothing to check
            }

            $written = $this->versionOf($target);

            if ($written !== null) {
                if (ltrim($written, 'vV') !== ltrim($installed, 'vV')) {
                    $this->reportWarn("Generated AI catalog {$relative} was written for WireKit {$written}, and {$installed} is installed");
                    $this->line('  An AI tool reading it sees the API surface of that version');
                    $this->line("  Fix: {$refreshCmd}");
                }

                continue;
            }

            // A manifest that names no version: when the package sources it is built from last
            // changed (the registry, the component views and the install) is all there is to go
            // on. One that predates them was generated against an older package.
            $packageNewest ??= $this->packageChangedAt();

            if (filemtime($target) < $packageNewest) {
                $this->reportWarn("Generated AI catalog {$relative} is older than the installed WireKit package");
                $this->line('  It was generated against an earlier version — an AI tool reading it sees a stale API surface');
                $this->line("  Fix: {$refreshCmd}");
            }
        }
    }

    /** The version a manifest names at its top level, or null where it names none. */
    private function versionOf(string $path): ?string
    {
        $content = is_readable($path) ? file_get_contents($path) : false;
        $manifest = is_string($content) ? json_decode($content, true) : null;
        $version = is_array($manifest) ? ($manifest['version'] ?? null) : null;

        return is_string($version) && trim($version) !== '' ? trim($version) : null;
    }
}
