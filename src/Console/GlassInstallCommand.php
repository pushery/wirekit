<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Installs the WireKit Liquid Glass extension.
 *
 * Publishes CSS/JS assets and registers the glass Blade component.
 * After installation, add <x-wirekit::glass /> to your layout's <head>.
 */
class GlassInstallCommand extends Command
{
    protected $signature = 'wirekit:glass {action=install : The action to perform (install)}';

    protected $description = 'Install the WireKit Liquid Glass extension';

    public function handle(): int
    {
        $action = $this->argument('action');

        if ($action !== 'install') {
            $this->error("Unknown action: {$action}. Use 'install'.");

            return self::FAILURE;
        }

        $this->info('Installing WireKit Liquid Glass extension...');

        $sourcePath = __DIR__.'/../../resources/glass';
        $targetPath = public_path('vendor/wirekit/glass');

        if (! File::isDirectory($sourcePath)) {
            $this->error('Glass source files not found. Package may be corrupted.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($targetPath);
        File::copyDirectory($sourcePath, $targetPath);

        $this->info('  Published: public/vendor/wirekit/glass/wirekit-glass.css');
        $this->info('  Published: public/vendor/wirekit/glass/wirekit-glass.js');
        $this->newLine();
        $this->info('Add to your layout\'s <head>:');
        $this->line('  <x-wirekit::glass />');
        $this->newLine();
        $this->info('Usage in templates:');
        $this->line('  <div class="wk-glass">Frosted glass (all browsers)</div>');
        $this->line('  <div class="wk-glass-refract">Refraction glass (Chrome, frosted fallback)</div>');

        return self::SUCCESS;
    }
}
