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
    protected $signature = 'wirekit:glass
        {action=install : The action to perform (install)}
        {--force : Overwrite a published file that has been edited since it was published}
        {--strict : Treat a refusal as a failure (exit 1). Off by default, because a refusal is a deliberate no-op and this command runs in composer post-install-cmd, where a non-zero exit aborts the whole install.}';

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

        /*
         * An EDITED published file is not overwritten without `--force`.
         *
         * ⚠️ `liquid-glass.md` tells the developer, in as many words, that they can edit the
         * published CSS directly to adjust blur, saturation, opacity and border. So the file
         * this command overwrites is one the documentation invited them to change — and
         * `File::copyDirectory()` did it silently, on a command they would plausibly re-run
         * after upgrading the package. The customization is simply gone, with no output
         * difference from a first install.
         *
         * "Edited" is decided by CONTENT, not by mtime: a `vendor:publish`, a deploy step or
         * a checkout all rewrite the timestamp of a file nobody touched, and a warning that
         * fires on those is one a reader learns to pass with `--force` every time.
         */
        $modified = [];

        foreach ((array) File::files($sourcePath) as $sourceFile) {
            $published = $targetPath.'/'.$sourceFile->getFilename();

            if (! File::exists($published)) {
                continue;
            }

            if (File::get($published) !== File::get($sourceFile->getPathname())) {
                $modified[] = $sourceFile->getFilename();
            }
        }

        if ($modified !== [] && ! $this->option('force')) {
            $this->warn('Skipped published glass file(s) that differ from the package copy:');

            foreach ($modified as $filename) {
                $this->line('  '.$filename);
            }

            $this->newLine();
            $this->line('These may carry your own adjustments — the docs describe editing them directly.');
            $this->line('Re-run with --force to replace them, or move your changes into your own stylesheet first.');

            /*
             * ⚠️ SUCCESS, AND THE EXIT CODE IS THE WHOLE DEFECT THIS BRANCH ONCE HAD.
             *
             * The refusal itself is right: these files may carry the developer's own edits,
             * and the docs invite exactly that. What was wrong is calling a deliberate no-op
             * a failure. The documented home for this command is composer's
             * `post-install-cmd`, and composer aborts the ENTIRE `composer install` on any
             * non-zero exit from a script there — so a warning about two files became a
             * deployment outage.
             *
             * Measured in WireKit-Docs on 2026-09-08: every deploy plus FOUR consecutive
             * develop gates died here (2068, 2070, 2072, 2074). And the failure took its own
             * witness with it — CI dies in the `deps` step that runs `composer install`, so
             * everything after it is skipped, including the test that hashes these very files
             * against the package copy. That test was red the whole time and could never say so.
             *
             * ⚠️ AND THE TRIGGER IS THE ORDINARY CASE, NOT THE ONE THE REFUSAL IS FOR. A
             * developer checks the published files in so a fresh clone renders; the next build
             * that moves glass makes the checked-in copy simply the OLDER published version.
             * Measured in that incident: zero lines existed only in the adopting application's copy — it
             * was a strict subset of ours. Nobody had edited anything.
             *
             * `--strict` keeps the old exit for anyone who wants CI to notice, so the
             * capability is offered rather than removed.
             */
            return $this->option('strict') ? self::FAILURE : self::SUCCESS;
        }

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
