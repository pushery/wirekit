<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console;

use Illuminate\Console\Command;
use Pushery\WireKit\Fonts\FontCss;
use Pushery\WireKit\Fonts\FontPreset;
use Pushery\WireKit\Fonts\FontRegistry;
use Pushery\WireKit\Support\DirectoryHash;
use Pushery\WireKit\Support\FileWrite;

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

    /**
     * The `fonts.*` config keys that named something `FontRegistry` does not carry.
     *
     * Collected rather than counted, because the message has to name the key: "one of your
     * three font keys is wrong" sends the developer to read all three.
     *
     * @var list<string>
     */
    private array $unresolvable = [];

    public function handle(): int
    {
        $targets = $this->option('all')
            ? FontRegistry::all()
            : $this->configuredPresets();

        if ($targets === []) {
            /*
             * TWO different states reach this branch, and it used to absorb both.
             *
             * Nothing configured is a legitimate setup — an application may be serving its own
             * faces — and the comment below rightly justifies exit 0 for it. A key that NAMES a
             * family which is not bundled is the opposite: it is the misconfiguration this
             * command exists to prevent, and "nothing published" is the failure, not the
             * answer. This file's own docblock describes that outcome — the files are missing
             * and the page silently falls back to system fonts.
             *
             * Merged, the run printed "config fonts.sans names 'x', which is not a bundled
             * family" and then "No font families are configured." — two statements that
             * contradict each other — and exited 0. A developer who reads the second goes
             * looking at an empty config that is not empty. And the reference page recommends
             * hanging this command off `composer post-autoload-dump`, where nobody reads either
             * line; there, the exit code is the whole message.
             */
            if ($this->unresolvable !== []) {
                $this->error(count($this->unresolvable) === 1
                    ? sprintf('%s names a family that is not bundled — nothing was published.', $this->unresolvable[0])
                    : sprintf('%s name families that are not bundled — nothing was published.', implode(' / ', $this->unresolvable)));
                $this->line('  Fix the key, or publish everything: php artisan wirekit:publish-fonts --all');

                return self::FAILURE;
            }

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

            $alreadyPublished = is_dir($target);

            // Skip ONLY when the published copy already mirrors the bundled bytes.
            // The old skip-if-dir-exists check silently did nothing after a
            // `composer update` left the vendor tree with new font bytes and
            // public/ with the previous release's — the app kept serving stale
            // fonts, and both this command and `wirekit:verify` reported success.
            // Now a byte drift overwrites (an upgrade heals itself); `--force`
            // still overwrites unconditionally.
            if ($alreadyPublished && ! $this->option('force') && DirectoryHash::matches($source, $target, FontCss::publishTransform())) {
                $this->line("  Skipped {$preset->key} — already up to date");
                $published[] = $relative;

                continue;
            }

            $this->copyDirectory($source, $target, FontCss::publishTransform());

            // Name what happened so an overwrite is visible — an upgrade refresh, or
            // the rare case where it replaces a hand-edited published font file
            // (which `--force` and `--prune` already cover for the deliberate workflow).
            $verb = ($alreadyPublished && ! $this->option('force')) ? 'Updated' : 'Published';
            $this->info("{$verb} {$preset->key} → public/vendor/wirekit/fonts/{$relative}");
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
                $this->unresolvable[] = "fonts.{$category}";
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

    /**
     * @param  callable(string, string): string  $transform
     */
    private function copyDirectory(string $source, string $target, callable $transform): void
    {
        FileWrite::ensureDirectory($target);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $destination = $target.DIRECTORY_SEPARATOR.$relative;

            if ($item->isDir()) {
                FileWrite::ensureDirectory($destination);

                continue;
            }

            // A stylesheet is rewritten on the way out rather than copied, so the
            // published file carries `wirekit.fonts.display` instead of the `swap`
            // the package ships. Everything else — the woff2 payloads — is copied
            // byte for byte.
            $contents = (string) file_get_contents($item->getPathname());
            FileWrite::put($destination, $transform($relative, $contents));
        }
    }

    private function deleteDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            FileWrite::delete($item->getPathname());
        }

        // The directory itself, after its contents. Unchecked, a prune that could not finish
        // left an empty published family behind while the command reported it pruned — and
        // the next run skips it, because the check above is `in_array($relative, $keep)`
        // rather than "is it still on disk".
        FileWrite::delete($dir);
    }
}
