<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Icons\IconResolver;

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
     * Map preset keys to their composer-package SVG source roots.
     *
     * @var array<string, array{package: string, source: string}>
     */
    private const PRESET_PACKAGES = [
        'heroicons' => [
            'package' => 'blade-ui-kit/blade-heroicons',
            'source' => 'vendor/blade-ui-kit/blade-heroicons/resources/svg',
        ],
        'heroicons-app' => [
            'package' => 'blade-ui-kit/blade-heroicons',
            'source' => 'vendor/blade-ui-kit/blade-heroicons/resources/svg',
        ],
        'heroicons-marketing' => [
            'package' => 'blade-ui-kit/blade-heroicons',
            'source' => 'vendor/blade-ui-kit/blade-heroicons/resources/svg',
        ],
        'lucide' => [
            'package' => 'mallardduck/blade-lucide-icons',
            'source' => 'vendor/mallardduck/blade-lucide-icons/resources/svg',
        ],
        'phosphor' => [
            'package' => 'codeat3/blade-phosphor-icons',
            'source' => 'vendor/codeat3/blade-phosphor-icons/resources/svg',
        ],
        'tabler' => [
            'package' => 'ryangjchandler/blade-tabler-icons',
            'source' => 'vendor/ryangjchandler/blade-tabler-icons/resources/svg',
        ],
    ];

    public function handle(): int
    {
        $preset = $this->argument('preset');

        if (! in_array($preset, IconResolver::availablePresets(), true)) {
            $this->error("Unknown preset '{$preset}'.");
            $this->line('  Available: '.implode(', ', IconResolver::availablePresets()));

            return self::FAILURE;
        }

        $config = self::PRESET_PACKAGES[$preset] ?? null;
        if ($config === null) {
            $this->error("No publishable source registered for preset '{$preset}'.");

            return self::FAILURE;
        }

        $sourcePath = base_path($config['source']);
        if (! is_dir($sourcePath)) {
            $this->error("Composer package '{$config['package']}' is not installed.");
            $this->line('  Fix: composer require '.$config['package']);

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
