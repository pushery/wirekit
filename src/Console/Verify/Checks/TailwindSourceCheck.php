<?php

declare(strict_types=1);

namespace Pushery\WireKit\Console\Verify\Checks;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
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
     * nothing fails. So the views are read and held against what the declared sources cover:
     * the application's, and those of every package that registered views of its own, since a
     * package's view renders on the application's pages too. A source covers its whole closure,
     * so a component rendered inside a declared one (a button in a modal) is covered without a
     * line of its own. A view that calls `WireKit::spinePadding()` counts as using `spine-aware`
     * unless a declared source carries the runtime class list, which spells what the helper
     * returns.
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

        // Each component with the views that render it: the application's, or a package's by its
        // directory.
        $used = [];
        // The views that take an inline padding from `WireKit::spinePadding()`. The helper returns
        // its classes in PHP, so they are built only from the runtime class list.
        $spinePadding = [];

        foreach ($this->viewDirectories() as [$whose, $directory]) {
            foreach (File::allFiles($directory) as $view) {
                if (! str_ends_with($view->getFilename(), '.blade.php')) {
                    continue;
                }

                $contents = $view->getContents();

                foreach (BladeParser::extractWireKitComponentUsagesFromSource($contents) as $usage) {
                    $component = explode('.', $usage['name'])[0];

                    if (ComponentRegistry::get($component) !== null) {
                        $used[$component][$whose] = true;
                    }
                }

                if (preg_match('/\bspinePadding\s*\(/', $contents) === 1) {
                    $spinePadding[$whose] = true;
                }
            }
        }

        $missing = [];

        foreach ($used as $component => $whose) {
            $templates = TailwindSources::templatesOf($component);

            if ($templates !== [] && array_diff($templates, array_keys($covered)) !== []) {
                $missing[$component] = array_map('strval', array_keys($whose));
            }
        }

        // A view calling the helper needs a declared source that carries the runtime class list.
        // The source of `spine-aware`, whose padding the helper writes, carries it, so that is the
        // line the report names.
        if ($spinePadding !== [] && ! isset($covered[TailwindSources::runtimeClassList()]) && ! isset($missing['spine-aware'])) {
            $missing['spine-aware'] = array_map('strval', array_keys($spinePadding));
        }

        ksort($missing);

        if ($missing !== []) {
            // A component only a package renders says which package, since nothing in the
            // application's own views would lead a developer to it.
            $named = [];

            foreach ($missing as $component => $whose) {
                $packages = array_values(array_filter($whose, static fn (string $w): bool => $w !== ''));
                $named[] = in_array('', $whose, true) || $packages === []
                    ? $component
                    : sprintf('%s (in %s)', $component, implode(', ', array_map(static fn (string $n): string => "`{$n}`", $packages)));
            }

            $this->reportFail(sprintf(
                'Tailwind @source misses %d WireKit component(s) your views render: %s',
                count($missing),
                implode(', ', $named),
            ));
            $this->line('  Their utilities are not built, so they render unstyled. Fix: add to your stylesheet:');

            foreach (array_keys($missing) as $component) {
                $this->line(sprintf('  @source "../../vendor/pushery/wirekit/%s/%s.txt";', TailwindSources::DIRECTORY, $component));
            }

            return;
        }

        if ($unresolved === []) {
            $packages = array_unique(array_filter(array_map('strval', array_merge([], ...array_map('array_keys', array_values($used)))), static fn (string $w): bool => $w !== ''));

            $this->reportPass(sprintf(
                'Tailwind @source covers the %d WireKit component(s) your views render, through %d per-component source(s)%s',
                count($used),
                count($declared),
                $packages === [] ? '' : sprintf(', the views of %d package director%s included', count($packages), count($packages) === 1 ? 'y' : 'ies'),
            ));
        }
    }

    /**
     * The view directories whose templates render on the application's pages, each with whose
     * they are: `''` for the application's own, and for every directory a package registered
     * (`loadViewsFrom()`, an anonymous component path) the directory, relative to the base path.
     * A path rather than the namespace, because an anonymous component path is registered under
     * a hash of its directory, which names no package.
     *
     * WireKit's own views are left out by their directory, whatever namespace holds them: a
     * component they render is in the closure of the one that renders it, which its source
     * covers already. A directory inside the application's views, a published package's for
     * one, is read with those. `getHints()` is on `FileViewFinder` and not on the finder
     * interface, so an application with a finder of its own is read for its own views.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function viewDirectories(): array
    {
        $application = realpath(resource_path('views')) ?: resource_path('views');
        $own = realpath(dirname(__DIR__, 4).'/resources/views') ?: dirname(__DIR__, 4).'/resources/views';
        $base = rtrim(base_path(), '/').'/';
        $directories = [['', $application]];
        $finder = View::getFinder();

        if (! method_exists($finder, 'getHints')) {
            return $directories;
        }

        /** @var array<string, array<int, string>|string> $hints */
        $hints = $finder->getHints();
        $seen = [];

        foreach ($hints as $paths) {
            foreach ((array) $paths as $path) {
                $directory = realpath($path);

                if ($directory === false
                    || ! is_dir($directory)
                    || isset($seen[$directory])
                    || str_starts_with($directory.'/', $application.'/')
                    || str_starts_with($directory.'/', $own.'/')) {
                    continue;
                }

                $seen[$directory] = true;
                $directories[] = [str_starts_with($directory, $base) ? substr($directory, strlen($base)) : $directory, $directory];
            }
        }

        return $directories;
    }
}
