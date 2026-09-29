<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Illuminate\Support\Facades\File;
use Pushery\WireKit\ComponentRegistry;
use Pushery\WireKit\Console\Verify\VerifyCheck;
use Pushery\WireKit\Support\AppCss;
use Pushery\WireKit\Support\BladeParser;
use Pushery\WireKit\Support\SuggestSimilar;
use Pushery\WireKit\Support\TailwindSources;
use Pushery\WireKit\WireKit;

/**
 * One check `wirekit:verify` runs, in its package tier. The command decides the order and
 * prints the totals; this class decides what it reports.
 *
 * @internal
 */
final class TailwindSourceCheck extends VerifyCheck
{
    public function run(): void
    {
        $this->checkTailwindSource();
    }

    /**
     * Check that resources/css/app.css has a @source directive scanning WireKit Blade templates.
     * Without this, Tailwind v4 won't generate utility classes used by WireKit components.
     */
    private function checkTailwindSource(): void
    {
        $cssFiles = glob(resource_path('css/*.css')) ?: [];
        $hasSource = false;

        // The narrow form: one `@source` per component, pointing at `resources/tailwind/<name>.txt`.
        $declared = [];
        $unresolved = [];

        foreach ($cssFiles as $file) {
            // `@` plus an explicit false check. Under strict_types a `false` here is a
            // fatal TypeError in str_contains(), and the unsuppressed warning is promoted
            // to an ErrorException before that — so this command, the one a developer runs
            // BECAUSE the install is broken, died on the broken install.
            $content = @file_get_contents($file);

            if ($content === false) {
                continue;
            }

            // The DIRECTIVE, not two words that happen to share a file.
            //
            // Two words tested independently anywhere in the file are satisfied by ordinary
            // shapes that configure nothing: a comment mentioning WireKit beside an unrelated
            // `@source` line, a commented-out directive left behind after a migration, and an
            // exclusion such as `@source not '../../vendor/pushery/wirekit/tests/**';`, which
            // contains both words while telling Tailwind to skip a WireKit path. Each would
            // report a pass over the most common integration failure: components rendering
            // unstyled because Tailwind never scanned the package views.
            //
            // Comments are stripped first because a directive inside `/* … */` is text, not
            // configuration, and `not` is excluded because an exclusion is the opposite of
            // the thing being looked for. `views` anchors it to the templates the utilities
            // are generated from, which is what the PASS line claims — a directive naming
            // some other part of the package (`…/wirekit/dist/**`) generates none of them.
            //
            // `views`, not `resources/views`, and the difference is a whole class of
            // correct installs. A Tailwind `@source` path is relative to the CSS file it
            // sits in, so from `resources/css/app.css` the two canonical spellings are
            // `../../vendor/pushery/wirekit/resources/views/**` and — once the views are
            // published — `../views/vendor/wirekit/**`. The second one never contains the
            // longer literal, so requiring it would tell a correctly configured developer
            // their integration is missing.
            //
            // The two halves are checked inside ONE directive rather than across the file,
            // and in either order: the published-views form says the same thing backwards.
            //
            // And the comment strip is string-aware, because a Tailwind glob is comment
            // syntax. `preg_replace('~/\*.*?\*/~s', …)` over this file is not a comment
            // strip, it is a hazard: `views/**/*.blade.php` contains a complete empty
            // comment pair, and `views/*.blade.php` contains an UNPAIRED opener. Put an
            // ordinary Laravel pagination `@source` above the WireKit one and everything
            // between them is swallowed, including the WireKit path.
            //
            // That is the very sentence this comment warns about two paragraphs up, pointed
            // the other way, and it shipped anyway — because the hardening was written
            // against CSS-as-text and the argument of a `@source` is a QUOTED STRING.
            // Skipping comment openers inside quotes is the whole fix, and it keeps what
            // the strip was for: a directive commented out during a migration is still text.
            foreach (AppCss::sourceArguments($content) as $argument) {
                $lower = strtolower($argument);

                if (str_contains($lower, 'wirekit') && str_contains($lower, 'views')) {
                    $hasSource = true;

                    break 2;
                }

                // A per-component source. The path is relative to the stylesheet it sits in, as
                // Tailwind reads it, and a path that resolves to nothing is recorded: Tailwind
                // skips it without a word, and the component it names renders unstyled.
                if (preg_match('~'.preg_quote(TailwindSources::DIRECTORY, '~').'/([a-z0-9-]+)\.txt~', $lower, $named) === 1) {
                    $path = trim($argument, " \t\n\r'\"");
                    $resolved = realpath(str_starts_with($path, '/') ? $path : dirname($file).'/'.$path);

                    // Ours when it resolves inside this package's sources, whatever the vendor
                    // directory is called; a path that resolves nowhere is ours when it says so.
                    if ($resolved !== false && str_starts_with($resolved, $this->tailwindSourcesDirectory().'/')) {
                        $declared[$named[1]] = true;
                    } elseif ($resolved === false && str_contains($lower, 'wirekit')) {
                        $unresolved[] = $path;
                    }
                }
            }
        }

        if ($hasSource) {
            $this->reportPass('Tailwind @source includes WireKit templates');

            return;
        }

        if ($declared !== [] || $unresolved !== []) {
            $this->checkPerComponentSources(array_keys($declared), $unresolved);

            return;
        }

        $this->reportFail('Missing @source for WireKit in Tailwind CSS');
        $this->line('  Fix: Add to resources/css/app.css:');
        $this->line('  @source "../../vendor/pushery/wirekit/resources/views/**/*.blade.php";');
    }

