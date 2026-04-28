<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * copy the package's `.cursor/rules/wirekit.mdc` file
 * into the consumer's `.cursor/rules/` directory so AI tooling running
 * inside the consumer's project picks up WireKit's authoring conventions.
 *
 * Usage:
 *   php artisan wirekit:cursor-rules
 *   php artisan wirekit:cursor-rules --force
 *
 * The `.mdc` format is Cursor's native rules format — globs in the
 * frontmatter scope the rules to specific filetypes (Blade / CSS).
 */
class CursorRulesCommand extends Command
{
    protected $signature = 'wirekit:cursor-rules
        {--force : Overwrite an existing wirekit.mdc}';

    protected $description = 'Publish the WireKit Cursor rules file to .cursor/rules/wirekit.mdc';

    public function handle(): int
    {
        $sourcePath = realpath(__DIR__.'/../../.cursor/rules/wirekit.mdc');
        if ($sourcePath === false) {
            $this->error('Package source file .cursor/rules/wirekit.mdc not found.');

            return self::FAILURE;
        }

        $targetDir = base_path('.cursor/rules');
        $targetPath = $targetDir.'/wirekit.mdc';

        if (file_exists($targetPath) && ! $this->option('force')) {
            $this->error("File already exists at {$targetPath}.");
            $this->line('  Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists($targetDir);
        File::copy($sourcePath, $targetPath);

        $this->info("Published Cursor rules → {$targetPath}");
        $this->line('  Cursor + Codeium will pick up these rules automatically');
        $this->line('  for any *.blade.php / *.css file in this project.');

        return self::SUCCESS;
    }
}
