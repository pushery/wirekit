<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Icons\IconResolver;
use Pushery\WireKit\Icons\IconSourceLocator;
use Pushery\WireKit\Support\SuggestSimilar;

/**
 * Publish a specific icon-set's blade-icons SVG directory
 * to public/vendor/wirekit/icons/{preset}/.
 *
 * Usage:
 *   php artisan wirekit:publish-icons heroicons
 *   php artisan wirekit:publish-icons lucide
 *
 * Each preset wraps a separate composer package (e.g.
 * blade-ui-kit/blade-heroicons for heroicons). The command refuses if the
 * package is not installed and prints the exact composer require line.
 */
class PublishIconsCommand extends Command
{
    protected $signature = 'wirekit:publish-icons
        {preset : Icon preset key (heroicons / heroicons-app / heroicons-marketing / lucide / phosphor / tabler)}
        {--force : Overwrite existing files}';

    protected $description = 'Publish a specific icon preset SVG directory under public/vendor/wirekit/icons/{preset}/';

    /**
     * Map preset keys to their composer package.
     *
     * The package is written down; the SVG DIRECTORY is not, and that asymmetry is the
     * point. A path here was wrong twice over: it named `ryangjchandler/blade-tabler-icons`
     * (abandoned, and uninstallable against the Laravel versions this package supports),
     * and it named `resources/svg` for Lucide, which v2 emptied when it moved its icons one
     * level down. The second is the worse one — the directory still exists, so the guard
     * answered yes and the command reported a successful publish of nothing.
     *
     * @var array<string, string>
     */
    private const PRESET_PACKAGES = [
        'heroicons' => 'blade-ui-kit/blade-heroicons',
        'heroicons-app' => 'blade-ui-kit/blade-heroicons',
        'heroicons-marketing' => 'blade-ui-kit/blade-heroicons',
        'lucide' => 'mallardduck/blade-lucide-icons',
        'phosphor' => 'codeat3/blade-phosphor-icons',
        'tabler' => 'secondnetwork/blade-tabler-icons',
    ];

    public function handle(): int
    {
        $preset = $this->argument('preset');

        if (! in_array($preset, IconResolver::availablePresets(), true)) {
            $this->error("Unknown preset '{$preset}'.");
            $this->line('  Available: '.implode(', ', IconResolver::availablePresets()));

            $hint = SuggestSimilar::format(
                SuggestSimilar::byLevenshtein((string) $preset, IconResolver::availablePresets())
            );
            if ($hint !== null) {
                $this->line('  '.$hint);
            }

            return self::FAILURE;
        }

        $package = self::PRESET_PACKAGES[$preset] ?? null;
        if ($package === null) {
            $this->error("No publishable source registered for preset '{$preset}'.");

            return self::FAILURE;
        }

        // Located rather than assumed. An installed package whose SVGs moved is
        // indistinguishable from a healthy one to `is_dir`, and that is exactly how a
        // publish of an empty directory came to report success.
        $sourcePath = IconSourceLocator::locate(base_path('vendor/'.$package));

        if ($sourcePath === null) {
            $this->error("Composer package '{$package}' is not installed, or ships no SVGs.");
            $this->line('  Fix: composer require '.$package);

            return self::FAILURE;
        }

        $targetPath = public_path("vendor/wirekit/icons/{$preset}");

        if (is_dir($targetPath) && ! $this->option('force')) {
            $this->error("Target already exists at {$targetPath}.");
            $this->line('  Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        $this->copyDirectory($sourcePath, $targetPath);

        $this->info("Published '{$preset}' icons → {$targetPath}");

        return self::SUCCESS;
    }

    /**
     * Recursive directory copy without external deps.
     */
    private function copyDirectory(string $source, string $target): void
    {
        if (! is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destPath = $target.DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }
}