    /**
     * The directory of this package's per-component Tailwind sources, resolved.
     */
    private function tailwindSourcesDirectory(): string
    {
        $directory = dirname(__DIR__, 4).'/'.TailwindSources::DIRECTORY;

        return realpath($directory) ?: $directory;
    }

    /**
     * Per-component sources: every one resolves, and together they cover every component the
     * application's views render.
     *
     * The narrow form is only as good as the list, and a list goes stale the first time a view
     * starts using a component nobody added: that component's utilities are simply missing, and
     * nothing fails. So the views are read and held against what the declared sources cover.
     * A source covers its whole closure, so a component rendered inside a declared one (a button
     * in a modal) is covered without a line of its own.
     *
     * @param  list<string>  $declared  component names whose source resolved
     * @param  list<string>  $unresolved  `@source` paths that resolve to no file
     */
    private function checkPerComponentSources(array $declared, array $unresolved): void
    {
        foreach ($unresolved as $path) {
            $this->reportFail(sprintf('Tailwind @source names a WireKit source that does not exist: %s', $path));

            $name = basename($path, '.txt');
            $suggestions = SuggestSimilar::byLevenshtein($name, array_keys(ComponentRegistry::all()));

            $this->line($suggestions === []
                ? '  No WireKit component has that name. Check the path, relative to the stylesheet it sits in.'
                : '  Did you mean '.implode(' or ', array_map(fn (string $s): string => "`{$s}`", $suggestions)).'?');
        }

        $covered = [];

        foreach ($declared as $name) {
            foreach (TailwindSources::templatesOf($name) as $template) {
                $covered[$template] = true;
            }
        }

        $used = [];

        foreach (File::allFiles(resource_path('views')) as $view) {
            if (! str_ends_with($view->getFilename(), '.blade.php')) {
                continue;
            }

            foreach (BladeParser::extractWireKitComponentUsagesFromSource($view->getContents()) as $usage) {
                $component = explode('.', $usage['name'])[0];

                if (ComponentRegistry::get($component) !== null) {
                    $used[$component] = true;
                }
            }
        }

        $missing = [];

        foreach (array_keys($used) as $component) {
            $templates = TailwindSources::templatesOf($component);

            if ($templates !== [] && array_diff($templates, array_keys($covered)) !== []) {
                $missing[] = $component;
            }
        }

        sort($missing);

        if ($missing !== []) {
            $this->reportFail(sprintf(
                'Tailwind @source misses %d WireKit component(s) your views render: %s',
                count($missing),
                implode(', ', $missing),
            ));
            $this->line('  Their utilities are not built, so they render unstyled. Fix: add to your stylesheet:');

            foreach ($missing as $component) {
                $this->line(sprintf('  @source "../../vendor/pushery/wirekit/%s/%s.txt";', TailwindSources::DIRECTORY, $component));
            }

            return;
        }

        if ($unresolved === []) {
            $this->reportPass(sprintf(
                'Tailwind @source covers the %d WireKit component(s) your views render, through %d per-component source(s)',
                count($used),
                count($declared),
            ));
        }
    }
}
