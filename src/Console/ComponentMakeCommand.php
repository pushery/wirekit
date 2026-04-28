<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Scaffold a custom component derived from a WireKit base.
 *
 * Copies the base component's Blade file to
 * `resources/views/components/custom/{name}.blade.php` so the consumer can
 * override classes, variants, slots without publishing the whole package
 * vendor:publish --tag=wirekit-views (which would copy ~109 files).
 *
 * Usage:
 *   php artisan wirekit:component button
 *     -> resources/views/components/custom/button.blade.php (copy of base)
 *
 *   php artisan wirekit:component my-button --base=button
 *     -> resources/views/components/custom/my-button.blade.php (copy of button)
 *
 *   php artisan wirekit:component button --force
 *     -> overwrite existing file (safe-by-default refuses without --force)
 */
class ComponentMakeCommand extends Command
{
    protected $signature = 'wirekit:component
        {name : Component slug for the new file (kebab-case)}
        {--base= : Source component to copy from (defaults to {name})}
        {--force : Overwrite an existing custom component}';

    protected $description = 'Scaffold a custom Blade component derived from a WireKit base';

    public function handle(): int
    {
        $name = $this->argument('name');
        $base = $this->option('base') ?? $name;

        if (! preg_match('/^[a-z][a-z0-9-]*$/', $name)) {
            $this->error("Invalid component name '{$name}'. Use kebab-case (e.g. 'my-button').");

            return self::FAILURE;
        }

        // Resolve base component to its package source path.
        // The Blade file is the source of truth — sub-components like
        // `card.header` exist on disk but not in ComponentRegistry's
        // top-level list, so we look up the file directly.
        $baseSourcePath = $this->resolveBladeSource($base);
        if ($baseSourcePath === null || ! file_exists($baseSourcePath)) {
            $this->error("Unknown base component '{$base}'.");
            $this->line('  Run `php artisan wirekit:list` to see all top-level components.');
            $this->line("  Sub-components use dotted names (e.g. 'card.header').");

            return self::FAILURE;
        }

        $targetPath = resource_path("views/components/custom/{$name}.blade.php");

        if (file_exists($targetPath) && ! $this->option('force')) {
            $this->error("Component already exists at {$targetPath}.");
            $this->line('  Re-run with --force to overwrite.');

            return self::FAILURE;
        }

        File::ensureDirectoryExists(dirname($targetPath));
        File::copy($baseSourcePath, $targetPath);

        $this->info("Scaffolded custom component → {$targetPath}");
        $this->line('  Source: '.str_replace(base_path().'/', '', $baseSourcePath));
        $this->line('');
        $this->line('  Usage:');
        $this->line('    <x-custom::'.$name.' ... />');
        $this->line('');
        $this->line('  To register a personalization scope instead of copying the file,');
        $this->line('  see https://docs.wirekit.app/customization');

        return self::SUCCESS;
    }

    /**
     * Resolve a component name to its Blade source path inside the package.
     * Handles both flat (`button.blade.php`) and dotted
     * (`card.header` -> `card/header.blade.php`) forms.
     */
    private function resolveBladeSource(string $name): ?string
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
}
