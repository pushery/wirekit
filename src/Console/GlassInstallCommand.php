<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Installs the WireKit Liquid Glass extension.
 *
 * Publishes CSS/JS assets and registers the glass Blade component.
 * After installation, add <x-wirekit::glass /> at the start of the layout body.
 *
 * Placement is not cosmetic. The component emits an <svg> holding the refraction filter,
 * and the HTML parser has no "in head" insertion mode for SVG: it terminates that section
 * and switches to the body. Every metadata tag after it — a canonical link, the Open Graph
 * block, a layout's @stack('meta') — is reparented into <body>, where a crawler does not
 * look. The page still renders, which is why this went unnoticed.
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
        // The BODY, and as its first element — never the head. This component emits an
        // <svg>, and an SVG in the head ends head parsing: every metadata tag after it is
        // reparented into the body, where a crawler does not look. The command is the last
        // place that still said `<head>`, and it is the one a developer reads while doing
        // the install rather than afterwards.
        $this->info('Add as the FIRST element of your layout\'s <body> — not the <head>:');
        $this->line('  <body>');
        $this->line('      <x-wirekit::glass />');
        $this->line('      …');
        $this->line('  </body>');
        $this->newLine();
        $this->line('  It emits an <svg>. An SVG in the <head> ends head parsing, so every');
        $this->line('  metadata tag after it lands in the body where a crawler will not read it.');
        $this->newLine();
        $this->info('Usage in templates:');
        $this->line('  <div class="wk-glass">Frosted glass (all browsers)</div>');
        $this->line('  <div class="wk-glass-refract">Refraction glass (Chrome, frosted fallback)</div>');

        return self::SUCCESS;
    }
}
