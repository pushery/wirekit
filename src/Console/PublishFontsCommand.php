<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Fonts\FontPreset;
use Pushery\WireKit\Fonts\FontRegistry;

/**
 * Publish exactly the font families the app has configured.
 *
 * The all-or-nothing `wirekit-fonts` tag copies the whole tree — 5.8 MB, against
 * roughly 430 KB for a typical two-family setup. The per-preset tags added in
 * 2.17.0 fix the size but not the problem, because using them means hardcoding a
 * preset NAME:
 *
 *     php artisan vendor:publish --tag=wirekit-font-ibm-plex-sans
 *
 * That is safe for an app that never changes its type, and unsafe for a template
 * that is meant to: a clone which switches `fonts.sans` to another family
 * publishes nothing, the files are missing, and the page silently falls back to
 * system fonts — the exact failure the route fallback was built to end, only
 * reintroduced by the publish mechanism itself.
 *
 * This command reads the CONFIG instead of a name, so a clone changes one line
 * and the setup script keeps working:
 *
 *     php artisan wirekit:publish-fonts            # what fonts.* names
 *     php artisan wirekit:publish-fonts --all      # the whole tree
 *     php artisan wirekit:publish-fonts --prune    # …and remove families no longer configured
 */
class PublishFontsCommand extends Command
{
    protected $signature = 'wirekit:publish-fonts
        {--all : Publish every bundled family instead of only the configured ones}
        {--prune : Delete published families that the config no longer names}
        {--force : Overwrite files that already exist}';

    protected $description = 'Publish the font families named in config/wirekit.php (not the whole 5.8 MB tree)';

    public function handle(): int
    {
        $targets = $this->option('all')
            ? FontRegistry::all()
            : $this->configuredPresets();

        if ($targets === []) {
            $this->warn('No font families are configured.');
            $this->line('  config/wirekit.php → fonts.sans / fonts.serif / fonts.mono');
            $this->line('  Or publish everything: php artisan wirekit:publish-fonts --all');

            // Not a failure: "no bundled fonts" is a legitimate setup — an app may
            // be serving its own. Saying so beats exiting non-zero on a valid state.
            return self::SUCCESS;
        }

        $published = [];

        foreach ($targets as $preset) {
            $relative = dirname($preset->cssFile);
            $source = __DIR__.'/../../resources/fonts/'.$relative;
            $target = public_path('vendor/wirekit/fonts/'.$relative);

            if (! is_dir($source)) {
                $this->error("Bundled files missing for '{$preset->key}' (expected {$source}).");

                return self::FAILURE;
            }

            if (is_dir($target) && ! $this->option('force')) {
                $this->line("  Skipped {$preset->key} — already published (use --force to overwrite)");
                $published[] = $relative;

                continue;
            }

            $this->copyDirectory($source, $target);
            $this->info("Published {$preset->key} → public/vendor/wirekit/fonts/{$relative}");
            $published[] = $relative;
        }

        if ($this->option('prune')) {
            $this->prune($published);
        }

        return self::SUCCESS;
    }

    /**
     * The presets named by `fonts.sans` / `fonts.serif` / `fonts.mono`.
     *
     * A configured key that names no bundled preset is reported rather than
     * skipped: silently publishing nothing is how a page ends up in system fonts
     * with no one the wiser.
     *
     * @return list<FontPreset>
     */
    private function configuredPresets(): array
    {
        $presets = [];

        foreach (['sans', 'serif', 'mono'] as $category) {
            $key = config("wirekit.fonts.{$category}");

            if ($key === null || $key === '') {
                continue;
            }

            $preset = FontRegistry::get((string) $key);

            if ($preset === null) {
                $this->warn("config fonts.{$category} names '{$key}', which is not a bundled family — skipped.");
                $this->line('  Available: '.implode(', ', array_map(
                    static fn ($p) => $p->key,
                    FontRegistry::all()
                )));

                continue;
            }

            $presets[] = $preset;
        }

        return $presets;
    }

    /**
     * Remove published families the config no longer names.
     *
     * Switching a family otherwise leaves the old one in public/ forever — dead
     * weight nobody thinks to look for, and the reason a "slim" publish can end up
     * larger than the all-or-nothing one after a few changes.
     *
     * @param  list<string>  $keep  relative directories that must survive
     */
    private function prune(array $keep): void
    {
        $root = public_path('vendor/wirekit/fonts');

        if (! is_dir($root)) {
            return;
        }

        foreach (['sans', 'serif', 'mono'] as $category) {
            $categoryDir = $root.'/'.$category;

            if (! is_dir($categoryDir)) {
                continue;
            }

            foreach ((array) glob($categoryDir.'/*', GLOB_ONLYDIR) as $dir) {
                $relative = $category.'/'.basename((string) $dir);

                if (in_array($relative, $keep, true)) {
                    continue;
                }

                $this->deleteDirectory((string) $dir);
                $this->line("  Pruned {$relative} — no longer configured");
            }
        }
    }

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
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                if (! is_dir($destination)) {
                    mkdir($destination, 0755, true);
                }

                continue;
            }

            copy($item->getPathname(), $destination);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
